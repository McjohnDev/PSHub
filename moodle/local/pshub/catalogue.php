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
 * The public catalogue.
 *
 * Browsable WITHOUT an account. AC-2 requires a visitor to see what is on
 * offer and what it costs, and to be asked to sign in only when they try to
 * open something. So there is deliberately no require_login() here.
 *
 * Every filter is a LINK, not a control that needs scripting: a chip is this
 * same page with one parameter changed. That is what makes a filtered view
 * shareable, keeps the back button working, and keeps the page usable with
 * JavaScript off (AC-20) on the connections §13.2 is written around.
 *
 * @package    local_pshub
 * @copyright  PS Language Hub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_pshub\catalogue\catalogue_service;

$lang = optional_param('lang', '', PARAM_ALPHANUMEXT);
$level = optional_param('level', '', PARAM_ALPHANUMEXT);
$access = optional_param('access', '', PARAM_ALPHA);
$typecode = optional_param('typecode', '', PARAM_ALPHANUMEXT);
$query = optional_param('q', '', PARAM_TEXT);
$page = optional_param('page', 0, PARAM_INT);

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/pshub/catalogue.php'));
// The same catalogue, in whichever shell the reader belongs to: the public
// site for a visitor, their own space for someone signed in. One page, one
// query, one set of cards - only the furniture around it changes, which is why
// a learner browsing courses never falls out of the product (AC-17).
$inapp = isloggedin() && !isguestuser();
$PAGE->set_pagelayout($inapp ? 'pslearner' : 'pspublic');
$PAGE->set_title(get_string('catalogue', 'local_pshub'));
$PAGE->set_heading(get_string('catalogue', 'local_pshub'));

$filters = array_filter([
    'lang' => $lang,
    'level' => $level,
    'access' => $access,
    'typecode' => $typecode,
    'q' => $query,
], static fn($value) => $value !== '');

$service = new catalogue_service();
$results = $service->search($filters, $page);
$facets = $service->get_facets();

/**
 * This page's URL with one filter changed and the rest kept.
 *
 * Dropping a filter is setting it to '', which is how the "All" chip works.
 * The page number is never carried over: changing a filter changes the result
 * set, and landing on page 4 of a different search is disorienting.
 *
 * @param string $name
 * @param string $value
 * @return string
 */
$chipurl = function(string $name, string $value) use ($filters): string {
    $params = $filters;
    if ($value === '') {
        unset($params[$name]);
    } else {
        $params[$name] = $value;
    }
    return (new moodle_url('/local/pshub/catalogue.php', $params))->out(false);
};

/**
 * One row of chips: an "any" chip, then one per value.
 *
 * @param string $label
 * @param string $name
 * @param array $values value => label
 * @param string $current
 * @return array
 */
$group = function(string $label, string $name, array $values, string $current) use ($chipurl): array {
    $chips = [[
        'url' => $chipurl($name, ''),
        'label' => get_string('filter_any', 'local_pshub'),
        'active' => ($current === ''),
    ]];
    foreach ($values as $value => $text) {
        $chips[] = [
            'url' => $chipurl($name, (string) $value),
            'label' => $text,
            'active' => ((string) $value === $current),
        ];
    }
    return ['label' => $label, 'chips' => $chips];
};

// Facets are derived from what is actually published, so the catalogue never
// offers a filter that would come back empty.
$groups = [];

if ($facets->langs) {
    $groups[] = $group(get_string('filter_language', 'local_pshub'), 'lang',
        array_combine($facets->langs, array_map([core_text::class, 'strtoupper'], $facets->langs)), $lang);
}

if ($facets->levels) {
    $groups[] = $group(get_string('filter_level', 'local_pshub'), 'level',
        array_combine($facets->levels, $facets->levels), $level);
}

$groups[] = $group(get_string('filter_access', 'local_pshub'), 'access', [
    'free' => get_string('filter_access_free', 'local_pshub'),
    'paid' => get_string('filter_access_paid', 'local_pshub'),
], $access);

if ($facets->types) {
    $typelabels = [];
    foreach ($facets->types as $type) {
        // The badge name an administrator gave the type, in the reader's
        // language (AC-8: types are data, not code).
        $typelabels[$type->code] = (current_language() === 'en' && !empty($type->name_en))
            ? $type->name_en : $type->name_fr;
    }
    $groups[] = $group(get_string('filter_type', 'local_pshub'), 'typecode', $typelabels, $typecode);
}

// The search box carries the chips that are currently on, so searching inside
// a filtered view does not silently widen it.
$hidden = [];
foreach ($filters as $name => $value) {
    if ($name !== 'q') {
        $hidden[] = ['name' => $name, 'value' => $value];
    }
}

echo $OUTPUT->header();

echo $OUTPUT->render_from_template('local_pshub/catalogue', [
    'inapp' => $inapp,
    'actionurl' => (new moodle_url('/local/pshub/catalogue.php'))->out(false),
    'searchvalue' => $query,
    'hiddenfields' => $hidden,
    'groups' => $groups,
    'count' => get_string('catalogue_count', 'local_pshub', $results->total),
    'courses' => $results->courses,
    'hasresults' => (bool) $results->courses,
    'hasfilters' => (bool) $filters,
    'clearurl' => (new moodle_url('/local/pshub/catalogue.php'))->out(false),
    'paging' => $OUTPUT->paging_bar($results->total, $page, $results->perpage,
        new moodle_url($PAGE->url, $filters)),
]);

echo $OUTPUT->footer();
