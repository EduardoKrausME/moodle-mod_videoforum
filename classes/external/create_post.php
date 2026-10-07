<?php
namespace mod_videoforum\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;

/**
 * Create a Video Forum post.
 *
 * @package mod_videoforum
 */
class create_post extends external_api {
    public static function execute_parameters(): external_function_parameters {
        return api::create_post_parameters();
    }

    public static function execute(
        int $cmid,
        int $draftitemid,
        int $parentid,
        string $title = '',
        string $description = ''
    ): array {
        return api::create_post($cmid, $draftitemid, $parentid, $title, $description);
    }

    public static function execute_returns(): external_single_structure {
        return api::create_post_returns();
    }
}
