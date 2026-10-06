<?php
// This file is part of Moodle - http://moodle.org/.

defined('MOODLE_INTERNAL') || die();

/**
 * Video Forum backup structure.
 *
 * @package mod_videoforum
 */
class backup_videoforum_activity_structure_step extends backup_activity_structure_step {
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
