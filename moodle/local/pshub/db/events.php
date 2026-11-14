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

/**
 * Event observers for local_pshub.
 *
 * @package    local_pshub
 * @copyright  PS Language Hub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        // Every account gets its platform row here rather than in the signup
        // form, because accounts also arrive by CLI, by upload and by web
        // service (see local_pshub\observers::user_created).
        'eventname' => '\core\event\user_created',
        'callback' => '\local_pshub\observers::user_created',
        // Not internal: the row is written in its own transaction, so a
        // failure here cannot roll back the account that was just created.
        'internal' => false,
    ],
];
