# PS Language Hub

**Live-first language learning on Moodle.** Every course is taught in a scheduled live session — video, collaborative whiteboard and screen share — with quizzes, assignments and workshops around it. Built for francophone Africa: low bandwidth, Mobile Money, mobile-first.

**Tagline:** LEARN. SPEAK. SUCCEED.

> **Status: in build.** Sprints 2 and 3 are closed; sprints 4 and 5 were closed but lost
> their written evidence (below); sprint 8 is 6 of 10; sprints 6, 7 and 9 have not
> started, and payments are blocked on a merchant account. **251 tests pass.**
>
> What is built, what is not, and what each claim was verified against:
> **[docs/BACKLOG.md](docs/BACKLOG.md)**. Every status note there was earned against a
> running site rather than asserted, and says how it was checked.
>
> ⚠️ **The backlog was destroyed on 2026-10-13 and rebuilt from session logs.** The
> notes on sprints 4 and 5 did not come back; those rows show their original planning
> text and are marked ♻️. The work is done and running — the review workflow and the
> live sessions are both live — but the file is no longer evidence of it. This
> repository existing is the fix for the cause (`PSH-S01-07`).

## What is in this repository

**Only the code written for PS Language Hub.** Moodle itself is upstream and is cloned
separately; nothing of it is vendored here.

| Path here | Goes to, in a Moodle install |
|---|---|
| `moodle/local/pshub/` | `public/local/pshub/` — the plugin: gating, catalogue, review, sessions, payments seam, app API |
| `moodle/theme/pslanguagehub/` | `public/theme/pslanguagehub/` — the theme and every page shell |
| `moodle/mod/bigbluebuttonbn/extension/pshub/` | the same path — the `bbbext` subplugin that signs a `duration` into the BigBlueButton call |
| `docs/` | nothing; it is the project's written record |
| `tools/` | nothing; small scripts for the development box |

### Installing it over a Moodle 5.3 tree

Copy the three directories under `moodle/` into the matching places in a Moodle install,
then run the upgrade. The plugin declares its own tables, capabilities, message providers,
scheduled tasks and web services, so there is nothing to configure by hand first:

```bash
php admin/cli/upgrade.php --non-interactive
php admin/cli/purge_caches.php
```

> ⚠️ On the development box the live code is at `C:\laragon\www\pshub-moodle\public`, and the copies here were taken from it.
> **Two copies drift**, so edit in the Moodle tree and copy into the repository — the
> repository is the backup and the history, not the working copy.
>
> **Do not junction or symlink them.** It is the obvious fix and it does not work:
> every entry point here does `require_once(__DIR__ . '/../../config.php')`, PHP
> resolves `__DIR__` through the link to the *real* path, and the lookup lands in this
> repository where there is no `config.php`. Tried on 2026-11-14; every page of the
> plugin returned a fatal error, and PHP's resolved-path cache kept returning it for a
> couple of minutes after the links were removed — an Apache restart clears that.
>
> What would work, and is the proper fix, is making this repository the Moodle tree:
> clone Moodle, then lay these three directories over it. That is what
> **Installing it over a Moodle 5.3 tree** above describes.

## Development environment

A working Moodle **5.3dev** is installed at `C:\laragon\www\pshub-moodle`, pinned to commit
`48ea8c3`, with 499 tables in the `pshub` database. Plugin source lives **inside** that
install:

| | On disk | Served at |
|---|---|---|
| Plugin | `pshub-moodle/public/local/pshub` | `/local/pshub/…` |
| Theme | `pshub-moodle/public/theme/pslanguagehub` | — |
| CLI, `config.php` | repo root, above `public/` | not web-reachable |

Moodle 5.x keeps web-servable code under `public/` and everything else above it. URLs are
unaffected — `public/` never appears in a URL — so the paths quoted throughout the
specification remain correct.

Setup, the install sequence, and the traps in it: **[docs/ENVIRONMENT.md](docs/ENVIRONMENT.md)**.

## Documentation

| Document | Purpose |
|---|---|
| [docs/ENVIRONMENT.md](docs/ENVIRONMENT.md) | The installed environment, and corrections to the spec found by building it |
| [functional-spec-ps-language-hub.md](functional-spec-ps-language-hub.md) | **Canon** — functional specification v3.1 |
| [docs/ROADMAP.md](docs/ROADMAP.md) | Two parallel tracks, sprints S1–S22, critical path, risk register, definition of done |
| [docs/BACKLOG.md](docs/BACKLOG.md) | Story IDs `PSH-S{sprint}-{nn}` with team labels; P1 in detail, P2/P3 as epics |
| [docs/cahier-des-charges-v2.md](docs/cahier-des-charges-v2.md) | Client requirements v2.0 (Sept 2026), transcribed verbatim |
| [docs/GUIDE-UTILISATION.md](docs/GUIDE-UTILISATION.md) | **En français** — how to run the platform: start it, create a course, publish it, schedule it, follow it |
| [docs/API-APPLICATION.md](docs/API-APPLICATION.md) | The web-service contract, written for whoever builds the handset application |
| [docs/PAYMENTS.md](docs/PAYMENTS.md) | The payment seam, and the scripted provider that makes it testable before a merchant account exists |
| [docs/PERFORMANCE-BASELINE.md](docs/PERFORMANCE-BASELINE.md) | What each page weighs, measured; and the one open decision about the JavaScript budget |
| [docs/SPIKE-50-MIN-CAP.md](docs/SPIKE-50-MIN-CAP.md) | How a 50-minute cap is actually enforced in BigBlueButton |
| `cahier_des_charges-elearning.pdf` | Client requirements v1.0 (June 2026) — superseded |
| [docs/archive/functional-spec-v2.0.md](docs/archive/functional-spec-v2.0.md) | Previous spec (async-lesson product) — superseded, kept for reference |

Read §0.1 and §0.2 of the spec first. **v3 is a pivot, not an increment**: the product moved
from asynchronous lessons to 100 % live sessions, and the role model, publishing flow, payment
flow and non-functional targets all moved with it. §0.2 records the client decisions that
closed the architecture questions v3.0 had left open.

## Intended architecture

| Layer | Owner |
|---|---|
| Business logic — gating, catalogue, course validation, sessions, XP, payments, audit | `local_pshub` (to build) |
| Live classroom | BigBlueButton via `mod_bigbluebuttonbn` (bundled in Moodle core) |
| Pedagogy — courses, activities, completion, competencies, badges, files | **Moodle 5.3 LTS** core |
| Presentation (web, all roles) | `theme_pslanguagehub` — server-rendered Mustache throughout; four AMD modules (spec §12.1) |
| Presentation (teacher & admin) | Moodle portals `/local/pshub/{teacher,admin}/*` |
| Presentation (mobile) | **Flutter** app, Android first (spec §12.3) |
| Payments | `core_payment` + `paygw_pshub` (MTN MoMo, Orange Money, Wave, card; XAF) |

> **Golden rule:** access authorisation lives in **`local_pshub`**, never in Moodle's
> per-course enrolment configuration. One gatekeeper, decoupled from pedagogy.
> Logic belongs in a `local_pshub` service — the theme renders it, the web service
> serialises it, neither decides.

## Roles

Four roles, mapped onto Moodle capabilities (spec §2, §5.3):

- **Gestionnaire de site** — Moodle site administrator; creates the admin accounts.
- **Administrateur principal** — validates courses and payments, sets pricing and course
  types, assigns competency briefs to teachers, configures XP and certificates.
- **Enseignant** — authors in standard Moodle, schedules and runs live sessions, submits
  courses for validation. **Cannot publish.**
- **Apprenant** — web and mobile only; never sees the raw Moodle interface.

## Architecture decisions, closed

Settled by client arbitration in September 2026 (spec §0.2):

- **The whole web surface is server-rendered Mustache — no client framework, at any phase.**
  The Sharecourse reference is CSS, not architecture, so nothing in the design needs React.
  The four genuinely interactive pieces — session countdown, payment polling, connection strip,
  optimistic quiz submit — are small Moodle AMD modules. No bundle, no hydration, no client
  router. Every page works with JavaScript disabled.
- **Two delivery teams** — Moodle/PHP and design/CSS — meeting at the **template context
  shape**: what each `templatable` class exports, and the partial written against it. Agree the
  convention in sprint 1.
- **Mobile is Flutter**, Android first.
- **The Flutter app is the only consumer of the web-service API** — the web surface calls
  `local_pshub` services directly in PHP. That makes spec §6 P2 work, not P1.
- **Payment validation does both**: per-method auto-validation for trusted gateways, manual
  queue for everything else, through one state transition.
- **Screen share suppresses per-participant** below a bitrate threshold, so the 64 kbit/s
  audio-plus-whiteboard floor and the screen-share requirement can both hold.
- **Packs, learning groups, kids catalogue, streaks and Wave are in scope** despite the cahier
  omitting them. Packs are modelled in P1 and sold in P2 — a second grant dimension cannot be
  retrofitted into a live gating service cheaply.
- **Platform is Moodle 5.3 LTS** (5 Oct 2026, security support to Oct 2029). Develop against
  the RC, pin on release, launch on 5.3.2+.

## Platform floors — check before signing a hosting contract

Moodle 5.3 requires **PHP 8.3 (64-bit only)** and **PostgreSQL 16** or **MySQL 8.4** /
**MariaDB 10.11**. Budget hosting in the region is frequently still on PHP 8.1 and MariaDB 10.6.
This is a hard requirement, not a preference (spec §13.6).

## What is blocked, and on what

Not engineering problems — accounts, hardware and one product decision:

| Blocked | Waiting on |
|---|---|
| Real payments (`PSH-S07-01`) | A Flutterwave merchant account. The seam and a scripted provider are built, so the purchase flow can be exercised today — see [docs/PAYMENTS.md](docs/PAYMENTS.md) |
| A 50-minute class (`PSH-S01-02`) | A BigBlueButton server of our own. The public test server works but forces every meeting to 10 minutes, so **AC-4 is unproven** |
| The bandwidth spike (`PSH-S01-08`) | A real Cameroonian mobile network. If 64 kbit/s is unreachable, §13.2 is renegotiated **before** sprint 6 |
| The JavaScript budget (`PSH-S01-14`) | **A product decision.** The §13.2 budget of 50 KB per page cannot be met while Moodle serves the pages: 362 KB of the 370 KB is core's, ours is 0. Four options and a recommendation in [docs/PERFORMANCE-BASELINE.md](docs/PERFORMANCE-BASELINE.md) |
| Push notifications (`PSH-S08-10`) | Nothing but build time. Devices register; nothing sends yet |

One documentation task remains: the **cahier needs amending** to cover the five retained
features, or the two documents will contradict each other (§18.2, #13).

## Licence

**GPL v3 or later**, as every source file's header already declares — a Moodle plugin
inherits Moodle's licence. Full text in [LICENSE](LICENSE).

## Brand assets

[`ps-brand-assets/`](ps-brand-assets/) — logo mark, horizontal logos (light + navy), app
icons (standard + maskable), favicons, web manifest. Colour tokens in spec §12.4.
