<?php
// This file is part of Moodle - http://moodle.org/.

require_once('../../config.php');

use mod_videoforum\local\manager;

$id = required_param('id', PARAM_INT);
$postid = required_param('postid', PARAM_INT);
[$cm, $course, $activity, $context] = manager::require_runtime($id);

$root = manager::get_post((int)$activity->id, $postid);
if ((int)$root->rootid !== 0 || !manager::can_view_post($cm, $activity, $root, (int)$USER->id)) {
    throw new moodle_exception('cannotviewpost', 'videoforum');
}

$posts = manager::get_thread($cm, $activity, $postid, (int)$USER->id);

$canreply = false;
try {
    manager::assert_can_publish($cm, $activity, $postid, (int)$USER->id);
    $canreply = true;
} catch (moodle_exception $exception) {
    $canreply = false;
}

foreach ($posts as &$post) {
    $post['canreply'] = $canreply;
}
unset($post);

$hascontributed = $DB->record_exists_select(
    'videoforum_post',
    'videoforumid = :activityid
     AND userid = :userid
     AND deleted = 0
     AND rootid = :rootid',
    [
        'activityid' => $activity->id,
        'userid' => $USER->id,
        'rootid' => $postid,
    ]
);

$PAGE->set_url('/mod/videoforum/thread.php', ['id' => $cm->id, 'postid' => $postid]);
$PAGE->set_title(format_string($activity->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$PAGE->requires->js_call_amd('mod_videoforum/feed', 'init', [[
    'cmid' => (int)$cm->id,
]]);
if ($canreply) {
    $PAGE->requires->js_call_amd('mod_videoforum/recorder', 'init', [[
        'cmid' => (int)$cm->id,
        'maxduration' => (int)$activity->maxduration,
    ]]);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($activity->name));

echo $OUTPUT->render_from_template('mod_videoforum/thread', [
    'cmid' => (int)$cm->id,
    'posts' => $posts,
    'canreply' => $canreply,
    'parentid' => $postid,
    'maxduration' => (int)$activity->maxduration,
    'allowupload' => !empty($activity->allowupload),
    'allowrecording' => !empty($activity->allowrecording),
    'blockedreplies' => !empty($activity->postbeforeview)
        && !$hascontributed
        && (int)$root->userid !== (int)$USER->id,
    'locked' => !empty($root->locked),
]);

echo $OUTPUT->footer();
