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
 * Hook callbacks for local_pshub.
 *
 * @package    local_pshub
 * @copyright  PS Language Hub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$callbacks = [
    [
        // The site root hands over to the public home page for visitors who
        // are not signed in. A hook rather than a patched core file: this
        // survives every Moodle upgrade, and removing it is deleting one
        // array entry.
        'hook' => core\hook\after_config::class,
        'callback' => local_pshub\hook_callbacks::class . '::redirect_front_page',
        'priority' => 0,
    ],
    [
        // A learner signing in lands in their own space, not on Moodle's
        // dashboard. Staff keep the dashboard: they work in Moodle by design.
        'hook' => core\hook\after_config::class,
        'callback' => local_pshub\hook_callbacks::class . '::redirect_dashboard',
        'priority' => 0,
    ],
    [
        // Moodle's self-enrolment screen never reaches a learner on a hub
        // course: the course's own page decides what happens next, through the
        // gating service (PSH-S02-07, AC-17).
        'hook' => core\hook\after_config::class,
        'callback' => local_pshub\hook_callbacks::class . '::redirect_enrolment_ui',
        'priority' => 0,
    ],
    [
        // A learner opening a hub course gets the course page built for taking
        // a course, not the one built for assembling it (AC-17).
        'hook' => core\hook\after_config::class,
        'callback' => local_pshub\hook_callbacks::class . '::redirect_course_view',
        'priority' => 0,
    ],
    [
        // Staff reach the product's consoles from Moodle's own top navigation:
        // they keep the Moodle dashboard by design, so this is the only door.
        'hook' => core\hook\navigation\primary_extend::class,
        'callback' => local_pshub\hook_callbacks::class . '::extend_primary_navigation',
        'priority' => 0,
    ],
    [
        // The middle of the signup journey - "we have sent you an e-mail" and
        // the page the link lands on - stays in the product's shell instead of
        // falling back to Moodle's bare layout (AC-17).
        'hook' => core\hook\output\before_http_headers::class,
        'callback' => local_pshub\hook_callbacks::class . '::brand_login_flow',
        'priority' => 0,
    ],
];
