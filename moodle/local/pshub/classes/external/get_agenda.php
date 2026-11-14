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

namespace local_pshub\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_pshub\calendar\agenda_service;

/**
 * The caller's diary: when they are next expected somewhere, and whether they
 * can get in.
 *
 * The application's home screen. In a product where every course is taught
 * live, this is the first question anybody opens the app to ask - not "what is
 * in my course" but "am I due anywhere, and can I walk in" (§6.1, §10.4).
 *
 * IT RETURNS A VERDICT, NOT A TIME FOR THE CLIENT TO JUDGE
 * `canjoin` comes from session_service::can_join(), the same function the web
 * lobby and the door itself use. A client that worked it out from `starttime`
 * and the device clock would be wrong often: a wrong clock is ordinary on the
 * handsets this product targets, which is exactly why the web pages print the
 * server's time rather than trusting the phone's.
 *
 * `servertime` is in the response for the same reason. Show it; do not correct
 * it against the device.
 *
 * @package    local_pshub
 * @copyright  PS Language Hub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_agenda extends external_api {

    /**
     * Describes the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'includerecent' => new external_value(PARAM_BOOL,
                'Also return the classes that have just been', VALUE_DEFAULT, false),
        ]);
    }

    /**
     * The caller's own diary. There is no userid parameter, by design.
     *
     * @param bool $includerecent
     * @return array
     */
    public static function execute(bool $includerecent = false): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'includerecent' => $includerecent,
        ]);

        $context = \context_system::instance();
        self::validate_context($context);

        $service = new agenda_service();
        $now = time();

        return [
            'upcoming' => $service->get_upcoming_days($USER->id, $now),
            'recent' => $params['includerecent'] ? $service->get_recent_days($USER->id, $now) : [],
            'servertime' => $now,
            // The zone the times above were worked out in, so a handset abroad
            // can label them rather than silently re-interpreting them.
            'timezone' => \core_date::get_user_timezone(),
        ];
    }

    /**
     * One day of the diary.
     *
     * @return external_single_structure
     */
    private static function day_structure(): external_single_structure {
        return new external_single_structure([
            'dayiso' => new external_value(PARAM_RAW, 'YYYY-MM-DD in the caller\'s timezone'),
            'daylabel' => new external_value(PARAM_TEXT,
                'Written for a person: "Today", "Tomorrow", or the date'),
            'istoday' => new external_value(PARAM_BOOL, 'Whether this day is today'),
            'sessions' => new external_multiple_structure(self::session_structure()),
        ]);
    }

    /**
     * One class in the diary.
     *
     * @return external_single_structure
     */
    private static function session_structure(): external_single_structure {
        return new external_single_structure([
            'sessionid' => new external_value(PARAM_INT, 'Session id'),
            'courseid' => new external_value(PARAM_INT, 'Course id'),
            'coursename' => new external_value(PARAM_TEXT, 'Course name'),
            'title' => new external_value(PARAM_TEXT, 'What this class is about'),
            'starttime' => new external_value(PARAM_TEXT,
                'Start time, formatted in the caller\'s timezone'),
            'duration' => new external_value(PARAM_TEXT, 'How long it runs, written out'),
            'url' => new external_value(PARAM_URL, 'The web lobby for this class'),
            'courseurl' => new external_value(PARAM_URL, 'The web page for this course'),
            'islive' => new external_value(PARAM_BOOL, 'The class is running now'),
            'isended' => new external_value(PARAM_BOOL, 'The class is over'),
            'canjoin' => new external_value(PARAM_BOOL,
                'THE SERVER\'S verdict. Do not compute this from starttime'),
            'note' => new external_value(PARAM_TEXT,
                'Why not, when there is something useful to say', VALUE_OPTIONAL),
            'state' => new external_value(PARAM_ALPHA,
                'ok, tooearly, full, noaccess, ended, cancelled'),
        ]);
    }

    /**
     * Describes the return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'upcoming' => new external_multiple_structure(self::day_structure()),
            'recent' => new external_multiple_structure(self::day_structure()),
            'servertime' => new external_value(PARAM_INT,
                'Unix time on the SERVER. Show this, do not trust the device clock'),
            'timezone' => new external_value(PARAM_TEXT, 'The timezone those times are in'),
        ]);
    }
}
