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
 * The session clock: a countdown to the door, and a warning before the end.
 *
 * THIS MODULE DECIDES NOTHING. It counts, and when the count reaches zero it
 * asks the server again by reloading the page. The verdict - may this person
 * go in - is taken server-side on every request (AC-10). A countdown that
 * granted access would be a door JavaScript could open.
 *
 * NEVER THE DEVICE CLOCK FOR ABSOLUTE TIME. The page is rendered with the
 * number of seconds, from the server's own clock, until the door opens and
 * until the class ends. From there this ticks with the ELAPSED time since the
 * page loaded, which is all a device clock can be trusted for. A handset an
 * hour out therefore still shows the right countdown.
 *
 * The page is complete without this file: it already prints when the room
 * opens and how long the class runs (AC-20). This makes the wait legible,
 * nothing more.
 *
 * WHY AMD AND NOT AN ES6 MODULE
 * Moodle's convention is an ES6 source compiled by grunt into amd/build. This
 * project has no node toolchain yet (`PSH-S01-07`), and requirejs.php serves
 * amd/src verbatim when a build file has no source map - so an ES6 source here
 * would reach the browser as `export const`, which is not AMD and does not
 * load. Written as AMD, both paths serve working code. When the toolchain
 * exists, this becomes ES6 and grunt generates the build file.
 *
 * @module     local_pshub/session_clock
 * @copyright  PS Language Hub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define('local_pshub/session_clock', [], function() {
    'use strict';

    /**
     * Seconds as a short, spoken-sounding duration.
     *
     * @param {Number} total
     * @returns {String}
     */
    var readable = function(total) {
        var minutes = Math.floor(total / 60);
        var seconds = total % 60;

        if (minutes >= 60) {
            var hours = Math.floor(minutes / 60);
            return hours + ' h ' + String(minutes % 60).padStart(2, '0');
        }

        return minutes + ':' + String(seconds).padStart(2, '0');
    };

    return {
        /**
         * Start the clock on a lobby page.
         *
         * @param {Number} opensin Seconds until the door opens; 0 when open.
         * @param {Number} endsin Seconds until the class ends; 0 when not running.
         */
        init: function(opensin, endsin) {
            var countdown = document.querySelector('[data-ps-countdown]');
            var warning = document.querySelector('[data-ps-endswarning]');
            var started = Date.now();

            // Elapsed since load, from the device - the one thing its clock is
            // good for. Never Date.now() against a server timestamp.
            var elapsed = function() {
                return Math.floor((Date.now() - started) / 1000);
            };

            var tick = function() {
                var sincestart = elapsed();
                var left;

                if (opensin > 0) {
                    left = opensin - sincestart;

                    if (left <= 0) {
                        // The moment has come: ask the server, which is the
                        // only thing allowed to say yes.
                        window.location.reload();
                        return;
                    }

                    if (countdown) {
                        countdown.textContent = readable(left);
                        countdown.hidden = false;
                    }
                }

                if (endsin > 0 && warning) {
                    left = endsin - sincestart;

                    if (left <= 0) {
                        window.location.reload();
                        return;
                    }

                    // Five minutes, then two. On a fifty-minute class that is
                    // the 45 and 48 minute marks the specification asks for,
                    // expressed as time remaining so a shorter class gets the
                    // same courtesy.
                    if (left <= 300) {
                        warning.textContent = warning.dataset.psEndswarning
                            .replace('{time}', readable(left));
                        warning.hidden = false;
                        warning.classList.toggle('ps-lobby__warning--urgent', left <= 120);
                    }
                }
            };

            tick();
            setInterval(tick, 1000);
        }
    };
});
