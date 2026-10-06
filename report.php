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
 * Participation and moderation report for Video Forum.
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
require_capability('mod/videoforum:viewreport', $context);

$PAGE->set_url('/mod/videoforum/report.php', ['id' => $cm->id]);
$PAGE->set_title(get_string('reportactivity', 'videoforum'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);
$PAGE->requires->js_call_amd('mod_videoforum/feed', 'init', [['cmid' => (int)$cm->id]]);

$stats = manager::get_report_stats($activity);
$users = get_enrolled_users(
    $context,
    'mod/videoforum:view',
    0,
    'u.id,u.firstname,u.lastname,u.firstnamephonetic,u.lastnamephonetic,u.middlename,u.alternatename,u.email'
);

$table = new html_table();
$table->head = [
    get_string('author', 'videoforum'),
    get_string('topics', 'videoforum'),
    get_string('totalreplies', 'videoforum'),
    get_string('participations', 'videoforum'),
    get_string('videoswatched', 'videoforum'),
    get_string('lastactivity', 'videoforum'),
];

foreach ($users as $user) {
    $row = $stats[(int)$user->id] ?? [
        'topics' => 0,
        'replies' => 0,
        'participations' => 0,
        'watched' => 0,
        'lastactivity' => 0,
    ];
    $table->data[] = [
        fullname($user),
        $row['topics'],
        $row['replies'],
        $row['participations'],
        $row['watched'],
        $row['lastactivity'] ? userdate($row['lastactivity']) : '-',
    ];
}

$reports = $DB->get_records_sql(
    "SELECT r.*, p.title, p.userid AS postauthorid
       FROM {videoforum_report} r
       JOIN {videoforum_post} p ON p.id = r.postid
      WHERE r.videoforumid = :activityid
        AND r.status = 0
   ORDER BY r.timecreated DESC",
    ['activityid' => $activity->id]
);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('reportactivity', 'videoforum'));
echo html_writer::table($table);

echo $OUTPUT->heading(get_string('moderation', 'videoforum'), 3);
if (!$reports) {
    echo $OUTPUT->notification(get_string('noreports', 'videoforum'), 'notifymessage');
} else {
    foreach ($reports as $report) {
        $reporter = $DB->get_record('user', ['id' => $report->userid], '*', MUST_EXIST);
        $postauthor = $DB->get_record('user', ['id' => $report->postauthorid], '*', MUST_EXIST);

        echo html_writer::start_div('card mb-3');
        echo html_writer::start_div('card-body');
        echo html_writer::tag('h4', format_string($report->title ?: get_string('modulename', 'videoforum')));
        echo html_writer::tag('p',
            s(fullname($reporter)) . ' → ' . s(fullname($postauthor)) . ' · ' .
            s(get_string('reason:' . $report->reason, 'videoforum')) . ' · ' .
            s(userdate($report->timecreated))
        );
        if (trim((string)$report->details) !== '') {
            echo html_writer::tag('p', s($report->details));
        }

        foreach (['hide', 'delete'] as $action) {
            echo html_writer::tag('button', get_string($action, 'videoforum'), [
                'type' => 'button',
                'class' => 'btn btn-sm btn-outline-secondary me-2',
                'data-action' => 'moderate',
                'data-moderation' => $action,
                'data-postid' => (int)$report->postid,
            ]);
        }
        echo html_writer::end_div();
        echo html_writer::end_div();
    }
}

echo $OUTPUT->footer();
