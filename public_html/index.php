<?php
/**
 * Guest-facing home page for the Quizzer plugin.
 * Used to either display a specific form, or to save the user-entered data.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2018-2020 Lee Garner <lee@leegarner.com>
 * @package     quizzer
 * @version     v0.0.3
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */

require_once '../lib-common.php';
if (!in_array('quizzer', $_PLUGINS)) {
    COM_404();
}

$Request = Quizzer\Models\Request::getInstance();
$content = '';
$action = '';
$actionval = '';
$quizID = '';
$expected = array(
    'savedata', 'saveintro', 'results', 'mode', 'print', 'startquiz',
    'next_q', 'finishquiz',
);
list($action, $actionval) = $Request->getAction($expected);

if (empty($action)) {
    COM_setArgNames(array('quizID', 'action'));
    $quizID = COM_getArgument('quizID');
    $action = COM_getArgument('action');
    if (empty($action)) {
        $action = 'startquiz';
    }
}
if (empty($quizID)) {
    // Still no quiz ID? Get from POST or possibly from URL
    $quizID= $Request->getString('quizID');
}
if ($quizID == '') {
    // Missing quiz ID, get the first enabled one
    $quizID = SESS_getVar('quizzer_quizID');
    if ($quizID !== 0) {
        $Q = \Quizzer\Quiz::getInstance($quizID);
    } else {
        $Q = \Quizzer\Quiz::getFirst();
    }
} else {
    // Else get the specific quiz
    $Q = \Quizzer\Quiz::getInstance($quizID);
}

// get the question ID if specified.
// @todo: clean up code to use a single name for question ID
if (isset($Request['q_id'])) {
    $q_id = $Request->getInt('q_id');
} elseif (isset($Request['questionID'])) {
    $q_id = $Request->getInt('questionID');
} else {
    $q_id = 0;
}

$Result = Quizzer\Result::getCurrent($Q->getID());
$outputHandle = outputHandler::getInstance();
$outputHandle->addMeta('http-equiv', 'Pragma', 'no-cache');
$outputHandle->addMeta('http-equiv', 'Expires', '-1');

switch ($action) {
case 'saveintro':
    $intro = $Request->getArray('intro');
    if ($Result->isNew()) {
        $Result->Create($Q->getID(), $intro);
    }
    $Result->saveIntro($intro);
    echo COM_refresh(QUIZ_PI_URL . '/index.php?next_q=x&quizID=' . $Q->getID());
    break;

case 'finishquiz':
    $content .= $Result->showScore();
    SESS_unSet('quizzer_quizID');
    break;

case 'startquiz':
    if ($actionval != 'x' && !empty($actionval) && $Q->getID() != $actionval) {
        $Q = Quizzer\Quiz::getInstance($actionval);
    }
    if ($Q->isNew()) {   // still no valid quiz, get the first available
        $Q = Quizzer\Quiz::getFirst();
    }
    if (!$Q->isNew()) { // double-check
        SESS_setVar('quizzer_quizID', $Q->getID());
    } else {
        SESS_unSet('quizzer_quizID');
        $content .= COM_showMessageText($LANG_QUIZ['msg_noquizzes'], '', true, 'error');
        break;
    }
    if ($Result->isNew()) {
        $Result->Create($Q->getID());
    }
    if (!$Q->isNew()) {
        // If the quiz exists, render the question
        if (count($Q->getQuestions()) == 0) {
            COM_setMsg($LANG_QUIZ['msg_no_questions']);
            echo COM_refresh($_CONF['site_url']);
        }
        $content .= $Q->Render(0);
    }
    if ($content == '') {
        COM_refresh(QUIZ_PI_URL . '/index.php');
    }
    break;

case 'next_q':
    $q_id = $Result->getNextQuestion();
default:
    if (!$Q->isNew()) {
        // If the quiz exists, render the question
        $content .= $Q->Render($q_id);
    }
    break;
}

echo COM_siteHeader();
echo $content;
echo COM_siteFooter();
exit;
