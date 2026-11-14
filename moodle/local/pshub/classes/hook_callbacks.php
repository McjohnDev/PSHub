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

namespace local_pshub;

use core\hook\after_config;
use core\hook\output\before_http_headers;
use core\hook\navigation\primary_extend;
use moodle_url;

/**
 * Hook callbacks for local_pshub.
 *
 * @package    local_pshub
 * @copyright  PS Language Hub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_callbacks {

    /**
     * Send visitors arriving at the site root to the public home page.
     *
     * The product's front door is /local/pshub/index.php. Moodle's own front
     * page is a list of courses wrapped in site chrome, which is exactly what
     * AC-17 says a learner should never meet. Rather than rebuild the front
     * page inside core, the root hands over.
     *
     * WHY after_config AND NOT an output hook
     * This site runs with $CFG->forcelogin on, so core's index.php calls
     * require_login() and bounces an anonymous visitor to the login form long
     * before any page or output hook fires. A visitor would meet a password
     * box before ever seeing what the site sells. after_config is dispatched
     * at the end of setup.php - after the session is started, before any page
     * code runs - which is the only point early enough to matter.
     *
     * WHO IS NOT REDIRECTED
     * Anyone signed in. An administrator needs Moodle's front page to reach
     * site administration, and a teacher may have it bookmarked; both have
     * already passed the door this page exists to open. A signed-in learner
     * keeps it too, for now: their landing belongs to the dashboard in S4, and
     * deciding it here as well would put the rule in two places.
     *
     * @param after_config $hook
     */
    public static function redirect_front_page(after_config $hook): void {
        global $CFG, $SCRIPT;

        // Command line, AJAX and web services have no business being
        // redirected, and an installer or upgrader must never be.
        if ((defined('CLI_SCRIPT') && CLI_SCRIPT)
                || (defined('AJAX_SCRIPT') && AJAX_SCRIPT)
                || (defined('WS_SERVER') && WS_SERVER)
                || !empty($CFG->upgraderunning)
                || during_initial_install()) {
            return;
        }

        // Only the site root itself. $SCRIPT is the path below wwwroot, so
        // this is '/index.php' and nothing else - our own home page is
        // /local/pshub/index.php and cannot match, which is what keeps this
        // from looping.
        if (($SCRIPT ?? '') !== '/index.php') {
            return;
        }

        if (isloggedin() && !isguestuser()) {
            return;
        }

        redirect(new moodle_url('/local/pshub/index.php'));
    }

    /**
     * Send learners from Moodle's dashboard to their own space.
     *
     * A learner who signed in landed on /my/: course overview blocks, a
     * timeline, an edit-mode toggle - the LMS, not the product, and exactly
     * what AC-17 says they must never meet.
     *
     * WHO KEEPS /my/
     * Anyone with a staff capability. An administrator and a teacher both work
     * in Moodle by design (§3.2, §5.3): the teacher authors courses there and
     * the administrator configures the site. Deciding this by CAPABILITY and
     * not by the platform_role column is deliberate - that column is display
     * only, and authorisation here would be the one place it started meaning
     * something.
     *
     * Same hook and the same guards as the front page: after_config runs after
     * the session starts and before any page code, which is early enough to
     * redirect before Moodle builds a dashboard nobody will see.
     *
     * @param after_config $hook
     */
    public static function redirect_dashboard(after_config $hook): void {
        global $CFG, $SCRIPT;

        if ((defined('CLI_SCRIPT') && CLI_SCRIPT)
                || (defined('AJAX_SCRIPT') && AJAX_SCRIPT)
                || (defined('WS_SERVER') && WS_SERVER)
                || !empty($CFG->upgraderunning)
                || during_initial_install()) {
            return;
        }

        if (($SCRIPT ?? '') !== '/my/index.php') {
            return;
        }

        if (!isloggedin() || isguestuser()) {
            // Not our business: Moodle will ask them to sign in, and the login
            // page is already the product's.
            return;
        }

        if (self::is_staff()) {
            return;
        }

        redirect(new moodle_url('/local/pshub/learn/index.php'));
    }

    /**
     * Keep Moodle's enrolment screen away from learners (PSH-S02-07).
     *
     * A learner who opens a hub course they are not in is sent by
     * require_login() to /enrol/index.php - Moodle's self-enrolment page,
     * complete with the course shortname as its title and a "Enrol me" button
     * that knows nothing about prices, access rights or payment. It is the
     * clearest case of the Moodle chrome AC-17 forbids, and it bypasses the
     * gating service entirely (§3.7).
     *
     * They go to the course's own page instead, where the call to action comes
     * from the gating verdict: join a free course, pay for a paid one, or read
     * why it is not available.
     *
     * Only HUB courses. A course an administrator set up directly in Moodle is
     * not part of the product and keeps Moodle's own behaviour.
     *
     * @param after_config $hook
     */
    public static function redirect_enrolment_ui(after_config $hook): void {
        global $CFG, $SCRIPT, $DB;

        if ((defined('CLI_SCRIPT') && CLI_SCRIPT)
                || (defined('AJAX_SCRIPT') && AJAX_SCRIPT)
                || (defined('WS_SERVER') && WS_SERVER)
                || !empty($CFG->upgraderunning)
                || during_initial_install()) {
            return;
        }

        if (($SCRIPT ?? '') !== '/enrol/index.php') {
            return;
        }

        // Staff keep it: an administrator reviewing how a course is enrolled
        // has a reason to see the real page.
        if (self::is_staff()) {
            return;
        }

        $courseid = optional_param('id', 0, PARAM_INT);
        if (!$courseid) {
            return;
        }

        if (!$DB->record_exists('local_pshub_course', ['courseid' => $courseid])) {
            return;
        }

        redirect(new moodle_url('/local/pshub/course.php', ['id' => $courseid]));
    }

    /**
     * Keep the whole signup journey inside the product's own shell.
     *
     * Signing up is three pages, not one: the form, "we have sent you an
     * e-mail", and the page the confirmation link lands on. Moodle renders the
     * first through the `login` layout - which is ours - and the other two
     * through `base`, which is Moodle's: a bare page with a "Powered by
     * Moodle" footer, shown to someone who has just handed over their name and
     * their phone number.
     *
     * Confirmation is mandatory (decision 4), so those two pages are not an
     * edge case: every single learner sees both, between deciding to join and
     * being able to. They carry the brand like the rest of the journey.
     *
     * The hook fires at the very start of core_renderer::header(), before the
     * layout file is chosen, which is the last moment set_pagelayout() still
     * decides anything.
     *
     * @param before_http_headers $hook
     */
    public static function brand_login_flow(before_http_headers $hook): void {
        global $PAGE;

        // login-signup covers the "check your e-mail" notice, which is printed
        // by signup.php after the account is created; login-confirm is where
        // the link in that e-mail lands.
        $pagetypes = [
            'login-signup',
            'login-confirm',
            'login-forgot_password',
            'login-change_password',
        ];

        if (!in_array($PAGE->pagetype, $pagetypes, true)) {
            return;
        }

        if ($PAGE->pagelayout !== 'login') {
            $PAGE->set_pagelayout('login');
        }
    }

    /**
     * Send a learner opening a hub course to their own course page.
     *
     * Moodle's /course/view.php is built for whoever is assembling a course:
     * edit mode, a section index, two drawers and an activity list. A learner
     * on this platform opens a course for the live classes, so they get the
     * page that leads with those (AC-17).
     *
     * Only PUBLISHED hub courses. A course an administrator set up directly in
     * Moodle keeps Moodle's own page, and so does a hub course still in draft:
     * the learner page describes a published catalogue entry, and bouncing
     * someone to an error would be worse than the page they asked for.
     *
     * Staff are not redirected. Teaching a course means editing it, and that
     * happens on Moodle's page.
     *
     * @param after_config $hook
     */
    public static function redirect_course_view(after_config $hook): void {
        global $CFG, $SCRIPT, $DB;

        if ((defined('CLI_SCRIPT') && CLI_SCRIPT)
                || (defined('AJAX_SCRIPT') && AJAX_SCRIPT)
                || (defined('WS_SERVER') && WS_SERVER)
                || !empty($CFG->upgraderunning)
                || during_initial_install()) {
            return;
        }

        if (($SCRIPT ?? '') !== '/course/view.php') {
            return;
        }

        if (self::is_staff()) {
            return;
        }

        // Only the plain "open this course" address. /course/view.php also
        // takes a section to jump to and a shortname instead of an id; those
        // are Moodle's own navigation, and redirecting them would drop what
        // the reader asked for.
        $courseid = optional_param('id', 0, PARAM_INT);
        if (!$courseid || optional_param('section', 0, PARAM_INT)
                || optional_param('sectionid', 0, PARAM_INT)) {
            return;
        }

        $published = $DB->record_exists('local_pshub_course', [
            'courseid' => $courseid,
            'review_status' => \local_pshub\local\constants::REVIEW_PUBLISHED,
        ]);
        if (!$published) {
            return;
        }

        redirect(new moodle_url('/local/pshub/learn/course.php', ['id' => $courseid]));
    }

    /**
     * Put the staff consoles in Moodle's own top navigation.
     *
     * Staff still land on Moodle's dashboard when they sign in - a deliberate
     * decision, because that is where an administrator works - so without this
     * there is no way to reach the product's own consoles except by typing the
     * address.
     *
     * core\hook\navigation\primary_extend rather than the older
     * local_*_extend_navigation callback: on Moodle 5 that one adds a node to
     * a tree the current themes do not render, which is a link that exists in
     * the database and nowhere on screen. Checked, not assumed.
     *
     * By CAPABILITY, never by local_pshub_user_profile.platform_role: that
     * column is display only (§5.3).
     *
     * @param primary_extend $hook
     */
    public static function extend_primary_navigation(primary_extend $hook): void {
        $context = \context_system::instance();
        $view = $hook->get_primaryview();

        if (has_capability('local/pshub:viewadminportal', $context)) {
            $view->add(
                get_string('nav_admin_console', 'local_pshub'),
                new \moodle_url('/local/pshub/admin/index.php'),
                \navigation_node::TYPE_CUSTOM,
                null,
                'pshubadmin'
            );
        }

        if (has_capability('local/pshub:viewteacherportal', $context)) {
            $view->add(
                get_string('nav_teacher_console', 'local_pshub'),
                new \moodle_url('/local/pshub/teacher/index.php'),
                \navigation_node::TYPE_CUSTOM,
                null,
                'pshubteacher'
            );
        }
    }

    /**
     * Whether the current user works on the platform rather than learns on it.
     *
     * By CAPABILITY, never by local_pshub_user_profile.platform_role: that
     * column is display only (§5.3), and a redirect that consulted it would be
     * the first place it started deciding who may see what.
     *
     * @return bool
     */
    private static function is_staff(): bool {
        $system = \context_system::instance();

        return has_capability('moodle/site:config', $system)
            || has_capability('local/pshub:viewadminportal', $system)
            || has_capability('local/pshub:viewteacherportal', $system);
    }
}
