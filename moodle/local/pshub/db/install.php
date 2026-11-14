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
 * Install-time setup for local_pshub.
 *
 * Creates the two platform roles and seeds the data an administrator would
 * otherwise have to type before the site does anything useful: the four course
 * types the cahier names, and a starting XP scale.
 *
 * Runs on FIRST INSTALL ONLY. A plugin already registered at the same version
 * gets none of this, which is why the development loop uninstalls rather than
 * upgrading. See docs/ENVIRONMENT.md §6.1.
 *
 * @package    local_pshub
 * @copyright  PS Language Hub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use local_pshub\local\constants;

require_once(__DIR__ . '/../lib.php');

/**
 * Run once, on plugin install.
 *
 * @return bool
 */
function xmldb_local_pshub_install() {
    local_pshub_install_roles();
    local_pshub_install_course_types();
    local_pshub_install_xp_rules();

    return true;
}

/**
 * Create the principal administrator and teacher roles.
 *
 * Note what this does NOT do: assign capabilities. It cannot, and trying was a
 * real bug. Core runs db/install.php at upgradelib.php line 767 and only
 * registers capabilities at line 773, so at this moment not one
 * local/pshub:* capability exists yet and assign_capability() fails.
 *
 * The archetypes declared in db/access.php do the work instead. Each role is
 * created FROM an archetype, and when core registers the capabilities moments
 * later it grants each one to every role carrying the matching archetype:
 *
 *   pshub_admin   <- manager         gets every administrative capability
 *   pshub_teacher <- editingteacher  gets the three teacher capabilities
 *
 * publishcourse declares only `manager`, so an editingteacher-derived role
 * never receives it. A teacher cannot approve a course, including their own
 * (AC-1), because the capability was never granted rather than because it was
 * granted and then prohibited.
 *
 * local/pshub:manageadmins declares no archetype at all, so no role receives
 * it and only a site administrator holds it (cahier §4.1).
 *
 * @return void
 */
function local_pshub_install_roles() {
    global $DB;

    if (!$DB->record_exists('role', ['shortname' => 'pshub_admin'])) {
        $roleid = create_role(
            get_string('role_pshub_admin', 'local_pshub'),
            'pshub_admin',
            get_string('role_pshub_admin_desc', 'local_pshub'),
            'manager'
        );
        set_role_contextlevels($roleid, [CONTEXT_SYSTEM]);
    }

    if (!$DB->record_exists('role', ['shortname' => 'pshub_teacher'])) {
        $roleid = create_role(
            get_string('role_pshub_teacher', 'local_pshub'),
            'pshub_teacher',
            get_string('role_pshub_teacher_desc', 'local_pshub'),
            'editingteacher'
        );
        set_role_contextlevels($roleid, [CONTEXT_SYSTEM, CONTEXT_COURSE]);
    }
}

/**
 * Seed the four course types named in the cahier.
 *
 * These are ordinary rows, not special cases. An administrator can edit or
 * delete any of them and add others, which is the whole point of AC-8.
 *
 * @return void
 */
function local_pshub_install_course_types() {
    global $DB;

    $now = time();
    $types = [
        ['code' => 'promo20', 'name_fr' => '-20 %', 'name_en' => '-20%',
         'kind' => 'discount', 'discount_percent' => 20,
         'grants_free_access' => 0, 'badge_style' => 'promo', 'sortorder' => 10],
        ['code' => 'hot', 'name_fr' => 'Hot', 'name_en' => 'Hot',
         'kind' => 'highlight', 'discount_percent' => 0,
         'grants_free_access' => 0, 'badge_style' => 'hot', 'sortorder' => 20],
        ['code' => 'free', 'name_fr' => 'Gratuit', 'name_en' => 'Free',
         'kind' => 'free', 'discount_percent' => 0,
         'grants_free_access' => 1, 'badge_style' => 'free', 'sortorder' => 30],
        ['code' => 'exam', 'name_fr' => 'Examen', 'name_en' => 'Exam',
         'kind' => 'exam', 'discount_percent' => 0,
         'grants_free_access' => 0, 'badge_style' => 'exam', 'sortorder' => 40],
    ];

    foreach ($types as $type) {
        if ($DB->record_exists('local_pshub_course_type', ['code' => $type['code']])) {
            continue;
        }
        $record = (object) $type;
        $record->is_active = 1;
        $record->timecreated = $now;
        $record->timemodified = $now;
        $DB->insert_record('local_pshub_course_type', $record);
    }
}

/**
 * Seed a starting XP scale.
 *
 * Attendance is worth the most because attendance is the product. These are a
 * starting point for an administrator to tune, not a balance decision.
 *
 * @return void
 */
function local_pshub_install_xp_rules() {
    global $DB;

    $rules = [
        [constants::XP_LIVE_ATTENDANCE, 50, 200],
        [constants::XP_QUIZ_PASSED, 20, 100],
        [constants::XP_ASSIGNMENT_SUBMITTED, 15, 60],
        [constants::XP_WORKSHOP_COMPLETED, 25, 75],
        [constants::XP_COURSE_COMPLETED, 150, 0],
    ];

    foreach ($rules as [$key, $points, $cap]) {
        if ($DB->record_exists('local_pshub_xp_rule', ['event_key' => $key])) {
            continue;
        }
        $DB->insert_record('local_pshub_xp_rule', (object) [
            'event_key' => $key,
            'points' => $points,
            'daily_cap' => $cap,
            'is_active' => 1,
        ]);
    }

    // The signup form asks for a Mobile Money number through a custom profile
    // field. Upgrades get it from db/upgrade.php; fresh installs get it here,
    // and the function is the same one, so the two cannot drift.
    local_pshub_ensure_phone_profile_field();
}
