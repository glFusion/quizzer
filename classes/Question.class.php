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
    const MAX_ANSWERS = 5;

    /** Flag to indicate that this is a new question.
     * @var boolean */
    private $isNew = true;

    /** Quiz ID related to this queston.
     * @var string */
    private $quiz_id = '';

    /** Question record ID.
     * @var integer */
    private $q_id = 0;

    /** Flag to indicate question can appear on quizzes.
     * @var boolean */
    private $enabled = 1;

    /** Flag to indicate that answers should be shown in random order.
     * @var boolean */
    private $randomize = 0;

    /** Question text.
     * @var string */
    private $question = '';

    /** Question type (checkbox, radio, etc).
     * @var string */
    private $type = 'radio';

    /** Help message to show with the question.
     * @var string */
    private $help_msg = '';

    /** Message to show after answering.
     * @var string */
    private $answer_msg = '';

    /** Flag to indicate that partial credit is granted.
     * @var boolean */
    private $partial_credit = 0;

    /** Answer value.
     * @var integer */
    protected $have_answer = 0;

    /** Answer options for this question
     * @var array */
    protected $Answers = array();


    /**
     * Constructor.
     *
     * @param   integer $q_id       ID of the existing question, empty if new
     * @param   integer $quiz_id    ID of the related quiz
     */
    public function __construct($q_id = 0, $quiz_id=NULL)
    {
        global $_USER, $_TABLES;

        if ($q_id == 0) {
            $this->quiz_id = $quiz_id;
        } elseif (is_array($q_id)) {
            $this->setVars($q_id, true);
        } else {
            $q = self::Read($q_id);
            if ($q) {
                $this->setVars($q);
            }
        }
        $this->Answers = Answer::getByQuestion($this->q_id);
    }


    /**
     * Get an instance of a question based on the question type.
     * If the "question" parameter is an array it must include at least q_id
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
            if (!isset($question['type']) || !isset($question['q_id'])) {
                return NULL;
            }
            $q_id = (int)$question['q_id'];
        } elseif (is_numeric($question)) {
            // Received a question ID, have to look up the record to get
            // the type.
            $q_id = (int)$question;
            if ($q_id > 0) {
                $question = self::Read($q_id);
                if (empty($question)) return NULL;
            }
        }

        // Instantiate the question object.
        // The answers will be read from cache or DB by the constructor.
        if ($q_id == 0) {
            return new self(0, $quiz);
        } else {
            $cls = __NAMESPACE__ . '\\Questions\\' . $question['type'];
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
                    WHERE q_id = $id";
            $res = DB_query($sql, 1);
            if (DB_error() || !$res) return false;
            $A = DB_fetchArray($res, false);
            Cache::set($cache_key, $A, array('questions', $A['quiz_id']));
        }
        return $A;
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
        $this->randomize = isset($A['randomize']) && $A['randomize'] == 1 ? 1 : 0;
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
        $Val = new Value($res_id, $this->q_id);
        if (!$Val->isNew()) {
            $sub_btn_vis = 'none';
            $next_btn_vis = '';
            $this->have_answer = $Val->getValue();
        } else {
            $sub_btn_vis = '';
            $next_btn_vis = 'none';
            $this->have_answer = 0;
        }

        $Q = Quiz::getInstance($this->quiz_id);
        $T = new \Template(QUIZ_PI_PATH . '/templates');
        $T->set_file('question', 'question.thtml');
        // Set template variables without allowing caching
        $T->set_var(array(
            'quiz_id'       => $this->quiz_id,
            'quiz_name'     => $Q->getName(),
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
        if ($this->randomize) {
            // Randomize the answers if so configured.
            shuffle($this->Answers);
        }
        foreach ($this->Answers as $A) {
            $T->set_var(array(
                'q_id'      => $A->getQid(),
                'a_id'      => $A->getAid(),
                'answer'    => $A->getValue(),
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
                    $icon = '<i class="uk-icon uk-icon-close uk-icon-medium qz-color-incorrect"></i>';
                }
                if (in_array($A->getAid(), $correct)) {
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
            'editing'   => $this->isNew() ? '' : 'true',
            'help_msg'  => $this->help_msg,
            'answer_msg' => $this->answer_msg,
            'can_delete' => $this->isNew() || $this->_wasAnswered() ? false : true,
            $this->type . '_sel' => 'selected="selected"',
            'pcred_vis' => $this->allowPartial() ? '' : 'none',
            'random_chk' => $this->randomize ? 'checked="checked"' : '',
            'pcred_chk' => $this->isPartialAllowed() ? 'checked="checked"' : '',
        ) );

        $T->set_block('editform', 'Answers', 'Ans');
        foreach ($this->Answers as $answer) {
            $T->set_var(array(
                'ans_id'    => $answer->getAid(),
                'ans_val'   => $answer->getValue(),
                'ischecked' => $answer->isCorrect() ? 'checked="checked"' : '',
                'isRadio'   => $this->type == 'radio' ? true : false,
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
                partial_credit = '{$this->partial_credit}',
                randomize = '{$this->randomize}'";
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
        $to_del = array();
        for ($i = 1; $i <= $count; $i++) {
            if (!empty($A['opt'][$i])) {
                $question = DB_escapeString($A['opt'][$i]);
                if ($this->type == 'radio') {
                    $correct = isset($A['correct']) && $A['correct'] == $i ? 1 : 0;
                } else {
                    $correct = isset($A['correct'][$i]) && $A['correct'][$i] == 1 ? 1 : 0;
                }
                $Ans = new Answer;
                $Ans->setQid($q_id)
                    ->setAid($i)
                    ->setValue($question)
                    ->setCorrect($correct)
                    ->Save();
            } else {
                $to_del[] = $i;
            }
        }
        if (!empty($to_del)) {
            Answer::Delete($this->q_id, implode(',', $to_del));
        }
        Cache::clear(array('questions', $this->quiz_id));
        Cache::clear(array('answers', $this->q_id));
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
            'q_id',
            $this->q_id
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
     * Used to determine whether the partial credit option is shown on the
     * question definition form.
     *
     * @return  boolean     True if partial credit is allowed
     */
    protected function allowPartial()
    {
        return false;
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
        return $this->allowPartial() && $this->partial_credit;
    }


    /**
     * Uses lib-admin to list the question definitions and allow updating.
     *
     * @param   string  $quiz_id    ID of quiz
     * @return  string              HTML for the question list
     */
    public static function adminList($quiz_id = '')
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
                'field' => 'question',
                'sort'  => false,
            ),
            array(
                'text'  => $LANG_QUIZ['type'],
                'field' => 'type',
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
            ),
        );

        $defsort_arr = array(
            'field'     => 'q_id',
            'direction' => 'ASC',
        );
        $text_arr = array(
            'form_url' => QUIZ_ADMIN_URL . '/index.php',
        );
        $options_arr = array(
            'chkdelete' => true,
            'chkname'   => 'delquestion',
            'chkfield'  => 'q_id',
        );
        $query_arr = array(
            'table' => 'quizzer_questions',
            'sql'   => "SELECT * FROM {$_TABLES['quizzer_questions']}",
            'query_fields' => array('name', 'type', 'value'),
            'default_filter' => '',
        );
        if ($quiz_id != '') {
            $query_arr['sql'] .= " WHERE quiz_id='" . DB_escapeString($quiz_id) . "'";
        }
        $form_arr = array();
        $T = new \Template(QUIZ_PI_PATH . '/templates/admin');
        $T->set_file('questions', 'questions.thtml');
        $T->set_var(array(
            'action_url'    => QUIZ_ADMIN_URL . '/index.php',
            'quiz_id'       => $quiz_id,
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
                $_CONF_QUIZ['icons']['edit'],
                QUIZ_ADMIN_URL . "/index.php?editquestion=x&amp;q_id={$A['q_id']}"
            );
            break;

        case 'delete':
            $retval = COM_createLink(
                $_CONF_QUIZ['icons']['delete'],
                QUIZ_ADMIN_URL . '/index.php?delQuestion=x&q_id=' .
                    $A['q_id'] . '&quiz_id=' . $A['quiz_id'],
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
            $retval = "<input name=\"{$fieldname}_{$A['q_id']}\" " .
                "type=\"checkbox\" $chk " .
                "onclick='QUIZtoggleEnabled(this, \"{$A['q_id']}\", \"question\", \"{$fieldname}\", \"" . QUIZ_ADMIN_URL . "\");' ".
                "/>\n";
            break;

        case 'id':
        case 'q_id':
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
        return $this->q_id == 0 ? 1 : 0;
    }


    /**
     * Get the record ID for this question.
     *
     * @return  integer     Record ID
     */
    public function getID()
    {
        return (int)$this->q_id;
    }


    /**
     * Get the text for this question.
     *
     * @return  string      Question text to display
     */
    public function getQuestion()
    {
        return $this->question;
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
        return $this->answer_msg;
    }

}

?>
