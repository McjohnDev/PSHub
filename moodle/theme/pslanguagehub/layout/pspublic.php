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
 * The public shell: home, catalogue, course detail.
 *
 * WHY A LAYOUT OF OUR OWN, IN A THEME THAT DELIBERATELY HAS NONE
 *
 * Every other layout is inherited from Boost, because each one we copy is one
 * we have to re-reconcile at the next Moodle major (spec §12.1). This one
 * earns the exception: AC-17 says a learner never sees Moodle chrome, and
 * Boost's layouts are Moodle chrome by definition - the navbar with its user
 * menu, the drawers, the "?" footer popover, the course menus. A visitor who
 * has not signed up yet should see a product, not a learning management
 * system.
 *
 * It is also the cheapest of the layouts to carry forward: no blocks, no
 * regions, no drawers, no activity header. What it renders is a navigation
 * bar, the page, and a footer.
 *
 * @package    theme_pslanguagehub
 * @copyright  PS Language Hub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/** @var moodle_page $PAGE */
/** @var core_renderer $OUTPUT */

$loggedin = isloggedin() && !isguestuser();

// The nav is short on purpose. Four destinations, no dropdowns: a menu that
// has to be opened is a menu most phone visitors never open.
$navitems = [
    [
        'url' => (new moodle_url('/local/pshub/catalogue.php'))->out(false),
        'label' => get_string('nav_catalogue', 'theme_pslanguagehub'),
    ],
    [
        'url' => (new moodle_url('/local/pshub/index.php', [], 'fonctionnement'))->out(false),
        'label' => get_string('nav_howitworks', 'theme_pslanguagehub'),
    ],
    [
        'url' => (new moodle_url('/local/pshub/index.php', [], 'reseaux'))->out(false),
        'label' => get_string('nav_bandwidth', 'theme_pslanguagehub'),
    ],
    [
        'url' => (new moodle_url('/local/pshub/index.php', [], 'app'))->out(false),
        'label' => get_string('nav_app', 'theme_pslanguagehub'),
    ],
];

$templatecontext = [
    'output' => $OUTPUT,
    'bodyattributes' => $OUTPUT->body_attributes(['ps-public']),
    'sitename' => format_string($SITE->shortname, true,
        ['context' => context_course::instance(SITEID), 'escape' => false]),
    'homeurl' => (new moodle_url('/local/pshub/index.php'))->out(false),
    'catalogueurl' => (new moodle_url('/local/pshub/catalogue.php'))->out(false),
    'navitems' => $navitems,
    'loggedin' => $loggedin,

    // Signing in returns the visitor to the page they were reading, which is
    // the whole point of asking them to sign in there rather than at the door.
    'loginurl' => (new moodle_url('/login/index.php'))->out(false),
    'signupurl' => (new moodle_url('/login/signup.php'))->out(false),
    'myurl' => (new moodle_url('/my/'))->out(false),

    // The staff door (cahier §5.1): one discreet line in the footer, pointing
    // at Moodle's own login. Moodle's landing rules then route by capability,
    // so there is nothing here to keep in step with the role model.
    'staffurl' => (new moodle_url('/login/index.php'))->out(false),

    'year' => userdate(time(), '%Y'),
];

echo $OUTPUT->render_from_template('theme_pslanguagehub/pspublic', $templatecontext);
