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
 * Upgrade steps for local_pshub.
 *
 * Every step is guarded by the version it lands in and must be safe to run on
 * a site that already has the change: an upgrade runs on installations we
 * cannot inspect first.
 *
 * @package    local_pshub
 * @copyright  PS Language Hub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * @param int $oldversion The version the site is upgrading FROM.
 * @return bool
 */
function xmldb_local_pshub_upgrade($oldversion) {
    global $CFG, $DB;

    require_once($CFG->dirroot . '/local/pshub/lib.php');

    if ($oldversion < 2026092600) {

        // Capture the Mobile Money number at signup (§3.1, §3.8). The function
        // is idempotent, so a site that already has the field keeps it and its
        // data untouched.
        local_pshub_ensure_phone_profile_field();

        // Every account that existed before this step still needs its platform
        // row - the observer only fires for accounts created after it. Guests
        // and the site admin included: a row is metadata, not a grant, and
        // leaving holes here means later code has to wonder whether a missing
        // row means "learner without a phone" or "user we never processed".
        $sql = "SELECT u.id
                  FROM {user} u
             LEFT JOIN {local_pshub_user_profile} p ON p.userid = u.id
                 WHERE u.deleted = 0 AND p.id IS NULL";

        $service = new \local_pshub\profile\profile_service();
        $missing = $DB->get_fieldset_sql($sql);
        foreach ($missing as $userid) {
            $service->ensure_for_user((int) $userid);
        }

        upgrade_plugin_savepoint(true, 2026092600, 'local', 'pshub');
    }

    if ($oldversion < 2026092601) {

        // The profile category is a heading on the signup form, above the
        // phone field. It was first created as "PS Language Hub", which on the
        // PS Language Hub signup page says nothing; "Contact" says what the
        // field under it is for. Renamed rather than hidden, because the same
        // heading appears on the user's profile page.
        //
        // Only the name this plugin gave it is touched: an administrator who
        // has since renamed it keeps their name.
        $oldname = get_string('profilecategory_legacy', 'local_pshub', null, $CFG->lang ?? 'fr');
        $newname = get_string('profilecategory', 'local_pshub', null, $CFG->lang ?? 'fr');

        if ($oldname !== $newname) {
            $DB->set_field('user_info_category', 'name', $newname, ['name' => $oldname]);
        }

        upgrade_plugin_savepoint(true, 2026092601, 'local', 'pshub');
    }

    return true;
}
