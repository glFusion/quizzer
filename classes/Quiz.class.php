<?php
/**
*   Class to handle all quizzer items.
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2010-2018 Lee Garner <lee@leegarner.com>
*   @package    quizzer
*   @version    0.4.0
*   @license    http://opensource.org/licenses/gpl-2.0.php
*               GNU Public License v2 or later
*   @filesource
*/
namespace Quizzer;


/**
*   Class for a user's custom quizzer.
*/
class Quiz
{
    const TAG = 'quiz';     // cache tag

    /** Local properties
    *   @var array */
    var $properties = array();

    /** Quiz fields, an array of objects
    *   @var array */
    var $fields = array();

    /** Result object for a user submission
    *   @var object */
    var $Result;

    /** Database ID of a result record
    *   @var integer */
    var $res_id;

    var $allow_submit;  // Turn off the submit button when previewing
    var $isNew;
    var $uid;
    var $access;


    /**
    *   Constructor.  Create a quizzer object for the specified user ID,
    *   or the current user if none specified.  If a key is requested,
    *   then just build the quizzer for that key (requires a $uid).
    *
    *   @param  integer $uid    Optional user ID
    *   @param  string  $key    Optional key to retrieve
    */
    function __construct($id = '', $access=QUIZ_ACCESS_ADMIN)
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
        } elseif (!empty($id)) {
            $id = COM_sanitizeID($id);
            $this->id = $id;
            $this->isNew = false;
            if (!$this->Read($id, $access)) {
                $this->id = COM_makeSid();
                $this->isNew = true;
            }
        } else {
            $this->isNew = true;
            $this->fill_gid = 0;
//            $this->results_gid = 0;
            $this->group_id = $def_group;
            $this->enabled = 1;
            $this->id = COM_makeSid();
            $this->introtext = '';
            $this->introfields = '';
            $this->name = '';
//            $this->filled_by = 0;
            $this->onetime = 0;
            $this->redirect = '';
            $this->num_q = 0;
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
    *   Set a local property
    *
    *   @param  string  $name   Name of property to set
    *   @param  mixed   $value  Value to set
    */
    public function __set($name, $value)
    {
        global $LANG_QUIZ;

        switch ($name) {
        case 'id':
        case 'old_id':
            $this->properties[$name] = COM_sanitizeID($value);
            break;

/*        case 'owner_id':
        case 'group_id':
        case 'onsubmit':*/
        case 'fill_gid':
//        case 'results_gid':
//        case 'filled_by':
        case 'onetime':
        case 'num_q':
            $this->properties[$name] = (int)$value;
            break;

        case 'enabled':
            $this->properties[$name] = $value == 0 ? 0 : 1;
            break;

        case 'introtext':
        case 'introfields':
        case 'name':
        case 'redirect':
            $this->properties[$name] = trim($value);
            break;

        case 'questions':
            $this->properties[$name] = $value;
            break;
        }
    }


    /**
    *   Return a property, if it exists.
    *
    *   @param  string  $name   Name of property to get
    *   @return mixed   Value of property identified as $name
    */
    public function __get($name)
    {
        global $_CONF;

        // Special handling, return the site_url by default
        if ($name == 'redirect') {
            if (isset($this->properties['redirect']) &&
                    !empty($this->properties['redirect']))
                return $this->properties['redirect'];
            else
                return $_CONF['site_url'];
        }

        if (array_key_exists($name, $this->properties)) {
            return $this->properties[$name];
        } else {
            return '';
        }
    }


    /**
    *   Read all quizzer variables into the $items array.
    *   Set the $uid paramater to read another user's quizzer into
    *   the current object instance.
    *
    *   @param  string  $key    Optional specific key to retrieve
    *   @param  integer $uid    Optional user ID
    */
    public function Read($id = '', $access=QUIZ_ACCESS_ADMIN)
    {
        global $_TABLES;

        $this->id = $id;

        // Clear out any existing items, in case we're reusing this instance.
        $this->fields = array();

        $sql = "SELECT qd.* FROM {$_TABLES['quizzer_quizzes']} qd
            WHERE qd.id = '" . $this->id . "'";
        //echo $sql;die;
        $res1 = DB_query($sql, 1);
        if (!$res1 || DB_numRows($res1) < 1) {
            $this->access = false;
            return false;
        }

        $A = DB_fetchArray($res1, false);
        $this->SetVars($A, true);
        $this->access = $this->hasAccess($access);

        // Now get field inquization
        $sql = "SELECT *
                FROM {$_TABLES['quizzer_questions']}
                WHERE id = '{$this->id}'
                ORDER BY orderby ASC";
        //echo $sql;die;
        $res2 = DB_query($sql, 1);
        while ($A = DB_fetchArray($res2, false)) {
            $this->questions[$A['name']] = Question::getInstance($A, $this);
        }
        return true;
    }


    /**
    *   Read a results set for this quiz.
    *   If no results set ID is given, then find the first set for the
    *   current user ID.
    *
    *   @param  integer $res_id     Results set to read
    */
    public function ReadData($res_id = 0, $token = '')
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
    *   Set all values for this quiz into local variables.
    *
    *   @param  array   $A          Array of values to use.
    *   @param  boolean $fromdb     Indicate if $A is from the DB or a quiz.
    */
    function SetVars($A, $fromdb=false)
    {
        if (!is_array($A))
            return false;

        $this->id = $A['id'];
        $this->name = $A['name'];
        $this->introtext = $A['introtext'];
        $this->introfields = $A['introfields'];
        //$this->email = $A['email'];
//        $this->owner_id = $A['owner_id'];
//        $this->group_id = $A['group_id'];
        $this->fill_gid = $A['fill_gid'];
//        $this->results_gid = $A['results_gid'];
        $this->onetime = $A['onetime'];
        //$this->redirect = $A['redirect'];
        $this->num_q = $A['num_q'];

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
    *   Create the edit quiz for all the quizzer variables.
    *   Checks the type of edit being done to select the right template.
    *
    *   @param  string  $type   Type of editing- 'edit' or 'registration'
    *   @return string          HTML for edit quiz
    */
    public function editQuiz($type = 'edit')
    {
        global $_CONF, $_CONF_QUIZ, $_USER, $_TABLES, $LANG_QUIZ;

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
            'introfields' => $this->introfields,
//            'submit_msg' => $this->submit_msg,
//            'noaccess_msg' => $this->noaccess_msg,
//            'noedit_msg' => $this->noedit_msg,
//            'max_submit' => $this->max_submit,
//            'max_submit_msg' => $this->max_submit_msg,
            'redirect' => $this->redirect,
            'ena_chk' => $this->enabled == 1 ? 'checked="checked"' : '',
            //'mod_chk' => $this->moderate == 1 ? 'checked="checked"' : '',
            //'owner_dropdown' => QUIZ_UserDropdown($this->owner_id),
            'email' => $this->email,
/*            'admin_group_dropdown' =>
QUIZ_GroupDropdown($this->group_id, 3),
 */
            'user_group_dropdown' =>
                    QUIZ_GroupDropdown($this->fill_gid, 3),
/*            'results_group_dropdown' =>
                    QUIZ_GroupDropdown($this->results_gid, 3),
            'emailowner_chk' => $this->onsubmit & QUIZ_ACTION_MAILOWNER ?
                        'checked="checked"' : '',
            'emailgroup_chk' => $this->onsubmit & QUIZ_ACTION_MAILGROUP ?
                        'checked="checked"' : '',
            'emailadmin_chk' => $this->onsubmit & QUIZ_ACTION_MAILADMIN ?
                        'checked="checked"' : '',
            'store_chk' => $this->onsubmit & QUIZ_ACTION_STORE ?
                        'checked="checked"' : '',
            'preview_chk' => $this->onsubmit & QUIZ_ACTION_DISPLAY ?
            'checked="checked"' : '',*/
            'doc_url'   => QUIZ_getDocURL('quiz_def.html'),
            'referrer'      => $referrer,
            'lang_confirm_delete' => $LANG_QUIZ['confirm_quiz_delete'],
//            'captcha_chk' => $this->captcha == 1 ? 'checked="checked"' : '',
//            'inblock_chk' => $this->inblock == 1 ? 'checked="checked"' : '',
            'one_chk_' . $this->onetime => 'selected="selected"',
            'num_q'     => (int)$this->num_q,
            'iconset'   => $_CONF_QUIZ['_iconset'],
        ) );
        if (!$this->isNew) {
            $T->set_var('candelete', 'true');
        }
        $T->parse('output', 'editquiz');
        return $T->finish($T->get_var('output'));
    }


    /**
    *   Save all quizzer items to the database.
    *   Calls each field's Save() method iff there is a corresponding
    *   value set in the $vals array.
    *
    *   @param  array   $vals   Values to save, from $_POST, normally
    *   @return string      HTML error list, or empty for success
    */
    public function SaveData($vals)
    {
        global $LANG_QUIZ, $_CONF, $_TABLES;

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
    *   Save a quiz definition.
    *
    *   @param  array   $A      Array of values (e.g. $_POST)
    *   @return string      Error message, empty on success
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
            enabled = '{$this->enabled}',
            fill_gid = '{$this->fill_gid}',
            onetime = '{$this->onetime}'";
/*            owner_id = '{$this->owner_id}',
    group_id = '{$this->group_id}',
            submit_msg = '" . DB_escapeString($this->submit_msg) . "',
            results_gid = '{$this->results_gid}',
            redirect = '" . DB_escapeString($this->redirect) . "',
 */
        $sql = $sql1 . $sql2 . $sql3;
        DB_query($sql, 1);

        if (!DB_error()) {
            // Now, if the ID was changed, update the field & results tables
            if ($changingID) {
                DB_query("UPDATE {$_TABLES['quizzer_results']}
                        SET frm_id = '{$this->id}'
                        WHERE id = '{$this->old_id}'", 1);
                DB_query("UPDATE {$_TABLES['quizzer_flddef']}
                        SET frm_id = '{$this->id}'
                        WHERE id = '{$this->old_id}'", 1);
                Cache::clear('quiz_' . $this->old_id);  // Clear old quiz cache
            }
            CTL_clearCache();       // so autotags pick up changes
            Cache::clear('quiz_' . $this->id);      // Clear plugin cache
            $msg = '';              // no error message if successful
        } else {
            $msg = 5;
        }

        // Finally, if the option is selected, update each field's permission
        // with the quiz's.
        if (isset($A['reset_fld_perm'])) {
            DB_query("UPDATE {$_TABLES['quizzer_flddef']} SET
                    fill_gid = '{$this->fill_gid}',
                    results_gid = '{$this->results_gid}'
                WHERE id = '{$this->id}'", 1);
        }
        return $msg;
    }


    /**
    *   Render the quiz.
    *   Set $mode to 'preview' to have the cancel button return to the admin
    *   list.  Otherwise it might return and re-execute an action, like "copy".
    *
    *   @param  string  $mode   'preview' if this is an admin preview, or blank
    *   @return string  HTML for the quiz
    */
    public function Render($question = 0)
    {
        global $_CONF, $_TABLES, $LANG_QUIZ, $_GROUPS, $_CONF_QUIZ;

        $retval = '';
        $isAdmin = false;

        // Check that the current user has access to fill out this quiz.
        if (!$this->hasAccess(QUIZ_ACCESS_FILL)) {
            return $this->noaccess_msg;
        }

        if ($question == 0) {
            SESS_unset('quizzer_questions');
            SESS_unset('quizzer_resultset');
            SESS_setVar('quizzer_questions', Question::getQuestions($this->num_q));
        }

        if ($question == 0 &&
            ($this->introtext != '' || $this->introfields != '') ) {
                $T = new \Template(QUIZ_PI_PATH . '/templates');
                $T->set_file('intro', 'intro.thtml');
                $T->set_var('introtext', $this->introtext);
                $introfields = explode('|', $this->introfields);
                $T->set_block('intro', 'introFields', 'iField');
                foreach ($introfields as $fld) {
                    $T->set_var(array(
                        'if_prompt' => $fld,
                        'if_name'   => COM_sanitizeId($fld),
                    ) );
                    $T->parse('iField', 'introFields', true);
                }
                $T->parse('output', 'intro');
                $retval .= $T->finish($T->get_var('output'));
        } else {
            $questions = SESS_getVar('quizzer_questions');
            $total_q = count($questions);
            if (isset($questions[$question])) {
                $Q = Question::getInstance($questions[$question]);
                $retval .= $Q->Render($question >= $total_q);
            }
        }

/*

        $T = QUIZ_getTemplate('quiz', 'quiz');
        // Set template variables without allowing caching
        $T->set_var(array(
            'frm_action'    => $actionurl,
            'btn_submit'    => $saveaction,
            'frm_id'        => $this->id,
            'introtext'     => $this->introtext,
            'error_msg'     => isset($_POST['quizzer_error_msg']) ?
                                $_POST['quizzer_error_msg'] : '',
            'referrer'      => $referrer,
            'res_id'        => $res_id,
            'success_msg'   => self::_stripHtml($success_msg),
            'help_msg'      => self::_stripHtml($this->help_msg),
            'pi_url'        => QUIZ_PI_URL,
            'submit_disabled' => $allow_submit ? '' : 'disabled="disabled"',
            'instance_id'   => $this->instance_id,
            'iconset'       => $_CONF_QUIZ['_iconset'],
            'additional'    => $additional,
            'ajax'          => $this->sub_type == 'ajax' ? true : false,
        ), '', false, true );

        $T->set_block('quiz', 'QueueRow', 'qrow');
        $hidden = '';

        foreach ($this->questions as $Q) {
            // Fields that can't be rendered (no permission, calc, disabled)
            // return null. Skip those completely.
            $rendered = $F->displayField($res_id, $mode);
            if ($rendered !== NULL) {
                $T->set_var(array(
                    'prompt'    => PLG_replaceTags($F->prompt),
                    'safe_prompt' => self::_stripHtml($F->prompt),
                    'fieldname' => $F->name,
                    'field'     => $rendered,
                    'help_msg'  => self::_stripHtml($F->help_msg),
                    'spancols'  => isset($F->options['spancols']) && $F->options['spancols'] == 1 ? 'true' : '',
                    'is_required' => $F->access == QUIZ_FIELD_REQUIRED ? 'true' : '',
                ), '', false, true);
                $T->parse('qrow', 'QueueRow', true);
            }
        }

        $T->set_var('hidden_vars', $hidden);*/
    //    $T->parse('output', 'quiz');
    //    $retval .= $T->finish($T->get_var('output'));
        return $retval;
    }


    /**
    *   Delete a quize definition.
    *   Deletes a quiz, removes the questions, and deletes user data.
    *
    *   @uses   Result::Delete()
    *   @param  integer $quiz_id     Optional quiz ID, current object if empty
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
    }


    /**
    *   Determine if a specific user has a given access level to the quiz
    *
    *   @param  integer $level  Requested access level
    *   @param  integer $uid    Optional user ID, current user if omitted.
    *   @return boolean     True if the user has access, false if not
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
    *   Duplicate this quiz.
    *   Creates a copy of this quiz with all its fields.
    *
    *   @uses   Field::Duplicate()
    *   @return string      Error message, empty if successful
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
    *   Get the number of responses (result sets) for this quiz.
    *
    *   @return integer     Response count.
    */
    public function Responses()
    {
        global $_TABLES;
        return DB_count($_TABLES['quizzer_results'], 'quiz_id', $this->id);
    }


    /**
    *   Remove HTML and convert other characters.
    *
    *   @param  string  $str    String to sanitize
    *   @return string          String with no quotes or tags
    */
    private static function _stripHtml($str)
    {
        return htmlentities(strip_tags($str));
    }


    /**
    *   Toggle a boolean field in the database
    *
    *   @param  $id     Field def ID
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

}

?>
