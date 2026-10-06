<?php
// This file is part of Moodle - http://moodle.org/.

namespace mod_videoforum\event;

/**
 * A video post was created.
 *
 * @package mod_videoforum
 */
class post_created extends \core\event\base {
    protected function init(): void {
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
        $this->data['objecttable'] = 'videoforum_post';
    }

    public static function get_name(): string {
        return get_string('newtopic', 'videoforum');
    }

    public function get_description(): string {
        return "The user with id '{$this->userid}' created Video Forum post '{$this->objectid}'.";
    }

    public function get_url(): \moodle_url {
        return new \moodle_url('/mod/videoforum/view.php', ['id' => $this->contextinstanceid]);
    }

    public static function get_objectid_mapping() {
        return ['db' => 'videoforum_post', 'restore' => 'videoforum_post'];
    }

    public static function get_other_mapping() {
        return [
            'parentid' => ['db' => 'videoforum_post', 'restore' => 'videoforum_post'],
            'rootid' => ['db' => 'videoforum_post', 'restore' => 'videoforum_post'],
        ];
    }
}
