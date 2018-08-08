<?php
/**
*   Entry point to administration functions for the Quizzer plugin.
*   This module isn't exclusively for site admins.  Regular users may
*   be given administrative privleges for certain quizzer, so they'll need
*   access to this file.
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2010-2018 Lee Garner <lee@leegarner.com>
*   @package    quizzer
*   @version    0.3.1
*   @license    http://opensource.org/licenses/gpl-2.0.php
*               GNU Public License v2 or later
*   @filesource
*/
namespace Quizzer;

/** Import core glFusion libraries */
require_once '../../../lib-common.php';

// Make sure the plugin is installed and enabled
if (!in_array('quizzer', $_PLUGINS)) {
    COM_404();
}

// Flag to indicate if this user is a "real" administrator for the plugin.
// Some functions, like deleting definitions, are only available to
// plugin admins.
$isAdmin = plugin_isadmin_quizzer();

// Import administration functions
USES_lib_admin();
USES_quizzer_functions();

$action = 'listquizzes';      // Default view
$expected = array('edit','updateform','editquestion', 'updatequestion',
    'save', 'print', 'editresult', 'updateresult',
    'editquiz', 'copyform', 'delbutton_x', 'showhtml',
    'moderate',
    'delQuiz', 'delQuestion', 'cancel', 'action', 'view',
    'results',
);
foreach($expected as $provided) {
    if (isset($_POST[$provided])) {
        $action = $provided;
        $actionval = $_POST[$provided];
        break;
    } elseif (isset($_GET[$provided])) {
        $action = $provided;
        $actionval = $_GET[$provided];
        break;
    }
}

$view = isset($_REQUEST['view']) ? $_REQUEST['view'] : $action;
$quiz_id = isset($_REQUEST['quiz_id']) ? COM_sanitizeID($_REQUEST['quiz_id']) : '';
$msg = isset($_GET['msg']) && !empty($_GET['msg']) ? $_GET['msg'] : '';
$content = '';

// Get the permission SQL once, since it's used in a couple of places.
// This determines if the current user is an admin for a particular form
if ($isAdmin) {
    $perm_sql = '';
} else {
    $perm_sql = " AND (owner_id='". (int)$_USER['uid'] . "'
            OR group_id IN (" . implode(',', $_GROUPS). "))";
}

if ($quiz_id != '') {
    // Check user's access, make sure they're admin for at least one form
    $x = DB_fetchArray(DB_query("SELECT count(*) as c
            FROM {$_TABLES['quizzer_quizzes']}
            WHERE id='$quiz_id' $perm_sql"), false);
    if (!$x || $x['c'] < 1) {
        COM_404();
    }
}

switch ($action) {
case 'action':      // Got "?action=something".
    switch ($actionval) {
    case 'bulkfldaction':
        if (!isset($_POST['cb']) || !isset($_POST['quiz_id']))
            break;
        $id = $_POST['quiz_id'];    // Override the usual 'id' parameter
        $fldaction = isset($_POST['fldaction']) ? $_POST['fldaction'] : '';

        switch ($fldaction) {
        case 'rmfld':
        case 'killfld':
            $deldata = $fldaction = 'killfld' ? true : false;
            $F = new Question();
            foreach ($_POST['cb'] as $varname=>$val) {
                $F->Read($varname);
                if (!empty($F->id)) {
                    $F->Remove($id, $deldata);
                }
            }
            break;
        }
        $view = 'editquiz';
        break;

    default:
        $view = $actionval;
        break;
    }
    break;

case 'updateresult':
    $F = new Quiz($_POST['quiz_id']);
    $R = new Result($_POST['res_id']);
    // Clear the moderation flag when saving a moderated submission
    $R->SaveData($_POST['quiz_id'], $F->fields, $_POST, $R->uid);
    Result::Approve($R->id);
    $view = 'results';
    break;

case 'updatequestion':
    $Q = Question::getInstance($_POST, $quiz_id);
    $msg = $Q->SaveDef($_POST);
    $view = 'editquiz';
    break;

case 'delbutton_x':
    if (isset($_POST['delfield']) && is_array($_POST['delfield'])) {
        // Deleting one or more fields
        foreach ($_POST['delfield'] as $key=>$value) {
            Field::Delete($value);
        }
    } elseif (isset($_POST['delresmulti']) && is_array($_POST['delresmulti'])) {
        foreach ($_POST['delresmulti'] as $key=>$value) {
            Result::Delete($value);
        }
        $view = 'results';
    }
    CTL_clearCache();   // so the autotags will pick it up.
    break;

case 'copyform':
    $F = new Quiz($quiz_id);
    $msg = $F->Duplicate();
    if (empty($msg)) {
        echo COM_refresh(QUIZ_ADMIN_URL . '/index.php?editquiz=x&amp;quiz_id=' .
            $F->id);
        exit;
    } else {
        $view = 'listquizzes';
    }
    break;

case 'updateform':
    $F = new Quiz($_POST['old_id']);
    $msg = $F->SaveDef($_POST);
    if ($msg != '') {                   // save operation failed
        $view = 'editquiz';
    } elseif (empty($_POST['old_id'])) {    // New form, return to add fields
        $quiz_id = $F->id;
        $view = 'editquiz';
        $msg = 6;
    } else {
        $view = 'listquizzes';
    }
    break;

case 'delQuiz':
    // Delete a form definition.  Also deletes user values.
    if (!$isAdmin) COM_404();
    $id = $_REQUEST['quiz_id'];
    $msg = Quiz::DeleteDef($id);
    $view = 'listquizzes';
    break;

case 'deleteQuestion':
    if (!$isAdmin) COM_404();
    // Delete a field definition.  Also deletes user values.
    $msg = Field::Delete($_GET['q_id']);
    $view = 'editquiz';
    break;

}

// Select the page to display
switch ($view) {
case 'export':
    $Frm = new Quiz($quiz_id);

    // Get the form result sets
    $sql = "SELECT r.* FROM {$_TABLES['quizzer_results']} r
            LEFT JOIN {$_TABLES['quizzer_quizzes']} f
            ON f.id = r.quiz_id
            WHERE quiz_id='$quiz_id'
            $perm_sql
            ORDER BY dt ASC";
    $res = DB_query($sql);

    $R = new Result();
    $fields = array('"UserID"', '"Submitted"');
    foreach ($Frm->fields as $F) {
        if (!$F->enabled) continue;     // ignore disabled fields
        $fields[] = '"' . $F->name . '"';
    }
    $retval = join(',', $fields) . "\n";
    while ($A = DB_fetchArray($res, false)) {
        $R->Read($A['id']);
        $fields = array(
            COM_getDisplayName($R->uid),
            strftime('%Y-%m-%d %H:%M', $R->dt),
        );
        foreach ($Frm->fields as $F) {
            if (!$F->enabled) continue;     // ignore disabled fields
            $F->GetValue($R->id);
            $fields[] = '"' . str_replace('"', '""', $F->value_text) . '"';
        }
        $retval .= join(',', $fields) . "\n";
    }
    header('Content-type: text/csv');
    header('Content-Disposition: attachment; filename="'.$quiz_id.'.csv"');
    echo $retval;
    exit;
    break;

case 'editquiz':
    // Edit a single definition
    $Q = new Quiz($quiz_id);
    $content .= adminMenu($view, 'hlp_quiz_edit');
    $content .= $Q->editQuiz();

    // Allow adding/removing fields from existing quizzer
    if ($quiz_id != '') {
        $content .= "<br /><hr />\n";
        $content .= listQuestions($quiz_id);
    }
    break;

case 'editquestion':
    if (!$isAdmin) COM_404();
    $q_id = isset($_GET['q_id']) ? (int)$_GET['q_id'] : 0;
    $Q = new Question($q_id, $quiz_id);
    $content .= adminMenu($view, 'hlp_question_edit');
    $content .= $Q->EditDef();
    break;

case 'resetpermform':
    if (!$isAdmin) COM_404();
    $content .= QUIZ_permResetForm();
    break;

case 'none':
    // In case any modes create their own content
    break;

case 'listquizzes':
default:
    $content .= adminMenu('listquizzes', 'hlp_quiz_list');
    $content .= listQuizzes();
    break;

}

$display = COM_siteHeader();
if (isset($msg) && !empty($msg)) {
    $display .= COM_showMessage(
        COM_applyFilter($msg, true), $_CONF_QUIZ['pi_name']);
}
$display .= COM_startBlock(
    $LANG_QUIZ['admin_title'] . ' (Ver. ' . $_CONF_QUIZ['pi_version'] . ')',
     '', COM_getBlockTemplate('_admin_block', 'header'));
$display .= $content;
$display .= COM_endBlock(COM_getBlockTemplate('_admin_block', 'footer'));
$display .= COM_siteFooter();
echo $display;
exit;


/**
*   Uses lib-admin to list the quizzer definitions and allow updating.
*
*   @return string HTML for the list
*/
function listQuizzes()
{
    global $_CONF, $_TABLES, $LANG_ADMIN, $LANG_QUIZ, $perm_sql;

    $retval = '';

    $header_arr = array(
        array('text' => 'ID',
            'field' => 'id',
            'sort' => true,
        ),
        array('text' => $LANG_ADMIN['edit'],
            'field' => 'edit',
            'sort' => false,
            'align' => 'center',
        ),
        array('text' => $LANG_ADMIN['copy'],
            'field' => 'copy',
            'sort' => false,
            'align' => 'center',
        ),
        array('text' => $LANG_QUIZ['submissions'],
            'field' => 'submissions',
            'sort' => false,
            'align' => 'center',
        ),
        array('text' => $LANG_QUIZ['quiz_name'],
            'field' => 'name',
            'sort' => true,
        ),
        array('text' => $LANG_QUIZ['enabled'],
            'field' => 'enabled',
            'sort' => false,
            'align' => 'center',
        ),
        array('text' => $LANG_QUIZ['export'],
            'field' => 'export',
            'sort' => true,
            'align' => 'center',
        ),
        array('text' => $LANG_ADMIN['delete'],
            'field' => 'delete',
            'sort' => false,
            'align' => 'center',
        ),
    );

    $text_arr = array();
    $query_arr = array('table' => 'quizzer_quizzes',
        'sql' => "SELECT *
                FROM {$_TABLES['quizzer_quizzes']}
                WHERE 1=1 $perm_sql",
        'query_fields' => array('name'),
        'default_filter' => ''
    );
    $defsort_arr = array('field' => 'name', 'direction' => 'ASC');
    $form_arr = array();
    $retval .= ADMIN_list('quizzer', __NAMESPACE__ . '\getField_form', $header_arr,
                    $text_arr, $query_arr, $defsort_arr, '', '', '', $form_arr);

    return $retval;
}


/**
*   Uses lib-admin to list the field definitions and allow updating.
*
*   @return string HTML for the list
*/
function listQuestions($quiz_id = '')
{
    global $_CONF, $_TABLES, $LANG_ADMIN, $LANG_QUIZ, $_CONF_QUIZ;

    $header_arr = array(
        array('text' => $LANG_ADMIN['edit'],
            'field' => 'edit',
            'sort' => false,
            'align' => 'center',
        ),
        array('text' => $LANG_QUIZ['question'],
            'field' => 'question',
            'sort' => false,
        ),
        array('text' => $LANG_QUIZ['type'],
            'field' => 'type',
            'sort' => false,
        ),
        array('text' => $LANG_QUIZ['enabled'],
            'field' => 'enabled',
            'sort' => false,
            'align' => 'center',
        ),
        array('text' => $LANG_ADMIN['delete'],
            'field' => 'delete',
            'sort' => false,
        ),
    );

    $defsort_arr = array('field' => 'q_id');
    $text_arr = array('form_url' => QUIZ_ADMIN_URL . '/index.php');
    $options_arr = array('chkdelete' => true,
            'chkname' => 'delquestion',
            'chkfield' => 'q_id',
    );
    $query_arr = array('table' => 'quizzer_questions',
        'sql' => "SELECT * FROM {$_TABLES['quizzer_questions']}",
        'query_fields' => array('name', 'type', 'value'),
        'default_filter' => '',
    );
    if ($quiz_id != '') {
        $query_arr['sql'] .= " WHERE quiz_id='" . DB_escapeString($quiz_id) . "'";
    }
    $form_arr = array();
    $T = new \Template(QUIZ_PI_PATH . '/templates/admin');
    $T->set_file('questions', 'questions.thtml');
    $T->set_var(array(
        'action_url'    => QUIZ_ADMIN_URL . '/index.php',
        'is_uikit'      => $_CONF_QUIZ['_is_uikit'],
        'quiz_id'        => $quiz_id,
        'pi_url'        => QUIZ_PI_URL,
        'question_adminlist' => ADMIN_list('quizzer',
                    __NAMESPACE__ . '\getField_field', $header_arr,
                    $text_arr, $query_arr, $defsort_arr, '', '',
                    $options_arr, $form_arr),
    ) );
    $T->parse('output', 'questions');
    return $T->finish($T->get_var('output'));
}


/**
*   Determine what to display in the admin list for each form.
*
*   @param  string  $fieldname  Name of the field, from database
*   @param  mixed   $fieldvalue Value of the current field
*   @param  array   $A          Array of all name/field pairs
*   @param  array   $icon_arr   Array of system icons
*   @return string              HTML for the field cell
*/
function getField_form($fieldname, $fieldvalue, $A, $icon_arr)
{
    global $_CONF, $LANG_ACCESS, $LANG_QUIZ, $_TABLES, $_CONF_QUIZ, $_LANG_ADMIN;

    $retval = '';

    switch($fieldname) {
    case 'edit':
        $url = QUIZ_ADMIN_URL . "/index.php?editquiz=x&amp;quiz_id={$A['id']}";
        $retval = COM_createLink('<i class="' . $_CONF_QUIZ['_iconset'] .
                '-edit qz-icon-info"></i>',
                $url
        );
        break;

    case 'copy':
        $url = QUIZ_ADMIN_URL . "/index.php?copyform=x&amp;quiz_id={$A['id']}";
        $retval = COM_createLink('<i class="' . $_CONF_QUIZ['_iconset'] .
                '-copy qz-icon-info"></i>',
                $url
        );
        break;

    case 'questions':
        $url = QUIZ_ADMIN_URL . "/index.php?questions=x&amp;quiz_id={$A['id']}";
        $retval = COM_createLink(
            '<i class="' . $_CONF_QUIZ['_iconset'] . '-question qz-icon-info"></i>', $url);
        break;

    case 'delete':
        $url = QUIZ_ADMIN_URL . "/index.php?delQuiz=x&quiz_id={$A['id']}";
        $retval = COM_createLink('<i class="'. $_CONF_QUIZ['_iconset'] .
                '-trash-o qz-icon-danger" ' .
                'onclick="return confirm(\'' .$LANG_QUIZ['confirm_delete'] .
                    '?\');"',
                $url
        );
        break;

    case 'enabled':
        if ($A[$fieldname] == 1) {
            $chk = ' checked ';
            $enabled = 1;
        } else {
            $chk = '';
            $enabled = 0;
        }
        $retval = "<input name=\"{$fieldname}_{$A['id']}\" " .
                "type=\"checkbox\" $chk " .
                "onclick='QUIZtoggleEnabled(this, \"{$A['id']}\", \"quiz\", \"{$fieldname}\", \"" . QUIZ_ADMIN_URL . "\");' " .
                "/>\n";
    break;

    case 'submissions':
        $url = QUIZ_ADMIN_URL . '/index.php?results=x&quiz_id=' . $A['id'];
        $txt = (int)DB_count($_TABLES['quizzer_results'], 'quiz_id', $A['id']);
        $retval = COM_createLink($txt, $url,
            array(
                'class' => 'tooltip',
                'title' => $LANG_QUIZ['form_results'],
            )
        );
        break;

    case 'export':
        $url = QUIZ_ADMIN_URL . "/index.php?export=x&amp;quiz_id={$A['id']}";
        $retval = COM_createLink(
            '<i class="' . $_CONF_QUIZ['_iconset'] . '-download qz-icon-info"></i>', $url);
        break;

    case 'action':
        $retval = '<select name="action"
            onchange="javascript: document.location.href=\'' .
            QUIZ_ADMIN_URL . '/index.php?quiz_id=' . $A['id'] .
            '&action=\'+this.options[this.selectedIndex].value">'. "\n";
        $retval .= '<option value="">--' . $LANG_QUIZ['select'] . '--</option>'. "\n";
        $retval .= '<option value="preview">' . $LANG_QUIZ['preview'] . '</option>'. "\n";
        $retval .= '<option value="results">' . $LANG_QUIZ['form_results'] . '</option>'. "\n";
        $retval .= '<option value="export">' . $LANG_QUIZ['export'] . '</option>'. "\n";
        $retval .= "</select>\n";
        break;

    default:
        $retval = $fieldvalue;
        break;

    }

    return $retval;
}


/**
*   Determine what to display in the admin list for each field.
*
*   @param  string  $fieldname  Name of the field, from database
*   @param  mixed   $fieldvalue Value of the current field
*   @param  array   $A          Array of all name/field pairs
*   @param  array   $icon_arr   Array of system icons
*   @return string              HTML for the field cell
*/
function getField_field($fieldname, $fieldvalue, $A, $icon_arr)
{
    global $_CONF, $_CONF_QUIZ, $LANG_ACCESS, $LANG_QUIZ;

    $retval = '';

    switch($fieldname) {
    case 'edit':
        $retval = COM_createLink('<i class="' . $_CONF_QUIZ['_iconset'] .
                '-edit qz-icon-info"></i>',
            QUIZ_ADMIN_URL . "/index.php?editquestion=x&amp;q_id={$A['q_id']}"
        );
        break;

    case 'delete':
        $retval = COM_createLink(
            '<i class="' . $_CONF_QUIZ['_iconset'] . '-trash-o qz-icon-danger"></i>',
            QUIZ_ADMIN_URL . '/index.php?delQuestion=x&q_id=' .
                    $A['q_id'] . '&quiz_id=' . $A['quiz_id'],
            array(
                'onclick' => "return confirm('{$LANG_QUIZ['confirm_delete']}');",
            )
        );
       break;

    case 'enabled':
        if ($A[$fieldname] == 1) {
            $chk = ' checked ';
            $enabled = 1;
        } else {
            $chk = '';
            $enabled = 0;
        }
        $retval = "<input name=\"{$fieldname}_{$A['q_id']}\" " .
                "type=\"checkbox\" $chk " .
                "onclick='QUIZtoggleEnabled(this, \"{$A['q_id']}\", \"question\", \"{$fieldname}\", \"" . QUIZ_ADMIN_URL . "\");' ".
                "/>\n";
    break;

    case 'id':
    case 'q_id':
        return '';
        break;

    default:
        $retval = $fieldvalue;
        break;

    }

    return $retval;
}


/**
*   Create the admin menu at the top of the list and form pages.
*
*   @param  string  $view   Current view, used to select menu options
*   @param  string  $help_text  Text to display below menu
*   @return string      HTML for admin menu section
*/
function adminMenu($view ='', $help_text = '', $other_text='')
{
    global $_CONF, $LANG_QUIZ, $_CONF_QUIZ, $LANG01, $isAdmin;

    $menu_arr = array ();
    if ($help_text == '')
        $help_text = 'admin_text';

    if ($view == 'listquizzes' && $isAdmin) {
        $menu_arr[] = array('url' => QUIZ_ADMIN_URL . '/index.php?action=editquiz',
            'text' => $LANG_QUIZ['add_quiz']);
    } else {
        $menu_arr[] = array('url' => QUIZ_ADMIN_URL . '/index.php?view=listquizzes',
            'text' => $LANG_QUIZ['list_quizzes']);
    }

    $menu_arr[] = array('url' => $_CONF['site_admin_url'],
            'text' => $LANG01[53]);

    $text = $LANG_QUIZ[$help_text];
    if (!empty($other_text)) $text .= '<br />' . $other_text;
    return ADMIN_createMenu($menu_arr, $text, plugin_geticon_quizzer());
}

?>
