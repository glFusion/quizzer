<?php
/**
 * Entry point to administration functions for the Quizzer plugin.
 * This module isn't exclusively for site admins.  Regular users may
 * be given administrative privleges for certain quizzer, so they'll need
 * access to this file.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2010-2018 Lee Garner <lee@leegarner.com>
 * @package     quizzer
 * @version     v0.0.3
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */

/** Import core glFusion libraries */
require_once '../../../lib-common.php';

// Make sure the plugin is installed and enabled
if (!in_array('quizzer', $_PLUGINS)) {
    COM_404();
}

// Flag to indicate if this user is a "real" administrator for the plugin.
// Some functions, like deleting definitions, are only available to
// plugin admins.
if (!plugin_isadmin_quizzer()) {
    COM_404();
}

$action = 'listquizzes';      // Default view
$expected = array(
    'edit','updateform','editquestion', 'updatequestion',
    'savequiz', 'print', 'editresult', 'updateresult', 'resetquiz',
    'editquiz', 'copyform', 'delbutton_x', 'showhtml',
    'moderate',
    'savereward', 'delreward',
    'delQuiz', 'delQuestion', 'cancel', 'action', 'view',
    'results', 'resultsbyq', 'csvbyq', 'csvbysubmitter',
    'delresult', 'viewresult',
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
$quizID = QUIZ_getVar($_REQUEST, 'quizID', 'string');
if ($quizID == '') {
    // Question update submissions have "id" as the id field name
    $quizID = QUIZ_getVar($_REQUEST, 'id', 'string');
}
$quizID = COM_sanitizeID($quizID, false);
$questionID = isset($_REQUEST['questionID']) ? (int)$_REQUEST['questionID'] : 0;
$msg = isset($_GET['msg']) && !empty($_GET['msg']) ? $_GET['msg'] : '';
$content = '';

switch ($action) {
case 'action':      // Got "?action=something".
    switch ($actionval) {
    case 'bulkfldaction':
        if (!isset($_POST['cb']) || !isset($_POST['quizID']))
            break;
        $id = $_POST['quizID'];    // Override the usual 'id' parameter
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
    $F = new Quizzer\Quiz($_POST['quizID']);
    $R = new Quizzer\Result($_POST['res_id']);
    // Clear the moderation flag when saving a moderated submission
    $R->SaveData($_POST['quizID'], $F->fields, $_POST, $R->uid);
    Quizzer\Result::Approve($R->id);
    $view = 'results';
    break;

case 'delresult':
    $res_id = (int)$actionval;
    $R = new Quizzer\Result($res_id);       // to get the quiz id
    $quizID = $R->getQuizID();
    if (!$R->isNew()) {
        Quizzer\Result::Delete($res_id);
        Quizzer\Cache::clear();
    }
    echo COM_refresh(
        QUIZ_ADMIN_URL .
        '/index.php?action=results&quizID=' . $quizID
    );
    break;

case 'updatequestion':
    $Q = Quizzer\Question::getInstance($_POST, $quizID);
    if ($Q) {
        $msg = $Q->SaveDef($_POST);
    }
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
            Quizzer\Result::Delete($value);
        }
        $view = 'results';
    }
    CTL_clearCache();   // so the autotags will pick it up.
    break;

case 'copyform':
    $F = new Quizzer\Quiz($quizID);
    $msg = $F->Duplicate();
    if (empty($msg)) {
        echo COM_refresh(
            QUIZ_ADMIN_URL . '/index.php?editquiz=x&amp;quizID=' . $F->id
        );
        exit;
    } else {
        $view = 'listquizzes';
    }
    break;

case 'savequiz':
    $Q = new Quizzer\Quiz($_POST['old_id']);
    $msg = $Q->SaveDef($_POST);
    if ($msg != '') {                   // save operation failed
        $view = 'editquiz';
    } elseif (empty($_POST['old_id'])) {    // New form, return to add fields
        $quizID = $Q->getID();
        $view = 'editquiz';
        $msg = 6;
    } else {
        COM_refresh(QUIZ_ADMIN_URL . '/index.php?quizzes');
    }
    break;

case 'delQuiz':
    // Delete a form definition.  Also deletes user values.
    $id = $_REQUEST['quizID'];
    $msg = Quizzer\Quiz::DeleteDef($id);
    $view = 'listquizzes';
    break;

case 'resetquiz':
    // Removes all results for the quiz.
    Quizzer\Result::ResetQuiz($quizID);
    echo COM_refresh(QUIZ_ADMIN_URL);
    break;

case 'delQuestion':
    // Delete a field definition.  Also deletes user values.
    $msg = Quizzer\Question::Delete($questionID);
    $view = 'editquiz';
    break;
}

// Select the page to display
switch ($view) {
case 'csvbyq':
    $Q = new Quizzer\Quiz($quizID);
    // initiate the download
    header('Content-type: text/csv');
    header('Content-Disposition: attachment; filename="quiz-summary-'.$quizID.'.csv"');
    echo $Q->csvByQuestion();
    exit;

case 'csvbysubmitter':
    $Q = new Quizzer\Quiz($quizID);
    // initiate the download
    header('Content-type: text/csv');
    header('Content-Disposition: attachment; filename="quiz-detail-'.$quizID.'.csv"');
    echo $Q->csvBySubmitter();
    exit;

case 'editquiz':
    // Edit a single definition
    $Q = new Quizzer\Quiz($quizID);
    $content .= Quizzer\Menu::Admin($view, 'hlp_quiz_edit');
    $content .= $Q->editQuiz();

    // Allow adding/removing questions from existing quiz
    if ($quizID != '') {
        $content .= "<br /><hr />\n";
        $content .= Quizzer\Question::adminList($quizID);
    }
    break;

case 'editquestion':
    $Q = Quizzer\Question::getInstance($questionID, $quizID);
    $content .= Quizzer\Menu::Admin($view, 'hlp_question_edit');
    $content .= $Q->EditDef();
    break;

case 'resetpermform':
    $content .= QUIZ_permResetForm();
    break;

case 'results':
    $content .= Quizzer\Menu::Admin('', '');
    $content .= Quizzer\Quiz::getInstance($quizID)->resultSummary();
    break;

case 'resultsbyq':
    $content .= Quizzer\Menu::Admin('', '');
    $content .= Quizzer\Quiz::getInstance($quizID)->resultByQuestion();
    break;

case 'none':
    // In case any modes create their own content
    break;

case 'viewresult':
    $res_id = (int)$actionval;
    $R = new Quizzer\Result($res_id);       // to get the quiz id
    if (!$R->isNew()) {
        $content .= $R->Render();
    }
    break;

case 'listquizzes':
default:
    $content .= Quizzer\Menu::Admin('listquizzes', 'hlp_quiz_list');
    $content .= Quizzer\Quiz::adminList();
    break;

}

$display = COM_siteHeader();
if (isset($msg) && !empty($msg)) {
    $display .= COM_showMessage(
        COM_applyFilter($msg, true), $_CONF_QUIZ['pi_name']
    );
}
$display .= COM_startBlock(
    $LANG_QUIZ['admin_title'] . ' (Ver. ' . $_CONF_QUIZ['pi_version'] . ')',
    '',
    COM_getBlockTemplate('_admin_block', 'header')
);
$display .= $content;
$display .= COM_endBlock(COM_getBlockTemplate('_admin_block', 'footer'));
$display .= COM_siteFooter();
echo $display;
exit;

?>
