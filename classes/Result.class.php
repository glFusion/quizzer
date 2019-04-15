<?php
/**
 * Class to handle the Quiz result sets.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2018 Lee Garner <lee@leegarner.com>
 * @package     quizzer
 * @version     v0.0.1
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
    /** Questions, with answers.
    * @var array */
    public $Questions = array();

    /** Submission Values.
     * @var array */
    public $Values = array();

    /** Result record ID.
    * @var integer */
    var $res_id;

    /** Quize ID.
    * @var string */
    var $quiz_id;

    /** Submitting user ID.
    * @var integer */
    var $uid;

    /** Submission date.
    * @var string */
    var $dt;

    /** IP address of submitter.
    * @var string */
    var $ip;

    /** Unique token for this submission.
    * @var string */
    var $token;

    /** Answers provided in intro fields.
     * @var string */
    var $introfields;

    /** Number of questions asked.
     * @var integer */
    public $asked = 0;

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
            $this->isNew = false;
        } elseif ($res_id > 0) {
            // Result ID supplied, read it
            $this->isNew = false;
            $this->res_id = (int)$res_id;
            $this->Read($res_id);
        } else {
            // No ID supplied, create a new object
            $this->isNew = true;
            $this->res_id = 0;
            $this->quiz_id = '';
            $this->uid = 0;
            $this->dt = 0;
            $this->ip = '';
            $this->token = '';
            $this->introfields = '';
            $this->asked = 0;
        }

        if (!$this->isNew) {    // existing record, get the questions and answers
            $this->Values = Value::getByResult($this->res_id);
            $this->Questions = array();
            foreach ($this->Values as $val) {
                $this->Questions[$val->q_id] = Question::getInstance($val->q_id);
            }
        }
    }


    /**
     * Get a specific result set.
     * Gets the value from the session if no ID is supplied.
     *
     * @param   integer $res_id     Optional result set ID
     * @return  object      Instance of a Result object
     */
    public static function getResult($res_id = 0)
    {
        if ($res_id == 0) {
            $res_id = SESS_getVar('quizzer_resultset');
        }
        return new self($res_id);
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
        if ($id > 0) $this->id = (int)$id;

        $sql = "SELECT * FROM {$_TABLES['quizzer_results']}
                WHERE res_id = " . $this->id;
        //echo $sql;die;
        $res1 = DB_query($sql);
        if (!$res1)
            return false;

        $A = DB_fetchArray($res1, false);
        if (empty($A)) return false;

        $this->SetVars($A);
        return true;
    }


    /**
     * Set all the variables from a DB record.
     *
     * @param   array   $A      Array of values
     */
    public function SetVars($A)
    {
        if (!is_array($A))
            return false;

        $this->res_id = (int)$A['res_id'];
        $this->quiz_id = COM_sanitizeID($A['quiz_id']);
        $this->dt = (int)$A['dt'];
        $this->uid = (int)$A['uid'];
        $this->ip = $A['ip'];
        $this->token = $A['token'];
        $this->introfields = $A['introfields'];
        $this->asked = (int)$A['asked'];
    }


    /**
     * Retrieve all the values for this set into the supplied field objects.
     *
     * @param   array   $fields     Array of Field objects
     */
    public function getValues($fields)
    {
        global $_TABLES;
        $sql = "SELECT * from {$_TABLES['quizzer_values']}
                WHERE res_id = '{$this->id}'";
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
        $questions = Question::getQuestions($Q->id, $Q->num_q);
        SESS_unset('quizzer_resultset');
        SESS_setVar('quizzer_questions', $questions);
        $Q->num_q = count($questions);   // replace with actual number
        //$num_q = min($Q->num_q, Question::countQ($quiz_id));
        $this->uid = $_USER['uid'];
        $this->quiz_id = COM_sanitizeID($quiz_id);
        $this->dt = time();
        $this->ip = $_SERVER['REMOTE_ADDR'];
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
            $this->id = DB_insertID();
            SESS_setVar('quizzer_resultset', $this->id);
            Cache::Clear();
        } else {
            $this->id = 0;
        }
        return $this->id;
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
            $correct += $this->Questions[$V->q_id]->Verify($V->value);
        }
        if (!is_int($correct)) {
            $correct = round($correct, 2);
        }

        $Q = Quiz::getInstance($this->quiz_id);
        $Q->Reset();
        if ($total_q > 0) {
            $pct = (int)(($correct / $total_q) * 100);
        } else {
            $pct = 0;
        }
        $prog_status = $Q->getGrade($pct);
        if ($prog_status == 'success') {
            $msg = $Q->pass_msg;
            /////// TODO : testing gift card rewards
            //$msg .= \Quizzer\Rewards\GiftCard::CreateReward();
        } else {
            $msg = $Q->fail_msg;
        }
        $T = new \Template(QUIZ_PI_PATH . '/templates');
        $T->set_file('result', 'finish.thtml');
        $T->set_var(array(
            'pct' => $pct,
            'quiz_name' => $Q->name,
            'quiz_id'   => $Q->id,
            'correct' => $correct,
            'total' => $total_q,
            'prog_status' => $prog_status,
            'finish_msg' => $msg,
        ) );
        $T->parse('output', 'result');
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

}

?>
