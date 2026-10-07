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
 * External service for reporting Video Forum posts.
 *
 * @package    mod_videoforum
 * @copyright  2026 Eduardo Kraus
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videoforum\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use mod_videoforum\local\manager;

/**
 * Report a Video Forum post.
 *
 * @package mod_videoforum
 */
class report_post extends external_api {
    /**
     * Defines the parameters accepted by the external function.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return api::report_post_parameters();
    }

    /**
     * Reports a visible Video Forum post.
     *
     * @param int $cmid Course module id.
     * @param int $postid Post id.
     * @param string $reason Report reason.
     * @param string $details Optional report details.
     * @return array
     */
    public static function execute(int $cmid, int $postid, string $reason, string $details = ''): array {
        $params = self::validate_parameters(
            self::execute_parameters(),
            compact('cmid', 'postid', 'reason', 'details')
        );
        [, , , $context] = manager::require_runtime($params['cmid']);
        self::validate_context($context);

        return api::report_post(
            $params['cmid'],
            $params['postid'],
            $params['reason'],
            $params['details']
        );
    }

    /**
     * Defines the external function return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return api::report_post_returns();
    }
}
