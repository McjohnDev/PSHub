# Screens v2 → what gets built, when, and what is missing

The ten screens in `screens-v2/`, each mapped to a route, a sprint, and the data it needs.
Where a screen shows something the platform cannot truthfully say yet, that is called out
rather than mocked — the rule this project works to is that nothing on a page a learner
pays from may be invented.

## The map

| # | Screen | Route | Sprint | State |
|---|---|---|---|---|
| 02 | Public home | `/local/pshub/index.php` | **S3** | Built. Needs the v2 refresh: nav logo block, hero session mock, card style |
| 08 | Login | `/login/index.php` | **S3** | Built to the split layout and verified at 1280 and 390 |
| 09 | Signup | `/login/signup.php` | **S3** | Built: brand panel, styled mform, phone field captured into the platform row. ⚠️ decision 4 (email confirmation) still open |
| 01 / 06 | Learner catalogue, in the app shell | `/local/pshub/catalogue.php` | S4 | Shell exists; the catalogue still renders in the public shell. Next step is to show it inside `pslearner` for a signed-in learner |
| 07 | Learner progress dashboard | `/local/pshub/learn/index.php` | S4 | **Built and verified.** Three tiles, not four: the quiz average and certificate count have no data behind them. No progress bars: progress is sessions attended over sessions scheduled, and sessions land in S5 |
| 05 | Learner course detail with tabs | `/local/pshub/learn/course.php` | S3 | ✅ Built. Tabs are anchors generated from the activities the course actually holds - no tab onto an empty section. `/course/view.php` redirects here for non-staff on published hub courses. **Left out until the data exists**: the rating out of five (nothing records reviews), the teacher line (no role assignment yet - the page says so), the progress bar (no sessions yet), and the play button and audio-only note (no BBB server, `PSH-S01-02`) |
| 04 | Teacher overview | `/local/pshub/teacher/index.php` | S3 | ✅ Overview built. Three tiles, courses with review status and learner counts, sessions, the admin's brief. **Left out**: the average rating tile and column - nothing records a review |
| 03 | Admin overview | `/local/pshub/admin/index.php` | S3 | ✅ Overview built. Figures, review queue oldest-first, active types. **Left out**: revenue and the payments queue (no transaction exists), and Approve/Reject (the audited transition is `PSH-S04-05`) |

The tables behind most of this **already exist**: `local_pshub_session`, `local_pshub_xp_rule`
and `_xp_ledger`, `local_pshub_teacher_assign`, `local_pshub_transaction`,
`local_pshub_access`, `local_pshub_attendance`. The screens are ahead of the UI, not ahead
of the schema.

## Four things the screens assume that the platform does not have

These need your decision, not code.

### 1. Prices are shown **per month** — the model prices a course once ⚠️

Every card says `25 000 FCFA / mois`. `local_pshub_course.price_amount` is a single price for
the course, and `price_resolver` and the paywall are built on that. Monthly pricing is a
different product: it means subscriptions, renewals, failed-renewal handling, proration and
a different refund policy, and it changes what "packs" mean.

**This has to be settled before S7 (checkout).** If the answer is monthly, the change is
cheap now and expensive later.

### 2. Ratings and review counts do not exist anywhere ⚠️

`4,8 (320 avis)` appears on every course card and on the course page. There is no rating
table, no review capture, no moderation. A rating needs: who may leave one (learners who
attended?), when, whether a teacher can reply, and what stops a course launching with a
fake 4.8.

Until that exists the cards will simply not show a rating. **They will not show an invented
one.**

### 3. Login accepts an **e-mail or a phone number** ⚠️

Moodle authenticates against a username (and optionally e-mail). It has no concept of
signing in with `6XX XX XX XX`. Supporting it means either a small custom auth plugin or a
pre-login lookup that maps a phone number to a username. Neither is hard; both are work, and
phone-first login matters in this market.

For now the field keeps Moodle's own "username or e-mail" behaviour.

### 4. "Se souvenir de moi" is not a checkbox Moodle renders

Moodle has `$CFG->rememberusername` (never / always / let the user choose), but its login
form template has no checkbox for it — it is a site setting, not a form control. Adding one
means overriding `core/loginform`, which is the one template this theme deliberately does
not touch, because it carries the CSRF token, password policy and lockout handling.

Recommendation: set the site to "remember" and drop the checkbox.

## Smaller gaps, cheap to close

| Screen shows | Reality | Fix |
|---|---|---|
| "Débutant / Intermédiaire / Avancé" | We store CEFR (`A1`…`C2`) | Map CEFR → plain label in the presenter |
| "Un cours de Mme Ngo Bell" | `local_pshub_teacher_assign` exists | Join it in `catalogue_service::present()` |
| "100 % live" ribbon on every card | True of every course by definition | Static ribbon, no data needed |
| "12 apprenants par groupe max" | Not stored | Add a column, or drop the line |

## Note on the mockups themselves

Several screens show clipped text — `25 000 FCFA` cut mid-word, "Ma progression" overlapping
"11/20 sessions", "Mes cours" overlapping the column header. Those are artefacts of the
design tool's fixed-height boxes, not instructions. The build uses flow layout, so text
wraps instead of being cut.
