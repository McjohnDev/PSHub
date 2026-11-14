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
 * The application tells us where it can be reached.
 *
 * Called on every launch, because the operating system refreshes a push token
 * whenever it chooses. Registering the same token twice is one device.
 *
 * THE PERSON IS THE CALLER, NOT A PARAMETER. There is no userid in the
 * signature: the web-service token says who is calling, and accepting an id
 * would let any app user register a handset against somebody else's account -
 * which is how one learner ends up receiving another's class reminders.
 *
 * @package    local_pshub
 * @copyright  PS Language Hub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class register_device extends external_api {

    /**
     * Describes the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'token' => new external_value(PARAM_RAW_TRIMMED,
                'The push token this device was issued', VALUE_REQUIRED),
            'platform' => new external_value(PARAM_ALPHA,
                'android, ios or web', VALUE_REQUIRED),
        ]);
    }

    /**
     * Remember this handset for whoever is calling.
     *
     * @param string $token
     * @param string $platform
     * @return array
     */
    public static function execute(string $token, string $platform): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'token' => $token,
            'platform' => $platform,
        ]);

        $context = \context_system::instance();
        self::validate_context($context);

        // No capability check: being signed in is the whole requirement. Every
        // account on this platform is entitled to be told when its own class
        // is about to start, and a capability here would mean inventing one
        // that is granted to everybody.
        $id = (new device_service())->register($USER->id, $params['token'], $params['platform']);

        return ['id' => $id, 'registered' => true];
    }

    /**
     * Describes the return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'The stored device id'),
            'registered' => new external_value(PARAM_BOOL, 'Always true; failure throws'),
        ]);
    }
}
