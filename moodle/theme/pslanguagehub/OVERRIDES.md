# Override register

Spec §12.1 requires a list of every core template this theme overrides, with a reason.
At the next Moodle major this list **is the upgrade work plan**; without it the work is
archaeology.

Keep it short. Every entry is a file that must be re-reconciled against core whenever
Boost changes it.

| Core template | Why | Risk at upgrade |
|---|---|---|
| `core/login_layout` | Boost splits the login page into a brand column and a form column, and marks the brand half **`d-none d-lg-flex`** — hidden below 992px. On a mobile-first product for a mobile-first market, that meant most visitors saw a bare white form with no brand on it at all. Replaced with a card that crosses the seam between the navy brand ground and the white page: the promise beside it on desktop, above it on a phone, the same composition at 360px and 1920px. The overlap is one negative margin on the card, not a stack of positioned elements. | **Medium.** It is a layout template, so it must be re-read against core whenever Boost restructures the login page. Kept small and free of Bootstrap grid classes to limit the exposure. **It must keep the `.login-container` wrapper** — Boost styles the login fields through `.login-container .login-form` (icon inside the field, the 3rem inset that clears it, the reveal button's colours). An earlier version dropped that class, and the icon then sat on top of the placeholder. |

`core/login_panel` was overridden earlier and has been **removed**: the new layout does not
include it, and a dead override is worse than none.

## Files this theme adds (not overrides)

These replace nothing in core, so they carry no upgrade reconciliation risk — but they are
listed because they are the public site's chrome and someone will look for them.

| File | What it is |
|---|---|
| `layout/pspublic.php` + `templates/pspublic.mustache` | The public shell: navigation bar, page, footer, and the discreet staff-login link (cahier §5.1). Registered as the `pspublic` layout in `config.php`; core cascades it on top of Boost's layouts, so every other page is untouched. The public pages ask for it with `$PAGE->set_pagelayout('pspublic')`. **It exists because AC-17 forbids Moodle chrome on a learner journey**, and every Boost layout is Moodle chrome. |
| `templates/brandmark.mustache` | Two letters on the signature gradient, for the nav and footer. The full logo is kept for the login page, where it is big enough to read. |
| `scss/public.scss` | Styles for the public site only. Separate from `post.scss` because the two age differently: `post.scss` styles Moodle's own pages and must be re-checked when Boost changes, `public.scss` changes only when the product does. |

## Deliberately NOT overridden

| Core template | Why it is left alone |
|---|---|
| `core/loginform` | Carries the CSRF `logintoken`, password policy, account-lockout messaging, error rendering and accessibility. None of that is worth reimplementing for a visual change, and reimplementing it is how login pages grow security holes. Styled entirely from `scss/post.scss`. |
| Every layout in `layout/` | Inherited from Boost via `$THEME->parents`. The theme defines no layouts of its own. |

## Not a template, but required

`config.php` must set `$THEME->rendererfactory = 'theme_overridden_renderer_factory'`.
It is **not** inherited from a parent theme. Without it Moodle builds only core renderers,
Boost's own renderer methods disappear, and every page using Boost's `drawers` layout — the
dashboard, all of the admin — dies on
`Call to undefined method core\output\core_renderer::firstview_fakeblocks()`.
The login page uses a different layout and so looks perfectly healthy while the rest of the
site is broken.

## A note on course images

Course images on public pages are served by `local/pshub/image.php`, not Moodle's
`pluginfile.php`. `pluginfile.php` honours `$CFG->forcelogin`, which this site sets, so an
anonymous visitor's browser is redirected to the login page instead of receiving the image —
pictures on a public page that only members can see. The plugin endpoint applies the
catalogue's own rule instead: published is public, unpublished is 404.

## Rules

1. **Prefer a variable to an override.** Anything Bootstrap can be told via `scss/pre.scss`
   is done there, so Bootstrap generates brand-coloured components rather than having them
   repainted afterwards. That is why there is not one `!important` in this theme.
2. **Prefer CSS to a template.** A rule in `post.scss` survives an upgrade; a copied
   Mustache file does not.
3. **Adding a row to the table above is a decision, not a detail.** Moodle 5.3 refreshed
   the theme layer and this project is building on it early — every override is exposure to
   that (spec §12.1, "the 5.3 theme risk").
