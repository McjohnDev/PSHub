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
 * Message providers for local_pshub.
 *
 * Declared rather than sent directly so that each recipient keeps control of
 * where a message reaches them - in-app, e-mail, mobile push - through
 * Moodle's own preferences (§10.1). This plugin decides what to say and to
 * whom, never through which channel.
 *
 * @package    local_pshub
 * @copyright  PS Language Hub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$messageproviders = [

    // To whoever reviews courses: one is waiting for a decision.
    'coursesubmitted' => [
        'capability' => 'local/pshub:publishcourse',
        // MESSAGE_PERMITTED means the recipient may turn the channel off;
        // MESSAGE_DEFAULT_ENABLED means it starts on. The LOGGEDIN/LOGGEDOFF
        // pair that older documentation shows no longer exists in Moodle 5 -
        // using it throws "Undefined constant" during the upgrade.
        'defaults' => [
            'popup' => MESSAGE_PERMITTED + MESSAGE_DEFAULT_ENABLED,
            'email' => MESSAGE_PERMITTED + MESSAGE_DEFAULT_ENABLED,
        ],
    ],

    // Before a live class, to the teacher and to every learner enrolled.
    // Popup and e-mail both on by default: a reminder nobody receives is the
    // one thing this provider cannot afford to be (AC-6).
    'sessionreminder' => [
        'defaults' => [
            'popup' => MESSAGE_PERMITTED + MESSAGE_DEFAULT_ENABLED,
            'email' => MESSAGE_PERMITTED + MESSAGE_DEFAULT_ENABLED,
        ],
    ],

    // To the teacher who submitted it: what was decided, and why when the
    // answer is no.
    'coursereviewed' => [
        // Only people who can submit a course can receive a decision on one.
        // Without this the preferences screen asks every learner about it.
        'capability' => 'local/pshub:submitcourse',
        'defaults' => [
            'popup' => MESSAGE_PERMITTED + MESSAGE_DEFAULT_ENABLED,
            'email' => MESSAGE_PERMITTED + MESSAGE_DEFAULT_ENABLED,
        ],
    ],
];
