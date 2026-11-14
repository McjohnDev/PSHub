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
 * Choose which courses the home page leads with.
 *
 * The home page already reads `is_featured` and `featured_order`; until this
 * page existed, setting them meant SQL. That is the same gap course types had
 * before `PSH-S02-05`: a column the product depends on and no way for the
 * person responsible to touch it.
 *
 * Only PUBLISHED courses can be featured. Featuring a draft would put a course
 * on the front page that its own catalogue refuses to show.
 *
 * @package    local_pshub
 * @copyright  PS Language Hub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

use local_pshub\admin\featured_service;

require_login();

$context = context_system::instance();
require_capability('local/pshub:viewadminportal', $context);

$action = optional_param('action', '', PARAM_ALPHA);
$id = optional_param('id', 0, PARAM_INT);

$pageurl = new moodle_url('/local/pshub/admin/featured.php');

$PAGE->set_context($context);
$PAGE->set_url($pageurl);
$PAGE->set_pagelayout('psadmin');
$PAGE->set_title(get_string('featured_title', 'local_pshub'));
$PAGE->set_heading(get_string('featured_title', 'local_pshub'));

$service = new featured_service();

// Every change is a POST carrying a sesskey: the home page's running order is
// not something to alter by following a link.
if ($action && confirm_sesskey() && $id) {
    switch ($action) {
        case 'add':
            $service->feature($id);
            break;
        case 'remove':
            $service->unfeature($id);
            break;
        case 'up':
            $service->move($id, -1);
            break;
        case 'down':
            $service->move($id, 1);
            break;
    }

    redirect($pageurl, get_string('featured_saved', 'local_pshub'), null,
        \core\output\notification::NOTIFY_SUCCESS);
}

$featured = $service->get_featured();
$available = $service->get_available();

$rows = [];
$last = count($featured) - 1;
foreach ($featured as $index => $course) {
    $rows[] = [
        'courseid' => $course->courseid,
        'fullname' => $course->fullname,
        'langplate' => $course->langplate,
        'cardtone' => $course->cardtone,
        'position' => $index + 1,
        'isfirst' => ($index === 0),
        'islast' => ($index === $last),
        'islive' => $course->islive,
    ];
}

$choices = [];
foreach ($available as $course) {
    $choices[] = ['courseid' => $course->courseid, 'fullname' => $course->fullname];
}

echo $OUTPUT->header();

echo $OUTPUT->render_from_template('local_pshub/admin_featured', [
    'actionurl' => $pageurl->out(false),
    'sesskey' => sesskey(),
    'featured' => $rows,
    'hasfeatured' => (bool) $rows,
    'choices' => $choices,
    'haschoices' => (bool) $choices,
    'homeurl' => (new moodle_url('/local/pshub/index.php'))->out(false),
]);

echo $OUTPUT->footer();
