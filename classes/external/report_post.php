<?php
namespace mod_videoforum\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;

/**
 * Report a Video Forum post.
 *
 * @package mod_videoforum
 */
class report_post extends external_api {
    public static function execute_parameters(): external_function_parameters {
        return api::report_post_parameters();
    }

    public static function execute(int $cmid, int $postid, string $reason, string $details = ''): array {
        return api::report_post($cmid, $postid, $reason, $details);
    }

    public static function execute_returns(): external_single_structure {
        return api::report_post_returns();
    }
}
