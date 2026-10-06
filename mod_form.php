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
 * Activity settings form for Video Forum.
 *
 * @package    mod_videoforum
 * @copyright  2026 Eduardo Kraus
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot . '/course/moodleform_mod.php');

/**
 * Video Forum settings form.
 *
 * @package mod_videoforum
 */
class mod_videoforum_mod_form extends moodleform_mod {
    /**
     * Definition.
     *
     * @return void
     */
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

        $mform->addElement(
            'date_time_selector',
            'topicdeadline',
            get_string('topicdeadline', 'videoforum'),
            ['optional' => true]
        );
        $mform->addElement(
            'date_time_selector',
            'replydeadline',
            get_string('replydeadline', 'videoforum'),
            ['optional' => true]
        );

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

    /**
     * Add custom completion rules.
     *
     * @return array
     */
    /**
     * Add Completion Rules.
     *
     * @return array
     */
    public function add_completion_rules(): array {
        $mform = $this->_form;
        $fields = $this->completion_fields();

        $mform->addElement('text', $fields['completiontopics'], get_string('completiontopics', 'videoforum'), ['size' => 8]);
        $mform->setType($fields['completiontopics'], PARAM_INT);
        $mform->setDefault($fields['completiontopics'], 0);

        $mform->addElement(
            'text',
            $fields['completionreplies'],
            get_string('completionreplies', 'videoforum'),
            ['size' => 8]
        );
        $mform->setType($fields['completionreplies'], PARAM_INT);
        $mform->setDefault($fields['completionreplies'], 0);

        $mform->addElement(
            'text',
            $fields['completionparticipations'],
            get_string('completionparticipations', 'videoforum'),
            ['size' => 8]
        );
        $mform->setType($fields['completionparticipations'], PARAM_INT);
        $mform->setDefault($fields['completionparticipations'], 0);

        return array_values($fields);
    }

    /**
     * Whether any custom completion rule is enabled.
     *
     * @param array $data Submitted form data.
     * @return bool
     */
    /**
     * Completion Rule Enabled.
     *
     * @param mixed $data Parameter.
     * @return bool
     */
    public function completion_rule_enabled($data): bool {
        foreach ($this->completion_fields() as $formfield) {
            if (!empty($data[$formfield])) {
                return true;
            }
        }
        return false;
    }

    /**
     * Prepare persisted completion values for the suffixed form controls.
     *
     * @param array $defaultvalues Default values.
     */
    /**
     * Data Preprocessing.
     *
     * @param mixed $defaultvalues Parameter.
     * @return void
     */
    public function data_preprocessing(&$defaultvalues): void {
        foreach ($this->completion_fields() as $dbfield => $formfield) {
            if (array_key_exists($dbfield, $defaultvalues)) {
                $defaultvalues[$formfield] = $defaultvalues[$dbfield];
            }
        }
    }

    /**
     * Convert suffixed custom completion form controls back to DB fields.
     *
     * @return stdClass|false
     */
    /**
     * Get Data.
     *
     * @return mixed
     */
    public function get_data() {
        $data = parent::get_data();
        if (!$data) {
            return $data;
        }

        foreach ($this->completion_fields() as $dbfield => $formfield) {
            if (property_exists($data, $formfield)) {
                $data->{$dbfield} = (int)$data->{$formfield};
                unset($data->{$formfield});
            }
        }

        return $data;
    }

    /**
     * Validate activity data.
     *
     * @param array $data Submitted values.
     * @param array $files Submitted files.
     * @return array
     */
    /**
     * Validation.
     *
     * @param mixed $data Parameter.
     * @param mixed $files Parameter.
     * @return array
     */
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

        foreach ($this->completion_fields() as $formfield) {
            if ((int)($data[$formfield] ?? 0) < 0) {
                $errors[$formfield] = get_string('invaliddata', 'error');
            }
        }

        return $errors;
    }

    /**
     * Map DB completion fields to collision-safe form control names.
     *
     * @return array<string,string>
     */
    /**
     * Completion Fields.
     *
     * @return array
     */
    private function completion_fields(): array {
        return [
            'completiontopics' => 'completiontopics_videoforum',
            'completionreplies' => 'completionreplies_videoforum',
            'completionparticipations' => 'completionparticipations_videoforum',
        ];
    }
}
