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
 * Question type class for the calculated multiple-choice question type.
 *
 * @package    qtype
 * @subpackage calculatedmulti
 * @copyright  2009 Pierre Pichet
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/question/type/multichoice/questiontype.php');
require_once($CFG->dirroot . '/question/type/calculated/questiontype.php');


/**
 * The calculated multiple-choice question type.
 *
 * @copyright  2009 Pierre Pichet
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_calculatedmulti extends qtype_calculated {

    public function requires_qtypes() {
        return array('calculated', 'multichoice');
    }

    public function save_question_options($question) {
        global $CFG, $DB;
        $context = $question->context;
        if (isset($question->answer) && !isset($question->answers)) {
            $question->answers = $question->answer;
        }
        // calculated options
        $update = true;
        $options = $DB->get_record('question_calculated_options',
                array('question' => $question->id));
        if (!$options) {
            $update = false;
            $options = new stdClass();
            $options->question = $question->id;
        }
        $options->synchronize = $question->synchronize;
        $options->single = $question->single;
        $options->answernumbering = $question->answernumbering;
        $options->shuffleanswers = $question->shuffleanswers;

        // save question feedback files
        foreach (array('correct', 'partiallycorrect', 'incorrect') as $feedbacktype) {
            $feedbackname = $feedbacktype . 'feedback';
            $feedbackformat = $feedbackname . 'format';
            $feedback = $question->$feedbackname;
            $options->$feedbackformat = $feedback['format'];
            if (isset($feedback['files'])) {
                $options->$feedbackname = trim($feedback['text']);
                $files = $feedback['files'];
                foreach ($files as $file) {
                    $this->import_file($question->context, 'qtype_calculatedmulti',
                            $feedbackname, $question->id, $file);
                }
            } else {
                $options->$feedbackname = file_save_draft_area_files($feedback['itemid'],
                        $context->id, 'qtype_calculatedmulti', $feedbackname,
                        $question->id, $this->fileoptionsa, trim($feedback['text']));
            }
        }

        if ($update) {
            $DB->update_record('question_calculated_options', $options);
        } else {
            $DB->insert_record('question_calculated_options', $options);
        }

        // Get old versions of the objects
        if (!$oldanswers = $DB->get_records('question_answers',
                array('question' => $question->id), 'id ASC')) {
            $oldanswers = array();
        }

        if (!$oldoptions = $DB->get_records('question_calculated',
                array('question' => $question->id), 'answer ASC')) {
            $oldoptions = array();
        }

        // Insert all the new answers
        if (isset($question->answer) && !isset($question->answers)) {
            $question->answers = $question->answer;
        }
        foreach ($question->answers as $key => $dataanswer) {
            if (is_array($dataanswer)) {
                $dataanswer = $dataanswer['text'];
            }
            if (trim($dataanswer) != '') {
                $answer = new stdClass();
                $answer->question = $question->id;
                $answer->answer = trim($dataanswer);
                $answer->fraction = $question->fraction[$key];
                $answer->feedback = trim($question->feedback[$key]['text']);
                $answer->feedbackformat = $question->feedback[$key]['format'];
                if (isset($question->feedback[$key]['files'])) {
                    $files = $question->feedback[$key]['files'];
                }

                if ($oldanswer = array_shift($oldanswers)) {
                    // Existing answer, so reuse it
                    $answer->id = $oldanswer->id;
                    $answer->feedback = file_save_draft_area_files(
                            $question->feedback[$key]['itemid'], $context->id, 'question',
                            'answerfeedback', $answer->id, $this->fileoptionsa, $answer->feedback);
                    $DB->update_record('question_answers', $answer);
                } else {
                    // This is a completely new answer
                    $answer->id = $DB->insert_record('question_answers', $answer);
                    if (isset($files)) {
                        $feedbacktext = $answer->feedback;
                        foreach ($files as $file) {
                            $this->import_file($context, 'question', 'answerfeedback',
                                    $answer->id, $file);
                        }
                    } else {
                        $feedbacktext = file_save_draft_area_files(
                                $question->feedback[$key]['itemid'], $context->id,
                                'question', 'answerfeedback', $answer->id,
                                $this->fileoptionsa, $answer->feedback);
                    }
                    $DB->set_field('question_answers', 'feedback', $feedbacktext,
                            array('id'=>$answer->id));
                }

                // Set up the options object
                if (!$options = array_shift($oldoptions)) {
                    $options = new stdClass();
                }
                $options->question  = $question->id;
                $options->answer    = $answer->id;
                $options->tolerance = trim($question->tolerance[$key]);
                $options->tolerancetype  = trim($question->tolerancetype[$key]);
                $options->correctanswerlength  = trim($question->correctanswerlength[$key]);
                $options->correctanswerformat  = trim($question->correctanswerformat[$key]);

                // Save options
                if (isset($options->id)) { // reusing existing record
                    $DB->update_record('question_calculated', $options);
                } else { // new options
                    $DB->insert_record('question_calculated', $options);
                }
            }
        }
        // delete old answer records
        if (!empty($oldanswers)) {
            foreach ($oldanswers as $oa) {
                $DB->delete_records('question_answers', array('id' => $oa->id));
            }
        }

        // delete old answer records
        if (!empty($oldoptions)) {
            foreach ($oldoptions as $oo) {
                $DB->delete_records('question_calculated', array('id' => $oo->id));
            }
        }

        if (isset($question->import_process) && $question->import_process) {
            $this->import_datasets($question);
        }
        // Report any problems.
        if (!empty($result->notice)) {
            return $result;
        }

        $this->save_hints($question, true);

        return true;
    }

    protected function make_question_instance($questiondata) {
        question_bank::load_question_definition_classes($this->name());
        if ($questiondata->options->single) {
            $class = 'qtype_calculatedmulti_single_question';
        } else {
            $class = 'qtype_calculatedmulti_multi_question';
        }
        return new $class();
    }

    protected function initialise_question_instance(question_definition $question, $questiondata) {
        question_type::initialise_question_instance($question, $questiondata);

        $question->shuffleanswers = $questiondata->options->shuffleanswers;
        $question->answernumbering = $questiondata->options->answernumbering;
        if (!empty($questiondata->options->layout)) {
            $question->layout = $questiondata->options->layout;
        } else {
            $question->layout = qtype_multichoice_single_question::LAYOUT_VERTICAL;
        }

        $question->synchronised = $questiondata->options->synchronize;

        $this->initialise_combined_feedback($question, $questiondata, true);
        $this->initialise_question_answers($question, $questiondata);

        foreach ($questiondata->options->answers as $a) {
            $question->answers[$a->id]->correctanswerlength = $a->correctanswerlength;
            $question->answers[$a->id]->correctanswerformat = $a->correctanswerformat;
        }

        $question->datasetloader = new qtype_calculated_dataset_loader($questiondata->id);
    }

    public function comment_header($question) {
        $strheader = '';
        $delimiter = '';

        $answers = $question->options->answers;

        foreach ($answers as $key => $answer) {
            if (is_string($answer)) {
                $strheader .= $delimiter.$answer;
            } else {
                $strheader .= $delimiter.$answer->answer;
            }
            $delimiter = '<br/>';
        }
        return $strheader;
    }

    public function comment_on_datasetitems($qtypeobj, $questionid, $questiontext,
            $answers, $data, $number) {
        global $DB;
        $comment = new stdClass();
        $comment->stranswers = array();
        $comment->outsidelimit = false;
        $comment->answers = array();

        $answers = fullclone($answers);
        $errors = '';
        $delimiter = ': ';
        foreach ($answers as $key => $answer) {
            $answer->answer = $this->substitute_variables($answer->answer, $data);
            //evaluate the equations i.e {=5+4)
            $qtext = '';
            $qtextremaining = $answer->answer;
            while (preg_match('~\{=([^[:space:]}]*)}~', $qtextremaining, $regs1)) {
                $qtextsplits = explode($regs1[0], $qtextremaining, 2);
                $qtext =$qtext.$qtextsplits[0];
                $qtextremaining = $qtextsplits[1];
                if (empty($regs1[1])) {
                    $str = '';
                } else {
                    if ($formulaerrors = qtype_calculated_find_formula_errors($regs1[1])) {
                        $str=$formulaerrors;
                    } else {
                        eval('$str = '.$regs1[1].';');
                    }
                }
                $qtext = $qtext.$str;
            }
            $answer->answer = $qtext.$qtextremaining;
            $comment->stranswers[$key] = $answer->answer;
        }
        return fullclone($comment);
    }

    public function get_virtual_qtype() {
        return question_bank::get_qtype('multichoice');
    }

    public function get_possible_responses($questiondata) {
        if ($questiondata->options->single) {
            $responses = array();

            foreach ($questiondata->options->answers as $aid => $answer) {
                $responses[$aid] = new question_possible_response($answer->answer,
                        $answer->fraction);
            }

            $responses[null] = question_possible_response::no_response();
            return array($questiondata->id => $responses);
        } else {
            $parts = array();

            foreach ($questiondata->options->answers as $aid => $answer) {
                $parts[$aid] = array($aid =>
                        new question_possible_response($answer->answer, $answer->fraction));
            }

            return $parts;
        }
    }

    public function move_files($questionid, $oldcontextid, $newcontextid) {
        $fs = get_file_storage();

        parent::move_files($questionid, $oldcontextid, $newcontextid);
        $this->move_files_in_answers($questionid, $oldcontextid, $newcontextid, true);

        $fs->move_area_files_to_new_context($oldcontextid,
                $newcontextid, 'qtype_calculatedmulti', 'correctfeedback', $questionid);
        $fs->move_area_files_to_new_context($oldcontextid,
                $newcontextid, 'qtype_calculatedmulti', 'partiallycorrectfeedback', $questionid);
        $fs->move_area_files_to_new_context($oldcontextid,
                $newcontextid, 'qtype_calculatedmulti', 'incorrectfeedback', $questionid);
    }

    protected function delete_files($questionid, $contextid) {
        $fs = get_file_storage();

        parent::delete_files($questionid, $contextid);
        $this->delete_files_in_answers($questionid, $contextid, true);

        $fs->delete_area_files($contextid, 'qtype_calculatedmulti',
                'correctfeedback', $questionid);
        $fs->delete_area_files($contextid, 'qtype_calculatedmulti',
                'partiallycorrectfeedback', $questionid);
        $fs->delete_area_files($contextid, 'qtype_calculatedmulti',
                'incorrectfeedback', $questionid);
    }
}
