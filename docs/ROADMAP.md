# Roadmap — PS Language Hub

**Version:** 3.0 · **Date:** September 2026
**Derived from:** [functional specification v3.1](../functional-spec-ps-language-hub.md) · [cahier des charges v2.0](cahier-des-charges-v2.md)
**Status:** Greenfield. No code exists.

**Changed in v3.0:** the web surface is **entirely server-rendered Mustache — no client framework at any phase** (spec §0.2 decision A). Two delivery teams, Moodle/PHP and design/CSS, meeting at the template context shape. **Phase 1 shortens from ten sprints to nine** and the whole plan from twenty-three to twenty-two: no build pipeline, no bundle budgets, no mock infrastructure, no bootstrap-payload contract, and no integration sprint — because with one rendering path, integration is continuous by construction.

---

## 0. How to read this

Sprints are **two weeks**, numbered continuously (S1…S22). Phase boundaries are release gates, not pauses.

Phase 1 runs as **two parallel tracks** meeting at a defined contract. Each sprint states both tracks, its **exit criteria** (verifiable, usually an AC from spec §14), and what it unblocks. Stories live in [BACKLOG.md](BACKLOG.md).

🟢 P1 Fondations · 🟠 P2 Expérience apprenant · 🔵 P3 Optimisation, matching cahier §8.

### 0.1 The two teams

| | **Team A — Moodle** | **Team B — Design/CSS** |
|---|---|---|
| Owns | `local_pshub`, gating, course validation, sessions, payments, notifications, web services, `templatable` classes and renderers | Design system, brand implementation, **every Mustache partial**, all CSS, the four AMD modules |
| Skills | Moodle plugin APIs, PHP 8.3, BBB | Mustache, CSS, Boost theming, design systems, light JS |
| Meets the other at | **the template context shape** | **the template context shape** |

**The template context shape is the contract**, and it is the thing most likely to sink the parallelism if treated casually. For every component, Team A defines what its `templatable` class exports — the array the partial receives — and Team B writes the partial against it.

Three rules make the parallelism real rather than nominal:

1. **The context shape is written down before the partial is.** Documented and versioned, exactly as an API would be. A partial written against a guessed shape is rework waiting to happen.
2. **Data shaping lives in PHP.** `export_for_template()` is unit-tested; the partial renders and never computes. This is what lets the two teams move without stepping on each other.
3. **Templates carry no capability decisions** — only their outcomes (spec §7.4). A partial renders what the context says is permitted; it never works out permission itself.

> Integration is **continuous by construction** here. There is one rendering path, so a partial either works against the real context or fails immediately. That is the main practical gain from dropping the client framework, and it is why there is no integration sprint.

### 0.2 The assumption, and its biggest unknown

Two teams of two, plus part-time QA shared across both; a Flutter developer joining at S9. Twenty-two sprints is roughly **ten and a half months** at that shape, with P1 at S1–S9.

**Actual headcount is still unknown.** Two specific risks:

- **Moodle depth is the scarce skill.** Team A carries the critical path alone for the first two sprints — schema, then gating — and nothing Team B builds can ship without it. One developer who genuinely knows Moodle's plugin and theme APIs is worth more than two learning them here. Team B needs **Boost theming depth** too: with everything server-rendered, the theme layer is the entire front end.
- **The Flutter developer starts cold at S10**, because the web-service API does not exist until then (spec §6.0). Hire for S9 so they are productive on day one of S10.

Revise this section once the team is real. Everything downstream derives from it.

---

## 1. Critical path

Team A owns all of it. Team B cannot shorten it, which is why S1–S2 look thin on the design side.

```
Environment (Moodle 5.3 RC + BBB server)
  → local_pshub skeleton + FULL schema
    → gating_service                          ← one gatekeeper, spec §3.7
      → course validation workflow            ← AC-1, the cahier's headline
        → BBB session lifecycle + 50-min cap  ← AC-4, the product itself
          → payments + validation queue       ← AC-3, the revenue
            → P1 acceptance pass
```

**The schema is the tightest constraint.** Spec §1.1's design mandate requires every table in P1 — access, transaction, pack, XP, competency-assignment, group, attendance — even where the features reading them ship in P2. Getting it wrong is not a bug to fix later; it is a migration against live payment data.

**`gating_service` is second.** Every renderer and every web-service function consults it. Anything built before it needs rewriting to use it.

---

## 2. Phase 1 🟢 — Fondations (S1–S9, ~4.5 months)

**Release gate:** a teacher creates a course, an admin validates it, a learner finds it, pays for it, and attends a 50-minute live class — on a weak connection.

### S1 — Foundations ⛓

| Team A | Team B |
|---|---|
| Moodle 5.3 RC + BBB server; dev and staging meeting the platform floors (spec §13.6) | Design system foundation: brand tokens (spec §12.4), type scale, spacing, colour roles |
| `local_pshub` skeleton; **complete schema, every table** (spec §4.1) | Boost child theme scaffolding, built **against the 5.3 RC** |
| Four roles and capabilities (spec §5.3); `pshub_admin` assignable only by site admin | Partial library conventions; the register of overridden core templates, started empty |
| `local_pshub_audit` service | Component primitives as partials: button, field, card, badge, dialog |
| 🔬 **Bandwidth spike** on a real Cameroonian network (spec §8.4) | CI check: **no framework bundle, page JS under 50 KB** (AC-21) |
| **Both teams: agree the template context shape convention** | |

**Exit:** schema installs and upgrades cleanly; a learner cannot reach any admin capability (**AC-11**); context-shape convention agreed in writing; bandwidth spike has produced a yes/no on 64 kbit/s.

> If the spike says 64 kbit/s is unreachable, **renegotiate spec §13.2 with the client now** — before S6 builds against a number that is wrong.

### S2 — The gatekeeper ⛓

| Team A | Team B |
|---|---|
| `gating_service::check()` — free, paid, pack (held shut), not-published (spec §3.7) | Design system: navigation, tables, empty and error states |
| Access-right lifecycle: create, expire, revoke, manual grant with reason | Public page partials: home, catalogue, course card with badges |
| `are_packs_enabled()`; pack branch built, tested, shut | Dashboard and course-experience designs |
| Free enrolment: access right + enrolment, atomically | **View Transitions** for page-to-page navigation |
| Course-type CRUD and price resolver (spec §3.3) | |

**Exit:** **AC-8**, **AC-9**, **AC-18** (P1 half).

### S3 — The public face

| Team A | Team B |
|---|---|
| Page shells and renderers: public, learner, teacher, admin | Public pages styled and finished |
| Public home with featured courses; catalogue with filters; course detail | Signup and login styling over Moodle forms |
| Signup, login, discreet staff door (spec §3.2) | Responsive pass: sidebar and grid → single column with bottom tabs |
| App-store links; FR/EN throughout | |
| **Performance baseline on throttled 3G, recorded** | |
| 🔬 **50-minute warning spike** (spec §8.3) | |

**Exit:** **AC-2**, **AC-17**, **AC-20** (works without JavaScript), **AC-21**; first 3G measurement on record.

### S4 — Course validation workflow

| Team A | Team B |
|---|---|
| Teacher portal; create course shell; my courses with status | Teacher and admin portal styling |
| Submission checks (spec §3.4) | Validation queue and review states |
| Admin validation queue; approve / reject with required comment | **Learner dashboard** — the Sharecourse reference (spec §12.2) |
| `review_status` ⟺ `course.visible` enforced in one place | My courses, course experience |
| Featured-course selection | |

**Exit:** **AC-1** — a teacher cannot publish by any route, including a direct URL.

### S5 — Live sessions, first light ⛓

| Team A | Team B |
|---|---|
| Session scheduling; `local_pshub_session` + BBB instance creation | **Session lobby and card** — the most important screen in the product |
| Calendar events for teacher and learners | **AMD module: countdown + join activation** against a server timestamp |
| Signed single-use join URLs; moderator vs viewer | Join states: too early, open, full, ended, access lapsed |
| Join gating: T-15 window, capacity, access re-check | |
| **50-min cap, all three layers** (spec §8.3) | |

**Exit:** **AC-4** tested hostilely — clients disconnected, device clocks altered, the meeting still ends. **AC-10**.

### S6 — Live sessions, hardened ⚠️

| Team A | Team B |
|---|---|
| Audio-only fallback; "mode économie de données" | **AMD module: connection/quality strip** |
| **Per-participant screen-share suppression** (spec §8.4) | Degradation messaging — calm, once, non-blocking |
| Auto-reconnect preserving place and whiteboard state | Reconnection states |
| Attendance capture and reconciliation (spec §8.6) | BBB session branding (spec §12.4) |
| BBB load test against the concurrency target | |

**Exit:** **AC-5**, **AC-14**, **AC-16**, **AC-19**.

### S7 — Payments ⚠️

| Team A | Team B |
|---|---|
| `paygw_pshub`: MTN MoMo, Orange Money, Wave, card | Paywall; method selection; MSISDN entry |
| Signed callback; idempotency on `provider_ref` | **AMD module: payment status polling** |
| Admin validation queue; per-method `auto_validate`, default off | "Paiement reçu — validation en cours" state (spec §15) |
| Back-office transfer and cash through the same path | Receipt and history views |
| Reconciliation task for stuck transactions | |

**Exit:** **AC-3**, **AC-12** including concurrent duplicate callbacks.
**Blocked until** decision 8 closes — this sprint cannot start without a provider sandbox. Close it by **end of S4**.

### S8 — Notifications and calendar

| Team A | Team B |
|---|---|
| Message providers; email and in-app; plain-language preferences | Notification and calendar surfaces |
| Reminders T-24h / T-1h / T-10min, idempotent by constraint | Branded transactional email templates |
| Re-queue on reschedule, cancel on cancellation | |
| Announcements, validation and payment notifications | |
| FCM token groundwork for S12 | |
| Calendar and iCal export | |

**Exit:** **AC-6**, including no duplicates on a repeated cron run.

### S9 — Assessment surfaces and the P1 gate

| Team A | Team B |
|---|---|
| Quiz, assignment, workshop presented without Moodle chrome | **AMD module: optimistic quiz submit with retry** (**AC-15**) |
| Security pass: capabilities, callback signatures, join-URL TTLs | Error-state pass across spec §15 |
| Backup **and restore**, into a clean environment | Accessibility pass; final polish |
| **Both teams: full acceptance pass, AC-1 → AC-21** | |

**Exit:** every P1 acceptance criterion passes, recorded not asserted. Anything failing is fixed or deferred **in writing**.

---

## 3. Phase 2 🟠 — Expérience apprenant (S10–S18, ~4.5 months)

The Flutter developer joins. Team B continues on web; Team A splits between web services and platform features.

| Sprint | Goal | Exit |
|---|---|---|
| **S10** | Web-service API — auth, catalogue, enrolment, sessions (spec §6) | Callable and documented; the app has something to build against |
| **S11** | Flutter: auth, catalogue, course detail; <25 MB measured in CI | Artefact size tracked from the first build |
| **S12** | Flutter: sessions, join, FCM push | A learner attends a class from the phone |
| **S13** | Flutter: offline cache, Wi-Fi-only downloads, WebP, connection indicator | Spec §5.5 advanced low-bandwidth |
| **S14** | XP engine, admin-configurable rules, ledger idempotency | **AC-13** |
| **S15** | Competencies, learning plans, teacher briefs enforced at submission | The S4 soft check becomes blocking |
| **S16** | Certificates: issue, brand, QR verification | **AC-7**. ⚠️ Re-check `mod_customcert` 5.3 support before S14 |
| **S17** | **Packs go live** | **AC-18** P2 half. Can move earlier — the branch exists from S2 |
| **S18** | Dashboard completion, streaks, groups, kids catalogue | Spec §12.2, §11.1.1, §11.4, §11.5 |

S14–S17 are independent of S10–S13 and run in parallel across the two teams.

> **Watch the partial library here.** S14–S18 add seven substantial authenticated areas — the point at which Mustache's verbosity is felt (spec §12.1). Keep partials flat, keep `export_for_template()` tested, and let design tokens rather than over-parameterised partials carry visual consistency.

---

## 4. Phase 3 🔵 — Optimisation (S19–S22, ~2 months)

| Sprint | Goal |
|---|---|
| **S19** | Accounting: revenue by course, period, teacher; audit trail surfaced |
| **S20** | CSV/Excel export; receipts and invoices, gapless numbering |
| **S21** | Recording, publication, replay, retention policy |
| **S22** | In-session polls; SMS channel; field-feedback backlog |

---

## 5. Risk register

| Risk | Impact | Mitigation | Owner |
|---|---|---|---|
| **5.3 theme APIs churn during the RC** | Rework across the whole front end — with everything server-rendered, **this is now the main front-end risk** | Build against the RC from day one; override as few Boost templates as the design allows; keep the override register current from S1 (spec §12.1) | Team B lead |
| **Context shapes left implicit** | Team B writes partials against guesses; rework from S3 | Write the shape before the partial; `export_for_template()` unit-tested | Both leads |
| **64 kbit/s proves unreachable** | Headline NFR is wrong; client expectation reset | Spike in **S1** on a real network. Renegotiate §13.2 before S6 | Tech + Client |
| **Hosting cannot meet 5.3 floors** | Environment blocked, possibly after a contract is signed | Verify **before** signing (spec §13.6) | Infra |
| **Payment aggregator lacks Wave** | S7 slips or needs a second integration | Close decision 8 by end of S4; Wave is now a selection criterion | Business |
| **Team A is the bottleneck** | Team B idles in S1–S2; critical path is single-threaded | Front-load Team B with the design system, which needs nothing from Team A | Product |
| **JavaScript creep** | The four AMD modules become fifteen, and a framework nobody chose | **AC-21 is an automated build check**; a fifth module is a design conversation | Tech |
| **Mustache verbosity bites in P2** | S14–S18 slow down; partials sprawl | Flat partial library, tested exports, tokens over parameters (spec §12.1) | Team B lead |
| **`mod_customcert` 5.3 support absent** | S16 blocked | Re-check before S14; fall back to in-house rendering | Tech |
| **BBB capacity unknown** | Sessions degrade at launch | Close decision 7 before procurement; load-test in S6 | Business + Infra |
| **Cahier and spec disagree** on packs, groups, kids, streaks, Wave | Scope argument mid-build | Amend the cahier (decision 13) before P2 | Product |

---

## 6. Definition of done

A story is done when **all** hold:

1. The behaviour matches the spec section it cites — or the spec is updated in the same change.
2. Authorisation is checked in `local_pshub`, never in a renderer, a template or a route definition (spec §7.4). **A partial renders what the context permits; it never computes permission.**
3. Tests exist: PHPUnit for services and for `export_for_template()`, at minimum one per acceptance criterion touched.
4. Idempotency is enforced by a **database constraint** where the spec says so — `provider_ref`, `(userid, event_key, itemid)`, `(sessionid, offset_minutes, audience)` — not by a code check.
5. No learner-facing string is hardcoded in one language.
6. No Moodle page, system name or raw error identifier reaches a learner (**AC-17**).
7. **The page works with JavaScript disabled** (**AC-20**), and ships **no framework bundle**, under 50 KB of JS (**AC-21**).
8. Any core Moodle template overridden is added to the override register, with a reason.
9. If it renders, it was measured on throttled 3G.
10. Course-validation and payment-validation transitions write `local_pshub_audit`.

---

## 7. Verification

| Gate | Method |
|---|---|
| Unit and integration | `php vendor/bin/phpunit --filter local_pshub` from the Moodle root |
| Acceptance | One documented pass per AC in spec §14, per phase gate |
| Performance | Lighthouse, throttled 3G, cold cache — every learner route |
| No-JS | Automated: load each route with scripting disabled, assert core content and forms work (**AC-20**) |
| No framework | Automated: assert no framework bundle, page JS under 50 KB (**AC-21**) |
| Live session | Manual, two devices, one throttled below the screen-share threshold |
| Backup | Restore into a clean environment. A backup never restored is a hypothesis |

---

*Revise alongside the specification. Sprint contents are a plan, not a contract — the critical path in §1, the contract rules in §0.1 and the definition of done in §6 are the parts that should not drift.*
