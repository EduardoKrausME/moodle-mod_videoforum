<?php
// This file is part of Moodle - http://moodle.org/.

namespace mod_videoforum\privacy;

use context;
use context_module;
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
        ], 'privacy:metadata:report');

        $collection->add_database_table('videoforum_view', [
            'userid' => 'privacy:metadata:view:userid',
            'lastposition' => 'privacy:metadata:view:lastposition',
            'completed' => 'privacy:metadata:view:completed',
        ], 'privacy:metadata:view');

        return $collection;
    }

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

        return $contextlist;
    }

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
        }
    }

    public static function delete_data_for_all_users_in_context(context $context): void {
        global $DB;

        if (!$context instanceof context_module) {
            return;
        }
        $cm = get_coursemodule_from_id('videoforum', $context->instanceid, 0, false, IGNORE_MISSING);
        if (!$cm) {
            return;
        }

        get_file_storage()->delete_area_files($context->id, 'mod_videoforum', 'postvideo');
        $DB->delete_records('videoforum_view', ['videoforumid' => $cm->instance]);
        $DB->delete_records('videoforum_report', ['videoforumid' => $cm->instance]);
        $DB->delete_records('videoforum_draft', ['videoforumid' => $cm->instance]);
        $DB->delete_records('videoforum_post', ['videoforumid' => $cm->instance]);
    }

    public static function delete_data_for_user(approved_contextlist $contextlist): void {
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
            ]);
            foreach ($posts as $post) {
                get_file_storage()->delete_area_files(
                    $context->id,
                    'mod_videoforum',
                    'postvideo',
                    $post->id
                );
                $post->title = '';
                $post->description = '';
                $post->hidden = 1;
                $post->deleted = 1;
                $post->timemodified = time();
                $DB->update_record('videoforum_post', $post);
            }

            $DB->delete_records('videoforum_report', [
                'videoforumid' => $cm->instance,
                'userid' => $userid,
            ]);
            $DB->delete_records('videoforum_view', [
                'videoforumid' => $cm->instance,
                'userid' => $userid,
            ]);
            $DB->delete_records('videoforum_draft', [
                'videoforumid' => $cm->instance,
                'userid' => $userid,
            ]);
        }
    }
}
