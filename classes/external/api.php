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
 * AJAX external API for Video Forum.
 *
 * @package    mod_videoforum
 * @copyright  2026 Eduardo Kraus
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

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
    /**
     * Create Post Parameters.
     *
     * @return external_function_parameters
     */
    public static function create_post_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
            'draftitemid' => new external_value(PARAM_INT, 'Authenticated Moodle draft item id'),
            'parentid' => new external_value(PARAM_INT, 'Direct parent post id, 0 for a root topic'),
            'title' => new external_value(PARAM_TEXT, 'Optional short title', VALUE_DEFAULT, ''),
            'description' => new external_value(PARAM_TEXT, 'Optional short description', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Create Post.
     *
     * @param int $cmid Parameter.
     * @param int $draftitemid Parameter.
     * @param int $parentid Parameter.
     * @param string $title Parameter.
     * @param string $description Parameter.
     * @return array
     */
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

    /**
     * Create Post Returns.
     *
     * @return external_single_structure
     */
    public static function create_post_returns(): external_single_structure {
        return new external_single_structure([
            'postid' => new external_value(PARAM_INT, 'Created post id'),
        ]);
    }

    /**
     * Moderate Post Parameters.
     *
     * @return external_function_parameters
     */
    public static function moderate_post_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
            'postid' => new external_value(PARAM_INT, 'Post id'),
            'action' => new external_value(PARAM_ALPHA, 'hide, restore, delete, lock or unlock'),
        ]);
    }

    /**
     * Moderate Post.
     *
     * @param int $cmid Parameter.
     * @param int $postid Parameter.
     * @param string $action Parameter.
     * @return array
     */
    public static function moderate_post(int $cmid, int $postid, string $action): array {
        $params = self::validate_parameters(self::moderate_post_parameters(), compact('cmid', 'postid', 'action'));
        [$cm, $course, $activity, $context] = manager::require_runtime($params['cmid']);
        self::validate_context($context);

        manager::moderate($cm, $course, $activity, $context, $params['postid'], $params['action']);
        return ['success' => true];
    }

    /**
     * Moderate Post Returns.
     *
     * @return external_single_structure
     */
    public static function moderate_post_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the action succeeded'),
        ]);
    }

    /**
     * Report Post Parameters.
     *
     * @return external_function_parameters
     */
    public static function report_post_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
            'postid' => new external_value(PARAM_INT, 'Post id'),
            'reason' => new external_value(PARAM_ALPHA, 'Report reason'),
            'details' => new external_value(PARAM_TEXT, 'Optional report details', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Report Post.
     *
     * @param int $cmid Parameter.
     * @param int $postid Parameter.
     * @param string $reason Parameter.
     * @param string $details Parameter.
     * @return array
     */
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

    /**
     * Report Post Returns.
     *
     * @return external_single_structure
     */
    public static function report_post_returns(): external_single_structure {
        return new external_single_structure([
            'reportid' => new external_value(PARAM_INT, 'Created report id'),
        ]);
    }

    /**
     * Track View Parameters.
     *
     * @return external_function_parameters
     */
    public static function track_view_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
            'postid' => new external_value(PARAM_INT, 'Post id'),
            'position' => new external_value(PARAM_FLOAT, 'Current playback position'),
            'ended' => new external_value(PARAM_BOOL, 'Whether playback emitted ended'),
        ]);
    }

    /**
     * Track View.
     *
     * @param int $cmid Parameter.
     * @param int $postid Parameter.
     * @param float $position Parameter.
     * @param bool $ended Parameter.
     * @return array
     */
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

    /**
     * Track View Returns.
     *
     * @return external_single_structure
     */
    public static function track_view_returns(): external_single_structure {
        return new external_single_structure([
            'completed' => new external_value(PARAM_BOOL, 'Whether the video is now considered watched'),
        ]);
    }
}
