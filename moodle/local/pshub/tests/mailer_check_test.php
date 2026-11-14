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

namespace local_pshub;

use core\check\result;
use local_pshub\check\mailer;

/**
 * The check that says, in a sentence, whether this site can send mail.
 *
 * @package    local_pshub
 * @copyright  PS Language Hub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_pshub\check\mailer
 */
final class mailer_check_test extends \advanced_testcase {

    /**
     * A port nothing is listening on, found rather than assumed.
     *
     * Hard-coding one risks a test that passes because something really was
     * there, which is the opposite of what it checks.
     *
     * @return int
     */
    private function closed_port(): int {
        for ($port = 49200; $port < 49300; $port++) {
            $socket = @fsockopen('127.0.0.1', $port, $errno, $errstr, 1);
            if ($socket === false) {
                return $port;
            }
            fclose($socket);
        }

        $this->markTestSkipped('no closed port found on this machine');
    }

    /**
     * A host that answers is reported as answering.
     *
     * The listener is opened by the test, so this passes on a machine with no
     * mail catcher at all.
     */
    public function test_a_host_that_answers_is_ok(): void {
        global $CFG;
        $this->resetAfterTest();

        $server = @stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        if ($server === false) {
            $this->markTestSkipped('cannot open a listening socket here');
        }
        $name = stream_socket_get_name($server, false);
        $CFG->smtphosts = $name;

        $outcome = (new mailer())->get_result();
        fclose($server);

        $this->assertSame(result::OK, $outcome->get_status());
        $this->assertStringContainsString($name, $outcome->get_summary());
    }

    /**
     * A host that refuses is an error, not a warning.
     *
     * Nothing reaches anybody: not the signup confirmation decision 4 made
     * mandatory, not the review decision a teacher is waiting on, not the
     * reminder that stops a learner missing a class.
     */
    public function test_a_host_that_refuses_is_an_error(): void {
        global $CFG;
        $this->resetAfterTest();

        $CFG->smtphosts = '127.0.0.1:' . $this->closed_port();

        $outcome = (new mailer())->get_result();

        $this->assertSame(result::ERROR, $outcome->get_status());
        $this->assertNotEmpty($outcome->get_details());
    }

    /**
     * The detail says the actions themselves still succeeded.
     *
     * This is the whole reason the check is worded the way it is: the failure
     * arrives as a stack trace over a course that really was approved, and an
     * administrator reads it as "the approval failed".
     */
    public function test_the_detail_says_the_action_still_succeeded(): void {
        global $CFG;
        $this->resetAfterTest();

        $CFG->smtphosts = '127.0.0.1:' . $this->closed_port();

        $details = (new mailer())->get_result()->get_details();

        // Take the string with a sentinel and keep the part before it, so the
        // assertion holds in any language without pinning a sentence.
        $template = get_string('check_mailer_down_detail', 'local_pshub', '@@HOST@@');
        $fixed = explode('@@HOST@@', $template)[0];

        $this->assertNotEmpty($fixed);
        $this->assertStringContainsString($fixed, $details);
    }

    /**
     * One host answering is enough, as it is when Moodle sends.
     */
    public function test_one_host_answering_is_enough(): void {
        global $CFG;
        $this->resetAfterTest();

        $server = @stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        if ($server === false) {
            $this->markTestSkipped('cannot open a listening socket here');
        }
        $good = stream_socket_get_name($server, false);
        $CFG->smtphosts = '127.0.0.1:' . $this->closed_port() . ';' . $good;

        $outcome = (new mailer())->get_result();
        fclose($server);

        $this->assertSame(result::OK, $outcome->get_status());
    }

    /**
     * With no SMTP host at all, the honest answer is "unknown".
     *
     * The site is using PHP's mail(), which cannot be reached out and tested.
     * Reporting OK would be a guess, and this check exists because a guess was
     * wrong twice.
     */
    public function test_no_smtp_host_is_unknown_not_ok(): void {
        global $CFG;
        $this->resetAfterTest();

        $CFG->smtphosts = '';

        $this->assertSame(result::UNKNOWN, (new mailer())->get_result()->get_status());
    }

    /**
     * Moodle finds the check through the plugin's lib.php.
     *
     * A check nothing collects is a check nobody reads.
     */
    public function test_moodle_collects_the_check(): void {
        $this->resetAfterTest();

        $found = false;
        foreach (\core\check\manager::get_status_checks() as $check) {
            if ($check instanceof mailer) {
                $found = true;
            }
        }

        $this->assertTrue($found, 'local_pshub_status_checks() must expose it');
    }
}
