<?php
namespace mod_videoforum\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;

/**
 * Track viewing progress for a Video Forum post.
 *
 * @package mod_videoforum
 */
class track_view extends external_api {
    public static function execute_parameters(): external_function_parameters {
        return api::track_view_parameters();
    }

    public static function execute(int $cmid, int $postid, float $position, bool $ended): array {
        return api::track_view($cmid, $postid, $position, $ended);
    }

    public static function execute_returns(): external_single_structure {
        return api::track_view_returns();
    }
}
