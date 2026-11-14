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

use local_pshub\local\constants;
use stdClass;

/**
 * What a course actually costs, given the types attached to it.
 *
 * One place answers this, because the answer appears on the catalogue card,
 * the course page, the paywall and the checkout, and those four disagreeing is
 * a support ticket that starts with the word "fraud".
 *
 * The rules, in order:
 *
 *   1. Any active type with grants_free_access makes the course free. A
 *      "Gratuit" badge is not decoration, it is the price.
 *   2. Otherwise the single largest active discount applies. Discounts do not
 *      stack: two promotions on one course is a merchandising decision, not a
 *      45 per cent course.
 *   3. The result floors at zero. A 150 per cent discount is a typo, not a
 *      refund.
 *
 * Packs price separately and do not pass through here (spec §3.3).
 *
 * @package    local_pshub
 * @copyright  PS Language Hub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class price_resolver {

    /** @var array Per-request cache, keyed by course id. */
    private array $cache = [];

    /**
     * Resolve the price of a course.
     *
     * @param int $courseid
     * @return stdClass isfree (bool), base (int, minor units), amount (int,
     *         effective), currency (string), discountpercent (int),
     *         badges (array of objects with label and style).
     */
    public function resolve(int $courseid): stdClass {
        if (isset($this->cache[$courseid])) {
            return $this->cache[$courseid];
        }

        global $DB;

        $course = $DB->get_record('local_pshub_course', ['courseid' => $courseid]);
        $types = $this->types_for_course($courseid);

        $result = new stdClass();
        $result->base = $course ? (int) $course->price_amount : 0;
        $result->currency = $course ? $course->price_currency : 'XAF';
        $result->discountpercent = 0;
        $result->badges = [];

        foreach ($types as $type) {
            $result->badges[] = (object) [
                'label' => $this->label($type),
                'style' => $type->badge_style,
            ];
        }

        // Rule 1: a free-granting type settles it.
        $grantsfree = false;
        foreach ($types as $type) {
            if ((int) $type->grants_free_access === 1) {
                $grantsfree = true;
                break;
            }
        }

        if ($grantsfree || !$course || $course->access_type === constants::ACCESS_TYPE_FREE) {
            $result->isfree = true;
            $result->amount = 0;
            $this->cache[$courseid] = $result;

            return $result;
        }

        // Rule 2: the largest single discount, not the sum of them.
        foreach ($types as $type) {
            $percent = (int) $type->discount_percent;
            if ($type->kind === 'discount' && $percent > $result->discountpercent) {
                $result->discountpercent = min($percent, 100);
            }
        }

        // Rule 3: floor at zero.
        $amount = (int) round($result->base * (100 - $result->discountpercent) / 100);
        $result->amount = max(0, $amount);
        $result->isfree = ($result->amount === 0);

        $this->cache[$courseid] = $result;

        return $result;
    }

    /**
     * Whether a course opens without payment.
     *
     * The gating service asks this rather than reading access_type directly,
     * so a "Gratuit" type attached by an administrator opens the course
     * without anyone having to remember to change access_type as well.
     *
     * @param int $courseid
     * @return bool
     */
    public function is_effectively_free(int $courseid): bool {
        return (bool) $this->resolve($courseid)->isfree;
    }

    /**
     * Active types attached to a course, in display order.
     *
     * @param int $courseid
     * @return array
     */
    public function types_for_course(int $courseid): array {
        global $DB;

        $sql = "SELECT t.*
                  FROM {local_pshub_course_type} t
                  JOIN {local_pshub_course_type_map} m ON m.typeid = t.id
                 WHERE m.courseid = :courseid
                       AND t.is_active = 1
              ORDER BY t.sortorder ASC, t.id ASC";

        return $DB->get_records_sql($sql, ['courseid' => $courseid]);
    }

    /**
     * The badge label in the current interface language.
     *
     * Types are admin-created data, so their labels live in columns rather
     * than in a language pack. Two columns cover the two interface languages
     * of §12.4; a third language means a third column or a rethink, and that
     * is a deliberate limit rather than an oversight.
     *
     * @param stdClass $type
     * @return string
     */
    protected function label(stdClass $type): string {
        return (current_language() === 'en' && $type->name_en !== '')
            ? $type->name_en
            : $type->name_fr;
    }

    /**
     * Drop the per-request cache. For tests and long-running tasks.
     *
     * @return void
     */
    public function reset_cache(): void {
        $this->cache = [];
    }

    /**
     * Currencies with no subunit, per ISO 4217.
     *
     * XAF is the platform's principal currency and has no centimes: 15000 XAF
     * is fifteen thousand francs, not a hundred and fifty. Formatting it the
     * way one would format euros would divide every price on the site by a
     * hundred, which is the kind of bug that gets noticed by an accountant
     * rather than a test.
     *
     * @var string[]
     */
    private const ZERO_DECIMAL = ['XAF', 'XOF', 'JPY', 'KRW', 'VND', 'CLP', 'ISK'];

    /**
     * Format a stored amount for display.
     *
     * @param int $amount As stored.
     * @param string $currency ISO 4217 code.
     * @return string
     */
    public static function format(int $amount, string $currency = 'XAF'): string {
        $currency = strtoupper($currency);

        if (in_array($currency, self::ZERO_DECIMAL, true)) {
            // Narrow no-break space between groups: it is the French
            // convention, and the platform's default language is French.
            $number = number_format($amount, 0, ',', "\u{202F}");
        } else {
            $number = number_format($amount / 100, 2, ',', "\u{202F}");
        }

        // FCFA is what people actually call it in the region; XAF is the code
        // a bank uses.
        $symbol = ($currency === 'XAF' || $currency === 'XOF') ? 'FCFA' : $currency;

        return $number . "\u{00A0}" . $symbol;
    }
}
