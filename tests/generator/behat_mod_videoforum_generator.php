<?php
// This file is part of Moodle - http://moodle.org/.

defined('MOODLE_INTERNAL') || die();

/**
 * Behat data generator for mod_videoforum.
 *
 * @package mod_videoforum
 * @category test
 */
class behat_mod_videoforum_generator extends behat_generator_base {
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
