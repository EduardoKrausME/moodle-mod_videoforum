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
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.

/**
 * Event fired when a Video Forum activity is viewed.
 *
 * @package mod_videoforum
 * @copyright 2026 Eduardo Kraus
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videoforum\event;

/**
 * Video Forum activity viewed event.
 */
class course_module_viewed extends \core\event\course_module_viewed {
    /**
     * Initialize event metadata.
     */
    protected function init(): void {
        $this->data['crud'] = 'r';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
        $this->data['objecttable'] = 'videoforum';
    }

    /**
     * Return the translated event name.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('eventcoursemoduleviewed', 'core');
    }

    /**
     * Return the activity URL.
     *
     * @return \moodle_url
     */
    public function get_url(): \moodle_url {
        return new \moodle_url('/mod/videoforum/view.php', ['id' => $this->contextinstanceid]);
    }

    /**
     * Return object ID mapping for backup restore.
     *
     * @return array
     */
    public static function get_objectid_mapping() {
        return ['db' => 'videoforum', 'restore' => 'videoforum'];
    }
}
