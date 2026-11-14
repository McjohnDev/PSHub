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
 * "My notifications": what we tell you, and where it reaches you.
 *
 * Moodle's own screen is a grid of provider names against processor names.
 * It is the right screen for an administrator and the wrong one for a learner
 * on a phone, who came to answer one question: do I get a reminder before my
 * class (§10.1).
 *
 * The same page serves learner, teacher and administrator, in whichever shell
 * is theirs - the questions differ by what each of them can actually receive,
 * and Moodle answers that itself through each provider's capability.
 *
 * It writes Moodle's own preferences, in Moodle's own format. This is a
 * different way of asking, not a second place where the answer lives.
 *
 * @package    local_pshub
 * @copyright  PS Language Hub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_pshub\notification\preference_service;

require_login();

$context = context_system::instance();
$url = new moodle_url('/local/pshub/notifications.php');

$PAGE->set_context($context);
$PAGE->set_url($url);
$PAGE->set_title(get_string('notif_title', 'local_pshub'));
$PAGE->set_heading(get_string('notif_title', 'local_pshub'));

// Everyone's own space, not a fourth one. The shell follows the person.
if (has_capability('local/pshub:viewadminportal', $context)) {
    $PAGE->set_pagelayout('psadmin');
} else if (has_capability('local/pshub:viewteacherportal', $context)) {
    $PAGE->set_pagelayout('psteacher');
} else {
    $PAGE->set_pagelayout('pslearner');
}

$service = new preference_service();
$saved = false;

if (optional_param('save', 0, PARAM_BOOL) && confirm_sesskey()) {
    // A form that posts nothing but the token means every box was unticked,
    // which is a choice and has to be honoured - so this is not guarded by
    // "did anything arrive".
    $service->set_silenced($USER, (bool) optional_param('silenced', 0, PARAM_BOOL));
    $service->save($USER, $service->read_form($_POST));

    // Redirect rather than render, so a reload does not re-post. The notice
    // travels in the session, not in the URL.
    redirect(new moodle_url($url, ['saved' => 1]));
}

$saved = (bool) optional_param('saved', 0, PARAM_BOOL);

echo $OUTPUT->header();

echo $OUTPUT->render_from_template('local_pshub/notifications', [
    'formaction' => $url->out(false),
    'sesskey' => sesskey(),
    'saved' => $saved,

    'settings' => $service->get_settings($USER),
    'hassettings' => (bool) $service->get_settings($USER),

    'silenced' => $service->is_silenced($USER),
    'email' => $USER->email,
]);

echo $OUTPUT->footer();
