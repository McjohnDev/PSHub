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
 * PS Language Hub theme.
 *
 * A Boost child. Boost is the only theme bundled with Moodle 5.3 now that
 * Classic is deprecated, and it is the right base regardless.
 *
 * Layouts are deliberately NOT redefined here. Inheriting them from Boost is
 * what keeps this theme cheap to carry across Moodle upgrades: every layout we
 * copy is a layout we have to re-reconcile later (spec §12.1). The override
 * register in OVERRIDES.md records the few files we do take responsibility for.
 *
 * @package    theme_pslanguagehub
 * @copyright  PS Language Hub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$THEME->name = 'pslanguagehub';
$THEME->parents = ['boost'];
$THEME->sheets = [];
$THEME->editor_sheets = [];
$THEME->usefallback = true;
$THEME->iconsystem = \core\output\icon_system::FONTAWESOME;

// Required, and NOT inherited from the parent even though $THEME->parents
// names Boost. Without it Moodle uses the default factory, which only ever
// instantiates core renderers, so Boost's own renderer methods vanish. Boost's
// drawers layout calls $OUTPUT->firstview_fakeblocks(), which lives on
// theme_boost\output\core_renderer, and every page using that layout dies with
// "Call to undefined method core\output\core_renderer::firstview_fakeblocks()".
//
// Worth knowing how this hid: the login page uses its own layout and never
// touches those methods, so the theme looked healthy right up until the first
// authenticated page was loaded.
$THEME->rendererfactory = 'theme_overridden_renderer_factory';

// Boost compiles the Bootstrap preset; we only bracket it. Brand variables go
// in before (so Bootstrap builds itself in our colours) and component styles
// after (so we can rely on Bootstrap's output existing).
$THEME->scss = function($theme) {
    return theme_boost_get_main_scss_content($theme);
};
$THEME->prescsscallback = 'theme_pslanguagehub_get_pre_scss';
$THEME->extrascsscallback = 'theme_pslanguagehub_get_extra_scss';

// One layout of our own, and only one. Core cascades parent layouts first and
// the child's last (theme_config, "Cascade all layouts properly"), so adding a
// key here leaves every other page on the layout Boost gives it.
//
// The public pages need it because AC-17 forbids Moodle chrome on a learner
// journey, and Boost's layouts ARE Moodle chrome: the navbar and user menu,
// the drawers, the footer popover. This one renders a product site instead.
// It declares no block regions, which is what keeps it cheap to re-reconcile
// at the next Moodle major (spec §12.1).
$THEME->layouts = [
    // Sign in, sign up and forgotten password. Boost's version of this layout
    // renders a template we already override; ours exists only to pass two
    // pieces of context Boost does not (which page it is, and the year).
    'login' => [
        'file' => 'login.php',
        'regions' => [],
        'options' => ['langmenu' => true],
    ],

    // The learner's own space behind the login wall: sidebar, search, page.
    // Until it existed, signing in landed a learner on Moodle's dashboard,
    // which is exactly the chrome AC-17 forbids.
    'pslearner' => [
        'file' => 'pslearner.php',
        'regions' => [],
        'options' => [
            'nonavbar' => true,
            'nocourseheaderfooter' => true,
            'noactivityheader' => true,
        ],
    ],

    'psteacher' => [
        'file' => 'psteacher.php',
        'regions' => [],
        'options' => [
            'nonavbar' => true,
            'nocourseheaderfooter' => true,
            'noactivityheader' => true,
        ],
    ],

    'psadmin' => [
        'file' => 'psadmin.php',
        'regions' => [],
        'options' => [
            'nonavbar' => true,
            'nocourseheaderfooter' => true,
            'noactivityheader' => true,
        ],
    ],

    'pspublic' => [
        'file' => 'pspublic.php',
        'regions' => [],
        'options' => [
            'nonavbar' => true,
            'nocourseheaderfooter' => true,
            'noactivityheader' => true,
        ],
    ],
];

$THEME->requiredblocks = '';
$THEME->addblockposition = BLOCK_ADDBLOCK_POSITION_FLATNAV;
