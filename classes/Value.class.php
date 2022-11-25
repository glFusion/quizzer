<?php
/**
 * Base class to handle quiz values (user-supplied answers).
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2018-2022 Lee Garner <lee@leegarner.com>
 * @package     quizzes
 * @version     v0.2.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Quizzer;
use glFusion\Database\Database;
use glFusion\Log\Log;
use Quizzer\Models\DataArray;


/**
 * Class for answer values.
 */
class Value
{
    const FORFEIT = -1;
    const UNANSWERED = NULL;

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
    public function __construct($resultID=0, int $questionID=0)
    {
        global $_USER, $_CONF_QUIZ, $_TABLES;

        $this->isNew = true;
        if (is_array($resultID)) {
            $this->setVars(new DataArray($resultID), true);
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
    public function Read(?int $resultID, ?int $questionID) : bool
    {
        global $_TABLES;

        if (!empty($resultID)) {
            $this->resultID = (int)$resultID;
        }
        if (!empty($questionID)) {
            $this->questionID = (int)$questionID;
        }
        try {
            $data = Database::getInstance()->conn->executeQuery(
                "SELECT * FROM {$_TABLES['quizzer_values']}
                WHERE resultID = ? AND questionID = ?",
                array($this->resultID, $this->questionID),
                array(Database::INTEGER, Database::INTEGER)
            )->fetchAssociative();
        } catch (\Throwable $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            $data = false;
        }
        if (!empty($data)) {
            if ($this->setVars(new DataArray($data), true)) {
                $this->isNew = false;
                return true;
            }
        }
        return false;
    }


    /**
     * Set all variables for this field.
     * Data is expected to be from $_POST or a database record.
     *
     * @param   DataArray   $A  Array of name=>value pairs from DB or form
     * @param   boolean $fromDB True if reading from the DB, False if from a form
     */
    public function setVars(DataArray $A, bool $fromDB=false) : bool
    {
        $this->resultID = $A->getInt('resultID');
        $this->questionID = $A->getInt('questionID');
        $this->orderby = $A->getInt('orderby');
        if ($fromDB) {
            if ($A['value'] == self::FORFEIT || $A['value'] == self::UNANSWERED) {
                $this->value = $A['value'];
            } else {
                $this->value    = @unserialize($A['value']);
                if ($this->value === false) {
                    $this->value = array();
                }
            }
        } else {
            // Coming from a submission form
            $this->value = $A->getString('value');
        }
        return true;
    }


    /**
     * Delete the current field definition.
     *
     * @param   integer $resultID     Resultset ID
     * @param   integer $questionID       Question ID
     */
    public static function Delete(int $resultID, int $questionID) : void
    {
        global $_TABLES;

        try {
            Database::getInstance()->conn->delete(
                $_TABLES['quizzer_values'],
                array(
                    'resultID' => $resultID,
                    'questionID' => $questionID,
                ),
                array(Database::INTEGER, Database::INTEGER)
            );
        } catch (\Throwable $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
        }
    }


    public static function deleteByQuestion(array $q_ids) : bool
    {
        global $_TABLES;

        try {
            Database::getInstance()->conn->executeStatement(
                "DELETE FROM {$_TABLES['quizzer_values']} WHERE questionID IN (?)",
                array($q_ids),
                array(Database::PARAM_INT_ARRAY)
            );
            return true;
        } catch (\Throwable $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            return false;
        }
    }


/*    public static function resetQuiz(string $quizID) : void
    {
        global $_TABLES;

        $db = Database::getInstance();
        try {
            $db->conn->delete(
                $_TABLES['quizzer_values'],
                array('')

    }
 */

    /**
     * Create a set of answer records for a new result set.
     * Populates the values table with the result and question IDs, and
     * empty answer fields.
     *
     * @param   integer $resultID     Result ID
     * @param   array   $questions  Array of question IDs
     */
    public static function createResultSet(int $resultID, array $questions) : void
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
        try {
            Database::getInstance()->conn->executeStatement(
                "INSERT INTO {$_TABLES['quizzer_values']}
                (resultID, orderby, questionID) VALUES $values"
            );
        } catch (\Throwable $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
        }
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
     * The resultset has already been created so only an UPDATE is needed.
     *
     * @param   integer $resultID     Resultset ID
     * @param   integer $questionID       Question ID
     * @param   integer|array   $values     Value(s) of answer
     * @return  boolean     True on success, False on failure
     */
    public static function Save(int $resultID, int $questionID, $values) : bool
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
            $values = @serialize($values);
        }
        try {
            Database::getInstance()->conn->update(
                $_TABLES['quizzer_values'],
                array('value' => $values),
                array('resultID' => $resultID, 'questionID' => $questionID),
                array(Database::STRING, Database::INTEGER, Database::INTEGER)
            );
            return true;
        } catch (\Throwable $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            return false;
        }
    }


    /**
     * Get the first unanswered question by searching the values table.
     *
     * @param   integer $resultID     Result set ID
     * @return  integer     ID of the question to be presented
     */
    public static function getFirstUnanswered(int $resultID) : int
    {
        global $_TABLES;

        try {
            $data = Database::getInstance()->conn->executeQuery(
                "SELECT questionID FROM {$_TABLES['quizzer_values']}
                WHERE resultID = ? AND value IS NULL
                ORDER BY resultID,orderby ASC LIMIT 1",
                array($resultID),
                array(Database::INTEGER)
            )->fetchAssociative();
        } catch (\Throwable $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            $data = false;
        }
        if (is_array($data)) {
            return (int)$data['questionID'];
        } else {
            return 0;
        }
    }


    /**
     * Get all submitted values by resultset.
     * Used for scoring overall results by submitter
     *
     * @param   integer $resultID Resultset ID
     * @return  array   Array of Value objects
     */
    public static function getByResult(int $resultID) : array
    {
        global $_TABLES;

        $vals = array();
        try {
            $stmt = Database::getInstance()->conn->executeQuery(
                "SELECT * FROM {$_TABLES['quizzer_values']}
                WHERE resultID = ?
                ORDER BY orderby ASC",
                array($resultID),
                array(Database::INTEGER)
            );
        } catch (\Throwable $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            $stmt = false;
        }
        if ($stmt) {
            while ($A = $stmt->fetchAssociative()) {
                $vals[$A['orderby']] = new self($A);
            }
        }
        return $vals;
    }


    /**
     * Get all submitted values by question.
     * Used for scoring overall results by question.
     *
     * @param   integer $questionID   Question ID
     * @return  array       Array of Value objects
     */
    public static function getByQuestion(int $questionID) : array
    {
        global $_TABLES;

        $vals = array();
        $questionID = (int)$questionID;
        try {
            $stmt = Database::getInstance()->conn->executeQuery(
                "SELECT * FROM {$_TABLES['quizzer_values']}
                WHERE questionID = ?",
                array($questionID),
                array(Database::INTEGER)
            );
        } catch (\Throwable $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            $stmt = false;
        }
        if ($stmt) {
            while ($A = $stmt->fetchAssociative()) {
                $vals[] = new self($A);
            }
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
        return !self::isValidAnswer($this->value);
    }


    /**
     * Static function to check if an answer value is valid.
     * Returns False if the value represents forfeit or unanswered.
     *
     * @param   mixed   $val    Answer value
     * @return  boolean     True if valid (array), False if not
     */
    public static function isValidAnswer($val)
    {
        return $val !== self::FORFEIT && $val !== self::UNANSWERED;
    }

}

