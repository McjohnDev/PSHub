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
 * Scheduled tasks for local_pshub.
 *
 * @package    local_pshub
 * @copyright  PS Language Hub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$tasks = [
    [
        // Every minute. A class that has ended must stop calling itself live
        // on our own pages quickly - a learner opening the lobby should not be
        // told a finished class is still going. The task selects nothing when
        // there is nothing to close, so the cost of running it often is a
        // single indexed query (§8.3).
        'classname' => 'local_pshub\task\close_overrunning_sessions',
        'blocking' => 0,
        'minute' => '*',
        'hour' => '*',
        'day' => '*',
        'month' => '*',
        'dayofweek' => '*',
    ],
    [
        // Every minute, for the same reason: a reminder that is due at 17:00
        // and goes at 17:30 has missed what it was for. The query is indexed
        // and selects nothing almost every time it runs (§10.2).
        'classname' => 'local_pshub\\task\\send_session_reminders',
        'blocking' => 0,
        'minute' => '*',
        'hour' => '*',
        'day' => '*',
        'month' => '*',
        'dayofweek' => '*',
    ],
];
