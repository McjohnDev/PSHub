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

namespace local_pshub\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_pshub\learner\learner_service;

/**
 * The courses the caller is taking.
 *
 * WHAT IS DELIBERATELY NOT HERE
 * `learner_service::get_courses()` returns a template context: a card tone, a
 * language plate, a call-to-action label, a price already formatted with a
 * narrow no-break space. Those are answers to questions the web pages ask.
 * An application lays itself out and writes its own buttons, and a field like
 * `cardtone` would be a web decision leaking into a client that cannot act on
 * it - and a field we could never change afterwards without breaking it.
 *
 * So this returns the course, and the numbers the app needs to format for
 * itself. `priceformatted` is the one exception, because XAF has no subunit
 * and a client that divides by 100 produces a wrong price in a currency its
 * author has probably never handled.
 *
 * @package    local_pshub
 * @copyright  PS Language Hub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_my_courses extends external_api {

    /**
     * Describes the parameters: none. The caller is the answer.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    /**
     * The caller's own courses.
     *
     * @return array
     */
    public static function execute(): array {
        global $USER;

        self::validate_parameters(self::execute_parameters(), []);
        self::validate_context(\context_system::instance());

        $service = new learner_service();
        $courses = [];

        foreach ($service->get_courses($USER->id) as $course) {
            $courses[] = [
                'courseid' => (int) $course->courseid,
                'fullname' => $course->fullname,
                // Already run through format_text by the catalogue, so it is
                // HTML. An app renders or strips it; it must not be trusted as
                // plain text.
                'summaryhtml' => $course->summary,
                'langcode' => (string) $course->lang_code,
                'ceflevel' => (string) $course->cefr_level,
                'isfree' => (bool) $course->isfree,
                'price' => (int) $course->price,
                'currency' => (string) $course->currency,
                // NOT $course->priceformatted when the course is free: that
                // field says "0 FCFA", and no web page has ever shown it,
                // because every template branches on isfree first and prints
                // "Gratuit". A client that trusts a field called
                // "priceformatted" would ship "0 FCFA" on every free course,
                // and the trap would only be found by a learner.
                'priceformatted' => $course->isfree
                    ? get_string('card_free', 'local_pshub')
                    : $course->priceformatted,
                'isonetoone' => (bool) $course->is_one_to_one,
                'sessionminutes' => (int) $course->session_duration_minutes,
                'imageurl' => $course->imageurl,
                'weburl' => $course->url,
            ];
        }

        return ['courses' => $courses];
    }

    /**
     * Describes the return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'courses' => new external_multiple_structure(new external_single_structure([
                'courseid' => new external_value(PARAM_INT, 'Course id'),
                'fullname' => new external_value(PARAM_TEXT, 'Course name'),
                'summaryhtml' => new external_value(PARAM_RAW, 'Description, as HTML'),
                'langcode' => new external_value(PARAM_ALPHANUMEXT, 'Language taught'),
                'ceflevel' => new external_value(PARAM_ALPHANUMEXT, 'A1 to C2'),
                'isfree' => new external_value(PARAM_BOOL, 'Free to join'),
                'price' => new external_value(PARAM_INT,
                    'In the currency\'s own units. XAF has no subunit: do not divide'),
                'currency' => new external_value(PARAM_ALPHA, 'ISO currency code'),
                'priceformatted' => new external_value(PARAM_TEXT,
                    'Ready to show, in the caller\'s language. Says "free" for a free course, not "0"'),
                'isonetoone' => new external_value(PARAM_BOOL, 'One learner, one teacher'),
                'sessionminutes' => new external_value(PARAM_INT, 'How long a class runs'),
                'imageurl' => new external_value(PARAM_URL,
                    'Course image, or absent when there is none', VALUE_OPTIONAL),
                'weburl' => new external_value(PARAM_URL, 'The web page for this course'),
            ])),
        ]);
    }
}
