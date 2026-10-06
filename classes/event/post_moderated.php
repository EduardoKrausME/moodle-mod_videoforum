<?php
// This file is part of Moodle - http://moodle.org/.

namespace mod_videoforum\event;

/**
 * A publication was moderated.
 *
 * @package mod_videoforum
 */
class post_moderated extends \core\event\base {
    protected function init(): void {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_TEACHING;
        $this->data['objecttable'] = 'videoforum_post';
    }

    public static function get_name(): string {
        return get_string('moderation', 'videoforum');
    }

    public function get_description(): string {
        $action = s($this->other['action'] ?? '');
        return "The user with id '{$this->userid}' applied '{$action}' to Video Forum post '{$this->objectid}'.";
    }

    public function get_url(): \moodle_url {
        return new \moodle_url('/mod/videoforum/view.php', ['id' => $this->contextinstanceid]);
    }

    public static function get_objectid_mapping() {
        return ['db' => 'videoforum_post', 'restore' => 'videoforum_post'];
    }

    public static function get_other_mapping() {
        return [];
    }
}
