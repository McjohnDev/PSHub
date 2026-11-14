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
 * Site configuration for local_pshub.
 *
 * @package    local_pshub
 * @copyright  PS Language Hub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use local_pshub\local\constants;

// The course-type manager. Registered outside the $hassiteconfig block so it
// is reachable by the pshub_admin role, which holds
// local/pshub:managecoursetypes but is not a site administrator (cahier §4.1).
// The page itself re-checks that capability; this only puts it in the tree.
$ADMIN->add('localplugins', new admin_externalpage(
    'local_pshub_coursetypes',
    get_string('coursetypes', 'local_pshub'),
    new moodle_url('/local/pshub/admin/coursetypes.php'),
    'local/pshub:managecoursetypes'
));

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_pshub', get_string('pluginname', 'local_pshub'));

    $settings->add(new admin_setting_heading(
        'local_pshub/sessionsheading',
        get_string('settings_sessions', 'local_pshub'),
        ''
    ));

    $settings->add(new admin_setting_configduration(
        'local_pshub/sessioncap',
        get_string('settings_sessioncap', 'local_pshub'),
        get_string('settings_sessioncap_desc', 'local_pshub'),
        constants::DEFAULT_SESSION_MINUTES * 60,
        60
    ));

    $settings->add(new admin_setting_configtext(
        'local_pshub/presencethreshold',
        get_string('settings_presencethreshold', 'local_pshub'),
        get_string('settings_presencethreshold_desc', 'local_pshub'),
        (string) (int) (constants::PRESENCE_THRESHOLD * 100),
        PARAM_INT
    ));

    $settings->add(new admin_setting_heading(
        'local_pshub/catalogueheading',
        get_string('settings_catalogue', 'local_pshub'),
        ''
    ));

    // Which languages may be chosen when a course is created. A list rather
    // than free text, so the catalogue's filters stay clean; a setting rather
    // than code, so adding Portuguese is a form submission (AC-8).
    $settings->add(new admin_setting_configtext(
        'local_pshub/languages',
        get_string('settings_languages', 'local_pshub'),
        get_string('settings_languages_desc', 'local_pshub'),
        'en,fr,es,de',
        PARAM_TEXT
    ));

    // Where hub courses are created. Left at zero the first visible category
    // is used, which is right on a site that has only one.
    $settings->add(new admin_settings_coursecat_select(
        'local_pshub/categoryid',
        get_string('settings_category', 'local_pshub'),
        get_string('settings_category_desc', 'local_pshub'),
        0
    ));

    $settings->add(new admin_setting_heading(
        'local_pshub/paymentsheading',
        get_string('settings_payments', 'local_pshub'),
        ''
    ));

    // What the fake provider answers. Inert unless $CFG->pshub_fake_payments
    // is set in config.php, which is the guard that keeps it out of
    // production - see docs/PAYMENTS.md.
    $settings->add(new admin_setting_configselect(
        'local_pshub/fakescript',
        get_string('settings_fakescript', 'local_pshub'),
        get_string('settings_fakescript_desc', 'local_pshub'),
        \local_pshub\payment\fake_provider::SCRIPT_PAY,
        [
            \local_pshub\payment\fake_provider::SCRIPT_PAY =>
                get_string('fakescript_pay', 'local_pshub'),
            \local_pshub\payment\fake_provider::SCRIPT_DECLINE =>
                get_string('fakescript_decline', 'local_pshub'),
            \local_pshub\payment\fake_provider::SCRIPT_SILENT =>
                get_string('fakescript_silent', 'local_pshub'),
            \local_pshub\payment\fake_provider::SCRIPT_WRONG_AMOUNT =>
                get_string('fakescript_wrongamount', 'local_pshub'),
            \local_pshub\payment\fake_provider::SCRIPT_WRONG_CURRENCY =>
                get_string('fakescript_wrongcurrency', 'local_pshub'),
        ]
    ));

    $settings->add(new admin_setting_heading(
        'local_pshub/monetisationheading',
        get_string('settings_monetisation', 'local_pshub'),
        ''
    ));

    // The P1 to P2 switch. Off for all of P1 (AC-18).
    $settings->add(new admin_setting_configcheckbox(
        'local_pshub/packsenabled',
        get_string('settings_packsenabled', 'local_pshub'),
        get_string('settings_packsenabled_desc', 'local_pshub'),
        0
    ));

    // Per-method auto-validation. Every method ships off, so the first weeks
    // of trading are reviewed by a person (spec §3.8).
    foreach (constants::METHODS as $method) {
        if (in_array($method, constants::OFFLINE_METHODS, true)) {
            continue;
        }
        $settings->add(new admin_setting_configcheckbox(
            'local_pshub/autovalidate_' . $method,
            get_string('settings_autovalidate', 'local_pshub',
                get_string('method_' . $method, 'local_pshub')),
            get_string('settings_autovalidate_desc', 'local_pshub'),
            0
        ));
    }

    $ADMIN->add('localplugins', $settings);
}
