# Spike: enforcing the 50-minute cap, and warning at 45 and 48 minutes

`PSH-S03-09`, decision 3. Investigated 2026-10-04 by reading `mod_bigbluebuttonbn`
2026042000 as shipped with Moodle 5.3dev in this installation. **No BigBlueButton server
was available**, so everything below is divided into what the code proves and what a server
still has to confirm.

AC-4 is the criterion this serves: *a session ends at the cap, and it still ends when every
client has disconnected and when a device clock has been altered.*

---

## 1. The finding that shapes the whole design

**`mod_bigbluebuttonbn` never sends BigBlueButton's `duration` parameter.**
`meeting::create_meeting_data()` (classes/meeting.php:385) builds the `create` payload and
sets `meetingID`, passwords, `record`, `welcome`, `voiceBridge`, `maxParticipants`,
`muteOnStart`, guest policy and the lock settings. There is no `duration`, and no module
setting produces one.

Since `duration` is what makes the **server** end a meeting on its own, nothing in the
module as shipped can guarantee AC-4. A meeting created through it runs until somebody ends
it or the last participant leaves.

## 2. There is a supported way in, and it is not a core patch

The module declares a subplugin type, `bbbext` (`db/subplugins.json`), and the proxy calls
into it on **every** API action:

```php
// mod/bigbluebuttonbn/classes/local/proxy/proxy_base.php:58
protected static function action_url(string $action, array $data, array $metadata, ?int $instanceid): string {
    $baseurl = self::sanitized_url() . $action . '?';
    ['data' => $additionaldata, 'metadata' => $additionalmetadata] =
        extension::action_url_addons($action, $data, $metadata, $instanceid);
    $data = array_merge($data, $additionaldata ?? []);
    ...
    $checksum = self::get_checksum($action, $params);
```

Two things matter here, and both are verified by reading it:

1. **The subplugin's parameters are merged before the checksum is computed.** An injected
   `duration` is signed like any other parameter, so BigBlueButton accepts it. A design that
   appended to the URL afterwards would fail the checksum.
2. **`$action` is passed in**, so the subplugin can add `duration` to `create` and nothing
   else.

So **layer 1 is a `bbbext_pshub` subplugin** — roughly thirty lines — implementing
`mod_bigbluebuttonbn\local\extension\action_url_addons::execute()`. No core file is
modified, and the mechanism is one the module documents and tests. That is the difference
between a cap that survives the next Moodle upgrade and one that does not.

## 3. The three layers, and why none of them is enough alone

| Layer | What it is | What it survives | What it does not |
|---|---|---|---|
| **1. `duration` at creation** | The subplugin above. BBB ends the meeting itself | Every client disconnecting; our site being down; any device clock | A meeting created before the subplugin was installed |
| **2. Countdown and warnings** | Our own UI, from a **server** timestamp sent with the page | A clock the learner has changed | Nothing — it is information, never enforcement |
| **3. Sweeper task** | Scheduled task calling `end_meeting` on anything past its end | A server that ignored `duration`; a meeting created outside our flow | Cron not running |

Layer 1 is the guarantee. Layer 3 is the belt. Layer 2 is courtesy — a class that vanishes
without warning is a bad experience, but a warning that does not arrive is not a breach of
AC-4.

## 4. The 45 and 48 minute warnings

There is **no BigBlueButton API for "warn at minute N"**. Two mechanisms are available and
they are not alternatives — they answer to different people:

- **BBB's own end-of-meeting notice.** When `duration` is set, the HTML5 client shows the
  participants that the meeting is about to end. ⚠️ **To confirm on a real server**: the
  exact wording, how many minutes ahead it appears, and whether it is configurable. Do not
  design around a specific number until it has been seen.
- **Ours, in the session page.** The page is served with the **server's** current time and
  the session's end time; the countdown is arithmetic on those two, and the 45 and 48 minute
  marks are thresholds crossed by it. This is `PSH-S05-06` and `PSH-S05-09`.

**Never the device clock.** On the handsets this product targets a wrong clock is ordinary,
not an attack, and a countdown that reads the device would quietly show the wrong number to
exactly the learners least able to question it.

## 4b. Built, and what the building confirmed

`bbbext_pshub` exists (2026-10-04) and is enabled. Three things the code review
predicted were then checked against the running site, with no BigBlueButton server
involved - the URL is built and signed locally, so only the server's *reaction* needs one:

| Checked | Result |
|---|---|
| `duration` reaches the `create` URL | `meetingID=test-1&duration=50` |
| It is inside what the checksum covers | the module's own `get_checksum()` reproduces the sent value exactly |
| A tampered value would be rejected | changing `duration=50` to `90` no longer matches the checksum |
| Other actions are untouched | `join`, `end`, `getMeetingInfo`, `getRecordings` add nothing |
| A known session wins over the site cap | a 45-minute session sends `duration=45` |
| The meeting carries its session | `meta_pshub-sessionid`, so recordings and attendance match back without guessing |

One correction worth keeping: the first check reported a checksum mismatch and the harness
was wrong, not the code - it recomputed with SHA-1 while the module reads
`checksum_algorithm`, which is SHA-256 here. When a check disagrees with the code, check
the check first.

## 4c. Against a real server, 2026-10-10

`bigbluebuttonbn_server_url` was pointed at the public test server the module itself ships
as its default — `test-moodle.blindsidenetworks.com`, which `config::server_credentials_invalid()`
flags as test credentials. It answered: **version 2**.

| Question | Answer |
|---|---|
| Does a real BigBlueButton **accept** a `create` call carrying our injected `duration`? | **Yes.** The meeting was created and returned a meetingID. The checksum held with our parameter inside it — which was the main unknown, and it is now settled against a real server rather than against our own re-implementation |
| Does it **honour** the value? | **Unknown, and this server does not.** Three distinct meetings asking for 5, 25 and 50 minutes were all reported back as `duration: 10`. The public test server overrides the parameter with a fixed ten minutes, almost certainly to stop strangers holding its rooms open |

**What this means for layer 1.** Half the risk is gone: the mechanism works and the server
takes the parameter. The other half is untouched — whether a BigBlueButton configured the
way ours will be actually ends a meeting at `duration` cannot be learned from a server that
ignores it. ⚠️ **This is the first thing to check on the real server**, before anybody
relies on AC-4.

It also raises a question nobody had asked: **a server can impose its own maximum**. If the
production server caps meetings below fifty minutes, our cap is not the one that applies
and §8.3's promise is the server's to keep, not ours. Worth reading its
`bigbluebutton.properties` before the first class rather than after.

## 5. What a server still has to answer

1. Does this BBB version honour `duration` the way the API documents — and does it end the
   meeting, or only stop admitting people?
2. What does its own warning say, and when?
3. What happens to a participant at the moment of the cut: a message, or a dropped
   connection?
4. Does `end_meeting` on an already-ended meeting return an error our sweeper must tolerate?

All four are an afternoon's work **once `PSH-S01-02` provisions a server**, and none of them
can be guessed.

## 6. What this changes in the backlog

- `PSH-S05-05` is no longer "set a module setting". It is **build `bbbext_pshub`**, a small
  subplugin of our own, and it is buildable before a server exists — only its verification
  needs one.
- `PSH-S05-06` and `PSH-S05-09` are confirmed as ours, from a server timestamp.
- `PSH-S05-07`'s sweeper must tolerate "meeting already ended" as success, not failure.
- Decision 3 can be closed on the mechanism; what remains is observation, not design.
