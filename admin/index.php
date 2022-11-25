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

$Request = Quizzer\Models\Request::getInstance();
$expected = array(
    'edit','updateform','editquestion', 'updatequestion',
    'savequiz', 'print', 'editresult', 'updateresult', 'resetquiz',
    'editquiz', 'delQuizmulti', 'showhtml',
    'moderate',
    'savereward', 'delreward', 'delbutton', 'delbutton_x',
    'delQuiz', 'delQuestion', 'cancel', 'action', 'view',
    'results', 'resultsbyq', 'csvbyq', 'csvbysubmitter',
    'delresult', 'viewresult',
);
list ($action, $actionval) = $Request->getAction($expected, 'listquizzes');

$view = $Request->getString('view', $action);
$quizID = $Request->getString('quizID');
if ($quizID == '') {
    // Question update submissions have "id" as the quiz_id field name
    $quizID = $Request->getString('id');
}
$quizID = COM_sanitizeID($quizID, false);
$questionID = $Request->getInt('questionID');
$msg = $Request->getString('msg');
$content = '';

switch ($action) {
case 'action':      // Got "?action=something".
    switch ($actionval) {
    case 'bulkfldaction':
        if (!isset($Request['cb']) || !isset($Request['quizID'])) {
            break;
        }
        $id = $_POST['quizID'];    // Override the usual 'id' parameter
        $fldaction = $Request->getString('fldaction');

        switch ($fldaction) {
        case 'rmfld':
        case 'killfld':
            $deldata = $fldaction = 'killfld' ? true : false;
            $F = new Question();
            foreach ($Request->getArray('cb') as $varname=>$val) {
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
    $F = new Quizzer\Quiz($quizID);
    $R = new Quizzer\Result($Result->getInt('res_id'));
    // Clear the moderation flag when saving a moderated submission
    $R->SaveData($quizID, $F->fields, $Request->toArray(), $R->uid);
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
    $Q = Quizzer\Question::getInstance($Request->toArray(), $quizID);
    if ($Q) {
        $msg = $Q->SaveDef($Request->toArray());
    }
    $view = 'editquiz';
    break;

case 'delQuizmulti':
    $delfield = $Request->getArray('delfield');
    if (!empty($delfield)) {
        // Deleting one or more fields
        foreach ($delfield as $key=>$value) {
            Quizzer\Quiz::DeleteDef($value);
        }
    }
    CTL_clearCache();   // so the autotags will pick it up.
    break;

case 'savequiz':
    $old_id = $Request->getString('old_id');
    $Q = new Quizzer\Quiz($old_id);
    $msg = $Q->SaveDef($Request->toArray());
    if ($msg != '') {               // save operation failed
        $view = 'editquiz';
    } elseif (empty($old_id)) {     // New form, return to add fields
        $quizID = $Q->getID();
        $view = 'editquiz';
        $msg = 6;
    } else {
        COM_refresh(QUIZ_ADMIN_URL . '/index.php?quizzes');
    }
    break;

case 'delQuiz':
    // Delete a form definition.  Also deletes user values.
    $msg = Quizzer\Quiz::DeleteDef($quizID);
    $view = 'listquizzes';
    break;

case 'resetquiz':
    // Removes all results for the quiz.
    Quizzer\Result::ResetQuiz($quizID);
    echo COM_refresh(QUIZ_ADMIN_URL);
    break;

case 'delQuestion':
    // Delete a single question.
    Quizzer\Question::Delete(array($questionID));
    $view = 'editquiz';
    break;

case 'delbutton':
case 'delbutton_x':
    if (isset($Request['delquestion'])) {
        $q_ids = $Request->getArray('delquestion');
        Quizzer\Question::Delete($q_ids);
        $view = 'editquiz';
    }
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
