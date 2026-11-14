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

namespace local_pshub\admin;

use local_pshub\local\constants;
use stdClass;

/**
 * Which courses the home page leads with, and in what order.
 *
 * `featured_order` is kept **dense and one-based** - 1, 2, 3 with no holes -
 * because the only operations on it are "move up" and "move down", and a
 * sparse column turns those into arithmetic nobody can check by looking.
 * Every write renumbers.
 *
 * Only published courses may be featured: the home page would otherwise lead
 * with a course the catalogue refuses to show.
 *
 * @package    local_pshub
 * @copyright  PS Language Hub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class featured_service {

    /**
     * The featured courses, in the order the home page shows them.
     *
     * @return stdClass[] {courseid, fullname, langplate, cardtone}
     */
    public function get_featured(): array {
        global $DB;

        $sql = "SELECT p.courseid, p.lang_code, p.review_status, c.fullname
                  FROM {local_pshub_course} p
                  JOIN {course} c ON c.id = p.courseid
                 WHERE p.is_featured = 1
              ORDER BY p.featured_order ASC, c.fullname ASC";

        $out = [];
        foreach ($DB->get_records_sql($sql) as $record) {
            $course = $this->present($record);
            // A featured course that is no longer published - sent back for
            // review, say - stays on this list so it can be taken off, but the
            // home page does not show it. Saying so here is the difference
            // between a list and a list that lies.
            $course->islive = ($record->review_status === constants::REVIEW_PUBLISHED);
            $out[] = $course;
        }

        return $out;
    }

    /**
     * Published courses that are not featured yet.
     *
     * @return stdClass[]
     */
    public function get_available(): array {
        global $DB;

        $sql = "SELECT p.courseid, p.lang_code, c.fullname
                  FROM {local_pshub_course} p
                  JOIN {course} c ON c.id = p.courseid
                 WHERE p.is_featured = 0 AND p.review_status = :published
              ORDER BY c.fullname ASC";

        return array_map([$this, 'present'],
            array_values($DB->get_records_sql($sql, ['published' => constants::REVIEW_PUBLISHED])));
    }

    /**
     * Add a course to the front page, at the end.
     *
     * @param int $courseid
     * @return void
     * @throws \moodle_exception
     */
    public function feature(int $courseid): void {
        global $DB;

        $hub = $DB->get_record('local_pshub_course', ['courseid' => $courseid], '*', MUST_EXIST);

        if ($hub->review_status !== constants::REVIEW_PUBLISHED) {
            throw new \moodle_exception('featured_not_published', 'local_pshub');
        }

        if ($hub->is_featured) {
            return;
        }

        $hub->is_featured = 1;
        $hub->featured_order = $this->next_position();
        $hub->timemodified = time();
        $DB->update_record('local_pshub_course', $hub);

        $this->renumber();
    }

    /**
     * Take a course off the front page.
     *
     * @param int $courseid
     * @return void
     */
    public function unfeature(int $courseid): void {
        global $DB;

        $hub = $DB->get_record('local_pshub_course', ['courseid' => $courseid], '*', MUST_EXIST);
        $hub->is_featured = 0;
        $hub->featured_order = 0;
        $hub->timemodified = time();
        $DB->update_record('local_pshub_course', $hub);

        $this->renumber();
    }

    /**
     * Move a featured course one place up or down.
     *
     * Swapping with the neighbour rather than adding or subtracting from the
     * position: with a dense column these are the same thing, and a swap stays
     * correct even if the column ever is not.
     *
     * @param int $courseid
     * @param int $direction -1 up, 1 down.
     * @return void
     */
    public function move(int $courseid, int $direction): void {
        global $DB;

        $ordered = $this->get_featured();
        $index = null;
        foreach ($ordered as $position => $course) {
            if ($course->courseid === $courseid) {
                $index = $position;
                break;
            }
        }

        $target = $index === null ? null : $index + ($direction < 0 ? -1 : 1);
        if ($target === null || $target < 0 || $target >= count($ordered)) {
            // Already at the end it was asked to move past. Not an error: the
            // button is simply not offered there, and a stray request should
            // change nothing rather than explain itself.
            return;
        }

        $swap = $ordered;
        [$swap[$index], $swap[$target]] = [$swap[$target], $swap[$index]];

        $position = 1;
        foreach ($swap as $course) {
            $DB->set_field('local_pshub_course', 'featured_order', $position,
                ['courseid' => $course->courseid]);
            $position++;
        }
    }

    /**
     * Close the gaps, so positions are always 1..n.
     *
     * @return void
     */
    private function renumber(): void {
        global $DB;

        $position = 1;
        foreach ($this->get_featured() as $course) {
            $DB->set_field('local_pshub_course', 'featured_order', $position,
                ['courseid' => $course->courseid]);
            $position++;
        }
    }

    /**
     * One past the last position in use.
     *
     * @return int
     */
    private function next_position(): int {
        global $DB;

        return 1 + (int) $DB->get_field_sql(
            'SELECT COALESCE(MAX(featured_order), 0) FROM {local_pshub_course} WHERE is_featured = 1');
    }

    /**
     * The fields the page shows.
     *
     * @param stdClass $record
     * @return stdClass
     */
    private function present(stdClass $record): stdClass {
        return (object) [
            'courseid' => (int) $record->courseid,
            'fullname' => format_string($record->fullname),
            'langplate' => \core_text::strtoupper($record->lang_code ?? ''),
            'cardtone' => ((int) $record->courseid % 4) + 1,
        ];
    }
}
