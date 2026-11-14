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
 * Course types and their badges.
 *
 * AC-8 requires an administrator to add a type and see its badge appear on
 * course cards, with its pricing behaviour applied, and no deployment. The
 * service layer already does the second half; this page is the first.
 *
 * @package    local_pshub
 * @copyright  PS Language Hub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

admin_externalpage_setup('local_pshub_coursetypes');

$context = context_system::instance();
require_capability('local/pshub:managecoursetypes', $context);

$action = optional_param('action', '', PARAM_ALPHA);
$id = optional_param('id', 0, PARAM_INT);

$pageurl = new moodle_url('/local/pshub/admin/coursetypes.php');
$PAGE->set_url($pageurl);

// Delete. Requires sesskey, and refuses to orphan courses silently.
if ($action === 'delete' && $id) {
    require_sesskey();

    $inuse = $DB->count_records('local_pshub_course_type_map', ['typeid' => $id]);
    if ($inuse > 0) {
        redirect(
            $pageurl,
            get_string('type_delete_inuse', 'local_pshub', $inuse),
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }

    $DB->delete_records('local_pshub_course_type', ['id' => $id]);
    redirect($pageurl, get_string('type_deleted', 'local_pshub'), null,
        \core\output\notification::NOTIFY_SUCCESS);
}

// Toggle active without a round trip through the form.
if ($action === 'toggle' && $id) {
    require_sesskey();

    $type = $DB->get_record('local_pshub_course_type', ['id' => $id], '*', MUST_EXIST);
    $DB->set_field('local_pshub_course_type', 'is_active', $type->is_active ? 0 : 1, ['id' => $id]);
    $DB->set_field('local_pshub_course_type', 'timemodified', time(), ['id' => $id]);
    redirect($pageurl);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('coursetypes', 'local_pshub'));

echo html_writer::tag('p', get_string('coursetypes_intro', 'local_pshub'),
    ['class' => 'text-muted']);

echo $OUTPUT->single_button(
    new moodle_url('/local/pshub/admin/coursetype_edit.php'),
    get_string('type_add', 'local_pshub'),
    'get'
);

$types = $DB->get_records('local_pshub_course_type', null, 'sortorder ASC, id ASC');

if (!$types) {
    echo $OUTPUT->notification(get_string('type_none', 'local_pshub'),
        \core\output\notification::NOTIFY_INFO);
} else {
    $table = new html_table();
    $table->head = [
        get_string('type_badge', 'local_pshub'),
        get_string('type_code', 'local_pshub'),
        get_string('type_kind', 'local_pshub'),
        get_string('type_effect', 'local_pshub'),
        get_string('type_sortorder', 'local_pshub'),
        get_string('type_inuse', 'local_pshub'),
        get_string('type_active', 'local_pshub'),
        get_string('actions'),
    ];
    $table->attributes['class'] = 'generaltable';

    foreach ($types as $type) {
        // Show the badge as a learner would see it, so an administrator can
        // judge the colour choice here rather than by visiting the catalogue.
        $badge = html_writer::tag(
            'span',
            format_string($type->name_fr),
            ['class' => 'ps-badge ps-badge--' . $type->badge_style]
        );

        $effect = '';
        if ($type->grants_free_access) {
            $effect = get_string('type_effect_free', 'local_pshub');
        } else if ($type->kind === 'discount') {
            $effect = get_string('type_effect_discount', 'local_pshub', $type->discount_percent);
        } else {
            $effect = get_string('type_effect_none', 'local_pshub');
        }

        $inuse = $DB->count_records('local_pshub_course_type_map', ['typeid' => $type->id]);

        $toggleurl = new moodle_url($pageurl,
            ['action' => 'toggle', 'id' => $type->id, 'sesskey' => sesskey()]);
        $activelabel = $type->is_active
            ? get_string('yes')
            : get_string('no');
        $active = html_writer::link($toggleurl, $activelabel,
            ['class' => $type->is_active ? 'badge bg-success' : 'badge bg-secondary']);

        $actions = html_writer::link(
            new moodle_url('/local/pshub/admin/coursetype_edit.php', ['id' => $type->id]),
            $OUTPUT->pix_icon('t/edit', get_string('edit'))
        );
        // Only offer deletion when it is actually possible.
        if ($inuse === 0) {
            $actions .= ' ' . html_writer::link(
                new moodle_url($pageurl,
                    ['action' => 'delete', 'id' => $type->id, 'sesskey' => sesskey()]),
                $OUTPUT->pix_icon('t/delete', get_string('delete')),
                ['data-confirm' => get_string('type_delete_confirm', 'local_pshub')]
            );
        }

        $table->data[] = [
            $badge,
            html_writer::tag('code', s($type->code)),
            get_string('kind_' . $type->kind, 'local_pshub'),
            $effect,
            $type->sortorder,
            $inuse,
            $active,
            $actions,
        ];
    }

    echo html_writer::table($table);
}

echo $OUTPUT->footer();
