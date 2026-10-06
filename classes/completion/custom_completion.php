<?php
// This file is part of Moodle - http://moodle.org/.

namespace mod_videoforum\completion;

use core_completion\activity_custom_completion;
use mod_videoforum\local\manager;

/**
 * Custom activity completion.
 *
 * @package mod_videoforum
 */
class custom_completion extends activity_custom_completion {
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

    public static function get_defined_custom_rules(): array {
        return ['completiontopics', 'completionreplies', 'completionparticipations'];
    }

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

    public function get_sort_order(): array {
        return ['completiontopics', 'completionreplies', 'completionparticipations'];
    }
}
