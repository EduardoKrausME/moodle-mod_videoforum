<?php
// This file is part of Moodle - http://moodle.org/.

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/videoforum/backup/moodle2/restore_videoforum_stepslib.php');

/**
 * Video Forum restore task.
 *
 * @package mod_videoforum
 */
class restore_videoforum_activity_task extends restore_activity_task {
    protected function define_my_settings(): void {
    }

    protected function define_my_steps(): void {
        $this->add_step(new restore_videoforum_activity_structure_step('videoforum_structure', 'videoforum.xml'));
    }

    public static function define_decode_rules(): array {
        return [];
    }

    public static function define_decode_contents(): array {
        return [];
    }
}
