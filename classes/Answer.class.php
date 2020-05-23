<?php
/**
 * Class to describe question answers.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2020 Lee Garner <lee@leegarner.com>
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
class Answer
{
    /** Question record ID.
     * @var integer */
    private $questionID = 0;

    /** Answer ID, numbered within the question.
     * @var integer */
    private $answerID = 0;

    /** Answer value text.
     * @var string */
    private $answerText = '';

    /** Flag to indicate that this is a correct answer.
     * @var boolean */
    private $is_correct = 0;


    /**
     * Constructor.
     *
     * @param   array   $A      DB record array, NULL for new record
     */
    public function __construct($A = NULL)
    {
        if (is_array($A)) {
            $this->setVars($A);
        }
    }


    /**
     * Get all the answers for a given question.
     *
     * @param   integer $q_id       Question ID
     * @return  array       Array of Answer objects
     */
    public static function getByQuestion($q_id)
    {
        global $_TABLES;

        $q_id = (int)$q_id;
        $retval = array();
        $sql = "SELECT * FROM {$_TABLES['quizzer_answers']}
            WHERE questionID = '{$q_id}'";
        $res = DB_query($sql);
        if ($res) {
            while ($A = DB_fetchArray($res, false)) {
                $retval[$A['answerID']] = new self($A);
            }
        }
        return $retval;
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

        $this->questionID = (int)$A['questionID'];
        $this->answerID = (int)$A['answerID'];
        $this->is_correct  = isset($A['is_correct']) ? (int)$A['is_correct'] : 0;
        $this->answerText = $A['answerText'];
        return true;
    }


    /**
     * Save the field definition to the database.
     *
     * @param   array   $A  Array of name->value pairs
     * @return  string          Error message, or empty string for success
     */
    public function Save()
    {
        global $_TABLES;

        $answerText = DB_escapeString($this->answerText);
        $sql = "INSERT INTO {$_TABLES['quizzer_answers']} SET
                questionID = '{$this->getQid()}',
                answerID = '{$this->getAid()}',
                answerText = '$answerText',
                is_correct = '{$this->isCorrect()}'
            ON DUPLICATE KEY UPDATE
                answerText = '$answerText',
                is_correct = '{$this->isCorrect()}'";
        DB_query($sql);
        if (DB_error()) {
            return 6;
        }
        Cache::clear(array('answers', $this->questionID));
        return 0;
    }


    /**
     * Delete the current question definition.
     *
     * @param   integer $q_id       ID number of the question
     * @param   string  $a_id       Comma-separated answer IDs
     */
    public static function Delete($q_id, $a_id)
    {
        global $_TABLES;

        $q_id = (int)$q_id;
        $a_id= DB_escapeString($a_id);
        $sql = "DELETE FROM {$_TABLES['quizzer_answers']}
            WHERE questionID = $q_id
            AND answerID IN ($a_id)";
        DB_query($sql);
    }


    /**
     * Set the question ID.
     *
     * @param   integer $q_id   Question ID
     * @return  object  $this
     */
    public function setQid($q_id)
    {
        $this->questionID = (int)$q_id;
        return $this;
    }


    /**
     * Get the question ID.
     *
     * @return  integer     Question record ID
     */
    public function getQid()
    {
        return (int)$this->questionID;
    }


    /**
     * Set the answer ID.
     *
     * @param   integer $a_id   Answer ID
     * @return  object  $this
     */
    public function setAid($a_id)
    {
        $this->answerID = (int)$a_id;
        return $this;
    }


    /**
     * Get the answer ID.
     *
     * @return  integer     Answer ID
     */
    public function getAid()
    {
        return (Int)$this->answerID;
    }


    /**
     * Set the correct flag.
     *
     * @param   integer $flag   1 if correct, 0 if not
     * @return  object  $this
     */
    public function setCorrect($flag)
    {
        $this->is_correct = $flag ? 1 : 0;
        return $this;
    }


    /**
     * Check if this is a correct answer.
     *
     * @return  integer     1 if correct, 0 if not
     */
    public function isCorrect()
    {
        return $this->is_correct ? 1 : 0;
    }


    /**
     * Set the value text.
     *
     * @param   string  $txt    Value text for the answer
     * @return  object  $this
     */
    public function setValue($txt)
    {
        $this->answerText = $txt;
        return $this;
    }


    /**
     * Get the value text to display.
     *
     * @param   boolean $esc    True to escape for saving
     * @return  string      Value of the answer
     */
    public function getValue($esc = false)
    {
        return $this->answerText;
    }


    /**
     * Convert this answer into an array for AJAX usage.
     *
     * @return  array   Array of object properties
     */
    public function toArray()
    {
        return array(
            'questionID' => $this->questionID,
            'answerID' => $this->answerID,
            'answerText' => $this->answerText,
            'is_correct' => $this->is_correct,
        );
    }

}

?>
