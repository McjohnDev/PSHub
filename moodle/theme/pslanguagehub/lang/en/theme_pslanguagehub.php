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
 * English strings for theme_pslanguagehub.
 *
 * @package    theme_pslanguagehub
 * @copyright  PS Language Hub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'PS Language Hub';
$string['choosereadme'] = 'The PS Language Hub theme. A Boost child carrying the brand: navy for structure, blue for interaction, and the signature gradient reserved for live sessions.';

// The one-line claim under the wordmark. It says the single thing that makes
// this platform different from every other language site a visitor has tried:
// there is no video library, a person is waiting.
$string['brand_claim'] = 'Every class is live. 50 minutes, one teacher.';

// Tagline.
$string['tagline_learn'] = 'LEARN.';
$string['tagline_speak'] = 'SPEAK.';
$string['tagline_succeed'] = 'SUCCEED.';

// The public shell: navigation bar.
$string['skipmain'] = 'Skip to content';
$string['nav_main'] = 'Main';
$string['nav_catalogue'] = 'Courses';
$string['nav_howitworks'] = 'How it works';
$string['nav_bandwidth'] = 'Low bandwidth';
$string['nav_app'] = 'App';
$string['nav_signin'] = 'Log in';
$string['nav_signup'] = 'Create an account';
$string['nav_myspace'] = 'My courses';

// The public shell: footer.
$string['foot_blurb'] = 'Language courses taught 100% live, built for francophone Africa.';
$string['foot_platform'] = 'Platform';
$string['foot_legal'] = 'Legal';
$string['foot_app'] = 'App';
$string['foot_terms'] = 'Terms of use — coming soon';
$string['foot_privacy'] = 'Data privacy — coming soon';
$string['foot_refunds'] = 'Refund policy — coming soon';
$string['app_google_soon'] = 'Google Play — coming soon';
$string['app_apple_soon'] = 'App Store — coming soon';
// Single quotes: {$a} must reach get_string() intact, and PHP would eat it in
// a double-quoted string.
$string['foot_copyright'] = '© {$a} PS Language Hub — Douala, Cameroon';
$string['foot_staff'] = 'Teacher and administration access';

// The live-session illustration on the login page. Decorative and hidden from
// screen readers, but still translated: it is on screen, in the reader's
// language, and a French visitor should not meet an English label.
//
// These live in the THEME rather than being borrowed from local_pshub, so the
// login page keeps rendering if the plugin is ever absent — a missing string
// on the login page would be a fatal error on the one page nobody can route
// around.
$string['demo_course'] = 'English B1 — Conversation';
$string['demo_live'] = 'LIVE';
$string['demo_teacher'] = 'Your teacher';
$string['demo_you'] = 'You';
$string['demo_board'] = 'Shared whiteboard';

// Login panel.
$string['login_headline'] = 'Learn languages, live.';
$string['login_lede'] = '100% live courses with real teachers: video, a collaborative whiteboard and personal follow-up.';
$string['login_point_live'] = 'Live sessions of 50 minutes maximum';
$string['login_point_teacher'] = 'A real teacher who answers you';
$string['login_point_bandwidth'] = 'Built for weak 2G/3G connections';
$string['login_point_xp'] = 'XP, skills and certificates';
$string['login_back_home'] = 'Back to the site';

// The signup page runs through the same layout; only the panel copy changes.
$string['signup_headline'] = 'Create your learner account';
$string['signup_lede'] = 'Sign up in seconds, choose your language, and join your first live session.';
$string['signup_payment_note'] = 'Simple payment by MTN Mobile Money or Orange Money, in FCFA.';

// The learner shell: sidebar and top bar.
$string['nav_progress'] = 'My progress';
$string['nav_explore'] = 'Explore courses';
$string['nav_agenda'] = 'My diary';
$string['nav_notifications'] = 'My notifications';
$string['nav_section_main'] = 'MAIN MENU';
$string['nav_logout'] = 'Log out';
$string['role_learner'] = 'Learner';
$string['search_placeholder_app'] = 'Search for a course, a language…';

// -- The staff spaces ---------------------------------------------------------
$string['nav_section_teacher'] = 'TEACHER SPACE';
$string['nav_section_admin'] = 'ADMINISTRATION';
$string['nav_overview'] = 'Overview';
$string['nav_types'] = 'Types & badges';
$string['role_teacher'] = 'Teacher';
$string['role_admin'] = 'Administration';
$string['action_open_moodle'] = 'Open Moodle';
$string['moodlenote_title'] = 'Moodle interface';
$string['moodlenote_teacher'] = 'Creating courses and the detail of activities happen in Moodle.';
$string['moodlenote_admin'] = 'Detailed configuration - roles, cohorts, competency frameworks - happens in Moodle.';
