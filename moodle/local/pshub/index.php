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
 * The public home page.
 *
 * Browsable without an account, like the catalogue: a visitor must be able to
 * see what is on offer and what it costs before being asked for anything
 * (AC-2). So there is deliberately no require_login() here.
 *
 * Every section is server-rendered and every control is a link or a form.
 * Nothing on this page needs JavaScript to appear, which is what AC-20 asks
 * for and what makes it usable on the 2G connections §13.2 is written around.
 *
 * @package    local_pshub
 * @copyright  PS Language Hub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_pshub\catalogue\catalogue_service;

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/pshub/index.php'));
$PAGE->set_pagelayout('pspublic');
$PAGE->set_title(get_string('home_title', 'local_pshub'));
$PAGE->set_heading(get_string('home_title', 'local_pshub'));

$service = new catalogue_service();

// Featured is an editorial choice (§3.3): an administrator marks courses and
// orders them. When nobody has chosen yet, the home page shows the first
// published courses rather than an empty shelf - a new site should still look
// like a shop on the day it opens.
$featured = $service->get_featured(4);
if (!$featured) {
    $featured = $service->search([], 0, 4)->courses;
}

echo $OUTPUT->header();

echo $OUTPUT->render_from_template('local_pshub/home', [
    'featured' => $featured,
    'hasfeatured' => (bool) $featured,
    'catalogueurl' => (new moodle_url('/local/pshub/catalogue.php'))->out(false),
    'signupurl' => (new moodle_url('/login/signup.php'))->out(false),
]);

echo $OUTPUT->footer();
