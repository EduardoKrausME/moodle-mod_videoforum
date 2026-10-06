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
 * Video Forum restore structure step.
 *
 * @package    mod_videoforum
 * @copyright  2026 Eduardo Kraus
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


/**
 * Video Forum restore structure.
 *
 * @package mod_videoforum
 */
class restore_videoforum_activity_structure_step extends restore_activity_structure_step {
    /** @var array<int,array{parentid:int,rootid:int}> */
    private array $postlinks = [];

    /**
     * Define Structure.
     *
     * @return array
     */
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

    /**
     * Process Videoforum.
     *
     * @param mixed $data Parameter.
     * @return void
     */
    protected function process_videoforum($data): void {
        global $DB;

        $data = (object)$data;
        $data->course = $this->get_courseid();
        $newid = $DB->insert_record('videoforum', $data);
        $this->apply_activity_instance($newid);
    }

    /**
     * Process Videoforum Post.
     *
     * @param mixed $data Parameter.
     * @return void
     */
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

    /**
     * Process Videoforum Report.
     *
     * @param mixed $data Parameter.
     * @return void
     */
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

    /**
     * Process Videoforum View.
     *
     * @param mixed $data Parameter.
     * @return void
     */
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

    /**
     * After Execute.
     *
     * @return void
     */
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
