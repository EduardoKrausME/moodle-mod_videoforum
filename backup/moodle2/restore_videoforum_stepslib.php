<?php
// This file is part of Moodle - http://moodle.org/.

defined('MOODLE_INTERNAL') || die();

/**
 * Video Forum restore structure.
 *
 * @package mod_videoforum
 */
class restore_videoforum_activity_structure_step extends restore_activity_structure_step {
    /** @var array<int,array{parentid:int,rootid:int}> */
    private array $postlinks = [];

    protected function define_structure(): array {
        $paths = [
            new restore_path_element('videoforum', '/activity/videoforum'),
        ];

        if ($this->get_setting_value('userinfo')) {
            $paths[] = new restore_path_element('videoforum_post', '/activity/videoforum/posts/post');
            $paths[] = new restore_path_element('videoforum_report', '/activity/videoforum/reports/report');
            $paths[] = new restore_path_element('videoforum_view', '/activity/videoforum/views/view');
        }

        return $this->prepare_activity_structure($paths);
    }

    protected function process_videoforum($data): void {
        global $DB;

        $data = (object)$data;
        $data->course = $this->get_courseid();
        $newid = $DB->insert_record('videoforum', $data);
        $this->apply_activity_instance($newid);
    }

    protected function process_videoforum_post($data): void {
        global $DB;

        $data = (object)$data;
        $oldid = (int)$data->id;
        $oldparentid = (int)$data->parentid;
        $oldrootid = (int)$data->rootid;

        $data->videoforumid = $this->get_new_parentid('videoforum');
        $data->userid = $this->get_mappingid('user', $data->userid, 0);
        $data->groupid = !empty($data->groupid) ? $this->get_mappingid('group', $data->groupid, 0) : 0;
        $data->parentid = 0;
        $data->rootid = 0;

        if (!$data->userid) {
            return;
        }

        $newid = $DB->insert_record('videoforum_post', $data);
        $this->set_mapping('videoforum_post', $oldid, $newid, true);
        $this->postlinks[$newid] = ['parentid' => $oldparentid, 'rootid' => $oldrootid];
    }

    protected function process_videoforum_report($data): void {
        global $DB;

        $data = (object)$data;
        $data->videoforumid = $this->get_new_parentid('videoforum');
        $data->postid = $this->get_mappingid('videoforum_post', $data->postid, 0);
        $data->userid = $this->get_mappingid('user', $data->userid, 0);
        $data->reviewedby = !empty($data->reviewedby)
            ? $this->get_mappingid('user', $data->reviewedby, 0)
            : 0;

        if ($data->postid && $data->userid) {
            $oldid = (int)$data->id;
            $newid = $DB->insert_record('videoforum_report', $data);
            $this->set_mapping('videoforum_report', $oldid, $newid);
        }
    }

    protected function process_videoforum_view($data): void {
        global $DB;

        $data = (object)$data;
        $data->videoforumid = $this->get_new_parentid('videoforum');
        $data->postid = $this->get_mappingid('videoforum_post', $data->postid, 0);
        $data->userid = $this->get_mappingid('user', $data->userid, 0);

        if ($data->postid && $data->userid) {
            $DB->insert_record('videoforum_view', $data);
        }
    }

    protected function after_execute(): void {
        global $DB;

        foreach ($this->postlinks as $newid => $links) {
            $parentid = $links['parentid']
                ? $this->get_mappingid('videoforum_post', $links['parentid'], 0)
                : 0;
            $rootid = $links['rootid']
                ? $this->get_mappingid('videoforum_post', $links['rootid'], 0)
                : 0;

            if (($links['parentid'] && !$parentid) || ($links['rootid'] && !$rootid)) {
                $DB->set_field('videoforum_post', 'deleted', 1, ['id' => $newid]);
                continue;
            }

            $DB->update_record('videoforum_post', (object)[
                'id' => $newid,
                'parentid' => $parentid,
                'rootid' => $rootid,
            ]);
        }

        $this->add_related_files('mod_videoforum', 'intro', null);
        $this->add_related_files('mod_videoforum', 'postvideo', 'videoforum_post');
    }
}
