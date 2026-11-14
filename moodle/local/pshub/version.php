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
 * PS Language Hub - business layer.
 *
 * Access authorisation lives here, never in Moodle's per-course enrolment
 * configuration. One gatekeeper, decoupled from pedagogy.
 *
 * @package    local_pshub
 * @copyright  PS Language Hub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_pshub';
$plugin->version   = 2026101401;

// Must not exceed the version actually installed, or Moodle refuses the plugin
// as requiring a newer core. This is the 5.3dev build we are pinned to
// (2026091100.00); raise it to the v5.3.0 release stamp when we move to the
// stable tag in October. See docs/ENVIRONMENT.md §2.1.
$plugin->requires  = 2026091100;

$plugin->maturity  = MATURITY_ALPHA;
$plugin->release   = '0.9.2 (app API; mail health check)';
