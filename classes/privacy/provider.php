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
 * Privacy API implementation for Video Forum.
 *
 * @package    mod_videoforum
 * @copyright  2026 Eduardo Kraus
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videoforum\privacy;

use context;
use context_module;
use context_user;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\writer;

/**
 * Privacy API provider.
 *
 * @package mod_videoforum
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider {

    /**
     * Get Metadata.
     *
     * @param collection $collection Parameter.
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('videoforum_post', [
            'userid' => 'privacy:metadata:post:userid',
            'title' => 'privacy:metadata:post:title',
            'description' => 'privacy:metadata:post:description',
            'timecreated' => 'privacy:metadata:post:timecreated',
        ], 'privacy:metadata:post');

        $collection->add_database_table('videoforum_report', [
            'userid' => 'privacy:metadata:report:userid',
            'reason' => 'privacy:metadata:report:reason',
            'details' => 'privacy:metadata:report:details',
            'reviewedby' => 'privacy:metadata:report:reviewedby',
        ], 'privacy:metadata:report');

        $collection->add_database_table('videoforum_view', [
            'userid' => 'privacy:metadata:view:userid',
            'lastposition' => 'privacy:metadata:view:lastposition',
            'completed' => 'privacy:metadata:view:completed',
        ], 'privacy:metadata:view');

        $collection->add_database_table('videoforum_draft', [
            'userid' => 'privacy:metadata:draft:userid',
            'filename' => 'privacy:metadata:draft:filename',
            'mimetype' => 'privacy:metadata:draft:mimetype',
            'duration' => 'privacy:metadata:draft:duration',
        ], 'privacy:metadata:draft');

        return $collection;
    }

    /**
     * Get Contexts For Userid.
     *
     * @param int $userid Parameter.
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $base = "FROM {context} ctx
                  JOIN {course_modules} cm
                    ON cm.id = ctx.instanceid
                   AND ctx.contextlevel = :contextlevel
                  JOIN {modules} m
                    ON m.id = cm.module
                   AND m.name = :modname
                  JOIN {videoforum} v
                    ON v.id = cm.instance";

        foreach ([
            ['table' => 'videoforum_post', 'alias' => 'p'],
            ['table' => 'videoforum_report', 'alias' => 'r'],
            ['table' => 'videoforum_view', 'alias' => 'w'],
            ['table' => 'videoforum_draft', 'alias' => 'd'],
        ] as $source) {
            $contextlist->add_from_sql(
                "SELECT ctx.id
                   {$base}
                   JOIN {{$source['table']}} {$source['alias']}
                     ON {$source['alias']}.videoforumid = v.id
                    AND {$source['alias']}.userid = :userid",
                [
                    'contextlevel' => CONTEXT_MODULE,
                    'modname' => 'videoforum',
                    'userid' => $userid,
                ]
            );
        }

        $contextlist->add_from_sql(
            "SELECT ctx.id
               {$base}
               JOIN {videoforum_report} r
                 ON r.videoforumid = v.id
                AND r.reviewedby = :userid",
            [
                'contextlevel' => CONTEXT_MODULE,
                'modname' => 'videoforum',
                'userid' => $userid,
            ]
        );

        return $contextlist;
    }

    /**
     * Export User Data.
     *
     * @param approved_contextlist $contextlist Parameter.
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $userid = (int)$contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof context_module) {
                continue;
            }
            $cm = get_coursemodule_from_id('videoforum', $context->instanceid, 0, false, IGNORE_MISSING);
            if (!$cm) {
                continue;
            }

            $posts = $DB->get_records('videoforum_post', [
                'videoforumid' => $cm->instance,
                'userid' => $userid,
            ], 'timecreated ASC');
            foreach ($posts as $post) {
                $path = ['posts', (string)$post->id];
                writer::with_context($context)->export_data($path, (object)[
                    'title' => $post->title,
                    'description' => $post->description,
                    'duration' => (float)$post->duration,
                    'parentid' => (int)$post->parentid,
                    'rootid' => (int)$post->rootid,
                    'timecreated' => transform::datetime((int)$post->timecreated),
                ]);
                writer::with_context($context)->export_area_files(
                    $path,
                    'mod_videoforum',
                    'postvideo',
                    $post->id
                );
            }

            $reports = $DB->get_records('videoforum_report', [
                'videoforumid' => $cm->instance,
                'userid' => $userid,
            ]);
            writer::with_context($context)->export_data(['reports'], (object)[
                'items' => array_map(static fn($report): array => [
                    'postid' => (int)$report->postid,
                    'reason' => $report->reason,
                    'details' => $report->details,
                    'timecreated' => transform::datetime((int)$report->timecreated),
                ], array_values($reports)),
            ]);

            $reviews = $DB->get_records('videoforum_report', [
                'videoforumid' => $cm->instance,
                'reviewedby' => $userid,
            ]);
            writer::with_context($context)->export_data(['moderationreviews'], (object)[
                'items' => array_map(static fn($report): array => [
                    'postid' => (int)$report->postid,
                    'status' => (int)$report->status,
                    'timereviewed' => transform::datetime((int)$report->timereviewed),
                ], array_values($reviews)),
            ]);

            $views = $DB->get_records('videoforum_view', [
                'videoforumid' => $cm->instance,
                'userid' => $userid,
            ]);
            writer::with_context($context)->export_data(['views'], (object)[
                'items' => array_map(static fn($view): array => [
                    'postid' => (int)$view->postid,
                    'lastposition' => (float)$view->lastposition,
                    'completed' => (bool)$view->completed,
                    'timemodified' => transform::datetime((int)$view->timemodified),
                ], array_values($views)),
            ]);

            $drafts = $DB->get_records('videoforum_draft', [
                'videoforumid' => $cm->instance,
                'userid' => $userid,
            ]);
            writer::with_context($context)->export_data(['drafts'], (object)[
                'items' => array_map(static fn($draft): array => [
                    'filename' => $draft->filename,
                    'mimetype' => $draft->mimetype,
                    'duration' => (float)$draft->duration,
                    'parentid' => (int)$draft->parentid,
                    'timecreated' => transform::datetime((int)$draft->timecreated),
                ], array_values($drafts)),
            ]);
        }
    }

    /**
     * Delete Data For All Users In Context.
     *
     * @param context $context Parameter.
     * @return void
     */
    public static function delete_data_for_all_users_in_context(context $context): void {
        global $DB;

        if (!$context instanceof context_module) {
            return;
        }
        $cm = get_coursemodule_from_id('videoforum', $context->instanceid, 0, false, IGNORE_MISSING);
        if (!$cm) {
            return;
        }

        self::delete_draft_files((int)$cm->instance);
        get_file_storage()->delete_area_files($context->id, 'mod_videoforum', 'postvideo');
        $DB->delete_records('videoforum_view', ['videoforumid' => $cm->instance]);
        $DB->delete_records('videoforum_report', ['videoforumid' => $cm->instance]);
        $DB->delete_records('videoforum_draft', ['videoforumid' => $cm->instance]);
        $DB->delete_records('videoforum_post', ['videoforumid' => $cm->instance]);
    }

    /**
     * Delete Data For User.
     *
     * @param approved_contextlist $contextlist Parameter.
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $CFG, $DB;

        $userid = (int)$contextlist->get_user()->id;
        require_once($CFG->dirroot . '/mod/videoforum/lib.php');

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof context_module) {
                continue;
            }
            $cm = get_coursemodule_from_id('videoforum', $context->instanceid, 0, false, IGNORE_MISSING);
            if (!$cm) {
                continue;
            }
            $activity = $DB->get_record('videoforum', ['id' => $cm->instance], '*', MUST_EXIST);

            $posts = $DB->get_records('videoforum_post', [
                'videoforumid' => $cm->instance,
                'userid' => $userid,
            ]);
            foreach ($posts as $post) {
                get_file_storage()->delete_area_files(
                    $context->id,
                    'mod_videoforum',
                    'postvideo',
                    $post->id
                );
                $post->userid = 0;
                $post->title = '';
                $post->description = '';
                $post->hidden = 1;
                $post->deleted = 1;
                $post->timemodified = time();
                $DB->update_record('videoforum_post', $post);
            }

            self::delete_draft_files((int)$cm->instance, $userid);
            $DB->delete_records('videoforum_report', [
                'videoforumid' => $cm->instance,
                'userid' => $userid,
            ]);
            $DB->set_field_select(
                'videoforum_report',
                'reviewedby',
                0,
                'videoforumid = :activityid AND reviewedby = :userid',
                ['activityid' => $cm->instance, 'userid' => $userid]
            );
            $DB->delete_records('videoforum_view', [
                'videoforumid' => $cm->instance,
                'userid' => $userid,
            ]);
            $DB->delete_records('videoforum_draft', [
                'videoforumid' => $cm->instance,
                'userid' => $userid,
            ]);

            \videoforum_update_grades($activity, $userid, true);
        }
    }

    /**
     * Delete user draft files owned by this activity's server-side draft bindings.
     *
     * @param int $activityid Activity id.
     * @param int|null $userid Optional user restriction.
     */
    /**
     * Delete Draft Files.
     *
     * @param int $activityid Parameter.
     * @param int|null $userid Parameter.
     * @return void
     */
    private static function delete_draft_files(int $activityid, ?int $userid = null): void {
        global $DB;

        $conditions = ['videoforumid' => $activityid];
        if ($userid !== null) {
            $conditions['userid'] = $userid;
        }

        $drafts = $DB->get_records('videoforum_draft', $conditions);
        $fs = get_file_storage();
        foreach ($drafts as $draft) {
            $usercontext = context_user::instance((int)$draft->userid, IGNORE_MISSING);
            if ($usercontext) {
                $fs->delete_area_files(
                    $usercontext->id,
                    'user',
                    'draft',
                    (int)$draft->draftitemid
                );
            }
        }
    }
}
