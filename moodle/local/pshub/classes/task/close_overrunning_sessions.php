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

namespace local_pshub\task;

use local_pshub\audit\audit_service;
use local_pshub\local\constants;

/**
 * Close sessions that have run past their end. The belt, not the braces.
 *
 * Layer 3 of the fifty-minute cap (§8.3, AC-4). Layer 1 is the `duration`
 * that `bbbext_pshub` sends at meeting creation, which makes the
 * BigBlueButton server end the meeting on its own; that is the guarantee.
 * This task exists for what layer 1 cannot reach:
 *
 *  - a meeting created before the subplugin was installed;
 *  - a meeting created outside our scheduling page;
 *  - a server that ignored `duration`, or honoured it and left our row
 *    saying `live` forever.
 *
 * The last of those is the common case and the reason this runs even with no
 * BigBlueButton server at all: a session whose end has passed must stop
 * calling itself live on our own pages, or a learner opening the lobby
 * tomorrow is told the class is still going.
 *
 * IDEMPOTENT BY CONSTRUCTION. It only ever selects sessions that are not yet
 * ended, so a second run finds nothing. "Meeting already ended" coming back
 * from the server is success, not failure (see docs/SPIKE-50-MIN-CAP.md).
 *
 * @package    local_pshub
 * @copyright  PS Language Hub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class close_overrunning_sessions extends \core\task\scheduled_task {

    /** @var int Grace after the scheduled end before this intervenes. */
    private const GRACE_SECONDS = 120;

    /**
     * @return string
     */
    public function get_name(): string {
        return get_string('task_close_sessions', 'local_pshub');
    }

    /**
     * Close everything that is past its end.
     *
     * @return void
     */
    public function execute(): void {
        global $DB;

        $now = time();
        $audit = new audit_service();

        // A short grace, so a class that ran four seconds long is not reported
        // as an overrun by a cron that happened to fire at that moment.
        $sql = "SELECT * FROM {local_pshub_session}
                 WHERE scheduled_end < :cutoff
                       AND status IN (:scheduled, :live)";

        $overrunning = $DB->get_records_sql($sql, [
            'cutoff' => $now - self::GRACE_SECONDS,
            'scheduled' => constants::SESSION_SCHEDULED,
            'live' => constants::SESSION_LIVE,
        ]);

        if (!$overrunning) {
            return;
        }

        foreach ($overrunning as $session) {
            $before = [
                'status' => $session->status,
                'actual_end' => (int) $session->actual_end,
            ];

            // Tell BigBlueButton first, then record. If the server call fails
            // the row is still closed: our pages must not keep claiming a
            // class is live because a server was unreachable.
            $this->end_meeting($session);

            $session->status = constants::SESSION_ENDED;
            $session->actual_end = $session->actual_end ?: $session->scheduled_end;
            $session->timemodified = $now;
            $DB->update_record('local_pshub_session', $session);

            // actorid 0: the system did this, and the trail must not claim a
            // person ended a class they were not in.
            $audit->record(audit_service::ACTION_SESSION_ENDED, audit_service::TARGET_SESSION,
                (int) $session->id, $before,
                ['status' => $session->status, 'actual_end' => (int) $session->actual_end], 0);

            mtrace("local_pshub: session {$session->id} closed at its cap");
        }
    }

    /**
     * Ask BigBlueButton to end the meeting, if there is one and a server to ask.
     *
     * Every failure here is swallowed deliberately: an unreachable server, a
     * meeting that ended by itself, a room that was never created. None of
     * them is a reason to leave a finished class marked live, and none of them
     * should stop the task closing the rest.
     *
     * @param \stdClass $session
     * @return void
     */
    private function end_meeting(\stdClass $session): void {
        global $CFG;

        if (empty($session->bbb_cmid)) {
            return;
        }

        if (!get_config('mod_bigbluebuttonbn', 'server_url')) {
            return;
        }

        try {
            require_once($CFG->dirroot . '/mod/bigbluebuttonbn/lib.php');
            $instance = \mod_bigbluebuttonbn\instance::get_from_cmid((int) $session->bbb_cmid);
            if (!$instance) {
                return;
            }
            (new \mod_bigbluebuttonbn\meeting($instance))->end_meeting();
        } catch (\Throwable $e) {
            // "Already ended" is the expected answer when layer 1 did its job.
            mtrace("local_pshub: session {$session->id} - BBB said: " . $e->getMessage());
        }
    }
}
