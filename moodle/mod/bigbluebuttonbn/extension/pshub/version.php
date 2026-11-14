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
 * PS Language Hub's BigBlueButton extension.
 *
 * Its whole job is one parameter: `duration`, which mod_bigbluebuttonbn does
 * not send and which is what makes the BigBlueButton server end a meeting on
 * its own. See docs/SPIKE-50-MIN-CAP.md.
 *
 * @package    bbbext_pshub
 * @copyright  PS Language Hub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'bbbext_pshub';
$plugin->version   = 2026100400;

// The 5.3dev build this project is pinned to, same as local_pshub.
$plugin->requires  = 2026091100;

$plugin->dependencies = [
    'mod_bigbluebuttonbn' => ANY_VERSION,
];

$plugin->maturity  = MATURITY_ALPHA;
$plugin->release   = '0.1.0 (50-minute cap, layer 1)';
