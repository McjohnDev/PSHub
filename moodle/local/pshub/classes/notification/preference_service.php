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

namespace local_pshub\notification;

use stdClass;

/**
 * What this person is told, and where - in sentences rather than a matrix.
 *
 * WHY THIS EXISTS
 * Moodle's notification preferences are a grid of provider names against
 * processor names: "Course submitted for review / Web / Email / Mobile". It
 * is the right screen for an administrator and the wrong one for a learner on
 * a phone, who is trying to answer a single question - do I get a reminder
 * before my class (§10.1).
 *
 * WHAT IT DOES NOT DO
 * It writes Moodle's own preferences, in Moodle's own format. It is a
 * different way of asking the same question, not a second place where the
 * answer lives: turning e-mail off here is the same as turning it off there,
 * and message_send() reads one of them.
 *
 * It also covers this plugin's notifications only, and says so on the page.
 * The rest of Moodle's providers are for things this product does not put in
 * front of a learner.
 *
 * @package    local_pshub
 * @copyright  PS Language Hub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class preference_service {

    /** @var string The component whose providers this screen covers. */
    public const COMPONENT = 'local_pshub';

    /** @var string[] The one nobody should turn off without being told. */
    public const ESSENTIAL = ['sessionreminder'];

    /**
     * The channels that can actually reach this person.
     *
     * A processor the site has switched off, or one the person has not set up
     * - mobile push with no device registered - is not an option. Offering it
     * would be offering a box that delivers nothing when ticked, which is
     * worse than not offering it: they would stop looking for the message.
     *
     * @param stdClass $user
     * @return array<int, array{name: string, label: string}>
     */
    public function get_channels(stdClass $user): array {
        $channels = [];

        foreach (get_message_processors(true) as $processor) {
            if (!$processor->object->is_user_configured($user)) {
                continue;
            }

            $channels[] = [
                'name' => $processor->name,
                'label' => $this->channel_label($processor->name),
            ];
        }

        return $channels;
    }

    /**
     * Every notification this person can receive, with where it reaches them.
     *
     * Which ones they see is Moodle's own answer: a provider declared with a
     * capability is only offered to people who hold it, so a learner is never
     * asked whether they want to hear about courses awaiting review.
     *
     * @param stdClass $user
     * @return array<int, array>
     */
    public function get_settings(stdClass $user): array {
        $channels = $this->get_channels($user);
        $settings = [];

        foreach (message_get_providers_for_user($user->id) as $provider) {
            if ($provider->component !== self::COMPONENT) {
                continue;
            }

            $on = $this->enabled_channels($user, $provider->name);

            $rows = [];
            foreach ($channels as $channel) {
                $rows[] = $channel + [
                    'checked' => in_array($channel['name'], $on, true),
                    // The field name carries both halves, so the form posts a
                    // flat list and the service does the unpicking.
                    'field' => 'pref_' . $provider->name . '_' . $channel['name'],
                ];
            }

            $settings[] = [
                'provider' => $provider->name,
                'title' => get_string('notif_' . $provider->name, 'local_pshub'),
                'description' => get_string('notif_' . $provider->name . '_desc', 'local_pshub'),
                'essential' => in_array($provider->name, self::ESSENTIAL, true),
                'channels' => $rows,
            ];
        }

        return $settings;
    }

    /**
     * Which channels are on for one notification.
     *
     * The user's own answer when they have given one, the site's default when
     * they have not. Reading only the user preference would show every box
     * unticked to somebody who has never been here - and they do receive
     * these messages, so that would be a lie on the screen.
     *
     * @param stdClass $user
     * @param string $provider
     * @return string[]
     */
    public function enabled_channels(stdClass $user, string $provider): array {
        $name = 'message_provider_' . self::COMPONENT . '_' . $provider . '_enabled';

        $preference = get_user_preferences($name, null, $user);
        if ($preference === null) {
            $defaults = get_message_output_default_preferences();
            $preference = $defaults->{$name} ?? '';
        }

        if ($preference === '' || $preference === 'none') {
            return [];
        }

        return explode(',', $preference);
    }

    /**
     * Write the choices back, in Moodle's own format.
     *
     * @param stdClass $user
     * @param array<string, string[]> $chosen Provider name => channel names.
     * @return void
     */
    public function save(stdClass $user, array $chosen): void {
        $available = array_column($this->get_channels($user), 'name');

        foreach ($this->get_settings($user) as $setting) {
            $provider = $setting['provider'];

            // Only channels that exist, and only providers this person is
            // actually offered: a posted field is not a permission.
            $channels = array_values(array_intersect($chosen[$provider] ?? [], $available));

            set_user_preference(
                'message_provider_' . self::COMPONENT . '_' . $provider . '_enabled',
                $channels ? implode(',', $channels) : 'none',
                $user
            );
        }
    }

    /**
     * Pull the choices out of a submitted form.
     *
     * @param array $raw Usually $_POST, already past require_sesskey().
     * @return array<string, string[]>
     */
    public function read_form(array $raw): array {
        $chosen = [];

        foreach ($raw as $key => $value) {
            if (strpos($key, 'pref_') !== 0 || !$value) {
                continue;
            }

            // pref_<provider>_<channel>; the channel has no underscore, the
            // provider might.
            $rest = substr($key, strlen('pref_'));
            $cut = strrpos($rest, '_');
            if ($cut === false) {
                continue;
            }

            $chosen[substr($rest, 0, $cut)][] = substr($rest, $cut + 1);
        }

        return $chosen;
    }

    /**
     * Has this person switched every notification off at the account level?
     *
     * `emailstop` is badly named: Moodle checks it before EVERY processor, not
     * just e-mail, so somebody who set it hears nothing at all. A preferences
     * screen that showed ticked boxes over the top of it would be showing
     * settings that cannot take effect.
     *
     * @param stdClass $user
     * @return bool
     */
    public function is_silenced(stdClass $user): bool {
        return !empty($user->emailstop);
    }

    /**
     * Turn the account-level silence on or off.
     *
     * @param stdClass $user
     * @param bool $silenced
     * @return void
     */
    public function set_silenced(stdClass $user, bool $silenced): void {
        global $DB, $USER;

        $DB->set_field('user', 'emailstop', $silenced ? 1 : 0, ['id' => $user->id]);
        $user->emailstop = $silenced ? 1 : 0;

        if ((int) $USER->id === (int) $user->id) {
            $USER->emailstop = $user->emailstop;
        }
    }

    /**
     * What to call a channel in front of somebody who is not an administrator.
     *
     * "Popup" and "Airnotifier" are the names of the code that delivers the
     * message. Nobody outside this repository has heard of either.
     *
     * @param string $processor
     * @return string
     */
    private function channel_label(string $processor): string {
        $key = 'notif_channel_' . $processor;

        if (get_string_manager()->string_exists($key, 'local_pshub')) {
            return get_string($key, 'local_pshub');
        }

        // An unknown processor still gets a name rather than nothing: a site
        // can install one we have never heard of.
        return get_string('pluginname', 'message_' . $processor);
    }
}
