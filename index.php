<?php
// This file is part of Moodle - http://moodle.org/.

require_once('../../config.php');

$id = required_param('id', PARAM_INT);
$course = get_course($id);
require_course_login($course);

$PAGE->set_url('/mod/videoforum/index.php', ['id' => $course->id]);
$PAGE->set_title(get_string('modulenameplural', 'videoforum'));
$PAGE->set_heading(format_string($course->fullname));

$instances = get_all_instances_in_course('videoforum', $course);

$table = new html_table();
$table->head = [
    get_string('name'),
    get_string('description'),
];

foreach ($instances as $instance) {
    $table->data[] = [
        html_writer::link(
            new moodle_url('/mod/videoforum/view.php', ['id' => $instance->coursemodule]),
            format_string($instance->name)
        ),
        format_text($instance->intro, $instance->introformat),
    ];
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('modulenameplural', 'videoforum'));
echo html_writer::table($table);
echo $OUTPUT->footer();
