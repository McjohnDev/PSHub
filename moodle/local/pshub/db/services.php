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

/**
 * The API our own application talks to.
 *
 * PS Language Hub ships its own handset application rather than Moodle's
 * (§6.1), so it needs a service of its own. It is deliberately NOT part of
 * MOODLE_OFFICIAL_MOBILE_SERVICE: that service is the official Moodle app's,
 * and adding functions to it would hand them to an app we did not write.
 *
 * The service ships DISABLED. Turning on web services opens a door, and a door
 * is opened on purpose by whoever runs the site - not by installing a plugin.
 * See docs/ENVIRONMENT.md for how it is switched on here.
 *
 * @package    local_pshub
 * @copyright  PS Language Hub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [

    // Where this person can be reached. Called on every launch, because the
    // operating system refreshes a push token whenever it chooses.
    'local_pshub_register_device' => [
        'classname' => 'local_pshub\external\register_device',
        'description' => 'Register this handset for push notifications.',
        // 'write': it stores something. Marking a writing function 'read'
        // is how a function slips past a read-only service.
        'type' => 'write',
        'ajax' => false,
        'services' => ['pshub_app'],
        'loginrequired' => true,
    ],

    // And where it can no longer be reached. Sign-out depends on this: a
    // shared handset must stop showing somebody else's timetable.
    'local_pshub_deregister_device' => [
        'classname' => 'local_pshub\external\deregister_device',
        'description' => 'Forget this handset.',
        'type' => 'write',
        'ajax' => false,
        'services' => ['pshub_app'],
        'loginrequired' => true,
    ],

    // The application's home screen. Read-only, and it returns the server's
    // verdict on every class rather than a time for the client to judge.
    'local_pshub_get_agenda' => [
        'classname' => 'local_pshub\external\get_agenda',
        'description' => "The caller's diary: when they are next expected, and whether they can get in.",
        'type' => 'read',
        'ajax' => false,
        'services' => ['pshub_app'],
        'loginrequired' => true,
    ],

    // What the caller is taking.
    'local_pshub_get_my_courses' => [
        'classname' => 'local_pshub\external\get_my_courses',
        'description' => 'The courses the caller is enrolled in.',
        'type' => 'read',
        'ajax' => false,
        'services' => ['pshub_app'],
        'loginrequired' => true,
    ],
];

$services = [
    'PS Language Hub application' => [
        // Listed per function above, which is how Moodle prefers it.
        'functions' => [],
        'shortname' => 'pshub_app',
        // Off until somebody decides otherwise.
        'enabled' => 0,
        // Any account may use it: every learner has an app. Restricting it to
        // named users would mean an administrator authorising each learner by
        // hand before they could be reminded of their own class.
        'restrictedusers' => 0,
        'downloadfiles' => 0,
        'uploadfiles' => 0,
    ],
];
