<?php
/**
 * Base class to handle quiz values (user-supplied answers).
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2018-2020 Lee Garner <lee@leegarner.com>
 * @package     quizzes
 * @version     v0.0.3
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Quizzer;

/**
 * Class for answer values.
 */
class Value
{
    const FORFEIT = -1;

    /** Flag to indicate that this is a new record.
     * @var boolean */
    private $isNew = true;

    /** Result set record ID.
     * @var integer */
    private $resultID = 0;

    /** Order in which the question appears on the quiz.
     * @var integer */
    private $orderby = 0;

    /** Question record ID.
     * @var integer */
    private $questionID = 0;

    /** Value(s) of the response.
     * @var array */
    private $value = array();


    /**
     * Constructor. Sets the local properties using the array $item.
     *
     * @param   integer $resultID     ID of the result set, if any
     * @param   integer $questionID       Question ID
     */
    public function __construct($resultID = 0, $questionID = 0)
    {
        global $_USER, $_CONF_QUIZ, $_TABLES;

        $this->isNew = true;
        if (is_array($resultID)) {
            $this->setVars($resultID, true);
            $this->isNew = false;
        } else {
            $this->Read((int)$resultID, (int)$questionID);
        }
    }


    /**
     * Read this field definition from the database and load the object.
     *
     * @see     self::setVars
     * @param   integer     $resultID     Resulset ID
     * @param   integer     $questionID       Question ID
     * @return  boolean     Status from setVars()
     */
    public function Read($resultID = 0, $questionID = 0)
    {
        global $_TABLES;

        if ($resultID > 0) {
            $this->resultID = (int)$resultID;
        }
        if ($questionID > 0) {
            $this->questionID = (int)$questionID;
        }
        $sql = "SELECT * FROM {$_TABLES['quizzer_values']}
                WHERE resultID = {$this->resultID} AND questionID = {$this->questionID}";
        $res = DB_query($sql, 1);
        if (DB_error() || !$res) {
            return false;
        }
        $A = DB_fetchArray($res, false);
        if ($this->setVars($A, true)) {
            $this->isNew = false;
            return true;
        } else {
            $this->isNew = true;
            return false;
        }
    }


    /**
     * Set all variables for this field.
     * Data is expected to be from $_POST or a database record.
     *
     * @param   array   $A      Array of name=>value pairs from DB or 
     * @param   boolean $fromDB True if reading from the DB, False if from a form
     */
    public function setVars($A, $fromDB=false)
    {
        if (!is_array($A)) {
            return false;
        }

        $this->resultID   = isset($A['resultID']) ? (int)$A['resultID'] : 0;
        $this->questionID     = isset($A['questionID']) ? (int)$A['questionID'] : 0;;
        $this->orderby  = isset($A['orderby']) ? (int)$A['orderby'] : 0;;
        if ($fromDB) {
            if ($A['value'] == self::FORFEIT) {
                $this->value = $A['value'];
            } else {
                $this->value    = @unserialize($A['value']);
                if ($this->value === false) {
                    $this->value = array();
                }
            }
        } else {
            // Coming from a submission form
            $this->value    = isset($A['value']) ? $A['value'] : '';
        }
        return true;
    }


    /**
     * Delete the current field definition.
     *
     * @param   integer $resultID     Resultset ID
     * @param   integer $questionID       Question ID
     */
    public static function Delete($resultID = 0, $questionID = 0)
    {
        global $_TABLES;

        DB_delete(
            $_TABLES['quizzer_values'],
            array('resultID', 'questionID'),
            array($resultID,$questionID)
        );
    }


    /**
     * Create a set of answer records for a new result set.
     * Populates the values table with the result and question IDs, and
     * empty answer fields.
     *
     * @param   integer $resultID     Result ID
     * @param   array   $questions  Array of question IDs
     */
    public static function createResultSet($resultID, $questions)
    {
        global $_TABLES;

        $resultID = (int)$resultID;
        $i = 0;
        foreach ($questions as $key=>$questionID) {
            $questionID = (int)$questionID;
            $i++;
            $values[] = "($resultID, $i, $questionID)";
        }
        $values = implode(',', $values);
        $sql = "INSERT INTO {$_TABLES['quizzer_values']}
            (resultID, orderby, questionID) VALUES $values";
        DB_query($sql);
    }


    /**
     * Forfeit this question, e.g. when the timer runs out.
     *
     * @param   integer $resultID     Resultset ID
     * @param   integer $questionID       Question ID
     * @return  boolean     True on success, False on failure
     */
    public static function Forfeit($resultID, $questionID)
    {
        return self::Save($resultID, $questionID, self::FORFEIT);
    }


    /**
     * Save this value to the database.
     *
     * @param   integer $resultID     Resultset ID
     * @param   integer $questionID       Question ID
     * @param   integer|array   $values     Value(s) of answer
     * @return  boolean     True on success, False on failure
     */
    public static function Save($resultID, $questionID, $values)
    {
        global $_TABLES;

        $resultID = (int)$resultID;
        $questionID = (int)$questionID;
        if ($resultID == 0 || $questionID == 0) {
            return false;
        }

        if ($values != self::FORFEIT) {
            if (!is_array($values)) {
                $values = array($values);
            }
            $values = DB_escapeString(@serialize($values));
        }
        /*$sql = "INSERT INTO {$_TABLES['quizzer_values']}
                    (resultID, questionID, value)
                VALUES
                    ('$resultID', '$questionID', '$value')
                ON DUPLICATE KEY
                UPDATE value = '$value'";*/
        $sql = "UPDATE {$_TABLES['quizzer_values']}
            SET value = '$values'
            WHERE resultID = $resultID AND questionID = $questionID";
        DB_query($sql, 1);
        $status = DB_error();
        return $status ? false : true;
    }


    /**
     * Get the first unanswered question by searching the values table.
     *
     * @param   integer $resultID     Result set ID
     * @return  integer     ID of the question to be presented
     */
    public static function getFirstUnanswered($resultID)
    {
        global $_TABLES;

        return (int)DB_getItem(
            $_TABLES['quizzer_values'],
            'questionID',
            "resultID = $resultID AND value IS NULL ORDER BY resultID,orderby ASC LIMIT 1"
        );
    }


    /**
     * Get all submitted values by resultset.
     * Used for scoring overall results by submitter
     *
     * @param   integer $resultID Resultset ID
     * @return  objecdt         Value object
     */
    public static function getByResult($resultID)
    {
        global $_TABLES;

        $vals = array();
        $sql = "SELECT * FROM {$_TABLES['quizzer_values']}
                WHERE resultID = $resultID
                ORDER BY orderby ASC";
        $res = DB_query($sql);
        while ($A = DB_fetchArray($res, false)) {
            $vals[$A['orderby']] = new self($A);
        }
        return $vals;
    }


    /**
     * Get all submitted values by question.
     * Used for scoring overall results by question.
     *
     * @param   integer $questionID   Question ID
     * @return  objecdt         Value object
     */
    public static function getByQuestion($questionID)
    {
        global $_TABLES;

        $vals = array();
        $questionID = (int)$questionID;
        $sql = "SELECT * FROM {$_TABLES['quizzer_values']}
                WHERE questionID = $questionID";
        $res = DB_query($sql);
        while ($A = DB_fetchArray($res, false)) {
            $vals[] = new self($A);
        }
        return $vals;
    }


    /**
     * Check if this is a new record.
     *
     * @return  integer     1 if new, 0 if not
     */
    public function isNew()
    {
        return $this->isNew ? 1 : 0;
    }


    /**
     * Get the answer value.
     *
     * @return  array   Value array
     */
    public function getValue()
    {
        return $this->value;
    }


    /**
     * Get the question record ID related to this answer.
     *
     * @return  integer     Question record ID
     */
    public function getQuestionID()
    {
        return (int)$this->questionID;
    }


    /**
     * Get the sequence number for this question to appear on the quiz.
     *
     * @return  integer     Sequence number
     */
    public function getOrderby()
    {
        return (int)$this->orderby;
    }


    /**
     * Check if this answer has been completed. The value will be empty if not.
     *
     * @return  boolean     True if an answer has been recorded
     */
    public function hasAnswer()
    {
        return !empty($this->value);
    }


    /**
     * Check if this answer was forfeited (not answered).
     *
     * @return  boolean     True if forfeit, False if answered
     */
    public function isForfeit()
    {
        return (
            $this->value == self::FORFEIT ||
            $this->value == NULL
        );
    }

}

?>
