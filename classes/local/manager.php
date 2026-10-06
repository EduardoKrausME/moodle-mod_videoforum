<?php
// This file is part of Moodle - http://moodle.org/.

namespace mod_videoforum\local;

use completion_info;
use context_module;
use moodle_exception;
use moodle_url;
use stdClass;

/**
 * Domain rules for Video Forum.
 *
 * Browser supplied ids are treated as selectors only. Every rule reloads the
 * canonical activity, post, group and user ownership from Moodle data.
 *
 * @package mod_videoforum
 */
class manager {
    /**
     * Resolve and validate a Video Forum runtime from a course-module id.
     *
     * @param int $cmid Course-module id.
     * @return array{0:stdClass,1:stdClass,2:stdClass,3:context_module}
     */
    public static function require_runtime(int $cmid): array {
        global $DB;

        $cm = get_coursemodule_from_id('videoforum', $cmid, 0, false, MUST_EXIST);
        $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
        $activity = $DB->get_record('videoforum', ['id' => $cm->instance], '*', MUST_EXIST);
        $context = context_module::instance($cm->id);

        require_login($course, true, $cm);
        require_capability('mod/videoforum:view', $context);

        return [$cm, $course, $activity, $context];
    }

    /**
     * Load a post only inside the expected activity.
     */
    public static function get_post(int $activityid, int $postid): stdClass {
        global $DB;

        $post = $DB->get_record('videoforum_post', [
            'id' => $postid,
            'videoforumid' => $activityid,
        ]);
        if (!$post) {
            throw new moodle_exception('invalidpost', 'videoforum');
        }
        return $post;
    }

    /**
     * Whether a user can see a post and therefore its File API media.
     */
    public static function can_view_post(stdClass $cm, stdClass $activity, stdClass $post, int $userid): bool {
        global $DB;

        $context = context_module::instance($cm->id);
        $moderator = has_capability('mod/videoforum:moderate', $context, $userid);

        if (!empty($post->deleted)) {
            return false;
        }
        if (!empty($post->hidden) && !$moderator) {
            return false;
        }
        if (!self::can_access_group($cm, (int)$post->groupid, $userid)) {
            return false;
        }

        // Root topics are always visible once their activity/group is visible.
        if ((int)$post->rootid === 0 || empty($activity->postbeforeview) || $moderator) {
            return true;
        }

        if ((int)$post->userid === $userid) {
            return true;
        }

        $rootid = (int)$post->rootid;
        return $DB->record_exists_select(
            'videoforum_post',
            'videoforumid = :activityid
             AND userid = :userid
             AND deleted = 0
             AND (id = :rootself OR rootid = :rootid)',
            [
                'activityid' => $activity->id,
                'userid' => $userid,
                'rootself' => $rootid,
                'rootid' => $rootid,
            ]
        );
    }

    /**
     * Check Moodle group visibility.
     */
    public static function can_access_group(stdClass $cm, int $groupid, int $userid): bool {
        if ($groupid === 0 || (int)$cm->groupmode === NOGROUPS) {
            return true;
        }

        $context = context_module::instance($cm->id);
        if (has_capability('moodle/site:accessallgroups', $context, $userid)) {
            return true;
        }
        if ((int)$cm->groupmode === VISIBLEGROUPS) {
            return true;
        }

        return groups_is_member($groupid, $userid);
    }

    /**
     * Resolve the group for a new root topic on the server.
     */
    private static function resolve_topic_group(stdClass $cm, int $userid): int {
        if ((int)$cm->groupmode === NOGROUPS) {
            return 0;
        }

        $context = context_module::instance($cm->id);
        $groupid = (int)groups_get_activity_group($cm, true);

        if ($groupid === 0) {
            if ((int)$cm->groupmode === SEPARATEGROUPS
                    && !has_capability('moodle/site:accessallgroups', $context, $userid)) {
                throw new moodle_exception('cannotcreatetopic', 'videoforum');
            }
            return 0;
        }

        if (!groups_is_member($groupid, $userid)
                && !has_capability('moodle/site:accessallgroups', $context, $userid)) {
            throw new moodle_exception('cannotcreatetopic', 'videoforum');
        }

        return $groupid;
    }

    /**
     * Validate publication permission and return canonical target data.
     *
     * @return array{parent:?stdClass,root:?stdClass,groupid:int}
     */
    public static function assert_can_publish(stdClass $cm, stdClass $activity, int $parentid, int $userid): array {
        global $DB;

        $context = context_module::instance($cm->id);
        $moderator = has_capability('mod/videoforum:moderate', $context, $userid);

        if ($parentid <= 0) {
            require_capability('mod/videoforum:createtopic', $context, $userid);
            if (empty($activity->allowtopics) && !$moderator) {
                throw new moodle_exception('cannotcreatetopic', 'videoforum');
            }
            if (!empty($activity->topicdeadline) && time() > (int)$activity->topicdeadline && !$moderator) {
                throw new moodle_exception('topicdeadlinepassed', 'videoforum');
            }
            return [
                'parent' => null,
                'root' => null,
                'groupid' => self::resolve_topic_group($cm, $userid),
            ];
        }

        require_capability('mod/videoforum:reply', $context, $userid);
        if (empty($activity->allowreplies) && !$moderator) {
            throw new moodle_exception('cannotreply', 'videoforum');
        }
        if (!empty($activity->replydeadline) && time() > (int)$activity->replydeadline && !$moderator) {
            throw new moodle_exception('replydeadlinepassed', 'videoforum');
        }

        $parent = self::get_post((int)$activity->id, $parentid);
        if (!self::can_view_post($cm, $activity, $parent, $userid)) {
            throw new moodle_exception('cannotreply', 'videoforum');
        }

        $rootid = (int)$parent->rootid > 0 ? (int)$parent->rootid : (int)$parent->id;
        $root = self::get_post((int)$activity->id, $rootid);

        if (!empty($root->locked) && !$moderator) {
            throw new moodle_exception('locked', 'videoforum');
        }

        if (!empty($activity->maxreplies) && !$moderator) {
            $replycount = $DB->count_records('videoforum_post', [
                'videoforumid' => $activity->id,
                'rootid' => $rootid,
                'deleted' => 0,
            ]);
            if ($replycount >= (int)$activity->maxreplies) {
                throw new moodle_exception('replylimitreached', 'videoforum');
            }
        }

        if (!self::can_access_group($cm, (int)$root->groupid, $userid)) {
            throw new moodle_exception('cannotreply', 'videoforum');
        }

        return ['parent' => $parent, 'root' => $root, 'groupid' => (int)$root->groupid];
    }

    /**
     * Create a topic/reply from a server-bound Moodle draft item.
     */
    public static function create_post(
        stdClass $cm,
        stdClass $course,
        stdClass $activity,
        context_module $context,
        int $userid,
        int $draftitemid,
        int $parentid,
        string $title,
        string $description
    ): stdClass {
        global $CFG, $DB;

        $target = self::assert_can_publish($cm, $activity, $parentid, $userid);

        $draft = $DB->get_record('videoforum_draft', [
            'videoforumid' => $activity->id,
            'userid' => $userid,
            'draftitemid' => $draftitemid,
            'parentid' => $parentid,
        ]);
        if (!$draft || (int)$draft->timecreated < time() - DAYSECS) {
            throw new moodle_exception('invaliddraft', 'videoforum');
        }
        if ((float)$draft->duration <= 0 || (float)$draft->duration > (int)$activity->maxduration + 0.5) {
            throw new moodle_exception('videotoolong', 'videoforum');
        }

        require_once($CFG->libdir . '/filelib.php');

        $now = time();
        $post = (object)[
            'videoforumid' => (int)$activity->id,
            'userid' => $userid,
            'parentid' => $parentid,
            'rootid' => $target['root'] ? (int)$target['root']->id : 0,
            'groupid' => (int)$target['groupid'],
            'title' => core_text::substr(trim($title), 0, 120),
            'description' => core_text::substr(trim($description), 0, 500),
            'duration' => (float)$draft->duration,
            'hidden' => 0,
            'deleted' => 0,
            'locked' => 0,
            'timecreated' => $now,
            'timemodified' => $now,
        ];

        $transaction = $DB->start_delegated_transaction();
        $post->id = $DB->insert_record('videoforum_post', $post);

        file_save_draft_area_files(
            $draftitemid,
            $context->id,
            'mod_videoforum',
            'postvideo',
            $post->id,
            [
                'subdirs' => 0,
                'maxfiles' => 1,
                'accepted_types' => ['.mp4', '.webm'],
            ]
        );
        $DB->delete_records('videoforum_draft', ['id' => $draft->id]);
        $transaction->allow_commit();

        self::refresh_user_state($cm, $course, $activity, $userid);

        \mod_videoforum\event\post_created::create([
            'context' => $context,
            'objectid' => $post->id,
            'relateduserid' => $userid,
            'other' => [
                'parentid' => $parentid,
                'rootid' => (int)$post->rootid,
            ],
        ])->trigger();

        return $post;
    }

    /**
     * Moderate a publication.
     */
    public static function moderate(
        stdClass $cm,
        stdClass $course,
        stdClass $activity,
        context_module $context,
        int $postid,
        string $action
    ): void {
        global $DB, $USER;

        require_capability('mod/videoforum:moderate', $context);
        $post = self::get_post((int)$activity->id, $postid);

        $allowed = ['hide', 'restore', 'delete', 'lock', 'unlock'];
        if (!in_array($action, $allowed, true)) {
            throw new moodle_exception('invaliddata', 'error');
        }

        $affectedusers = [(int)$post->userid];
        if ($action === 'hide' || $action === 'restore') {
            $post->hidden = $action === 'hide' ? 1 : 0;
            $post->timemodified = time();
            $DB->update_record('videoforum_post', $post);
        } else if ($action === 'lock' || $action === 'unlock') {
            $rootid = (int)$post->rootid > 0 ? (int)$post->rootid : (int)$post->id;
            $root = self::get_post((int)$activity->id, $rootid);
            $root->locked = $action === 'lock' ? 1 : 0;
            $root->timemodified = time();
            $DB->update_record('videoforum_post', $root);
            $post = $root;
        } else {
            if ((int)$post->rootid === 0) {
                $threadposts = $DB->get_records_select(
                    'videoforum_post',
                    'videoforumid = :activityid AND (id = :rootid OR rootid = :rootid2)',
                    [
                        'activityid' => $activity->id,
                        'rootid' => $post->id,
                        'rootid2' => $post->id,
                    ]
                );
            } else {
                $threadposts = [$post->id => $post];
            }

            $fs = get_file_storage();
            foreach ($threadposts as $threadpost) {
                $threadpost->deleted = 1;
                $threadpost->hidden = 1;
                $threadpost->timemodified = time();
                $DB->update_record('videoforum_post', $threadpost);
                $fs->delete_area_files($context->id, 'mod_videoforum', 'postvideo', $threadpost->id);
                $affectedusers[] = (int)$threadpost->userid;
            }
        }

        $DB->set_field_select(
            'videoforum_report',
            'status',
            1,
            'videoforumid = :activityid AND postid = :postid AND status = 0',
            ['activityid' => $activity->id, 'postid' => $postid]
        );
        $DB->set_field_select(
            'videoforum_report',
            'reviewedby',
            (int)$USER->id,
            'videoforumid = :activityid AND postid = :postid AND reviewedby = 0',
            ['activityid' => $activity->id, 'postid' => $postid]
        );
        $DB->set_field_select(
            'videoforum_report',
            'timereviewed',
            time(),
            'videoforumid = :activityid AND postid = :postid AND timereviewed = 0',
            ['activityid' => $activity->id, 'postid' => $postid]
        );

        foreach (array_unique($affectedusers) as $userid) {
            self::refresh_user_state($cm, $course, $activity, $userid);
        }

        \mod_videoforum\event\post_moderated::create([
            'context' => $context,
            'objectid' => $postid,
            'other' => ['action' => $action],
        ])->trigger();
    }

    /**
     * Report a visible post.
     */
    public static function report(
        stdClass $cm,
        stdClass $activity,
        context_module $context,
        int $userid,
        int $postid,
        string $reason,
        string $details
    ): int {
        global $DB;

        require_capability('mod/videoforum:reportpost', $context, $userid);
        $post = self::get_post((int)$activity->id, $postid);
        if (!self::can_view_post($cm, $activity, $post, $userid)) {
            throw new moodle_exception('cannotviewpost', 'videoforum');
        }
        if ((int)$post->userid === $userid) {
            throw new moodle_exception('cannotreportown', 'videoforum');
        }
        if ($DB->record_exists('videoforum_report', ['postid' => $postid, 'userid' => $userid])) {
            throw new moodle_exception('alreadyreported', 'videoforum');
        }

        $allowedreasons = ['inappropriate', 'spam', 'privacy', 'other'];
        $reason = in_array($reason, $allowedreasons, true) ? $reason : 'other';
        $id = $DB->insert_record('videoforum_report', (object)[
            'videoforumid' => $activity->id,
            'postid' => $postid,
            'userid' => $userid,
            'reason' => $reason,
            'details' => core_text::substr(trim($details), 0, 1000),
            'status' => 0,
            'reviewedby' => 0,
            'timecreated' => time(),
            'timereviewed' => 0,
        ]);

        \mod_videoforum\event\post_reported::create([
            'context' => $context,
            'objectid' => $id,
            'relateduserid' => (int)$post->userid,
            'other' => ['postid' => $postid, 'reason' => $reason],
        ])->trigger();

        return (int)$id;
    }

    /**
     * Update bounded viewing state.
     */
    public static function track_view(
        stdClass $cm,
        stdClass $activity,
        int $userid,
        int $postid,
        float $position,
        bool $ended
    ): bool {
        global $DB;

        $post = self::get_post((int)$activity->id, $postid);
        if (!self::can_view_post($cm, $activity, $post, $userid)) {
            throw new moodle_exception('cannotviewpost', 'videoforum');
        }

        $duration = max(0.0, (float)$post->duration);
        $position = max(0.0, min($duration, $position));
        $complete = $ended && $duration > 0 && $position >= max(0.0, $duration - 2.0);

        $record = $DB->get_record('videoforum_view', ['postid' => $postid, 'userid' => $userid]);
        if ($record) {
            $record->lastposition = max((float)$record->lastposition, $position);
            $record->completed = !empty($record->completed) || $complete ? 1 : 0;
            $record->timemodified = time();
            $DB->update_record('videoforum_view', $record);
        } else {
            $now = time();
            $DB->insert_record('videoforum_view', (object)[
                'videoforumid' => $activity->id,
                'postid' => $postid,
                'userid' => $userid,
                'lastposition' => $position,
                'completed' => $complete ? 1 : 0,
                'timecreated' => $now,
                'timemodified' => $now,
            ]);
        }

        return $complete;
    }

    /**
     * Count a learner's publications by type.
     *
     * @return array{topics:int,replies:int,total:int}
     */
    public static function count_user_posts(int $activityid, int $userid): array {
        global $DB;

        $topics = $DB->count_records_select(
            'videoforum_post',
            'videoforumid = :activityid AND userid = :userid AND deleted = 0 AND rootid = 0',
            ['activityid' => $activityid, 'userid' => $userid]
        );
        $replies = $DB->count_records_select(
            'videoforum_post',
            'videoforumid = :activityid AND userid = :userid AND deleted = 0 AND rootid <> 0',
            ['activityid' => $activityid, 'userid' => $userid]
        );

        return ['topics' => $topics, 'replies' => $replies, 'total' => $topics + $replies];
    }

    /**
     * Build the visible root feed.
     */
    public static function get_feed(stdClass $cm, stdClass $activity, int $userid): array {
        global $DB;

        $posts = $DB->get_records('videoforum_post', [
            'videoforumid' => $activity->id,
            'rootid' => 0,
            'deleted' => 0,
        ], 'timecreated DESC');

        $result = [];
        foreach ($posts as $post) {
            if (self::can_view_post($cm, $activity, $post, $userid)) {
                $result[] = self::to_template($cm, $activity, $post, $userid);
            }
        }
        return $result;
    }

    /**
     * Build one visible thread.
     */
    public static function get_thread(stdClass $cm, stdClass $activity, int $rootid, int $userid): array {
        global $DB;

        $root = self::get_post((int)$activity->id, $rootid);
        if ((int)$root->rootid !== 0 || !self::can_view_post($cm, $activity, $root, $userid)) {
            throw new moodle_exception('cannotviewpost', 'videoforum');
        }

        $rows = $DB->get_records_select(
            'videoforum_post',
            'videoforumid = :activityid AND (id = :rootid OR rootid = :rootid2) AND deleted = 0',
            ['activityid' => $activity->id, 'rootid' => $rootid, 'rootid2' => $rootid],
            'timecreated ASC'
        );

        $visible = [];
        foreach ($rows as $row) {
            if (self::can_view_post($cm, $activity, $row, $userid)) {
                $visible[] = self::to_template($cm, $activity, $row, $userid);
            }
        }
        return $visible;
    }

    /**
     * Convert a post into Mustache data.
     */
    public static function to_template(stdClass $cm, stdClass $activity, stdClass $post, int $viewerid): array {
        global $DB, $OUTPUT;

        static $usercache = [];
        $context = context_module::instance($cm->id);

        if (!isset($usercache[$post->userid])) {
            $usercache[$post->userid] = $DB->get_record('user', ['id' => $post->userid], '*', MUST_EXIST);
        }
        $author = $usercache[$post->userid];

        $fs = get_file_storage();
        $files = $fs->get_area_files(
            $context->id,
            'mod_videoforum',
            'postvideo',
            $post->id,
            'id',
            false
        );
        $videourl = '';
        if ($files) {
            $file = reset($files);
            $videourl = moodle_url::make_pluginfile_url(
                $context->id,
                'mod_videoforum',
                'postvideo',
                $post->id,
                $file->get_filepath(),
                $file->get_filename(),
                false
            )->out(false);
        }

        $replycount = (int)$DB->count_records('videoforum_post', [
            'videoforumid' => $activity->id,
            'rootid' => (int)$post->id,
            'deleted' => 0,
        ]);

        $moderator = has_capability('mod/videoforum:moderate', $context, $viewerid);
        return [
            'id' => (int)$post->id,
            'parentid' => (int)$post->parentid,
            'rootid' => (int)$post->rootid,
            'isroot' => (int)$post->rootid === 0,
            'title' => format_string($post->title),
            'description' => format_text($post->description, FORMAT_PLAIN),
            'author' => fullname($author),
            'avatar' => $OUTPUT->user_picture($author, ['size' => 72, 'class' => 'videoforum-avatar']),
            'date' => userdate((int)$post->timecreated),
            'duration' => format_time((float)$post->duration),
            'videourl' => $videourl,
            'replycount' => $replycount,
            'replylabel' => get_string('replies', 'videoforum', $replycount),
            'threadurl' => (new moodle_url('/mod/videoforum/thread.php', [
                'id' => $cm->id,
                'postid' => (int)$post->id,
            ]))->out(false),
            'canreport' => has_capability('mod/videoforum:reportpost', $context, $viewerid)
                && (int)$post->userid !== $viewerid,
            'canmoderate' => $moderator,
            'hidden' => !empty($post->hidden),
            'locked' => !empty($post->locked),
        ];
    }

    /**
     * Participation statistics for the report.
     */
    public static function get_report_stats(stdClass $activity): array {
        global $DB;

        $rows = $DB->get_records_sql(
            "SELECT userid,
                    SUM(CASE WHEN rootid = 0 THEN 1 ELSE 0 END) AS topics,
                    SUM(CASE WHEN rootid <> 0 THEN 1 ELSE 0 END) AS replies,
                    COUNT(1) AS participations,
                    MAX(timecreated) AS lastpost
               FROM {videoforum_post}
              WHERE videoforumid = :activityid
                AND deleted = 0
           GROUP BY userid",
            ['activityid' => $activity->id]
        );

        $views = $DB->get_records_sql(
            "SELECT userid, COUNT(1) AS watched, MAX(timemodified) AS lastview
               FROM {videoforum_view}
              WHERE videoforumid = :activityid
                AND completed = 1
           GROUP BY userid",
            ['activityid' => $activity->id]
        );

        $result = [];
        foreach ($rows as $userid => $row) {
            $result[(int)$userid] = [
                'topics' => (int)$row->topics,
                'replies' => (int)$row->replies,
                'participations' => (int)$row->participations,
                'watched' => 0,
                'lastactivity' => (int)$row->lastpost,
            ];
        }
        foreach ($views as $userid => $row) {
            if (!isset($result[(int)$userid])) {
                $result[(int)$userid] = [
                    'topics' => 0,
                    'replies' => 0,
                    'participations' => 0,
                    'watched' => 0,
                    'lastactivity' => 0,
                ];
            }
            $result[(int)$userid]['watched'] = (int)$row->watched;
            $result[(int)$userid]['lastactivity'] = max(
                $result[(int)$userid]['lastactivity'],
                (int)$row->lastview
            );
        }

        return $result;
    }

    /**
     * Recalculate completion and grade after publication changes.
     */
    private static function refresh_user_state(
        stdClass $cm,
        stdClass $course,
        stdClass $activity,
        int $userid
    ): void {
        global $CFG;

        require_once($CFG->dirroot . '/mod/videoforum/lib.php');
        \videoforum_update_grades($activity, $userid, true);

        $completion = new completion_info($course);
        if ($completion->is_enabled($cm)) {
            $completion->update_state($cm, COMPLETION_UNKNOWN, $userid);
        }
    }
}
