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
 * The administration console: what is waiting, and what exists.
 *
 * The product's own overview, not a replacement for Moodle's site
 * administration - roles, cohorts and competency frameworks stay there, which
 * the sidebar says. This page answers the questions the platform is about:
 * what is waiting for a decision, what is published, who is on it.
 *
 * It is READ ONLY. Approving a course is sprint 4 and validating a payment
 * sprint 7; both go through their own services, with their own audit rows. A
 * console that could quietly change state without one would be the first place
 * the audit trail got a hole in it.
 *
 * @package    local_pshub
 * @copyright  PS Language Hub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

use local_pshub\admin\admin_service;
use local_pshub\catalogue\price_resolver;

require_login();

$context = context_system::instance();
require_capability('local/pshub:viewadminportal', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/pshub/admin/index.php'));
$PAGE->set_pagelayout('psadmin');
$PAGE->set_title(get_string('admin_title', 'local_pshub'));
$PAGE->set_heading(get_string('admin_title', 'local_pshub'));

$service = new admin_service();
$counts = $service->get_course_counts();
$queue = $service->get_review_queue();
$people = $service->get_people();
$money = $service->get_money();
$types = $service->get_types();

$queuerows = [];
foreach ($queue as $row) {
    $queuerows[] = [
        'fullname' => $row->fullname,
        'langplate' => $row->langplate,
        'cardtone' => $row->cardtone,
        'url' => $row->url,
        'hassubmitter' => $row->hassubmitter,
        'submitter' => $row->submitter,
        'submitted' => $row->timesubmitted
            ? userdate($row->timesubmitted, get_string('strftimedate', 'langconfig'))
            : null,
        'hassubmitted' => (bool) $row->timesubmitted,
        'hasmeta' => ($row->hassubmitter || $row->timesubmitted),
        'approveurl' => (new moodle_url('/local/pshub/review.php',
            ['id' => $row->courseid, 'action' => 'approve']))->out(false),
        'rejecturl' => (new moodle_url('/local/pshub/review.php',
            ['id' => $row->courseid, 'action' => 'reject']))->out(false),
    ];
}

$typerows = [];
foreach ($types as $type) {
    $typerows[] = [
        'label' => (current_language() === 'en' && !empty($type->name_en)) ? $type->name_en : $type->name_fr,
        'style' => $type->badge_style,
    ];
}

echo $OUTPUT->header();

echo $OUTPUT->render_from_template('local_pshub/admin_overview', [
    'published' => $counts[\local_pshub\local\constants::REVIEW_PUBLISHED],
    'pending' => $counts[\local_pshub\local\constants::REVIEW_PENDING],
    'draft' => $counts[\local_pshub\local\constants::REVIEW_DRAFT],
    'teachers' => $people->teachers,
    'learners' => $people->learners,

    'queue' => $queuerows,
    'hasqueue' => (bool) $queuerows,

    // No transaction has ever been recorded, so there is nothing to report and
    // the page says that rather than printing a zero that looks like a result.
    'hasmoney' => (bool) $money,
    'money' => $money ? [
        'validated' => price_resolver::format($money->validated, $money->currency),
        'pending' => $money->pending,
        'pendingamount' => price_resolver::format($money->pendingamount, $money->currency),
    ] : null,

    // The fourth guard from docs/PAYMENTS.md. A console that looked the same
    // whether the money was real or scripted is how a fake provider survives
    // into production unnoticed.
    'fakepayments' => \local_pshub\payment\provider_factory::is_fake_enabled(),

    'types' => $typerows,
    'hastypes' => (bool) $typerows,
    'typesurl' => (new moodle_url('/local/pshub/admin/coursetypes.php'))->out(false),
]);

echo $OUTPUT->footer();
