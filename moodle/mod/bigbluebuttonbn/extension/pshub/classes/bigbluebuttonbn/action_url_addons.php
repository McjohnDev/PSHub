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

namespace bbbext_pshub\bigbluebuttonbn;

/**
 * Tell BigBlueButton how long a meeting may last.
 *
 * WHY THIS SUBPLUGIN EXISTS
 * `mod_bigbluebuttonbn::create_meeting_data()` builds the `create` payload and
 * never sets `duration` - the parameter that makes the BBB server end a
 * meeting by itself. Without it a class runs until somebody closes it or the
 * last person leaves, and AC-4's "the session still ends when every client has
 * disconnected" cannot hold.
 *
 * The module offers a supported way in: its `bbbext` subplugin point, called
 * from `proxy_base::action_url()` **before** the checksum is computed, so a
 * parameter added here is signed and accepted. That is why this is thirty
 * lines in a subplugin rather than a patch to a core file that the next
 * upgrade would overwrite. See docs/SPIKE-50-MIN-CAP.md.
 *
 * This is layer 1 of three. Layer 2 is the countdown the learner sees, layer 3
 * the sweeper task. This one is the guarantee; the others are courtesy and
 * belt.
 *
 * @package    bbbext_pshub
 * @copyright  PS Language Hub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class action_url_addons extends \mod_bigbluebuttonbn\local\extension\action_url_addons {

    /**
     * Add `duration` when a meeting is created.
     *
     * Only on `create`: BBB reads the parameter there, and adding it to a
     * `join` or an `end` would be noise in a signed URL.
     *
     * @param string $action
     * @param array $data
     * @param array $metadata
     * @param int|null $instanceid The bigbluebuttonbn instance id, not the cmid.
     * @return array The parameters to ADD, as the extension point expects.
     */
    public function execute(
        string $action = '',
        array $data = [],
        array $metadata = [],
        ?int $instanceid = null
    ): array {
        if ($action !== 'create') {
            return ['data' => [], 'metadata' => []];
        }

        $session = $this->session_for($instanceid);
        $minutes = $session
            ? (int) $session->duration_minutes
            : $this->site_cap_minutes();

        if ($minutes <= 0) {
            return ['data' => [], 'metadata' => []];
        }

        $add = ['duration' => $minutes];
        $meta = [];

        // Tie the meeting back to the session it belongs to. BBB returns
        // metadata on recordings and reports, so this is what lets attendance
        // and recordings be matched to a row later without guessing from
        // names or times.
        if ($session) {
            $meta['pshub-sessionid'] = (string) $session->id;
        }

        return ['data' => $add, 'metadata' => $meta];
    }

    /**
     * The hub session this BigBlueButton activity belongs to, if any.
     *
     * The extension point hands over the module INSTANCE id; the hub records
     * the course module id, so this translates between them.
     *
     * @param int|null $instanceid
     * @return \stdClass|null
     */
    private function session_for(?int $instanceid): ?\stdClass {
        global $DB;

        if (!$instanceid) {
            return null;
        }

        // The hub may not be installed beside this subplugin - a Moodle where
        // somebody copied only one of the two. Capping at the site default is
        // still the right behaviour there, so this returns null rather than
        // failing.
        if (!class_exists('\local_pshub\session\session_service')) {
            return null;
        }

        $cm = get_coursemodule_from_instance('bigbluebuttonbn', $instanceid, 0, false, IGNORE_MISSING);
        if (!$cm) {
            return null;
        }

        $session = $DB->get_record('local_pshub_session', ['bbb_cmid' => $cm->id]);

        return $session ?: null;
    }

    /**
     * The platform's cap, applied to any meeting this subplugin does not
     * recognise.
     *
     * A deliberate choice, and the honest one on this platform: §8.3 promises
     * that **no** live class runs past the cap, not merely the ones created
     * through our own scheduling page. A meeting somebody made directly in
     * Moodle is still a class on this site.
     *
     * The setting is an admin_setting_configduration, so it stores SECONDS.
     *
     * @return int
     */
    private function site_cap_minutes(): int {
        $seconds = (int) get_config('local_pshub', 'sessioncap');

        return $seconds > 0 ? (int) round($seconds / MINSECS) : 0;
    }
}
