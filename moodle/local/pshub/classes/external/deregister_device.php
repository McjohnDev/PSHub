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
use core_external\external_single_structure;
use core_external\external_value;
use local_pshub\push\device_service;

/**
 * The application says it can no longer be reached here.
 *
 * Called on sign-out, and when the operating system reports the token dead.
 * Signing out of a shared handset has to stop the reminders reaching it, or
 * the next person to pick up the phone reads somebody else's timetable on the
 * lock screen.
 *
 * It only ever removes the caller's own row. A token is a string somebody
 * could guess at or copy; being able to name one is not the same as owning it.
 *
 * @package    local_pshub
 * @copyright  PS Language Hub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class deregister_device extends external_api {

    /**
     * Describes the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'token' => new external_value(PARAM_RAW_TRIMMED,
                'The push token to forget', VALUE_REQUIRED),
        ]);
    }

    /**
     * Forget this handset.
     *
     * @param string $token
     * @return array
     */
    public static function execute(string $token): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), ['token' => $token]);

        $context = \context_system::instance();
        self::validate_context($context);

        // A token we do not hold is not an error: sign-out must succeed even
        // when the device was already forgotten, or the app is left unable to
        // finish signing somebody out.
        $removed = (new device_service())->deregister($USER->id, $params['token']);

        return ['removed' => $removed];
    }

    /**
     * Describes the return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'removed' => new external_value(PARAM_BOOL,
                'False when we were not holding that token for this account'),
        ]);
    }
}
