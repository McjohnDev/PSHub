<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_pshub\push;

/**
 * Which handsets a person may be reached on.
 *
 * WHY WE KEEP OUR OWN, RATHER THAN USING MOODLE'S
 * Moodle's `message_airnotifier` routes push through Moodle's own gateway,
 * which only talks to the official Moodle app. PS Language Hub ships its own
 * application (§6.1), so its devices are ours to hold and its messages ours to
 * send - and `local_pshub_push_token` has been in the schema since S1 waiting
 * for exactly this.
 *
 * WHAT THIS IS NOT
 * It is not a sender. Nothing here pushes anything; it records where a push
 * could go. The sender is a separate piece, and until it exists the
 * preferences screen will not offer "on my phone" as a channel - a tick box
 * that delivers nothing is worse than no tick box, because the person stops
 * looking for the message.
 *
 * A TOKEN BELONGS TO A DEVICE, NOT TO A PERSON
 * That is the whole reason the column is unique. A handset gets passed on, or
 * sold, or shared between a mother and a daughter who each sign in; the
 * firebase token stays the same and the person behind it changes. Registering
 * a token that already exists MOVES it, and the previous owner stops being
 * reachable there. Anything else sends one learner's class reminders to
 * another learner's lock screen.
 *
 * @package    local_pshub
 * @copyright  PS Language Hub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class device_service {

    /** @var string[] The platforms our own application is built for. */
    public const PLATFORMS = ['android', 'ios', 'web'];

    /** @var int The column is char(255); a longer token is a mistake, not a device. */
    public const TOKEN_MAX = 255;

    /**
     * Remember this handset for this person.
     *
     * Idempotent: the app registers on every launch, because a firebase token
     * is refreshed by the operating system whenever it feels like it. Calling
     * this twice with the same token is one device, not two.
     *
     * @param int $userid
     * @param string $token The push token the device was issued.
     * @param string $platform One of PLATFORMS.
     * @return int The row id.
     */
    public function register(int $userid, string $token, string $platform): int {
        global $DB;

        $token = trim($token);
        $platform = strtolower(trim($platform));

        if ($token === '' || \core_text::strlen($token) > self::TOKEN_MAX) {
            throw new \moodle_exception('device_badtoken', 'local_pshub');
        }

        if (!in_array($platform, self::PLATFORMS, true)) {
            throw new \moodle_exception('device_badplatform', 'local_pshub');
        }

        $record = (object) [
            'userid' => $userid,
            'token' => $token,
            'platform' => $platform,
            'timemodified' => time(),
        ];

        // Insert first and let the unique index answer, rather than asking
        // whether the row exists and then writing: two launches of the app at
        // once both pass the question and only one survives the write. The
        // same rule the reminders use.
        try {
            return (int) $DB->insert_record('local_pshub_push_token', $record);
        } catch (\dml_exception $e) {
            $existing = $DB->get_record('local_pshub_push_token', ['token' => $token]);
            if (!$existing) {
                // The insert failed for some other reason, and swallowing that
                // would hide a real fault behind a device that never gets a
                // reminder.
                throw $e;
            }

            $record->id = $existing->id;
            $DB->update_record('local_pshub_push_token', $record);

            return (int) $existing->id;
        }
    }

    /**
     * Forget a handset.
     *
     * Called when the person signs out of the application, and when the
     * operating system tells it the token is dead. Silent about a token it
     * does not know: there is nothing useful to say, and an error would make
     * sign-out fail for a device that was already forgotten.
     *
     * Only the owner's own row goes: a token somebody else holds is not this
     * person's to delete, even if they can name it.
     *
     * @param int $userid
     * @param string $token
     * @return bool Whether a row went.
     */
    public function deregister(int $userid, string $token): bool {
        global $DB;

        $token = trim($token);
        if ($token === '') {
            return false;
        }

        $existing = $DB->get_record('local_pshub_push_token',
            ['token' => $token, 'userid' => $userid]);
        if (!$existing) {
            return false;
        }

        $DB->delete_records('local_pshub_push_token', ['id' => $existing->id]);

        return true;
    }

    /**
     * Every handset this person can be reached on.
     *
     * @param int $userid
     * @return \stdClass[]
     */
    public function get_devices(int $userid): array {
        global $DB;

        return array_values($DB->get_records('local_pshub_push_token',
            ['userid' => $userid], 'timemodified DESC'));
    }

    /**
     * Forget every handset of a person.
     *
     * For account deletion and for "sign me out everywhere".
     *
     * @param int $userid
     * @return void
     */
    public function forget_all(int $userid): void {
        global $DB;

        $DB->delete_records('local_pshub_push_token', ['userid' => $userid]);
    }
}
