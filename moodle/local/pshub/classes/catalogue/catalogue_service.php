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

namespace local_pshub\catalogue;

use core_text;
use local_pshub\local\constants;
use stdClass;

/**
 * Reading the public catalogue.
 *
 * The catalogue is browsable without an account (AC-2): a visitor sees what is
 * on offer and what it costs, and is asked to sign in only when they try to
 * open something. So this service answers questions for anonymous callers and
 * never consults the gating service - gating decides whether content OPENS,
 * not whether a course may be advertised.
 *
 * What it does enforce, absolutely, is that an unpublished course is invisible
 * (AC-1). A course under review must not surface in a search result, a
 * featured row, or a filter facet.
 *
 * @package    local_pshub
 * @copyright  PS Language Hub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class catalogue_service {

    /** @var int Default page size for the catalogue. */
    public const PER_PAGE = 12;

    /** @var price_resolver */
    private price_resolver $prices;

    /**
     * @param price_resolver|null $prices
     */
    public function __construct(?price_resolver $prices = null) {
        $this->prices = $prices ?? new price_resolver();
    }

    /**
     * Courses an administrator has chosen for the home page.
     *
     * @param int $limit
     * @return stdClass[] Presented courses, see present().
     */
    public function get_featured(int $limit = 6): array {
        global $DB;

        $sql = "SELECT p.*, c.fullname, c.shortname, c.summary, c.summaryformat
                  FROM {local_pshub_course} p
                  JOIN {course} c ON c.id = p.courseid
                 WHERE p.review_status = :published
                       AND p.is_featured = 1
              ORDER BY p.featured_order ASC, c.fullname ASC";

        $records = $DB->get_records_sql($sql,
            ['published' => constants::REVIEW_PUBLISHED], 0, $limit);

        return array_map([$this, 'present'], array_values($records));
    }

    /**
     * Search the catalogue.
     *
     * @param array $filters Any of: lang (string), level (string),
     *        access (free|paid), typecode (string), q (string).
     * @param int $page Zero-based.
     * @param int $perpage
     * @return stdClass total (int), courses (array), page, perpage
     */
    public function search(array $filters = [], int $page = 0, int $perpage = self::PER_PAGE): stdClass {
        global $DB;

        [$where, $params] = $this->build_where($filters);

        $from = "FROM {local_pshub_course} p
                 JOIN {course} c ON c.id = p.courseid";

        // A type filter needs the join, but only when one was asked for: a
        // course carries several types, and an unconditional join would
        // multiply rows.
        if (!empty($filters['typecode'])) {
            $from .= " JOIN {local_pshub_course_type_map} m ON m.courseid = p.courseid
                       JOIN {local_pshub_course_type} t ON t.id = m.typeid";
        }

        $total = (int) $DB->count_records_sql("SELECT COUNT(DISTINCT p.id) {$from} WHERE {$where}", $params);

        $records = $DB->get_records_sql(
            "SELECT DISTINCT p.*, c.fullname, c.shortname, c.summary, c.summaryformat
             {$from} WHERE {$where}
             ORDER BY c.fullname ASC",
            $params,
            $page * $perpage,
            $perpage
        );

        $result = new stdClass();
        $result->total = $total;
        $result->page = $page;
        $result->perpage = $perpage;
        $result->courses = array_map([$this, 'present'], array_values($records));

        return $result;
    }

    /**
     * One course, or false when it is not publicly visible.
     *
     * @param int $courseid
     * @return stdClass|false
     */
    public function get_course(int $courseid) {
        global $DB;

        $sql = "SELECT p.*, c.fullname, c.shortname, c.summary, c.summaryformat
                  FROM {local_pshub_course} p
                  JOIN {course} c ON c.id = p.courseid
                 WHERE p.courseid = :courseid AND p.review_status = :published";

        $record = $DB->get_record_sql($sql, [
            'courseid' => $courseid,
            'published' => constants::REVIEW_PUBLISHED,
        ]);

        return $record ? $this->present($record) : false;
    }

    /**
     * The values worth offering as filters.
     *
     * Derived from what is actually published rather than from a fixed list,
     * so the catalogue never offers a filter that returns nothing.
     *
     * @return stdClass langs, levels, types (each an array)
     */
    public function get_facets(): stdClass {
        global $DB;

        $published = ['published' => constants::REVIEW_PUBLISHED];

        $facets = new stdClass();

        $facets->langs = $DB->get_fieldset_sql(
            "SELECT DISTINCT lang_code FROM {local_pshub_course}
              WHERE review_status = :published AND lang_code IS NOT NULL AND lang_code <> ''
           ORDER BY lang_code ASC", $published);

        $facets->levels = $DB->get_fieldset_sql(
            "SELECT DISTINCT cefr_level FROM {local_pshub_course}
              WHERE review_status = :published AND cefr_level IS NOT NULL AND cefr_level <> ''
           ORDER BY cefr_level ASC", $published);

        $facets->types = $DB->get_records_sql(
            "SELECT DISTINCT t.id, t.code, t.name_fr, t.name_en, t.badge_style, t.sortorder
               FROM {local_pshub_course_type} t
               JOIN {local_pshub_course_type_map} m ON m.typeid = t.id
               JOIN {local_pshub_course} p ON p.courseid = m.courseid
              WHERE t.is_active = 1 AND p.review_status = :published
           ORDER BY t.sortorder ASC", $published);

        return $facets;
    }

    /**
     * Build the WHERE clause shared by search and count.
     *
     * @param array $filters
     * @return array [string $where, array $params]
     */
    protected function build_where(array $filters): array {
        global $DB;

        // Published is not a filter. It is the floor, and it is applied first
        // so no later branch can accidentally widen past it (AC-1).
        $where = ['p.review_status = :published'];
        $params = ['published' => constants::REVIEW_PUBLISHED];

        if (!empty($filters['lang'])) {
            $where[] = 'p.lang_code = :lang';
            $params['lang'] = $filters['lang'];
        }

        if (!empty($filters['level'])) {
            $where[] = 'p.cefr_level = :level';
            $params['level'] = $filters['level'];
        }

        if (!empty($filters['access'])) {
            $where[] = 'p.access_type = :access';
            $params['access'] = $filters['access'];
        }

        if (!empty($filters['typecode'])) {
            $where[] = 't.code = :typecode AND t.is_active = 1';
            $params['typecode'] = $filters['typecode'];
        }

        if (!empty($filters['q'])) {
            $like = $DB->sql_like('c.fullname', ':q', false, false);
            $where[] = $like;
            $params['q'] = '%' . $DB->sql_like_escape($filters['q']) . '%';
        }

        return [implode(' AND ', $where), $params];
    }

    /**
     * Turn a raw joined row into what a card or a page needs.
     *
     * Public because the learner's own space presents the same courses on the
     * same cards (learner_service). One presenter means the price, badges and
     * image a learner sees inside are the ones the catalogue showed outside.
     *
     * Price and badges come from the price resolver rather than being read off
     * the row, so the catalogue can never disagree with the paywall about what
     * a course costs.
     *
     * @param stdClass $record
     * @return stdClass
     */
    public function present(stdClass $record): stdClass {
        $price = $this->prices->resolve((int) $record->courseid);

        $out = new stdClass();
        $out->courseid = (int) $record->courseid;
        $out->fullname = format_string($record->fullname);
        $out->shortname = format_string($record->shortname);
        $out->summary = format_text($record->summary ?? '', $record->summaryformat ?? FORMAT_HTML);
        $out->lang_code = $record->lang_code;
        $out->cefr_level = $record->cefr_level;
        $out->isfree = (bool) $price->isfree;
        $out->price = $price->amount;
        $out->pricebase = $price->base;
        $out->discountpercent = $price->discountpercent;
        $out->currency = $price->currency;
        $out->badges = $price->badges;

        // Preformatted for templates, which must not do arithmetic or know
        // that XAF has no subunit.
        $out->priceformatted = price_resolver::format($price->amount, $price->currency);
        $out->pricebaseformatted = price_resolver::format($price->base, $price->currency);
        $out->hasdiscount = ($price->discountpercent > 0 && !$price->isfree);
        $out->is_one_to_one = (bool) $record->is_one_to_one;
        $out->session_duration_minutes = (int) $record->session_duration_minutes;
        $out->max_participants = $record->max_participants ? (int) $record->max_participants : null;
        $out->url = (new \moodle_url('/local/pshub/course.php',
            ['id' => $record->courseid]))->out(false);

        // A course shows its own image when someone has set one, and the
        // language on a brand ground when nobody has. Both matter: an image
        // sells a course, and the plate means a card is never empty and no
        // course has to wait for a photograph before it can be published.
        //
        // The URL comes from core's own cache, which reads the course overview
        // files - so an image uploaded through Moodle's course form appears
        // here with no extra work and no second copy of the resolving logic.
        // One caveat, core's and not ours: that cache is refreshed when the
        // course is saved, so a file dropped straight into the file area can
        // lag until the course is next edited.
        // Core's cache answers "is there an image", which is all it is asked.
        // The URL it holds points at pluginfile.php, and pluginfile.php obeys
        // $CFG->forcelogin - so on a public catalogue it would redirect every
        // anonymous visitor's browser to the login page instead of a picture.
        // image.php serves the same file under the catalogue's own rule:
        // published means public (AC-2), unpublished means 404 (AC-1).
        $image = \cache::make('core', 'course_image')->get($out->courseid);
        $out->hasimage = !empty($image);
        $out->imageurl = $out->hasimage
            ? (new \moodle_url('/local/pshub/image.php', ['id' => $out->courseid]))->out(false)
            : null;

        $out->langplate = core_text::strtoupper($record->lang_code ?? '');

        // Which of the four card grounds this course gets. Derived from the id
        // so a course keeps the same colour on the home page, in the catalogue
        // and after a reload - varied, but never random.
        $out->cardtone = ($out->courseid % 4) + 1;

        // Templates cannot count, and the card shows at most one badge: the
        // first, which price_resolver has already ordered by sortorder.
        $out->topbadge = $price->badges ? $price->badges[0] : null;

        return $out;
    }
}
