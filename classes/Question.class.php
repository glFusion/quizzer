<?php
/**
 * Base class to handle quiz questions.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2018 Lee Garner <lee@leegarner.com>
 * @package     quizzes
 * @version     v0.0.1
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Quizzer;

/**
 * Base class for quiz questions.
 */
class Question
{
    /** Maximum answers allowed.
     * @todo: Make this a per-quiz setting
     * @var integer */
    const MAX_ANSWERS = 5;

    /** Flag to indicate that this is a new question.
     * @var boolean */
    public $isNew;

    //public $options = array();  // Form object needs access
    /** Answer options for this question
     * @var array */
    public $Answers = array();

    /** Internal properties accessed via `__set()` and `__get()`
     * @var array */
    private $properties = array();

    //protected $sub_type = 'regular';

    /** Answer value.
     * @var integer */ 
    protected $have_answer = 0;

    /**
     * Constructor.
     *
     * @param   integer $id         ID of the existing question, empty if new
     * @param   integer $quiz_id    ID of the related quiz
     */
    public function __construct($id = 0, $quiz_id=NULL)
    {
        global $_USER, $_TABLES;

        $this->isNew = true;
        if ($id == 0) {
            $this->q_id = 0;
            $this->name = '';
            $this->type = 'radio';
            $this->enabled = 1;
            $this->access = 0;
            $this->prompt = '';
            $this->quiz_id = $quiz_id;
        } elseif (is_array($id)) {
            $this->setVars($id, true);
            $this->isNew = false;
        } else {
            $q = self::Read($id);
            if ($q) {
                $this->setVars($q);
                $this->isNew = false;
            }
        }

        if ($this->q_id > 0) {      // get answers
            $sql = "SELECT * FROM {$_TABLES['quizzer_answers']}
                WHERE q_id = '{$this->q_id}'";
            $res = DB_query($sql);
            $this->Answers = array();
            while ($A = DB_fetchArray($res, false)) {
                $this->Answers[$A['a_id']] = $A;
            }
        }
    }


    /**
     * Get an instance of a field based on the field type.
     * If the "fld" parameter is an array it must include at least q_id
     * and type.
     * Only works to retrieve existing fields.
     *
     * @param   mixed   $question   Question ID or record
     * @param   object  $quiz       Quiz object, or NULL
     * @return  object          Question object
     */
    public static function getInstance($question, $quiz = NULL)
    {
        global $_TABLES;
        static $_fields = array();

        if (is_array($question)) {
            // Received a field record, make sure required parameters
            // are present
            if (!isset($question['type']) || !isset($question['q_id'])) {
                return NULL;
            }
            $q_id = (int)$question['q_id'];
        } elseif (is_numeric($question)) {
            // Received a field ID, have to look up the record to get the type
            $q_id = (int)$question;
            if (!array_key_exists($q_id, $_fields)) {
                $question = self::Read($q_id);
                if (DB_error() || empty($question)) return NULL;
            }
        }

        if (!array_key_exists($q_id, $_fields)) {
            $cls = __NAMESPACE__ . '\\Questions\\' . $question['type'];
            $_fields[$q_id] = new $cls($question);
        }
        return $_fields[$q_id];
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
        $sql = "SELECT * FROM {$_TABLES['quizzer_questions']}
                WHERE q_id = $id";
        $res = DB_query($sql, 1);
        if (DB_error() || !$res) return false;
        return DB_fetchArray($res, false);
    }


    /**
     * Set a value into a property.
     *
     * @param   string  $name       Name of property
     * @param   mixed   $value      Value to set
     */
    public function __set($name, $value)
    {
        switch ($name) {
        case 'quiz_id':
            $this->properties[$name] = COM_sanitizeID($value);
            break;

        case 'q_id':
            $this->properties[$name] = (int)$value;
            break;

        case 'enabled':
        case 'partial_credit':
            $this->properties[$name] = $value == 0 ? 0 : 1;
            break;

        case 'question':
        case 'name':
        case 'type':
        case 'help_msg':
        case 'value':
        case 'answer_msg':
            $this->properties[$name] = trim($value);
            break;
        }
    }


    /**
     * Get a property's value.
     *
     * @param   string  $name       Name of property
     * @return  mixed       Value of property, or empty string if undefined
     */
    public function __get($name)
    {
        if (array_key_exists($name, $this->properties)) {
           return $this->properties[$name];
        } else {
            return '';
        }
    }


    /**
     * Set all variables for this field.
     * Data is expected to be from $_POST or a database record
     *
     * @param   array   $A      Array of name->value pairs
     * @param   boolean $fromDB Indicate whether this is read from the DB
     */
    public function setVars($A, $fromDB=false)
    {
        if (!is_array($A)) {
            return false;
        }

        $this->q_id = $A['q_id'];
        $this->quiz_id = $A['quiz_id'];
        $this->enabled = isset($A['enabled']) ? $A['enabled'] : 0;
        $this->question= $A['question'];
        $this->type = $A['type'];
        $this->answer_msg = $A['answer_msg'];
        $this->partial_credit = isset($A['partial_credit']) && $A['partial_credit'] == 1 ? 1 : 0;
        return true;
    }


    /**
     * Render the question.
     *
     * @param   integer $q_num  Sequential question number, e.g. first=1, etc.
     * @param   integer $num_q  Total number of questions for this quiz
     * @return  string  HTML for the question form
     */
    public function Render($q_num, $num_q)
    {
        $retval = '';
        $saveaction = 'savedata';
        $allow_submit = true;

        // Determine if this question has already been answered
        $res_id = SESS_getVar('quizzer_resultset');
        $Val = new Value();
        if ($res_id) {
            $res_id = (int)$res_id;
            $Val->Read($res_id, $this->q_id);
        }
        if (!$Val->isNew) {
            $sub_btn_vis = 'none';
            $next_btn_vis = '';
            $ans = $Val->value;
            $this->have_answer = $Val->value;
        } else {
            $sub_btn_vis = '';
            $next_btn_vis = 'none';
            $this->have_answer = 0;
        }

        $Q = Quiz::getInstance($this->quiz_id);
        $T = QUIZ_getTemplate('question', 'question');
        // Set template variables without allowing caching
        $T->set_var(array(
            'quiz_id'       => $this->quiz_id,
            'quiz_name'     => $Q->name,
            'num_q'         => $num_q,
            'q_num'         => $q_num,
            'q_id'          => $this->q_id,
            'question'      => $this->question,
            'answer_msg'    => $this->have_answer ? $this->answer_msg : '',
            'next_q_id'     => $q_num + 1,
            'is_last'       => $q_num == $num_q,
            'sub_btn_vis'   => $sub_btn_vis,
            'next_btn_vis'  => $next_btn_vis,
            'answer_vis'    => $this->have_answer ? '' : 'none',
            'pct'           => (int)(($q_num / $num_q) * 100),
        ) );

        $T->set_block('question', 'AnswerRow', 'Answer');
        $correct = $this->getCorrectAnswers();
        foreach ($this->Answers as $A) {
            $T->set_var(array(
                'q_id'      => $A['q_id'],
                'a_id'      => $A['a_id'],
                'answer'    => $A['value'],
                'answer_select' => $this->makeSelection($A['a_id']),
            ) );

            // If the question has been answered, show the answer and score.
            // Don't allow updates.
            $cls = 'qz-unanswered';
            if ($this->have_answer > 0) {
                $icon = '';
                if ($this->have_answer == $A['a_id'] && !in_array($this->have_answer, $correct)) {
                    $cls = 'qz-incorrect';
                    $icon = '<i class="uk-icon uk-icon-close uk-icon-medium qz-color-incorrect"></i>';
                }
                if (in_array($A['a_id'], $correct)) {
                    $cls = 'qz-correct';
                    if (in_array($this->have_answer, $correct)) {
                        $icon = '<i class="uk-icon uk-icon-check uk-icon-medium qz-color-correct"></i>';
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
        global $_TABLES;

        $retval = '';
        $format_str = '';
        $listinput = '';

        // Get defaults from the form, if defined
        if ($this->quiz_id > 0) {
            $form = Quiz::getInstance($this->quiz_id);
        }
        $T = new \Template(QUIZ_PI_PATH. '/templates/admin');
        $T->set_file('editform', 'editquestion.thtml');
 
        $T->set_var(array(
            'quiz_name' => DB_getItem($_TABLES['quizzer_quizzes'], 'name',
                            "id='" . DB_escapeString($this->quiz_id) . "'"),
            'quiz_id'   => $this->quiz_id,
            'q_id'      => $this->q_id,
            'question'      => $this->question,
            'type'      => $this->type,
            'ena_chk'   => $this->enabled == 1 ? 'checked="checked"' : '',
            'doc_url'   => QUIZ_getDocURL('question_def.html'),
            'editing'   => $this->isNew ? '' : 'true',
            'help_msg'  => $this->help_msg,
            'answer_msg' => $this->answer_msg,
            'can_delete' => $this->isNew || $this->_wasAnswered() ? false : true,
            $this->type . '_sel' => 'selected="selected"',
            'pcred_vis' => $this->allowPartial() ? '' : 'none',
        ) );

        $T->set_block('editform', 'Answers', 'Ans');
        foreach ($this->Answers as $answer) {
            $T->set_var(array(
                'ans_id'    => $answer['a_id'],
                'ans_val'   => $answer['value'],
                'isRadio'   => $this->type == 'radio' ? true : false,
                'ischecked' => $answer['correct'] ? 'checked="checked"' : '',
            ) );
            $T->parse('Ans', 'Answers', true);
        }
        $count = count($this->Answers);
        for ($i = $count + 1; $i <= self::MAX_ANSWERS; $i++) {
            $T->set_var(array(
                'ans_id'    => $i,
                'ans_val'   => '',
                'isRadio'   => $this->type == 'radio' ? true : false,
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

        $q_id = isset($A['q_id']) ? (int)$A['q_id'] : 0;
        $quiz_id = isset($A['quiz_id']) ? COM_sanitizeID($A['quiz_id']) : '';
        if ($quiz_id == '') {
            return 'Invalid form ID';
        }

        if (empty($A['type']))
            return;

        $this->setVars($A, false);

        if ($q_id > 0) {
            // Existing record, perform update
            $sql1 = "UPDATE {$_TABLES['quizzer_questions']} SET ";
            $sql3 = " WHERE q_id = $q_id";
        } else {
            $sql1 = "INSERT INTO {$_TABLES['quizzer_questions']} SET ";
            $sql3 = '';
        }

        $sql2 = "quiz_id = '" . DB_escapeString($this->quiz_id) . "',
                type = '" . DB_escapeString($this->type) . "',
                enabled = '{$this->enabled}',
                help_msg = '" . DB_escapeString($this->help_msg) . "',
                question = '" . DB_escapeString($this->question) . "',
                answer_msg = '" . DB_escapeString($this->answer_msg) . "',
                partial_credit = '{$this->partial_credit}'";
        $sql = $sql1 . $sql2 . $sql3;
        //echo $sql;die;
        DB_query($sql, 1);
        if (DB_error()) {
            return 5;
        }
        if ($q_id == 0) {
            $q_id = DB_insertID();
        }

        // Now save the answer options
        $count = count($A['opt']);   // index into opt and correct arrays
        for ($i = 1; $i <= $count; $i++) {
            if (!empty($A['opt'][$i])) {
                $question = DB_escapeString($A['opt'][$i]);
                if ($this->type == 'radio') {
                    $correct = isset($A['correct']) && $A['correct'] == $i ? 1 : 0;
                } else {
                    $correct = isset($A['correct'][$i]) && $A['correct'][$i] == 1 ? 1 : 0;
                }
                $sql = "INSERT INTO {$_TABLES['quizzer_answers']} SET
                        q_id = '{$q_id}',
                        a_id = '$i',
                        value = '$question',
                        correct = '$correct'
                    ON DUPLICATE KEY UPDATE
                        value = '$question',
                        correct = '$correct'";
COM_errorLog($sql);
                DB_query($sql);
                if (DB_error()) {
                    return 6;
                }
            } else {
                // Answer left blank to remove
                DB_delete($_TABLES['quizzer_answers'], array('q_id', 'a_id'), array($this->q_id, $i));
            }
        }
        return 0;
    }


    /**
     * Delete the current question definition.
     *
     * @param  integer $q_id     ID number of the question
     */
    public static function Delete($q_id=0)
    {
        global $_TABLES;

        DB_delete($_TABLES['quizzer_values'], 'q_id', $q_id);
        DB_delete($_TABLES['quizzer_questions'], 'q_id', $q_id);
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

        return Value::Save($res_id, $this->q_id, $value);
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
                quiz_id = '" . DB_escapeString($this->quiz_id) . "',
                type = '" . DB_escapeString($this->type) . "',
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
                WHERE q_id = '$id'";
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
     * @param   integer $quiz_id    Quiz ID
     * @param   integer $max        Max questions, default to all
     * @param   boolean $rand       True to randomize the return array
     * @return  array       Array of question data
     */
    public static function getQuestions($quiz_id, $max = 0, $rand = true)
    {
        global $_TABLES;

        $max = (int)$max;
        $sql = "SELECT * FROM {$_TABLES['quizzer_questions']}
                WHERE quiz_id = '" . DB_escapeString($quiz_id) . "'
                AND enabled = 1";
        if ($rand) $sql .= ' ORDER BY RAND()';
        if ($max > 0) $sql .= " LIMIT $max";
        $res = DB_query($sql);

        // Question #0 indicates the start of the quiz, so index actual
        // questions starting at #1
        $questions = array();
        $i = 1;
        while ($A = DB_fetchArray($res, false)) {
            $questions[$i] = $A;
            $i++;
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
     * Determine whether this questoin was ever answered.
     * Used to see if the question may be deleted without affectinge existing
     * results.
     *
     * @return  boolean     True if there is an answer, False if not
     */
    private function _wasAnswered()
    {
        global $_TABLES;

        if (DB_count($_TABLES['quizzer_values'], 'q_id', $this->q_id) > 0) {
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
     * @param   string  $quiz_id    ID of the quiz
     * return   integer     Number of quiz questions in the database
     */
    public static function countQ($quiz_id)
    {
        global $_TABLES;

        return DB_count($_TABLES['quizzer_questions'], 'quiz_id', $quiz_id);
    }


    /**
     * Check if this question type allows partial credit.
     *
     * @return  boolean     True if partial credit is allowed
     */
    protected function allowPartial()
    {
        return false;
    }

}

?>
