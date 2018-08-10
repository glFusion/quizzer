<?php
/**
*   Class to handle the Quiz result sets.
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2018 Lee Garner <lee@leegarner.com>
*   @package    quizzer
*   @version    0.0.1
*   @license    http://opensource.org/licenses/gpl-2.0.php 
*               GNU Public License v2 or later
*   @filesource
*/
namespace Quizzer;


/**
*   Class for a single form's result
*/
class Result
{
    /** Questions, with answers
    *   @var array */
    public $Questions = array();

    /** Values
     * @var array */
    public $Values = array();

    /** Result record ID
    *   @var integer */
    var $res_id;

    /** Quize ID
    *   @var string */
    var $quiz_id;

    /** Submitting user ID
    *   @var integer */
    var $uid;

    /** Submission date
    *   @var string */
    var $dt;

    /** IP address of submitter
    *   @var string */
    var $ip;

    /** Unique token for this submission
    *   @var string */
    var $token;

    /** Answers provided in intro fields
     *  @var string */
    var $introfields;


    /**
    *   Constructor.
    *   If a result set ID is specified, it is read. If an array is given
    *   then the fields are simply copied from the array, e.g. when displaying
    *   many results in a table.
    *
    *   @param  mixed   $res_id     Result set ID or array from DB
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
        }

        if (!$this->isNew) {    // existing record, get the questions and answers
            $this->Values = Value::getByResult($this->res_id);
            $this->Questions = array();
            foreach ($this->Values as $val) {
                $this->Questions[$val->q_id] = Question::getInstance($val->q_id);
            }
        }
    }


    public static function getResult($res_id = 0)
    {
        if ($res_id == 0) {
            $res_id = SESS_getVar('quizzer_resultset');
        }
        return new self($res_id);
    }


    /**
    *   Read all quizzer variables into the $items array.
    *   Set the $uid paramater to read another user's quizzer into
    *   the current object instance.
    *
    *   @param  integer $id     Result set ID
    *   @return boolean         True on success, False on failure/not found
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
    *   Set all the variables from a form or when read from the DB
    *
    *   @param  array   $A      Array of values
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
    }


    /**
    *   Retrieve all the values for this set into the supplied field objects.
    *
    *   @param  array   $fields     Array of Field objects
    */
    public function GetValues($fields)
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
    *   Save the field results in a new result set
    *
    *   @param  string  $quiz_id     Form ID
    *   @param  array   $fields     Array of Field objects
    *   @param  array   $vals       Array of values ($_POST)
    *   @param  integer $uid        Optional user ID, default=$_USER['uid']
    *   @return mixed       False on failure/invalid, result ID on success
    */
    function SaveData($quiz_id, $fields, $vals, $uid = 0)
    {
        global $_USER;

        $this->uid = $uid == 0 ? (int)$_USER['uid'] : (int)$uid;
        $this->quiz_id = COM_sanitizeID($quiz_id);

        // Get the result set ID, creating a new one if needed
        if ($this->isNew) {
            $res_id = $this->Create($quiz_id, $this->uid);
        } else {
            $res_id = $this->id;
        }
        if (!$res_id)       // couldn't create a result set
            return false;

        foreach ($fields as $field) {
            // Get the value to save and have the field save it
            $newval = $field->valueFromForm($vals);
            $field->SaveData($newval, $res_id);
        }
        Cache::clear(array('result_fields', 'result_' . $res_id));
        return $res_id;
    }


    /**
    *   Creates a result set in the database.
    *
    *   @param  string  $quiz_id Form ID
    *   @param  integer $uid    Optional user ID, if not the current user
    *   @return integer         New result set ID
    */
    function Create($quiz_id, $introfields = array())
    {
        global $_TABLES, $_USER;

        $this->uid = $_USER['uid'];
        $this->quiz_id = COM_sanitizeID($quiz_id);
        $this->dt = time();
        $this->ip = $_SERVER['REMOTE_ADDR'];
        $ip = DB_escapeString($this->ip);
        $this->token = md5(time() . rand(1,100));
        $sql = "INSERT INTO {$_TABLES['quizzer_results']} SET
                quiz_id='{$this->quiz_id}',
                uid='{$this->uid}',
                dt='{$this->dt}',
                ip = '$ip',
                introfields = '" . DB_escapeString(@serialize($introfields)) . "',
                token = '{$this->token}'";
        DB_query($sql, 1);
        if (!DB_error()) {
            $this->id = DB_insertID();
        } else {
            $this->id = 0;
        }
        return $this->id;
    }


    /**
     * Delete all results for a quiz
     *
     * @param   string  $quiz_id    
     */
    public static function ResetQuiz($quiz_id)
    {
        $results = self::findQuiz($quiz_id);
        foreach ($results as $R) {
            self::Delete($R->res_id);
        }
    }


    /**
    *   Delete a single result set
    *
    *   @param  integer $res_id     Database ID of result to delete
    *   @return boolean     True on success, false on failure
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
    *   Delete the form values related to a result set.
    *
    *   @param  integer $res_id Required result ID
    *   @param  integer $uid    Optional user ID
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
    *   Returns this result set's token.
    *   The token provides a very basic authentication mechanism when
    *   the after-submission action is to display the results, to ensure
    *   that only the newly-submitted result set is displayed.
    *
    *   @return string      Token saved with this result set
    */
    public function Token()
    {
        return $this->token;
    }


    public function showScore()
    {
        $total_q = 0;
        $correct = 0;

        foreach ($this->Values as $V) {
            $total_q++;
            if ($this->Questions[$V->q_id]->Verify($V->value)) {
                $correct++;
            }
        }
        $Q = Quiz::getInstance($this->quiz_id);
        if ($total_q > 0) {
            $pct = (int)(($correct / $total_q) * 100);
        } else {
            $pct = 100;
        }
        if ($pct > 75) {
            $prog_status = 'success';
        } elseif ($pct < 50) {
            $prog_status = 'danger';
        } else {
            $prog_status = 'warning';
        } 
        $T = new \Template(QUIZ_PI_PATH . '/templates');
        $T->set_file('result', 'finish.thtml');
        $T->set_var(array(
            'pct' => $pct,
            'quiz_name' => $Q->name,
            'correct' => $correct,
            'total' => $total_q,
            'prog_status' => $prog_status,
            'finish_msg' => $Q->pass_msg,
        ) );
        $T->parse('output', 'result');
        return $T->finish($T->get_var('output'));
    }


    /**
     * Find a particular quiz
     *
     * @param   string  $quiz_id    Quiz ID
     * @return  object      Quiz object
     */
    public static function findQuiz($quiz_id)
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

}

?>
