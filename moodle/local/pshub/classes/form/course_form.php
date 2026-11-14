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

namespace local_pshub\form;

use local_pshub\local\constants;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Create or edit a hub course.
 *
 * The form a teacher actually fills in. It asks for the things the catalogue
 * needs and nothing else: the course's own content is authored in Moodle,
 * which is where a teacher already knows how to work.
 *
 * No shortname field. Moodle requires one and requires it to be unique, and
 * asking a teacher to invent an identifier - then telling them it is taken -
 * is a step that teaches nobody anything. course_service derives it.
 *
 * The checks here are the ones a browser can make. The ones that decide
 * whether a course may be SUBMITTED live in review_service, because they have
 * to hold whoever calls them.
 *
 * @package    local_pshub
 * @copyright  PS Language Hub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_form extends \moodleform {

    /**
     * Form definition.
     *
     * @return void
     */
    protected function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        // -- What the course is ------------------------------------------
        $mform->addElement('header', 'about', get_string('course_form_about', 'local_pshub'));

        $mform->addElement('text', 'fullname', get_string('course_form_title', 'local_pshub'),
            ['size' => 48]);
        $mform->setType('fullname', PARAM_TEXT);
        $mform->addRule('fullname', null, 'required', null, 'client');
        $mform->addRule('fullname', get_string('maximumchars', '', 254), 'maxlength', 254, 'client');

        $mform->addElement('textarea', 'summary', get_string('course_form_summary', 'local_pshub'),
            ['rows' => 6, 'cols' => 60]);
        $mform->setType('summary', PARAM_CLEANHTML);
        $mform->addHelpButton('summary', 'course_form_summary', 'local_pshub');

        $mform->addElement('select', 'lang_code', get_string('course_form_language', 'local_pshub'),
            self::languages());
        $mform->addRule('lang_code', null, 'required', null, 'client');

        $mform->addElement('select', 'cefr_level', get_string('course_form_level', 'local_pshub'),
            self::levels());
        $mform->addRule('cefr_level', null, 'required', null, 'client');

        $mform->addElement('select', 'audience', get_string('course_form_audience', 'local_pshub'), [
            'adult' => get_string('audience_adult', 'local_pshub'),
            'kid' => get_string('audience_kid', 'local_pshub'),
        ]);

        // -- How it is taught --------------------------------------------
        $mform->addElement('header', 'teaching', get_string('course_form_teaching', 'local_pshub'));
        $mform->setExpanded('teaching');

        $cap = self::cap_minutes();
        $mform->addElement('text', 'session_duration_minutes',
            get_string('course_form_duration', 'local_pshub'), ['size' => 6]);
        $mform->setType('session_duration_minutes', PARAM_INT);
        $mform->setDefault('session_duration_minutes', $cap);
        $mform->addHelpButton('session_duration_minutes', 'course_form_duration', 'local_pshub');

        $mform->addElement('advcheckbox', 'is_one_to_one',
            get_string('course_form_onetoone', 'local_pshub'));

        $mform->addElement('text', 'max_participants',
            get_string('course_form_maxparticipants', 'local_pshub'), ['size' => 6]);
        $mform->setType('max_participants', PARAM_INT);
        $mform->hideIf('max_participants', 'is_one_to_one', 'checked');

        // -- What it costs -----------------------------------------------
        $mform->addElement('header', 'price', get_string('course_form_price', 'local_pshub'));
        $mform->setExpanded('price');

        $mform->addElement('select', 'access_type', get_string('course_form_access', 'local_pshub'), [
            constants::ACCESS_TYPE_FREE => get_string('filter_access_free', 'local_pshub'),
            constants::ACCESS_TYPE_PAID => get_string('filter_access_paid', 'local_pshub'),
        ]);

        $mform->addElement('text', 'price_amount',
            get_string('course_form_amount', 'local_pshub'), ['size' => 10]);
        $mform->setType('price_amount', PARAM_INT);
        $mform->hideIf('price_amount', 'access_type', 'eq', constants::ACCESS_TYPE_FREE);
        $mform->addHelpButton('price_amount', 'course_form_amount', 'local_pshub');

        $this->add_action_buttons(true, get_string('course_form_save', 'local_pshub'));
    }

    /**
     * Server-side checks.
     *
     * hideIf hides a field; it does not stop a browser sending it, so every
     * rule that matters is repeated here.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        $cap = self::cap_minutes();
        $minutes = (int) $data['session_duration_minutes'];

        if ($minutes < 10) {
            $errors['session_duration_minutes'] = get_string('course_form_duration_short', 'local_pshub');
        } else if ($minutes > $cap) {
            // The cap is the platform's promise, not a suggestion: a session
            // ends at it whatever a course says (§8.3).
            $errors['session_duration_minutes'] =
                get_string('course_form_duration_long', 'local_pshub', $cap);
        }

        if (empty($data['is_one_to_one']) && !empty($data['max_participants'])
                && (int) $data['max_participants'] < 2) {
            $errors['max_participants'] = get_string('course_form_group_small', 'local_pshub');
        }

        if ($data['access_type'] === constants::ACCESS_TYPE_PAID
                && (int) $data['price_amount'] <= 0) {
            $errors['price_amount'] = get_string('course_form_price_zero', 'local_pshub');
        }

        return $errors;
    }

    /**
     * The platform's session cap, in minutes.
     *
     * The setting is an admin_setting_configduration, which stores SECONDS.
     * Reading it as minutes gives a cap of 3000 minutes and a validation rule
     * that can never fail.
     *
     * @return int
     */
    public static function cap_minutes(): int {
        $seconds = (int) get_config('local_pshub', 'sessioncap');

        return $seconds > 0
            ? (int) round($seconds / MINSECS)
            : constants::DEFAULT_SESSION_MINUTES;
    }

    /**
     * The languages this school teaches.
     *
     * An administrator setting rather than a list in the code, for the same
     * reason course types are rows: adding Portuguese should be a form
     * submission, not a deployment (AC-8).
     *
     * @return array code => label
     */
    public static function languages(): array {
        $configured = get_config('local_pshub', 'languages');
        $codes = preg_split('/\s*,\s*/', trim((string) $configured), -1, PREG_SPLIT_NO_EMPTY);

        if (!$codes) {
            $codes = ['en', 'fr', 'es', 'de'];
        }

        $names = get_string_manager()->get_list_of_languages();
        $out = [];
        foreach ($codes as $code) {
            // The language's own name when Moodle knows it, the code itself
            // when it does not - never an empty option.
            $out[$code] = $names[$code] ?? \core_text::strtoupper($code);
        }

        return $out;
    }

    /**
     * The CEFR levels. Fixed by the standard, so not configurable.
     *
     * @return array
     */
    public static function levels(): array {
        return array_combine(
            ['A1', 'A2', 'B1', 'B2', 'C1', 'C2'],
            ['A1', 'A2', 'B1', 'B2', 'C1', 'C2']
        );
    }
}
