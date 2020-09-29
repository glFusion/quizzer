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
    private $resultID = 0;

    /** Quize ID.
    * @var string */
    private $quizID = '';

    /** Submitting user ID.
    * @var integer */
    private $uid = 0;

    /** Submission date, as a timestamp.
    * @var integer */
    private $ts = 0;

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
    * @param    mixed   $resultID     Result set ID or array from DB
    */
    public function __construct($resultID=0)
    {
        if (is_array($resultID)) {
            // Already read from the DB, just load the values
            $this->SetVars($resultID);
        } elseif ($resultID > 0) {
            // Result ID supplied, read it
            $this->resultID = (int)$resultID;
            if (!$this->Read($resultID)) {
                $this->resultID = 0;
            }
        }

        if (!$this->isNew()) {    // existing record, get the questions and answers
            $this->readQuestions();
        }
    }


    /**
     * Get a specific result set.
     * Gets the value from the session if no ID is supplied.
     *
     * @param   integer $resultID     Optional result set ID
     * @param   string  $quizID    ID of quiz if $resultID is not provided
     * @return  object      Instance of a Result object
     */
    public static function getResult($resultID = 0, $quizID='')
    {
        if ($resultID == 0 && $quizID != '') {
            $resultID = SESS_getVar(self::SESS_NAME . '.' . $quizID);
        }
        return new self($resultID);
    }


    /**
     * Set the result set ID into the session variable.
     */
    private function setCurrent()
    {
//        echo "setting current quiz {$this->quizID} to {$this->resultID}";die;
        SESS_setVar(self::SESS_NAME . '.' . $this->quizID, $this->resultID);
        return $this;
    }


    /**
     * Get the current result set used by the guest.
     *
     * @param   string  $quizID    ID of the quiz
     * @return  object      Current result set, new set if none.
     */
    public static function getCurrent($quizID)
    {
        $resultID = (int)SESS_getVar(self::SESS_NAME . '.' . $quizID);
        return self::getResult($resultID);
    }


    /**
     * Clear the current result set to re-initialize the quiz.
     *
     * @param   string  $quizID    ID of the quiz to clear
     */
    public static function clearCurrent($quizID)
    {
        SESS_unset(self::SESS_NAME . '.' . $quizID);
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
            $this->resultID = (int)$id;
        }

        $sql = "SELECT * FROM {$_TABLES['quizzer_results']}
                WHERE resultID = " . $this->resultID;
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
        $this->Values = Value::getByResult($this->resultID);
        $this->Questions = array();
        foreach ($this->Values as $val) {
            $this->Questions[$val->getQuestionID()] = Question::getInstance($val->getQuestionID())
                ->setSeq($val->getOrderby());
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

        $this->resultID = (int)$A['resultID'];
        $this->quizID = COM_sanitizeID($A['quizID']);
        $this->ts = (int)$A['ts'];
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
                WHERE resultID = '{$this->resultID}'";
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
     * @param   string  $quizID        Quiz ID
     * @param   array   $introfields    Array of intro field prompts
     * @return  integer         New result set ID
     */
    public function Create($quizID, $introfields = array())
    {
        global $_TABLES, $_USER;

        $Q = Quiz::getInstance($quizID);
        $questions = Question::getQuestions($Q->getID(), $Q->getNumQ());
        if (empty($questions)) {
            return $this;
        }
        $question_ids = array();
        foreach ($questions as $Q) {
            // Here only the IDs are needed to create the value records
            $question_ids[] = $Q->getID();
        }

        self::clearCurrent($quizID);
        $num_asked = count($questions);   // replace with actual number
        $this->uid = $_USER['uid'];
        $this->quizID = COM_sanitizeID($quizID);
        $this->ts = time();
        $this->ip = $_SERVER['REAL_ADDR'];
        $ip = DB_escapeString($this->ip);
        $this->token = uniqid();
        $sql = "INSERT INTO {$_TABLES['quizzer_results']} SET
                quizID='{$this->quizID}',
                uid='{$this->uid}',
                ts='{$this->ts}',
                ip = '$ip',
                introfields = '" . DB_escapeString(@serialize($introfields)) . "',
                asked = {$num_asked},
                token = '{$this->token}'";
        //echo $sql;die;
        DB_query($sql, 1);
        if (!DB_error()) {
            $this->resultID = DB_insertID();
            $this->setCurrent();
            Value::createResultSet($this->resultID, $question_ids);
            $this->readQuestions();
            Cache::Clear();
        } else {
            $this->resultID = 0;
        }
        return $this;
    }


    /**
     * Delete all results for a quiz.
     *
     * @param   string  $quizID
     */
    public static function ResetQuiz($quizID)
    {
        $results = self::findByQuiz($quizID);
        foreach ($results as $R) {
            self::Delete($R->resultID);
        }
        Cache::Clear();
    }


    /**
     * Delete a single result set.
     *
     * @param   integer $resultID     Database ID of result to delete
     * @return  boolean     True on success, false on failure
     */
    public static function Delete($resultID=0)
    {
        global $_TABLES;

        $resultID = (int)$resultID;
        if ($resultID == 0) return false;
        self::DeleteValues($resultID);
        DB_delete($_TABLES['quizzer_results'], 'resultID', $resultID);
        return true;
    }


    /**
     * Delete the form values related to a result set.
     *
     * @param   integer $resultID Required result ID
     * @param   integer $uid    Optional user ID
     */
    public static function DeleteValues($resultID, $uid=0)
    {
        global $_TABLES;

        $resultID = (int)$resultID;
        if ($resultID == 0) return false;
        $uid = (int)$uid;

        $keys = array('resultID');
        $vals = array($resultID);
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
    public function getToken()
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

        $Q = Quiz::getInstance($this->quizID);
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
        $msg .= $Q->createReward($score['grade']);

        $T = new \Template(QUIZ_PI_PATH . '/templates');
        $T->set_file('result', 'finish.thtml');
        $T->set_var(array(
            'pct' => $pct,
            'quiz_name' => $Q->getName(),
            'quizID'   => $Q->getID(),
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
     * @param   string  $quizID    Quiz ID
     * @return  array       Array of Result objects
     */
    public static function findByQuiz($quizID)
    {
        global $_TABLES;

        $results = array();
        $quizID = DB_escapeString($quizID);
        $sql = "SELECT * FROM {$_TABLES['quizzer_results']}
                WHERE quizID = '$quizID'";
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
     * @param   string  $quizID    Quiz ID
     * @return  array       Array of Result objects
     */
    public static function findByUser($uid, $quizID = '')
    {
        global $_TABLES;

        $results = array();
        $uid = (int)$uid;
        $sql = "SELECT * FROM {$_TABLES['quizzer_results']}
                WHERE uid = '$uid'";
        if ($quizID != '') {
            $sql .= " AND quizID = '" . DB_escapeString($quizID) . "'";
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
        return $this->resultID == 0 ? 1 : 0;
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
        return $this->quizID;
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
        return (int)$this->resultID;
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
        $q_id = Value::getFirstUnanswered($this->resultID);
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
     * Get the timestamp, optionally formatting as requested.
     *
     * @param   string  $fmt    Format
     * @return  integer|string  Formatted or raw timestamp
     */
    public function getTS($fmt='')
    {
        global $_CONF;      // to get the timezone

        if ($fmt == '') {
            return (int)$this->ts;
        } else {
            $dt = new \Date($this->ts, $_CONF['timezone']);
            return $dt->format($fmt, true);
        }
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
            WHERE resultID = {$this->resultID}";
        DB_query($sql);
        return $this;
    }


    /**
     * Purge result records which have no questions answered.
     *
     * @param   integer $days   How old, in days, the record must be
     */
    public static function purgeNulls($days=1)
    {
        global $_TABLES;

        if ($days > 0) {
            $cutoff = max((int)$days, 1) * 86400;
            $sql = "DELETE r.* FROM {$_TABLES['quizzer_results']} r
                WHERE NOT EXISTS (
                    SELECT v.resultID FROM {$_TABLES['quizzer_values']} v
                    WHERE v.resultID = r.resultID AND v.value IS NOT NULL)
                AND r.ts < unix_timestamp() - $cutoff";
            //echo $sql;die;
            DB_query($sql);
        }
    }


    /**
     * Display a specific detailed result.
     *
     * @return  string      HTML for output
     */
    public function Render()
    {
        $T = new \Template(QUIZ_PI_PATH . '/templates/admin');
        $T->set_file('result', 'oneresult.thtml');

        $T->set_block('result', 'IntroRows', 'iRow');
        foreach ($this->introfields as $prompt=>$value) {
            $T->set_var(array(
                'intro_prompt'  => $prompt,
                'intro_value'   => $value,
            ) );
            $T->parse('iRow', 'IntroRows', true);
        }

        $T->set_block('result', 'DataRows', 'dRow');
        foreach ($this->getValues() as $V) {
            $Q = $this->Questions[$V->getQuestionID()];
            $T->set_var(array(
                'question' => $Q->getQuestion(),
            ) );
            $given = array();
            foreach ($V->getValue() as $Val) {
                if ($Val > 0) {
                    $Ans = $Q->getAnswers()[$Val];
                    $given[] = $Ans->getValue();
                }
            }
            $given = implode(',', $given);
            $score = $Q->Verify($V->getValue());
            $T->set_var(array(
                'answer'    => $given,
                'is_correct' => $score == 1,
                'is_partial' => $score < 1 && $score > 0,
                'is_wrong' => $score == 0,
                'score' => number_format(round($score * 100, 2), 2) . ' %',
            ) );
            $T->parse('dRow', 'DataRows', true);
            $T->clear_var('aRow');
        }
        $T->parse('output', 'result');
        return $T->finish($T->get_var('output'));
    }

}

?>
