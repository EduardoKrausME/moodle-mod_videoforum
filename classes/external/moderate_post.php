<?php
namespace mod_videoforum\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;

/**
 * Moderate a Video Forum post.
 *
 * @package mod_videoforum
 */
class moderate_post extends external_api {
    public static function execute_parameters(): external_function_parameters {
        return api::moderate_post_parameters();
    }

    public static function execute(int $cmid, int $postid, string $action): array {
        return api::moderate_post($cmid, $postid, $action);
    }

    public static function execute_returns(): external_single_structure {
        return api::moderate_post_returns();
    }
}
