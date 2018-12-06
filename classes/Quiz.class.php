<?php
/**
 * Class to represent a quiz.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2010-2018 Lee Garner <lee@leegarner.com>
 * @package     quizzer
 * @version     v0.4.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Quizzer;


/**
 * Class for a single quiz.
 */
class Quiz
{
    /** Base tag for caching.
     * @const string */
    const TAG = 'quiz';

    /** Local properties.
    * @var array */
    var $properties = array();

    /** Quiz fields, an array of objects.
    * @var array */
    var $fields = array();

    /** Result object for a user submission.
    * @var object */
    var $Result;

    /** Database ID of a result record.
    * @var integer */
    var $res_id;

    /** Flag to indicate that submission is allowed.
     * Turns off the submit button when previewing.
     * @var boolean */
    var $allow_submit;

    /** Flag to indicate that this is a new quic.
     * @var boolean */
    var $isNew;

    /** User ID.
     * @var integer */
    var $uid;


    /**
     * Constructor.
     * Create a quizzer object for the specified user ID, or the current
     * user if none specified.
     * If a key is requested, then just build the quizzer for that key (requires a $uid).
     *
     * @param   integer $id     Quiz ID, empty to create a new record
     */
    function __construct($id = '')
    {
        global $_USER, $_CONF_QUIZ, $_TABLES;

        $this->uid = (int)$_USER['uid'];
        if ($this->uid == 0) $this->uid = 1;    // Anonymous
        $def_group = (int)DB_getItem($_TABLES['groups'], 'grp_id',
                "grp_name='quizzer Admin'");
        if ($def_group < 1) $def_group = 1;     // default to Root
        $this->Result = NULL;

        if (is_array($id)) {
            $this->SetVars($id, true);
            $this->isNew = false;
        } elseif (!empty($id)) {
            $id = COM_sanitizeID($id);
            $this->id = $id;
            $this->isNew = false;
            if (!$this->Read($id)) {
                $this->id = COM_makeSid();
                $this->isNew = true;
            }
        } else {
            $this->isNew = true;
            $this->fill_gid = $_CONF_QUIZ['fill_gid'];
            $this->group_id = $def_group;
            $this->enabled = 1;
            $this->id = COM_makeSid();
            $this->introtext = '';
            $this->pass_msg = '';
            $this->fail_msg = '';
            $this->introfields = '';
            $this->name = '';
            $this->onetime = 0;
            $this->num_q = 0;
            $this->levels = 0;
        }
    }


    /**
     * Get an instance of a quiz object.
     *
     * @param   string  $quiz_id     Quiz ID
     * @return  object      Quiz object
     */
    public static function getInstance($quiz_id)
    {
        $key = self::TAG . '_' . $quiz_id;
        $Obj = Cache::get($key);
        if ($Obj === NULL) {
            $Obj = new self($quiz_id);
            Cache::set($quiz_id, $Obj);
        }
        return $Obj;
    }


    /**
     * Set a local property.
     *
     * @param   string  $name   Name of property to set
     * @param   mixed   $value  Value to set
     */
    public function __set($name, $value)
    {
        switch ($name) {
        case 'id':
        case 'old_id':
            $this->properties[$name] = COM_sanitizeID($value);
            break;

        case 'fill_gid':
        case 'onetime':
        case 'num_q':
            $this->properties[$name] = (int)$value;
            break;

        case 'enabled':
            $this->properties[$name] = $value == 0 ? 0 : 1;
            break;

        case 'introtext':
        case 'introfields':
        case 'pass_msg':
        case 'fail_msg':
        case 'name':
        case 'levels':
            $this->properties[$name] = trim($value);
            break;

        case 'questions':
            $this->properties[$name] = $value;
            break;
        }
    }


    /**
     * Return a property, if it exists.
     *
     * @param   string  $name   Name of property to get
     * @return  mixed   Value of property identified as $name
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
     * Read all auiz fields.
     *
     * @param  string  $id      Optional quiz ID.
     */
    public function Read($id = '')
    {
        global $_TABLES;

        $this->id = $id;

        // Clear out any existing items, in case we're reusing this instance.
        $this->fields = array();

        $sql = "SELECT * FROM {$_TABLES['quizzer_quizzes']}
                WHERE id = '" . $this->id . "'";
        //echo $sql;die;
        $res1 = DB_query($sql, 1);
        if (!$res1 || DB_numRows($res1) < 1) {
            return false;
        }

        $A = DB_fetchArray($res1, false);
        $this->SetVars($A, true);
        return true;
    }


    /**
     * Read a results set for this quiz.
     * If no results set ID is given, then find the first set for the
     * current user ID.
     *
     * @depreated
     * @param   integer $res_id     Results set to read
     */
    public function ReadData($res_id = 0)
    {
        if ($res_id == 0) {
            $res_id = Result::FindResult($this->id, $this->uid);
        } else {
            $res_id = (int)$res_id;
        }

        if ($res_id > 0) {
            $this->Result = new Result($res_id);
            $this->Result->GetValues($this->questions);
        }
    }


    /**
     * Set all values for this quiz into local variables.
     *
     * @param   array   $A          Array of values to use.
     * @param   boolean $fromdb     Indicate if $A is from the DB or a quiz.
     */
    function SetVars($A, $fromdb=false)
    {
        if (!is_array($A))
            return false;

        $this->id = $A['id'];
        $this->name = $A['name'];
        $this->introtext = $A['introtext'];
        $this->pass_msg = $A['pass_msg'];
        $this->fail_msg = $A['fail_msg'];
        $this->introfields = $A['introfields'];
        $this->fill_gid = $A['fill_gid'];
        $this->onetime = $A['onetime'];
        $this->num_q = $A['num_q'];
        $this->levels = $A['levels'];

        if ($fromdb) {
            // Coming from the database
            $this->enabled = $A['enabled'];
            $this->old_id = $A['id'];
        } else {
            // This is coming from the quiz edit form
            $this->enabled = isset($A['enabled']) ? 1 : 0;
            $this->old_id = $A['old_id'];
        }
    }


    /**
     * Create the edit quiz for all the quizzer variables.
     * Checks the type of edit being done to select the right template.
     *
     * @param   string  $type   Type of editing- 'edit' or 'registration'
     * @return  string          HTML for edit quiz
     */
    public function editQuiz($type = 'edit')
    {
        global $_CONF_QUIZ, $_USER, $LANG_QUIZ;

        if (isset($_POST['referrer'])) {
            $referrer = $_POST['referrer'];
        } elseif (isset($_SERVER['HTTP_REFERER'])) {
            $referrer = $_SERVER['HTTP_REFERER'];
        } else {
            $referrer = '';
        }

        $T = QUIZ_getTemplate('editquiz', 'editquiz', 'admin');
        $T->set_var(array(
            'id'    => $this->id,
            'old_id' => $this->old_id,
            'name'  => $this->name,
            'introtext' => $this->introtext,
            'pass_msg' => $this->pass_msg,
            'fail_msg' => $this->fail_msg,
            'introfields' => $this->introfields,
            'ena_chk' => $this->enabled == 1 ? 'checked="checked"' : '',
            'email' => $this->email,
            'user_group_dropdown' =>
                    $this->_groupDropdown(),
            'doc_url'   => QUIZ_getDocURL('quiz_def.html'),
            'referrer'      => $referrer,
            'lang_confirm_delete' => $LANG_QUIZ['confirm_quiz_delete'],
            'one_chk_' . $this->onetime => 'selected="selected"',
            'num_q'     => (int)$this->num_q,
            'levels'    => $this->levels,
            'iconset'   => $_CONF_QUIZ['_iconset'],
        ) );
        if (!$this->isNew) {
            $T->set_var('candelete', 'true');
        }
        $T->parse('output', 'editquiz');
        return $T->finish($T->get_var('output'));
    }


    /**
     * Save all quizzer items to the database.
     * Calls each field's Save() method iff there is a corresponding
     * value set in the $vals array.
     *
     * @param   array   $vals   Values to save, from $_POST, normally
     * @return  string      HTML error list, or empty for success
     */
    public function SaveData($vals)
    {
        global $_TABLES;

        // Check that $vals is an array; should be from $_POST;
        if (!is_array($vals)) return false;

        // Check that the user has access to fill out this quiz
        if (!$this->hasAccess(QUIZ_ACCESS_FILL)) return false;

        if (isset($vals['res_id']) && !empty($vals['res_id'])) {
            $res_id = (int)$vals['res_id'];
            $newSubmission = false;
        } else {
            $newSubmission = true;
            $res_id = 0;
        }

        // Check whether the submission can be updated and, if so, whether
        // the res_id from the quiz is correct
        if ($this->onetime == QUIZ_LIMIT_ONCE) {
            if ($res_id == 0) {
                // even if no result ID given, see if there is one
                $res_id = Result::FindResult($this->id, $this->uid);
            }
            if ($res_id > 0) return false;       // can't update the submission
        /*} elseif ($this->onetime == QUIZ_LIMIT_EDIT) {
            // check that the supplied result ID is the same as the saved one.
            $real_res_id = Result::FindResult($this->id, $this->uid);
            if ($real_res_id != $res_id) {
                return false;
            }*/
        }   // else, multiple submissions are allowed

        // Validate the quiz fields
        $msg = '';
        $invalid_flds = '';
        foreach ($this->questions as $Q) {
            $msg = $Q->Validate($vals);
            if (!empty($msg)) {
                $invalid_flds .= "<li>$msg</li>\n";
            }
        }

        // All fields are valid, carry on with the onsubmit actions
        $onsubmit = $this->onsubmit;
        if ($onsubmit & QUIZ_ACTION_STORE) {
            // Save data to the database
            $this->Result = new Result($res_id);
            $this->Result->setInstance($this->instance_id);
            $this->Result->setModerate($this->moderate);
            $this->res_id = $this->Result->SaveData($this->id, $this->questions,
                    $vals, $this->uid);
        } else {
            $this->res_id = false;
        }
        return '';
    }


    /**
     * Save a quiz definition.
     *
     * @param   array   $A      Array of values (e.g. $_POST)
     * @return  string      Error message, empty on success
     */
    function SaveDef($A = '')
    {
        global $_TABLES, $LANG_QUIZ;

        if (is_array($A)) {
            $this->SetVars($A, false);
        }

        $frm_name = $this->name;
        if (empty($frm_name)) {
            return $LANG_QUIZ['err_name_required'];
        }

        $changingID = false;
        if ($this->isNew || (!$this->isNew && $this->id != $this->old_id)) {
            if (!$this->isNew) $changingID = true;
            // Saving a new record or changing the ID of an existing one.
            // Make sure the new frm ID doesn't already exist.
            $x = DB_count($_TABLES['quizzer_quizzes'], 'id', $this->id);
            if ($x > 0) {
                $this->id = COM_makeSid();
            }
        }

        if (!$this->isNew && $this->old_id != '') {
            $sql1 = "UPDATE {$_TABLES['quizzer_quizzes']} ";
            $sql3 = " WHERE id = '{$this->old_id}'";
        } else {
            $sql1 = "INSERT INTO {$_TABLES['quizzer_quizzes']} ";
            $sql3 = '';
        }
        $sql2 = "SET id = '{$this->id}',
            name = '" . DB_escapeString($this->name) . "',
            introtext = '" . DB_escapeString($this->introtext) . "',
            introfields= '" . DB_escapeString($this->introfields) . "',
            pass_msg= '" . DB_escapeString($this->pass_msg) . "',
            fail_msg= '" . DB_escapeString($this->fail_msg) . "',
            enabled = '{$this->enabled}',
            fill_gid = '{$this->fill_gid}',
            onetime = '{$this->onetime}',
            num_q = {$this->num_q},
            levels = '" . DB_escapeString($this->levels) . "'";
        $sql = $sql1 . $sql2 . $sql3;
        //echo $sql;die;
        DB_query($sql, 1);

        if (!DB_error()) {
            // Now, if the ID was changed, update the field & results tables
            if ($changingID) {
                DB_query("UPDATE {$_TABLES['quizzer_results']}
                        SET quiz_id = '{$this->id}'
                        WHERE quiz_id = '{$this->old_id}'");
                DB_query("UPDATE {$_TABLES['quizzer_questions']}
                        SET quiz_id = '{$this->id}'
                        WHERE quiz_id = '{$this->old_id}'");
            }
            CTL_clearCache();       // so autotags pick up changes
            Cache::clear();         // Clear plugin cache
            $msg = '';              // no error message if successful
        } else {
            $msg = 5;
        }
        return $msg;
    }


    /**
     * Render a quiz question.
     * Set $mode to 'preview' to have the cancel button return to the admin
     * list.  Otherwise it might return and re-execute an action, like "copy".
     *
     * @param   string  $question   ID of question being rendered.
     * @return  string      HTML for the quiz
     */
    public function Render($question = 0)
    {
        global $LANG_QUIZ, $_CONF;

        $retval = '';
        $isAdmin = false;

        // Don't allow another submission if the user has already filled out
        // this quiz.
        if ($this->onetime) {
            $results = Result::findByUser($this->uid, $this->id);
            if (count($results) > 0) {
                COM_refresh($_CONF['site_url'] . '/index.php?plugin=quizzer&msg=7');
                exit;
            }
        }

        // Check that the current user has access to fill out this quiz.
        if (!$this->hasAccess(QUIZ_ACCESS_FILL)) {
            return $this->noaccess_msg;
        }

        if ($question == 0) {
            SESS_unset('quizzer_resultset');
            $questions = Question::getQuestions($this->id, $this->num_q);
            SESS_setVar('quizzer_questions', $questions);
            $this->num_q = count($questions);   // replace with actual number
        }

        // If starting the quiz, and there are intro fields to fill out, then
        // display those. Otherwise start with the first question below.
        if ($question == 0 &&
            ($this->introtext != '' || $this->introfields != '') ) {
                $T = new \Template(QUIZ_PI_PATH . '/templates');
                $T->set_file('intro', 'intro.thtml');
                $T->set_var(array(
                    'introtext'     => $this->introtext,
                    'quiz_name'     => $this->name,
                    'quiz_id'       => $this->id,
                ) );
                if ($this->introfields != '') {
                    $introfields = explode('|', $this->introfields);
                    $T->set_block('intro', 'introFields', 'iField');
                    foreach ($introfields as $fld) {
                        $T->set_var(array(
                            'if_prompt' => $fld,
                            'if_name'   => COM_sanitizeId($fld),
                        ) );
                        $T->parse('iField', 'introFields', true);
                    }
                }
                $T->parse('output', 'intro');
                $retval .= $T->finish($T->get_var('output'));
        } else {
            $questions = SESS_getVar('quizzer_questions');
            if ($questions == 0) {
                COM_setMsg($LANG_QUIZ['msg_no_questions']);
                echo COM_refresh(QUIZ_PI_URL);
            }
            $total_q = count($questions);
            if (isset($questions[$question])) {
                $Q = Question::getInstance($questions[$question]);
                $retval .= $Q->Render($question, $total_q);
            }
        }
        return $retval;
    }


    /**
     * Delete a quiz definition.
     * Deletes a quiz, removes the questions, and deletes user data.
     *
     * @uses    Result::Delete()
     * @param   integer $quiz_id     Optional quiz ID, current object if empty
     */
    public static function DeleteDef($quiz_id)
    {
        global $_TABLES;

        $quiz_id = COM_sanitizeID($quiz_id);
        // If still no valid ID, do nothing
        if ($quiz_id == '') return;

        DB_delete($_TABLES['quizzer_quizzes'], 'id', $quiz_id);
        //DB_delete($_TABLES['quizzer_frmXfld'], 'frm_id', $quiz_id);
        DB_delete($_TABLES['quizzer_questions'], 'quiz_id', $quiz_id);

        $sql = "SELECT id FROM {$_TABLES['quizzer_results']}
            WHERE quiz_id='$quiz_id'";
        $r = DB_query($sql, 1);
        if ($r) {
            while ($A = DB_fetchArray($r, false)) {
                Result::Delete($A['id']);
            }
        }
        Cache::clear();
    }


    /**
     * Determine if a specific user has a given access level to the quiz.
     *
     * @param   integer $level  Requested access level
     * @param   integer $uid    Optional user ID, current user if omitted.
     * @return  boolean     True if the user has access, false if not
     */
    public function hasAccess($level, $uid = 0)
    {
        global $_USER;

        if ($uid == 0) $uid = (int)$_USER['uid'];
        //if ($uid == $this->owner_id) return true;

        $retval = false;

        switch ($level) {
        case QUIZ_ACCESS_VIEW:
            if (SEC_inGroup($this->results_gid, $uid)) $retval = true;
            break;
        case QUIZ_ACCESS_FILL:
            if (SEC_inGroup($this->fill_gid, $uid)) $retval = true;
            break;
        case QUIZ_ACCESS_ADMIN:
            if (SEC_inGroup($this->group_id, $uid)) $retval = true;
            break;
        }
        return $retval;
    }


    /**
     * Duplicate this quiz.
     * Creates a copy of this quiz with all its fields.
     *
     * @uses    Question::Duplicate()
     * @return  string      Error message, empty if successful
     */
    public function Duplicate()
    {
        $this->name .= ' -Copy';
        $this->id = COM_makeSid();
        $this->isNew = true;
        $this->SaveDef();

        foreach ($this->questions as $Q) {
            $Q->frm_id = $this->id;
            $msg = $F->Duplicate();
            if (!empty($msg)) return $msg;
        }
        return '';
    }


    /**
     * Remove HTML and convert other characters.
     *
     * @param   string  $str    String to sanitize
     * @return  string          String with no quotes or tags
     */
    private static function _stripHtml($str)
    {
        return htmlentities(strip_tags($str));
    }


    /**
     * Toggle a boolean field in the database
     *
     * @param   string  $id     Field def ID
     * @param   string  $fld    DB variable to change
     * @param   integer $oldval Original value
     * @return  integer New value
     */
    public static function toggle($id, $fld, $oldval)
    {
        global $_TABLES;

        $id = DB_escapeString($id);
        $fld = DB_escapeString($fld);
        $oldval = $oldval == 0 ? 0 : 1;
        $newval = $oldval == 0 ? 1 : 0;
        $sql = "UPDATE {$_TABLES['quizzer_quizzes']}
                SET $fld = $newval
                WHERE id = '$id'";
        $res = DB_query($sql, 1);
        if (DB_error($res)) {
            COM_errorLog(__CLASS__ . '\\' . __FUNCTION__ . ':: ' . $sql);
            return $oldval;
        } else {
            return $newval;
        }
    }


    /**
     * Get the first active quiz, for when a quiz ID is not provided
     *
     * @return  object  Quiz object
     */
    public static function getFirst()
    {
        global $_TABLES;

        $sql = "SELECT * FROM {$_TABLES['quizzer_quizzes']}
                WHERE enabled = 1
                ORDER BY id ASC
                LIMIT 1";
        $res = DB_query($sql);
        if (DB_numRows($res) == 1) {
            $A = DB_fetchArray($res, false);
            $Q = new self($A);
        } else {
            $Q = new self();
        }
        return $Q;
    }


    /**
     * Get the class to use for the scoring progress bars
     *
     * @param   float   $pct    Percentage of correct answers
     * @return  string          Class to use
     */
    public function getGrade($pct)
    {
        static $scores = NULL;
        if ($scores === NULL) $scores = explode('|', $this->levels);
        $levels = array('success', 'warning', 'danger');
        $max_i = min(count($scores), 3);
        $grade = 'danger';
        for ($i = 0; $i < $max_i; $i++) {
            if ($pct >= (float)$scores[$i]) {
                $grade = $levels[$i];
                break;
            }
        }
        return $grade;
    }


    /**
     * Display a summary of results by question.
     * Shows each question and the average score for that question.
     *
     * @return  string  HTML for display
     */
    public function resultByQuestion()
    {
        global $_TABLES;

        $T = new \Template(QUIZ_PI_PATH . '/templates/admin');
        $T->set_file('results', 'resultsbyq.thtml');
        $T->set_var('quiz_name', $this->name);
        $sql = "SELECT * FROM {$_TABLES['quizzer_questions']}
                WHERE quiz_id = '{$this->id}'";
        $res = DB_query($sql);
        $questions = array();
        while ($A = DB_fetchArray($res, false)) {
            $questions[] = Question::getInstance($A);
        }
        $T->set_block('results', 'DataRows', 'dRow');
        foreach ($questions as $Q) {
            $total = 0;
            $correct = 0;
            $vals = Value::getByQuestion($Q->q_id);
            foreach ($vals as $Val) {
                $total++;
                if ($Q->Verify($Val->value)) {
                    $correct++;
                }
            }
            if ($total > 0) {
                $pct = (int)(($correct / $total) * 100);
            } else {
                $pct = 0;
            }
            $prog_status = $this->getGrade($pct);
            $T->set_var(array(
                'question' => $Q->question,
                'pct' => $pct,
                'correct' => $correct,
                'total' => $total,
                'prog_status' => $total > 0 ? $prog_status : false,
            ) );
            $T->parse('dRow', 'DataRows', true);
        }
        $T->parse('output', 'results');
        return $T->finish($T->get_var('output'));
    }


    /**
     * Display a summary of results by submitter.
     * Shows intro field data and overall score.
     *
     * @return  string  HTML for display
     */
    public function resultSummary()
    {
        global $LANG_QUIZ;

        $T = new \Template(QUIZ_PI_PATH . '/templates/admin');
        $T->set_file('results', 'results.thtml');
        $T->set_var('quiz_name', $this->name);
        $intro = explode('|', $this->introfields);
        $keys = array();
        $T->set_block('results', 'hdrIntroFields', 'hdrIntro');
        foreach ($intro as $key) {
            $T->set_var('introfield_value', $key);
            $T->parse('hdrIntro', 'hdrIntroFields', true);
            $keys[] = COM_sanitizeId($key);
        }
        $results = Result::findByQuiz($this->id);
        $T->set_block('results', 'DataRows', 'dRow');
        foreach ($results as $R) {
            $introfields = @unserialize($R->introfields);
            $T->set_block('results', 'dataIntroFields', 'dataIntro');
            $T->clear_var('dataIntro');
            foreach ($keys as $key) {
                $T->set_var('introfield_value', QUIZ_getVar($introfields, $key));
                $T->parse('dataIntro', 'dataIntroFields', true);
            }
            $correct = 0;
            $total_a = 0;
            foreach ($R->Values as $V) {
                $total_a++;
                $Q = Question::getInstance($V->q_id);
                if ($Q->Verify($V->value)) {
                    $correct++;
                }
            }
            $total_q = $R->asked;
            if ($total_q > 0) {
                $pct = (int)(($correct / $total_q) * 100);
            } else {
                $pct = 0;
            }
            if ($total_a < $total_q) {
                $msg = ' (' . sprintf($LANG_QUIZ['num_answered'], $total_a) . ')';
            } else {
                $msg = '';
            }
            $prog_status = $this->getGrade($pct);
            $T->set_var(array(
                'pct' => $pct,
                'correct' => $correct,
                'total' => $total_q,
                'not_all_answered' => $msg,
                'prog_status' => $prog_status,
                'res_id' => $R->res_id,
            ) );
            $T->parse('dRow', 'DataRows', true);
        }
        $T->parse('output', 'results');
        return $T->finish($T->get_var('output'));
    }


    /**
     * Export user responses as a CSV.
     *
     * @return  string  CSV file contents
     */
    public function csvBySubmitter()
    {
        global $LANG_QUIZ;

        $questions = Question::getQuestions($this->id, 0, false);
        $intro = explode('|', $this->introfields);
        $total_q = count($questions);
        $headers = array();
        foreach ($intro as $key) {
            $headers[COM_sanitizeId($key, false)] = $key;
        }
        for ($i = 1; $i <= $total_q; $i++) {
            $headers['q_' . $i] = $LANG_QUIZ['question'] . ' ' . $i;
        }
        $retval = '"' . implode('","', $headers) . '"' . "\n";
        $results = Result::findByQuiz($this->id);
        foreach ($results as $R) {
            $result_arr = $headers;
            foreach ($result_arr as $key=>$val) $result_arr[$key] = '';

            $introfields = @unserialize($R->introfields);
            foreach ($introfields as $key=>$val) {
                $result_arr[$key] = str_replace('"', "'", $val);
            }
            $correct = 0;
            foreach ($R->Values as $V) {
                $Q = Question::getInstance($V->q_id);
                if ($Q->Verify($V->value)) {
                    $correct = 1;
                } else {
                    $correct = 0;
                }
                $result_arr['q_' . $Q->q_id] = $correct;
            }
            $retval .= '"' . implode('","', $result_arr) . '"' . "\n";
        }
        return $retval;
    }



    /**
     * Export questions, total responses and correct responses as a CSV.
     *
     * @return  string  CSV file contents
     */
    public function csvByQuestion()
    {
        global $_TABLES, $LANG_QUIZ;

        $questions = Question::getQuestions($this->id, 0, false);
        $sql = "SELECT * FROM {$_TABLES['quizzer_questions']}
                WHERE quiz_id = '{$this->id}'";
        $res = DB_query($sql);
        $questions = array();
        while ($A = DB_fetchArray($res, false)) {
            $questions[] = Question::getInstance($A);
        }
        $retval = '"' . $LANG_QUIZ['question'] . '","' .
                    $LANG_QUIZ['answers'] . '","' .
                    $LANG_QUIZ['correct'] . '"'. "\n";
        foreach ($questions as $Q) {
            $total = 0;
            $correct = 0;
            $vals = Value::getByQuestion($Q->q_id);
            foreach ($vals as $Val) {
                $total++;
                if ($Q->Verify($Val->value)) {
                    $correct++;
                }
            }
            // Make sure there are no embedded quotes
            $question = str_replace('"', "'", $Q->question);
            $retval .= '"' . $Q->question . '",' . $total . ',' . $correct . "\n";
        }
        return $retval;
    }


    /**
     * Get a dropdown of user groups to set the fill_gid field.
     * Sets the current fill_gid as selected if not empty.
     *
     * @return  string  Options for group dropdown
     */
    private function _groupDropdown()
    {
        $retval = '';
        $usergroups = SEC_getUserGroups();
        foreach ($usergroups as $ug_name => $ug_id) {
            $retval .= '<option value="' . $ug_id . '"';
            if ($this->fill_gid == $ug_id) {
                $retval .= ' selected="selected"';
            }
            $retval .= '>' . $ug_name . '</option>' . LB;
        }
        return $retval;
    }


    /**
     * Reset the quiz for the next taker.
     * Currently a noop.
     */
    public function Reset()
    {}

}

?>
