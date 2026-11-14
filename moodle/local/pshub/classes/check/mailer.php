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

namespace local_pshub\check;

use core\check\check;
use core\check\result;

/**
 * Can this site actually send mail?
 *
 * WHY THIS EXISTS
 * Every notification this platform sends depends on an SMTP host answering:
 * the signup confirmation, which decision 4 made mandatory; the review
 * decision a teacher is waiting for; the reminder that stops a learner missing
 * a class. When the host stops answering, Moodle does not fail the action - it
 * commits it and calls `debugging()`. With developer debugging on, that notice
 * becomes a stack trace printed over an approval that actually succeeded, and
 * the administrator reads it as "the approval failed". It is not. The mail is
 * what failed, and nothing anywhere said so.
 *
 * It happened twice on the development box in four days, both times because
 * the local mail catcher had stopped. This check turns that into a sentence on
 * the site health page and in `admin/cli/checks.php`, before anybody meets it
 * as a stack trace.
 *
 * WHAT IT DOES NOT DO
 * It opens a socket; it does not send a message, log in, or test TLS. A host
 * that accepts a connection can still refuse the mail. This is the cheap
 * question - "is anything listening" - because that is the one that was
 * answered "no" both times, and a check that costs a round of SMTP is a check
 * that gets disabled.
 *
 * @package    local_pshub
 * @copyright  PS Language Hub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mailer extends check {

    /** @var int Seconds to wait for the host. Short: this runs on a status page. */
    private const TIMEOUT = 3;

    /** @var int Where Moodle sends when no smtphosts is set. */
    private const DEFAULT_PORT = 25;

    /**
     * @return string
     */
    public function get_name(): string {
        return get_string('check_mailer_name', 'local_pshub');
    }

    /**
     * @return result
     */
    public function get_result(): result {
        global $CFG;

        // No SMTP host configured means PHP's own mail(), which this check
        // cannot reach out and test. Saying "unknown" is honest; saying "ok"
        // would be a guess, and this check exists because a guess was wrong.
        if (empty($CFG->smtphosts)) {
            return new result(result::UNKNOWN,
                get_string('check_mailer_nosmtp', 'local_pshub'),
                get_string('check_mailer_nosmtp_detail', 'local_pshub'));
        }

        // smtphosts may list several, separated by semicolons. One answering
        // is enough: that is Moodle's own rule when it sends.
        $hosts = array_filter(array_map('trim', explode(';', $CFG->smtphosts)));
        $failures = [];

        foreach ($hosts as $host) {
            [$name, $port] = $this->split($host);
            $error = $this->probe($name, $port);

            if ($error === null) {
                return new result(result::OK,
                    get_string('check_mailer_ok', 'local_pshub', $host),
                    get_string('check_mailer_ok_detail', 'local_pshub', $host));
            }

            $failures[] = $host . ' - ' . $error;
        }

        // ERROR rather than CRITICAL: the site serves pages perfectly well
        // without a mailer. What it cannot do is let anybody new in, or tell
        // anybody their class is about to start.
        return new result(result::ERROR,
            get_string('check_mailer_down', 'local_pshub', implode(', ', $hosts)),
            get_string('check_mailer_down_detail', 'local_pshub',
                implode('; ', $failures)));
    }

    /**
     * Split "host:port" into its parts, with Moodle's own default.
     *
     * @param string $host
     * @return array{0: string, 1: int}
     */
    private function split(string $host): array {
        $parts = explode(':', $host);
        $port = (count($parts) > 1) ? (int) array_pop($parts) : self::DEFAULT_PORT;

        return [implode(':', $parts), $port ?: self::DEFAULT_PORT];
    }

    /**
     * Is anything listening?
     *
     * @param string $host
     * @param int $port
     * @return string|null The error, or null when the host answered.
     */
    private function probe(string $host, int $port): ?string {
        $errno = 0;
        $errstr = '';

        $socket = @fsockopen($host, $port, $errno, $errstr, self::TIMEOUT);
        if ($socket === false) {
            return $errstr !== '' ? $errstr : ('errno ' . $errno);
        }

        fclose($socket);

        return null;
    }
}
