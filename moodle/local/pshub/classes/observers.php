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

namespace local_pshub;

use core\event\user_created;
use local_pshub\profile\profile_service;

/**
 * Event observers for local_pshub.
 *
 * @package    local_pshub
 * @copyright  PS Language Hub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observers {

    /**
     * Give every new account its platform row, and keep the phone it signed up with.
     *
     * WHY AN OBSERVER AND NOT THE SIGNUP FORM
     * Accounts arrive by more routes than the signup page: an administrator
     * creating one by hand, a CSV upload, a web service, the CLI. Hanging this
     * off the signup form would mean every other route produced a learner with
     * no platform row, and the bug would surface much later as a payment that
     * cannot find a phone number.
     *
     * The phone is read from the custom profile field the plugin installs, and
     * copied into our own column in E.164. Two places hold it afterwards: the
     * profile field, because Moodle owns the form, and this column, because
     * §3.8 needs a canonical number to hand a payment aggregator.
     *
     * @param user_created $event
     */
    public static function user_created(user_created $event): void {
        global $CFG;

        $service = new profile_service();
        $service->ensure_for_user((int) $event->objectid);

        require_once($CFG->dirroot . '/user/profile/lib.php');

        // profile_user_record() returns only the custom fields, and only those
        // this user has a value for.
        $custom = profile_user_record((int) $event->objectid, false);
        $field = profile_service::PHONE_FIELD;

        if (!empty($custom->$field)) {
            $service->set_phone((int) $event->objectid, (string) $custom->$field);
        }
    }
}
