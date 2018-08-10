<?php
/**
*   Common Guest-Facing AJAX functions.
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2018 Lee Garner <lee@leegarner.com>
*   @package    forms
*   @version    0.3.1
*   @license    http://opensource.org/licenses/gpl-2.0.php
*               GNU Public License v2 or later
*   @filesource
*/

/** Include required glFusion common functions */
require_once '../lib-common.php';

switch ($_GET['action']) {
case 'saveresponse':
    $result_id = SESS_getVar('quizzer_resultset');
    if (!$result_id) {
        exit;
    }
    $quiz_id = isset($_POST['quiz_id']) ? $_POST['quiz_id'] : '';
    $q_id = isset($_POST['q_id']) ? (int)$_POST['q_id'] : '';
    $a_id = isset($_POST['a_id']) ? (int)$_POST['a_id'] : '';
    $Q = Quizzer\Question::getInstance($q_id);
    $correct = $Q->getCorrectAnswers();
    $retval = array(
        'submitted_ans' => $a_id,
        'correct_ans' => $correct,
        'answer_msg' => $Q->answer_msg,
    );
    Quizzer\Value::Save($result_id, $q_id, $a_id);
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');
//A date in the past
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
echo json_encode($retval);

?>
