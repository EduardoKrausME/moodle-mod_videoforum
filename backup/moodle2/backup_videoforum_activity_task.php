<?php
// This file is part of Moodle - http://moodle.org/.

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/videoforum/backup/moodle2/backup_videoforum_stepslib.php');

/**
 * Video Forum backup task.
 *
 * @package mod_videoforum
 */
class backup_videoforum_activity_task extends backup_activity_task {
    protected function define_my_settings(): void {
    }

    protected function define_my_steps(): void {
        $this->add_step(new backup_videoforum_activity_structure_step('videoforum_structure', 'videoforum.xml'));
    }

    public static function encode_content_links($content): string {
        return $content;
    }
}
