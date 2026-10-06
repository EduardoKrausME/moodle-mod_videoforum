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
 * Event fired when a Video Forum post is moderated.
 *
 * @package    mod_videoforum
 * @copyright  2026 Eduardo Kraus
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videoforum\event;

/**
 * A publication was moderated.
 *
 * @package mod_videoforum
 */
class post_moderated extends \core\event\base {
    /**
     * Init.
     *
     * @return void
     */
    protected function init(): void {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_TEACHING;
        $this->data['objecttable'] = 'videoforum_post';
    }

    /**
     * Get Name.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('moderation', 'videoforum');
    }

    /**
     * Get Description.
     *
     * @return string
     */
    public function get_description(): string {
        $action = s($this->other['action'] ?? '');
        return "The user with id '{$this->userid}' applied '{$action}' to Video Forum post '{$this->objectid}'.";
    }

    /**
     * Get Url.
     *
     * @return \moodle_url
     */
    public function get_url(): \moodle_url {
        return new \moodle_url('/mod/videoforum/view.php', ['id' => $this->contextinstanceid]);
    }

    /**
     * Get Objectid Mapping.
     *
     * @return mixed
     */
    public static function get_objectid_mapping() {
        return ['db' => 'videoforum_post', 'restore' => 'videoforum_post'];
    }

    /**
     * Get Other Mapping.
     *
     * @return mixed
     */
    public static function get_other_mapping() {
        return [];
    }
}
