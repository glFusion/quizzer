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
$Request = Quizzer\Models\Request::getInstance();
$retval = array(
    'invalid' => 1,
);
COM_errorLog($Request->getQueryString());

switch ($Request->getString('action')) {
case 'saveresponse':
    $forfeit = $Request->getBool('forfeit');
    $quiz_id = $Request->getString('quizID');
    $R = Quizzer\Result::getCurrent($quiz_id);
    $Q = Quizzer\Quiz::getInstance($quiz_id);
    $isvalid = $Q->isNew() ? 0 : 1;
    if ($R->getID() == 0) {
        // This happens if there are no intro questions already saved,
        // which would have created a result set.
        $R->Create($Q->getID());
    }
    $q_id = $Request->getInt('questionID');
    $a_id = $Request->get('a_id');
    if (
        $R->getID() == 0 ||
        $quiz_id == '' ||
        $q_id == 0 ||
        (empty($a_id) && !$forfeit)
    ) {
        $retval = array(
            'isvalid' => 0,
            'answer_msg' => $LANG_QUIZ['must_supply_answer'],
        );
    } else {
        if (!is_array($a_id)) {
            $a_id = array($a_id);
        }
        $Question = Quizzer\Question::getInstance($q_id);
        $isvalid = $Question->isNew() ? 0 : 1;
        $correct = 0;   // so there's something for $retval
        if ($isvalid) {
            $correct = $Question->getCorrectAnswers();
            if ($forfeit) {
                Quizzer\Value::Forfeit($R->getID(), $q_id);
            } else {
                Quizzer\Value::Save($R->getID(), $q_id, $a_id);
            }
        }
        $sub_answers = $Question->getAnswers();
        foreach ($sub_answers as $id=>&$answer) {
            $answer = $answer->toArray();
            $answer['submitted'] = (int)in_array($id, $a_id);
        }
        $retval = array(
            'isvalid' => $isvalid,
            'submitted_ans' => $a_id,
            'answers' => $sub_answers,
            'correct_ans' => $correct,
            'answer_msg' => $Question->getAnswerMsg(),
        );
    }
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');
//A date in the past
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
echo json_encode($retval);
