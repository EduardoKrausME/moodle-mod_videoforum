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
 * Custom completion rules for Video Forum.
 *
 * @package    mod_videoforum
 * @copyright  2026 Eduardo Kraus
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videoforum\completion;

use core_completion\activity_custom_completion;
use mod_videoforum\local\manager;

/**
 * Custom activity completion.
 *
 * @package mod_videoforum
 */
class custom_completion extends activity_custom_completion {
    /**
     * Get State.
     *
     * @param string $rule Parameter.
     * @return int
     */
    public function get_state(string $rule): int {
        $this->validate_rule($rule);

        $counts = manager::count_user_posts((int)$this->cm->instance, (int)$this->userid);
        $rules = $this->cm->customdata['customcompletionrules'] ?? [];

        $map = [
            'completiontopics' => 'topics',
            'completionreplies' => 'replies',
            'completionparticipations' => 'total',
        ];
        $required = (int)($rules[$rule] ?? 0);

        if ($required <= 0) {
            return COMPLETION_COMPLETE;
        }

        return $counts[$map[$rule]] >= $required ? COMPLETION_COMPLETE : COMPLETION_INCOMPLETE;
    }

    /**
     * Get Defined Custom Rules.
     *
     * @return array
     */
    public static function get_defined_custom_rules(): array {
        return ['completiontopics', 'completionreplies', 'completionparticipations'];
    }

    /**
     * Get Custom Rule Descriptions.
     *
     * @return array
     */
    public function get_custom_rule_descriptions(): array {
        $rules = $this->cm->customdata['customcompletionrules'] ?? [];
        return [
            'completiontopics' => get_string(
                'completiondetail:topics',
                'videoforum',
                (int)($rules['completiontopics'] ?? 0)
            ),
            'completionreplies' => get_string(
                'completiondetail:replies',
                'videoforum',
                (int)($rules['completionreplies'] ?? 0)
            ),
            'completionparticipations' => get_string(
                'completiondetail:participations',
                'videoforum',
                (int)($rules['completionparticipations'] ?? 0)
            ),
        ];
    }

    /**
     * Get Sort Order.
     *
     * @return array
     */
    public function get_sort_order(): array {
        return ['completiontopics', 'completionreplies', 'completionparticipations'];
    }
}
