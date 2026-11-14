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
 * Library functions for local_pshub.
 *
 * @package    local_pshub
 * @copyright  PS Language Hub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use local_pshub\profile\profile_service;

/**
 * Make sure the signup form asks for a phone number.
 *
 * WHY A CUSTOM PROFILE FIELD AND NOT A FORM OVERRIDE
 * Moodle's signup form carries the password policy, the e-mail confirmation
 * flow, reCAPTCHA and the site policy checkbox. Subclassing it to add one
 * input would put all of that behind our own copy, forever. A custom profile
 * field marked `signup` puts the field on the form through Moodle's own
 * supported door, and it then appears everywhere a profile does - admin
 * editing a user, bulk upload, web services - for nothing.
 *
 * The number matters because payment is by Mobile Money (§3.8): an account
 * with no phone is an account that cannot pay.
 *
 * NOT REQUIRED, deliberately. Making it mandatory would block signup for
 * anyone paying by card, and for a visitor who simply wants to look around
 * with a free course. Checkout asks for it when checkout needs it.
 *
 * Idempotent: safe to call from install and from every upgrade step.
 *
 * @return void
 */
function local_pshub_ensure_phone_profile_field(): void {
    global $DB, $CFG;

    require_once($CFG->dirroot . '/user/profile/lib.php');

    if ($DB->record_exists('user_info_field', ['shortname' => profile_service::PHONE_FIELD])) {
        return;
    }

    // The field name is stored once, as text, in whatever language the site
    // runs in - Moodle's custom profile fields are not language packs. The
    // site default is French (§12.4), so that is what an administrator sees
    // in the field list, and it is editable there afterwards.
    $lang = $CFG->lang ?? 'fr';
    $categoryname = get_string('profilecategory', 'local_pshub', null, $lang);

    $category = $DB->get_record('user_info_category', ['name' => $categoryname]);
    if (!$category) {
        $max = (int) $DB->get_field_sql('SELECT MAX(sortorder) FROM {user_info_category}');
        $category = (object) [
            'name' => $categoryname,
            'sortorder' => $max + 1,
        ];
        $category->id = $DB->insert_record('user_info_category', $category);
    }

    $DB->insert_record('user_info_field', (object) [
        'shortname' => profile_service::PHONE_FIELD,
        'name' => get_string('profilefield_phone', 'local_pshub', null, $lang),
        'datatype' => 'text',
        'description' => get_string('profilefield_phone_help', 'local_pshub', null, $lang),
        'descriptionformat' => FORMAT_HTML,
        'categoryid' => $category->id,
        'sortorder' => 1,
        'required' => 0,
        'locked' => 0,
        // Visible to the account holder and to staff who can see profiles.
        'visible' => PROFILE_VISIBLE_ALL,
        'forceunique' => 0,
        // The one that matters: this is what puts it on the signup form.
        'signup' => 1,
        'defaultdata' => '',
        'defaultdataformat' => FORMAT_MOODLE,
        // Display size, then maximum length. Twenty characters holds E.164
        // with room for the way people type it: "+237 6 77 12 34 56".
        'param1' => 20,
        'param2' => 20,
        'param3' => 0,
    ]);
}

/**
 * A person's initials, for the avatar tile in the learner shell.
 *
 * Two letters in brand colour rather than Moodle's default avatar, which is a
 * grey silhouette and another image request on a 2G connection (§13.2).
 *
 * Falls back to one letter, then to nothing: a name with a single word is
 * common here, and an empty tile is better than a stray character.
 *
 * @param stdClass $user
 * @return string
 */
function local_pshub_user_initials(stdClass $user): string {
    $first = trim((string) ($user->firstname ?? ''));
    $last = trim((string) ($user->lastname ?? ''));

    $initials = core_text::strtoupper(
        core_text::substr($first, 0, 1) . core_text::substr($last, 0, 1)
    );

    return $initials;
}

/**
 * The site health checks this plugin contributes.
 *
 * Moodle collects these through `get_plugins_with_function('status_checks')`,
 * so they appear on the site status page and in `admin/cli/checks.php`
 * alongside core's own.
 *
 * @return \core\check\check[]
 */
function local_pshub_status_checks(): array {
    return [
        new \local_pshub\check\mailer(),
    ];
}
