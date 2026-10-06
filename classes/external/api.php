<?php
// This file is part of Moodle - http://moodle.org/.

namespace mod_videoforum\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use mod_videoforum\local\manager;

/**
 * AJAX API.
 *
 * @package mod_videoforum
 */
class api extends external_api {
    public static function create_post_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
            'draftitemid' => new external_value(PARAM_INT, 'Authenticated Moodle draft item id'),
            'parentid' => new external_value(PARAM_INT, 'Direct parent post id, 0 for a root topic'),
            'title' => new external_value(PARAM_TEXT, 'Optional short title', VALUE_DEFAULT, ''),
            'description' => new external_value(PARAM_TEXT, 'Optional short description', VALUE_DEFAULT, ''),
        ]);
    }

    public static function create_post(
        int $cmid,
        int $draftitemid,
        int $parentid,
        string $title = '',
        string $description = ''
    ): array {
        global $USER;

        $params = self::validate_parameters(self::create_post_parameters(), compact(
            'cmid',
            'draftitemid',
            'parentid',
            'title',
            'description'
        ));
        [$cm, $course, $activity, $context] = manager::require_runtime($params['cmid']);
        self::validate_context($context);

        $post = manager::create_post(
            $cm,
            $course,
            $activity,
            $context,
            (int)$USER->id,
            $params['draftitemid'],
            $params['parentid'],
            $params['title'],
            $params['description']
        );

        return ['postid' => (int)$post->id];
    }

    public static function create_post_returns(): external_single_structure {
        return new external_single_structure([
            'postid' => new external_value(PARAM_INT, 'Created post id'),
        ]);
    }

    public static function moderate_post_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
            'postid' => new external_value(PARAM_INT, 'Post id'),
            'action' => new external_value(PARAM_ALPHA, 'hide, restore, delete, lock or unlock'),
        ]);
    }

    public static function moderate_post(int $cmid, int $postid, string $action): array {
        $params = self::validate_parameters(self::moderate_post_parameters(), compact('cmid', 'postid', 'action'));
        [$cm, $course, $activity, $context] = manager::require_runtime($params['cmid']);
        self::validate_context($context);

        manager::moderate($cm, $course, $activity, $context, $params['postid'], $params['action']);
        return ['success' => true];
    }

    public static function moderate_post_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the action succeeded'),
        ]);
    }

    public static function report_post_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
            'postid' => new external_value(PARAM_INT, 'Post id'),
            'reason' => new external_value(PARAM_ALPHA, 'Report reason'),
            'details' => new external_value(PARAM_TEXT, 'Optional report details', VALUE_DEFAULT, ''),
        ]);
    }

    public static function report_post(int $cmid, int $postid, string $reason, string $details = ''): array {
        global $USER;

        $params = self::validate_parameters(
            self::report_post_parameters(),
            compact('cmid', 'postid', 'reason', 'details')
        );
        [$cm, $course, $activity, $context] = manager::require_runtime($params['cmid']);
        self::validate_context($context);

        $reportid = manager::report(
            $cm,
            $activity,
            $context,
            (int)$USER->id,
            $params['postid'],
            $params['reason'],
            $params['details']
        );

        return ['reportid' => $reportid];
    }

    public static function report_post_returns(): external_single_structure {
        return new external_single_structure([
            'reportid' => new external_value(PARAM_INT, 'Created report id'),
        ]);
    }

    public static function track_view_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
            'postid' => new external_value(PARAM_INT, 'Post id'),
            'position' => new external_value(PARAM_FLOAT, 'Current playback position'),
            'ended' => new external_value(PARAM_BOOL, 'Whether playback emitted ended'),
        ]);
    }

    public static function track_view(int $cmid, int $postid, float $position, bool $ended): array {
        global $USER;

        $params = self::validate_parameters(
            self::track_view_parameters(),
            compact('cmid', 'postid', 'position', 'ended')
        );
        [$cm, $course, $activity, $context] = manager::require_runtime($params['cmid']);
        self::validate_context($context);

        $completed = manager::track_view(
            $cm,
            $activity,
            (int)$USER->id,
            $params['postid'],
            (float)$params['position'],
            (bool)$params['ended']
        );

        return ['completed' => $completed];
    }

    public static function track_view_returns(): external_single_structure {
        return new external_single_structure([
            'completed' => new external_value(PARAM_BOOL, 'Whether the video is now considered watched'),
        ]);
    }
}
