<?php
/**
 * Base class to handle quiz values (user-supplied answers).
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2018 Lee Garner <lee@leegarner.com>
 * @package     quizzes
 * @version     v0.0.1
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
    /** Flag to indicate that this is a new record.
     * @var boolean */
    public $isNew = true;

    /** Internal properties accessed via `__set()` and `__get()`.
     * @var array */
    protected $properties = array();

    /**
     * Constructor. Sets the local properties using the array $item.
     *
     * @param   integer $res_id     ID of the result set, if any
     * @param   integer $q_id       Question ID
     */
    public function __construct($res_id = 0, $q_id = 0)
    {
        global $_USER, $_CONF_QUIZ, $_TABLES;

        $this->isNew = true;
        if ($res_id == 0) {
            $this->res_id = 0;
            $this->q_id = 0;
            $this->value = '';
        } elseif (is_array($res_id)) {
            $this->setVars($res_id, true);
            $this->isNew = false;
        } else {
            $this->Read($res_id, $q_id);
        }
    }


    /**
     * Read this field definition from the database and load the object.
     *
     * @see     self::setVars
     * @param   integer     $res_id     Resulset ID
     * @param   integer     $q_id       Question ID
     * @return  boolean     Status from setVars()
     */
    public function Read($res_id = 0, $q_id = 0)
    {
        global $_TABLES;

        if ($res_id > 0) $this->res_id = $res_id;
        if ($q_id > 0) $this->q_id = $q_id;
        $sql = "SELECT * FROM {$_TABLES['quizzer_values']}
                WHERE res_id = {$this->res_id} AND q_id = {$this->q_id}";
        $res = DB_query($sql, 1);
        if (DB_error() || !$res) return false;
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
     * Set a value into a property.
     *
     * @param   string  $name       Name of property
     * @param   mixed   $value      Value to set
     */
    public function __set($name, $value)
    {
        switch ($name) {
        case 'res_id':
        case 'q_id':
            $this->properties[$name] = (int)$value;
            break;

        case 'value':
            if (!is_array($value)) {
                $value = array();
            }
            $this->properties[$name] = $value;
            break;
        }
    }


    /**
     * Get a property's value, or null if not set.
     *
     * @param   string  $name       Name of property
     * @return  mixed       Value of property, or empty string if undefined
     */
    public function __get($name)
    {
        if (array_key_exists($name, $this->properties)) {
           return $this->properties[$name];
        } else {
            return '';
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

        $this->res_id   = isset($A['res_id']) ? $A['res_id'] : 0;
        $this->q_id     = isset($A['q_id']) ? $A['q_id'] : 0;;
        if ($fromDB) {
            $this->value    = @unserialize($A['value']);
            if ($this->value === false) {
                $this->value = array();
            }
        } else {
            $this->value    = isset($A['value']) ? $A['value'] : '';
        }
        return true;
    }


    /**
     * Delete the current field definition.
     *
     * @param   integer $res_id     Resultset ID
     * @param   integer $q_id       Question ID
     */
    public static function Delete($res_id = 0, $q_id = 0)
    {
        global $_TABLES;

        DB_delete($_TABLES['quizzer_values'], array('res_id', 'q_id'), array($res_id,$q_id));
    }


    /**
     * Save this value to the database.
     *
     * @param   integer $res_id     Resultset ID
     * @param   integer $q_id       Question ID
     * @param   integer|array   $values     Value(s) of answer
     * @return  boolean     True on success, False on failure
     */
    public static function Save($res_id, $q_id, $values)
    {
        global $_TABLES;

        $res_id = (int)$res_id;
        $q_id = (int)$q_id;
        if ($res_id == 0 || $q_id == 0) {
            return false;
        }
        if (!is_array($values)) {
            $values = array($values);
        }
        $value = DB_escapeString(@serialize($values));
        $sql = "INSERT INTO {$_TABLES['quizzer_values']}
                    (res_id, q_id, value)
                VALUES
                    ('$res_id', '$q_id', '$value')
                ON DUPLICATE KEY
                    UPDATE value = '$value'";
        DB_query($sql, 1);
        $status = DB_error();
        return $status ? false : true;
    }


    /**
     * Get all submitted values by resultset.
     * Used for scoring overall results by submitter
     *
     * @param   integer $res_id Resultset ID
     * @return  objecdt         Value object
     */
    public static function getByResult($res_id)
    {
        global $_TABLES;

        $vals = array();
        $sql = "SELECT * FROM {$_TABLES['quizzer_values']}
                WHERE res_id = $res_id";
        $res = DB_query($sql);
        while ($A = DB_fetchArray($res, false)) {
            $vals[] = new self($A);
        }
        return $vals;
    }


    /**
     * Get all submitted values by question.
     * Used for scoring overall results by question.
     *
     * @param   integer $q_id   Question ID
     * @return  objecdt         Value object
     */
    public static function getByQuestion($q_id)
    {
        global $_TABLES;

        $vals = array();
        $q_id = (int)$q_id;
        $sql = "SELECT * FROM {$_TABLES['quizzer_values']}
                WHERE q_id = $q_id";
        $res = DB_query($sql);
        while ($A = DB_fetchArray($res, false)) {
            $vals[] = new self($A);
        }
        return $vals;
    }

}

?>
