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
 * The image for a published course, served publicly.
 *
 * WHY THIS EXISTS
 * Moodle serves course files through pluginfile.php, which honours
 * $CFG->forcelogin - and this site sets it, so that Moodle's own pages stay
 * out of a visitor's way (AC-17). The consequence is that an anonymous
 * visitor browsing the catalogue gets a redirect to the login page instead of
 * every course image: pictures on a public page that only members can see.
 *
 * So the catalogue serves its own. The rule it enforces is the catalogue's
 * rule, not Moodle's: a course that is PUBLISHED is public, image included
 * (AC-2), and a course that is not published does not exist as far as this
 * endpoint is concerned (AC-1) - it answers 404 rather than explaining.
 *
 * Nothing else is exposed. Only the course overview file area, only images,
 * only for published courses.
 *
 * @package    local_pshub
 * @copyright  PS Language Hub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:disable moodle.Files.RequireLogin.Missing
require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/filelib.php');

use local_pshub\local\constants;

$id = required_param('id', PARAM_INT);

/**
 * Answer 404 and stop.
 *
 * Deliberately not send_file_not_found(), which renders a Moodle error page:
 * this URL is only ever the src of an <img>, and a styled HTML page is a
 * strange thing to hand a browser that asked for a picture.
 */
$notfound = function(): void {
    header('HTTP/1.1 404 Not Found');
    die();
};

$published = $DB->record_exists('local_pshub_course', [
    'courseid' => $id,
    'review_status' => constants::REVIEW_PUBLISHED,
]);

if (!$published) {
    $notfound();
}

$context = context_course::instance($id, IGNORE_MISSING);
if (!$context) {
    $notfound();
}

$fs = get_file_storage();
$files = $fs->get_area_files($context->id, 'course', 'overviewfiles', 0, 'filename', false);

foreach ($files as $file) {
    if (!$file->is_valid_image()) {
        continue;
    }

    // Cached hard, because a course image changes about never and this is a
    // 2G-first product (§13.2). The response carries an ETag and
    // Last-Modified, so a browser that does revalidate gets a 304 - but one
    // that does not may show a replaced image for up to a day.
    send_stored_file($file, DAYSECS, 0, false, [
        'cacheability' => 'public',
        'immutable' => false,
    ]);
}

$notfound();
