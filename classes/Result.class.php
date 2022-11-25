<?php
/**
 * Class to handle the Quiz result sets.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2018-2022 Lee Garner <lee@leegarner.com>
 * @package     quizzer
 * @version     v0.2.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Quizzer;
use glFusion\Database\Database;
use glFusion\Log\Log;
use Quizzer\Models\Score;
use Quizzer\Models\DataArray;


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
            $this->setVars(new DataArray($resultID));
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

        $db = Database::getInstance();
        try {
            $data = $db->conn->executeQuery(
                "SELECT * FROM {$_TABLES['quizzer_results']}
                WHERE resultID = ?",
                array($this->resultID),
                array(Database::INTEGER)
            )->fetch(Database::ASSOCIATIVE);
        } catch (\Throwable $e) {
            Log::write('system', Log::ERROR, $e->getMessage());
            $data = NULL;
        }
        if (is_array($data)) {
            $this->setVars(new DataArray($data));
            return true;
        } else {
            return false;
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
            $qid = $val->getQuestionID();
            $this->Questions[$qid] = Question::getInstance($qid);
            if ($this->Questions[$qid]) {
                $this->Questions[$qid]->setSeq($val->getOrderby());
            }
        }
    }


    /**
     * Set all the variables from a DB record.
     *
     * @param   DataArray   $A  Array of values
     */
    public function setVars(DataArray $A) : void
    {
        $this->resultID = $A->getInt('resultID');
        $this->quizID = COM_sanitizeID($A->getString('quizID'));
        $this->ts = $A->getInt('ts');
        $this->uid = $A->getInt('uid');
        $this->ip = $A->getString('ip');
        $this->token = $A->getString('token');
        $this->asked = $A->getInt('asked');
        $this->introfields = $A->unserialize('introfields');
        if (!is_array($this->introfields)) {
            $this->introfields = array();
        }
    }


    /**
     * Retrieve all the values for this set into the supplied field objects.
     *
     * @param   array   $fields     Array of Field objects
     */
    public function readValues(array $fields) : void
    {
        global $_TABLES;

        $db = Database::getInstance();
        try {
            $data = $db->conn->executeQuery(
                "SELECT * from {$_TABLES['quizzer_values']}
                WHERE resultID = ?", //'{$this->resultID}'";
                array($this->resultID),
                array(Database::INTEGER)
            )->fetchAll(Database::ASSOCIATIVE);
        } catch (\Throwable $e) {
            Log::write('system', Log::ERROR, $e->getMessage());
            $data = NULL;
        }

        if (is_array($data)) {
            foreach ($data as $A) {
                $vals[$A['fld_id']] = $A;
            }
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
        $this->token = uniqid();
        $db = Database::getInstance();
        $qb = $db->conn->createQueryBuilder();
        try {
            $qb->insert($_TABLES['quizzer_results'])
               ->setValue('quizID', ':quizID')
               ->setValue('uid', ':uid')
               ->setValue('ts', ':ts')
               ->setValue('ip', ':ip')
               ->setValue('introfields', ':intro')
               ->setValue('asked', ':asked')
               ->setValue('token', ':token')
               ->setParameter('quizID', $this->quizID, Database::STRING)
               ->setParameter('uid', $this->uid, Database::INTEGER)
               ->setParameter('ts', $this->ts, Database::INTEGER)
               ->setParameter('ip', $this->ip, Database::STRING)
               ->setParameter('intro', @serialize($introfields), Database::STRING)
               ->setParameter('asked', $num_asked, Database::INTEGER)
               ->setParameter('token', $this->token, Database::STRING)
               ->execute();
            $this->resultID = $db->conn->lastInsertId();
            $this->setCurrent();
            Value::createResultSet($this->resultID, $question_ids);
            $this->readQuestions();
            Cache::Clear();
        } catch (\Throwable $e) {
            Log::write('system', Log::ERROR, __CLASS__.'::'.__FUNCTION__.': '.$e->getMessage());
            $this->resultID = 0;
        }
        return $this;
    }


    /**
     * Delete all results for a quiz.
     *
     * @param   string  $quizID
     */
    public static function XResetQuiz($quizID)
    {
        $results = self::findByQuiz($quizID);
        var_dumP($results);die;
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
    public static function Delete(int $resultID) : bool
    {
        global $_TABLES;

        if ($resultID == 0) return false;
        $db = Database::getInstance();
        $db->conn->delete(
            $_TABLES['quizzer_results'],
            array('resultID' => $resultID),
            array(Database::INTEGER)
        );
        return true;
    }


    public static function resetQuiz(string $quizID) : void
    {
        global $_TABLES;

        $db = Database::getInstance();
        $r_ids = array();
        try {
            $stmt = $db->conn->executeQuery(
                "SELECT resultID FROM {$_TABLES['quizzer_results']}
                WHERE quizID = ?",
                array($quizID),
                array(Database::STRING)
            );
        } catch (\Throwable $e) {
            Log::write('system', Log::ERROR, __CLASS__.'::'.__FUNCTION__.': '.$e->getMessage());
            $stmt = NULL;
        }
        if ($stmt) {
            while ($A = $stmt->fetch(Database::ASSOCIATIVE)) {
                $r_ids[] = $A['resultID'];
            }
        }
        if (!empty($r_ids)) {
            try {
                $db->conn->executeStatement(
                    "DELETE FROM {$_TABLES['quizzer_values']} WHERE resultID IN (?)",
                    array($r_ids),
                    array(Database::PARAM_INT_ARRAY)
                );
            } catch (\Throwable $e) {
                Log::write('system', Log::ERROR, __CLASS__.'::'.__FUNCTION__.': '.$e->getMessage());
            }
            try {
                $db->conn->executeStatement(
                    "DELETE FROM {$_TABLES['quizzer_results']} WHERE resultID IN (?)",
                    array($r_ids),
                    array(Database::PARAM_INT_ARRAY)
                );
            } catch (\Throwable $e) {
                Log::write('system', Log::ERROR, __CLASS__.'::'.__FUNCTION__.': '.$e->getMessage());
            }
        }
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

        if ($resultID == 0) return false;

        $params = array('resultID' => $resultID);
        $types = array(Database::INTEGER);
        if ($uid > 0) {
            $params['uid'] = $uid;
            $types[] = Database::INTEGER;
        }
        $db = Database::getInstance();
        $db->conn->delete($_TABLES['quizzer_values'], $params, $types);
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
        $Score = $Q->getGrade($pct);
        if ($Score->grade == Score::PASSED) {
            $msg = $Q->getPassMsg();
            /////// TODO : testing gift card rewards
            //$msg .= \Quizzer\Rewards\GiftCard::CreateReward();
        } else {
            $msg = $Q->getFailMsg();
        }
        $T = new \Template(QUIZ_PI_PATH . '/templates');
        $T->set_file('result', 'finish.thtml');
        $T->set_var(array(
            'pct' => $pct,
            'quiz_name' => $Q->getName(),
            'quizID'   => $Q->getID(),
            'correct' => $correct,
            'total' => $total_q,
            'prog_status' => $Score->getCSS(),
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
    public static function findByQuiz(string $quizID) : array
    {
        global $_TABLES;

        $results = array();
        $db = Database::getInstance();
        try {
            $data = $db->conn->executeQuery(
                "SELECT * FROM {$_TABLES['quizzer_results']}
                WHERE quizID = ?",
                array($quizID),
                array(Database::STRING)
            )->fetchAll(Database::ASSOCIATIVE);
        } catch (\Throwable $e) {
            Log::write('system', Log::ERROR, $e->getMessage());
            $data = NULL;
        }
        if (is_array($data)) {
            foreach ($data as $A) {
                $results[] = new self($A);
            }
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
    public static function findByUser(int $uid, string $quizID = '') : array
    {
        global $_TABLES;

        $results = array();
        $db = Database::getInstance();
        $sql = 
                "SELECT * FROM {$_TABLES['quizzer_results']}
                WHERE uid = ?";
        $params = array($uid);
        $types = array(Database::INTEGER);
        if ($quizID != '') {
            $sql .= " AND quizID = ?";
            $params[] = $quizID;
            $types[] = Database::STRING;
        }

        try {
            $data = $db->conn->executeQuery($sql, $params, $types)
                             ->fetchAll(Database::ASSOCIATIVE);
        } catch (\Throwable $e) {
            Log::write('system', Log::ERROR, $e->getMessage());
            $data = NULL;
        }
        if (is_array($data)) {
            foreach ($data as $A) {
                $results[] = new self($A);
            }
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
    public function saveIntro(array $A) : self
    {
        global $_TABLES;

        $db = Database::getInstance();
        try {
            $db->conn->executeUpdate(
                "UPDATE {$_TABLES['quizzer_results']}
                SET introfields = ?
                WHERE resultID = ?",
                array(@serialize($A), $this->resultID),
                array(Database::STRING, Database::INTEGER)
            );
        } catch (\Throwable $e) {
            Log::write('system', Log::ERROR, $e->getMessage());
        }
        return $this;
    }


    /**
     * Purge result records which have no questions answered.
     *
     * @param   integer $days   How old, in days, the record must be
     */
    public static function purgeNulls(int $days=1) : void
    {
        global $_TABLES;

        if ($days > 0) {
            $cutoff = max((int)$days, 1) * 86400;
            $sql = "DELETE r.* FROM {$_TABLES['quizzer_results']} r
                WHERE NOT EXISTS (
                    SELECT v.resultID FROM {$_TABLES['quizzer_values']} v
                    WHERE v.resultID = r.resultID AND v.value IS NOT NULL)
                AND r.ts < unix_timestamp() - ?";
            $db = Database::getInstance();
            try {
                $db->conn->executeUpdate($sql, array($cutoff), array(Database::INTEGER));
            } catch (\Throwable $e) {
                Log::write('system', Log::ERROR, $e->getMessage());
            }
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
        $T->set_var(array(
            'pi_url' => QUIZ_ADMIN_URL,
            'quiz_id' => $this->quizID,
        ) );
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
                'question' => self::removeAutotags(strip_tags($Q->getQuestion())),
            ) );
            $given = array();
            if (is_array($V->getValue())) {
                foreach ($V->getValue() as $Val) {
                    if ($Val > 0) {
                        $Ans = $Q->getAnswers()[$Val];
                        $given[] = $Ans->getValue();
                    }
                }
                $given = implode(',', $given);
            }
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


    /**
     * Remove autotags before displaying a question.
     *
     * @param   string  $content    Content to examine
     * @return  string      Content withoug autotags
     */
    protected static function removeAutotags($content)
    {
        static $autolinkModules = NULL;
        static $tags = array();

        // Just return content if there are no autotags
        if (strpos($content, '[') === false) {
            return $content;
        }

        if ($autolinkModules === NULL) {
            $autolinkModules = PLG_collectTags();
            foreach ($autolinkModules as $moduletag => $module) {
                $tags[] = $moduletag;
            }
            $tags = implode('|', $tags);
        }
        if (!empty($tags)) {
            $result = preg_filter("/\[(($tags):.[^\]]*\])/i", '', $content);
            if ($result === NULL) {
                // Just means no match found
                return $content;
            } else {
                return $result;
            }
        } else {
            return $content;
        }
    }

}
