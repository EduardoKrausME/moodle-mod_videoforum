<?php
// This file is part of Moodle - http://moodle.org/.

defined('MOODLE_INTERNAL') || die();

/**
 * Data generator for mod_videoforum.
 *
 * @package mod_videoforum
 */
class mod_videoforum_generator extends testing_module_generator {
    public function create_instance($record = null, ?array $options = null) {
        $record = (object)($record ?? []);
        $record->maxduration = $record->maxduration ?? 120;
        $record->allowtopics = $record->allowtopics ?? 1;
        $record->allowreplies = $record->allowreplies ?? 1;
        $record->maxreplies = $record->maxreplies ?? 0;
        $record->topicdeadline = $record->topicdeadline ?? 0;
        $record->replydeadline = $record->replydeadline ?? 0;
        $record->postbeforeview = $record->postbeforeview ?? 0;
        $record->allowupload = $record->allowupload ?? 1;
        $record->allowrecording = $record->allowrecording ?? 1;
        $record->gradetarget = $record->gradetarget ?? 1;
        $record->completiontopics = $record->completiontopics ?? 0;
        $record->completionreplies = $record->completionreplies ?? 0;
        $record->completionparticipations = $record->completionparticipations ?? 0;
        $record->grade = $record->grade ?? 0;

        return parent::create_instance($record, $options);
    }

    public function create_post($record): stdClass {
        global $DB;

        $record = (object)$record;
        $activityid = (int)($record->videoforumid ?? $record->videoforum ?? 0);
        $userid = (int)($record->userid ?? $record->user ?? 0);

        $activity = $DB->get_record('videoforum', ['id' => $activityid], '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('videoforum', $activity->id, $activity->course, false, MUST_EXIST);
        $context = context_module::instance($cm->id);

        $parentid = (int)($record->parentid ?? 0);
        $rootid = (int)($record->rootid ?? 0);
        if ($parentid && !$rootid) {
            $parent = $DB->get_record('videoforum_post', ['id' => $parentid], '*', MUST_EXIST);
            $rootid = (int)$parent->rootid > 0 ? (int)$parent->rootid : (int)$parent->id;
        }

        $now = time();
        $post = (object)[
            'videoforumid' => $activityid,
            'userid' => $userid,
            'parentid' => $parentid,
            'rootid' => $rootid,
            'groupid' => (int)($record->groupid ?? 0),
            'title' => (string)($record->title ?? 'Generated video post'),
            'description' => (string)($record->description ?? ''),
            'duration' => (float)($record->duration ?? 5),
            'hidden' => (int)($record->hidden ?? 0),
            'deleted' => (int)($record->deleted ?? 0),
            'locked' => (int)($record->locked ?? 0),
            'timecreated' => (int)($record->timecreated ?? $now),
            'timemodified' => (int)($record->timemodified ?? $now),
        ];
        $post->id = $DB->insert_record('videoforum_post', $post);

        if (empty($post->deleted)) {
            $fs = get_file_storage();
            $fs->create_file_from_string([
                'contextid' => $context->id,
                'component' => 'mod_videoforum',
                'filearea' => 'postvideo',
                'itemid' => $post->id,
                'filepath' => '/',
                'filename' => 'generated.webm',
                'mimetype' => 'video/webm',
                'userid' => $userid,
            ], 'generated-video-fixture');
        }

        return $DB->get_record('videoforum_post', ['id' => $post->id], '*', MUST_EXIST);
    }
}
