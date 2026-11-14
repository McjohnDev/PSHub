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
 * SCSS bracketing for theme_pslanguagehub.
 *
 * @package    theme_pslanguagehub
 * @copyright  PS Language Hub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Brand variables, injected before Bootstrap compiles.
 *
 * Setting Bootstrap's own variables rather than overriding its output means
 * every component it generates - buttons, focus rings, links, form controls -
 * comes out in brand colours without a single !important.
 *
 * @param theme_config $theme
 * @return string
 */
function theme_pslanguagehub_get_pre_scss($theme) {
    global $CFG;

    $scss = file_get_contents($CFG->dirroot . '/theme/pslanguagehub/scss/pre.scss');

    // Anything Boost's parent wants to say still gets said.
    if (function_exists('theme_boost_get_pre_scss')) {
        $scss = theme_boost_get_pre_scss($theme) . "\n" . $scss;
    }

    return $scss;
}

/**
 * Component styles, appended after Bootstrap.
 *
 * Note what this does NOT do: call theme_boost_get_extra_scss(). Two reasons,
 * and the second is the one that actually forced it.
 *
 * 1. Core already calls it. theme_config::get_extra_scss_code() walks the
 *    parent configs first and invokes each parent's callback before this one,
 *    so calling it here emitted every Boost rule twice.
 *
 * 2. Boost's version paints login_background.jpg across the login panel and,
 *    when that default image is in use, stamps an "AI-generated image"
 *    watermark over it. Our login layout has no such panel, so not emitting
 *    those rules is simpler than overriding them.
 *
 *    A correction, because the wrong reason was recorded here first: an
 *    override written in post.scss appeared not to reach the browser, and
 *    core_minify::css was blamed for collapsing it against Boost's identical
 *    earlier selector. It does no such thing. purge_caches.php does not bump
 *    the theme revision, so styles.php was still serving the previous
 *    stylesheet. Use theme_reset_all_caches(), and read the CSS at the URL the
 *    page actually references before concluding anything about the minifier.
 *
 * What is reproduced below is the one part of Boost's callback worth keeping:
 * the site-wide background image setting, and any raw SCSS an administrator
 * has entered in theme settings. Dropping those silently would break unrelated
 * features.
 *
 * @param theme_config $theme
 * @return string
 */
function theme_pslanguagehub_get_extra_scss($theme) {
    global $CFG;

    $content = '';

    // Site-wide background image, as Boost does it.
    $imageurl = $theme->setting_file_url('backgroundimage', 'backgroundimage');
    if (!empty($imageurl)) {
        $content .= '@media (min-width: 768px) {';
        $content .= 'body { ';
        $content .= "background-image: url('$imageurl'); background-size: cover;";
        $content .= ' } }';
    }

    // A custom login background, only if an administrator deliberately set one.
    // The default photograph and its watermark are never emitted.
    $loginbackgroundimageurl = $theme->setting_file_url('loginbackgroundimage', 'loginbackgroundimage');
    if (!empty($loginbackgroundimageurl)) {
        $content .= 'body.pagelayout-login #page .login-layout-left { ';
        $content .= "background-image: url('$loginbackgroundimageurl'); ";
        $content .= 'background-size: cover; background-position: center;';
        $content .= ' }';
    }

    // Raw SCSS from theme settings, preserved.
    if (!empty($theme->settings->scss)) {
        $content = $theme->settings->scss . "\n" . $content;
    }

    // Three files, in this order, because they age differently. post.scss
    // styles MOODLE's own pages - the login form above all - and has to be
    // re-checked whenever Boost changes. public.scss styles the public site
    // and app.scss the learner's space behind the login wall; both change only
    // when the product does.
    foreach (['post.scss', 'public.scss', 'app.scss', 'transitions.scss'] as $file) {
        $content .= "\n" . file_get_contents($CFG->dirroot . '/theme/pslanguagehub/scss/' . $file);
    }

    return $content;
}

/**
 * One sidebar icon, inline.
 *
 * Inline because a handful of shapes does not justify an icon font: a font is
 * a download that says nothing until it arrives, on a product written for 2G
 * (§13.2). They are decorative - every one sits beside its own label - and the
 * shell hides them from assistive technology.
 *
 * @param string $name
 * @return string SVG markup, or an empty string for an unknown name.
 */
function theme_pslanguagehub_icon(string $name): string {
    $open = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" '
        . 'stroke-linecap="round" stroke-linejoin="round">';

    $paths = [
        // A rising line in a chart: progress.
        'progress' => '<path d="M3 3v18h18"/><path d="M7 15l4-4 3 3 5-6"/>',
        // A book: the catalogue, and a teacher's own courses.
        'book' => '<path d="M4 5a2 2 0 0 1 2-2h12v18H6a2 2 0 0 1-2-2z"/><path d="M8 7h7"/>',
        // A camera: live sessions.
        'live' => '<rect x="2" y="6" width="13" height="12" rx="2"/><path d="M15 11l7-4v10l-7-4z"/>',
        // Two people: learners.
        'people' => '<path d="M16 19v-1a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v1"/><circle cx="9" cy="7" r="4"/>'
            . '<path d="M22 19v-1a4 4 0 0 0-3-3.87"/>',
        // A ticked box: validation.
        'check' => '<rect x="3" y="3" width="18" height="18" rx="3"/><path d="M8 12l3 3 5-6"/>',
        // A grid with a torn-off top: a diary.
        'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18"/>'
            . '<path d="M8 3v4"/><path d="M16 3v4"/>',
        // A card: payments.
        'card' => '<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/>',
        // A tag: prices and badges.
        'tag' => '<path d="M3 11V4a1 1 0 0 1 1-1h7l10 10-8 8z"/><circle cx="7.5" cy="7.5" r="1.5"/>',
        // Bars: the accounts.
        'chart' => '<path d="M4 20V10"/><path d="M10 20V4"/><path d="M16 20v-7"/><path d="M22 20H2"/>',
    ];

    return isset($paths[$name]) ? $open . $paths[$name] . '</svg>' : '';
}

/**
 * One sidebar entry, marked current when it is the page being shown.
 *
 * Matching on the path rather than the whole URL means a filtered catalogue
 * still highlights "Explore courses" instead of nothing.
 *
 * @param string $path
 * @param string $label
 * @param string $icon Name for theme_pslanguagehub_icon().
 * @param string $currenturl The page's URL without its query string.
 * @return array
 */
function theme_pslanguagehub_nav_item(string $path, string $label, string $icon,
        string $currenturl): array {
    return [
        'url' => (new moodle_url($path))->out(false),
        'label' => $label,
        'iconsvg' => theme_pslanguagehub_icon($icon),
        'current' => (strpos($currenturl, $path) !== false),
    ];
}

/**
 * The parts of the shell that every signed-in space fills in the same way.
 *
 * @param string $homeurl Where the brand mark leads in this space.
 * @return array
 */
function theme_pslanguagehub_shell_context(string $homeurl): array {
    global $OUTPUT, $USER;

    require_once(__DIR__ . '/../../local/pshub/lib.php');

    return [
        'output' => $OUTPUT,
        'bodyattributes' => $OUTPUT->body_attributes(['ps-app']),
        'homeurl' => $homeurl,
        'logouturl' => (new moodle_url('/login/logout.php', ['sesskey' => sesskey()]))->out(false),
        'notificationsurl' => (new moodle_url('/local/pshub/notifications.php'))->out(false),

        // The person, as they are named on their own account. Initials rather
        // than an avatar: Moodle's default avatar is a grey silhouette, and two
        // letters in brand colour say more with less to download (§13.2).
        'fullname' => fullname($USER),
        'initials' => local_pshub_user_initials($USER),
    ];
}
