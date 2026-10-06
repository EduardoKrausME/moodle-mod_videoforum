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
 * Behat data generator for Video Forum.
 *
 * @package    mod_videoforum
 * @copyright  2026 Eduardo Kraus
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


/**
 * Behat data generator for mod_videoforum.
 *
 * @package mod_videoforum
 * @category test
 */
class behat_mod_videoforum_generator extends behat_generator_base {
    /**
     * Get Creatable Entities.
     *
     * @return array
     */
    protected function get_creatable_entities(): array {
        return [
            'posts' => [
                'singular' => 'post',
                'datagenerator' => 'post',
                'required' => ['videoforum', 'user'],
                'switchids' => [
                    'videoforum' => 'videoforumid',
                    'user' => 'userid',
                    'group' => 'groupid',
                ],
            ],
        ];
    }

    /**
     * Resolve an activity by course-module idnumber or activity name.
     *
     * @param string $identifier Human-readable identifier from the feature table.
     * @return int Video Forum instance id.
     */
    /**
     * Get Videoforum Id.
     *
     * @param string $identifier Parameter.
     * @return int
     */
    protected function get_videoforum_id(string $identifier): int {
        global $DB;

        $sql = "SELECT vf.id
                  FROM {videoforum} vf
                  JOIN {course_modules} cm ON cm.instance = vf.id
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                 WHERE cm.idnumber = :idnumber OR vf.name = :activityname";
        $ids = $DB->get_fieldset_sql($sql, [
            'modname' => 'videoforum',
            'idnumber' => $identifier,
            'activityname' => $identifier,
        ]);

        if (count($ids) !== 1) {
            throw new Exception(
                'Expected exactly one Video Forum matching "' . $identifier . '", found ' . count($ids) . '.'
            );
        }

        return (int)reset($ids);
    }
}
