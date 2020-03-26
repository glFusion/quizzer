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
    private $q_id = 0;

    /** Answer ID, numbered within the question.
     * @var integer */
    private $a_id = 0;

    /** Answer value text.
     * @var string */
    private $value = '';

    /** Flag to indicate that this is a correct answer.
     * @var boolean */
    private $correct = 0;

    /** Indicate that this answer was submitted.
     * Not part of the DB record.
     * @var boolean */
    private $_submitted = 0;


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
     * Get an instance of a question based on the question type.
     * If the "question" parameter is an array it must include at least q_id
     * and type.
     * Only works to retrieve existing fields.
     *
     * @param   integer $q_id       Question ID
     * @return  array       Array of Answer objects
     */
    public static function getByQuestion($q_id)
    {
        global $_TABLES;

        $q_id = (int)$q_id;
        $cache_key = 'answers_q_' . $q_id;
//        $retval = Cache::get($cache_key);
        if ($retval == NULL) {
            $retval = array();
            $sql = "SELECT * FROM {$_TABLES['quizzer_answers']}
                WHERE q_id = '{$q_id}'";
            $res = DB_query($sql);
            if ($res) {
                while ($A = DB_fetchArray($res, false)) {
                    $retval[$A['a_id']] = new self($A);
                }
            }
            Cache::set($cache_key, $retval, array('answers', $q_id));
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

        $this->q_id = (int)$A['q_id'];
        $this->a_id = (int)$A['a_id'];
        $this->correct = isset($A['correct']) ? (int)$A['correct'] : 0;
        $this->value = $A['value'];
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

        $value = DB_escapeString($this->value);
        $sql = "INSERT INTO {$_TABLES['quizzer_answers']} SET
                q_id = '{$this->getQid()}',
                a_id = '{$this->getAid()}',
                value = '$value',
                correct = '{$this->isCorrect()}'
            ON DUPLICATE KEY UPDATE
                value = '$value',
                correct = '{$this->isCorrect()}'";
COM_errorLog($sql);
        DB_query($sql);
        if (DB_error()) {
            return 6;
        }
        Cache::clear(array('answers', $this->q_id));
        return 0;
    }


    /**
     * Delete the current question definition.
     *
     * @param   integer $q_id       ID number of the question
     * @param   string  @a_id       Comma-separated answer IDs
     */
    public static function Delete($q_id, $a_id)
    {
        global $_TABLES;

        $q_id = (int)$q_id;
        $a_id= DB_escapeString($a_id);
        $sql = "DELETE FROM {$_TABLES['quizzer_answers']}
            WHERE q_id = $q_id
            AND a_id IN ($a_id)";
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
        $this->q_id = (int)$q_id;
        return $this;
    }


    /**
     * Get the question ID.
     *
     * @return  integer     Question record ID
     */
    public function getQid()
    {
        return (int)$this->q_id;
    }


    /**
     * Set the answer ID.
     *
     * @param   integer $a_id   Answer ID
     * @return  object  $this
     */
    public function setAid($a_id)
    {
        $this->a_id = (int)$a_id;
        return $this;
    }


    /**
     * Get the answer ID.
     *
     * @return  integer     Answer ID
     */
    public function getAid()
    {
        return (Int)$this->a_id;
    }


    /**
     * Set the correct flag.
     *
     * @param   integer $flag   1 if correct, 0 if not
     * @return  object  $this
     */
    public function setCorrect($flag)
    {
        $this->correct = $flag ? 1 : 0;
        return $this;
    }


    /**
     * Check if this is a correct answer.
     *
     * @return  integer     1 if correct, 0 if not
     */
    public function isCorrect()
    {
        return $this->correct ? 1 : 0;
    }


    /**
     * Set the value text.
     *
     * @param   string  $txt    Value text for the answer
     * @return  object  $this
     */
    public function setValue($txt)
    {
        $this->value = $txt;
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
        return $this->value;
    }


    /**
     * Indicate that this answer was submitted.
     *
     * @param   boolean $flag   Non-zero if submitted, zero if not
     * @return  object  $this
     */
    public function setSubmitted($flag)
    {
        $this->_submitted = $flag ? 1 : 0;
    }


    /**
     * Convert this answer into an array for AJAX usage.
     *
     * @return  array   Array of object properties
     */
    public function toArray()
    {
        return array(
            'q_id' => $this->q_id,
            'a_id' => $this->a_id,
            'value' => $this->value,
            'correct' => $this->correct,
            'submitted' => $this->_submitted,
        );
    }

}

?>
