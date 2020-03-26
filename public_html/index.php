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

$content = '';
$action = '';
$quiz_id = '';
$expected = array(
    'savedata', 'saveintro', 'results', 'mode', 'print', 'startquiz',
    'next_q', 'finishquiz',
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
if (empty($action)) {
    COM_setArgNames(array('quiz_id', 'action'));
    $quiz_id = COM_getArgument('quiz_id');
    $action = COM_getArgument('action');
    if (empty($action)) {
        $action = 'startquiz';
    }
}
if (empty($quiz_id)) {
    // Still no quiz ID? Get from POST or possibly from URL
    $quiz_id = isset($_REQUEST['quiz_id']) ? $_REQUEST['quiz_id'] : '';
}
if ($quiz_id == '') {
    // Missing quiz ID, get the first enabled one
    $quiz_id = SESS_getVar('quizzer_quizID');
    if ($quiz_id !== 0) {
        $Q = \Quizzer\Quiz::getInstance($quiz_id);
    } else {
        $Q = \Quizzer\Quiz::getFirst();
    }
} else {
    // Else get the specific quiz
    $Q = \Quizzer\Quiz::getInstance($quiz_id);
}

// get the question ID if specified
$q_id = isset($_REQUEST['q_id']) ? (int)$_REQUEST['q_id'] : 0;
$Result = Quizzer\Result::getCurrent($Q->getID());
$outputHandle = outputHandler::getInstance();
$outputHandle->addRaw('<meta http-equiv="Pragma" content="no-cache">');
$outputHandle->addRaw('<meta http-equiv="Expires" content="-1">');

switch ($action) {
case 'saveintro':
    $intro = isset($_POST['intro']) ? $_POST['intro'] : '';
    if ($Result->isNew()) {
        $Result->Create($Q->getID(), $intro);
    } else {
        $Result->saveIntro($intro);
    }
    echo COM_refresh(QUIZ_PI_URL . '/index.php?startquiz=' . $Q->getID());
    break;

case 'finishquiz':
    $content .= $Result->showScore();
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
        SESS_unsetVar('quizzer_quizID');
    }
    if ($Result->isNew()) {
        $Result->Create($Q->getID());
    }
    if (!$Q->isNew()) {
        // If the quiz exists, render the question
        $content .= $Q->Render(0);
    }
    break;

case 'next_q':
    //$q_id = isset($_REQUEST['next_q_id']) ? $_REQUEST['next_q_id'] : $q_id++;
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

?>
