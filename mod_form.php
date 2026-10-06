<?php
// This file is part of Moodle - http://moodle.org/.

defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot . '/course/moodleform_mod.php');

/**
 * Video Forum settings form.
 *
 * @package mod_videoforum
 */
class mod_videoforum_mod_form extends moodleform_mod {
    public function definition(): void {
        $mform = $this->_form;

        $mform->addElement('header', 'general', get_string('general', 'form'));
        $mform->addElement('text', 'name', get_string('videoforumname', 'videoforum'), ['size' => 64]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $this->standard_intro_elements();

        $mform->addElement('header', 'publicationsettings', get_string('publicationsettings', 'videoforum'));

        $mform->addElement('text', 'maxduration', get_string('maxduration', 'videoforum'), ['size' => 8]);
        $mform->setType('maxduration', PARAM_INT);
        $mform->setDefault('maxduration', 120);
        $mform->addHelpButton('maxduration', 'maxduration', 'videoforum');

        $mform->addElement('advcheckbox', 'allowtopics', get_string('allowtopics', 'videoforum'));
        $mform->setDefault('allowtopics', 1);
        $mform->addElement('advcheckbox', 'allowreplies', get_string('allowreplies', 'videoforum'));
        $mform->setDefault('allowreplies', 1);

        $mform->addElement('text', 'maxreplies', get_string('maxreplies', 'videoforum'), ['size' => 8]);
        $mform->setType('maxreplies', PARAM_INT);
        $mform->setDefault('maxreplies', 0);
        $mform->addHelpButton('maxreplies', 'maxreplies', 'videoforum');

        $mform->addElement('date_time_selector', 'topicdeadline', get_string('topicdeadline', 'videoforum'), ['optional' => true]);
        $mform->addElement('date_time_selector', 'replydeadline', get_string('replydeadline', 'videoforum'), ['optional' => true]);

        $mform->addElement('advcheckbox', 'postbeforeview', get_string('postbeforeview', 'videoforum'));
        $mform->setDefault('postbeforeview', 0);
        $mform->addHelpButton('postbeforeview', 'postbeforeview', 'videoforum');

        $mform->addElement('advcheckbox', 'allowupload', get_string('allowupload', 'videoforum'));
        $mform->setDefault('allowupload', 1);
        $mform->addElement('advcheckbox', 'allowrecording', get_string('allowrecording', 'videoforum'));
        $mform->setDefault('allowrecording', 1);

        $mform->addElement('header', 'gradingheader', get_string('grade'));
        $this->standard_grading_coursemodule_elements();
        $mform->setDefault('grade', 0);

        $mform->addElement('text', 'gradetarget', get_string('gradetarget', 'videoforum'), ['size' => 8]);
        $mform->setType('gradetarget', PARAM_INT);
        $mform->setDefault('gradetarget', 1);
        $mform->addHelpButton('gradetarget', 'gradetarget', 'videoforum');

        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }

    public function add_completion_rules(): array {
        $mform = $this->_form;

        $mform->addElement('text', 'completiontopics', get_string('completiontopics', 'videoforum'), ['size' => 8]);
        $mform->setType('completiontopics', PARAM_INT);
        $mform->setDefault('completiontopics', 0);

        $mform->addElement('text', 'completionreplies', get_string('completionreplies', 'videoforum'), ['size' => 8]);
        $mform->setType('completionreplies', PARAM_INT);
        $mform->setDefault('completionreplies', 0);

        $mform->addElement(
            'text',
            'completionparticipations',
            get_string('completionparticipations', 'videoforum'),
            ['size' => 8]
        );
        $mform->setType('completionparticipations', PARAM_INT);
        $mform->setDefault('completionparticipations', 0);

        return ['completiontopics', 'completionreplies', 'completionparticipations'];
    }

    public function completion_rule_enabled($data): bool {
        return !empty($data['completiontopics'])
            || !empty($data['completionreplies'])
            || !empty($data['completionparticipations']);
    }

    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);

        $duration = (int)($data['maxduration'] ?? 0);
        if ($duration < 5 || $duration > 3600) {
            $errors['maxduration'] = get_string('invaliddata', 'error');
        }
        if ((int)($data['maxreplies'] ?? 0) < 0) {
            $errors['maxreplies'] = get_string('invaliddata', 'error');
        }
        if (empty($data['allowupload']) && empty($data['allowrecording'])) {
            $errors['allowrecording'] = get_string('nopublicationmethod', 'videoforum');
        }
        if ((int)($data['gradetarget'] ?? 0) < 1) {
            $errors['gradetarget'] = get_string('invaliddata', 'error');
        }
        foreach (['completiontopics', 'completionreplies', 'completionparticipations'] as $field) {
            if ((int)($data[$field] ?? 0) < 0) {
                $errors[$field] = get_string('invaliddata', 'error');
            }
        }
        return $errors;
    }
}
