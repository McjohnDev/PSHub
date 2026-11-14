# Functional Specification — PS Language Hub E-learning Platform

**Version:** 2.0 · **Date:** August 2026 · **Status:** Working blueprint (for build)
**Supersedes:** v1.0 (June 2026), which specified a Laravel business tier
**Derived from:** *Cahier des charges — Plateforme E-learning Mobile, v1.0*
**Architecture:** Moodle (content engine **and** business layer, via `local_pshub`) + Flutter mobile client

---

## 0. How to use this document

This spec is a shared source of truth for four audiences. Read the whole thing once; thereafter, jump to what you own:

| If you are… | Start with | Then read |
|---|---|---|
| **Developer (Moodle)** | §4 Data model · §5 Auth · §6 API · §7 Moodle core usage · §8 Payments | §3 Flows, §9 Security |
| **Developer (mobile)** | §3 User flows · §6 API · §11 Error states | §2 Scope, §5 Auth |
| **QA / test** | §10 Acceptance criteria · §3 Flows · §11 Error states | §6 API, §8 Payments |
| **Product / business** | §1 Scope & phasing · §3 User flows · §12 KPIs · §13 Constraints | §2 Roles |

**Conventions**
- 🟢 **P1** = Phase 1 (MVP, free). 🟠 **P2** = Phase 2 (monetisation). Anything unmarked applies to both.
- Entity names are given in English (code identifiers) with the French term from the cahier in parentheses on first use.
- Example payloads are illustrative; field naming is normative, field formatting is implementation-defined unless stated.

### 0.1 What changed in v2.0

v1.0 placed a Laravel API between the clients and Moodle, and its governing rule was *"access authorisation lives in Laravel, never in Moodle."* Laravel has been removed from the architecture. The rule is restated, not abandoned:

> **Golden rule (v2):** access authorisation lives in **`local_pshub`**, never in Moodle's per-course enrolment configuration.

What that rule was protecting — **one gatekeeper, decoupled from pedagogy** — is fully preserved. `local_pshub\access\gating_service` is the single gate; Moodle core remains the content engine; courses are never configured with enrolment keys, cohort rules or `enrol_fee` conditions that would let pedagogy decide access. The P1→P2 switch remains a change in **grant logic only**, with no schema refactor and no course reconfiguration.

Consequences worth stating plainly:

| v1.0 | v2.0 |
|---|---|
| Two user records (Laravel `User` + Moodle user), linked by `moodle_user_id` | One Moodle user |
| User provisioning is async, retryable, and can fail | No provisioning step exists |
| Catalogue mirrored into Laravel, refreshed by a sync endpoint | Catalogue metadata sits beside the Moodle course |
| Lesson completion and quiz attempts duplicated in Laravel tables | Moodle core owns both |
| Custom JWT + refresh tokens | Moodle web-service tokens, later OAuth2 |
| REST API at `api.pslanguagehub.com/v1` | Moodle web-service functions on the site itself |

Migration and sprint sequencing: [`docs/ROADMAP-MOODLE-NATIVE.md`](docs/ROADMAP-MOODLE-NATIVE.md).

---

## 1. Scope & phasing

### 1.1 Phase matrix

| Capability | 🟢 P1 (MVP, free) | 🟠 P2 (monetisation) |
|---|:--:|:--:|
| Account creation / login (one identity, web + mobile) | ✅ | ✅ |
| Course catalogue (list, detail, content) | ✅ | ✅ |
| Filtering (language / level / exam / access) | ✅ | ✅ |
| Free enrolment → immediate access | ✅ | ✅ |
| Lesson content & progress tracking | ✅ | ✅ |
| Quizzes / evaluations & results | ✅ | ✅ |
| Gamification (XP, streaks, badges, journey) | ✅ | ✅ |
| Learning groups, kids catalogue, oral practice | ✅ | ✅ |
| Teacher qualification exam & course publishing | ✅ | ✅ |
| Free vs paid visual distinction in catalogue | — | ✅ |
| Paywall on access to an unowned paid course/pack | — | ✅ |
| Payment: card, Orange Money, MTN MoMo, Wave (via aggregator) | — | ✅ |
| Auto-unlock on payment confirmation (callback) + enrolment | — | ✅ |
| Course **packs** at a bundled price | data modelled, inactive | ✅ |
| `local_pshub_access` / `local_pshub_transaction` | modelled; access active, transaction inactive | both active |
| Admin: users, course access-type config, packs, access-rights ledger | ✅ | ✅ |
| Admin: transaction monitoring | — | ✅ |

> **Design mandate (Notes p.13):** model the access-right, pack and transaction tables in P1 even while inactive, so the P1→P2 switch is a change in *grant logic only*, with **no database refactor**. Delivered in M1; this is what keeps M15 to a single sprint.

### 1.2 Out of scope (both phases)
- Refactoring Moodle's authoring interface — trainers use **standard Moodle** for course content.
- 100% course creation through the app — the teacher portal creates the course shell, sets metadata and publishes; heavy authoring deep-links into Moodle.
- Advanced marketplace (trainer ratings, commission engine, payouts) — deferred, needs separate scoping.
- Moodle plugins beyond what the platform strictly requires: `local_pshub`, `theme_pslanguagehub`, and `paygw_pshub` in P2.

---

## 2. Roles & actors

| Role (FR) | Description | Primary surface |
|---|---|---|
| **Learner** (Apprenant) | Consumes courses. | Website + mobile app |
| **Trainer** (Formateur) | Qualifies by exam, creates and publishes courses, views enrolments and results. | Website — `/local/pshub/teacher/*`, deep-links to Moodle for authoring |
| **Administrator** (Administrateur) | Manages users, access types, packs, pricing, transactions, support. | Website — `/local/pshub/admin/*` |
| **Site administrator** | Installs and configures Moodle; defines qualification exams. | Moodle admin |
| **System (`local_pshub`)** | Gatekeeper: access rights, catalogue metadata, gamification, web services, payments. | — |
| **System (Moodle core)** | Content engine: courses, enrolment, quizzes, completion, badges, files. | — |
| **Payment aggregator** 🟠 | CinetPay / PayDunya / Flutterwave (TBC) — covers OM, MoMo, Wave, card. | External |

Trainers and administrators work on the **website**; the mobile app is a learner surface only.

---

## 3. User flows

Each flow lists **actor → goal → success criteria**, the step sequence, and the systems touched. The interface must never expose a Moodle page, a system name, an enrolment step, or a raw error code to the learner.

### 3.1 Registration 🟢
**Actor:** Learner · **Goal:** create an account and be ready to enrol.
**Success:** a Moodle account exists, a session (web) or token (mobile) is issued, and the learner lands on the home screen.

```
Learner submits {name, email, password, consent}
  → Moodle creates the user (signup)
  → local_pshub writes local_pshub_user_profile (audience, platform_role)
  → web: session      mobile: web-service token
  → Home
```
- Email uniqueness is enforced by Moodle.
- **There is no provisioning step and no second system to fall out of sync with.** This is the structural difference from v1.0 — see AC-2.
- Social login (Google/Apple) via OAuth2; same account, same profile row.

### 3.2 Login 🟢
**Actor:** Learner · **Goal:** resume.
**Success:** valid credentials → session or token → home, with "continue learning" populated.

One identity across both surfaces. Web uses the Moodle session; mobile calls `local_pshub_mobile_login` and stores a web-service token (§5).

### 3.3 Course discovery & enrolment (free) 🟢
**Actor:** Learner · **Goal:** find a course and start it.
**Success:** the learner is enrolled and the first lesson opens — one tap, one spinner, no visible system hop.

```
Browse catalogue (+filters) → open course detail (Screen F)
Tap "S'inscrire et commencer"
  gating_service::check() → free branch grants
    grant_auto()  → local_pshub_access(source=auto)
    enrol         → Moodle manual enrolment
    (both, atomically — never one without the other)
  → resume URL
Lesson player opens
```

Moodle's own enrolment UI is suppressed for hub courses: no `/enrol/index.php`, no enrolment key prompt, no "you are not enrolled" interstitial.

### 3.4 Lesson consumption, progress & quiz 🟢
**Actor:** Learner · **Goal:** learn and validate knowledge.
**Success:** content plays; progress persists; quiz scored; completion reflected in "mes cours".

Lesson content, media, completion, quiz questions, attempts and grading are **Moodle core**. `local_pshub` presents them and the client renders them. The learner never sees a module URL or an attempt id.

Progress is written through Moodle's completion API — there is no second progress store.

### 3.5 Access gating (decision logic) — `local_pshub`
Applied **before any sensitive action**: open course, open lesson, start quiz. Implemented in `local_pshub\access\gating_service::check()`, called by both the theme renderers and every web-service function.

| Course access type | Condition | Outcome |
|---|---|---|
| **free** | — | Grant. Enrolment additionally creates/confirms the auto access right. |
| **paid** 🟠 | valid access right exists | Grant. |
| **paid** 🟠 | no valid access right | Deny `payment_required` → client shows the paywall (§3.6). |
| **in a pack** 🟠 | valid pack-level right | Grant for every course in the pack. |

> In P1 only the **free** branch can grant. The paid and pack branches ship in M1, tested but held shut by `is_monetisation_enabled()`, which is false for all of P1 — this is what makes AC-12 assertable rather than aspirational. P2 opens them with no Moodle change.

### 3.6 Payment checkout 🟠
**Actor:** Learner · **Goal:** unlock a paid course or pack.
**Success:** payment confirmed → access auto-unlocked → enrolled → returned to the exact spot.

```
Paywall (single course vs pack) → choose method (OM / MoMo / Wave / card)
  → core_payment checkout via paygw_pshub
  → local_pshub_transaction(status=pending), aggregator returns redirect / STK instructions
Learner pays with the provider
Aggregator → POST /local/pshub/payment/callback.php   (signed, no session)
  → verify HMAC + amount → transaction=success (idempotent on provider_ref)
  → local_pshub_access(source=payment, status=active, expires_at?)
  → Moodle enrolment
  → notify client → access unlocked
```

No card data is stored on project servers — handled entirely by the aggregator (§9).

---

## 4. Data model

### 4.1 Where things live

Commercial and platform entities live in **`local_pshub` tables**; pedagogical data lives in **Moodle core** and is referenced by id. Nothing is duplicated across the two.

**User** (Utilisateur) — Moodle's `user` table, plus:

**`local_pshub_user_profile`**
| Field | Type | Notes |
|---|---|---|
| userid | int | → `user.id`, unique |
| audience | enum(`adult`,`kids`) | catalogue segmentation |
| platform_role | enum(`learner`,`teacher`,`admin`) | mirrors the Moodle role assignment |
| qual_language, qual_level, qual_skill | char | teacher qualification (M10–M11) |
| qual_verified_at | int | timestamp of the passing attempt |

> There is no `moodle_user_id`. There is one user.

**`local_pshub_course`** (Cours) — catalogue and commercial metadata beside a Moodle course
| Field | Type | Notes |
|---|---|---|
| courseid | int | → `course.id`, unique |
| categoryid | int | → `course_categories.id` |
| language, level, exam_type | char | catalogue + filtering |
| access_type | enum(`free`,`paid`) 🟠 | **the gating lever** (§3.5) |
| price_amount, price_currency | int / char | XAF; null when free |
| packid | int | → `local_pshub_pack.id`, nullable |
| is_published | int | mirrors `course.visible` |
| badge, rating, trending, active_this_week | mixed | catalogue presentation |

**`local_pshub_pack`** (Pack) 🟠 — `name`, `price_amount`, `price_currency`, `is_active`; membership in `local_pshub_pack_course`.

**`local_pshub_access`** (Droit d'accès) — *the heart of gating*
| Field | Type | Notes |
|---|---|---|
| userid | int | → `user.id` |
| target_type | enum(`course`,`pack`) | |
| target_id | int | course or pack |
| source | enum(`auto`,`payment`,`manual`) | `auto` in P1; `manual` for support grants |
| status | enum(`active`,`pending`,`expired`,`revoked`) | |
| expires_at | int | nullable (perpetual) |
| grantedby, grantreason | int / char | audit trail for manual grants |

Unique on `(userid, target_type, target_id)`.

**`local_pshub_transaction`** (Transaction) 🟠 — `userid`, `accessid`, `target_type`/`target_id`, `amount`, `currency`, `method` (`card`|`orange_money`|`mtn_momo`|`wave`), `provider`, `provider_ref` (**unique** — carries webhook idempotency), `status`, `confirmed_at`, `expires_at`.

**`local_pshub_learner`** — gamification snapshot: `xp`, `streak_days`, `badge_count`.

Also: `local_pshub_category_meta` (catalogue presentation on categories), plus the group, kids, oral-practice and push-token tables introduced in M5.

### 4.2 Relationships
```
user 1───* local_pshub_access *───1 (course | local_pshub_pack)
user 1───* local_pshub_transaction 1───1 local_pshub_access
local_pshub_pack 1───* course
course 1───1 local_pshub_course
user   1───1 local_pshub_user_profile
```

### 4.3 Moodle-owned data (referenced, never duplicated)
Course structure, sections, resources, activities, quiz questions, quiz attempts and grades, completion records, badges, files. `local_pshub` stores ids and platform metadata only.

---

## 5. Authentication & authorization

### 5.1 Sessions and tokens
- **Web:** the Moodle session.
- **Mobile:** a Moodle **web-service token**, obtained from `local_pshub_mobile_login` and stored in the platform keystore. Sent as the standard web-service token parameter.
- All exchanges over **HTTPS/TLS** (§9).

### 5.2 Single identity
One Moodle account serves both surfaces — there is no second identity to reconcile and no SSO handshake between systems.

**OAuth2** (M9) is additive: Moodle acts as an OAuth2 **client** for Google and Apple, and as a **provider** for future first-party surfaces. The handshake ends by issuing the same web-service token, so nothing downstream changes. Accounts created by password and by social login on the same email resolve to one user.

### 5.3 Authorization
Enforced by **Moodle capabilities**, checked inside every web-service function and every portal page — not by route configuration.

| Surface | learner | teacher | admin |
|---|:--:|:--:|:--:|
| Catalogue, own profile, own progress | ✅ | ✅ | ✅ |
| Enrol, lessons, quizzes | ✅ (gated) | ✅ | ✅ |
| `local/pshub:viewteacherportal` | — | ✅ | ✅ |
| `local/pshub:viewadminportal`, `:manageusers`, `:managecourses` | — | — | ✅ |
| `local/pshub:manageexams` | — | — | site admin |
| Payment callback 🟠 | — | — | signature auth only, no user session |

---

## 6. API specification (Moodle web services)

The mobile app calls Moodle web-service functions on the site itself. There is no separate API host. Functions are exposed through the service `ps_language_hub_mobile`; the contract is documented in [`docs/MOODLE-MOBILE-WS.md`](docs/MOODLE-MOBILE-WS.md).

`local_pshub_*` are platform functions; `core_*` and `mod_*` are Moodle core, called directly.

### 6.1 Auth
```
core_auth_request_signup            register
local_pshub_mobile_login            → { token, role, profile }
core_webservice_get_site_info       session validity + site metadata
local_pshub_get_profile             → { user, audience, qualification }
```

### 6.2 Catalogue & discovery 🟢
```
local_pshub_get_catalogue           filters: language, level, exam, access, q, page
local_pshub_get_course              detail + syllabus + enrolled flag
local_pshub_get_catalogue_facets    categories, exam tracks (ielts, toefl, toeic, delf, tcf)
```

### 6.3 Enrolment, content, progress 🟢
```
local_pshub_enrol_course                  gated (§3.5); may return payment_required 🟠
local_pshub_get_syllabus                  lessons with completion state
local_pshub_get_lesson                    content + file URLs
local_pshub_get_my_courses                in progress / completed / saved
local_pshub_get_progress                  percent, completed, total, last lesson
core_completion_update_activity_completion_status_manually   mark complete
webservice/pluginfile.php                 media — per-user, permission-checked, native
```

### 6.4 Quiz 🟢
```
mod_quiz_get_attempt_data / _start_attempt / _save_attempt / _process_attempt / _get_attempt_review
```
Attempts, scoring and review are Moodle's. If Moodle's page navigation cannot express the one-question-per-screen UI, a `local_pshub` façade wraps it — stateless, decided in M3.

### 6.5 Gamification, groups & rights
```
local_pshub_get_gamification        xp, level, streak, badges, journey
local_pshub_get_group               learning group + weekly picks
local_pshub_get_kids_catalogue      audience-filtered catalogue
local_pshub_record_oral_rep         oral practice repetition
local_pshub_register_push_token     device token for reminders
local_pshub_get_access_rights       the learner's own rights
```

### 6.6 Payments 🟠
```
core_payment + paygw_pshub          checkout (see §3.6)
/local/pshub/payment/callback.php   aggregator → site. HMAC-signed, no session,
                                    idempotent on provider_ref, always acks 200.
```

### 6.7 Administration
The admin surface is the **website**, not a web-service API — `/local/pshub/admin/*`, capability-gated (§5.3). Users, roles, courses & access, packs, access-rights ledger, invites, exam attempts, and 🟠 transactions.

### 6.8 Errors
Web-service functions raise Moodle exceptions carrying a stable identifier. Clients map identifiers to user-facing copy per §11; a learner never sees an identifier.

---

## 7. Moodle core usage

### 7.1 What core provides
| Purpose | Mechanism |
|---|---|
| Accounts | Moodle user + auth plugins |
| Courses, sections, activities | course core |
| Enrolment | manual enrolment, driven by `local_pshub` |
| Progress | completion API |
| Quizzes & results | `mod_quiz` |
| Achievements | `core_badges`, issued by `local_pshub` rules |
| Media | files API + `webservice/pluginfile.php` |
| Notifications | message providers |
| Payments 🟠 | `core_payment` + `paygw_pshub` |

### 7.2 No sync flows
v1.0 specified user-provisioning sync, catalogue sync and queued enrolment/completion jobs. **All three are gone** — they existed only to bridge a network boundary that no longer exists. Data is written once, where it lives.

### 7.3 Boundary rule
`local_pshub` **owns authorisation**; Moodle core **owns pedagogy**. Course configuration never decides paid or free access. Renderers and web-service functions consume `gating_service`'s verdict and never re-implement it. This is what keeps P2 monetisation a `local_pshub`-only change.

---

## 8. Payment workflow & error handling 🟠

### 8.1 Happy path
`checkout (pending)` → aggregator → learner pays → **signed callback** → verify → `success` → access right created → enrolment → client unlocked → return to the exact spot.

### 8.2 States & handling
| Situation | System behaviour | User-facing behaviour |
|---|---|---|
| Pending / awaiting confirmation | transaction `pending`; client polls status | "Paiement en cours…" with status |
| Callback success | idempotent on `provider_ref`; create right + enrol | auto-unlock, return to content |
| Callback duplicate | no-op — enforced by a unique index, not a code check | no change |
| Payment failed / declined | transaction `failed` | friendly retry; no code shown |
| Timeout / no callback | reconciliation cron re-queries the provider; expire after TTL | "Réessayer"; nothing charged silently |
| Signature / amount mismatch | reject, log, alert; transaction stays pending | support flow |
| Access expired mid-lesson | re-checked on the next gated action | non-blocking sheet, position preserved, "Renouveler" |

### 8.3 Aggregator
A single integration covering OM, MoMo, Wave and card, wrapped as the `paygw_pshub` gateway plugin. Candidates: **CinetPay, PayDunya, Flutterwave** — final choice pending coverage and fees (§14). No aggregator plugin for African mobile money exists in Moodle, so this is net-new plugin work. No card data on project servers (PCI handled by the aggregator — [`docs/PCI-POSTURE.md`](docs/PCI-POSTURE.md)).

---

## 9. Security (NFR)

| Requirement | Detail |
|---|---|
| Transport | HTTPS/TLS on all traffic — browser, app and aggregator callbacks. |
| Sessions & tokens | Moodle session (web); web-service token (mobile), revoked server-side on logout. OAuth2 from M9. |
| Authorization | Moodle capabilities, checked inside every web-service function and portal page. Clients are never trusted for gating. |
| Payment data | No card data stored; verify callback HMAC **and** amount before trusting anything. |
| Media | Served through `webservice/pluginfile.php` — per-user and permission-checked. |
| Rate limiting / idempotency | On login, signup, teacher-exam attempts, and payment callbacks (unique `provider_ref`). |
| Qualification exams | Held in a hidden category, unreachable from every learner surface. |
| PII | Minimal collection; align with local data norms. |

---

## 10. Acceptance criteria (QA)

Written as Given/When/Then. ✅ = must pass for the phase shown.

**AC-1 Registration 🟢** — *Given* valid details, *when* I register, *then* an account exists and I am signed in. *And* a duplicate email is refused without creating a user.

**AC-2 Single identity 🟢** — *Given* I register on either surface, *then* exactly one account exists and it works on both. *(v1.0 stated this as "provisioning resilience": that an account survives Moodle being briefly unavailable during registration. With one system there is nothing to provision and nothing to fall out of sync — the failure mode has been designed out rather than handled.)*

**AC-3 Free enrolment 🟢** — *Given* a free course, *when* I tap "S'inscrire et commencer", *then* an access right (`source=auto`) is created, I am enrolled, and the first lesson opens — with no visible system transition.

**AC-4 Progress & quiz 🟢** — *Given* I complete a lesson or quiz, *when* I return, *then* progress and score persist and "mes cours" reflects completion.

**AC-5 Quiz submit failure 🟢** — *Given* the network drops on submit, *when* I answer, *then* my answer is retained (optimistic UI), an inline non-blocking notice appears, the client auto-retries, and the attempt is finalised only on success.

**AC-6 Offline lesson access 🟢** — *Given* I lose connection, *when* I browse, *then* cached content stays usable, unavailable items are clearly marked, and queued progress syncs on reconnect.

**AC-7 Paywall on paid course 🟠** — *Given* a paid course I do not own, *when* I try to open it, *then* I get the paywall (single vs pack), not the content.

**AC-8 Payment unlock 🟠** — *Given* I pay via OM/MoMo/Wave/card, *when* the callback confirms, *then* an access right (`source=payment`) is created, I am enrolled, and I return to the exact spot.

**AC-9 Callback idempotency 🟠** — *Given* a duplicate callback for one `provider_ref`, *when* received, *then* it is a no-op — including under concurrent delivery.

**AC-10 Expired access mid-lesson 🟠** — *Given* my access lapses while a lesson is open, *when* I take the next gated action, *then* a non-blocking sheet offers renewal and my position is preserved.

**AC-11 Authorization 🟢** — *Given* a learner session or token, *when* I reach any admin surface or admin-scoped function, *then* I am refused.

**AC-12 Phase isolation 🟢** — *Given* a P1 build, *when* any course is opened, *then* only the free branch of gating can grant — **even for a user holding an access right** — and no payment UI is reachable.

---

## 11. Client error states (web & mobile)

Grammar: **preserve work · plain language · auto-recover · stay branded · never expose a system name or code.** Orange = recoverable/attention; red reserved for the truly unrecoverable.

| State | Trigger | Handling |
|---|---|---|
| Access expired mid-lesson 🟠 | gating re-check fails | non-blocking sheet, dim (not remove) content, "Renouveler", resume position after renewal |
| Quiz submit failure 🟢 | network/API error on submit | optimistic retain, inline retry notice, local cache + auto-retry, commit on success |
| Network interruption 🟢 | connectivity lost | calm offline strip; cached content usable; unavailable items dimmed; queued sync on reconnect |
| Login failure 🟢 | bad credentials | inline message, no modal, no code |
| Enrolment failure 🟢 | enrolment error | retry silently; if persistent, friendly "réessayer", keep the learner on the detail screen |

Responsive: the website is one set of components reflowed — sidebar and grid on desktop, single column with bottom tabs on mobile web.

---

## 12. Business metrics (KPIs)

| KPI | Phase | Source |
|---|---|---|
| Registrations & course completion rate | 🟢 | Moodle completion |
| Engagement: streaks, XP, active days | 🟢 | `local_pshub_learner` |
| Teacher qualification pass rate | 🟢 | teacher exam attempts |
| Free→paid conversion rate | 🟠 | access rights + transactions |
| ARPPU & retention | 🟠 | transactions + activity |
| Payment-method split (card/OM/MoMo/Wave) | 🟠 | `local_pshub_transaction.method` |

---

## 13. Constraints & exclusions (restated for stakeholders)

- Trainers author content in **standard Moodle**; the teacher portal handles the course shell, metadata and publication only.
- Marketplace (ratings, commissions, payouts) **excluded**, needs separate scoping.
- Custom plugins limited to `local_pshub`, `theme_pslanguagehub` and 🟠 `paygw_pshub`.
- The mobile app is a **learner** surface; teachers and admins work on the website.
- Detailed financials (CAC, revenue projections, hosting/aggregator-fee modelling) belong to a separate business-plan document.

---

## 14. Open decisions / dependencies

| # | Decision | Owner | Needed by |
|---|---|---|---|
| 1 | Payment aggregator: CinetPay vs PayDunya vs Flutterwave (coverage + fees) | Business/Tech | M13 |
| 2 | Signup confirmation: keep Moodle's email confirmation step, or auto-confirm hub accounts — affects AC-1 | Product/Tech | M5 |
| 3 | Hosting/infra for Moodle; media storage sizing | Infra | M14 |
| 4 | ~~Mobile stack~~ — **closed:** Flutter | — | — |
| 5 | ~~OAuth2 direction~~ — **closed:** web-service token (M5), OAuth2 client and provider (M9) | — | — |
| 6 | Certificate delivery (if offered) | Product | post-P1 |
| 7 | Environments: dev / staging / prod provisioning | Infra | M14 |

---

*End of functional specification v2.0. This is a living document; revise alongside the cahier des charges and field feedback.*
