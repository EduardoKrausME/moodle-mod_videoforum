<?php
// This file is part of Moodle - http://moodle.org/.

require_once('../../config.php');

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
