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

use local_pshub\session\session_service;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Put a live session in the diary.
 *
 * Three questions: what it is called, when it starts, how long it runs. The
 * cap is stated in the help text rather than discovered on submit - a teacher
 * who types ninety should be told the rule before they are corrected by it.
 *
 * @package    local_pshub
 * @copyright  PS Language Hub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class session_form extends \moodleform {

    /**
     * Form definition.
     *
     * @return void
     */
    protected function definition() {
        $mform = $this->_form;
        $cap = (new session_service())->cap_minutes();

        $mform->addElement('hidden', 'courseid');
        $mform->setType('courseid', PARAM_INT);

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('text', 'title', get_string('session_form_title', 'local_pshub'),
            ['size' => 48]);
        $mform->setType('title', PARAM_TEXT);
        $mform->addRule('title', null, 'required', null, 'client');
        $mform->addHelpButton('title', 'session_form_title', 'local_pshub');

        // date_time_selector works in the reader's own timezone and submits a
        // timestamp, which is the only way a teacher in Douala and a learner
        // in Yaoundé agree about when the class is.
        $mform->addElement('date_time_selector', 'start',
            get_string('session_form_start', 'local_pshub'));

        $mform->addElement('text', 'minutes', get_string('session_form_minutes', 'local_pshub'),
            ['size' => 6]);
        $mform->setType('minutes', PARAM_INT);
        $mform->setDefault('minutes', $cap);
        $mform->addElement('static', 'caphint', '',
            get_string('session_form_caphint', 'local_pshub', $cap));

        $this->add_action_buttons(true, get_string('session_form_save', 'local_pshub'));
    }

    /**
     * Server-side checks.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if ((int) $data['minutes'] < 10) {
            $errors['minutes'] = get_string('session_too_short', 'local_pshub');
        }

        // A class in the past cannot be attended, and scheduling one is almost
        // always a mistyped year. New sessions only: an existing one may be
        // edited after it has happened without this complaining.
        if (empty($data['id']) && (int) $data['start'] < time()) {
            $errors['start'] = get_string('session_form_past', 'local_pshub');
        }

        return $errors;
    }
}
