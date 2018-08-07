<?php
/**
*   Base class to handle quiz questions
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2018 Lee Garner <lee@leegarner.com>
*   @package    quizzes
*   @version    0.0.1
*   @license    http://opensource.org/licenses/gpl-2.0.php
*               GNU Public License v2 or later
*   @filesource
*/
namespace Quizzer;

/**
*   Class for form fields
*/
class Question
{
    const MAX_ANSWERS = 5;
    public $isNew;
    public $options = array();  // Form object needs access
    public $Answers = array();  // Answer options
    protected $properties = array();
    protected $sub_type = 'regular';

    /**
    *   Constructor.  Sets the local properties using the array $item.
    *
    *   @param  integer $id     ID of the existing field, empty if new
    *   @param  object  $Form   Form object to which this field belongs
    */
    public function __construct($id = 0, $quiz_id=NULL)
    {
        global $_USER, $_CONF_QUIZ, $_TABLES;

        $this->isNew = true;
        if ($id == 0) {
            $this->q_id = 0;
            $this->name = '';
            $this->type = 'radio';
            $this->enabled = 1;
            $this->access = 0;
            $this->prompt = '';
            $this->quiz_id = $quiz_id;
        } elseif (is_array($id)) {
            $this->SetVars($id, true);
        } else {
            if ($this->Read($id)) {
                $this->isNew = false;
            }
        }

        if ($this->q_id > 0) {      // get answers
            $sql = "SELECT * FROM {$_TABLES['quizzer_answers']}
                WHERE q_id = '{$this->q_id}'";
            $res = DB_query($sql);
            $this->Answers = array();
            while ($A = DB_fetchArray($res, false)) {
                $this->Answers[$A['a_id']] = $A;
            }
        }
    }


    /**
    *   Get an instance of a field based on the field type.
    *   If the "fld" parameter is an array it must include at least q_id
    *   and type.
    *   Only works to retrieve existing fields.
    *
    *   @param  mixed   $question   Question ID or record
    *   @param  object  $quiz       Quiz object, or NULL
    *   @return object          Question object
    */
    public static function getInstance($question, $quiz = NULL)
    {
        global $_TABLES;
        static $_fields = array();

        if (is_array($question)) {
            // Received a field record, make sure required parameters
            // are present
            if (!isset($question['type']) || !isset($question['q_id'])) {
                return NULL;
            }
        } elseif (is_numeric($question)) {
            // Received a field ID, have to look up the record to get the type
            $q_id = (int)$question;
            $question = self::_readFromDB($q_id);
            if (DB_error() || empty($question)) return NULL;
        }

        $q_id = (int)$question['q_id'];
        if (!array_key_exists($q_id, $_fields)) {
            $cls = __NAMESPACE__ . '\\Question_' . $question['type'];
            $_fields[$q_id] = new $cls($question);
        }
        return $_fields[$q_id];
    }


    /**
    *   Read this field definition from the database and load the object
    *
    *   @see Question::SetVars
    *   @uses Question::_readFromDB()
    *   @param  string  $name   Optional field name
    *   @return boolean     Status from SetVars()
    */
    public function Read($id = 0)
    {
        if ($id != 0) $this->q_id = $id;
        $A = self::_readFromDB($id);
        return $A ? $this->setVars($A, true) : false;
    }


    /**
    *   Actually read a field from the database
    *
    *   @param  integer $id     Question ID
    *   @return mixed       Array of fields or False on error
    */
    private static function _readFromDB($id)
    {
        global $_TABLES;

        $sql = "SELECT * FROM {$_TABLES['quizzer_questions']}
                WHERE q_id='" . (int)$id . "'";
        $res = DB_query($sql, 1);
        if (DB_error() || !$res) return false;
        return DB_fetchArray($res, false);
    }


    /**
    *   Set a value into a property
    *
    *   @uses   hour24to12()
    *   @param  string  $name       Name of property
    *   @param  mixed   $value      Value to set
    */
    public function __set($name, $value)
    {
        global $LANG_FORMS;
        switch ($name) {
        case 'quiz_id':
            $this->properties[$name] = COM_sanitizeID($value);
            break;

        case 'q_id':
            $this->properties[$name] = (int)$value;
            break;

        case 'enabled':
            $this->properties[$name] = $value == 0 ? 0 : 1;
            break;

        case 'question':
        case 'name':
        case 'type':
        case 'help_msg':
            $this->properties[$name] = trim($value);
            break;

        case 'value':
            $this->properties['value'] = $this->setValue($value);
            break;

        }
    }


    /**
    *   Get a property's value
    *
    *   @param  string  $name       Name of property
    *   @return mixed       Value of property, or empty string if undefined
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
    *   Set all variables for this field.
    *   Data is expected to be from $_POST or a database record
    *
    *   @param  array   $item   Array of fields for this item
    *   @param  boolean $fromdb Indicate whether this is read from the DB
    */
    public function SetVars($A, $fromdb=false)
    {
        if (!is_array($A))
            return false;

        $this->q_id = $A['q_id'];
        $this->quiz_id = $A['quiz_id'];
        //$this->orderby = empty($A['orderby']) ? 255 : $A['orderby'];
        $this->enabled = isset($A['enabled']) ? $A['enabled'] : 0;
        $this->question= $A['question'];
        //$this->name = $A['name'];
        // Make sure 'type' is set before 'value'
        $this->type = $A['type'];
        //$this->help_msg = $A['help_msg'];

        /*if (!$fromdb) {
            $this->options = $this->optsFromForm($_POST);
            $this->value = $this->valueFromForm($_POST);
        } else {
            $this->options = @unserialize($A['options']);
            if (!$this->options) $this->options = array();
        }*/
        return true;
    }


    /**
    *   Render the question
    *   @return string  HTML for the question form
    */
    public function Render($last = false)
    {
        global $_CONF, $_TABLES, $LANG_QUIZ, $_GROUPS, $_CONF_QUIZ;

        $retval = '';
        $saveaction = 'savedata';
        $allow_submit = true;

        $T = QUIZ_getTemplate('question', 'question');
        // Set template variables without allowing caching
        $T->set_var(array(
            'quiz_id'       => $this->quiz_id,
            'q_id'          => $this->q_id,
            'question'      => $this->question,
            'next_q_id'     => $this->q_id + 1,
            'is_last'       => $last,
        ) );

        $T->set_block('question', 'AnswerRow', 'Answer');
        foreach ($this->Answers as $A) {
            $T->set_var(array(
                'q_id'      => $A['q_id'],
                'a_id'      => $A['a_id'],
                'answer'    => $A['value'],
                'answer_select' => $this->makeSelection($A['a_id']),
            ) );
            $T->parse('Answer', 'AnswerRow', true);
        }
        $T->parse('output', 'question');
        $retval .= $T->finish($T->get_var('output'));
        return $retval;
    }


    protected function makeSelection($a_id)
    {
        return '<input type="radio" name="a_id" value="' . $a_id . '" />';
    }


    public function Verify($a_id)
    {
        if ($this->Answers[$a_id]['correct'] == 1) {
            return true;
        } else {
            return false;
        }
    }


    public function getCorrectAnswers()
    {
        $correct = array();
        foreach ($this->Answers as $a_id => $ans) {
            if ($ans['correct']) {
                return $a_id;
                $correct[] = $a_id;
            }
        }
        return $correct;
    }


    /**
    *   Edit a field definition.
    *
    *   @uses   DateFormatSelect()
    *   @return string      HTML for editing form
    */
    public function EditDef()
    {
        global $_TABLES, $_CONF, $LANG_FORMS, $LANG_ADMIN, $_CONF_QUIZ;

        $retval = '';
        $format_str = '';
        $listinput = '';

        // Get defaults from the form, if defined
        if ($this->quiz_id > 0) {
            $form = Quiz::getInstance($this->quiz_id);
        }
        $T = new \Template(QUIZ_PI_PATH. '/templates/admin');
        $T->set_file('editform', 'editquestion.thtml');
 
/*        // Create the selection list for the "Position After" dropdown.
        // Include all options *except* the current one
        $sql = "SELECT orderby, name
                FROM {$_TABLES['quizzer_questions']}
                WHERE q_id <> '{$this->q_id}'
                AND quiz_id = '{$this->quiz_id}'
                ORDER BY orderby ASC";
        $res1 = DB_query($sql, 1);
        $orderby_list = '';
        $count = DB_numRows($res1);
        for ($i = 0; $i < $count; $i++) {
            $B = DB_fetchArray($res1, false);
            if (!$B) break;
            $orderby = (int)$B['orderby'] + 1;
            if ($this->isNew && $i == ($count - 1)) {
                $sel =  'selected="selected"';
            } else {
                $sel = '';
            }
            $orderby_list .= "<option value=\"$orderby\" $sel>{$B['name']}</option>\n";
        }*/

        $T->set_var(array(
            'quiz_name' => DB_getItem($_TABLES['quizzer_quizzes'], 'name',
                            "id='" . DB_escapeString($this->quiz_id) . "'"),
            'quiz_id'   => $this->quiz_id,
            'q_id'      => $this->q_id,
            'question'      => $this->question,
            'type'      => $this->type,
  //          'prompt'    => $this->prompt,
            'ena_chk'   => $this->enabled == 1 ? 'checked="checked"' : '',
            'doc_url'   => QUIZ_getDocURL('field_def.html'),
 //           'orderby'   => $this->orderby,
            'editing'   => $this->isNew ? '' : 'true',
//            'orderby_selection' => $orderby_list,
            'help_msg'  => $this->help_msg,
        ) );

        $T->set_block('editform', 'Answers', 'Ans');
        foreach ($this->Answers as $answer) {
            $T->set_var(array(
                'ans_id'    => $answer['a_id'],
                'ans_val'   => $answer['value'],
                'isRadio'   => $this->type == 'radio' ? true : false,
                'ischecked' => $answer['correct'] ? 'checked="checked"' : '',
            ) );
            $T->parse('Ans', 'Answers', true);
        }
        $count = count($this->Answers);
        for ($i = $count + 1; $i <= self::MAX_ANSWERS; $i++) {
            $T->set_var(array(
                'ans_id'    => $i,
                'ans_val'   => '',
                'isRadio'   => $this->type == 'radio' ? true : false,
                'ischecked' => '',
            ) );
            $T->parse('Ans', 'Answers', true);
        }
        $T->parse('output', 'editform');
        $retval .= $T->finish($T->get_var('output'));
        return $retval;
    }


    /**
    *   Save the field definition to the database.
    *
    *   @param  mixed   $val    Value to save
    *   @return string          Error message, or empty string for success
    */
    public function SaveDef($A = '')
    {
        global $_TABLES, $_CONF_QUIZ;

        $q_id = isset($A['q_id']) ? (int)$A['q_id'] : 0;
        $quiz_id = isset($A['quiz_id']) ? COM_sanitizeID($A['quiz_id']) : '';
        if ($quiz_id == '') {
            return 'Invalid form ID';
        }

        // Sanitize the name, especially make sure there are no spaces
        //$A['name'] = COM_sanitizeID($A['name'], false);
        //if (empty($A['name']) || empty($A['type']))
        if (empty($A['type']))
            return;

        $this->SetVars($A, false);

        if ($q_id > 0) {
            // Existing record, perform update
            $sql1 = "UPDATE {$_TABLES['quizzer_questions']} SET ";
            $sql3 = " WHERE q_id = $q_id";
        } else {
            $sql1 = "INSERT INTO {$_TABLES['quizzer_questions']} SET ";
            $sql3 = '';
        }

        $sql2 = "quiz_id = '" . DB_escapeString($this->quiz_id) . "',
                type = '" . DB_escapeString($this->type) . "',
                enabled = '{$this->enabled}',
                help_msg = '" . DB_escapeString($this->help_msg) . "',
                question = '" . DB_escapeString($this->question) . "'";
/*
                options = '" . DB_escapeString(@serialize($this->options)) . "',
                fill_gid = '{$this->fill_gid}',
                results_gid = '{$this->results_gid}'";
                //name = '" . DB_escapeString($this->name) . "',
                //access = '{$this->access}',
        //orderby = '{$this->orderby}',
*/
        $sql = $sql1 . $sql2 . $sql3;
        //echo $sql;die;
        DB_query($sql, 1);

        if (DB_error()) {
            return 5;
        }

        // Now save the answers
        $count = count($A['opt']);   // index into opt and correct arrays
        for ($i = 1; $i <= $count; $i++) {
            if (!empty($A['opt'][$i])) {
                $question = DB_escapeString($A['opt'][$i]);
                if ($this->type == 'radio') {
                    $correct = isset($A['correct']) && $A['correct'] == $i ? 1 : 0;
                } else {
                    $correct = isset($A['correct'][$i]) && $A['correct'][$i] == 1 ? 1 : 0;
                }
                $sql = "INSERT INTO {$_TABLES['quizzer_answers']} SET
                        q_id = '{$this->q_id}',
                        a_id = '$i',
                        value = '$question',
                        correct = '$correct'
                    ON DUPLICATE KEY UPDATE
                        value = '$question',
                        correct = '$correct'";
//echo $sql;die;
                DB_query($sql);
                if (DB_error()) {
                    return 6;
                }
            } else {
                DB_delete($_TABLES['quizzer_answers'], array('q_id', 'a_id'), array($this->q_id, $i));
            }
        }
        return 0;
    }


    /**
    *   Delete the current field definition.
    *
    *   @param  integer $q_id     ID number of the field
    */
    public static function Delete($q_id=0)
    {
        global $_TABLES;

        DB_delete($_TABLES['quizzer_values'], 'q_id', $q_id);
        DB_delete($_TABLES['quizzer_questions'], 'q_id', $q_id);
    }


    /**
    *   Save this field to the database.
    *
    *   @uses   AutoGen()
    *   @param  mixed   $newval Data value to save
    *   @param  integer $res_id Result ID associated with this field
    *   @return boolean     True on success, False on failure
    */
    public function SaveData($newval, $res_id)
    {
        global $_TABLES;

        $res_id = (int)$res_id;
        if ($res_id == 0)
            return false;

        if (isset($this->options['autogen']) &&
            $this->options['autogen'] == QUIZ_AUTOGEN_SAVE) {
            $newval = self::AutoGen($this->properties, 'save');
        }

        // Put the new value back into the array after sanitizing
        $this->value = $newval;
        $db_value = $this->prepareForDB($newval);

        //$this->name = $name;
        $sql = "INSERT INTO {$_TABLES['quizzer_values']}
                    (results_id, q_id, value)
                VALUES (
                    '$res_id',
                    '{$this->q_id}',
                    '$db_value'
                )
                ON DUPLICATE KEY
                    UPDATE value = '$db_value'";
        COM_errorLog($sql);
        DB_query($sql, 1);
        $status = DB_error();
        return $status ? false : true;
    }


    /**
    *   Rudimentary date display function to mimic strftime()
    *   Timestamps don't handle dates far in the past or future.  This function
    *   does a str_replace using a subset of PHP's date variables.  Only the
    *   numeric variables with leading zeroes are used.
    *
    *   @return string  Date formatted for display
    */
    public function DateDisplay()
    {
        if ($this->type != 'date')
            return $this->value_text;

        $dt_tm = explode(' ', $this->value);
        if (strpos($dt_tm[0], '-')) {
            list($year, $month, $day) = explode('-', $dt_tm[0]);
        } else {
            $year = '0000';
            $month = '01';
            $day = '01';
        }
        if (isset($dt_tm[1]) && strpos($dt_tm[1], ':')) {
            list($hour, $minute, $second) = explode(':', $dt_tm[1]);
        } else {
            $hour = '00';
            $minute = '00';
            $second = '00';
        }

        switch ($this->options['input_format']) {
        case 2:
            $retval = sprintf('%02d/%02d/%04d', $day, $month, $year);
            break;
        case 1:
        default:
            $retval = sprintf('%02d/%02d/%04d', $month, $day, $year);
            break;
        }
        if ($this->options['showtime'] == 1) {
            if ($this->options['timeformat'] == '12') {
                list($hour, $ampm) = $this->hour24to12($hour);
                $retval .= sprintf(' %02d:%02d %s', $hour, $minute, $ampm);
            } else {
                $retval .= sprintf(' %02d:%02d', $hour, $minute);
            }
        }

        /*if (empty($this->options['format']))
            return $this->value;

        $formats = array('%Y', '%d', '%m', '%H', '%i', '%s');
        $values = array($year, $day, $month, $hour, $minute, $second);
        $retval = str_replace($formats, $values, $this->options['format']);*/

        return $retval;
    }


    /**
    *   Get the defined date formats into an array.
    *   Static for now, maybe allow more user-defined options in the future.
    *
    *   return  array   Array of date formats
    */
    public function DateFormats()
    {
        global $LANG_FORMS;
        $_formats = array(
            1 => $LANG_FORMS['month'].' '.$LANG_FORMS['day'].' '.$LANG_FORMS['year'],
            2 => $LANG_FORMS['day'].' '.$LANG_FORMS['month'].' '.$LANG_FORMS['year'],
        );
        return $_formats;
    }


    /**
    *   Provide a dropdown selection of date formats
    *
    *   @param  integer $cur    Option to be selected by default
    *   @return string          HTML for selection, without select tags
    */
    public function DateFormatSelect($cur=0)
    {
        $retval = '';
        $_formats = self::DateFormats();
        foreach ($_formats as $key => $string) {
            $sel = $cur == $key ? 'selected="selected"' : '';
            $retval .= "<option value=\"$key\" $sel>$string</option>\n";
        }
        return $retval;
    }


    /**
    *   Validate the submitted field value(s)
    *
    *   @param  array   $vals  All form values
    *   @return string      Empty string for success, or error message
    */
    public function Validate(&$vals)
    {
        global $LANG_FORMS;

        $msg = '';
        if (!$this->enabled) return $msg;   // not enabled
        if (($this->access & QUIZ_FIELD_REQUIRED) != QUIZ_FIELD_REQUIRED)
            return $msg;        // not required

        switch ($this->type) {
        case 'date':
            if (empty($vals[$this->name . '_month']) ||
                empty($vals[$this->name . '_day']) ||
                empty($vals[$this->name . '_year'])) {
                $msg = $this->prompt . ' ' . $LANG_FORMS['is_required'];
            }
            break;
        case 'time':
            if (empty($vals[$this->name . '_hour']) ||
                empty($vals[$this->name . '_minute'])) {
                $msg = $this->prompt . ' ' . $LANG_FORMS['is_required'];
            }
            break;
        case 'radio':
            if (empty($vals[$this->name])) {
                $msg = $this->prompt . ' ' . $LANG_FORMS['is_required'];
            }
            break;
        default:
            if (empty($vals[$this->name])) {
                $msg = $this->prompt . ' ' . $LANG_FORMS['is_required'];
            }
            break;
        }
        return $msg;
    }


    /**
    *   Copy this field to another form.
    *
    *   @see    Form::Duplicate()
    */
    public function Duplicate()
    {
        global $_TABLES;

        if (is_array($this->options)) {
            $options = serialize($this->options);
        } else {
            $options = $this->options;
        }

        $sql .= "INSERT INTO {$_TABLES['quizzer_questions']} SET
                quiz_id = '" . DB_escapeString($this->quiz_id) . "',
                name = '" . DB_escapeString($this->name) . "',
                type = '" . DB_escapeString($this->type) . "',
                enabled = {$this->enabled},
                access = {$this->access},
                prompt = '" . DB_escapeString($this->prompt) . "',
                options = '" . DB_escapeString($options) . "',
                help_msg = '" . DB_escapeString($this->help_msg) . "',
                fill_gid = {$this->fill_gid},
                results_gid = {$this->results_gid},
                orderby = '" . (int)$this->orderby . "'";
        DB_query($sql, 1);
        $msg = DB_error() ? 5 : '';
        return $msg;
    }


    /**
    *   Get the default value for a field.
    *   Normally this will be the configured default retuned verbatim.
    *   It could also be a value from the $_USER array (more maybe to follow).
    *
    *   @uses   AutoGen()
    *   @param  string  $def    Defined default value
    *   @return string          Actual text to use as the field value.
    */
    public function GetDefault($def = '')
    {
        global $_USER;

        if (empty($def) &&
                isset($this->options['autogen']) &&
                $this->options['autogen'] == QUIZ_AUTOGEN_FILL) {
            return self::AutoGen($this->name, 'fill');
        }

        $value = $def;      // by default just return the given value
        if (isset($def[0]) && $def[0] == '$') {
            // Look for something like "$_USER:fullname"
            $A = explode(':', $def);
            $var = $A[0];
            $valname = isset($A[1]) ? $A[1] : false;
            switch (strtoupper($var)) {
            case '$_USER':
                if ($valname && isset($_USER[$valname]))
                    $value = $_USER[$valname];
                else
                    $value = '';    // Empty if not available
                break;
            case '$NOW':
                if ($this->type == 'time') {
                    $value = date('H:i:s');
                } else {
                    $value = date('Y-m-d H:i:s');
                }
                break;
            }
        }
        return $value;
    }


    /**
    *   Toggle a boolean field in the database
    *
    *   @param  $id     Question def ID
    *   @param  $fld    DB variable to change
    *   @param  $oldval Original value
    *   @return integer New value
    */
    public static function toggle($id, $fld, $oldval)
    {
        global $_TABLES;

        $id = DB_escapeString($id);
        $fld = DB_escapeString($fld);
        $oldval = $oldval == 0 ? 0 : 1;
        $newval = $oldval == 0 ? 1 : 0;
        $sql = "UPDATE {$_TABLES['quizzer_questions']}
                SET $fld = $newval
                WHERE q_id = '$id'";
        $res = DB_query($sql, 1);
        if (DB_error($res)) {
            COM_errorLog(__CLASS__ . '\\' . __FUNCTION__ . ':: ' . $sql);
            return $oldval;
        } else {
            return $newval;
        }
    }


    /**
    *   Get the HTML element ID based on the form and field ID.
    *   This is for ajax fields that store values in session variables
    *   instead of result sets.
    *   Also uses the field value if available and needed, such as for
    *   multi-checkboxes.
    *
    *   @param  string  $val    Optional field value
    *   @return string          ID string for the field element
    */
    public function _elemID($val = '')
    {
        $name  = str_replace(' ', '', $this->name);
        $id = 'quizzer_' . $this->quiz_id . '_' . $name;
        if (!empty($val)) {
            $id .= '_' . str_replace(' ', '', $val);
        }
        return $id;
    }


    /**
    *   Default function to get the field value from the form
    *   Just returns the form value
    *   @param  array   $A      Array of form values, e.g. $_POST
    *   @return mixed           Question value
    */
    public function valueFromForm($A)
    {
        return isset($A[$this->name]) ? $A[$this->name] : '';
    }


    /**
    *   Get the value from the database.
    *   Typically this is just copying the "value" field, but
    *   some field types may need to unserialize values.
    *
    *   @param  array   $A      Array of all DB fields
    *   @return mixed           Value field used by the object
    */
    public function valueFromDB($A)
    {
        return $A['value'];
    }


    /**
    *   Default function to get the display value for a field
    *   Just returns the raw value
    *
    *   @param  array   $fields     Array of all field objects (for calc-type)
    *   @return string      Display value
    */
    public function displayValue($fields)
    {
        global $_GROUPS;

        if (!$this->canViewResults()) return NULL;
        return htmlspecialchars($this->value);
    }


    /**
    *   Default function to get the field prompt.
    *   Gets the user-defined prompt, if any, or falls back to the field name.
    *
    *   @return string  Question prompt
    */
    public function displayPrompt()
    {
        return $this->prompt == '' ? $this->name : $this->prompt;
    }


    public function setValue($value)
    {
        return trim($value);
    }


    /**
    *   Get the value to be rendered in the form
    *
    *   @param  integer $res_id     Result set ID
    *   @param  string  $mode       View mode, e.g. "preview"
    *   @return mixed               Question value used to populate form
    */
    protected function renderValue($res_id, $mode, $valname = '')
    {
        $value = '';

        if (isset($_POST[$this->name])) {
            // First, check for a POSTed value. The form is being redisplayed.
            $value = $_POST[$this->name];
        } elseif ($this->getSubType() == 'ajax' && SESS_isSet($this->_elemID($valname))) {
            // Second, if this is an AJAX form check the session variable.
            $value = SESS_getVar($this->_elemID());
        } elseif ($res_id == 0 || $mode == 'preview') {
            // Finally, use the default value if defined.
            if (isset($this->options['default'])) {
                $value = $this->GetDefault($this->options['default']);
            }
        } else {
            $value = $this->value;
        }
        return $value;
    }


    public static function getQuestions($max = 0)
    {
        global $_TABLES;
        $sql = "SELECT * FROM {$_TABLES['quizzer_questions']} ORDER BY RAND()";
        $max = (int)$max;
        if ($max > 0) $sql .= " LIMIT $max";
        $res = DB_query($sql);
        $questions = array();
        $i = 1;
        while ($A = DB_fetchArray($res, false)) {
            $questions[$i] = $A;
            $i++;
        }
        return $questions;
    }

}

?>
