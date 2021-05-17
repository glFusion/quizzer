<?php
/**
 * Base class to handle quiz questions.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2018-2020 Lee Garner <lee@leegarner.com>
 * @package     quizzer
 * @version     v0.0.3
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Quizzer;

/**
 * Base class for quiz questions.
 * @package quizzer
 */
class Question
{
    /** Maximum answers allowed.
     * @todo: Make this a per-quiz setting
     * @var integer */
    const MAX_ANSWERS = 10;

    /** Flag to indicate that this is a new question.
     * @var boolean */
    private $isNew = true;

    /** Quiz ID related to this queston.
     * @var string */
    private $quizID = '';

    /** Question record ID.
     * @var integer */
    private $questionID  = 0;

    /** Flag to indicate question can appear on quizzes.
     * @var boolean */
    private $enabled = 1;

    /** Flag to indicate that answers should be shown in random order.
     * @var boolean */
    private $randomizeAnswers = 0;

    /** Question text.
     * @var string */
    private $questionText = '';

    /** Question type (checkbox, radio, etc).
     * @var string */
    private $questionType = 'radio';

    /** Help message to show with the question.
     * @var string */
    private $help_msg = '';

    /** Message to show after answering.
     * @var string */
    private $postAnswerMsg = '';

    /** Flag to indicate that partial credit is granted.
     * @var boolean */
    private $allowPartialCredit = 0;

    /** Answer value.
     * @var integer */
    protected $have_answer = 0;

    /** Answer options for this question
     * @var array */
    protected $Answers = array();

    /** Question sequence on the quiz.
     * This is only set when the question set is read from a result record
     * so the renderer knows which question this is, e.g. "3 of 5"
     * @var integer */
    protected $_seq = 0;

    /** Total number of questions being asked.
     * Used to create the progress bar.
     * @var integer */
    protected $_totalAsked = 0;

    /** Flag to indicate question was written using the advanced editor.
     * @var int */
    protected $advanced_editor_mode = 1;

    /** Time limit to answer the question, in seconds.
     * @var integer */
    private $timelimit = 0;


    /**
     * Constructor.
     *
     * @param   integer $questionID       ID of the existing question, empty if new
     * @param   integer $quizID    ID of the related quiz
     */
    public function __construct($questionID = 0, $quizID=NULL)
    {
        global $_USER, $_TABLES;

        if ($questionID == 0) {
            $this->quizID = $quizID;
        } elseif (is_array($questionID)) {
            $this->setVars($questionID, true);
        } else {
            $q = self::Read($questionID);
            if ($q) {
                $this->setVars($q);
            }
        }
        $this->Answers = Answer::getByQuestion($this->questionID);
    }


    /**
     * Get an instance of a question based on the question type.
     * If the "question" parameter is an array it must include at least questionID
     * and type.
     * Only works to retrieve existing fields.
     *
     * @param   mixed   $question   Question ID or record
     * @param   object  $quiz       Quiz object, or NULL
     * @return  object          Question object, NULL if not found
     */
    public static function getInstance($question, $quiz = NULL)
    {
        global $_TABLES;

        if (is_array($question)) {
            // Received a question record, make sure required parameters
            // are present.
            if (!isset($question['questionType']) || !isset($question['questionID'])) {
                return NULL;
            }
            $questionID = (int)$question['questionID'];
        } elseif (is_numeric($question)) {
            // Received a question ID, have to look up the record to get
            // the type.
            $questionID = (int)$question;
            if ($questionID > 0) {
                $question = self::Read($questionID);
                if (empty($question)) return NULL;
            }
        }

        // Instantiate the question object.
        // The answers will be read from cache or DB by the constructor.
        if ($questionID == 0) {
            return new self(0, $quiz);
        } else {
            $cls = __NAMESPACE__ . '\\Questions\\' . $question['questionType'];
            if (class_exists($cls)) {
                return new $cls($question);
            } else {
                return NULL;
            }
        }
    }


    /**
     * Read this field definition from the database and load the object.
     *
     * @see     self::setVars()
     * @param   integer $id     Record ID of question
     * @return  array           DB record array
     */
    public static function Read($id = 0)
    {
        global $_TABLES;
        $id = (int)$id;
        $cache_key = 'question_' . $id;
        $A = Cache::get($cache_key);
        if ($A === NULL) {
            $sql = "SELECT * FROM {$_TABLES['quizzer_questions']}
                    WHERE questionID = $id";
            $res = DB_query($sql, 1);
            if (DB_error() || !$res) return false;
            $A = DB_fetchArray($res, false);
            Cache::set($cache_key, $A, array('questions', $A['quizID']));
        }
        return $A;
    }


    /**
     * Set all variables for this field.
     * Data is expected to be from $_POST or a database record
     *
     * @param   array   $A      Array of name->value pairs
     * @param   boolean $fromDB Indicate whether this is read from the DB
     * @return  object  $this
     */
    public function setVars($A, $fromDB=false)
    {
        if (!is_array($A)) {
            return false;
        }
        $this->questionID = $A['questionID'];
        $this->quizID = $A['quizID'];
        $this->enabled = isset($A['enabled']) ? $A['enabled'] : 0;
        $this->questionText = $A['questionText'];
        $this->questionType = $A['questionType'];
        $this->postAnswerMsg = $A['postAnswerMsg'];
        $this->allowPartialCredit = isset($A['allowPartialCredit']) && $A['allowPartialCredit'] == 1 ? 1 : 0;
        $this->randomizeAnswers = isset($A['randomizeAnswers']) && $A['randomizeAnswers'] == 1 ? 1 : 0;
        $this->timelimit = isset($A['timelimit']) ? (int)$A['timelimit'] : 0;
        $this->advanced_editor_mode = isset($A['advanced_editor_mode']) ? (int)$A['advanced_editor_mode'] : 0;
        return $this;
    }


    /**
     * Render the question.
     *
     * @param   integer $q_num  Sequential question number, e.g. first=1, etc.
     * @param   integer $num_q  Total number of questions for this quiz
     * @return  string  HTML for the question form
     */
    public function Render()
    {
        global $_CONF_QUIZ, $_CONF;

        $retval = '';
        $saveaction = 'savedata';
        $allow_submit = true;

        // Determine if this question has already been answered
        $Result = Result::getCurrent($this->quizID);
        $Val = new Value($Result->getID(), $this->questionID);
        if ($Val->hasAnswer()) {
            $sub_btn_vis = 'none';
            $next_btn_vis = '';
            $this->have_answer = $Val->getValue();
        } else {
            $sub_btn_vis = '';
            $next_btn_vis = 'none';
            $this->have_answer = 0;
        }

        $Q = Quiz::getInstance($this->quizID);
        $T = new \Template(QUIZ_PI_PATH . '/templates');
        $T->set_file('question', 'question.thtml');
        // Set template variables without allowing caching
        $T->set_var(array(
            'quizID'    => $this->quizID,
            'quizName'  => $Q->getName(),
            'num_q'     => $this->_totalAsked,
            'q_num'     => $this->_seq,
            'questionID' => $this->questionID,
            'questionText' => PLG_replaceTags($this->questionText),
            'postAnswerMsg' => $this->have_answer ? $this->postAnswerMsg : '',
            'next_questionID'   => $this->_seq + 1,
            'is_last'       => $this->_seq == $this->_totalAsked,
            'sub_btn_vis'   => $sub_btn_vis,
            'next_btn_vis'  => $next_btn_vis,
            'answer_vis'    => $this->have_answer ? '' : 'none',
            'pct'           => (int)(($this->_seq / $this->_totalAsked) * 100),
            'timelimit'     => $this->timelimit,
            'pi_name'       => $_CONF_QUIZ['pi_name'],
            'cookiename'    => $this->getCookieName(),
            'cookiedomain'  => $_CONF['cookiedomain'],
            'adv_edit_mode' => $this->advanced_editor_mode,
        ) );

        $T->set_block('question', 'AnswerRow', 'Answer');
        $correct = $this->getCorrectAnswers();
        if ($this->randomizeAnswers) {
            // Randomize the answers if so configured.
            shuffle($this->Answers);
        }
        foreach ($this->Answers as $A) {
            $T->set_var(array(
                'questionID'    => $A->getQid(),
                'a_id'          => $A->getAid(),
                'answer'        => $A->getValue(),
                'answer_select' => $this->makeSelection($A->getAid()),
            ) );

            // If the question has been answered, show the answer and score.
            // Don't allow updates.
            $cls = 'qz-unanswered';
            if ($this->have_answer > 0) {
                $icon = '';
                if (
                    $this->have_answer == $A->getAid() &&
                    !in_array($this->have_answer, $correct)
                ) {
                    $cls = 'qz-incorrect';
                    $icon = Icon::getHTML('close', 'uk-icon-medium qz-color-incorrect');
                }
                if (in_array($A->getAid(), $correct)) {
                    $cls = 'qz-correct';
                    if (in_array($this->have_answer, $correct)) {
                        $icon = Icon::getHTML('check', 'uk-icon-medium qz-color-correct');
                    }
                }
                $T->set_var(array(
                    'icon' => $icon,
                ) );
            }
            $T->set_var(array(
                'border_class' => $cls,
            ) );
            $T->parse('Answer', 'AnswerRow', true);
        }
        $T->parse('output', 'question');
        $retval .= $T->finish($T->get_var('output'));
        return $retval;
    }


    /**
     * Create the input selection for one answer.
     * Does not display the text for the answer, only the input element.
     * Must be overridden by the actual question class (radio, etc.)
     *
     * @param   integer $a_id   Answer ID
     * @return  string          HTML for input element
     */
    protected function makeSelection($a_id)
    {
        return '';
    }


    /**
     * Check whether the supplied answer ID is correct for this question.
     *
     * @param   integer $a_id   Answer ID
     * @return  float       Percentage of options correct.
     */
    public function Verify($a_id)
    {
        return (float)0;
    }


    /**
     * Get the ID of the correct answer.
     * Returns an array regardless of the actuall numbrer of possibilities
     * to ensure uniform handling by the caller.
     *
     * @return   array      Array of correct answer IDs
     */
    public function getCorrectAnswers()
    {
        return array();
    }


    /**
     * Edit a question definition.
     *
     * @return  string      HTML for editing form
     */
    public function EditDef()
    {
        global $_TABLES, $_CONF_QUIZ, $_CONF;

        $retval = '';
        $format_str = '';
        $listinput = '';

        $T = new \Template(QUIZ_PI_PATH. '/templates/admin');
        $T->set_file('editform', 'editquestion.thtml');

        SEC_setCookie(
            $_CONF['cookie_name'].'adveditor',
            SEC_createTokenGeneral('advancededitor'),
            time() + 1200, $_CONF['cookie_path'],
            $_CONF['cookiedomain'],
            $_CONF['cookiesecure'],
            false
        );

        // Set up the wysiwyg editor, if available
        $tpl_var = $_CONF_QUIZ['pi_name'] . '_entry';
        switch (PLG_getEditorType()) {
        case 'ckeditor':
            $T->set_var('show_htmleditor', true);
            PLG_requestEditor($_CONF_QUIZ['pi_name'], $tpl_var, 'ckeditor_quiz.thtml');
            PLG_templateSetVars($tpl_var, $T);
            break;
        case 'tinymce' :
            $T->set_var('show_htmleditor',true);
            PLG_requestEditor($_CONF_QUIZ['pi_name'], $tpl_var, 'tinymce_quiz.thtml');
            PLG_templateSetVars($tpl_var, $T);
            break;
        default :
            // don't support others right now
            $T->set_var('show_htmleditor', false);
            break;
        }

        // Get defaults from the form, if defined
        /*if ($this->quizID > 0) {
            $form = Quiz::getInstance($this->quizID);
        }*/
        if ($this->advanced_editor_mode) {
            $T->set_var(array(
                'default_visual_editor' => true,
                'adv_edit_mode' => 1,
            ) );
        } else {
            $T->set_var(array(
                'default_visual_editor' => false,
                'adv_edit_mode' => 0,
            ) );
        }
        $T->set_var(array(
            'quiz_name' => DB_getItem($_TABLES['quizzer_quizzes'], 'quizName',
                            "quizID='" . DB_escapeString($this->quizID) . "'"),
            'quizID'   => $this->quizID,
            'questionID'    => $this->questionID,
            'question'      => $this->questionText,
            'ena_chk'   => $this->enabled == 1 ? 'checked="checked"' : '',
            'doc_url'   => QUIZ_getDocURL('question_def.html'),
            'editing'   => $this->isNew() ? '' : 'true',
            'help_msg'  => $this->help_msg,
            'postAnswerMsg' => $this->postAnswerMsg,
            'can_delete' => $this->isNew() || $this->_wasAnswered() ? false : true,
            $this->questionType . '_sel' => 'selected="selected"',
            'pcred_vis' => $this->allowPartial() ? '' : 'none',
            'random_chk' => $this->randomizeAnswers ? 'checked="checked"' : '',
            'pcred_chk' => $this->isPartialAllowed() ? 'checked="checked"' : '',
            'timelimit' => $this->timelimit,
        ) );

        $T->set_block('editform', 'Answers', 'Ans');
        foreach ($this->Answers as $answer) {
            $T->set_var(array(
                'ans_id'    => $answer->getAid(),
                'ans_val'   => $answer->getValue(),
                'ischecked' => $answer->isCorrect() ? 'checked="checked"' : '',
                'isRadio'   => $this->questionType == 'radio' ? true : false,
            ) );
            $T->parse('Ans', 'Answers', true);
        }
        $count = count($this->Answers);
        for ($i = $count + 1; $i <= self::MAX_ANSWERS; $i++) {
            $T->set_var(array(
                'ans_id'    => $i,
                'ans_val'   => '',
                'isRadio'   => $this->questionType == 'radio' ? true : false,
                'ischecked' => '',
            ) );
            $T->parse('Ans', 'Answers', true);
        }
        $T->parse('output', 'editform');
        $retval .= $T->finish($T->get_var('output'));
        return $retval;
    }


    /**
     * Save the field definition to the database.
     *
     * @param   array   $A  Array of name->value pairs
     * @return  string          Error message, or empty string for success
     */
    public function SaveDef($A = '')
    {
        global $_TABLES;

        $questionID = isset($A['questionID']) ? (int)$A['questionID'] : 0;
        $quizID = isset($A['quizID']) ? COM_sanitizeID($A['quizID']) : '';
        if ($quizID == '') {
            return 'Invalid form ID';
        }
        if (empty($A['questionType'])) {
            return;
        }

        $this->setVars($A, false);

        if ($questionID > 0) {
            // Existing record, perform update
            $sql1 = "UPDATE {$_TABLES['quizzer_questions']} SET ";
            $sql3 = " WHERE questionID = $questionID";
        } else {
            $sql1 = "INSERT INTO {$_TABLES['quizzer_questions']} SET ";
            $sql3 = '';
        }

        $sql2 = "quizID = '" . DB_escapeString($this->quizID) . "',
            questionType = '" . DB_escapeString($this->questionType) . "',
            enabled = '{$this->enabled}',
            help_msg = '" . DB_escapeString($this->help_msg) . "',
            questionText = '" . DB_escapeString($this->questionText) . "',
            postAnswerMsg = '" . DB_escapeString($this->postAnswerMsg) . "',
            allowPartialCredit = '{$this->allowPartialCredit}',
            randomizeAnswers = '{$this->randomizeAnswers}',
            timelimit='{$this->getTimelimit()}',
            advanced_editor_mode='{$this->advanced_editor_mode}'";
        $sql = $sql1 . $sql2 . $sql3;
        //echo $sql;die;
        DB_query($sql, 1);
        if (DB_error()) {
            return 5;
        }
        if ($questionID == 0) {
            $this->questionID = DB_insertID();
        }

        // Now save the answer options
        $count = count($A['opt']);   // index into opt and correct arrays
        $to_del = array();
        for ($i = 1; $i <= $count; $i++) {
            if (!empty($A['opt'][$i])) {
                $answer = DB_escapeString($A['opt'][$i]);
                if ($this->questionType == 'radio') {
                    $correct = isset($A['correct']) && $A['correct'] == $i ? 1 : 0;
                } else {
                    $correct = isset($A['correct'][$i]) && $A['correct'][$i] == 1 ? 1 : 0;
                }
                $Ans = new Answer;
                $Ans->setQid($this->questionID)
                    ->setAid($i)
                    ->setValue($answer)
                    ->setCorrect($correct)
                    ->Save();
            } else {
                $to_del[] = $i;
            }
        }
        if (!empty($to_del)) {
            Answer::Delete($this->questionID, implode(',', $to_del));
        }
        Cache::clear(array('questions', $this->quizID));
        Cache::clear(array('answers', $this->questionID));
        return 0;
    }


    /**
     * Delete the current question definition.
     *
     * @param  integer $questionID     ID number of the question
     */
    public static function Delete($questionID)
    {
        global $_TABLES;

        $sql = "DELETE q, v FROM {$_TABLES['quizzer_questions']} q
            JOIN {$_TABLES['quizzer_answers']} ans
            ON ans.questionID = q.questionID
            WHERE q.questionID = $questionID";
        DB_query ($sql);
    }


    /**
     * Delete all questions and answers for a quiz.
     *
     * @param   string  $quiz_id    Quiz ID
     */
    public static function deleteQuiz($quiz_id)
    {
        global $_TABLES;

        $sql = "DELETE q, v FROM {$_TABLES['quizzer_questions']} q
            JOIN {$_TABLES['quizzer_answers']} ans
            ON ans.questionID = q.questionID
            WHERE q.quizID = '" . DB_escapeString($quiz_id) . "'";
        DB_query ($sql);
    }


    /**
     * Save a submitted answer to the database.
     *
     * @param   mixed   $value  Data value to save
     * @param   integer $res_id Result ID associated with this field
     * @return  boolean     True on success, False on failure
     */
    public function SaveData($value, $res_id)
    {
        global $_TABLES;

        $res_id = (int)$res_id;
        if ($res_id == 0)
            return false;

        return Value::Save($res_id, $this->questionID, $value);
    }


    /**
     * Copy this question to another quiz.
     *
     * @see     Quiz::Duplicate()
     */
    public function Duplicate()
    {
        global $_TABLES;

        $sql .= "INSERT INTO {$_TABLES['quizzer_questions']} SET
                quizID = '" . DB_escapeString($this->quizID) . "',
                type = '" . DB_escapeString($this->questionType) . "',
                enabled = {$this->enabled},
                help_msg = '" . DB_escapeString($this->help_msg) . "'";
        DB_query($sql, 1);
        $msg = DB_error() ? 5 : '';
        return $msg;
    }


    /**
     * Toggle a boolean field in the database.
     *
     * @param   integer $id     Question def ID
     * @param   string  $fld    DB field name to change
     * @param   integer $oldval Original value
     * @return  integer         New value
     */
    public static function toggle($id, $fld, $oldval)
    {
        global $_TABLES;

        $id = DB_escapeString($id);
        $fld = DB_escapeString($fld);
        $oldval = $oldval == 0 ? 0 : 1;
        $newval = $oldval == 0 ? 1 : 0;
        $sql = "UPDATE {$_TABLES['quizzer_questions']}
                SET $fld = $newval
                WHERE questionID = '$id'";
        $res = DB_query($sql, 1);
        if (DB_error($res)) {
            COM_errorLog(__CLASS__ . '\\' . __FUNCTION__ . ':: ' . $sql);
            return $oldval;
        } else {
            return $newval;
        }
    }


    /**
     * Get all the questions to show for a quiz.
     * This returns an array of question objects for a new quiz submission.
     *
     * @param   integer $quizID    Quiz ID
     * @param   integer $max        Max questions, default to all
     * @param   boolean $rand       True to randomizeAnswers the return array
     * @return  array       Array of question data
     */
    public static function getQuestions($quizID, $max = 0, $rand = true)
    {
        global $_TABLES;

        $max = (int)$max;
        $sql = "SELECT * FROM {$_TABLES['quizzer_questions']}
                WHERE quizID = '" . DB_escapeString($quizID) . "'
                AND enabled = 1";
        if ($rand) $sql .= ' ORDER BY RAND()';
        if ($max > 0) $sql .= " LIMIT $max";
        //echo $sql;die;
        $res = DB_query($sql);

        // Question #0 indicates the start of the quiz, so index actual
        // questions starting at #1
        $questions = array();   // array of question objects to return
        $i = 0;
        while ($A = DB_fetchArray($res, false)) {
            $questions[++$i] = self::getInstance($A);
        }
        return $questions;
    }


    /**
     * Get all the questions for a result set.
     *
     * @param   array   $ids    Array of question ids, from the resultset
     * @return  array       Array of question objects
     */
    public static function getByIds($ids)
    {
        global $_TABLES;

        $questions = array();
        foreach ($ids as $id) {
            $questons[] = new self($id);
        }
        return $questions;
    }


    /**
     * Determine whether this question was ever answered.
     * Used to see if the question may be deleted without affecting existing
     * results.
     *
     * @return  boolean     True if there is an answer, False if not
     */
    private function _wasAnswered()
    {
        global $_TABLES;

        if (DB_count(
            $_TABLES['quizzer_values'],
            'questionID',
            $this->questionID
        ) > 0) {
            return true;
        } else {
            return false;
        }
    }


    /**
     * Get a count of questions created for a quiz.
     * Used to determine the number of questions to ask, if this number
     * is less than the number assigned to the quiz.
     *
     * @param   string  $quizID    ID of the quiz
     * return   integer     Number of quiz questions in the database
     */
    public static function countQ($quizID)
    {
        global $_TABLES;

        return DB_count($_TABLES['quizzer_questions'], 'quizID', $quizID);
    }


    /**
     * Check if this question type allows partial credit.
     * Used to determine whether the partial credit option is shown on the
     * question definition form.
     *
     * @return  boolean     True if partial credit is allowed
     */
    public function allowPartial()
    {
        return false;
    }


    /**
     * Check if this question allows partial credit.
     * Only returns true if the question type and question itself
     * allow partial credit.
     *
     * @return  boolean     True if partial credit allowed, False if not
     */
    protected function allowsPartialCredit()
    {
        return $this->allowPartial() && $this->allowPartialCredit;
    }


    /**
     * Check if partial credit is allowed for this question.
     * Must have the partial checkbox checked, and be allowed by
     * the question type.
     *
     * @return  boolean     True if partial credit allowed
     */
    public function isPartialAllowed()
    {
        return $this->allowPartial() && $this->allowPartialCredit;
    }


    /**
     * Uses lib-admin to list the question definitions and allow updating.
     *
     * @param   string  $quizID    ID of quiz
     * @return  string              HTML for the question list
     */
    public static function adminList($quizID = '')
    {
        global $_CONF, $_TABLES, $LANG_ADMIN, $LANG_QUIZ, $_CONF_QUIZ;

        // Import administration functions
        USES_lib_admin();

        $header_arr = array(
            array(
                'text'  => $LANG_ADMIN['edit'],
                'field' => 'edit',
                'sort'  => false,
                'align' => 'center',
            ),
            array(
                'text'  => $LANG_QUIZ['question'],
                'field' => 'questionText',
                'sort'  => false,
            ),
            array(
                'text'  => $LANG_QUIZ['type'],
                'field' => 'questionType',
                'sort'  => false,
            ),
            array(
                'text'  => $LANG_QUIZ['enabled'],
                'field' => 'enabled',
                'sort'  => false,
                'align' => 'center',
            ),
            array(
                'text'  => $LANG_ADMIN['delete'],
                'field' => 'delete',
                'sort'  => false,
                'align' => 'center',
            ),
        );

        $defsort_arr = array(
            'field'     => 'questionID',
            'direction' => 'ASC',
        );
        $text_arr = array(
            'form_url' => QUIZ_ADMIN_URL . '/index.php',
        );
        $options_arr = array(
            'chkdelete' => true,
            'chkname'   => 'delquestion',
            'chkfield'  => 'questionID',
        );
        $query_arr = array(
            'table' => 'quizzer_questions',
            'sql'   => "SELECT * FROM {$_TABLES['quizzer_questions']}",
            'query_fields' => array('name', 'type', 'value'),
            'default_filter' => '',
        );
        if ($quizID != '') {
            $query_arr['sql'] .= " WHERE quizID='" . DB_escapeString($quizID) . "'";
        }
        $form_arr = array();
        $T = new \Template(QUIZ_PI_PATH . '/templates/admin');
        $T->set_file('questions', 'questions.thtml');
        $T->set_var(array(
            'action_url'    => QUIZ_ADMIN_URL . '/index.php',
            'quizID'       => $quizID,
            'pi_url'        => QUIZ_PI_URL,
            'question_adminlist' => ADMIN_list(
                'quizzer_questions',
                array(__CLASS__, 'getAdminField'),
                $header_arr,
                $text_arr, $query_arr, $defsort_arr, '', '',
                $options_arr, $form_arr
            ),
        ) );
        $T->parse('output', 'questions');
        return $T->finish($T->get_var('output'));
    }


    /**
     * Determine what to display in the admin list for each field.
     *
     * @param   string  $fieldname  Name of the field, from database
     * @param   mixed   $fieldvalue Value of the current field
     * @param   array   $A          Array of all name/field pairs
     * @param   array   $icon_arr   Array of system icons
     * @return  string              HTML for the field cell
     */
    public static function getAdminField($fieldname, $fieldvalue, $A, $icon_arr)
    {
        global $_CONF, $_CONF_QUIZ, $LANG_ACCESS, $LANG_QUIZ;

        $retval = '';

        switch($fieldname) {
        case 'edit':
            $retval = COM_createLink(
                Icon::getHTML('edit'),
                QUIZ_ADMIN_URL . "/index.php?editquestion=x&amp;questionID={$A['questionID']}"
            );
            break;

        case 'delete':
            $retval = COM_createLink(
                Icon::getHTML('delete'),
                QUIZ_ADMIN_URL . '/index.php?delQuestion=x&questionID=' .
                    $A['questionID'] . '&quizID=' . $A['quizID'],
                array(
                    'onclick' => "return confirm('{$LANG_QUIZ['confirm_delete']}');",
                )
            );
           break;

        case 'enabled':
            if ($A[$fieldname] == 1) {
                $chk = ' checked ';
                $enabled = 1;
            } else {
                $chk = '';
                $enabled = 0;
            }
            $retval = Field::checkbox(array(
                'name' => $fieldname . '_' . $A['questionID'],
                'checked' => $fieldname == 1,
                'onclick' => "QUIZtoggleEnabled(this, '{$A['questionID']}', 'question', '{$fieldname}', '" . QUIZ_ADMIN_URL . "');",
            ) );
            break;

        case 'id':
        case 'questionID':
            return '';
            break;

        default:
            $retval = $fieldvalue;
            break;
        }
        return $retval;
    }


    /**
     * Check if this is a new record or an existing one.
     *
     * @return  integer     1 if new, 0 if existing
     */
    public function isNew()
    {
        return $this->questionID == 0 ? 1 : 0;
    }


    /**
     * Get the record ID for this question.
     *
     * @return  integer     Record ID
     */
    public function getID()
    {
        return (int)$this->questionID;
    }


    /**
     * Get the text for this question.
     *
     * @return  string      Question text to display
     */
    public function getQuestion()
    {
        return $this->questionText;
    }


    /**
     * Get the possible answers for this question.
     *
     * @return  array       Array of answer records
     */
    public function getAnswers()
    {
        return $this->Answers;
    }


    /**
     * Get the message to display post-answer.
     *
     * @return  string      Answer message
     */
    public function getAnswerMsg()
    {
        return $this->postAnswerMsg;
    }


    /**
     * Get the sequence number for this question's appearance.
     *
     * @param   integer $seq    Sequence number
     * @return  object  $this
     */
    public function setSeq($seq)
    {
        $this->_seq = $seq;
        return $this;
    }


    /**
     * Get the sequence number for this question's appearance.
     *
     * @return  integer     Sequence number
     */
    public function getSeq()
    {
        return (int)$this->_seq;
    }


    /**
     * Set the total number of questions being asked.
     * Used to create the progress bar.
     *
     * @param   intger  $num    Number of questions on the quiz
     * @return  object  $this
     */
    public function setTotalQ($num)
    {
        $this->_totalAsked = (int)$num;
        return $this;
    }


    /**
     * Get the time limit in seconds to answer the question.
     * Allows access by child classes.
     *
     * @return  integer     Time limit, in seconds
     */
    protected function getTimelimit()
    {
        return (int)$this->timelimit;
    }


    public function getCookieName()
    {
        return $this->quizID . '-' . $this->questionID;
    }


    public function expireCookie()
    {
        SEC_setCookie(
            $this->getCookieName(),
            -1,
            -1
        );

    }

}

?>
