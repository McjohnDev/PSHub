# Functional Specification — PS Language Hub

**Version:** 3.1 · **Date:** September 2026 · **Status:** Working blueprint (for build)
**Supersedes:** v2.0 (August 2026) — archived at [`docs/archive/functional-spec-v2.0.md`](docs/archive/functional-spec-v2.0.md)
**Derived from:** [*Cahier des charges v2.0*, September 2026](docs/cahier-des-charges-v2.md) + client arbitration, September 2026 (§0.2)
**Architecture:** **Moodle 5.3 LTS** (content engine **and** business layer, via `local_pshub`) + BigBlueButton (live classroom) + `theme_pslanguagehub` server-rendered throughout (web) + Flutter app (learner mobile)

---

## 0. How to use this document

| If you are… | Start with | Then read |
|---|---|---|
| **Developer (Moodle)** | §4 Data model · §5 Auth · §6 API · §7 Moodle usage · §9 Payments | §3 Flows, §13 NFR |
| **Developer (live classroom)** | §8 Live classroom · §3.6 Session lifecycle | §13 NFR, §15 Error states |
| **Developer (web / mobile client)** | §3 Flows · §6 API · §12 Presentation · §15 Error states | §2 Roles, §5 Auth |
| **QA / test** | §14 Acceptance criteria · §3 Flows · §15 Error states | §8, §9 |
| **Product / business** | §1 Scope & phasing · §2 Roles · §3 Flows · §16 KPIs · §17 Constraints | §18 Open decisions |

**Conventions**
- 🟢 **P1** = Phase 1, Fondations (MVP). 🟠 **P2** = Phase 2, Expérience apprenant. 🔵 **P3** = Phase 3, Optimisation. Unmarked = all phases.
- Entity names are English (code identifiers), with the French term from the cahier in parentheses on first use.
- ⚠️ marks a requirement whose feasibility needs field or spike validation before it is committed to a sprint. Every ⚠️ has a matching row in §18.
- Example payloads are illustrative; **field naming is normative**, field formatting is implementation-defined unless stated.

### 0.1 What changed in v3.0 — and why it is a pivot, not an increment

*(v3.1 refines this; the decisions that settled the open rows are in §0.2.)*

v2.0 described an **asynchronous** product: a learner browses a catalogue, opens recorded lesson content, and progress is tracked through Moodle's completion API. Cahier v2.0 replaces that premise:

> **Tous les cours sont dispensés en direct (live).** (cahier §1)

The live session is now the product. Recorded media, quizzes, assignments and workshops are supporting material *around* scheduled live sessions, not the thing being sold. Everything downstream moves with that.

| Area | v2.0 | v3.0–3.1 (this document) | § |
|---|---|---|---|
| **Product core** | Asynchronous lessons + progress | **Live sessions** — BBB visio, whiteboard, screen share | §8 |
| **Plugin surface** | `local_pshub`, `theme_pslanguagehub`, `paygw_pshub` | adds `mod_bigbluebuttonbn` (core), `mod_customcert` ⚠️ | §7.3 |
| **Learner web UI** | Moodle theme, server-rendered | **Server-rendered Mustache, no client framework** — decided, §0.2 | §12.1 |
| **Mobile** | Flutter | **Flutter** — reopened by cahier §3, re-closed §0.2 | §12.3 |
| **Roles** | 4, trainer qualifies by exam | 4, **no qualification exam**; adds *Gestionnaire de site*; admin assigns competencies to teachers | §2 |
| **Publishing** | Teacher publishes directly | **Admin validation** — draft → pending → published/rejected | §3.4 |
| **Payment unlock** | Auto-unlock on aggregator callback | **Admin validation step** before access opens | §3.8, §9 |
| **Currency / methods** | XAF; OM, MoMo, Wave, card | XAF; **MTN MoMo, Orange Money, card** (Wave dropped) | §9 |
| **Competencies** | — | Moodle learning plans, admin-defined, assigned to teachers | §11.2 |
| **Certificates** | Open decision, post-P1 | **In scope** P2, branded, QR-verifiable | §11.3 |
| **Low bandwidth** | Not an NFR | **Critical NFR** — 64 kbit/s floor, <25 MB app, <3 s on 3G | §13.2 |
| **Accounting** | Transaction monitoring | **Accounting module** — revenue by course/period/teacher, exports, receipts | §9.4 |
| **Retained from v2.0** | learning groups, kids catalogue, streaks, course packs, Wave | absent from the cahier, but **kept by client arbitration** (§0.2) | §17.2 |
| **Dropped** | teacher qualification exam, oral-practice repetitions | absent | §17.2 |

**What survives unchanged.** The golden rule and the reason for it:

> **Golden rule:** access authorisation lives in **`local_pshub`**, never in Moodle's per-course enrolment configuration.

One gatekeeper, decoupled from pedagogy. `local_pshub\access\gating_service` remains the single gate; Moodle core remains the content engine; courses are never configured with enrolment keys, cohort rules or `enrol_fee` conditions that would let pedagogy decide access. Single Moodle identity across web and mobile — no second user record, no provisioning step, no sync jobs. Those properties are what make live sessions, payment validation and certificates additive rather than structural.

### 0.2 Client arbitration, September 2026 — decisions closed in v3.1

Where the cahier and the previous spec disagreed, the client arbitrated row by row. v3.1 records the outcome. Four calls changed the architecture or the scope:

| # | Decision | Consequence |
|---|---|---|
| **A** | **The entire web surface is server-rendered Mustache. No client framework, at any phase.** | `theme_pslanguagehub` renders everything; four small AMD modules cover the genuinely interactive pieces (§12.1). No bundle, no hydration, no bootstrap payload, no client router. The `<3 s on 3G` target applies uniformly and is met by construction. *Reached in three steps — SPA, then islands, then a login-boundary split — once it was established that the Sharecourse reference is CSS rather than architecture.* |
| **F** | **Two delivery teams: Moodle/PHP and design/CSS.** | The **template context shape is the contract between them** and must be defined in sprint 1 (§12.1). Team A owns `local_pshub`, `templatable` classes, renderers, gating, workflow, payments, web services and the staff portals; Team B owns the design system, Mustache partials, CSS and the AMD modules. |
| **B** | **Mobile = Flutter.** | Closes the stack question the cahier reopened. With the web server-rendered there was never client code to share, so the React Native argument disappears (§12.3). |
| **C** | **The Flutter app is the only consumer of the web-service API.** Follows from A. | §6 stops being sprint-1 blocking and is sized to the app's screens, in P2 (§6.0). |
| **D** | **Learning groups, kids catalogue, streaks, course packs and Wave return to scope.** | Reverses their removal. Packs are the structural one — pack-level pricing and pack-level access rights re-enter the gating service, the price resolver and the data model, and must be modelled in P1 (§17.2). |
| **E** | **Platform = Moodle 5.3 LTS**, released 5 October 2026. | Security support to **1 October 2029** — three years, matching a build that starts now and runs P1→P3. Sets hard infrastructure minimums: **PHP 8.3** (8.4 supported), 64-bit only, **PostgreSQL 16** or **MySQL 8.4** / **MariaDB 10.11** (§13.6). |

Also confirmed: **payment validation merges both models** — per-method auto-validation for trusted gateways, manual queue for everything else, through one state transition (§3.8, already specified); and **screen share auto-suppresses below a bitrate threshold** so that the 64 kbit/s audio-plus-whiteboard floor and the screen-share requirement can both hold (§8.4, now a requirement rather than a proposal).

> The cahier does not mention the five features in **D**. It needs amending to match, or the two documents will disagree the next time someone reads them side by side.

### 0.3 Repository state

As of this revision the repository contains **specifications and brand assets only**. No code has been written; the sprint history referenced by earlier revisions of `README.md` (M1–M5 "shipped", `local/pshub/`, `theme/pslanguagehub/`, `apps/mobile/`) does not exist on disk. This spec is written as **greenfield**.

---

## 1. Scope & phasing

### 1.1 Phase matrix

Phases follow cahier §8. Within a phase, capabilities are ordered roughly by build dependency.

| Capability | 🟢 P1 | 🟠 P2 | 🔵 P3 |
|---|:--:|:--:|:--:|
| **Moodle 5.3 LTS** + BigBlueButton installed, branded, bilingual FR/EN (§13.6) | ✅ | | |
| Four roles with Moodle capability mapping (§2) | ✅ | | |
| Course types & badges, admin-configurable **without code** (§3.3) | ✅ | | |
| Course authoring → **admin validation** → publication (§3.4) | ✅ | | |
| Public site: home, featured courses, catalogue, signup/login | ✅ | | |
| Discreet staff login link → Moodle (§3.2) | ✅ | | |
| App-store download links on the home page | ✅ | | |
| **Live sessions**: visio, screen share, collaborative whiteboard, chat, hand-raise | ✅ | | |
| 50-minute cap: visible timer, 45/48 min warnings, auto-close | ✅ | | |
| Free enrolment → immediate access | ✅ | | |
| Payment: MTN MoMo + Orange Money + Wave → **admin validation** → access opens | ✅ | | |
| Card payment | ✅ | | |
| Course **packs**: tables, gating branch and price resolver | modelled, inactive | ✅ sold | |
| Notifications: course announcements; session reminders 24 h / 1 h / 10 min | ✅ | | |
| Calendar of upcoming sessions and due work | ✅ | | |
| Quizzes, assignments, workshops (peer assessment) | ✅ | | |
| Audio-only fallback on bandwidth collapse (basic) | ✅ | | |
| Learner mobile app (Android first, then iOS) | | ✅ | |
| Advanced low-bandwidth: adaptive video ladder, offline cache, Wi-Fi-only downloads | | ✅ | |
| XP engine with admin-configurable scale | | ✅ | |
| **Streaks** — consecutive-day activity, surfaced on the dashboard | | ✅ | |
| **Kids catalogue** — `audience` segmentation, age-appropriate surface | | ✅ | |
| **Learning groups** — cohort grouping + weekly picks | | ✅ | |
| Competency frameworks & learning plans | | ✅ | |
| Certificates: auto-issue, branded, QR verification | | ✅ | |
| Full learner dashboard (Sharecourse reference) | | ✅ | |
| Accounting: revenue by course / period / teacher, CSV-Excel export, receipts | | | ✅ |
| Session recording & replay | | | ✅ |
| In-session polls | | | ✅ |
| SMS notification channel (optional) | | | ✅ |

> **Design mandate (carried from v2.0).** Model the access-right, transaction, **pack**, XP, competency-assignment, group and session-attendance tables in **P1**, even where the features that read them ship later. The P1→P2 step is then a change in *logic and UI only*, with no schema refactor. This is what keeps certificates, XP and packs to a single sprint each.
>
> Packs are the reason this mandate earns its keep. A pack grants access to several courses at a bundled price, so `target_type=pack` has to exist on the access right and in `gating_service` from day one — retrofitting a second grant dimension into a live gating service, after money has changed hands against the old shape, is the expensive version of this work.

### 1.2 Out of scope (all phases)

- Refactoring Moodle's authoring interface. Teachers author in **standard Moodle** (cahier §3, "principe clé").
- Full course creation from the mobile app — mobile is a **learner surface only**.
- Marketplace mechanics: teacher ratings, commission engine, payouts.
- Asynchronous-only courses with no live component. Every course has at least one scheduled session.
- Moodle plugins beyond those listed in §7.3.
- Detailed financial modelling (CAC, revenue projection, hosting cost) — belongs to a business-plan document.

---

## 2. Roles & actors

The cahier defines four human roles. They map onto Moodle as follows. **No role invents its own authorisation**: every surface checks a Moodle capability (§5.3).

| Role (cahier) | Moodle realisation | Primary surface |
|---|---|---|
| **Gestionnaire de site** (1 account) | Moodle **site administrator** | Moodle admin UI |
| **Administrateur principal** | Custom system role `pshub_admin`, holding the `local/pshub:*` admin capabilities | Admin portal `/local/pshub/admin/*` + Moodle |
| **Enseignant** (Teacher) | `editingteacher` in own courses + `local/pshub:viewteacherportal` | Teacher portal `/local/pshub/teacher/*` + Moodle for authoring |
| **Apprenant** (Learner) | `student` in enrolled courses | Learner web surface + mobile app — **never raw Moodle** |

Plus two non-human actors:

| Actor | Responsibility |
|---|---|
| **`local_pshub`** | Gatekeeper: access rights, catalogue metadata, course-validation workflow, session orchestration, XP, payments, web services, audit |
| **Moodle core** | Content engine: users, courses, enrolment, activities, grades, completion, competencies, files, messaging |
| **BigBlueButton** | Live classroom: media, whiteboard, recording (§8) |
| **Payment provider** | **Flutterwave** (aggregator): MTN MoMo, Orange Money, card — decision 8, closed 2026-10-03 (§9.1) |

### 2.1 Role boundaries that matter

1. **The Gestionnaire creates admins; admins do not create admins.** Only the site administrator may grant `pshub_admin`. This keeps the "1 compte" constraint of cahier §4.1 enforceable.
2. **Teachers never self-publish.** `local/pshub:publishcourse` belongs to `pshub_admin` alone. A teacher holds `local/pshub:submitcourse`. See §3.4 and AC-1.
3. **Teachers work inside a brief.** An admin assigns competencies and pedagogical requirements to a teacher (cahier §4.2); the teacher portal surfaces that brief, and course submission is checked against it (§3.4, §11.2).
4. **Learners never see Moodle chrome.** Any learner-facing route that would land on a Moodle page — `/enrol/index.php`, `/course/view.php`, an enrolment-key prompt, a "you are not enrolled" interstitial — is suppressed or redirected to the equivalent learner surface. The one sanctioned exception is the BBB session window (§8.1).

---

## 3. User flows

Each flow lists **actor → goal → success criteria**, then the step sequence. The learner interface must never expose a Moodle page, a system name, an enrolment step, or a raw error code.

### 3.1 Registration 🟢

**Actor:** Learner · **Goal:** create an account and be ready to enrol.
**Success:** a Moodle account exists, a session (web) or token (mobile) is issued, learner lands on the dashboard.

```
Learner submits {name, email, password, phone?, consent}
  → Moodle creates the user (signup)
  → local_pshub writes local_pshub_user_profile (platform_role=learner, phone, lang)
  → web: Moodle session      mobile: web-service token
  → Dashboard
```

- Email uniqueness enforced by Moodle. One user record; there is no second system to fall out of sync with.
- `phone` is collected here because Mobile Money checkout needs an MSISDN (§9.2). Optional at signup, required at first paid checkout.
- Social login (Google / Apple) via Moodle OAuth2 — same account, same profile row.
- **Email confirmation is required** (decision 4, closed 2026-09-28). A new account is created unconfirmed; the learner cannot sign in until they follow the link sent to their address. Verified: the account exists with `confirmed = 0`, sign-in is refused with "Vous devez confirmer l'enregistrement de votre compte", and the link confirms it.

  Three things follow, and none is optional:
  1. **A working transactional mailer is a launch requirement**, with SPF, DKIM and DMARC. Signup silently becomes impossible without one — on the development box, an unreachable SMTP server did not fail, it *hung*, leaving an unconfirmed account and a browser that never came back.
  2. **A resend path matters.** On the networks §13.2 is written around, a mail that does not arrive within a minute is a lost learner. Moodle has `/login/forgot_password.php` for passwords; confirmation resend needs its own route or an admin action.
  3. **A mistyped address is unrecoverable by the learner.** They cannot sign in to correct it, and the account holds the phone number they gave. Support needs a way to correct an address or delete an unconfirmed account.

### 3.2 Login 🟢 — learners, and the discreet staff door

**Learner:** one identity across both surfaces. Web uses the Moodle session; mobile calls `local_pshub_mobile_login` and stores a web-service token (§5.1).

**Staff (cahier §5.1):** the public site footer carries a **discreet link** — *Espace enseignant / administration* — pointing at Moodle's own login. After authentication, Moodle's landing rules route by capability:

| Capability held | Lands on |
|---|---|
| `local/pshub:viewadminportal` | Admin portal |
| `local/pshub:viewteacherportal` | Teacher portal |
| neither | Learner dashboard |

The link is unstyled-plain and excluded from the sitemap; it is *discreet*, not *secret* — it carries no security weight, and §5.3 does the actual work.

### 3.3 Catalogue, course types & badges 🟢

**Actor:** Visitor or learner · **Goal:** find a course.

The public home page shows **featured courses** chosen by the admin (`local_pshub_course.is_featured`, ordered by `featured_order`), each rendered as a card carrying its badges. The catalogue is **freely browsable**; signup/login is required to enrol or to open any course content (AC-2).

**Course types are data, not code** (cahier §5.2, AC-8). An admin creates them in the admin portal:

| Field | Purpose |
|---|---|
| `code` | stable identifier, e.g. `promo20`, `hot`, `free`, `exam` |
| `name_fr`, `name_en` | badge label |
| `kind` | `discount` \| `highlight` \| `free` \| `exam` \| `custom` — drives behaviour |
| `discount_percent` | for `kind=discount`; renders a struck-through original price |
| `grants_free_access` | for `kind=free`; forces `access_type=free` in gating |
| `badge_style` | brand colour token (§12.4) |
| `sortorder`, `is_active` | display control |

A course may carry **several** types (*Hot* and *-20 %* together). Effective price is computed as: base `price_amount`, minus the single largest active `discount` type, floored at 0; any `grants_free_access` type short-circuits to free. Adding a new type is an admin form submission — **no deployment** (AC-8).

**Packs** 🟠 sit beside this, not inside it. A pack has its own `price_amount` covering every course it contains; a learner holding a pack-level access right reaches all of them (§3.7). Course-level discounts do **not** stack onto a pack price — the pack price is the price. Where a course belongs to a pack the catalogue shows both routes, and the cheaper one first.

### 3.4 Course authoring & admin validation 🟢 — the workflow the cahier adds

**Actor:** Teacher, then Admin · **Goal:** get a course live.
**Success:** a course is publicly visible only after an admin approves it.

```
Admin assigns a brief to the teacher
  → local_pshub_teacher_assignment (competencies + requirements + due date)
Teacher opens the teacher portal → "Créer un cours"
  → local_pshub creates a Moodle course (hidden) + local_pshub_course(review_status=draft)
  → Teacher authors in standard Moodle: activities, resources, BBB session(s)
  → Teacher schedules live sessions (§3.5)
Teacher clicks "Soumettre pour validation"
  → submission checks run (below) → review_status=pending, submitted_at, notify admins
Admin opens the validation queue
  ├─ Approve  → review_status=published, course.visible=1, audit row, notify teacher
  └─ Reject   → review_status=rejected + review_comment (required), notify teacher
                 → teacher edits → resubmit (back to pending)
```

**Submission checks** (blocking, shown inline in the teacher portal):

1. Course has a title, summary, language and level.
2. At least one live session is scheduled in the future.
3. Every competency in the teacher's brief is mapped to at least one activity ⚠️ *(soft check in P1 — competencies ship in P2; enforced from P2, see §11.2)*.
4. Price is set, or a `free`-kind type is attached.

**Invariants**

- `review_status=published` ⟺ `course.visible=1`. `local_pshub` owns the transition; nothing else writes `course.visible` for hub courses.
- A course leaving `published` (archived or re-submitted for a major edit) hides it from the catalogue but **does not revoke access rights** of already-enrolled learners — their scheduled sessions still run.
- Every transition writes a `local_pshub_audit` row: actor, from-state, to-state, comment, timestamp, IP (cahier §7, journaux d'audit).

### 3.5 Scheduling a live session 🟢

**Actor:** Teacher (or admin) · **Goal:** put a session on the calendar.

```
Teacher portal → "Planifier une session"
  {title, date, start time, duration (default 50 min), 1-to-1 or group,
   max participants, recording on/off 🔵}
  → local_pshub creates a mod_bigbluebuttonbn instance in the course
  → local_pshub_session row links course ↔ cm ↔ teacher ↔ schedule
  → Moodle calendar event for teacher + enrolled learners
  → reminder jobs queued at T-24h, T-1h, T-10min (§10.2)
```

Duration defaults to the site setting (50 min) and is bounded by it — a teacher may schedule *shorter*, never longer, unless an admin raises the site cap (cahier §5.3, "paramétrable par l'admin").

### 3.6 Attending a live session 🟢 — the core loop

**Actor:** Learner and Teacher · **Goal:** hold the class.
**Success:** both join with one tap; audio, whiteboard and screen share work; the session ends cleanly at the cap; attendance is recorded.

```
T-15 min   "Rejoindre maintenant" activates on the dashboard card, with countdown
Learner taps
  → is_enrolled(course) AND gating_service::check(course)   — see below
  → local_pshub_get_session_join_url   — returns a signed, single-use BBB join URL
  → BBB client opens (§8.1)
In session  audio · video · whiteboard · screen share · chat · hand raise
            timer visible; warnings at 45 and 48 min
T+50 min   BBB ends the meeting; clients return to the course page
After      attendance reconciled → local_pshub_session_attendance
            → XP awarded 🟠 (§11.1)
            → recording published 🔵 (§8.5)
```

**Two checks at the door, not one** (added 2026-10-04, after a test caught it).
`gating_service::check()` answers *may this person obtain access* — and on a **free**
course that is yes to anybody who asks. It is the right question for a paywall and the
wrong one for a door. Walking into a session also requires an **active enrolment**, which
is what makes someone a participant rather than a prospect, and what keeps out a learner
whose enrolment was suspended on a course that happens to be free. The gating check stays,
second: an enrolment can outlive the payment that justified it.



Join is **gated on every attempt** — never on a cached client-side flag. A learner whose access lapsed between enrolment and session start is stopped here (AC-10).

### 3.7 Access gating (decision logic)

Applied **before every sensitive action**: opening a course, joining a session, starting a quiz, downloading a resource. Implemented in `local_pshub\access\gating_service::check()` and called by *both* the server-rendered pages and *every* web-service function. Clients are never trusted.

| Course access type | Condition | Outcome |
|---|---|---|
| **free** | — | Grant. Enrolment additionally creates/confirms the auto access right. |
| **paid** | valid course-level access right (`status=active`, not expired) | Grant. |
| **paid, in a pack** 🟠 | valid **pack-level** access right | Grant — for every course in that pack. |
| **paid** | transaction exists, `status=paid_unvalidated` | Deny `payment_pending` → client shows "Paiement en cours de validation" (§15) |
| **paid** | no right, no transaction | Deny `payment_required` → paywall, offering course **and** pack (§3.8) |
| **any** | course not `published` and viewer is not its teacher or an admin | Deny `not_available` |

> In P1 the **pack branch ships but cannot grant** — it is built, unit-tested, and held shut by `are_packs_enabled()`, false for all of P1. P2 opens it with no schema change and no course reconfiguration. This is the same technique v2.0 used to hold monetisation shut, and it is what makes AC-18 assertable rather than aspirational.

### 3.8 Payment with admin validation 🟢

**Actor:** Learner, then Admin · **Goal:** unlock a paid course.
**Success:** payment recorded → admin validates → access opens → learner returns to the exact spot.

This is the flow that changed most from v2.0. The cahier requires an **administrator validation step** (§5.8) between payment and access.

```
Paywall → choose method (MTN MoMo | Orange Money | card | transfer)
  → core_payment checkout via paygw_pshub
  → local_pshub_transaction(status=pending)
  → provider flow: MoMo push to the payer's handset, or card redirect

Provider confirms
  → signed callback → POST /local/pshub/payment/callback.php
  → verify signature + amount + currency
  → transaction.status = paid_unvalidated      (idempotent on provider_ref)
  → notify admins: "paiement à valider"
  → learner sees "Paiement reçu, validation en cours"

Admin opens the payments queue
  ├─ Validate → status=validated, validated_by/at
  │             → local_pshub_access(source=payment, status=active)
  │             → Moodle enrolment
  │             → notify learner: access open
  │             → receipt generated 🔵 (§9.4)
  └─ Reject   → status=rejected + note → notify learner → refund handled offline
```

**Auto-validation.** Cahier §5.8 allows validation to be *"automatique pour les passerelles fiables, manuelle pour les virements/dépôts"*. This is a **per-method admin setting**: `auto_validate` on/off for each of `mtn_momo`, `orange_money`, `card`, `bank_transfer`, `cash`. When on, a verified callback moves the transaction straight to `validated` and runs the grant — the manual queue is skipped, the audit row still written. Default at launch: **off for every method**, so the first weeks of trading are fully reviewed; turn on per method once reconciliation is trusted.

> **Design note.** The queue is deliberately a *state on the transaction*, not a separate approval table. `paid_unvalidated → validated` is the only transition that grants access, whether an admin or the auto-validator performs it. One code path, one audit shape, one thing to test.

### 3.9 Assessment 🟢

Quizzes (`mod_quiz`), assignments (`mod_assign`) and workshops (`mod_workshop`, peer assessment) are **Moodle core**, unchanged. `local_pshub` presents them; the client renders them; the learner never sees a module URL or an attempt id. Grading, attempts and feedback belong to Moodle — there is no second grade store.

### 3.10 Progression, competencies and certificate 🟠

```
Live attendance + quiz pass + assignment + workshop
  → Moodle completion + competency evidence
  → local_pshub XP ledger (§11.1)
  → course completion
  → cursus (programme) completion
  → certificate issued automatically, branded, QR-verifiable (§11.3)
  → learner dashboard reflects XP, competencies, certificates
```

---

## 4. Data model

### 4.1 Where things live

Commercial, scheduling and platform entities live in **`local_pshub` tables**. Pedagogical data lives in **Moodle core** and is referenced by id. Nothing is duplicated.

**`local_pshub_user_profile`** — one row per user
| Field | Type | Notes |
|---|---|---|
| `userid` | int | → `user.id`, **unique** |
| `platform_role` | enum(`learner`,`teacher`,`admin`,`manager`) | mirrors the Moodle role assignment; display only — never used for authorisation |
| `phone` | char | MSISDN for Mobile Money; E.164 |
| `preferred_lang` | char | `fr` \| `en` |
| `audience` | enum(`adult`,`kids`) 🟠 | catalogue segmentation; modelled P1, drives the kids catalogue from P2 |
| `timecreated`, `timemodified` | int | |

**`local_pshub_course_type`** — the parameterisable badges of §3.3
| Field | Type | Notes |
|---|---|---|
| `id`, `code` | int / char | `code` unique |
| `name_fr`, `name_en` | char | badge label |
| `kind` | enum(`discount`,`highlight`,`free`,`exam`,`custom`) | drives behaviour |
| `discount_percent` | int | 0–100, for `kind=discount` |
| `grants_free_access` | int | for `kind=free` |
| `badge_style` | char | brand colour token |
| `sortorder`, `is_active` | int | |

**`local_pshub_course_type_map`** — `courseid` × `typeid`, unique pair. A course carries many types.

**`local_pshub_course`** — catalogue, commercial and workflow metadata beside a Moodle course
| Field | Type | Notes |
|---|---|---|
| `courseid` | int | → `course.id`, **unique** |
| `categoryid` | int | → `course_categories.id` |
| `language`, `level` | char | catalogue + filtering; `level` is CEFR A1–C2 |
| `access_type` | enum(`free`,`paid`) | **the gating lever** (§3.7) |
| `price_amount`, `price_currency` | int / char | minor units; `XAF` |
| `review_status` | enum(`draft`,`pending`,`published`,`rejected`,`archived`) | §3.4 |
| `submittedby`, `submitted_at` | int | |
| `reviewedby`, `reviewed_at`, `review_comment` | int / char | comment **required** on reject |
| `is_featured`, `featured_order` | int | home-page selection (cahier §5.1) |
| `session_duration_minutes` | int | default from site setting (50) |
| `max_participants` | int | null = unlimited |
| `allow_recording` | int | 🔵 |
| `is_one_to_one` | int | 1-to-1 vs group (cahier §5.3) |
| `audience` | enum(`adult`,`kids`) 🟠 | catalogue segmentation |

**`local_pshub_pack`** (Pack) 🟠 — a bundle sold at one price
| Field | Type | Notes |
|---|---|---|
| `id`, `name_fr`, `name_en` | int / char | |
| `price_amount`, `price_currency` | int / char | minor units; `XAF`. **This is the price** — course discounts do not apply (§3.3) |
| `is_active` | int | |
| `sortorder` | int | |

**`local_pshub_pack_course`** 🟠 — `packid` × `courseid`, unique pair. A course may sit in more than one pack.

**`local_pshub_group`** (Groupe d'apprentissage) 🟠 — `id`, `name`, `courseid` (nullable), `level`, `capacity`, `is_active`. Membership in **`local_pshub_group_member`** — `groupid`, `userid`, `joined_at`, unique pair. Weekly picks are a derived view over group activity, not a stored table.

**`local_pshub_session`** (Session live) — a scheduled class
| Field | Type | Notes |
|---|---|---|
| `id`, `courseid` | int | |
| `bbb_cmid` | int | → the `mod_bigbluebuttonbn` course module |
| `teacherid` | int | → `user.id` |
| `title` | char | |
| `scheduled_start`, `scheduled_end` | int | |
| `duration_minutes` | int | ≤ site cap |
| `status` | enum(`scheduled`,`live`,`ended`,`cancelled`) | |
| `actual_start`, `actual_end` | int | reconciled from BBB |
| `recording_enabled`, `recording_url` | int / char | 🔵 |

**`local_pshub_session_attendance`** — who was actually there
`sessionid`, `userid`, `role` (`moderator`\|`viewer`), `joined_at`, `left_at`, `duration_seconds`, `counted_present` (int — duration ≥ the admin threshold), `xp_awarded`. Unique on `(sessionid, userid)`.

**`local_pshub_access`** (Droit d'accès) — *the heart of gating*
| Field | Type | Notes |
|---|---|---|
| `userid` | int | → `user.id` |
| `target_type` | enum(`course`,`pack`,`programme`) | `pack` 🟠 grants every course in the bundle; `programme` reserved for cursus-level rights |
| `target_id` | int | course, pack or programme id |
| `source` | enum(`auto`,`payment`,`manual`) | `manual` = support grant |
| `status` | enum(`active`,`pending`,`expired`,`revoked`) | |
| `expires_at` | int | nullable = perpetual |
| `grantedby`, `grantreason` | int / char | audit trail |

Unique on `(userid, target_type, target_id)`.

**`local_pshub_transaction`** — payment and its validation
| Field | Type | Notes |
|---|---|---|
| `userid`, `accessid` | int | `accessid` null until granted |
| `target_type`, `target_id` | enum(`course`,`pack`) / int | what was bought — mirrors the access right it will create |
| `amount`, `currency` | int / char | minor units; `XAF` |
| `method` | enum(`mtn_momo`,`orange_money`,`wave`,`card`,`bank_transfer`,`cash`) | |
| `provider`, `provider_ref` | char | `provider_ref` **unique** — carries webhook idempotency |
| `status` | enum(`pending`,`paid_unvalidated`,`validated`,`rejected`,`failed`,`refunded`) | §3.8 |
| `payer_msisdn` | char | Mobile Money payer |
| `validated_by`, `validated_at`, `validation_note` | int / char | who opened access, and why |
| `receipt_number` | char | 🔵 sequential, gapless |
| `timecreated`, `timemodified` | int | |

**`local_pshub_teacher_assignment`** — the admin's brief to a teacher (cahier §4.2)
`id`, `teacherid`, `frameworkid`, `competencyid` (nullable), `courseid` (nullable), `brief` (text), `assignedby`, `assigned_at`, `due_at`, `status` (`open`\|`in_progress`\|`met`\|`waived`).

**`local_pshub_xp_rule`** — the admin-configurable scale (cahier §5.7)
`id`, `event_key` (`live_attendance`\|`quiz_passed`\|`assignment_submitted`\|`workshop_completed`\|`course_completed`), `points`, `daily_cap`, `is_active`.

**`local_pshub_xp_ledger`** — append-only
`userid`, `event_key`, `points`, `contextid`, `itemid`, `timecreated`. Unique on `(userid, event_key, itemid)` — **this unique index is the idempotency guarantee**, not a code check (AC-13).

**`local_pshub_learner`** — read-model snapshot: `userid`, `xp_total`, `level`, `streak_days` 🟠, `longest_streak` 🟠, `sessions_attended`, `certificates_count`, `last_activity`. Derivable from the ledger and Moodle's logs; kept for cheap dashboard reads.

**`local_pshub_reminder`** — `sessionid`, `offset_minutes` (1440 \| 60 \| 10), `audience` (`teacher`\|`learners`), `sent_at`. Unique on `(sessionid, offset_minutes, audience)` — a reminder is sent once even if cron runs twice (AC-6).

**`local_pshub_push_token`** — `userid`, `token`, `platform` (`android`\|`ios`), `timemodified`.

**`local_pshub_audit`** — `actorid`, `action`, `target_type`, `target_id`, `before_json`, `after_json`, `ip`, `timecreated`. Written on **every** course-validation and payment-validation transition (cahier §7).

### 4.2 Relationships

```
user 1───1 local_pshub_user_profile
user 1───* local_pshub_access *───1 (course | local_pshub_pack | programme)
user 1───* local_pshub_transaction 1───0..1 local_pshub_access
user 1───* local_pshub_xp_ledger
user 1───1 local_pshub_learner
user *───* local_pshub_group  (via local_pshub_group_member)
course 1───1 local_pshub_course *───* local_pshub_course_type
course *───* local_pshub_pack  (via local_pshub_pack_course)
course 1───* local_pshub_session 1───1 mod_bigbluebuttonbn (cm)
local_pshub_session 1───* local_pshub_session_attendance *───1 user
local_pshub_session 1───* local_pshub_reminder
teacher(user) 1───* local_pshub_teacher_assignment *───1 competency
```

### 4.3 Moodle-owned data (referenced, never duplicated)

Course structure and sections, resources and activities, quiz questions/attempts/grades, assignment submissions, workshop assessments, completion records, badges, competency frameworks and learning plans, files, calendar events, messages. `local_pshub` stores ids and platform metadata only.

---

## 5. Authentication & authorization

### 5.1 Sessions and tokens

- **Web (learner and staff):** the **Moodle session**, throughout. The web surface is server-rendered, so there is no token flow, no refresh, and no client-side logout (§12.1). The four AMD modules run inside authenticated pages and inherit the session; where one calls the server it uses a Moodle AJAX endpoint under the session and its `sesskey`, never the web-service API.
- **Mobile:** Moodle **web-service token** from `local_pshub_mobile_login`, stored in the platform keystore, revoked server-side on logout.
- **BBB join:** a signed, short-lived, single-use join URL minted per user per session (§8.1). Never a long-lived meeting link.
- All traffic over **HTTPS/TLS**.

### 5.2 Single identity

One Moodle account serves web and mobile. No second identity, no SSO handshake between systems, nothing to reconcile. OAuth2 (Google/Apple) is additive: Moodle acts as OAuth2 client, the handshake ends by issuing the same web-service token, and password and social accounts on the same email resolve to one user.

### 5.3 Authorization — capabilities

Enforced by **Moodle capabilities**, checked inside every web-service function and every portal page — never by route configuration or client state.

| Capability | learner | teacher | `pshub_admin` | site admin |
|---|:--:|:--:|:--:|:--:|
| Catalogue, own profile, own progress | ✅ | ✅ | ✅ | ✅ |
| Enrol, join sessions, activities | ✅ (gated) | ✅ | ✅ | ✅ |
| `local/pshub:viewteacherportal` | — | ✅ | ✅ | ✅ |
| `local/pshub:submitcourse` | — | ✅ | ✅ | ✅ |
| `local/pshub:schedulesession` | — | ✅ | ✅ | ✅ |
| `local/pshub:viewadminportal` | — | — | ✅ | ✅ |
| `local/pshub:publishcourse` (approve / reject) | — | — | ✅ | ✅ |
| `local/pshub:validatepayment` | — | — | ✅ | ✅ |
| `local/pshub:managecoursetypes` | — | — | ✅ | ✅ |
| `local/pshub:manageteachers` (briefs, competency assignment) | — | — | ✅ | ✅ |
| `local/pshub:managexprules` | — | — | ✅ | ✅ |
| `local/pshub:viewaccounting` | — | — | ✅ | ✅ |
| `local/pshub:manageadmins` | — | — | — | ✅ |
| Payment callback | — | — | — | signature auth only, no session |

A learner holding a stale token or a crafted request reaching an admin function is refused by the capability check, not by the absence of a link (AC-11).

---

## 6. API specification (Moodle web services)

Clients call Moodle web-service functions on the site itself. **There is no separate API host.** Functions are exposed through the service `ps_language_hub`; `local_pshub_*` are platform functions, `core_*` / `mod_*` are Moodle core called directly.

### 6.0 Who consumes this, and when

**The Flutter app is the only consumer** (§0.2 decision C). The learner web surface is server-rendered and reaches `local_pshub` services directly in PHP — it never calls a web-service function. That has two consequences worth stating before anyone plans a sprint:

1. **This section is P2 work, not P1.** Nothing in P1 blocks on it. Build the services first, as plain PHP in `local_pshub`; the web-service layer is a thin serialisation skin over them, added when the app is built.
2. **Scope it to the app's screens, not to the whole product.** The teacher and admin portals are web-only (§6.8) and need no functions at all. Functions below that exist only to serve an admin or teacher view are marked as such and can be dropped if the app never grows those screens.

The services underneath must still be written as if the API existed — one gating service, one price resolver, one XP engine, called identically by a renderer and by a web-service function. That discipline is what §7.4 is protecting, and skipping it is how a second, subtly different gating implementation gets born.

### 6.1 Auth
```
core_auth_request_signup                  register
local_pshub_mobile_login                  → { token, role, profile }
core_webservice_get_site_info             session validity + site metadata
local_pshub_get_profile                   → { user, profile, xp, certificates }
local_pshub_register_push_token           device token for reminders
```

### 6.2 Catalogue & discovery 🟢
```
local_pshub_get_featured_courses          home-page selection, with badges
local_pshub_get_catalogue                 filters: language, level, type, access, q, page
local_pshub_get_course                    detail + upcoming sessions + enrolled flag + effective price
local_pshub_get_catalogue_facets          languages, levels, course types
```

### 6.3 Enrolment & access 🟢
```
local_pshub_enrol_course                  gated (§3.7); may return payment_required
local_pshub_get_my_courses                in progress / completed
local_pshub_get_access_rights             the learner's own rights, course- and pack-level
local_pshub_get_packs                     🟠 bundles, contents, pack price
```

### 6.4 Live sessions 🟢 — new in v3.x
```
local_pshub_get_upcoming_sessions         dashboard + calendar; includes countdown target
local_pshub_get_session                    detail, teacher, status, join window
local_pshub_get_session_join_url           gated; mints a signed single-use BBB join URL
local_pshub_get_session_status             live | scheduled | ended; server clock for the timer
local_pshub_report_connection_quality      client telemetry feeding the low-bandwidth mode (§8.4)
local_pshub_get_session_recording          🔵 replay URL when published
```

### 6.5 Activities 🟢
```
local_pshub_get_course_contents            sections + activities, presented, no Moodle URLs
mod_quiz_*                                 attempt lifecycle (core)
mod_assign_*                               submission + feedback (core)
mod_workshop_*                             peer assessment (core)
core_completion_update_activity_completion_status_manually
webservice/pluginfile.php                  media — per-user, permission-checked, native
```

### 6.6 Gamification, competencies, certificates 🟠
```
local_pshub_get_gamification               xp, level, badges, streak_days
local_pshub_get_competencies               learning plan progress
local_pshub_get_certificates               issued certificates + verification URLs
local_pshub_get_group                      🟠 learning group + weekly picks
local_pshub_get_kids_catalogue             🟠 audience-filtered catalogue
```

### 6.7 Payments 🟢
```
core_payment + paygw_pshub                 checkout (§3.8)
local_pshub_get_transactions               the learner's own payment history
/local/pshub/payment/callback.php          provider → site. Signed, sessionless,
                                           idempotent on provider_ref, always acks 200.
/local/pshub/certificate/verify.php        public QR verification endpoint 🟠
```

### 6.8 Teacher & admin

The teacher and admin surfaces are **websites**, not APIs — `/local/pshub/teacher/*` and `/local/pshub/admin/*`, capability-gated per §5.3. The mobile app never reaches them.

| Teacher portal | Admin portal |
|---|---|
| My courses + review status | Course validation queue |
| Submit for validation | Payment validation queue |
| Schedule / edit sessions | Course types & badges (§3.3) |
| My brief (assigned competencies) | Featured-course selection |
| Learner progress, grades, attendance | Teachers: accounts, briefs, activity |
| Upcoming sessions + reminders | Pricing & promotions · **Packs** 🟠 |
| | **Learning groups** 🟠 · Kids catalogue 🟠 |
| | XP rules, competency frameworks, certificate templates |
| | Accounting & exports 🔵 · Audit log |

### 6.9 Errors

Web-service functions raise Moodle exceptions carrying a **stable identifier**. Clients map identifiers to user-facing copy per §15. A learner never sees an identifier. Identifiers in use: `payment_required`, `payment_pending`, `not_available`, `session_not_open`, `session_full`, `session_ended`, `access_expired`, `capability_denied`.

---

## 7. Moodle & plugin usage

### 7.1 What core provides

| Purpose | Mechanism |
|---|---|
| Accounts, auth, OAuth2 | `core_user`, auth plugins |
| Courses, sections, activities | course core |
| Enrolment | manual enrolment, driven **only** by `local_pshub` |
| **Live classroom** | `mod_bigbluebuttonbn` (bundled with core) |
| Quizzes, assignments, peer assessment | `mod_quiz`, `mod_assign`, `mod_workshop` |
| Progress | completion API |
| Competencies & learning plans | `core_competency` |
| Achievements | `core_badges`, issued by `local_pshub` rules |
| Media | files API + `webservice/pluginfile.php` |
| Notifications | message providers + `\core\task` scheduled and ad-hoc tasks |
| Calendar | `core_calendar` events per session |
| Payments | `core_payment` + `paygw_pshub` |

### 7.2 No sync flows

Data is written once, where it lives. There is no user-provisioning job, no catalogue mirror, no queued enrolment or completion sync. This is a direct consequence of the single-identity architecture and it is worth defending against any future "let's cache it over here" proposal.

### 7.3 Plugin surface

| Plugin | Origin | Purpose |
|---|---|---|
| `local_pshub` | **ours** | Business layer: gating, catalogue, workflow, sessions, XP, payments, portals, web services, audit |
| `theme_pslanguagehub` | **ours** | Branded presentation: every page, server-rendered Mustache, plus four AMD modules (§12.1) |
| `paygw_pshub` | **ours** | MTN MoMo / Orange Money / card gateway for `core_payment` |
| `mod_bigbluebuttonbn` | Moodle core | Live classroom. Bundled since 4.0 and actively maintained — 5.2 shipped web-service work on it. No version risk |
| `mod_customcert` ⚠️ | contrib | Branded certificates with QR verification (§11.3) |

⚠️ `mod_customcert` is the only contrib dependency proposed. It is the mainstream choice for Moodle certificates and ships a QR/verification element, which is exactly what cahier §5.7 asks for. The alternative is building certificate rendering inside `local_pshub` — more control, materially more work, and a PDF pipeline to maintain. **Decision 6, §18.2.**

Two facts about it that matter for planning:

- **5.3 support is not confirmed yet.** Its 5.2-compatible release landed in June 2026, supporting 5.0–5.2, and the maintainer is active — so 5.3 support is likely to follow the October release rather than lag it. Certificates are **P2**, which gives months of slack; the risk is scheduling, not capability. Re-check before P2 planning and fall back to in-house rendering if it has gone quiet.
- **Its element architecture changed in the 5.2 release** — payload, layout and rendering are now separated, and element data that lived in dedicated columns (`font`, `fontsize`, `colour`, `width`) now sits inside the element JSON in `customcert_elements.data`. Any custom element written for the branded certificate must target the new shape. Older tutorials and examples describe the old one.

### 7.4 Boundary rule

`local_pshub` **owns authorisation and workflow**; Moodle core **owns pedagogy**; BBB **owns the media session**. Course configuration never decides paid-or-free access. Renderers and web-service functions consume `gating_service`'s verdict and never re-implement it.

---

## 8. Live classroom (BigBlueButton) 🟢

This is the product. It gets its own section.

### 8.1 Joining

`local_pshub_get_session_join_url` runs gating (§3.7), checks the join window (opens T-15 min, closes at `scheduled_end`), checks capacity against `max_participants`, then mints a BBB join URL bound to that user, that meeting and a short TTL. The teacher joins as **moderator**; learners join as **viewer** with a presenter promotion available.

The BBB HTML5 client runs in its own window or an embedded frame. This is the one sanctioned place where a learner leaves the branded surface — it is branded through BBB's own theming (logo, navy, gradient) so the seam is as small as it can be.

### 8.2 In-session capabilities (cahier §5.3)

| Requirement | BBB mechanism | Notes |
|---|---|---|
| Visio, camera + mic, all participants | WebRTC via mediasoup | Server capacity is the constraint (§13.4) |
| Screen share by teacher | BBB screen share | Teacher by default |
| Screen share by learner on authorisation | promote to presenter | Teacher-controlled |
| Collaborative whiteboard, multi-user | BBB whiteboard | Annotations are **vector data**, not video (§8.4) |
| Annotate shared documents (PDF, slides) | BBB presentation upload | Server-side converted to page images; annotations overlay as vectors |
| Chat, hand raise, reactions | BBB core | |
| Polls | BBB polls | 🔵 per cahier §8 phase 3 |
| 1-to-1 and group | meeting config | `is_one_to_one` sets capacity 2 |
| Recording | BBB recording | 🔵; needs recording-enabled BBB + storage (§13.4) |

### 8.3 The 50-minute cap (cahier §5.3, AC-4)

Three layers, because no single one is trustworthy alone:

1. **Server-enforced end.** The meeting is created with BBB's `duration` parameter set to `duration_minutes`. BBB ends the meeting itself — this is the layer that actually guarantees AC-4, because it holds even if every client is lying or disconnected.
2. **Visible timer and warnings.** A countdown to the scheduled end, with notices at **45 min** and **48 min**. ⚠️ BBB's built-in end-meeting warning is not configurable to two arbitrary marks in every version, so plan on a small `local_pshub` client script injected into the session view, driven by a server timestamp (never the device clock). **Decision 3, §18.**
3. **Reconciliation.** A scheduled task sweeps sessions past `scheduled_end` that BBB still reports as running and calls the BBB `end` API. Belt and braces for the case where the `duration` parameter was not applied at creation.

Admin-configurable: the site default (50), and whether teachers may schedule shorter.

### 8.4 Low bandwidth — the critical NFR (cahier §5.5)

**Target: audio + whiteboard usable from ~64 kbit/s; full video from ~300 kbit/s.**

| Mechanism | How |
|---|---|
| Audio-only fallback | BBB drops webcams under sustained packet loss / low bitrate; `local_pshub` additionally offers an explicit "Mode économie de données" toggle that joins with video disabled from the start |
| Adaptive video | BBB simulcast — join at the lowest layer, climb only if the connection holds |
| Whiteboard as vectors | BBB transmits annotations as drawing data over the websocket; a page of annotation is kilobytes, not a video stream |
| Documents as images | Presentations converted server-side and fetched per page — cacheable, no continuous stream |
| Auto-reconnect | BBB reconnects without ending the session; `local_pshub` preserves the learner's place |
| Connection indicator | BBB's own indicator, plus a coarse quality badge on our session card fed by `local_pshub_report_connection_quality` |

**Screen-share suppression — requirement, confirmed by the client (§0.2).** Screen share is a video stream, so cahier §5.3 (screen share) and §5.5 (a 64 kbit/s floor) cannot both hold for the same participant at the same time. They are reconciled as follows, and this is normative:

> When a participant's measured bitrate falls below the screen-share threshold, **their** incoming screen share is dropped automatically and they continue on audio and whiteboard. The share is not stopped for anyone else. When their connection recovers, the share resumes without action. The learner is told what happened in plain language, once, non-blockingly (§15).

Per-participant, not per-session: one learner on a weak link must never degrade the class for the other eleven. The threshold is an admin setting with a sane default.

Note the pedagogical consequence and design around it: a teacher demonstrating something *on a shared screen* is invisible to a suppressed participant. Teachers should be guided — in the teacher portal, not in a manual nobody reads — to put anything essential on the **whiteboard**, which survives at any bitrate, and to treat screen share as enrichment.

⚠️ **One caveat remains, needing a bandwidth spike before the number is committed (Decision 2, §18).** 64 kbit/s is tight: BBB audio (Opus) plus websocket signalling plus whiteboard sits in roughly that region with no headroom. It is plausible on a *stable* 64 kbit/s link and unreliable on a 2G link averaging 64 kbit/s with loss and jitter. Until a field test says otherwise, the defensible commitment is **"audio and whiteboard degrade gracefully and reconnect"** rather than a hard floor — and the test must run on a real Cameroonian mobile network, not a throttled desktop.

### 8.5 Recording and replay 🔵

Optional per course (`allow_recording`). When enabled, the teacher is shown a recording indicator, learners are notified in-session, and the published recording URL lands on `local_pshub_session.recording_url`, surfaced in the course. Retention policy and storage sizing: §13.4.

### 8.6 Attendance

Attendance drives XP (§11.1) and teacher reporting. `local_pshub` observes the BBB plugin's join/leave events and, at session end, reconciles against BBB's meeting info to close any open interval. `counted_present` is set when `duration_seconds` ≥ an admin threshold (default 60 % of scheduled duration) — a learner who joins for ninety seconds has not attended.

---

## 9. Payments & accounting

### 9.1 Methods and currency

**MTN Mobile Money, Orange Money, Wave, card**; principal currency **FCFA (XAF)**. `bank_transfer` and `cash` exist as back-office methods so an admin can record an offline payment and open access through the same validated path — never by hand-editing an access right.

Wave is retained by client arbitration (§0.2) though the cahier omits it. It is a Mobile Money flow like MTN and Orange (§9.2) and adds no new mechanics, but it does add provider coverage to decision 8.

**Provider: Flutterwave** (client decision, 2026-10-03). One aggregator for MTN MoMo, Orange Money and card, settling in XAF, which is what `paygw_pshub` is built against. Three things follow, and none of them should be discovered during the build:

1. **Wave stays a launch method** — client confirmed 2026-10-03 that it will operate here. The only thing left to settle at build time is *which* provider serves it. If it is not Flutterwave, Wave is a second gateway class beside the first: `provider` is already a column on the transaction and the flow is provider-independent, so that is an addition, not a rewrite.
2. **Nothing in the architecture changes.** The provider sits behind `paygw_pshub` and the `transaction` row (§9.3): a provider reference, a status, a signed callback. Swapping or adding a provider later means a second gateway class, not a different payment model. `provider` is already a column.
3. **The callback must verify the signature and stay idempotent on `provider_ref`** (AC-12). Flutterwave signs webhooks with a secret hash the merchant sets; the exact header name and verification recipe are read from their current documentation at build time, never assumed from memory. Everything else — unique index, always ack 200, re-query for stuck transactions — is already specified in §9.2 and §9.3 and is provider-independent.

A purchase targets either a **course** or a **pack** 🟠; the transaction records which (`target_type`), and validation creates the matching access right. One flow, two targets.

### 9.2 Mobile Money mechanics

MoMo is not a redirect flow. The learner supplies an MSISDN, the provider pushes a PIN prompt to the handset, and confirmation arrives asynchronously by callback — which may take seconds or minutes, and may never arrive. The client therefore shows a **pending state with polling**, never a spinner that assumes success, and the reconciliation task (§9.3) re-queries the provider for transactions stuck in `pending`.

### 9.3 States & handling

| Situation | System behaviour | User-facing behaviour |
|---|---|---|
| Checkout started | `pending`; client polls | "Paiement en cours…" with the provider's instructions |
| Callback confirms | `paid_unvalidated`, idempotent on `provider_ref`; notify admins | "Paiement reçu — validation en cours" |
| Admin validates (or auto-validate on) | `validated` → access right → enrolment → notify | Access opens; return to the exact spot |
| Admin rejects | `rejected` + note; notify | Clear message; refund handled offline |
| Duplicate callback | no-op — enforced by the **unique index** on `provider_ref`, not a code check | no change |
| Payment failed / declined | `failed` | Friendly retry, no code shown |
| Timeout / no callback | reconciliation cron re-queries; expire after TTL | "Réessayer"; nothing charged silently |
| Signature or amount mismatch | reject, log, alert admins; stays `pending` | Support flow |
| Access expires mid-session | re-checked on the next gated action | Non-blocking sheet, position preserved, "Renouveler" |

No card data is stored on project servers — PCI scope sits with the provider.

### 9.4 Accounting module 🔵 (cahier §5.8)

- Revenue by **course**, by **period**, by **teacher**.
- Full transaction history with filters, including the validation trail (who validated, when, with what note).
- **Export CSV / Excel**.
- Automatic **receipts and invoices** with sequential, gapless `receipt_number`, branded per §12.4.
- Reads `local_pshub_transaction` and `local_pshub_audit`. It is a reporting layer — it never mutates payment state.

---

### 9.5 Building and testing before the account is open

Nothing is wired to Flutterwave yet, and nothing has to be for this to be built and tested. The full design is in `docs/PAYMENTS.md`; this section is the summary. The condition is one design decision taken on day one, not later: **the provider sits behind an interface, and Flutterwave is one implementation of it.**

```
payment_provider  (interface: start, query, parse_callback, verify_signature)
  ├─ flutterwave_provider   real HTTP, real keys, used on staging and in production
  └─ fake_provider          scripted answers, no network, used on dev boxes and in tests
```

A site setting chooses which one is live. On a developer's machine it is the fake one.

**What that makes testable today, with no account:**

| | How |
|---|---|
| The whole flow | paywall → `pending` → callback → `paid_unvalidated` → admin queue → `validated` → access right → enrolment. None of it is Flutterwave-specific: the provider only ever answers "was this reference paid, for how much, in what currency" |
| **AC-12** idempotency | Fire the same recorded callback twice, and concurrently. The guarantee is the unique index on `provider_ref`, which is enforced by the database whoever is calling |
| Signature verification | A signature is a hash over the payload with a shared secret, so a test can produce a valid one and a tampered one itself. No network involved |
| Amount and currency checks | Replayed payloads with the wrong amount, the wrong currency, an unknown reference |
| The real endpoint | A **callback replay CLI** posts a fixture to `/local/pshub/payment/callback.php` with a correct signature — the endpoint is sessionless by design (§9.3), so it needs no browser and no public URL |
| The paywall and the pending screen | The fake provider can be told to answer slowly, to fail, or never to call back, which is how the "no spinner that assumes success" rule (§9.2) gets tested at all |

**What it does not prove, and must not be claimed:**

- **The recorded payloads are assumed shapes until a real sandbox response replaces them.** A green suite shows our code is self-consistent, not that it speaks Flutterwave. The first task once the account exists is to capture genuine sandbox payloads into `tests/fixtures/`, overwrite the invented ones, and fix whatever the parser got wrong. Tests written against imagined payloads are a scaffold, not evidence.
- MoMo push to a real handset, settlement delay, fees, rate limits, and which methods are actually enabled for this merchant — none of these are answerable without an account.

**When the account exists:**

1. Test keys, then capture real sandbox payloads and replace the fixtures.
2. Callbacks need a publicly reachable URL: a tunnel to the dev box, or the staging host. Staging is better — it is also where the certificate and the real domain get exercised.
3. One smoke run with a provider test MSISDN, then **one small real transaction** before launch, refunded and reconciled, because sandboxes do not prove settlement.

**Sequencing.** Opening the merchant account is a business task with an external clock — identity documents, a company bank account, their review. It can start now and should, because it is the long pole. The gateway work in S7 does not wait for it.

---

## 10. Notifications & reminders 🟢

### 10.1 Channels

Moodle message providers fan out to **in-app / web**, **email**, and **mobile push** (FCM). SMS is an optional P3 channel. Per-provider, per-channel preferences are exposed to the user in plain language — not Moodle's raw notification matrix.

### 10.2 Session reminders (cahier §5.6, AC-6)

At **T-24 h**, **T-1 h** and **T-10 min**, to the **teacher** and to **enrolled learners**. Implemented as ad-hoc tasks queued when a session is scheduled, re-queued on reschedule, cancelled on cancellation. `local_pshub_reminder` carries a unique key on `(sessionid, offset_minutes, audience)`, so a duplicated cron run cannot double-send.

The 10-minute reminder carries the join action directly — it is the one that actually gets people into the room.

### 10.3 Other notifications

| Event | Recipients |
|---|---|
| Course published / new course announcement | Learners (opt-in), site announcement |
| Promotion announced | Learners (opt-in) |
| Course submitted for validation | Admins |
| Course approved / rejected (with comment) | Submitting teacher |
| Payment awaiting validation | Admins |
| Payment validated / rejected | Paying learner |
| Session cancelled or rescheduled | Teacher + enrolled learners |
| Assignment graded, feedback available | Learner |
| Certificate issued 🟠 | Learner |

### 10.4 Calendar

Every session and every activity due date produces a `core_calendar` event, visible in the learner dashboard and the teacher portal, exportable as iCal.

---

## 11. Gamification, competencies & certificates 🟠

### 11.1 XP

Awarded on: **live-session attendance** (`counted_present`), quiz passed, assignment submitted, workshop completed, course completed. The scale is **admin-configurable** in `local_pshub_xp_rule` — points and an optional daily cap per event, no code change.

Implemented as Moodle event observers writing to `local_pshub_xp_ledger`. The unique index on `(userid, event_key, itemid)` makes awards **idempotent** — a replayed event, a re-graded quiz or a double cron cannot inflate a total (AC-13). `local_pshub_learner` is a derived snapshot, rebuildable from the ledger at any time.

### 11.1.1 Streaks 🟠

A streak counts **consecutive days on which the learner did something that earns XP**. Computed nightly from the ledger — not incremented live, so it cannot drift — and stored on `local_pshub_learner` as `streak_days` and `longest_streak`.

Two details that decide whether a streak motivates or punishes, for a product whose users have unreliable connectivity:

- **Timezone is the learner's**, not the server's. A day boundary that lands mid-evening in Douala will break streaks that the learner experienced as unbroken.
- **Attending a live session always counts**, even where the XP rule for attendance is capped or disabled. The streak's job is to reward showing up.


### 11.2 Competencies

Admins define **competency frameworks** in `core_competency` and attach competencies to courses and activities. Two consumers:

1. **Learner** — a learning plan showing competencies acquired and in progress, on the dashboard.
2. **Teacher** — the brief. `local_pshub_teacher_assignment` records which competencies and requirements an admin expects a teacher to cover (cahier §4.2, §4.3). The teacher portal shows it; course submission checks against it (§3.4, check 3).

### 11.3 Certificates

Issued **automatically** on completion of a cursus, per admin-defined rules. Branded per §12.4 — logo, navy ground, signature gradient, the tricolour tagline. Each certificate carries a unique verification code rendered as a **QR code** pointing at a public endpoint (`/local/pshub/certificate/verify.php`) that confirms holder, course, date and validity. Certificates appear on the learner dashboard and are downloadable as PDF.

### 11.4 Learning groups 🟠

A group is a cohort of learners — attached to a course or standing alone — with a level and a capacity. Admins create and populate them in the admin portal; learners see their group, its members' first names, and the **weekly picks** (courses and sessions surfaced to that group). Groups drive presentation and encouragement only: **they never grant access**. Membership is not an access right, and `gating_service` does not consult it (§7.4).

### 11.5 Kids catalogue 🟠

`audience` on the user profile and on the course (`adult` | `kids`) segments the catalogue. A learner with `audience=kids` sees the kids catalogue and its courses. The segmentation is a **presentation filter, not a security boundary** — an access right still decides what opens, so a mis-set `audience` is a bad experience, never a leak.

Where a kids account is managed by a parent, the parent's payment and the child's learning sit on the same account. A separate guardian model — one payer, several child profiles — is **not specified here**; if it is wanted, it needs its own scoping, because it is the one thing in this list that would genuinely change the identity model (§5.2).

---

## 12. Presentation layer

### 12.1 Learner web — server-rendered throughout ✅ decided

Cahier §3 specified a standalone SPA on the web-service API. The client's final position is the other end of that range, and it is a deliberate simplification rather than a fallback (§0.2 decision A):

> **The entire web surface is server-rendered Mustache on `theme_pslanguagehub`. There is no client framework — no React, no SPA, no client-side router, at any phase.** The handful of genuinely interactive pieces are small Moodle **AMD modules**.

#### Why this holds, when the reference design is a rich dashboard

Because the Sharecourse reference is **CSS, not architecture**. Dark sidebar, stat cards, course grid with progress bars, a sessions list with a join button — that is layout, type, colour and spacing. None of it needs a client framework to render, and saying otherwise confuses how something looks with how it is built.

Once the public/authenticated split moved the catalogue filters to the server side, the genuinely interactive surface shrank to four things — and all four are comfortably within Moodle's own AMD module system:

| Interactive piece | Mechanism | Scale |
|---|---|---|
| **Session countdown; join activating at T-15** | AMD module against a **server timestamp**, never the device clock | ~30 lines |
| **Payment status polling** | AMD module polling a `sesskey`-scoped endpoint (§9.2) | small |
| **In-session connection/quality strip** | AMD module fed by telemetry (§8.4) | small |
| **Optimistic quiz submit with retry** | AMD module with local cache (AC-15) | moderate |

Everything else on every page — dashboard, my courses, course experience, session lobby, payment flow, profile, XP, competencies, certificates, plus the whole public site and both staff portals — is a Mustache template rendered by PHP.

#### What this buys

| | Consequence |
|---|---|
| **Performance** | No bundle anywhere. The `<3 s on 3G` target applies uniformly and is met by construction, not defended against regression (§13.2) |
| **Auth, i18n, accessibility, SEO, forms** | All inherited from Moodle. None rebuilt |
| **Failure modes** | No hydration mismatch, no stale client state, no back-button or scroll-restoration defects, no blank content area |
| **Contracts** | No bootstrap payload, no per-route bundle budget, no framework upgrade treadmill |
| **Web services** | Still the Flutter app's alone (§6.0) — the web surface calls `local_pshub` services directly in PHP |
| **Operations** | One runtime. PHP renders, the browser displays |

**Polish is not the casualty it would once have been.** CSS handles transitions, transforms and micro-interactions; the **View Transitions API works for server-rendered multi-page navigation**, so moving from dashboard to course can animate smoothly with no framework involved. Reach for that before reaching for JavaScript.

#### The honest cost, and how it is managed

Mustache is logic-less: no expressions, no props, no composition beyond partials receiving a context. Building a large UI in it is workable and gets **verbose in proportion to how much UI there is**. Phase 2 adds seven substantial authenticated areas — XP, competencies, certificates, streaks, groups, kids catalogue and the full dashboard — and that is where the verbosity will be felt, around S19.

This was weighed and accepted. Four practices keep it from becoming a problem rather than a tax:

1. **Data shaping lives in PHP, not in templates.** Every non-trivial component gets a `templatable` class whose `export_for_template()` is unit-tested. Templates render; they never compute.
2. **A flat, documented partial library.** Each partial's context shape is written down and treated as an interface. Deep partial nesting is where Mustache becomes unreadable — keep it shallow.
3. **Visual consistency comes from design tokens and CSS**, not from partial reuse alone. Two partials that look alike should share a token set, not be forced into one over-parameterised partial.
4. **Resist JavaScript creep.** Any fifth AMD module is a design conversation first. Four small modules is an architecture; fifteen is a framework nobody chose.

#### The contract between the two teams

Team A (Moodle) and Team B (design/CSS) meet at **the template context shape** — the array each partial receives from its `templatable` class (see [ROADMAP §0.1](docs/ROADMAP.md)).

- Team A owns `templatable` classes and renderers; Team B owns partials, CSS and the AMD modules.
- Team A defines each context shape; Team B writes the partial against it. Documented and versioned, exactly as the payload contract would have been.
- **Templates carry no capability decisions** — only their outcomes. A partial renders what the context says is permitted; it never computes permission (§7.4).

#### The 5.3 theme risk — now the project's main front-end risk

`theme_pslanguagehub` extends **Boost**, which in 5.3 is the only bundled theme; Classic is deprecated there. Boost-only is the right base regardless, so the deprecation costs nothing. The cost is that **5.3 brings a refreshed look and feel** — the theme layer is the part of Moodle that moved most in the release being pinned, and this project will be among the first to build a substantial custom theme on it.

With the whole product now server-rendered, this is no longer one risk among several. **It is the front-end risk.** Every screen in the application depends on the theme layer holding still.

1. **Build against the 5.3 RC from day one.** Theme code written against 4.5 or 5.2 Boost will need reworking, and the RC is available before the October release.
2. **Override as little of Boost as the design allows.** Every overridden template is one to re-reconcile at the next major. Prefer extending via CSS and new partials over replacing core templates.
3. **Keep a list of every core template overridden**, with why. At upgrade time that list is the work plan; without it, the work is archaeology.

#### Not a staging post

This is not "Mustache until React becomes necessary." A client framework is **out of scope for every phase** (§17.1). If the P2 dashboard genuinely outgrows Mustache, that is a specification change with its own justification — not a decision a sprint gets to make on its own.

### 12.2 Learner UI reference (cahier §6.1)

Dark sidebar navigation; a "Course Overview" with stat cards (courses followed, workshops, average quiz score, certificates); course grids with progress bars; and — the piece that matters most for a live product — **upcoming sessions with a countdown and a "Rejoindre maintenant" button** that activates at T-15 min. Responsive: one component set reflowed — sidebar and grid on desktop, single column with bottom tabs on mobile web.

### 12.3 Mobile — Flutter ✅ decided

Cahier §3 reopened this ("Moodle App (Ionic) personnalisée **ou** app native"); the client re-closed it on v2.0's answer: **Flutter**, Android first, iOS after (§0.2 decision B).

| Option | For | Against |
|---|---|---|
| **Flutter** ✅ | Full design control; strong performance; small release artefact achievable | Everything rebuilt from scratch |
| Branded Moodle App (Ionic) | Fastest; offline, push, files and BBB handoff already solved | Cannot reach the Sharecourse look; carries the Moodle UI that §2.1 exists to hide from learners; <25 MB is tight |
| React Native | Shares code with a React web client | There is no React anywhere on the web (§12.1) — its one advantage does not exist here |

Two things follow, and both are easy to underestimate:

1. **The Flutter app is the sole consumer of the web-service API** (§6.0). Everything the app shows has to exist as a web-service function, and each one is work that the web surface does not share. That is the true price of this decision, and it should be planned as such rather than discovered in P2.
2. **The live classroom is a webview in every option.** Flutter embeds the BBB HTML5 client exactly as Ionic or React Native would — there is no native live-classroom UI on offer. Live-session quality was therefore never a differentiator between the three, and the app's job around the session is the shell: reminders, one-tap join, connection warnings, graceful return.

**<25 MB (§13.2)** is achievable in Flutter but not automatic — it needs split-per-ABI release artefacts, no bundled fonts beyond the brand set, and vector assets over raster. Measure the Android release artefact from the first build, not at the end.

### 12.4 Brand

From the supplied assets in [`ps-brand-assets/`](ps-brand-assets/):

| Token | Value | Use |
|---|---|---|
| `--ps-navy` | `#0A1A40` | primary ground and text |
| `--ps-pink` | `#E8336F` | signature gradient stop 1 |
| `--ps-orange` | `#F7941D` | signature gradient stop 2 |
| `--ps-yellow` | `#FBC02D` | signature gradient stop 3 |
| `--ps-blue-900/700/500/300` | `#0B3FA0` `#1F6FE0` `#2E8BFF` `#5BA3FF` | accents, links, globe |
| `--ps-white` / `--ps-blue-050` | `#FFFFFF` / `#CFE2FF` | surfaces |

Tagline **LEARN. SPEAK. SUCCEED.** in blue / pink / orange, with the horizontal logo. Assets available: logo-mark, horizontal light and navy, app icons (standard + maskable), favicons. Applied to the site, the app, the BBB session theme, certificates and transactional email. Interface languages: **French (default) and English**, extensible.

---

## 13. Non-functional requirements

### 13.1 Security (cahier §7)

| Requirement | Detail |
|---|---|
| Transport | HTTPS/TLS on all traffic — browser, app, BBB and payment callbacks |
| Passwords | Moodle password policy enforced, minimum strength configured at install |
| Sessions & tokens | Moodle session (web); web-service token (mobile), revoked server-side on logout |
| Authorization | Moodle capabilities, checked inside every function and page. Clients never trusted for gating |
| Role separation | The four roles are strictly partitioned (§5.3); learners cannot reach staff surfaces |
| **Audit log** | Every course-validation and payment-validation transition writes `local_pshub_audit` — actor, before, after, IP, timestamp. Admin-viewable, not admin-editable |
| Payment data | No card data stored; verify callback signature **and** amount **and** currency before trusting anything |
| BBB join | Signed, short-TTL, single-use join URLs; meeting links are never shareable |
| Media | Served through `webservice/pluginfile.php` — per-user, permission-checked |
| Rate limiting / idempotency | Login, signup, payment callbacks (unique `provider_ref`), XP awards (unique ledger key), reminders (unique reminder key) |
| PII | Minimal collection; MSISDN stored only where Mobile Money requires it; aligned with local data norms |

### 13.2 Performance (cahier §7)

| Target | Measured how |
|---|---|
| **All learner pages < 3 s on 3G** | Lighthouse throttled 3G, **cold cache** — home, catalogue, course detail, dashboard, course, session lobby. No page ships a framework bundle, so the target is met by construction (§12.1); the check is that it stays that way (AC-21) |
| Total JavaScript per page **< 50 KB** compressed | The four AMD modules plus Moodle's own. A page exceeding this is a design conversation, not a tuning exercise |
| Mobile app **< 25 MB** | Release artefact size, Android first |
| Live session usable from **~64 kbit/s** (audio + whiteboard) ⚠️ | Field test on a real mobile network — see §8.4 for the caveats |
| Full video from **~300 kbit/s** | Same |
| Images | WebP, responsive sizes |

### 13.3 Availability

**99 %** uptime. Automatic **daily backups** (database + `moodledata` + BBB recordings once P3 enables them), restore-tested — a backup that has never been restored is a hypothesis.

### 13.4 Scalability ⚠️ (Decision 7, §18)

BBB is the capacity constraint, and the cahier leaves the number open ("X sessions simultanées"). Planning needs a concurrency target before infrastructure can be sized: a BBB server's capacity depends heavily on webcams-on count and whether recording is enabled, so the sizing input is *concurrent users with video*, not sessions. The architecture supports adding BBB servers behind a load balancer; the deployment plan should assume that from the start rather than retrofitting it. Moodle and the database scale separately and are not expected to be the first ceiling.

### 13.5 Compatibility

Recent Chrome / Firefox / Safari; **Android 8+**; **iOS 14+**. WebRTC support is the real floor for live sessions — verify against the oldest supported Android WebView.

### 13.6 Platform requirements — Moodle 5.3 LTS

The version is pinned (§0.2 decision E). Its minimums are **hard floors**, not preferences, and they are higher than much of the budget hosting available in the target market:

| Component | Minimum | Note |
|---|---|---|
| Moodle | **5.3 LTS** | Released 5 Oct 2026; code freeze 31 Aug 2026; security support to 1 Oct 2029 |
| PHP | **8.3.0**, **64-bit only** | 8.4.x supported. 32-bit PHP will not run |
| PostgreSQL | **16** | Preferred |
| MySQL / MariaDB | **8.4** / **10.11.0** | |

**Verify this against the hosting before signing anything.** Shared hosting in the region is frequently still on PHP 8.1 and MariaDB 10.6 — discovering that after a contract is signed is an avoidable and expensive surprise. This is a hard input to decision 10 (§18.2), not a detail to settle at deployment.

**Sequencing.** 5.3 is in release candidate now, so its APIs are already stable — development starts against the RC and pins the release when it lands. **Do not launch production on 5.3.0**; on this timeline the build naturally reaches 5.3.2+ before go-live, and that should be deliberate rather than incidental.

**Why not the alternatives.** 4.5 LTS left general support in October 2025 and is security-only — a greenfield build starting there would face a forced major upgrade inside year one. 5.2's general support ends 19 April 2027, which would put a major upgrade in the middle of the build or just after launch, with a custom theme attached to it. Note that **no requirement in this spec needs Moodle 5.x**: BBB in core, `core_payment`, `core_competency` and `mod_workshop` all predate the 5.0 line. The version choice buys support lifetime, not capability.

---

## 14. Acceptance criteria (QA)

Given/When/Then. The first eight are the cahier's own (§9), restated testably; the rest carry forward from v2.0 where still applicable.

**AC-1 Course validation 🟢** *(cahier 1)* — *Given* a teacher creates a course, *when* it is submitted, *then* it is **not** publicly visible until an admin approves it. *And* a rejection requires a comment, which reaches the teacher. *And* every transition writes an audit row.

**AC-2 Login required to access 🟢** *(cahier 2)* — *Given* a visitor on the home page, *then* featured courses and the catalogue are browsable; *when* they attempt to enrol or open content, *then* they are required to sign up or log in.

**AC-3 Payment validation opens access 🟢** *(cahier 3)* — *Given* a learner pays by MTN MoMo, *when* the callback confirms, *then* the transaction becomes `paid_unvalidated` and appears in the admin queue; *when* the admin validates it, *then* an access right (`source=payment`) is created, enrolment happens, and the course opens. *And* access does **not** open before validation.

**AC-4 50-minute auto-close 🟢** *(cahier 4)* — *Given* a live session with a 50-minute duration, *when* 50 minutes elapse, *then* the meeting ends automatically — **including when all clients have been forcibly disconnected or their clocks altered**. *And* warnings appeared at 45 and 48 minutes.

**AC-5 Degraded-connection fallback 🟢** *(cahier 5)* — *Given* a participant's bandwidth collapses mid-session, *when* the drop is sustained, *then* video is dropped and **audio and whiteboard continue without the participant being disconnected**; *and* video resumes when the connection recovers.

**AC-6 Session reminder 🟢** *(cahier 6)* — *Given* a scheduled session, *then* the teacher and enrolled learners receive reminders at 24 h, 1 h and 10 min before start, on their enabled channels. *And* a repeated cron run does not send duplicates.

**AC-7 Certificate and XP 🟠** *(cahier 7)* — *Given* a learner completes a cursus, *then* a branded certificate is issued automatically, its QR code verifies at the public endpoint, and the dashboard XP total is updated.

**AC-8 Configurable badges 🟢** *(cahier 8)* — *Given* an admin creates a new course type in the admin UI, *when* it is attached to a course, *then* its badge appears on the course card and its pricing behaviour applies — **with no code deployment**. *And* a `-20 %` type renders a struck-through original price.

**AC-9 Free enrolment 🟢** — *Given* a free course, *when* the learner enrols, *then* an access right (`source=auto`) is created, enrolment succeeds, and the course opens — with no visible system transition and no Moodle enrolment page.

**AC-10 Gating at join 🟢** — *Given* a learner whose access lapsed after enrolling, *when* they tap "Rejoindre maintenant", *then* they are stopped with a renewal offer, not admitted to the meeting.

**AC-11 Authorization 🟢** — *Given* a learner session or token, *when* any admin or teacher surface or function is requested directly, *then* it is refused by capability check.

**AC-12 Callback idempotency 🟢** — *Given* duplicate provider callbacks for one `provider_ref`, *when* received — **including concurrently** — *then* exactly one transaction is affected and at most one access right created.

**AC-13 XP idempotency 🟠** — *Given* a repeated or replayed award event for the same item, *then* the XP total does not change.

**AC-14 Attendance 🟢** — *Given* a learner joins a session for less than the presence threshold, *then* they are not counted present, and no attendance XP is awarded.

**AC-15 Quiz submit failure 🟢** — *Given* the network drops on submit, *when* the learner answers, *then* the answer is retained optimistically, an inline non-blocking notice appears, the client auto-retries, and the attempt finalises only on success.

**AC-16 Reconnect 🟢** — *Given* a connection drop mid-session, *when* the network returns within the session window, *then* the participant rejoins automatically into the same meeting with the whiteboard state intact.

**AC-17 No Moodle chrome 🟢** — *Given* any learner journey, *then* no Moodle page, enrolment prompt, system name or raw error code is shown. The BBB session window is the sole sanctioned exception.

**AC-18 Pack phase isolation 🟢 → pack grant 🟠** — *Given* a P1 build, *when* any course is opened, *then* only the free and course-level-paid branches can grant — **even for a user holding a pack-level access right** — and no pack UI is reachable. *And* in P2, *given* a valid pack right, *when* any course in that pack is opened, *then* it opens; *and* removing a course from the pack closes it for rights that were granted by the pack alone.

**AC-19 Screen-share suppression is per-participant 🟢** — *Given* a session where one participant's bitrate falls below the threshold, *when* the teacher shares their screen, *then* that participant loses only the shared video and keeps audio and whiteboard, **and every other participant continues to see the share unchanged**. *And* the share resumes for that participant on recovery, without anyone acting.

**AC-20 Every page works without JavaScript 🟢** — *Given* **any** learner page, public or authenticated, *when* it is loaded with JavaScript disabled or a script fails, *then* it renders and its core function works: courses are listed, the next session and its join link are reachable, forms submit. JavaScript adds the countdown, the polling and the retry — it is never what puts content on the page.

**AC-21 We ship no client framework 🟢** — *Given* any page in the application, *when* the assets contributed by `local_pshub` and `theme_pslanguagehub` are enumerated, *then* neither ships a React, Vue or equivalent framework bundle, and neither adds more than 50 KB of compressed JavaScript to a page. An automated build check, not a review habit.

> **Scoped to our own code, and that is a correction.** This criterion originally read "no framework bundle is present on the page". That is not satisfiable on Moodle 5.3: **core itself references React**, via `@moodle/lms/core/react_autoinit` in its import map, on every page including the public catalogue. Nothing we can do removes it, and the architectural commitment in §12.1 was always about what *we* build. Measuring core's assets against our rule would produce a criterion that fails forever while telling us nothing about our own discipline.

---

## 15. Client error states (web & mobile)

Grammar: **preserve work · plain language · auto-recover · stay branded · never expose a system name or code.** Orange = recoverable / attention; red reserved for the genuinely unrecoverable.

| State | Trigger | Handling |
|---|---|---|
| Payment awaiting validation 🟢 | `payment_pending` | Calm informational panel: "Paiement reçu, validation en cours". Shows what happens next and roughly when. Not an error colour |
| Paywall 🟢 | `payment_required` | Price with any discount shown, methods offered, no dead end |
| Session not yet open 🟢 | `session_not_open` | Countdown to the join window; the button is visibly *waiting*, not broken |
| Session full 🟢 | `session_full` | Explain capacity, offer the next session for that course |
| Session ended 🟢 | `session_ended` | Offer the recording if published 🔵, else the next session |
| Bandwidth collapse mid-session 🟢 | sustained low bitrate | Non-blocking in-session strip: "Connexion faible — vidéo désactivée, audio et tableau maintenus". Recovery is silent |
| Disconnected mid-session 🟢 | socket drop | Auto-reconnect with visible attempt state; place preserved; manual retry after N failures |
| Access expired mid-lesson | gating re-check fails | Non-blocking sheet, content dimmed not removed, "Renouveler", position restored after renewal |
| Quiz submit failure 🟢 | network error on submit | Optimistic retain, inline retry notice, local cache, auto-retry, commit on success |
| Network interruption 🟢 | connectivity lost | Calm offline strip; cached content usable; unavailable items dimmed; queued sync on reconnect |
| Login failure 🟢 | bad credentials | Inline message, no modal, no code |
| Course under review 🟢 (teacher) | `review_status=pending` | Clear status with submission date; edit is locked until the admin responds |
| Course rejected 🟢 (teacher) | `review_status=rejected` | The admin's comment shown prominently, with a direct path to edit and resubmit |

---

## 16. Business metrics (KPIs)

| KPI | Phase | Source |
|---|---|---|
| Registrations, enrolments | 🟢 | Moodle + access rights |
| **Live-session attendance rate** (present ÷ enrolled) | 🟢 | `local_pshub_session_attendance` |
| **Session completion rate** (ran to term ÷ scheduled) | 🟢 | `local_pshub_session` |
| Payment validation latency (paid → validated) | 🟢 | `local_pshub_transaction` |
| Payment-method split (MoMo / OM / card) | 🟢 | `local_pshub_transaction.method` |
| Course-validation turnaround (submitted → reviewed) | 🟢 | `local_pshub_course` |
| Audio-only fallback frequency | 🟢 | connection-quality telemetry |
| Course completion rate | 🟢 | Moodle completion |
| Engagement: XP earned, active days | 🟠 | `local_pshub_xp_ledger` |
| Certificates issued | 🟠 | certificate records |
| Revenue by course / period / teacher, ARPPU, retention | 🔵 | accounting module (§9.4) |

---

## 17. Constraints & exclusions (restated for stakeholders)

### 17.1 Constraints

- Teachers author in **standard Moodle**; the teacher portal handles the course shell, scheduling, submission and learner tracking.
- Teachers **cannot publish** — an admin validates every course.
- **Access never opens before payment validation** (admin or configured auto-validation).
- Learners **never** see the raw Moodle interface; the BBB session window is the one sanctioned exception.
- The mobile app is a **learner** surface. Teachers and admins work on the web.
- Custom plugins limited to `local_pshub`, `theme_pslanguagehub`, `paygw_pshub`; one contrib dependency proposed (`mod_customcert` ⚠️).
- The web surface is **entirely server-rendered Mustache**. **No client framework — React, Vue or otherwise — at any phase** (§12.1). Interactivity is four small AMD modules; a fifth is a specification change, not a sprint decision.
- The **Flutter app is the only consumer of the web-service API** (§6.0). The web surface calls `local_pshub` services directly in PHP.
- Every course has at least one scheduled live session — there are no asynchronous-only courses.
- Detailed financial modelling belongs to a separate business-plan document.

### 17.2 Retained from v2.0 despite the cahier's silence

The cahier does not mention these. The client kept them (§0.2 decision D), so they are **in scope** — and the cahier should be amended to say so, or the two documents will contradict each other the next time they are read together.

| Feature | Phase | Where specified |
|---|---|---|
| **Course packs** and pack-level pricing | tables + gating branch **P1** (held shut), sold 🟠 | §3.3, §3.7, §4.1, §9.1 |
| **Learning groups** / weekly picks | 🟠 | §11.4 |
| **Kids catalogue** & `audience` segmentation | 🟠 | §11.5 |
| **Streaks** | 🟠 | §11.1.1 |
| **Wave** payment method | 🟢 | §9.1 |

Packs are the one that changes the architecture rather than adding a screen: a second grant dimension in `gating_service`, a second target on the access right and the transaction, and a price resolver that has to decide between a course price and a pack price. That is why it is modelled in P1 even though it is sold in P2 — see the design mandate in §1.1.

### 17.3 Dropped — do not build

| Feature | Status |
|---|---|
| **Teacher qualification exam** | Removed by the cahier's role model (§2). Admins vet and onboard teachers directly and assign competency briefs (§11.2) |
| **Oral-practice repetitions** | Removed — live sessions supersede it. *Not among the five features retained in §0.2; flag it if that was unintended* |
| Asynchronous-only courses | Never in scope — every course has at least one live session |

Anything else resurfacing from v2.0 needs adding to the cahier first, not rediscovering mid-sprint.

---

## 18. Open decisions & dependencies

### 18.1 Closed in v3.1

| # | Decision | Outcome |
|---|---|---|
| ~~1~~ | Learner web: SPA vs Moodle theme vs framework at all | **Server-rendered Mustache, no client framework at any phase** — §12.1 |
| ~~5~~ | Mobile stack | **Flutter**, Android first — §12.3 |
| ~~9~~ | Moodle version | **5.3 LTS**, released 5 Oct 2026, security support to Oct 2029 — §13.6. Develop against the RC, pin on release, launch on 5.3.2+ |
| — | Payment validation: auto vs manual | **Both** — per-method `auto_validate`, one state transition — §3.8 |
| — | Screen share vs the 64 kbit/s floor | **Per-participant suppression below threshold** — §8.4 |
| — | Features absent from the cahier | **Packs, groups, kids catalogue, streaks and Wave retained** — §17.2 |
| — | Client framework: React, Vue, or none | **None, at any phase** — §12.1. The Sharecourse reference is CSS; four AMD modules cover the real interactivity |
| — | Team split | **Moodle/PHP + design/CSS**, meeting at the template context shape — §12.1 |

### 18.2 Still open

| # | Decision | Blocks | Owner | Needed by |
|---|---|---|---|---|
| 2 | **Bandwidth reality check** — is 64 kbit/s audio + whiteboard achievable on the target networks? (§8.4). The screen-share half of this question is now closed; the number is not | the headline low-bandwidth commitment in §13.2 | Tech spike | before committing §13.2 |
| 3 | **50-min warnings at 45/48 min** — confirm the mechanism in the chosen BBB/Moodle version (§8.3) | AC-4 test design | Tech spike | P1 |
| 4 | ~~Signup: keep Moodle's email-confirmation step or auto-confirm?~~ **CLOSED 2026-09-28: confirmation is mandatory.** Built and verified end to end. ⚠️ **Consequence: e-mail delivery is now a launch blocker** — no mailer, no accounts. See §3.1 | AC-2, §3.1 | Product | P1 |
| 6 | **Certificates** — `mod_customcert` vs building rendering into `local_pshub` (§7.3, §11.3). Its 5.3 compatibility is expected but not yet published; re-check before committing | P2 planning | Tech | before P2 |
| 7 | **Concurrency target** — peak simultaneous participants with video, to size BBB (§13.4) | infrastructure cost and topology | Business | before infra procurement |
| 8 | ~~**Payment integration** — direct APIs vs an aggregator~~ **CLOSED 2026-10-03: Flutterwave**, for MTN MoMo, Orange Money, Wave and card (§9.1). ⚠️ **What it does not close**: the commercial terms — fees, settlement delay, sandbox access — read off the signed agreement rather than assumed; and **opening the merchant account, which is the long pole** (§9.5) | `paygw_pshub` build | Business + Tech | P1 |
| 10 | **Hosting and infrastructure** — Moodle and BBB on separate hosts, media storage sizing, dev/staging/prod. **Must satisfy the 5.3 floors: PHP 8.3 64-bit, PostgreSQL 16 / MySQL 8.4 / MariaDB 10.11** (§13.6). Regional budget hosting frequently does not | deployment; verify **before** signing a hosting contract | Infra | **P1, early** |
| 11 | Recording retention policy and storage budget (§8.5) | P3 planning | Business | before P3 |
| 12 | **Pack commercial rules** — do packs expire? Does buying a pack refund or credit a course already bought individually? (§3.3, §9.1) | pack build in P2 | Business | before P2 |
| 13 | **Cahier amendment** — the cahier does not mention packs, groups, kids catalogue, streaks or Wave (§17.2). Amend it, or accept that the two documents disagree | documentation integrity | Product + Client | before P2 |

**Nothing now blocks sprint 1.** Everything remaining is P1-parallel or later. The nearest-term item is **#10** — confirm the hosting meets the 5.3 platform floors before a contract is signed, not at deployment.

---

*End of functional specification v3.1. A living document — revise alongside the cahier des charges and field feedback.*
