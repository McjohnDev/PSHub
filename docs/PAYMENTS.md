# Payments: the provider seam, and how it is tested before anything is wired

**Status:** design, agreed 2026-10-03. Nothing in this document is built yet — it is what
`PSH-S07-00` builds, and it is deliberately written before the merchant account exists.

**Provider:** Flutterwave (decision 8, closed 2026-10-03). Methods at launch: MTN Mobile
Money, Orange Money, Wave, card. Currency: XAF.
See §9 of the functional specification for the payment model itself; this document covers
only the seam and the test strategy.

---

## 1. The problem this solves

Sprint 7 is where payments get built, and the merchant account has an external clock:
identity documents, a company bank account, Flutterwave's own review. If the code waits
for the account, sprint 7 starts on the day the account opens and every bug in the flow is
discovered under time pressure, against a live gateway, with real money.

It does not have to. **Almost nothing in the payment flow is Flutterwave-specific.** The
provider answers one question — *was this reference paid, how much, in what currency* —
and everything else is ours: the paywall, the pending state, the callback endpoint, the
idempotency guarantee, the admin validation queue, the access grant, the audit trail.

So the provider goes behind an interface on day one, and a fake implementation lets the
whole flow be built and tested with no account, no network and no money.

---

## 2. The seam

```
local_pshub\payment\payment_provider      (interface)
  ├─ local_pshub\payment\flutterwave_provider   real HTTP, real keys
  └─ local_pshub\payment\fake_provider          scripted answers, no network
```

```php
namespace local_pshub\payment;

interface payment_provider {

    /**
     * Begin a payment. Returns what the client needs to continue: a redirect
     * URL for card, or instructions for a Mobile Money push.
     *
     * Must be idempotent on $reference: calling it twice for the same
     * reference starts one payment, not two.
     */
    public function start(payment_request $request): payment_start;

    /**
     * Ask the provider what happened to a reference. Used by the pending
     * screen's poll and by the reconciliation task (§9.3) - never trusted
     * from the client.
     */
    public function query(string $reference): payment_status;

    /**
     * Turn a raw callback body into a payment_status, or throw.
     * Pure: no database, no HTTP, no clock.
     */
    public function parse_callback(string $body): payment_status;

    /**
     * Whether this callback really came from the provider.
     * Pure, and the only thing standing between the callback endpoint and
     * anyone on the internet who can read our URL.
     */
    public function verify_signature(string $body, array $headers): bool;
}
```

Three rules that make the seam worth having:

1. **`parse_callback()` and `verify_signature()` touch nothing.** No database, no clock, no
   `$CFG`. That is what makes them testable against a recorded payload in a unit test
   rather than an integration test.
2. **The provider never grants anything.** It reports. The access right is created by the
   validation path (§3.8), from a `validated` transaction, and by nothing else. A provider
   — real or fake — that could open a course would make the fake one a security hole.
3. **The provider never writes the transaction.** The caller does, so there is one place
   where `pending → paid_unvalidated` happens and one place where idempotency is enforced.

---

## 3. The fake provider, and keeping it out of production

`fake_provider` answers from a script instead of the network: pay, decline, go quiet, pay
the wrong amount, pay in the wrong currency, answer slowly, call back twice.

This is a provider that says "paid" for free, so **it must be impossible to switch on by
accident.** Four guards, all of them required:

| Guard | Why this one |
|---|---|
| A line in `config.php`: `$CFG->pshub_fake_payments = true;` | A setting in the admin UI can be clicked. A line in `config.php` is deployed on purpose, and is not in the database that a production restore might carry over |
| The class refuses to construct without it, and throws | Not a silent fallback to the real provider either: a dev box that quietly started charging real cards would be worse |
| Every fake transaction writes an audit row with `provider = 'fake'` | A payment history that cannot distinguish real from fake money is not an accounting record (§9.4) |
| A permanent red banner in the admin portal whenever it is active | Someone must be able to tell, in two seconds, that this site's payments are not real |

The dev-only `config.php` lines are already documented in `ENVIRONMENT.md`. Since
2026-10-10 this is the **only** one of them that must never reach production: the mail
setting that used to sit beside it, `$CFG->noemailever`, is gone, replaced by a local
mail catcher that sends for real into a box no one else can read.

---

## 4. What is testable today, with no account

| What | How it is tested |
|---|---|
| The whole flow | paywall → `pending` → callback → `paid_unvalidated` → admin queue → `validated` → access right → enrolment. The fake provider stands in for the one step that leaves the building |
| **AC-12**, idempotency | Replay the same callback twice, and concurrently. The guarantee is the unique index on `provider_ref` — the database enforces it whoever calls |
| Signature verification | A signature is a hash over the body with a shared secret, so the test makes its own valid pair and its own tampered one. No network |
| Amount and currency | Replayed payloads with the wrong amount, the wrong currency, an unknown reference. All must leave the transaction `pending` and alert, never grant |
| The real endpoint | `cli/replay_callback.php` posts a fixture to `/local/pshub/payment/callback.php` with a correct signature. The endpoint is sessionless by design, so it needs no browser and no public URL |
| The pending screen | The fake provider can answer slowly, fail, or never call back — which is the only way to test the "never a spinner that assumes success" rule (§9.2) |
| Reconciliation | Leave a transaction `pending` with no callback, run the task, assert it re-queries and expires rather than hanging forever |

### Fixtures

```
local/pshub/tests/fixtures/flutterwave/
    charge-completed.json
    charge-failed.json
    charge-wrong-amount.json
    charge-duplicate.json
```

**These start as assumed shapes.** We have not seen a Flutterwave payload. A green test
suite built on invented fixtures proves our code is consistent with our guess — it is a
scaffold, not evidence, and must be described that way in the sprint notes. The fixtures
carry a header comment saying so until they are replaced.

---

## 5. What this does not prove

- That we can parse Flutterwave. Only a real sandbox response proves that.
- The MoMo push on a real handset, settlement delay, fees, rate limits.
- Which methods are actually enabled for this merchant, Wave included.
- Anything about money arriving in a bank account.

---

## 6. The day the account opens

1. Test keys in `config.php`, switch the setting to `flutterwave_provider` on a dev box.
2. **Capture real sandbox payloads and overwrite every fixture.** Expect the parser to be
   wrong somewhere; that is the point of doing it before launch rather than after.
3. Callbacks need a publicly reachable URL: a tunnel to a dev box, or the staging host.
   Staging is better — it exercises the real domain and certificate too.
4. One smoke run with a provider test MSISDN, then **one small real transaction** before
   launch, refunded and reconciled. Sandboxes do not prove settlement.
5. Re-read their webhook verification documentation at that moment and check our
   implementation against it. Never from memory.

---

## 7. Where this lives in the backlog

| ID | What |
|---|---|
| `PSH-S07-00` | The interface, `fake_provider`, the fixtures, the replay CLI. **Not blocked. Build first** |
| `PSH-S07-01` | `flutterwave_provider`. Needs the account |
| `PSH-S07-05` | Signed callback: signature, amount **and** currency |
| `PSH-S07-06` | Idempotency via the unique index — a constraint, not a check |

Opening the merchant account is a business task with an external clock. It can start now,
and the gateway work does not wait for it.
