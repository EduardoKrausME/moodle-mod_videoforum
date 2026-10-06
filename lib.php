<?php
// This file is part of Moodle - http://moodle.org/.

use mod_videoforum\local\manager;

/**
 * Core callbacks for Video Forum.
 *
 * @package mod_videoforum
 */

function videoforum_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_ARCHETYPE:
            return MOD_ARCHETYPE_OTHER;
        case FEATURE_GROUPS:
        case FEATURE_GROUPINGS:
        case FEATURE_MOD_INTRO:
        case FEATURE_COMPLETION_HAS_RULES:
        case FEATURE_GRADE_HAS_GRADE:
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_MOD_PURPOSE:
            return MOD_PURPOSE_COLLABORATION;
        default:
            return null;
    }
}

function videoforum_add_instance(stdClass $data, ?mod_videoforum_mod_form $mform = null): int {
    global $DB;

    $data->timecreated = time();
    $data->timemodified = $data->timecreated;
    $data->id = $DB->insert_record('videoforum', $data);

    videoforum_grade_item_update($data);
    return (int)$data->id;
}

function videoforum_update_instance(stdClass $data, ?mod_videoforum_mod_form $mform = null): bool {
    global $DB;

    $data->id = $data->instance;
    $data->timemodified = time();

    $result = $DB->update_record('videoforum', $data);
    videoforum_grade_item_update($data);
    videoforum_update_grades($data);
    return $result;
}

function videoforum_delete_instance(int $id): bool {
    global $DB;

    $activity = $DB->get_record('videoforum', ['id' => $id]);
    if (!$activity) {
        return false;
    }

    $cm = get_coursemodule_from_instance('videoforum', $id, $activity->course, false, IGNORE_MISSING);
    if ($cm) {
        $fs = get_file_storage();
        $fs->delete_area_files(context_module::instance($cm->id)->id, 'mod_videoforum');
    }

    $transaction = $DB->start_delegated_transaction();
    $DB->delete_records('videoforum_view', ['videoforumid' => $id]);
    $DB->delete_records('videoforum_report', ['videoforumid' => $id]);
    $DB->delete_records('videoforum_draft', ['videoforumid' => $id]);
    $DB->delete_records('videoforum_post', ['videoforumid' => $id]);
    $DB->delete_records('videoforum', ['id' => $id]);
    $transaction->allow_commit();

    videoforum_grade_item_delete($activity);
    return true;
}

function videoforum_grade_item_update(stdClass $activity, ?array $grades = null): int {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    $maximum = max(0.0, (float)$activity->grade);
    $item = [
        'itemname' => clean_param($activity->name, PARAM_NOTAGS),
        'gradetype' => $maximum > 0 ? GRADE_TYPE_VALUE : GRADE_TYPE_NONE,
        'grademin' => 0,
        'grademax' => max(1.0, $maximum),
    ];

    return grade_update(
        'mod/videoforum',
        $activity->course,
        'mod',
        'videoforum',
        $activity->id,
        0,
        $grades,
        $item
    );
}

function videoforum_update_grades(stdClass $activity, int $userid = 0, bool $nullifnone = true): void {
    global $DB;

    if ((float)$activity->grade <= 0) {
        videoforum_grade_item_update($activity);
        return;
    }

    $params = ['activityid' => $activity->id];
    $usersql = '';
    if ($userid > 0) {
        $usersql = ' AND userid = :userid';
        $params['userid'] = $userid;
    }

    $rows = $DB->get_records_sql(
        "SELECT userid, COUNT(1) AS publicationcount
           FROM {videoforum_post}
          WHERE videoforumid = :activityid
            AND deleted = 0{$usersql}
       GROUP BY userid",
        $params
    );

    $target = max(1, (int)$activity->gradetarget);
    $maximum = (float)$activity->grade;
    $grades = [];
    foreach ($rows as $row) {
        $grades[(int)$row->userid] = (object)[
            'userid' => (int)$row->userid,
            'rawgrade' => min($maximum, ((int)$row->publicationcount / $target) * $maximum),
        ];
    }

    if ($userid > 0 && !$grades && $nullifnone) {
        $grades[$userid] = (object)['userid' => $userid, 'rawgrade' => null];
    }

    videoforum_grade_item_update($activity, $grades);
}

function videoforum_grade_item_delete(stdClass $activity): int {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    return grade_update(
        'mod/videoforum',
        $activity->course,
        'mod',
        'videoforum',
        $activity->id,
        0,
        null,
        ['deleted' => 1]
    );
}

function videoforum_get_coursemodule_info(stdClass $cm): ?cached_cm_info {
    global $DB;

    $activity = $DB->get_record('videoforum', ['id' => $cm->instance]);
    if (!$activity) {
        return null;
    }

    $info = new cached_cm_info();
    $info->name = $activity->name;
    if ($cm->showdescription) {
        $info->content = format_module_intro('videoforum', $activity, $cm->id, false);
    }
    if ((int)$cm->completion === COMPLETION_TRACKING_AUTOMATIC) {
        $info->customdata['customcompletionrules'] = [
            'completiontopics' => (int)$activity->completiontopics,
            'completionreplies' => (int)$activity->completionreplies,
            'completionparticipations' => (int)$activity->completionparticipations,
        ];
    }
    return $info;
}

function videoforum_get_completion_active_rule_descriptions(cached_cm_info $cm): array {
    if ((int)$cm->completion !== COMPLETION_TRACKING_AUTOMATIC) {
        return [];
    }
    $rules = $cm->customdata['customcompletionrules'] ?? [];
    $descriptions = [];
    foreach ([
        'completiontopics' => 'completiondetail:topics',
        'completionreplies' => 'completiondetail:replies',
        'completionparticipations' => 'completiondetail:participations',
    ] as $field => $string) {
        if (!empty($rules[$field])) {
            $descriptions[] = get_string($string, 'videoforum', (int)$rules[$field]);
        }
    }
    return $descriptions;
}

function videoforum_pluginfile(
    $course,
    $cm,
    $context,
    string $filearea,
    array $args,
    bool $forcedownload,
    array $options = []
): void {
    global $DB, $USER;

    if ($context->contextlevel !== CONTEXT_MODULE || $filearea !== 'postvideo') {
        send_file_not_found();
    }

    require_login($course, true, $cm);
    require_capability('mod/videoforum:view', $context);

    $postid = (int)array_shift($args);
    if ($postid <= 0) {
        send_file_not_found();
    }

    $activity = $DB->get_record('videoforum', ['id' => $cm->instance], '*', MUST_EXIST);
    $post = manager::get_post((int)$activity->id, $postid);
    if (!manager::can_view_post($cm, $activity, $post, (int)$USER->id)) {
        send_file_not_found();
    }

    $filename = array_pop($args);
    $filepath = '/' . ($args ? implode('/', $args) . '/' : '');
    $file = get_file_storage()->get_file(
        $context->id,
        'mod_videoforum',
        'postvideo',
        $postid,
        $filepath,
        $filename
    );
    if (!$file || $file->is_directory()) {
        send_file_not_found();
    }

    send_stored_file($file, 0, 0, false, $options);
}

function videoforum_reset_course_form_definition(&$mform): void {
    $mform->addElement('header', 'videoforumheader', get_string('modulenameplural', 'videoforum'));
    $mform->addElement('advcheckbox', 'reset_videoforum', get_string('resetvideoforum', 'videoforum'));
}

function videoforum_reset_course_form_defaults($course): array {
    return ['reset_videoforum' => 1];
}

function videoforum_reset_userdata($data): array {
    global $CFG, $DB;

    if (empty($data->reset_videoforum)) {
        return [];
    }

    $activities = $DB->get_records('videoforum', ['course' => $data->courseid]);
    if (!$activities) {
        return [];
    }

    require_once($CFG->libdir . '/gradelib.php');
    $completion = new completion_info(get_course($data->courseid));
    $modinfo = get_fast_modinfo($data->courseid);

    foreach ($activities as $activity) {
        $cm = get_coursemodule_from_instance('videoforum', $activity->id, $data->courseid, false, IGNORE_MISSING);
        if ($cm) {
            get_file_storage()->delete_area_files(
                context_module::instance($cm->id)->id,
                'mod_videoforum',
                'postvideo'
            );
        }

        $DB->delete_records('videoforum_view', ['videoforumid' => $activity->id]);
        $DB->delete_records('videoforum_report', ['videoforumid' => $activity->id]);
        $DB->delete_records('videoforum_draft', ['videoforumid' => $activity->id]);
        $DB->delete_records('videoforum_post', ['videoforumid' => $activity->id]);

        grade_update(
            'mod/videoforum',
            $activity->course,
            'mod',
            'videoforum',
            $activity->id,
            0,
            null,
            ['reset' => true]
        );

        if ($cm && isset($modinfo->cms[$cm->id])) {
            $completion->reset_all_state($modinfo->get_cm($cm->id));
        }
    }

    return [[
        'component' => get_string('modulenameplural', 'videoforum'),
        'item' => get_string('resetvideoforumstatus', 'videoforum'),
        'error' => false,
    ]];
}

function videoforum_extend_settings_navigation(settings_navigation $settings, navigation_node $node): void {
    global $PAGE;

    if (!$PAGE->cm || $PAGE->cm->modname !== 'videoforum') {
        return;
    }
    if (has_capability('mod/videoforum:viewreport', $PAGE->context)) {
        $node->add(
            get_string('reportactivity', 'videoforum'),
            new moodle_url('/mod/videoforum/report.php', ['id' => $PAGE->cm->id]),
            navigation_node::TYPE_SETTING
        );
    }
}
