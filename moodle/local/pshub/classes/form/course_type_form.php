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

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Create or edit a course type.
 *
 * This form is what makes AC-8 true for a human. The service layer already
 * prices an invented type correctly; without this an administrator would need
 * SQL to invent one.
 *
 * Built on moodleform rather than hand-rolled markup, which brings CSRF,
 * server-side validation, accessibility and the site's own styling for free -
 * the same reasoning that keeps core/loginform untouched in the theme.
 *
 * @package    local_pshub
 * @copyright  PS Language Hub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_type_form extends \moodleform {

    /**
     * Form definition.
     *
     * @return void
     */
    protected function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        // The stable identifier. Code is what the rest of the platform keys
        // on, so it is deliberately not free text once saved.
        $mform->addElement('text', 'code', get_string('type_code', 'local_pshub'), ['size' => 24]);
        $mform->setType('code', PARAM_ALPHANUMEXT);
        $mform->addRule('code', null, 'required', null, 'client');
        $mform->addHelpButton('code', 'type_code', 'local_pshub');

        $mform->addElement('text', 'name_fr', get_string('type_name_fr', 'local_pshub'), ['size' => 32]);
        $mform->setType('name_fr', PARAM_TEXT);
        $mform->addRule('name_fr', null, 'required', null, 'client');

        $mform->addElement('text', 'name_en', get_string('type_name_en', 'local_pshub'), ['size' => 32]);
        $mform->setType('name_en', PARAM_TEXT);
        $mform->addHelpButton('name_en', 'type_name_en', 'local_pshub');

        $kinds = [
            'discount' => get_string('kind_discount', 'local_pshub'),
            'highlight' => get_string('kind_highlight', 'local_pshub'),
            'free' => get_string('kind_free', 'local_pshub'),
            'exam' => get_string('kind_exam', 'local_pshub'),
            'custom' => get_string('kind_custom', 'local_pshub'),
        ];
        $mform->addElement('select', 'kind', get_string('type_kind', 'local_pshub'), $kinds);
        $mform->setDefault('kind', 'custom');
        $mform->addHelpButton('kind', 'type_kind', 'local_pshub');

        $mform->addElement('text', 'discount_percent',
            get_string('type_discount', 'local_pshub'), ['size' => 6]);
        $mform->setType('discount_percent', PARAM_INT);
        $mform->setDefault('discount_percent', 0);
        // Only meaningful for a discount, so only shown for one.
        $mform->hideIf('discount_percent', 'kind', 'neq', 'discount');

        $mform->addElement('advcheckbox', 'grants_free_access',
            get_string('type_grantsfree', 'local_pshub'), get_string('type_grantsfree_label', 'local_pshub'));
        $mform->setDefault('grants_free_access', 0);
        $mform->addHelpButton('grants_free_access', 'type_grantsfree', 'local_pshub');

        $styles = [
            'promo' => get_string('style_promo', 'local_pshub'),
            'hot' => get_string('style_hot', 'local_pshub'),
            'free' => get_string('style_free', 'local_pshub'),
            'exam' => get_string('style_exam', 'local_pshub'),
        ];
        $mform->addElement('select', 'badge_style', get_string('type_badgestyle', 'local_pshub'), $styles);
        $mform->setDefault('badge_style', 'exam');

        $mform->addElement('text', 'sortorder', get_string('type_sortorder', 'local_pshub'), ['size' => 6]);
        $mform->setType('sortorder', PARAM_INT);
        $mform->setDefault('sortorder', 50);
        $mform->addHelpButton('sortorder', 'type_sortorder', 'local_pshub');

        $mform->addElement('advcheckbox', 'is_active',
            get_string('type_active', 'local_pshub'), get_string('type_active_label', 'local_pshub'));
        $mform->setDefault('is_active', 1);
        $mform->addHelpButton('is_active', 'type_active', 'local_pshub');

        $this->add_action_buttons();
    }

    /**
     * Server-side validation.
     *
     * Client-side rules are a convenience; these are the ones that matter,
     * because a crafted POST never runs the JavaScript.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        global $DB;

        $errors = parent::validation($data, $files);

        // The code is the platform's handle on this type. Two rows sharing one
        // would make the unique index throw at insert, which is a stack trace
        // rather than a message.
        $params = ['code' => $data['code']];
        $select = 'code = :code';
        if (!empty($data['id'])) {
            $select .= ' AND id <> :id';
            $params['id'] = $data['id'];
        }
        if ($DB->record_exists_select('local_pshub_course_type', $select, $params)) {
            $errors['code'] = get_string('type_code_taken', 'local_pshub');
        }

        if ($data['kind'] === 'discount') {
            $percent = (int) $data['discount_percent'];
            if ($percent < 1 || $percent > 100) {
                $errors['discount_percent'] = get_string('type_discount_range', 'local_pshub');
            }
        }

        // A type that both grants free access and carries a discount is a
        // contradiction the price resolver settles silently in favour of free.
        // Better to refuse it here than to let an administrator believe a
        // 20 per cent discount is being applied.
        if (!empty($data['grants_free_access']) && $data['kind'] === 'discount') {
            $errors['grants_free_access'] = get_string('type_free_vs_discount', 'local_pshub');
        }

        return $errors;
    }
}
