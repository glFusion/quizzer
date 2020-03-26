<?php
/**
 * Class to handle the Quiz result sets.
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
 * Class for a single result.
 */
class Result
{
    const SESS_NAME = 'quizzer_resultset';

    /** Questions, with answers.
    * @var array */
    private $Questions = array();

    /** Submission Values.
     * @var array */
    private $Values = array();

    /** Result record ID.
    * @var integer */
    private $res_id = 0;

    /** Quize ID.
    * @var string */
    private $quiz_id = '';

    /** Submitting user ID.
    * @var integer */
    private $uid = 0;

    /** Submission date, as a timestamp.
    * @var integer */
    private $dt = 0;

    /** IP address of submitter.
    * @var string */
    private $ip = '';

    /** Unique token for this submission.
    * @var string */
    private $token = '';

    /** Answers provided in intro fields.
     * @var string */
    private $introfields = '';

    /** Number of questions asked.
     * @var integer */
    private $asked = 0;


    /**
    * Constructor.
    * If a result set ID is specified, it is read. If an array is given
    * then the fields are simply copied from the array, e.g. when displaying
    * many results in a table.
    *
    * @param    mixed   $res_id     Result set ID or array from DB
    */
    public function __construct($res_id=0)
    {
        if (is_array($res_id)) {
            // Already read from the DB, just load the values
            $this->SetVars($res_id);
        } elseif ($res_id > 0) {
            // Result ID supplied, read it
            $this->res_id = (int)$res_id;
            $this->Read($res_id);
        }

        if (!$this->isNew()) {    // existing record, get the questions and answers
            $this->readQuestions();
        }
    }


    /**
     * Get a specific result set.
     * Gets the value from the session if no ID is supplied.
     *
     * @param   integer $res_id     Optional result set ID
     * @param   string  $quiz_id    ID of quiz if $res_id is not provided
     * @return  object      Instance of a Result object
     */
    public static function getResult($res_id = 0, $quiz_id='')
    {
        if ($res_id == 0 && $quiz_id != '') {
            $res_id = SESS_getVar(self::SESS_NAME[$quiz_id]);
        }
        return new self($res_id);
    }


    /**
     * Set the result set ID into the session variable.
     */
    private function setCurrent()
    {
        SESS_setVar(self::SESS_NAME . '.' . $this->quiz_id, $this->res_id);
        return $this;
    }


    /**
     * Get the current result set used by the guest.
     *
     * @param   string  $quiz_id    ID of the quiz
     * @return  object      Current result set, new set if none.
     */
    public static function getCurrent($quiz_id)
    {
        $res_id = (int)SESS_getVar(self::SESS_NAME . '.' . $quiz_id);
        return self::getResult($res_id);
    }


    /**
     * Clear the current result set to re-initialize the quiz.
     *
     * @param   string  $quiz_id    ID of the quiz to clear
     */
    public static function clearCurrent($quiz_id)
    {
        SESS_unset(self::SESS_NAME . '.' . $quiz_id);
    }


    /**
     * Read a result record into object variables.
     *
     * @param   integer $id     Result set ID
     * @return  boolean         True on success, False on failure/not found
     */
    public function Read($id = 0)
    {
        global $_TABLES;

        $id = (int)$id;
        if ($id > 0) {
            $this->res_id = (int)$id;
        }

        $sql = "SELECT * FROM {$_TABLES['quizzer_results']}
                WHERE res_id = " . $this->res_id;
        //echo $sql;die;
        $res1 = DB_query($sql);
        if (!$res1) {
            return false;
        }

        $A = DB_fetchArray($res1, false);
        if (empty($A)) {
            return false;
        } else {
            $this->SetVars($A);
            return true;
        }
    }


    /**
     * Read the questions for this result set.
     * Sets the sequence number into the question object.
     */
    private function readQuestions()
    {
        $this->Values = Value::getByResult($this->res_id);
        $this->Questions = array();
        foreach ($this->Values as $val) {
            $this->Questions[$val->getQuestionID()] = Question::getInstance($val->getQuestionID())->setSeq($val->getOrderby());
        }
    }


    /**
     * Set all the variables from a DB record.
     *
     * @param   array   $A      Array of values
     */
    public function SetVars($A)
    {
        if (!is_array($A)) {
            return false;
        }

        $this->res_id = (int)$A['res_id'];
        $this->quiz_id = COM_sanitizeID($A['quiz_id']);
        $this->dt = (int)$A['dt'];
        $this->uid = (int)$A['uid'];
        $this->ip = $A['ip'];
        $this->token = $A['token'];
        $this->asked = (int)$A['asked'];
        $this->introfields = @unserialize($A['introfields']);
        if (!is_array($this->introfields)) {
            $this->introfields = array();
        }
    }


    /**
     * Retrieve all the values for this set into the supplied field objects.
     *
     * @param   array   $fields     Array of Field objects
     */
    public function readValues($fields)
    {
        global $_TABLES;
        $sql = "SELECT * from {$_TABLES['quizzer_values']}
                WHERE res_id = '{$this->res_id}'";
        $res = DB_query($sql);
        $vals = array();
        // First get the values into an array indexed by field ID
        while($A = DB_fetchArray($res, false)) {
            $vals[$A['fld_id']] = $A;
        }
        // Then they can be pushed into the field array
        foreach ($fields as $field) {
            if (isset($vals[$field->fld_id])) {
                $field->value = $vals[$field->fld_id]['value'];
            }
        }
    }


    /**
     * Creates a result set in the database.
     *
     * @param   string  $quiz_id        Quiz ID
     * @param   array   $introfields    Array of intro field prompts
     * @return  integer         New result set ID
     */
    public function Create($quiz_id, $introfields = array())
    {
        global $_TABLES, $_USER;

        $Q = Quiz::getInstance($quiz_id);
        $questions = Question::getQuestions($Q->getID(), $Q->getNumQ());
        if (empty($questions)) {
            return $this;
        }
        $question_ids = array();
        foreach ($questions as $A) {
            $question_ids[] = $A['q_id'];
        }

        self::clearCurrent($quiz_id);
        //SESS_setVar('quizzer_questions', $questions);
        $Q->num_q = count($questions);   // replace with actual number
        //$num_q = min($Q->num_q, Question::countQ($quiz_id));
        $this->uid = $_USER['uid'];
        $this->quiz_id = COM_sanitizeID($quiz_id);
        $this->dt = time();
        $this->ip = $_SERVER['REAL_ADDR'];
        $ip = DB_escapeString($this->ip);
        $this->token = uniqid();
        $sql = "INSERT INTO {$_TABLES['quizzer_results']} SET
                quiz_id='{$this->quiz_id}',
                uid='{$this->uid}',
                dt='{$this->dt}',
                ip = '$ip',
                introfields = '" . DB_escapeString(@serialize($introfields)) . "',
                asked = {$Q->num_q},
                token = '{$this->token}'";
        DB_query($sql, 1);
        if (!DB_error()) {
            $this->res_id = DB_insertID();
            $this->setCurrent();
            Value::createResultSet($this->res_id, $question_ids);
            $this->readQuestions();
            Cache::Clear();
        } else {
            $this->res_id = 0;
        }
        return $this;
    }


    /**
     * Delete all results for a quiz.
     *
     * @param   string  $quiz_id
     */
    public static function ResetQuiz($quiz_id)
    {
        $results = self::findByQuiz($quiz_id);
        foreach ($results as $R) {
            self::Delete($R->res_id);
        }
        Cache::Clear();
    }


    /**
     * Delete a single result set.
     *
     * @param   integer $res_id     Database ID of result to delete
     * @return  boolean     True on success, false on failure
     */
    public static function Delete($res_id=0)
    {
        global $_TABLES;

        $res_id = (int)$res_id;
        if ($res_id == 0) return false;
        self::DeleteValues($res_id);
        DB_delete($_TABLES['quizzer_results'], 'res_id', $res_id);
        return true;
    }


    /**
     * Delete the form values related to a result set.
     *
     * @param   integer $res_id Required result ID
     * @param   integer $uid    Optional user ID
     */
    public static function DeleteValues($res_id, $uid=0)
    {
        global $_TABLES;

        $res_id = (int)$res_id;
        if ($res_id == 0) return false;
        $uid = (int)$uid;

        $keys = array('res_id');
        $vals = array($res_id);
        if ($uid > 0) {
            $keys[] = 'uid';
            $vals[] = $uid;
        }
        DB_delete($_TABLES['quizzer_values'], $keys, $vals);
    }


    /**
     * Returns this result set's token.
     * The token provides a very basic authentication mechanism when
     * the after-submission action is to display the results, to ensure
     * that only the newly-submitted result set is displayed.
     *
     * @return  string      Token saved with this result set
     */
    public function Token()
    {
        return $this->token;
    }


    /**
     * Display the final score and progress bar to the user
     *
     * @return  string      HTML for score page
     */
    public function showScore()
    {
        $total_q = $this->asked;
        $correct = 0;

        foreach ($this->Values as $V) {
            $correct += $this->Questions[$V->getQuestionID()]->Verify($V->getValue());
        }
        if (!is_int($correct)) {
            $correct = round($correct, 2);
        }

        $Q = Quiz::getInstance($this->quiz_id);
        $Q->Reset();
        if ($total_q > 0) {
            $pct = round(($correct / $total_q) * 100);
        } else {
            $pct = 0;
        }
        $score = $Q->getGrade($pct);
        //$prog_status = $Q->getGrade($pct);
        //if ($prog_status == 'success') {
        if ($score['grade'] == Quiz::PASSED) {
            $msg = $Q->getPassMsg();
        } else {
            $msg = $Q->getFailMsg();
        }
        if ($Q->getRewardStatus() <= $score['grade']) {
            $msg .= \Quizzer\Reward::getById($Q->getRewardID())
                ->createReward($this->uid);
        }
        $T = new \Template(QUIZ_PI_PATH . '/templates');
        $T->set_file('result', 'finish.thtml');
        $T->set_var(array(
            'pct' => $pct,
            'quiz_name' => $Q->getName(),
            'quiz_id'   => $Q->getID(),
            'correct' => $correct,
            'total' => $total_q,
            'prog_status' => $prog_status,
            'finish_msg' => $msg,
        ) );
        $T->parse('output', 'result');
        self::clearCurrent($Q->getiD());
        return $T->finish($T->get_var('output'));
    }


    /**
     * Find all results for a particular quiz
     *
     * @param   string  $quiz_id    Quiz ID
     * @return  array       Array of Result objects
     */
    public static function findByQuiz($quiz_id)
    {
        global $_TABLES;

        $results = array();
        $quiz_id = DB_escapeString($quiz_id);
        $sql = "SELECT * FROM {$_TABLES['quizzer_results']}
                WHERE quiz_id = '$quiz_id'";
        $res = DB_query($sql);
        while ($A = DB_fetchArray($res, false)) {
            $results[] = new self($A);
        }
        return $results;
    }


    /**
     * Find all results for a particular user.
     * Used to check if a user has already filled out a onetime quiz.
     *
     * @param   string  $uid        User ID
     * @param   string  $quiz_id    Quiz ID
     * @return  array       Array of Result objects
     */
    public static function findByUser($uid, $quiz_id = '')
    {
        global $_TABLES;

        $results = array();
        $uid = (int)$uid;
        $sql = "SELECT * FROM {$_TABLES['quizzer_results']}
                WHERE uid = '$uid'";
        if ($quiz_id != '') {
            $sql .= " AND quiz_id = '" . DB_escapeString($quiz_id) . "'";
        }
        $res = DB_query($sql);
        while ($A = DB_fetchArray($res, false)) {
            $results[] = new self($A);
        }
        return $results;
    }

    /**
     * Determinie if this is a new record or an existing one.
     *
     * @return  integer     1 if new, 0 if existing
     */
    public function isNew()
    {
        return $this->res_id == 0 ? 1 : 0;
    }


    /**
     * Get the submitting user ID.
     *
     * @return  integer     User ID
     */
    public function getUid()
    {
        return (int)$this->uid;
    }


    /**
     * Get the associated quiz record ID.
     *
     * @return  string      Quiz ID
     */
    public function getQuizID()
    {
        return $this->quiz_id;
    }


    /**
     * Get the values in this result set.
     *
     * @return  array       Submitted answers
     */
    public function getValues()
    {
        return $this->Values;
    }


    /**
     * Get the number of questions asked by this quiz.
     * Used to determine if the quiz was answered completely.
     *
     * @return  integer     Number of questions asked
     */
    public function getAsked()
    {
        return (int)$this->asked;
    }


    /**
     * Get the record ID for this result set.
     *
     * @return  integer     Record ID
     */
    public function getID()
    {
        return (int)$this->res_id;
    }


    /**
     * Get the answers to the intro fields.
     *
     * @return  string      Intro field answers.
     */
    public function getIntroFields()
    {
        return $this->introfields;
    }


    /**
     * Get the next qustion to be answered for this resultset.
     *
     * @uses    Value::getNextUnanswered()
     * @return  object      Question object to be rendered
     */
    public function getNextQuestion()
    {
        $q_id = Value::getFirstUnanswered($this->res_id);
        return $q_id;
    }


    /**
     * Get the question objects related to this result.
     *
     * @return  array       Array of Question objects
     */
    public function getQuestions()
    {
        return $this->Questions;
    }


    /**
     * Check if the intro fields have been filled out.
     *
     * @return  boolean     True if the intro questions are completed
     */
    public function introDone()
    {
        return !empty($this->introfields);
    }


    /**
     * Save the answers to the intro questions.
     * These go into the results table, not the values.
     *
     * @param   array   $A      Array of prompt->value pairs
     * @return  object  $this
     */
    public function saveIntro($A)
    {
        global $_TABLES;

        $val = DB_escapeString(@serialize($A));
        $sql = "UPDATE {$_TABLES['quizzer_results']}
            SET introfields = '$val'
            WHERE res_id = {$this->res_id}";
        DB_query($sql);
        return $this;
    }


    /**
     * Purge result records which have no questions answered.
     *
     * @param   integer $days   How old, in days, the record must be
     */
    public static function purgeNulls($days=0)
    {
        global $_TABLES;

        $cutoff = max((int)$days, 1) * 86400;
        $sql = "DELETE r.* FROM {$_TABLES['quizzer_results']} r
            WHERE NOT EXISTS (
                SELECT v.res_id FROM {$_TABLES['quizzer_values']} v
                WHERE v.res_id = r.res_id AND v.value IS NOT NULL)
            AND r.dt < unix_timestamp() - $cutoff";
        //echo $sql;die;
        $res = DB_query($sql);
        $A = DB_fetchAll($res, false);
        $vals = array();
        foreach ($A as $v) {
            $vals[] = $v['res_id'];
        }
        $val_str = '(' . implode(',', $vals) . ')';
        // Delete cascades to values
        $sql = "DELETE FROM {$_TABLES['quizzer_results']}
            WHERE res_id IN $val_str";
    }

}

?>
