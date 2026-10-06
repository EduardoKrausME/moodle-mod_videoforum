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
 * Video Forum backup structure step.
 *
 * @package    mod_videoforum
 * @copyright  2026 Eduardo Kraus
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


/**
 * Video Forum backup structure.
 *
 * @package mod_videoforum
 */
class backup_videoforum_activity_structure_step extends backup_activity_structure_step {
    /**
     * Define Structure.
     *
     * @return mixed
     */
    protected function define_structure() {
        $userinfo = $this->get_setting_value('userinfo');

        $activity = new backup_nested_element('videoforum', ['id'], [
            'course', 'name', 'intro', 'introformat', 'maxduration', 'allowtopics', 'allowreplies',
            'maxreplies', 'topicdeadline', 'replydeadline', 'postbeforeview', 'allowupload',
            'allowrecording', 'gradetarget', 'completiontopics', 'completionreplies',
            'completionparticipations', 'grade', 'timecreated', 'timemodified',
        ]);

        $posts = new backup_nested_element('posts');
        $post = new backup_nested_element('post', ['id'], [
            'userid', 'parentid', 'rootid', 'groupid', 'title', 'description', 'duration',
            'hidden', 'deleted', 'locked', 'timecreated', 'timemodified',
        ]);
        $reports = new backup_nested_element('reports');
        $report = new backup_nested_element('report', ['id'], [
            'postid', 'userid', 'reason', 'details', 'status', 'reviewedby', 'timecreated', 'timereviewed',
        ]);
        $views = new backup_nested_element('views');
        $view = new backup_nested_element('view', ['id'], [
            'postid', 'userid', 'lastposition', 'completed', 'timecreated', 'timemodified',
        ]);

        $activity->add_child($posts);
        $posts->add_child($post);
        $activity->add_child($reports);
        $reports->add_child($report);
        $activity->add_child($views);
        $views->add_child($view);

        $activity->set_source_table('videoforum', ['id' => backup::VAR_ACTIVITYID]);
        if ($userinfo) {
            $post->set_source_table('videoforum_post', ['videoforumid' => backup::VAR_ACTIVITYID]);
            $report->set_source_table('videoforum_report', ['videoforumid' => backup::VAR_ACTIVITYID]);
            $view->set_source_table('videoforum_view', ['videoforumid' => backup::VAR_ACTIVITYID]);

            $post->annotate_ids('user', 'userid');
            $post->annotate_ids('group', 'groupid');
            $report->annotate_ids('user', 'userid');
            $report->annotate_ids('user', 'reviewedby');
            $view->annotate_ids('user', 'userid');
            $post->annotate_files('mod_videoforum', 'postvideo', 'id');
        }

        $activity->annotate_files('mod_videoforum', 'intro', null);

        return $this->prepare_activity_structure($activity);
    }
}
