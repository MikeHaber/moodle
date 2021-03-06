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
 * Defines the editing form for calculated multiple-choice questions.
 *
 * @package    qtype
 * @subpackage calculatedmulti
 * @copyright  2007 Jamie Pratt me@jamiep.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();


/**
 * Calculated multiple-choice question editing form.
 *
 * @copyright  2007 Jamie Pratt me@jamiep.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_calculatedmulti_edit_form extends question_edit_form {
    /**
     * Handle to the question type for this question.
     *
     * @var question_calculatedmulti_qtype
     */
    public $qtypeobj;
    public $questiondisplay;
    public $initialname = '';
    public $reload = false;

    public function __construct($submiturl, $question, $category,
            $contexts, $formeditable = true) {
        $this->question = $question;
        $this->qtypeobj = question_bank::get_qtype('calculatedmulti');
        if (1 == optional_param('reload', '', PARAM_INT)) {
            $this->reload = true;
        } else {
            $this->reload = false;
        }
        if (!$this->reload) {
            // use database data as this is first pass
            if (isset($this->question->id)) {
                // remove prefix #{..}# if exists
                $this->initialname = $question->name;
                $regs= array();
                if (preg_match('~#\{([^[:space:]]*)#~', $question->name , $regs)) {
                    $question->name = str_replace($regs[0], '', $question->name);
                };
            }
        }
        parent::__construct($submiturl, $question, $category, $contexts, $formeditable);
    }

    public function get_per_answer_fields($mform, $label, $gradeoptions,
            &$repeatedoptions, &$answersoption) {
        $repeated = array();
        $repeated[] = $mform->createElement('header', 'answerhdr', $label);
        $repeated[] = $mform->createElement('text', 'answer',
                get_string('answer', 'question'), array('size' => 50));
        $repeated[] = $mform->createElement('select', 'fraction',
                get_string('grade'), $gradeoptions);
        $repeated[] = $mform->createElement('editor', 'feedback',
                get_string('feedback', 'question'), null, $this->editoroptions);
        $repeatedoptions['answer']['type'] = PARAM_RAW;
        $repeatedoptions['fraction']['default'] = 0;
        $answersoption = 'answers';

        $mform->setType('answer', PARAM_NOTAGS);

        $addrepeated = array();
        $addrepeated[] = $mform->createElement('hidden', 'tolerance');
        $addrepeated[] = $mform->createElement('hidden', 'tolerancetype', 1);
        $repeatedoptions['tolerance']['type'] = PARAM_NUMBER;
        $repeatedoptions['tolerance']['default'] = 0.01;

        $addrepeated[] =  $mform->createElement('select', 'correctanswerlength',
                get_string('correctanswershows', 'qtype_calculated'), range(0, 9));
        $repeatedoptions['correctanswerlength']['default'] = 2;

        $answerlengthformats = array(
            '1' => get_string('decimalformat', 'qtype_numerical'),
            '2' => get_string('significantfiguresformat', 'qtype_calculated')
        );
        $addrepeated[] = $mform->createElement('select', 'correctanswerformat',
                get_string('correctanswershowsformat', 'qtype_calculated'), $answerlengthformats);
        array_splice($repeated, 3, 0, $addrepeated);
        $repeated[1]->setLabel('...<strong>{={x}+..}</strong>...');

        return $repeated;
    }

    protected function definition_inner($mform) {

        $label = get_string('sharedwildcards', 'qtype_calculated');
        $mform->addElement('hidden', 'initialcategory', 1);
        $mform->addElement('hidden', 'reload', 1);
        $mform->setType('initialcategory', PARAM_INT);

        $html2 = '';
        $mform->insertElementBefore(
                $mform->createElement('static', 'listcategory', $label, $html2), 'name');
        if (isset($this->question->id)) {
            $mform->insertElementBefore($mform->createElement('static', 'initialname',
                    get_string('questionstoredname', 'qtype_calculated'),
                    $this->initialname), 'name');
        };
        $addfieldsname = 'updatecategory';
        $addstring = get_string('updatecategory', 'qtype_calculated');
        $mform->registerNoSubmitButton($addfieldsname);
        $this->editasmultichoice = 1;

        $mform->insertElementBefore(
                $mform->createElement('submit', $addfieldsname, $addstring), 'listcategory');
        $mform->registerNoSubmitButton('createoptionbutton');
        $mform->addElement('hidden', 'multichoice', $this->editasmultichoice);
        $mform->setType('multichoice', PARAM_INT);

        $menu = array(get_string('answersingleno', 'qtype_multichoice'),
                get_string('answersingleyes', 'qtype_multichoice'));
        $mform->addElement('select', 'single',
                get_string('answerhowmany', 'qtype_multichoice'), $menu);
        $mform->setDefault('single', 1);

        $mform->addElement('advcheckbox', 'shuffleanswers',
                get_string('shuffleanswers', 'qtype_multichoice'), null, null, array(0, 1));
        $mform->addHelpButton('shuffleanswers', 'shuffleanswers', 'qtype_multichoice');
        $mform->setDefault('shuffleanswers', 1);

        $numberingoptions = question_bank::get_qtype('multichoice')->get_numbering_styles();
        $mform->addElement('select', 'answernumbering',
                get_string('answernumbering', 'qtype_multichoice'), $numberingoptions);
        $mform->setDefault('answernumbering', 'abc');

        $this->add_per_answer_fields($mform, get_string('choiceno', 'qtype_multichoice', '{no}'),
                question_bank::fraction_options_full(), max(5, QUESTION_NUMANS_START));

        $repeated = array();
        //   if ($this->editasmultichoice == 1) {
        $nounits = optional_param('nounits', 1, PARAM_INT);
        $mform->addElement('hidden', 'nounits', $nounits);
        $mform->setType('nounits', PARAM_INT);
        $mform->setConstants(array('nounits'=>$nounits));
        for ($i = 0; $i < $nounits; $i++) {
            $mform->addElement('hidden', 'unit'."[$i]",
                    optional_param('unit'."[$i]", '', PARAM_NOTAGS));
            $mform->setType('unit'."[$i]", PARAM_NOTAGS);
            $mform->addElement('hidden', 'multiplier'."[$i]",
                    optional_param('multiplier'."[$i]", '', PARAM_NUMBER));
            $mform->setType('multiplier'."[$i]", PARAM_NUMBER);
        }

        $this->add_combined_feedback_fields(true);
        $mform->disabledIf('shownumcorrect', 'single', 'eq', 1);

        $this->add_interactive_settings(true, true);

        //hidden elements
        $mform->addElement('hidden', 'synchronize', '');
        $mform->setType('synchronize', PARAM_INT);
        if (isset($this->question->options) && isset($this->question->options->synchronize)) {
            $mform->setDefault('synchronize', $this->question->options->synchronize);
        } else {
            $mform->setDefault('synchronize', 0);
        }
        $mform->addElement('hidden', 'wizard', 'datasetdefinitions');
        $mform->setType('wizard', PARAM_ALPHA);
    }

    public function data_preprocessing($question) {
        $default_values['multichoice']= $this->editasmultichoice;
        if (isset($question->options)) {
            $answers = $question->options->answers;
            if (count($answers)) {
                $key = 0;
                foreach ($answers as $answer) {
                    $draftid = file_get_submitted_draft_itemid('feedback['.$key.']');
                    $default_values['answer['.$key.']'] = $answer->answer;
                    $default_values['fraction['.$key.']'] = $answer->fraction;
                    $default_values['tolerance['.$key.']'] = $answer->tolerance;
                    $default_values['tolerancetype['.$key.']'] = $answer->tolerancetype;
                    $default_values['correctanswerlength['.$key.']'] = $answer->correctanswerlength;
                    $default_values['correctanswerformat['.$key.']'] = $answer->correctanswerformat;
                    $default_values['feedback['.$key.']'] = array();
                    // prepare draftarea
                    $default_values['feedback['.$key.']']['text'] = file_prepare_draft_area(
                            $draftid, $this->context->id, 'question', 'answerfeedback',
                            empty($answer->id) ? null : (int) $answer->id,
                            $this->fileoptions, $answer->feedback);
                    $default_values['feedback['.$key.']']['format'] = $answer->feedbackformat;
                    $default_values['feedback['.$key.']']['itemid'] = $draftid;
                    $key++;
                }
            }
            $default_values['synchronize'] = $question->options->synchronize;

            if (isset($question->options->units)) {
                $units  = array_values($question->options->units);
                // make sure the default unit is at index 0
                usort($units, create_function('$a, $b',
                    'if (1.0 === (float)$a->multiplier) { return -1; } else '.
                    'if (1.0 === (float)$b->multiplier) { return 1; } else { return 0; }'));
                if (count($units)) {
                    $key = 0;
                    foreach ($units as $unit) {
                        $default_values['unit['.$key.']'] = $unit->unit;
                        $default_values['multiplier['.$key.']'] = $unit->multiplier;
                        $key++;
                    }
                }
            }
        }
        if (isset($question->options->single)) {
            $default_values['single'] =  $question->options->single;
            $default_values['answernumbering'] =  $question->options->answernumbering;
            $default_values['shuffleanswers'] =  $question->options->shuffleanswers;
        }
        $default_values['submitbutton'] = get_string('nextpage', 'qtype_calculated');
        $default_values['makecopy'] = get_string('makecopynextpage', 'qtype_calculated');

        // prepare draft files
        foreach (array('correctfeedback', 'partiallycorrectfeedback',
                'incorrectfeedback') as $feedbackname) {
            if (!isset($question->options->$feedbackname)) {
                continue;
            }
            $text = $question->options->$feedbackname;
            $draftid = file_get_submitted_draft_itemid($feedbackname);
            $feedbackformat = $feedbackname . 'format';
            $format = $question->options->$feedbackformat;
            $default_values[$feedbackname] = array();
            $default_values[$feedbackname]['text'] = file_prepare_draft_area(
                $draftid,                // draftid
                $this->context->id,      // context
                'qtype_calculatedmulti', // component
                $feedbackname,           // filarea
                !empty($question->id)?(int)$question->id:null, // itemid
                $this->fileoptions,      // options
                $text                    // text
            );
            $default_values[$feedbackname]['format'] = $format;
            $default_values[$feedbackname]['itemid'] = $draftid;
        }
        /**
         * set the wild cards category display given that on loading the category element is
         * unselected when processing this function but have a valid value when processing the
         * update category button. The value can be obtain by
         * $qu->category = $this->_form->_elements[$this->_form->
         *      _elementIndex['category']]->_values[0];
         * but is coded using existing functions
         */
        $qu = new stdClass();
        $el = new stdClass();
        // no need to call elementExists() here.
        if ($this->_form->elementExists('category')) {
            $el = $this->_form->getElement('category');
        } else {
            $el = $this->_form->getElement('categorymoveto');
        }
        if ($value = $el->getSelected()) {
            $qu->category = $value[0];
        } else {
            // on load  $question->category is set by question.php
            $qu->category = $question->category;
        }
        $html2 = $this->qtypeobj->print_dataset_definitions_category($qu);
        $this->_form->_elements[$this->_form->_elementIndex['listcategory']]->_text = $html2;
        $question = (object)((array)$question + $default_values);
        return $question;
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        //verifying for errors in {=...} in question text;
        $qtext = '';
        $qtextremaining = $data['questiontext']['text'];
        $possibledatasets = $this->qtypeobj->find_dataset_names($data['questiontext']['text']);
        foreach ($possibledatasets as $name => $value) {
            $qtextremaining = str_replace('{'.$name.'}', '1', $qtextremaining);
        }

        while (preg_match('~\{=([^[:space:]}]*)}~', $qtextremaining, $regs1)) {
            $qtextsplits = explode($regs1[0], $qtextremaining, 2);
            $qtext = $qtext.$qtextsplits[0];
            $qtextremaining = $qtextsplits[1];
            if (!empty($regs1[1]) && $formulaerrors =
                    qtype_calculated_find_formula_errors($regs1[1])) {
                if (!isset($errors['questiontext'])) {
                    $errors['questiontext'] = $formulaerrors.':'.$regs1[1];
                } else {
                    $errors['questiontext'] .= '<br/>'.$formulaerrors.':'.$regs1[1];
                }
            }
        }
        $answers = $data['answer'];
        $answercount = 0;
        $maxgrade = false;
        $possibledatasets = $this->qtypeobj->find_dataset_names($data['questiontext']['text']);
        $mandatorydatasets = array();
        foreach ($answers as $key => $answer) {
            $mandatorydatasets += $this->qtypeobj->find_dataset_names($answer);
        }
        if (count($mandatorydatasets) == 0) {
            foreach ($answers as $key => $answer) {
                $errors['answer['.$key.']'] =
                        get_string('atleastonewildcard', 'qtype_calculated');
            }
        }
        if ($data['multichoice'] == 1) {
            foreach ($answers as $key => $answer) {
                $trimmedanswer = trim($answer);
                if ($trimmedanswer != '' || $answercount == 0) {
                    //verifying for errors in {=...} in answer text;
                    $qanswer = '';
                    $qanswerremaining =  $trimmedanswer;
                    $possibledatasets = $this->qtypeobj->find_dataset_names($trimmedanswer);
                    foreach ($possibledatasets as $name => $value) {
                        $qanswerremaining = str_replace('{'.$name.'}', '1', $qanswerremaining);
                    }

                    while (preg_match('~\{=([^[:space:]}]*)}~', $qanswerremaining, $regs1)) {
                        $qanswersplits = explode($regs1[0], $qanswerremaining, 2);
                        $qanswer = $qanswer . $qanswersplits[0];
                        $qanswerremaining = $qanswersplits[1];
                        if (!empty($regs1[1]) && $formulaerrors =
                                qtype_calculated_find_formula_errors($regs1[1])) {
                            if (!isset($errors['answer['.$key.']'])) {
                                $errors['answer['.$key.']'] = $formulaerrors.':'.$regs1[1];
                            } else {
                                $errors['answer['.$key.']'] .= '<br/>'.$formulaerrors.':'.$regs1[1];
                            }
                        }
                    }
                }
                if ($trimmedanswer != '') {
                    if ('2' == $data['correctanswerformat'][$key] &&
                            '0' == $data['correctanswerlength'][$key]) {
                        $errors['correctanswerlength['.$key.']'] =
                                get_string('zerosignificantfiguresnotallowed', 'qtype_calculated');
                    }
                    if (!is_numeric($data['tolerance'][$key])) {
                        $errors['tolerance['.$key.']'] =
                                get_string('mustbenumeric', 'qtype_calculated');
                    }
                    if ($data['fraction'][$key] == 1) {
                        $maxgrade = true;
                    }

                    $answercount++;
                }
                //check grades
                $totalfraction = 0;
                $maxfraction = 0;
                if ($answer != '') {
                    if ($data['fraction'][$key] > 0) {
                        $totalfraction += $data['fraction'][$key];
                    }
                    if ($data['fraction'][$key] > $maxfraction) {
                        $maxfraction = $data['fraction'][$key];
                    }
                }
            }
            if ($answercount == 0) {
                $errors['answer[0]'] = get_string('notenoughanswers', 'qtype_multichoice', 2);
                $errors['answer[1]'] = get_string('notenoughanswers', 'qtype_multichoice', 2);
            } else if ($answercount == 1) {
                $errors['answer[1]'] = get_string('notenoughanswers', 'qtype_multichoice', 2);

            }

            /// Perform sanity checks on fractional grades
            if ($data['single']) {
                if ($maxfraction > 0.999) {
                    $maxfraction = $maxfraction * 100;
                    $errors['fraction[0]'] =
                            get_string('errfractionsnomax', 'qtype_multichoice', $maxfraction);
                }
            } else {
                $totalfraction = round($totalfraction, 2);
                if ($totalfraction != 1) {
                    $totalfraction = $totalfraction * 100;
                    $errors['fraction[0]'] =
                            get_string('errfractionsaddwrong', 'qtype_multichoice', $totalfraction);
                }
            }

            if ($answercount == 0) {
                $errors['answer[0]'] = get_string('atleastoneanswer', 'qtype_calculated');
            }
            if ($maxgrade == false) {
                $errors['fraction[0]'] = get_string('fractionsnomax', 'question');
            }

        }
        return $errors;
    }

    public function qtype() {
        return 'calculatedmulti';
    }
}
