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
 * Create or edit one course type.
 *
 * @package    local_pshub
 * @copyright  PS Language Hub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_pshub\form\course_type_form;

admin_externalpage_setup('local_pshub_coursetypes');

require_capability('local/pshub:managecoursetypes', context_system::instance());

$id = optional_param('id', 0, PARAM_INT);
$listurl = new moodle_url('/local/pshub/admin/coursetypes.php');

$PAGE->set_url(new moodle_url('/local/pshub/admin/coursetype_edit.php', ['id' => $id]));

$existing = null;
if ($id) {
    $existing = $DB->get_record('local_pshub_course_type', ['id' => $id], '*', MUST_EXIST);
}

$form = new course_type_form();
if ($existing) {
    $form->set_data($existing);
}

if ($form->is_cancelled()) {
    redirect($listurl);
}

if ($data = $form->get_data()) {
    $record = (object) [
        'code' => $data->code,
        'name_fr' => $data->name_fr,
        'name_en' => $data->name_en,
        'kind' => $data->kind,
        // A discount percentage on a non-discount type is noise that would
        // confuse the price resolver's intent later. Normalise it away.
        'discount_percent' => $data->kind === 'discount' ? (int) $data->discount_percent : 0,
        'grants_free_access' => (int) $data->grants_free_access,
        'badge_style' => $data->badge_style,
        'sortorder' => (int) $data->sortorder,
        'is_active' => (int) $data->is_active,
        'timemodified' => time(),
    ];

    if (!empty($data->id)) {
        $record->id = $data->id;
        $DB->update_record('local_pshub_course_type', $record);
        $message = get_string('type_updated', 'local_pshub');
    } else {
        $record->timecreated = time();
        $DB->insert_record('local_pshub_course_type', $record);
        $message = get_string('type_created', 'local_pshub');
    }

    redirect($listurl, $message, null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo $OUTPUT->heading($existing
    ? get_string('type_edit', 'local_pshub')
    : get_string('type_add', 'local_pshub'));

$form->display();

echo $OUTPUT->footer();
