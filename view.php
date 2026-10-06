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
 * Main Video Forum feed page.
 *
 * @package    mod_videoforum
 * @copyright  2026 Eduardo Kraus
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_login();

use mod_videoforum\local\manager;

$id = required_param('id', PARAM_INT);
[$cm, $course, $activity, $context] = manager::require_runtime($id);

$PAGE->set_url('/mod/videoforum/view.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($activity->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$posts = manager::get_feed($cm, $activity, (int)$USER->id);

$cancreate = has_capability('mod/videoforum:createtopic', $context)
    && (!empty($activity->allowtopics) || has_capability('mod/videoforum:moderate', $context))
    && (empty($activity->topicdeadline)
        || time() <= (int)$activity->topicdeadline
        || has_capability('mod/videoforum:moderate', $context));

$data = [
    'cmid' => (int)$cm->id,
    'posts' => $posts,
    'hasposts' => !empty($posts),
    'cancreate' => $cancreate,
    'maxduration' => (int)$activity->maxduration,
    'allowupload' => !empty($activity->allowupload),
    'allowrecording' => !empty($activity->allowrecording),
    'parentid' => 0,
    'reporturl' => (new moodle_url('/mod/videoforum/report.php', ['id' => $cm->id]))->out(false),
    'canviewreport' => has_capability('mod/videoforum:viewreport', $context),
];

$PAGE->requires->js_call_amd('mod_videoforum/feed', 'init', [[
    'cmid' => (int)$cm->id,
]]);
if ($cancreate) {
    $PAGE->requires->js_call_amd('mod_videoforum/recorder', 'init', [[
        'cmid' => (int)$cm->id,
        'maxduration' => (int)$activity->maxduration,
    ]]);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($activity->name));

if (trim((string)$activity->intro) !== '') {
    echo $OUTPUT->box(format_module_intro('videoforum', $activity, $cm->id), 'generalbox mod_introbox');
}

echo $OUTPUT->render_from_template('mod_videoforum/feed', $data);
echo $OUTPUT->footer();
