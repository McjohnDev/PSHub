# Backlog — PS Language Hub

**Version:** 3.0 · **Date:** September 2026
**Companion to:** [ROADMAP.md](ROADMAP.md) · [functional specification v3.1](../functional-spec-ps-language-hub.md)
**Changed in v3.0:** server-rendered Mustache throughout, no client framework. P1 shortens to nine sprints; sprints renumbered.

> ## ⚠️ This file was destroyed on 2026-10-13 and rebuilt
>
> A script opened it for writing and then threw while encoding, truncating it to nothing. There is no repository (`PSH-S01-07`), so there was nothing to check out.
>
> It was rebuilt from the session transcripts, **verbatim, never from memory**: the v3.0 skeleton, with each story row replaced by the most recent copy of that row found anywhere in the logs. Sprints 1, 2, 3, 8 and 9 came back with their verification notes.
>
> **What did not come back:** the notes on **Sprint 4**, **Sprint 5** and `PSH-S07-00`. Those rows are marked ♻️ below and now show their original planning text. The work itself is done and running - the review workflow, the sessions, the lobby, the payment seam are all live on the box - but **this file is no longer evidence of it**. Re-verify against the running site before trusting a tick there, the way every other row here was earned.
>
> **This is the second argument for `PSH-S01-07`**, and a better one than CI: a repository would have made the whole incident a `git checkout`.


---

## 0. Conventions

**Story ID:** `PSH-S{sprint}-{nn}` — e.g. `PSH-S02-03` is the third story of sprint 2.

Every story cites the spec section that defines it. Where a story closes an acceptance criterion, the AC is named — that AC is the test, and the story is not done until it passes (ROADMAP §6).

**Phase 1 is detailed below. Phases 2 and 3 are epics**, deliberately. Breaking S14 into stories today would be fiction: it depends on what P1 field use teaches. Detail each phase at its gate.

**Team:** **A** = Moodle/PHP · **B** = Design/CSS · **A+B** = joint. Unmarked stories are Team A.

| Marker | Meaning |
|---|---|
| ⛓ | Blocks other work — slipping it slips the critical path |
| 🔬 | Spike — timeboxed investigation, output is a decision not a feature |
| ⚠️ | Carries a known risk from ROADMAP §5 |

**The template context shape is the contract between the teams** (spec §12.1). Team A defines what each `templatable` class exports; Team B writes the partial against it. Write the shape down before the partial. Integration is continuous — there is one rendering path, so a partial either works against the real context or fails at once.

---

## Sprint 1 — Foundations ⛓

| ID | Story | Spec | Notes |
|---|---|---|---|
| `PSH-S01-01` | Provision Moodle 5.3 RC, dev + staging, meeting the platform floors | §13.6 | 🟡 **Dev only.** Moodle 5.3dev build 2026091100 on PHP 8.3.30 / MySQL 8.4.3, which meets the floors. **No staging host** — and staging is where payment callbacks and the real domain get exercised (§9.5) |
| `PSH-S01-02` | Provision a BigBlueButton server; verify the core module connects | §8 | 🟡 **Connected — to the public TEST server**, which the module ships as its own default and flags as test credentials. It answers (version 2), meetings are created, and a learner can join one from the lobby. ⚠️ **Not a substitute for a real server**: meetings there are public, and it **forces every meeting to 10 minutes** whatever `duration` asks — verified with three distinct meetings at 5, 25 and 50. So AC-4 remains unproven, and S6's load test still has nowhere to run. Provisioning our own is still owed |
| `PSH-S01-03` | `local_pshub` plugin skeleton, version, language packs FR/EN | §7.3 | ✅ Installed at 2026100303, both language packs complete |
| `PSH-S01-04` | ⛓ **Install the complete schema — every table in §4.1** | §4.1 | ✅ **19/19 tables present**, verified against `install.xml` by `schema_test`. Includes the pack, XP and group tables nothing reads yet, and `local_pshub_learner` - a derived dashboard snapshot that is still **empty on the live site**, because nothing writes it. Its own schema comment says it is rebuildable and never the source of truth, so an empty table is correct until a dashboard needs it; worth knowing before anybody reads a learner's XP from there |
| `PSH-S01-05` | Define capabilities and the four roles; `pshub_admin` assignable only by site admin | §5.3, §2.1 | ✅ 12 capabilities, `pshub_admin` and `pshub_teacher` created from the manager and editingteacher archetypes. Learner and Gestionnaire are Moodle's own student and site administrator, by design. Covered by `capabilities_test`. Closes **AC-11** |
| `PSH-S01-06` | `local_pshub_audit` service; write on every state transition | §13.1 | ✅ In use: every course transition writes its row inside the same transaction as the change. Covered by `audit_service_test` |
| `PSH-S01-07` | CI: PHPUnit, Moodle code checker | ROADMAP §7 | ⚠️ **Not done, and the reason is upstream: there is no repository.** `local/pshub` and `theme/pslanguagehub` are untracked files inside a Moodle checkout. One machine, no history, no backup |
| `PSH-S01-08` | 🔬 **Bandwidth spike** — BBB audio + whiteboard on a real Cameroonian mobile network | §8.4 | ⚠️ Decision 2. If 64 kbit/s is unreachable, §13.2 is renegotiated **before** S6 |
| `PSH-S01-09` | ⛓ **Agree the template context-shape convention** — how shapes are documented and versioned | §12.1 | ✅ Every template carries its contract and an example context in its own header comment; the convention has held across 12 templates |
| `PSH-S01-10` | Design system foundation: brand tokens (§12.4), type scale, spacing, colour roles | §12.4 | ✅ 13 brand tokens in `scss/pre.scss`, two self-hosted families (71 KB) |
| `PSH-S01-11` | Boost child theme scaffolding, **built against the 5.3 RC** | §12.1 | ✅ Four layouts — `pspublic`, and one shell template driven by `pslearner`, `psteacher`, `psadmin` |
| `PSH-S01-12` | Partial library conventions; **override register** for core templates, started empty | §12.1 | ✅ `theme/pslanguagehub/OVERRIDES.md`. **One** core template is overridden, `core/login_layout` — the register exists to keep that number visible at upgrade time |
| `PSH-S01-13` | Component primitives as partials: button, field, card, badge, dialog | §12.2 | **B** |
| `PSH-S01-14` | CI check: **no framework bundle; page JS under 50 KB compressed** | §13.2 | ⚠️ Blocked by `PSH-S01-07` (no CI). The measurement exists and is recorded; **the budget itself needs a product decision** — see `PERFORMANCE-BASELINE.md`. Closes **AC-21** |

---

## Sprint 2 — The gatekeeper ⛓

| ID | Story | Spec | Notes |
|---|---|---|---|
| `PSH-S02-01` | ⛓ `gating_service::check()` — free, paid, pack, not-published branches | §3.7 | ✅ Every branch built and covered by `gating_service_test` |
| `PSH-S02-02` | Access-right lifecycle: create, expire, revoke, manual grant with reason | §4.1 | ✅ `access_service`, covered by `access_service_test` |
| `PSH-S02-03` | `are_packs_enabled()` flag; pack branch built, tested, held shut | §3.7 | ✅ Built and tested, and the setting ships **off**. Closes **AC-18** (P1 half) |
| `PSH-S02-04` | Free enrolment: access right + Moodle enrolment, atomically or not at all | §3.3 | ✅ `enrolment_service`, one transaction, covered by `enrolment_service_test`. Closes **AC-9** |
| `PSH-S02-05` | Course-type CRUD in the admin portal — code, kind, discount, badge style | §3.3 | ✅ Four types seeded and editable through the admin form; the console lists their badges. Closes **AC-8** |
| `PSH-S02-06` | Price resolver: largest discount wins, free short-circuits, pack price overrides | §3.3 | ✅ Covered by `price_resolver_test`, including XAF's zero-decimal formatting |
| `PSH-S02-07` | Suppress Moodle enrolment UI for hub courses | §3.3 | ✅ A learner opening a hub course they are not in lands on its own page, where the gating verdict decides the action. Verified: hub course → redirect; **non-hub course → Moodle untouched**; staff keep the real page |
| `PSH-S02-08` | Design system: navigation, tables, empty and error states | §15 | **B** ✅ Delivered through the build rather than as a separate artefact: the shell's navigation, the row lists both consoles use, and an empty state on every surface that can be empty. Each one offers the next step instead of describing the absence |
| `PSH-S02-09` | Public partials: home, catalogue, **course card with badges** | §3.3 | **B** ✅ One card partial serves the home page, the catalogue and the learner's space; it carries the image or the language plate, the top badge and the price |
| `PSH-S02-10` | Dashboard and course-experience designs | §12.2 | **B** ✅ Screens 05 and 07, built and screenshotted. **A defect was found in the dashboard on 2026-10-11 and fixed**: the tile "upcoming live sessions" counted the list it was about to print, and that list is capped at the next five - a learner with eleven classes left was told five, and nothing looked wrong. The count now comes from `agenda_service`, the same place the diary comes from, with a regression test |
| `PSH-S02-11` | **View Transitions** for page-to-page navigation | §12.1 | ✅ Cross-document, CSS only, 180 ms. Chrome and footer named so they stay still; disabled under `prefers-reduced-motion` (rule verified inside its media query) |

---

## Sprint 3 — The public face

| ID | Story | Spec | Notes |
|---|---|---|---|
| `PSH-S03-01` | Page shells and renderers: public, learner, teacher, admin | §12.1 | ✅ **Closed.** Four shells: `pspublic`, and one `psapp` template driven by `pslearner`, `psteacher` and `psadmin` - the same furniture with different doors, so there is one shell to re-reconcile at the next Moodle major, not three. Staff reach their console from Moodle's primary navigation, filtered by capability; verified that the teacher sees only hers and that a learner is refused both |
| `PSH-S03-02` | Public home: featured courses with badges, brand, how-it-works, FAQ | §3.3, cahier §5.1 | ✅ `/local/pshub/index.php`, and the site root hands over to it for visitors. FAQ is `<details>`, so it works with no JS |
| `PSH-S03-03` | Catalogue with filters by language, level, type, access | §3.3 | ✅ Filters are plain links; verified that each one changes the result set with scripting off |
| `PSH-S03-04` | Course detail with upcoming sessions and effective price | §6.2 | ✅ **Closed.** Price, badges, gating-aware CTA and what a place carries were already there; the next dates arrived with `PSH-S05-01` and the page now shows up to four. A course with no session shows no empty block — somebody deciding whether to pay is deciding whether they can be there on those evenings |
| `PSH-S03-05` | Signup and login; profile row creation; phone capture | §3.1 | ✅ **Closed.** Decision 4 settled: confirmation is mandatory. Full journey verified on a real account — form → account created unconfirmed with the phone normalised to `+237699112233` and the platform row written → "check your e-mail" page → sign-in refused → confirmation link → sign-in works → learner space. All three pages carry the product's shell, not Moodle's. **Re-verified 2026-10-10 through a real inbox**: the box now has a local mail catcher instead of `$CFG->noemailever`, so the confirmation is read from the message Moodle actually sent rather than lifted out of `mdl_user.secret`. Account `brigitte`, Mobile Money number stored, `confirmed = 1`, learner signed in by the link |
| `PSH-S03-06` | Discreet staff login link in the footer; capability-based landing | §3.2 | ✅ Footer link present on every public page; Moodle's own landing rules route by capability (verified: admin still lands on the dashboard) |
| `PSH-S03-07` | App-store download links on the home page | cahier §5.1 | ✅ Present as honest "coming soon" labels, not links that go nowhere. Become links in S12 |
| `PSH-S03-08` | **Performance baseline** — recorded and re-measured | §13.2 | 🟡 Re-measured 2026-10-03; `docs/PERFORMANCE-BASELINE.md` rewritten. **A real bug was found and fixed: HTML was going out uncompressed on every page** (120 KB instead of 14 KB) — the first measurement asked for gzip and never checked whether it got it. First visit is now ≈690 KB, every later page 7–14 KB, and no script blocks the first paint. ⚠️ **One decision left, and it is the product's**: the §13.2 budget of 50 KB JS/page cannot be met while Moodle serves the pages (362 KB of the 370 KB is core's, ours is 0). The doc sets out four options and a recommendation |
| `PSH-S03-09` | 🔬 **50-minute warning spike** — how to fire 45/48 min notices in BBB 5.3 | §8.3 | ✅ **Done: `docs/SPIKE-50-MIN-CAP.md`.** The finding that shapes S5: `mod_bigbluebuttonbn` **never sends BBB's `duration`**, so nothing in the module as shipped can end a meeting. The way in is its `bbbext` subplugin point, whose parameters are merged **before** the checksum — so a `duration` we inject is signed and accepted, with no core patch. Four questions remain that only a real server can answer |
| `PSH-S03-10` | Public pages styled and finished | §12.4 | ✅ Home, catalogue and course detail built to the approved design set (`docs/design/`). Screenshots in `docs/design/shots/` |
| `PSH-S03-11` | Signup and login styling over Moodle forms | §12.4 | ✅ Both done: login form and Moodle's signup mform, labels above, full-width controls, password policy moved under the password field it describes |
| `PSH-S03-12` | Responsive pass: sidebar + grid → single column with bottom tabs | §15 | ✅ Six routes measured for horizontal overflow: none. Two real bugs found and fixed — the footer grid (432 px of track floors in a 375 px viewport, a scrollbar on **every** public page) and the course page's two-column grid, which never stacked **A third bug surfaced 2026-10-11 and was fixed: on a phone there was no way to sign out at all.** The narrow rules hid `.ps-side__logout` along with the sidebar label, and the top bar carries only search - so below 768 px the app shell had no sign-out affordance anywhere. On handsets that get passed around, which is the target market, that is not a layout preference. The account links now stay, as a row under the navigation strip; measured visible at 390 px |
| `PSH-S03-13` | **No-JS verification** across every public route | §12.1 | ✅ Six routes verified with zero script execution: content renders, filters filter, search submits, FAQ opens, learner shell navigates. One real failure found and fixed: **the language menu could not be opened without JavaScript**, so nobody could switch language on the login page. Closes **AC-20** for public and learner routes |

**Exit:** **AC-2**, **AC-17**, **AC-20**, **AC-21**.

---

## Sprint 4 — Course validation workflow

| ID | Story | Spec | Notes |
|---|---|---|---|
| `PSH-S04-01` | Teacher portal shell; my courses with review status | §6.8 | ♻️ **Status note lost 2026-10-13, not yet re-verified.** |
| `PSH-S04-02` | Create course shell: hidden Moodle course + `local_pshub_course(draft)` | §3.4 | Authoring itself stays in Moodle ♻️ **Status note lost 2026-10-13, not yet re-verified.** |
| `PSH-S04-03` | Submission checks — title, summary, language, level, future session, price | §3.4 | Competency check soft until S15 ♻️ **Status note lost 2026-10-13, not yet re-verified.** |
| `PSH-S04-04` | Submit for validation → `pending`, notify admins | §3.4 | ♻️ **Status note lost 2026-10-13, not yet re-verified.** |
| `PSH-S04-05` | Admin validation queue: approve / reject, comment **required** on reject | §3.4 | ♻️ **Status note lost 2026-10-13, not yet re-verified.** |
| `PSH-S04-06` | Enforce `review_status` ⟺ `course.visible` in one place | §3.4 | Nothing else writes `course.visible` for hub courses ♻️ **Status note lost 2026-10-13, not yet re-verified.** |
| `PSH-S04-07` | Audit every transition; teacher-facing pending and rejected states | §3.4, §15 | ♻️ **Status note lost 2026-10-13, not yet re-verified.** |
| `PSH-S04-08` | Featured-course selection in the admin portal | §3.3 | ♻️ **Status note lost 2026-10-13, not yet re-verified.** |
| `PSH-S04-09` | Teacher and admin portal styling; validation queue and review states | §6.8 | **B** ♻️ **Status note lost 2026-10-13, not yet re-verified.** |
| `PSH-S04-10` | **Learner dashboard** — stat cards, course grid, progress bars | §12.2 | **B**. The Sharecourse reference ♻️ **Status note lost 2026-10-13, not yet re-verified.** |
| `PSH-S04-11` | My courses and course-experience screens | §12.2 | **B** ♻️ **Status note lost 2026-10-13, not yet re-verified.** |

**Exit:** **AC-1**. A teacher cannot publish by any route, including a direct URL.

---

## Sprint 5 — Live sessions, first light ⛓

| ID | Story | Spec | Notes |
|---|---|---|---|
| `PSH-S05-01` | Session scheduling UI; `local_pshub_session` + BBB instance creation | §3.5 | Duration bounded by the site cap ♻️ **Status note lost 2026-10-13, not yet re-verified.** |
| `PSH-S05-02` | Calendar events for teacher and enrolled learners | §10.4 | ♻️ **Status note lost 2026-10-13, not yet re-verified.** |
| `PSH-S05-03` | Signed, single-use, short-TTL join URLs; moderator vs viewer | §8.1 | ♻️ **Status note lost 2026-10-13, not yet re-verified.** |
| `PSH-S05-04` | Join gating: T-15 window, capacity, access re-checked | §3.6 | Closes **AC-10** ♻️ **Status note lost 2026-10-13, not yet re-verified.** |
| `PSH-S05-05` | ⛓ 50-min cap layer 1 — BBB `duration` at meeting creation | §8.3 | The layer that actually guarantees AC-4 ♻️ **Status note lost 2026-10-13, not yet re-verified.** |
| `PSH-S05-06` | 50-min cap layer 2 — timer and 45/48 min warnings from a **server timestamp** | §8.3 | Mechanism from `PSH-S03-09` ♻️ **Status note lost 2026-10-13, not yet re-verified.** |
| `PSH-S05-07` | 50-min cap layer 3 — sweeper task ending overrunning meetings | §8.3 | ♻️ **Status note lost 2026-10-13, not yet re-verified.** |
| `PSH-S05-08` | **Session lobby and session card** | §12.2 | **B**. The most important screen in the product ♻️ **Status note lost 2026-10-13, not yet re-verified.** |
| `PSH-S05-09` | **AMD module: countdown + join activation** at T-15 | §12.1 | **B**. Never the device clock ♻️ **Status note lost 2026-10-13, not yet re-verified.** |
| `PSH-S05-10` | Join states: too early, open, full, ended, access lapsed | §15 | **B** ♻️ **Status note lost 2026-10-13, not yet re-verified.** |

**Exit:** **AC-4** tested hostilely — clients disconnected, device clocks altered, the meeting still ends. **AC-10**.

---

## Sprint 6 — Live sessions, hardened ⚠️

| ID | Story | Spec | Notes |
|---|---|---|---|
| `PSH-S06-01` | Audio-only fallback on sustained low bitrate | §8.4 | Closes **AC-5** |
| `PSH-S06-02` | Explicit "mode économie de données" — join with video off | §8.4 | |
| `PSH-S06-03` | **Per-participant screen-share suppression** below threshold; admin setting | §8.4 | Closes **AC-19**. Must not affect other participants |
| `PSH-S06-04` | Auto-reconnect preserving place and whiteboard state | §8.4 | Closes **AC-16** |
| `PSH-S06-05` | Attendance capture from BBB events; end-of-session reconciliation | §8.6 | Closes **AC-14** |
| `PSH-S06-06` | Connection-quality telemetry endpoint | §6.4 | |
| `PSH-S06-07` | Teacher guidance: essentials on the whiteboard, not the shared screen | §8.4 | In the scheduling flow, not a manual |
| `PSH-S06-08` | BBB load test against the concurrency target | §13.4 | ⚠️ Needs decision 7 |
| `PSH-S06-09` | **AMD module: in-session connection/quality strip** | §8.4 | **B** |
| `PSH-S06-10` | Degradation and reconnection messaging — calm, once, non-blocking | §15 | **B** |
| `PSH-S06-11` | BBB session branding — logo, navy, gradient | §12.4 | **B**. Smallest possible seam |

---

## Sprint 7 — Payments ⚠️

| ID | Story | Spec | Notes |
|---|---|---|---|
| `PSH-S07-01` | `paygw_pshub` gateway skeleton on `core_payment` | §7.3 | ⚠️ Blocked until decision 8 — needs a sandbox |
| `PSH-S07-02` | MTN MoMo and Orange Money: MSISDN capture, push flow | §9.2 | |
| `PSH-S07-03` | Wave | §9.1 | Retained by arbitration; affects aggregator choice |
| `PSH-S07-04` | Card | §9.1 | No card data on our servers |
| `PSH-S07-05` | Signed callback; verify signature, amount **and** currency | §9.3 | |
| `PSH-S07-06` | Idempotency via unique `provider_ref` — a constraint, not a check | §9.3 | Closes **AC-12**, including concurrent delivery |
| `PSH-S07-07` | Admin validation queue; `paid_unvalidated → validated` grants access | §3.8 | Closes **AC-3** |
| `PSH-S07-08` | Per-method `auto_validate` setting, **default off for every method** | §3.8 | |
| `PSH-S07-09` | Back-office `bank_transfer` / `cash` through the same validated path | §9.1 | Never hand-edit an access right |
| `PSH-S07-10` | Reconciliation task for transactions stuck pending | §9.3 | |
| `PSH-S07-11` | Paywall: method selection, MSISDN entry, pack option (shut in P1) | §3.8 | **B** |
| `PSH-S07-12` | **AMD module: payment status polling** | §9.2 | **B**. Never a spinner that assumes success |
| `PSH-S07-13` | "Paiement reçu — validation en cours" state; receipt and history views | §15 | **B**. Informational, not an error colour |

---

## Sprint 8 — Notifications and calendar

| ID | Story | Spec | Notes |
|---|---|---|---|
| `PSH-S08-01` | Message providers; email and in-app; plain-language preferences | §10.1 | ✅ **Closed 2026-10-11.** **Three providers** - course submitted, course reviewed, session reminder - each sending **in the recipient's language**, with popup and e-mail on by default; e-mail delivery verified through a local catcher. The preferences screen is `/local/pshub/notifications.php`, reached from the account block in the shell all three spaces share: one sentence per thing that happens and a tick box per channel, instead of Moodle's grid of provider names against processor names. **It writes Moodle's own preferences in Moodle's own format** - a different way of asking, not a second place where the answer lives. Proved rather than asserted: with e-mail unticked a real `message_send()` produced 0 e-mails and 1 in-app message, while an untouched account got both. Who is asked what is Moodle's own answer, through each provider's capability - a learner gets one question, a teacher two, an administrator three. A channel is offered only when it can actually deliver, which is why mobile push is absent until a device is registered. **`emailstop` is surfaced honestly**: Moodle checks it before *every* processor, not just e-mail, so it is offered as "send me nothing at all" and the rest of the form is visibly not in force while it is on. Three defects found on the way - `coursereviewed` declared no capability, so every learner was being asked about decisions on courses they cannot submit; `larissa`'s address was `@example.cm`, a real TLD, against the documented promise that no test account can reach a real address; and the app shell had **no way to sign out on a phone** (see `PSH-S03-12`). 16 tests |
| `PSH-S08-02` | Session reminders T-24h / T-1h / T-10min, teacher and learners | §10.2 | ✅ `send_session_reminders`, every minute. **Each reminder has its own window**, closing when the next opens — a test caught that without it a class fifty minutes away fired the day-before AND the hour-before reminder in the same second, the first of them twenty-three hours late. **The message names the time the class starts, never "in one hour"**: cron can be late, and a late reminder that names an interval is worse than none. Verified on the live site: teacher and learner both notified, in French, naming the exact start. Closes **AC-6** |
| `PSH-S08-03` | Reminder idempotency via unique `(sessionid, offset, audience)` | §10.2 | ✅ **The insert is the claim**: the row is written before the message goes, and a duplicate key is how a second run learns it is too late. A `record_exists()` first would leave a gap two overlapping cron runs could both pass through. Verified on the live site — a second pass sent nothing |
| `PSH-S08-04` | Re-queue on reschedule, cancel on cancellation | §10.2 | ✅ Moving a class clears its reminder rows: a new appointment is owed its reminders again. Moving one to this evening does **not** fire yesterday's day-before reminder — the task refuses any whose moment passed before the session existed. A cancelled class reminds nobody |
| `PSH-S08-05` | Course announcements, promotions, validation and payment notifications | §10.3 | |
| `PSH-S08-09` | **A teacher's own diary, across her courses** | §6.8 | New, found 2026-10-11. A teacher has `teacher/sessions.php?courseid=N` - one course at a time - and nothing that answers "what am I teaching this week". She can open the learner's agenda, because she is enrolled, and it works; but it is written for a learner and says "vos prochains cours". `agenda_service` already does the work; what is missing is the teacher's view of it |
| `PSH-S08-10` | **Send the push: a `pshubpush` message processor** | §6.1, §10.1 | New, 2026-10-12. `PSH-S08-06` records where a push could go; nothing sends one. The sender belongs in a **Moodle message processor**, not in the reminder task: that is what makes "on my phone" appear on the preferences screen beside the other two channels, honour `emailstop`, and work for every provider rather than only for reminders. Until it exists the preferences screen correctly offers two channels, because a tick box that delivers nothing is worse than no tick box |
| `PSH-S08-06` | FCM token registration groundwork | §6.1 | ✅ **Closed 2026-10-12.** `local_pshub_push_token` has been in the schema since S1 and nothing wrote it; it does now. `device_service` plus two external functions, `local_pshub_register_device` and `local_pshub_deregister_device`, on a service of our own - **`pshub_app`, deliberately NOT `MOODLE_OFFICIAL_MOBILE_SERVICE`**, which belongs to an application we did not write. **The person is the caller, never a parameter**: there is no userid in either signature, so no app user can register a handset against somebody else's account. The token column is unique because a token belongs to a DEVICE, not a person - a phone gets passed on and the push token does not change, so registering an existing token MOVES it and the previous owner stops being reachable there. Verified over real HTTP: sign in, register, register again (one row, same id), a stranger fails to deregister it, the handset changes hands, an unknown platform is refused in the caller's own language, sign-out removes it. The service ships **disabled**. 15 tests |
| `PSH-S08-07` | Calendar view and iCal export | §10.4 | ✅ **Closed.** `/local/pshub/learn/calendar.php`, in the product's shell and second in the learner's navigation - where every course is live, "when am I next due somewhere" is asked more often than "what else is there". A list of days, not a month grid: a grid gives each day a box too small at 360 px for the only two things that matter. **Every door on it comes from `can_join()`**, so the diary and the lobby cannot disagree - including the case where the verdict is yes and no room exists yet, which offers no button and says why. Export is Moodle's own iCal, not a second writer to keep correct; the page says in as many words that the link is a credential. Verified live: 11 classes over three months, a real `text/calendar` download, no horizontal scroll at 390 px. **Two defects found and fixed on the way** - the horizon was 60 days, which silently hid the second half of every twelve-week course, and the whole site was in `Europe/London` (see ENVIRONMENT §2.3). 18 tests |
| `PSH-S08-08` | Notification and calendar surfaces; branded transactional email templates | §12.4 | **B** |

---

## Sprint 9 — Assessment surfaces and the P1 gate

| ID | Story | Spec | Notes |
|---|---|---|---|
| `PSH-S09-01` | Quiz, assignment and workshop presented without Moodle chrome | §3.9 | |
| `PSH-S09-02` | Security pass: capability coverage, callback signatures, join-URL TTLs | §13.1 | |
| `PSH-S09-03` | Backup **and restore**, into a clean environment | §13.3 | |
| `PSH-S09-04` | **AMD module: optimistic quiz submit, local cache, auto-retry** | §15 | **B**. Closes **AC-15** |
| `PSH-S09-05` | Error-state pass across §15 — every state styled and worded | §15 | **B**. Orange recoverable, red only for unrecoverable. **Known gap found 2026-10-10:** a capability refusal - a learner opening the teacher console, a teacher opening the admin one - is a correct `required_capability_exception` with the right French message, but it renders in **Moodle's** error chrome, not the product's shell. Every other learner route was brought into the shell in S3; this one was not |
| `PSH-S09-06` | Accessibility pass; final polish | §13.5 | **B** |
| `PSH-S09-07` | **Full acceptance pass, AC-1 → AC-21** | §14 | **A+B**. Recorded, not asserted |
| `PSH-S09-08` | Performance verification on real devices and a real network | §13.2 | **A+B** |

---

## Phase 2 🟠 — Epics (detail at the P1 gate)

| Epic | Sprint | Spec | Note |
|---|---|---|---|
| `PSH-E-WS` Web-service API for the app | S10 | §6 | The app is its only consumer - size it to the app's screens. 🟡 **Started early, 2026-10-12/13, because the app decision was taken early.** Four functions on our own `pshub_app` service: `register_device`, `deregister_device`, `get_agenda`, `get_my_courses`. Contract written for the app author in `docs/API-APPLICATION.md`, with every example run against the box. **The API hands over data, not a web page's context** - no `cardtone`, no `langplate`, no `ctalabel`; a field we publish is a field we can never change. One defect found by calling it: `priceformatted` said "0 FCFA" for a free course, which no web page ever showed because every template branches on `isfree` first. Still absent and listed as such: a catalogue endpoint, a join endpoint, anything to do with payment, token expiry, and rate limiting on sign-in. 27 tests |
| `PSH-E-APP` Flutter: auth, catalogue, detail | S11 | §12.3 | <25 MB measured from the first build |
| `PSH-E-APPLIVE` Flutter: sessions, join, push | S12 | §12.3 | BBB in a webview. The join endpoint's shape is already settled by `get_agenda`: a `sessionid` in, the verdict re-checked at that instant, and a room or a reason out - never a boolean, because the app must never decide for itself whether a class can be joined |
| `PSH-E-OFFLINE` Offline cache, Wi-Fi-only downloads | S13 | §5.5 | |
| `PSH-E-XP` XP engine and ledger | S14 | §11.1 | **AC-13** |
| `PSH-E-COMP` Competencies, learning plans, teacher briefs | S15 | §11.2 | Makes the S4 soft check blocking |
| `PSH-E-CERT` Certificates with QR verification | S16 | §11.3 | **AC-7**. ⚠️ Re-check `mod_customcert` 5.3 support before S14 |
| `PSH-E-PACK` Packs go live | S17 | §3.3, §9.1 | **AC-18** P2 half. Can move earlier — the branch exists from S2 |
| `PSH-E-DASH` Dashboard, streaks, groups, kids catalogue | S18 | §12.2, §11.1.1, §11.4, §11.5 | ⚠️ Where Mustache verbosity is felt — keep partials flat |

## Phase 3 🔵 — Epics

| Epic | Sprint | Spec |
|---|---|---|
| `PSH-E-ACCT` Accounting: revenue by course, period, teacher | S19 | §9.4 |
| `PSH-E-EXPORT` CSV/Excel export, receipts, gapless numbering | S20 | §9.4 |
| `PSH-E-REC` Recording, publication, replay, retention | S21 | §8.5 |
| `PSH-E-POLL` In-session polls; SMS channel; field feedback | S22 | §8.2, §10.1 |

---

## Decisions this backlog is waiting on

| Needed by | Decision | Spec |
|---|---|---|
| Before S1 | Team composition — Team A needs Moodle plugin depth, Team B needs Boost theming depth | ROADMAP §0.1 |
| Before S1 | Hosting meets the 5.3 platform floors | §18.2 #10 |
| Before S3 | Signup email confirmation: keep or auto-confirm | §18.2 #4 |
| Before S6 | BBB concurrency target | §18.2 #7 |
| **Before S7** | Payment aggregator vs direct, with Wave coverage | §18.2 #8 |
| Before S14 | `mod_customcert` 5.3 support confirmed, or fall back | §18.2 #6 |
| Before P2 | Pack commercial rules — expiry, overlap with individual purchases | §18.2 #12 |
| Before P2 | Cahier amended to cover the five retained features | §18.2 #13 |
