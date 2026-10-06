<?php
// This file is part of Moodle - http://moodle.org/.

namespace mod_videoforum\event;

/**
 * A publication was reported.
 *
 * @package mod_videoforum
 */
class post_reported extends \core\event\base {
    protected function init(): void {
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
        $this->data['objecttable'] = 'videoforum_report';
    }

    public static function get_name(): string {
        return get_string('report', 'videoforum');
    }

    public function get_description(): string {
        $postid = (int)($this->other['postid'] ?? 0);
        return "The user with id '{$this->userid}' reported Video Forum post '{$postid}'.";
    }

    public function get_url(): \moodle_url {
        return new \moodle_url('/mod/videoforum/report.php', ['id' => $this->contextinstanceid]);
    }

    public static function get_objectid_mapping() {
        return ['db' => 'videoforum_report', 'restore' => 'videoforum_report'];
    }

    public static function get_other_mapping() {
        return [
            'postid' => ['db' => 'videoforum_post', 'restore' => 'videoforum_post'],
        ];
    }
}
