# The API the PS Language Hub application talks to

**For:** whoever builds the handset application.
**Status:** 2026-10-13. Four functions exist. Everything else in here is marked as absent,
on purpose — an API document that describes endpoints nobody wrote is how a client gets
built against a server that was never going to answer.

---

## 1. Why there is an API at all

The platform ships **its own application**, not Moodle's (spec §6.1). That decision is what
makes this document necessary: Moodle's own mobile app talks to
`MOODLE_OFFICIAL_MOBILE_SERVICE`, and our functions are deliberately **not** on it. Ours
live on a service called `pshub_app`.

If you find yourself about to add a function to the official mobile service to make
something work, stop: you would be handing it to an application nobody here wrote, on every
site that installs this plugin.

---

## 2. Signing in

The app exchanges a username and password for a **web-service token**, once, and keeps it.

```http
POST /login/token.php
Content-Type: application/x-www-form-urlencoded

username=larissa&password=…&service=pshub_app
```

```json
{ "token": "6f1c…", "privatetoken": null }
```

The `service` parameter must be exactly `pshub_app`.

**Treat the token as the password.** It does not expire by default, it is a bearer
credential, and anyone holding it is the account. Keep it in the platform keystore
(`EncryptedSharedPreferences` on Android, Keychain on iOS) — never in plain preferences,
never in a log line.

Failures come back as JSON with an `error` key, not as an HTTP status:

| What happened | `errorcode` |
|---|---|
| Wrong username or password | `invalidlogin` |
| The account exists but is unconfirmed | `usernotconfirmed` |
| `service` names something that is not enabled | `servicenotavailable` |

> `login/token.php` is unauthenticated, so it is brute-forceable. Rate-limiting it is a
> server job and is **not done yet** — see ENVIRONMENT §2.4.

---

## 3. Calling a function

Every call is the same shape:

```http
POST /webservice/rest/server.php
Content-Type: application/x-www-form-urlencoded

wstoken=<token>&wsfunction=<name>&moodlewsrestformat=json&…parameters
```

Always send `moodlewsrestformat=json`; without it the answer comes back as XML.

**Errors are HTTP 200 with an exception body.** This is Moodle's convention and it catches
every new client out:

```json
{
  "exception": "core\\exception\\moodle_exception",
  "errorcode": "device_badplatform",
  "message": "Plateforme inconnue. Attendu : android, ios ou web."
}
```

So: parse the body and look for `exception`, rather than trusting the status line.

`message` arrives **in the account's own language**, which is what you show. `errorcode` is
stable and is what you branch on.

On the development box the body also carries `stacktrace`, `debuginfo` and
`reproductionlink`, because developer debugging is on there. **Those keys vanish in
production.** Parse `errorcode` and `message`; anything else will be missing exactly when it
matters.

---

## 4. What exists today

### `local_pshub_register_device`

Tells the platform where this person can be reached by push.

| Parameter | Type | |
|---|---|---|
| `token` | string, ≤255 | The push token the OS issued this install |
| `platform` | string | `android`, `ios` or `web` |

```json
{ "id": 1, "registered": true }
```

**Call it on every launch.** The operating system refreshes a push token whenever it
chooses, and registering a token twice is one device, not two — the server is idempotent on
the token.

Three things worth knowing, because they are the design and not an accident:

- **There is no `userid` parameter.** The token you authenticated with says who you are.
  This is not an oversight to work around.
- **A push token belongs to a device, not to a person.** Registering a token the server
  already holds for somebody else *moves* it, and the previous owner stops being reachable
  there. This is deliberate: handsets get passed on, and the alternative is one learner
  reading another's timetable on a lock screen.
- **One person may have several devices.** A phone and a tablet are two rows.

Errors: `device_badtoken` (empty, or longer than 255), `device_badplatform`.

### `local_pshub_deregister_device`

| Parameter | Type | |
|---|---|---|
| `token` | string | The push token to forget |

```json
{ "removed": false }
```

`removed: false` means the server was not holding that token **for this account** — either
it never had it, or it belongs to somebody else. It is **not an error**, and sign-out must
succeed either way.

**Call it before you drop the web-service token on sign-out.** On a shared handset this is
the difference between the next person seeing their own phone and seeing somebody else's
class reminders.
### `local_pshub_get_agenda`

The app's home screen: when this person is next expected somewhere, and whether they can
get in.

| Parameter | Type | |
|---|---|---|
| `includerecent` | bool, optional | Also return the classes that have just been. Default `false` |

```json
{
  "upcoming": [{
    "dayiso": "2026-10-24",
    "daylabel": "samedi 24 octobre 2026",
    "istoday": false,
    "sessions": [{
      "sessionid": 9, "courseid": 9,
      "coursename": "Anglais A2 — conversation pratique",
      "title": "Séance 2 — faire ses courses",
      "starttime": "18:00", "duration": "50 min",
      "url": "http://.../learn/session.php?id=9",
      "courseurl": "http://.../learn/course.php?id=9",
      "islive": false, "isended": false,
      "canjoin": false, "note": null, "state": "tooearly"
    }]
  }],
  "recent": [],
  "servertime": 1791925097,
  "timezone": "Africa/Douala"
}
```

**`canjoin` is the server's verdict.** It comes from the same function the web lobby and the
door itself use. Do not work it out from `starttime` — see §6.

`state` is one of `ok`, `tooearly`, `full`, `noaccess`, `ended`, `cancelled`. `note` carries
a sentence when there is something useful to say, and is `null` when the row speaks for
itself — a future class is "too early", and saying so on every row turns a diary into a wall
of apology.

`daylabel` is written for a person: "Today", "Tomorrow", or the date. `dayiso` is for you.

`servertime` is Unix time **on the server**, and `timezone` is the zone the strings above
were formatted in. Show them. A wrong device clock is ordinary on the handsets this product
targets, which is exactly why the web pages print the server's time.

Days with nothing in them are not returned. An empty diary is `"upcoming": []`, never
`null`.

### `local_pshub_get_my_courses`

No parameters. The caller is the answer.

```json
{ "courses": [{
    "courseid": 9,
    "fullname": "Anglais A2 — conversation pratique",
    "summaryhtml": "<p>...</p>",
    "langcode": "en", "ceflevel": "A2",
    "isfree": true, "price": 0, "currency": "XAF",
    "priceformatted": "Gratuit",
    "isonetoone": false, "sessionminutes": 50,
    "imageurl": null,
    "weburl": "http://.../local/pshub/course.php?id=9"
}] }
```

Three things to get right:

- **`price` is in the currency's own units.** XAF has no subunit. Do not divide by 100.
  `priceformatted` is in the payload precisely so you never have to decide — and it says
  "Gratuit" for a free course, not "0 FCFA". That one was a real bug, found by calling the
  endpoint: the web never showed it because every template branches on `isfree` first.
- **`summaryhtml` is HTML**, already run through Moodle's filters. Render it or strip it;
  do not show it as plain text.
- **There is no `cardtone`, `langplate` or `ctalabel`.** Those are the web pages' own
  answers — a card background, a two-letter plate, the words on a button. The app lays
  itself out, and a field we publish is a field we can never change.


---

## 5. What does not exist yet

Do not design around these; ask before you need them.

| Missing | Why it matters to you |
|---|---|
| **Any push actually being sent** | `register_device` records where a push *could* go. There is no sender yet (`PSH-S08-10`). Registering works and nothing will arrive |
| **A catalogue endpoint** | The app can read what the caller is *taking*, not what is for sale |
| **A join endpoint** | The diary says `canjoin`; there is nothing to call when it is true. The web lobby is the only door |
| **Payments** | No endpoint, and no merchant account behind one |
| **Token expiry or refresh** | Tokens are permanent. There is nothing to refresh against |
| **Rate limiting on sign-in** | Server-side work, not yours |

`local_pshub_get_agenda` already returns `canjoin`, so the shape of a future join endpoint
is settled: it will take a `sessionid`, re-check the verdict at that instant, and return a
room to enter or a reason it cannot be entered — never a boolean.

---

## 6. One rule to carry over from the web

**The app must never decide for itself whether a class can be joined.** The server answers
that, from `session_service::can_join()`, and it re-checks at the moment of the click. A
client that works it out from a start time and a clock will be wrong — the device clock is
wrong often on the handsets this product targets, which is precisely why the web pages print
the server's time.

When a join endpoint appears it will return a verdict and a reason, not a boolean, for the
same reason the web does: "too early", "full" and "your access has lapsed" need three
different sentences and three different next steps.

---

## 7. Trying it by hand

With the development box running (see `ENVIRONMENT.md`):

```bash
TOKEN=$(curl -s 'http://pshub-moodle.test/login/token.php' \
  -d 'username=larissa' -d 'password=PsHub#Test2026' -d 'service=pshub_app' \
  | sed 's/.*"token":"\([a-f0-9]*\)".*/\1/')

curl -s 'http://pshub-moodle.test/webservice/rest/server.php' \
  -d "wstoken=$TOKEN" \
  -d 'wsfunction=local_pshub_register_device' \
  -d 'moodlewsrestformat=json' \
  -d 'token=fcm-test-device' \
  -d 'platform=android'
```

Every example in this document was run against that box on 2026-10-12.
