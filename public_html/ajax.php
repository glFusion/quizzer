<?php
/**
*   Common Guest-Facing AJAX functions.
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2018 Lee Garner <lee@leegarner.com>
*   @package    forms
*   @version    v0.3.1
*   @license    http://opensource.org/licenses/gpl-2.0.php
*               GNU Public License v2 or later
*   @filesource
*/

/** Include required glFusion common functions */
require_once '../lib-common.php';

switch ($_POST['action']) {
case 'saveresponse':
    $result_id = SESS_getVar('quizzer_resultset');
    $quiz_id = isset($_POST['quiz_id']) ? $_POST['quiz_id'] : '';
    $q_id = isset($_POST['q_id']) ? (int)$_POST['q_id'] : 0;
    $a_id = isset($_POST['a_id']) ? $_POST['a_id'] : 0;
    if ($result_id == 0 || $quiz_id == '' || $q_id == 0 || $a_id == 0) {
        $retval = array(
            'isvalid' => 0,
            'answer_msg' => $LANG_QUIZ['must_supply_answer'],
        );
    } else {
        if (!is_array($a_id)) $a_id = array($a_id);
        $Q = \Quizzer\Question::getInstance($q_id);
        $isvalid = $Q->isNew ? 0 : 1;
        $correct = 0;   // so there's something for $retval
        if ($isvalid) {
            $correct = $Q->getCorrectAnswers();
            \Quizzer\Value::Save($result_id, $q_id, $a_id);
        }
        $sub_answers = $Q->Answers;
        foreach ($sub_answers as $id=>$answer) {
            $sub_answers[$id]['submitted'] = (int)in_array($id, $a_id);
        }
        $retval = array(
            'isvalid' => $isvalid,
            'submitted_ans' => $a_id,
            'answers' => $sub_answers,
            'correct_ans' => $correct,
            'answer_msg' => $Q->answer_msg,
        );
    }
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');
//A date in the past
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
echo json_encode($retval);

?>
