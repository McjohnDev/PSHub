# Development environment

**Created:** 13 September 2026 · **Host:** Windows 11 + Laragon

Everything here was established by building it, not by reading documentation. Several
points contradict the specification, which was written before a Moodle existed to check
against — those are called out.

---

## 1. What is installed

| Item | Value |
|---|---|
| Moodle | **5.3dev (Build: 20260911)**, `$branch = 503`, `$maturity = MATURITY_ALPHA` |
| Pinned commit | **`48ea8c33227277e916cdd4079dbecef448b37f2c`** (`main`, "weekly release 5.3dev", 2026-09-11) |
| Install path | `C:\laragon\www\pshub-moodle` |
| Document root | `C:\laragon\www\pshub-moodle\public` |
| Site URL | **`http://pshub-moodle.test`** — nothing after the hostname |
| `$CFG->wwwroot` | `http://pshub-moodle.test` — **without** `/public` |
| Data directory | `C:\laragon\data\pshub-moodledata` (outside the web root) |
| Database | `pshub` on MySQL **8.4.3**, utf8mb4 / utf8mb4_unicode_ci, prefix `mdl_`, 499 tables |
| PHP | 8.3.30 ZTS (Laragon) |
| Admin | `admin` / `PsHub#Dev2026` / `admin@pshub.local` |

**Test accounts.** All on `@pshub.local`, so a dev box can never mail a real address. That promise was false until 2026-10-11: `larissa` was on `@example.cm`, a real Cameroonian TLD. The mail catcher was holding it, but a catcher is the second line of defence, not the first.

| Account | Password | What it is for |
|---|---|---|
| `amina` | `PsHub#Learner2026` | Learner, enrolled in courses 2 and 3 |
| `larissa` | `PsHub#Test2026` | Learner, enrolled in course 9 — the twelve-session course |
| `brigitte` | `PsHub#Test2026` | Learner, enrolled in nothing — the "not enrolled" path. Created 2026-10-10 **through the signup form**, so it is also the proof that signup and e-mail confirmation work |
| `adele` | `PsHub#Teacher2026` | Teacher: `pshub_teacher` at system level, editingteacher on courses 2, 3, 6, 8, 9, 10 and 11 |

They exist so that pages are verified against real data instead of asserted.

**Self-registration is ON** on this box (`registerauth = email`), enabled 2026-09-26 so that
`/login/signup.php` exists to build and verify against — without it the page is a 500. Note
that Moodle's e-mail auth wants to send a confirmation mail and this box has no SMTP, so a
new account stays unconfirmed. **Decision 4 closed on 2026-09-28: confirmation is
mandatory**, which makes a working mailer a launch blocker; revert with
`php admin/cli/cfg.php --name=registerauth --set=''` if the box is ever exposed.

**BigBlueButton.** This box points at the public test server the module ships as its
default, `test-moodle.blindsidenetworks.com`, with the module's published test secret.
Set with core-level names, which is what the module actually reads:

```php
set_config('bigbluebuttonbn_server_url', 'https://test-moodle.blindsidenetworks.com/bigbluebutton/');
set_config('bigbluebuttonbn_shared_secret', '0b21fcaf34673a8c3ec8ed877d76ae34');
```

`set_config(..., 'bigbluebuttonbn')` does **not** work: `config::get()` reads
`$CFG->bigbluebuttonbn_<name>`, which is core-level config, not plugin config. That cost a
few minutes.

⚠️ Meetings on it are public and it caps every meeting at 10 minutes. Never for a real class.

**Change the admin password if this box is ever reachable from outside.**

There is an unrelated `moodle` database on the same server (763 tables) left behind by a
previous install. It is not ours and has not been touched.

---

## 2. Corrections to the specification

### 2.1 There is no Moodle 5.3 RC ⚠️

Spec §13.6 and open decision 9 assume a 5.3 release candidate. **None exists.** As of
13 September 2026 upstream has:

- no `v5.3.0-rc*` or `v5.3.0-beta` tag (newest tag anywhere is `v5.2.3`)
- no `MOODLE_503_STABLE` branch (newest is `MOODLE_502_STABLE`)
- 5.3 living on `main`, self-reporting **alpha** maturity

`admin/cli/install.php` refuses to install it without `--allow-unstable`, warning the code
is "not suitable for use on most production sites". That warning targets production; this
is a development box, which is the case the flag exists for.

**Mitigation:** the commit is pinned. Move to the `v5.3.0` tag when it releases on
5 October, **before S3 begins the theme work** — that is where §12.1 warns theme code
written against the wrong Boost has to be redone. S1 and S2 are schema and service code
and are barely exposed to core churn.

### 2.2 Plugins live under `public/` ⚠️

Moodle 5.x splits the tree. This invalidates every `local/pshub` path in the spec, the
roadmap and the backlog:

| Purpose | Path |
|---|---|
| **Our plugin** | `public/local/pshub` ← **not** `local/pshub` |
| Our theme | `public/theme/pslanguagehub` |
| Web-servable code | `public/` |
| CLI scripts | `admin/cli/` (repo root, unreachable over HTTP) |
| `config.php` | repo root; `public/config.php` is only a loader stub |
| PHPUnit | run from the repo root |

**Non-default lines in `config.php`, and why.** Four, all deliberate:

| Line | Why | Production |
|---|---|---|
| `$CFG->smtphosts = '127.0.0.1:2525'` | Points Moodle at Mailpit, which catches every message locally so none can reach a real address. Replaced `$CFG->noemailever` on 2026-10-10 - see trap 30 | A real SMTP service, set in the admin settings, not here |
| `$CFG->smtpsecure = ''` | Mailpit speaks plain SMTP on loopback | Set by the mail provider |
| `$CFG->noreplyaddress = 'no-reply@pshub.local'` | A sender address that exists on this box | A real domain |
| `ini_set('zlib.output_compression', 'On')` | Apache here ships `mod_deflate` commented out, so HTML was going out uncompressed at 120 KB a page (2026-10-03) | Do it **in the web server**, not here |
| `$CFG->pshub_fake_payments = true` | Switches on the scripted payment provider, so the purchase flow can be built and tested before the Flutterwave account exists. The admin console carries a loud banner while it is on | **Never** |

The vhost at `C:/laragon/etc/apache2/sites-enabled/01-pshub.conf` now carries the
`mod_deflate` rules as well, scoped to this site; they take effect at the next Apache
restart. See `PERFORMANCE-BASELINE.md`.

This is a security improvement upstream: internals and CLI are no longer web-reachable.

### 2.3 The site was in the wrong timezone ⚠️

**Corrected 2026-10-11.** The site default was `Europe/London`, which puts its clocks
forward and back. Cameroon never has: `Africa/Douala` is UTC+1 all year.

On a platform whose entire product is an appointment, that is not cosmetic. Every class
booked for "18:00" stored a different absolute instant depending on which side of a
European clock change it fell, and **the screen said 18:00 for both**. The iCal export is
where it became visible: two classes a week apart went out as `17:00Z` and `18:00Z`. A
learner's phone, in a zone that does not change, would have shown them an hour apart.

```php
set_config('timezone', 'Africa/Douala');
set_config('forcetimezone', '99');   // the diaspora is a market: nobody is forced onto it
```

All six accounts were on `99`, meaning "the site default", so all six moved with it. The
demo course's classes were re-timed through `session_service::reschedule()` afterwards, so
the calendar entries and the reminder queue followed.

### 2.4 Web services are ON, for our own application only ⚠️

**Changed 2026-10-12.** PS Language Hub ships its own handset application rather than
Moodle's (§6.1), so the site has to expose an API. What was turned on, and nothing else:

```php
set_config('enablewebservices', 1);
set_config('webserviceprotocols', 'rest');   // not soap
// and the pshub_app service, which the plugin installs DISABLED, enabled by hand.
```

Plus two capabilities on the **authenticated user** role, which is what lets the app
exchange a sign-in for a token the way any first-party app does:

| Capability | Why |
|---|---|
| `moodle/webservice:createtoken` | `login/token.php` mints the token behind the app's sign-in screen |
| `webservice/rest:use` | Without it the token exists and every call is refused |

**What this does and does not widen.** A token is obtained with a username and password,
so it grants what that account already had - it is not a new way in. What it *is* is a new
**door**, and the usual rules follow it: tokens do not expire by default, they are bearer
credentials, and `login/token.php` is unauthenticated and therefore brute-forceable. Before
production: rate-limit it, set a token lifetime, and serve the whole site over HTTPS, since
a token in a querystring over plain HTTP is a password in a querystring over plain HTTP.

**`pshub_app` is not `MOODLE_OFFICIAL_MOBILE_SERVICE`.** Our functions are deliberately not
added to the official mobile service: that one belongs to the Moodle app, and putting our
functions on it would hand them to an application we did not write, on every site that
installs this plugin.

Check what is exposed at any time:

```sql
SELECT s.shortname, s.enabled, sf.functionname
  FROM mdl_external_services s
  JOIN mdl_external_services_functions sf ON sf.externalserviceid = s.id
 WHERE s.component = 'local_pshub';
```

### 2.5 The new router is not configured

`admin/cli/checks.php` reports `core_router` as ERROR. The router is **opt-in** via
`$CFG->routerconfigured` and drives Moodle 5.x's REST API v2 routes (`/api/rest/v2/…`)
through `public/r.php`, which needs web-server rewrite rules.

**Deliberately deferred.** Our spec uses classic Moodle web services (§6) and a
server-rendered Mustache surface (§12.1); neither needs the router. Setting
`routerconfigured = true` without working rewrites would turn an honest error into a false
pass. Revisit only if REST API v2 is ever adopted.

---

## 3. Web server

Laragon created the hosts entry for `pshub-moodle.test` ("laragon magic!") but **no
vhost**, so the name fell through to the default host and served Laragon's welcome page.
This supplies the missing vhost, with the document root at `public/`:

`C:\laragon\etc\apache2\sites-enabled\01-pshub.conf`

```apache
<VirtualHost *:80>
    DocumentRoot "C:/laragon/www/pshub-moodle/public"
    ServerName pshub-moodle.test
    ServerAlias *.pshub-moodle.test

    <Directory "C:/laragon/www/pshub-moodle/public">
        Options FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    <FilesMatch "^(config\.php|version\.php)$">
        Require all denied
    </FilesMatch>
</VirtualHost>
```

### 3.1 Why the document root must be `public/`

Moodle 5.x ships a **tripwire** at the repo root. `pshub-moodle/index.php` is nothing but:

```php
require_once('config.php');
throw new \core\exception\moodle_exception('rootdirpublic', 'error');
```

If you ever see *"The Moodle root directory must not be publicly accessible"*, the web
server is pointed one directory too high and `config.php`, `lib/` and `admin/cli/` are
reachable over HTTP. **Missing CSS is the usual first symptom**, because every theme URL is
built from `wwwroot` and resolves against the same misrouted root.

### 3.2 `laragon.exe reload` stops Apache and does not restart it ⚠️

It exits 0 and looks successful. It is not. On this setup it leaves zero `httpd`
processes and nothing listening on port 80, taking every other Laragon site down with it.

Restart Apache directly with the arguments Laragon itself uses:

```bash
nohup "/c/laragon/bin/apache/httpd-2.4.66-260223-Win64-VS18/bin/httpd.exe" \
      -d "C:/laragon/bin/apache/httpd-2.4.66-260223-Win64-VS18" >/dev/null 2>&1 &
```

`httpd -k restart` does **not** work either: Laragon runs Apache as a plain process, so
there is no `Apache2.4` service to signal. Prefer the Laragon tray menu; if you use the
command line, verify afterwards that port 80 is listening and any other sites still answer.

### 3.3 Purge caches after changing `wwwroot`

Theme CSS and JS bake the base URL in. After any `wwwroot` change:

```bash
php admin/cli/purge_caches.php
```

### 3.4 The URL has no path

`http://pshub-moodle.test` — nothing after the hostname. `…/pshub-moodle/` is a leftover
from the earlier `localhost/pshub-moodle` URL and returns a bare Apache 404, because
Apache looks for `public/pshub-moodle/`. Browser autocomplete reintroduces it readily.

---

## 4. Installing from scratch, without the dead ends

Four attempts were needed to find this sequence. In order:

```bash
# 1. Clone and pin. Shallow; the tree is ~384 MB and takes a while.
cd /c/laragon/www
git clone --depth 1 --branch main https://github.com/moodle/moodle.git pshub-moodle
git -C pshub-moodle rev-parse HEAD     # record this

# 2. Database and data directory
mysql -u root -e "CREATE DATABASE pshub DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mkdir -p /c/laragon/data/pshub-moodledata

# 3. Install. Note --allow-unstable, and wwwroot WITHOUT /public.
cd /c/laragon/www/pshub-moodle
php admin/cli/install.php \
  --wwwroot=http://localhost/pshub-moodle \
  --dataroot='C:\laragon\data\pshub-moodledata' \
  --dbtype=mysqli --dbhost=127.0.0.1 --dbport=3306 \
  --dbname=pshub --dbuser=root --dbpass= --prefix=mdl_ \
  --fullname='PS Language Hub' --shortname='PSHub' \
  --adminuser=admin --adminpass='PsHub#Dev2026' --adminemail='admin@pshub.local' \
  --lang=en --agree-license --allow-unstable --non-interactive
```

**The trap that cost two attempts.** If `install.php` fails *after* writing `config.php` —
which it does when `wwwroot` is wrong — every later run reads that stale `config.php` and
**silently ignores your `--wwwroot`**. Either delete `config.php` and start over, or fix
`$CFG->wwwroot` in it by hand and finish with `install_database.php`.

Note also that `install_database.php` does **not** accept `--allow-unstable`; the maturity
check exists only in `install.php`, so the flag is an unrecognised-option error there.

---

## 5. Daily commands

Run from the repo root, `C:\laragon\www\pshub-moodle`:

```bash
php admin/cli/upgrade.php --non-interactive   # after any plugin version bump
php admin/cli/purge_caches.php
php admin/cli/checks.php                      # site health
php admin/cli/cfg.php --name=wwwroot          # read a config value
php admin/cli/cron.php                        # cron is a WARNING until scheduled
php vendor/bin/phpunit --filter local_pshub   # needs PHPUnit init first
```

**The mail catcher is a third service**, alongside Apache and MySQL, and Laragon does not
start it. Without it every send fails and the signup confirmation never arrives. Easiest
is `tools\start-mailpit.cmd` in the project, which checks the port first and is safe to
run twice; the long form is below.

Since 2026-10-14 the site also *says* when the mailer is down: `admin/cli/checks.php` and
the site status page carry a **Mail delivery** check from `local_pshub`. Before it, a dead
catcher surfaced as a stack trace printed over an action that had already succeeded.

```bash
C:/laragon/bin/mailpit/1.22.3/mailpit.exe --smtp 127.0.0.1:2525 --listen 127.0.0.1:8026 --smtp-auth-accept-any --smtp-auth-allow-insecure
curl -s http://127.0.0.1:8026/api/v1/messages?limit=1   # is it up, and what arrived
```

Cron is not scheduled. It reports WARNING rather than CRITICAL now that it has run once;
schedule it every minute before anything depends on reminders (spec §10.2, AC-6).

---

## 6. Known state

| Check | Status |
|---|---|
| Database installed, 499 core tables | ✅ |
| CLI bootstraps, caches purge | ✅ |
| Admin account confirmed | ✅ |
| `wwwroot` correct | ✅ |
| **Serves over HTTP, fully styled** | ✅ `http://pshub-moodle.test` → 200, 1.34 MB theme CSS |
| `local_pshub` installed, **19 tables** | ✅ |
| 15 unique constraints in MySQL 8.4.3 | ✅ |
| PHPUnit initialised and green | ✅ `schema_test` 7/7, core privacy 4/4 |
| `core_router` check | ❌ deferred by decision (§2.3) |
| Cron scheduled | ⚠️ ran once, not scheduled |

### 6.1 Traps that cost time, in the order they bit

1. `install.php` writes `config.php` **before** validating `wwwroot`. Every later run then
   reads that stale file and **silently ignores `--wwwroot`**.
2. `install_database.php` rejects `--allow-unstable` — that check lives only in `install.php`.
3. `upgrade.php` needs `--allow-unstable` on **every** run while core is alpha.
4. `install.xml` runs **only on first install**. A plugin already registered at the same
   version gets no tables. Uninstall it (`admin/cli/uninstall_plugins.php --plugins=… --run`)
   or bump the version with a `db/upgrade.php`.
5. The **PHPUnit database has the same problem**, and separately. Fix with
   `util.php --drop` then `init.php`, not `init.php` alone.
6. Moodle core **fails any plugin storing user data without a privacy provider**. Its own
   `core_privacy` suite lists the offending tables. This is mandatory, and absent from the
   functional specification.
7. `--filter local_pshub` sweeps in unrelated core quiz tests that have pre-existing empty
   data providers. Target test files **by path** instead.
8. A plugin's `$plugin->requires` must not exceed the installed core version. Ours pointed
   at the future 5.3 release stamp and would have been rejected outright.
9. **`db/install.php` runs BEFORE capabilities are registered.** Core calls the install
   function at `upgradelib.php:767` and only registers `db/access.php` at line 773, so
   `assign_capability('local/pshub:…')` inside `install.php` fails: the capability does not
   exist yet. It aborts the install half-done, leaving `installrunning = 1`, some tables,
   and one of two roles. **Do not assign capabilities in `install.php`** — declare
   `archetypes` in `db/access.php` and let core grant them to matching roles moments later.
10. Recovering from that half-done install needs three things, not one: delete the
    `local_pshub` rows from `config_plugins`, `delete_role()` any part-created roles, and
    drop the plugin tables. Then purge caches — `core_plugin_manager` caches plugin state,
    and until it is purged `upgrade.php` insists "No upgrade needed" even though the plugin
    is absent from the database.
11. **`$CFG->dirroot` is the `public/` directory**, not the repo root. Plugin code writing
    `$CFG->dirroot . '/local/pshub/…'` is therefore correct; `…/public/local/…` is not.
12. **No French language pack ships by default**, and there is no `admin/cli/install_langpack.php`
    in this build. A plugin's own `lang/fr/` strings silently fall back to English until the
    pack exists. Install it programmatically:
    ```php
    require_once($CFG->dirroot.'/admin/tool/langimport/classes/controller.php');
    (new \tool_langimport\controller())->install_languagepacks('fr');
    ```
    French is the platform's default language (spec §12.4), so this is not optional.

13. **`$THEME->rendererfactory` is NOT inherited from a parent theme.** Boost sets
    `theme_overridden_renderer_factory`; a child theme that omits it gets the default
    factory, which only ever instantiates *core* renderers. Boost's own renderer methods
    then vanish, and Boost's `drawers` layout dies on
    `Call to undefined method core\output\core_renderer::firstview_fakeblocks()`.
    That layout is used by the dashboard, every admin page and most of the site.

    **How it hid:** the login page uses its own layout and calls none of those methods, so
    the theme looked completely healthy until the first authenticated page was loaded.
    A branded login proves almost nothing about the rest of the site.

14. **Theme CSS caching needs more than `purge_caches.php`.** That script does not bump the
    theme revision. Compiled CSS also persists on disk. The reliable sequence is:
    ```bash
    rm -rf /c/laragon/data/pshub-moodledata/localcache/theme
    php -r 'define("CLI_SCRIPT",true); require("config.php");
            require_once($CFG->libdir."/outputlib.php"); theme_reset_all_caches();'
    ```
    **Probe freshness with the rule you just added**, never one that already existed. A rule
    from an earlier edit is present in stale CSS too, so it passes while proving nothing.
    Twice this session an old rule was used as the probe and stale CSS was mistaken for a
    compiler or minifier bug.

15. **SCSS compilation fails silently.** `theme_config::get_css_content()` falls back to the
    parent's *precompiled* CSS when compilation throws
    (`theme_config.php` lines 943-949), so a broken theme renders as merely unstyled with
    no error anywhere. `scssphp` resolves `@import url('https://…')` as a **local file** and
    fails the whole compile, which is one way in. To see the real error:
    ```php
    $t = theme_config::load('pslanguagehub');
    $m = new ReflectionMethod($t, 'get_css_content_from_scss'); $m->setAccessible(true);
    var_dump($m->invoke($t, false) === false);   // true means it failed
    ```
    Verify a brand value is actually present (`stripos($css, '0A1A40')`), never by eye.

16. **The PHPUnit dataroot is a separate directory** (`pshub-phpunitdata`), so language packs
    installed into the site's dataroot are absent from tests. `force_current_language('fr')`
    then silently no-ops, because it only acts when the translation exists. Copying the pack
    across is still not enough: the string manager caches its translation list in-process.
    Test language-dependent logic through its branches rather than by switching language
    mid-test.

17. **`db/install.php` runs BEFORE capabilities are registered**, and `install.xml` runs only
    on a *first* install. Together these mean the development loop for schema or role
    changes is uninstall-then-reinstall, not upgrade:
    ```bash
    php admin/cli/uninstall_plugins.php --plugins=local_pshub --run
    php admin/cli/purge_caches.php
    php admin/cli/upgrade.php --non-interactive --allow-unstable
    ```
    Note `upgrade.php` needs `--allow-unstable` on *every* run while core is an alpha, not
    just at install.

18. **Shell heredocs mangle multi-line content here.** Both `bash <<'EOF'` and
    `python <<'EOF'` have swallowed terminators and eaten backslash escapes, corrupting
    French strings and breaking PHP syntax. Write the script to the scratchpad with the
    Write tool and execute the file instead. Keep generated PHP ASCII where practical and
    apply accents programmatically. ~~Or use double-quoted PHP strings so apostrophes need
    no escaping at all.~~ **Do not** — see trap 19, which is what that advice caused.

19. **Moodle's `{$a}` placeholder must never sit in a double-quoted PHP string.** PHP
    interpolates `{$a}` as a variable, so the placeholder reaches `get_string()` empty and
    the file emits `Undefined variable $a`. With developer debugging on, that warning is an
    **HTTP 500 on every page that loads the language pack** — which, for a language file, is
    every page on the site. It is a French-specific trap in practice: French text is full of
    apostrophes, which is exactly what tempts double quotes.
    ```php
    $string['x'] = "… attaché à {$a} cours";   // 500. PHP ate the placeholder.
    $string['x'] = '… attaché à {$a} cours';   // correct
    $string['x'] = "… attaché à {\$a} cours";  // also correct
    ```
    Audit the class, not the instance:
    `grep -nE '=\s*"[^"]*[^\\]\{\$a\}' lang/*/*.php` must return nothing.

20. **YUI's cssbase overrides Bootstrap on form inputs.** Moodle still loads
    `lib/yuilib/3.18.1/cssbase/cssbase.css`, which carries
    `input[type="text"], input[type="password"] { width: 12.25em }`. One class plus one type
    selector **outranks `.form-control { width: 100% }`**, so a login field comes out 196px
    wide inside a 440px column. It arrives in a stylesheet injected by JavaScript, so the
    server-rendered HTML looks perfect and only the browser disagrees. Set the width from a
    selector of your own — and use a **child** combinator, because Moodle's password reveal
    button later moves the input into a Bootstrap `.input-group` that sizes it with
    `width: 1%; flex: 1 1 auto`, which a descendant rule would break.

21. **Verifying a visual change needs measurement, not a screenshot.** Two ways this lies:
    - Headless Chrome **will not go below about 504 CSS pixels** of viewport width.
      `--window-size=390,844` silently renders at 504 and crops, so a "phone" screenshot is
      a cropped desktop one. Render the page in a `<iframe width="390">` on a same-origin
      page instead; the iframe gets a real 390px viewport and its media queries fire.
    - A screenshot can render against a **stale stylesheet** even after
      `theme_reset_all_caches()` and with a fresh `--user-data-dir`. The cause is trap 23:
      the first request after a reset serves the old CSS while building the new one.

    The reliable check is to ask the browser what it computed: serve a copy of the page with
    a small `<script>` appended that writes `getComputedStyle()` values into a `<pre>`, load
    it with `--dump-dom`, and read the numbers. It also answers "which rule won", by walking
    `document.styleSheets` and testing `el.matches(rule.selectorText)`. **Delete the probe
    file from the web root afterwards.**

22. **Laragon can come up with Apache but no MySQL.** After a reboot, Apache was running and
    MySQL was not: it had failed at start with
    `Bind on TCP/IP port: An attempt was made to access a socket in a way forbidden by its
    access permissions` — Windows had port 3306 inside a reserved range at that moment
    (`netsh int ipv4 show excludedportrange protocol=tcp`). Every Moodle CLI script then
    reports "Error: Database connection failed", which reads like a config problem and is
    not. Start it directly and confirm it listens:
    ```bash
    powershell "Start-Process 'C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysqld.exe' \
      -ArgumentList '--defaults-file=C:\laragon\bin\mysql\mysql-8.4.3-winx64\my.ini' -WindowStyle Hidden"
    ```
    Note `laragon.ini` still names `mysql-8.0.30-winx64` while the data lives in
    `C:\laragon\data\mysql-8.4` and belongs to **8.4.3** — start 8.4.3, not whatever Laragon
    defaults to, or the data directory will be refused.

23. **The first CSS request after a theme reset serves the OLD stylesheet.** This one cost
    an hour and looked like four different bugs in turn — an opcache, an unreadable file, a
    failed SCSS compile, a stale MUC entry. None of those. After
    `theme_reset_all_caches()`, the first request to `theme/styles.php/...` returns the
    previous CSS while writing the new file; every request after that returns the new one.
    So a check written as "reset, fetch, grep" reads the old content and reports failure
    while everything is in fact correct.

    The recipe that works:
    ```bash
    php -r '…theme_reset_all_caches();'      # bump the revision
    curl -s "$CSSURL" > /dev/null            # warm-up: throw this response away
    curl -s "$CSSURL" | grep -c 'my-new-rule'  # now it is the truth
    ```
    **One warm-up is not always enough** (2026-10-03). Twice in one session the served
    sheet stayed a full generation behind after reset + warm-up + check, for several
    seconds and several requests, then flipped on its own. A single discarded fetch is a
    guess, not a guarantee. What is deterministic: compile directly (below) and poll the
    URL until the served bytes match that compile. And `admin/cli/purge_caches.php` is
    **not** a substitute for `theme_reset_all_caches()` here - it was run first both times
    and the stale sheet survived it.

    Two things make the confusion worse and are worth knowing:
    - the `core/postprocessedcss` MUC cache is keyed **`<theme>_<ltr|rtl>` with no theme
      revision in it**, so it is not invalidated by a revision bump — only by the explicit
      purge inside `theme_reset_all_caches()`;
    - to find out whether a compile is actually broken, compile it directly rather than
      reading the served file:
      ```php
      $theme = theme_config::load('pslanguagehub');
      $r = new ReflectionMethod($theme, 'get_css_content_from_scss');
      $r->setAccessible(true);
      $css = $r->invoke($theme, false);   // false === true would mean it threw
      ```

24. **`$CFG->forcelogin = 1` makes core's front page redirect to login before ANY page or
    output hook fires.** A visitor arriving at the site root met a password box instead of
    the product. Redirecting the root to the public home therefore has to happen in
    `\core\hook\after_config`, which setup.php dispatches after the session starts and
    before any page code runs — `before_http_headers` is far too late. Scope such a callback
    tightly: skip CLI, AJAX, web services, install and upgrade, and match the site root with
    `$SCRIPT === '/index.php'` so it cannot catch anything else (see
    `local/pshub/classes/hook_callbacks.php`).

25. **A new PHP class is not autoloaded until the component map is rebuilt — and
    `purge_all_caches()` from the CLI did not rebuild it.** A freshly added
    `classes/.../thing.php` threw `Class "..." not found` even though the file, the
    namespace and the path were all correct, and even after a full purge. The stale map
    lives at `$CFG->localcachedir/core_component.php`; deleting it fixed it at once:
    ```bash
    rm -f /c/laragon/data/pshub-moodledata/localcache/core_component.php
    ```
    Confirm with `core_component::get_component_classes_in_namespace('local_pshub', 'learner')`
    rather than by trying to instantiate the class, which only tells you it failed again.

26. **`$SCRIPT` for Moodle's dashboard is `/my/index.php`, not `/my/`.** A hook that
    redirects learners away from the dashboard has to match the script path, not the URL a
    person types. Same for the site root, which is `/index.php`.

27. **Signup needs mail, and the fix is to catch it, not to refuse to send it.**
    Confirmation is mandatory (decision 4), so every signup sends an e-mail. With no SMTP
    server configured, Moodle does not fail - it **hangs** on a connection that never
    answers, leaving an unconfirmed account behind and a browser waiting.

    The first attempt here was `$CFG->noemailever = true`, which skips sending and logs
    instead. It works, but it announces itself through `debugging()` on every send, and
    that one notice broke two other things: an **HTTP 500 on the signup POST** under
    Whoops (papered over with `$CFG->debug_developer_debugging_as_error = false`), and
    every post-notification redirect on the site (trap 30).

    Laragon already ships a mail catcher, which is the better answer - mail is sent, goes
    nowhere real, and is readable:
    ```bash
    C:/laragon/bin/mailpit/1.22.3/mailpit.exe --smtp 127.0.0.1:2525 --listen 127.0.0.1:8026 --smtp-auth-accept-any --smtp-auth-allow-insecure
    ```
    ```php
    $CFG->smtphosts = '127.0.0.1:2525';
    $CFG->smtpsecure = '';
    $CFG->noreplyaddress = 'no-reply@pshub.local';
    ```
    Read the inbox at <http://127.0.0.1:8026/>; the API answers too, which is what a
    harness should use:
    ```bash
    curl -s 'http://127.0.0.1:8026/api/v1/search?query=to%3Asomebody%40pshub.local'
    ```
    **Ports:** 1025, Mailpit's default, is held by a Windows `services` process on this
    machine, and 1026-1125 are in the OS reserved range - `netsh interface ipv4 show
    excludedportrange protocol=tcp` lists them. Hence 2525 and 8026. Mailpit exits
    silently with no usable error when the bind fails, so check the port before blaming
    the config.

    `--smtp-auth-accept-any --smtp-auth-allow-insecure` matter: Moodle sends a username
    and password when `smtpuser` is set and plain `AUTH` on an unencrypted link, and
    Mailpit rejects both by default.

    `$CFG->debug_developer_debugging_as_error = false` went with it, and nothing needed
    it: every route was re-swept as admin, teacher and learner afterwards and not one
    emitted a debug notice. The two 500s the sweep did find are
    `required_capability_exception` - a teacher at the admin console, a learner at the
    teacher console - which is the gate working, rendered by Whoops because developer
    debugging is on.

    Verified end to end on 2026-10-10: signup POST -> "un message vous a ete envoye" ->
    the message in Mailpit -> `confirm.php?data=.../username` -> `confirmed = 1`, the
    learner logged in, and the Mobile Money number stored in `user_info_data`.

    The old fallback, for when no catcher is running: take the confirmation link from the
    database instead of an inbox - `secret` is the token Moodle puts in the mail:
    ```sql
    SELECT CONCAT('/login/confirm.php?data=', secret, '/', username) FROM mdl_user WHERE username = 'x';
    ```

28. **`$PAGE->set_pagelayout()` still works inside the `before_http_headers` hook.** It fires
    at the very start of `core_renderer::header()`, before the layout file is chosen, which
    is what allows a plugin to re-skin a core page (`/login/confirm.php` renders in `base`
    otherwise) without patching core or overriding the `base` layout for the whole site.

29. **A copy of an authenticated Moodle page redirects itself to the login screen.**
    Measuring a learner page at 390 px means copying it somewhere same-origin and loading
    it in a 390 px iframe - headless Chrome will not lay out below ~504 px, so a phone
    screenshot of it is cropped, not narrow. The copy carries Moodle's own JavaScript,
    which finds no session in the fresh browser profile and navigates the frame to
    `/login/index.php`: the harness then measures the login page and reports a clean
    result for a page it never looked at. Strip every `<script>` from the copy before
    measuring. The layout is CSS only, so nothing is lost - and a page measured with
    scripting off is the state AC-20 requires it to work in anyway. **Make the harness
    print the document title it measured**; that one line is what catches this.

30. **Any debug output on a POST turns a 303 redirect into a 200 "please continue" page.**
    A POST that sends a notification - approving a course, rejecting one - came back 200
    instead of 303, and only for the administrator, with a stack trace above a *Continue*
    link. Nothing was wrong with the redirect: something had printed before it, so
    `headers_sent()` was true and `redirect()` fell back to its HTML page. **The state
    change has already happened and committed** - the course really was published.

    Here the printer was `$CFG->noemailever`, which emits `++ Not sending email due to
    $CFG->noemailever config setting ++` through `debugging()` on every send. Replacing it
    with a real local mailer (trap 27) fixed it: the same approval now answers 303 in
    1 568 bytes instead of 200 in 107 542.

    The general shape is worth more than the particular cause. When a redirect after a
    POST becomes a Continue page, do not look at the redirect - look for whatever started
    output, and expect it to be a `debugging()` call on a path that only one role takes.

31. **`userdate('%Y-%m-%d')` is not an ISO date on Windows.** It comes back as
    `2026-11-7`: Moodle rewrites `%d` to drop the leading zero, working around a platform
    difference in `strftime`. A browser does not read that as a date, and
    `<time datetime>` is exactly where it has to. For anything a machine reads, format
    the timestamp directly in the user's own zone:
    ```php
    $date = new \DateTime('@' . $timestamp);
    $date->setTimezone(\core_date::get_user_timezone_object());
    $date->format('Y-m-d');
    ```
    `userdate()` stays right for anything a person reads. Caught by a test written on the
    seventh of the month, which is the only kind that catches it.

32. **A direct database write to a session leaves its calendar entry behind.** The
    appointment exists twice: on `local_pshub_session`, and as a `course` event that
    session_service keeps in step. `$DB->set_field('local_pshub_session',
    'scheduled_start', ...)` changes one of them. Nothing fails, and nothing says so -
    the page is right and the learner's phone is a week out. Found on this box: a harness
    had moved a class with a direct write, and the iCal export still carried the old date
    seven days later.

    The repair is `session_service::resync_calendar($sessionid)`, which rewrites the
    entry from the session row and records nothing, because nothing happened - the row
    was always the truth. Anything that moves a class on purpose should call
    `reschedule()` instead, which also re-queues the reminders.

33. **A measuring iframe taller than a phone invents vertical gaps.** The responsive
    harness loads a script-free copy in a 390 px iframe and makes that iframe very tall so
    nothing is cut off. Inside it, `100vh` is the IFRAME's height - so a shell with
    `min-height: 100vh` gets a frame far taller than its content, and the free space lands
    between the grid rows: a 757 px hole between the navigation strip and the page body,
    on a page that is perfect on a handset. Measured again at 390x844 the gap was **0**.

    Measure layout at a real device height. Keep the tall iframe for what it is good at -
    finding horizontal overflow, and screenshotting a long page in one pass - and
    remember it takes 15 px for its own scrollbar unless `--hide-scrollbars` is passed,
    which is why a 390 px frame reports `scrollWidth` 375.

34. **Opening a file for writing destroys it before you know the content is good.**
    `open(path, 'w')` truncates immediately. A script that then throws - on an encode, on
    an assertion, on anything - leaves nothing behind. On 2026-10-13 that destroyed
    `docs/BACKLOG.md`: a lone surrogate pair in a string literal (`'\ud83d\udfe1'` for an
    emoji) raised `UnicodeEncodeError` **after** the file had been emptied. With no
    repository, there was nothing to check out.

    Build the whole text, encode it, and only then touch the target:

    ```python
    data = text.encode('utf-8')      # fails here, with the file untouched
    with open(path + '.tmp', 'wb') as fh:
        fh.write(data)
    shutil.move(path + '.tmp', path)
    ```

    Two smaller lessons from the same hour. **Build non-ASCII from code points**, not from
    literals that may be mangled in transit: `chr(0x1F7E1)`, not a pasted emoji and never a
    surrogate pair. And **the session transcripts are a backup of last resort** - every
    file this session read is in
    `~/.claude/projects/<project>/<session>.jsonl`, and a per-line harvest of the most
    recent verbatim copy of each row recovered most of what was lost. Most, not all: a
    section nobody re-read after editing it is gone.

    The real lesson is `PSH-S01-07`. A repository would have made this a `git checkout`.

35. **A dead mailer looks like a failed action, and is not one.** Approving a course came
    back as `Error calling message processor email` over a Whoops stack trace; so did a
    learner joining a free course. Both times the action had **already committed** - the
    course really was published, the learner really was enrolled - and only the
    notification was lost. Moodle does not fail a transaction because a message could not
    be sent: `message_send()` catches the processor error and calls `debugging()`, and with
    developer debugging on that notice becomes a trigger_error that Whoops renders as a
    page.

    Two giveaways that it is this and not a broken feature: the frame below `debugging()`
    is `core\message\manager::call_processors`, and the trace sits over a POST that worked.
    Check the audit trail or the database before debugging the feature - on 2026-10-14 the
    audit row `course_published` was already there while the screen showed an exception.

    When a message is sent inside a transaction, Moodle buffers it and flushes on commit,
    so the stack shows `database_transaction_commited` and names the function that
    committed rather than the one that sent - `enrolment_service::enrol_free` in that case,
    which sends nothing at all.

    The cause here was always the same: **Mailpit had stopped.** `tools\start-mailpit.cmd`
    restarts it, and the **Mail delivery** site check now answers the question before
    anybody meets it as a trace.

