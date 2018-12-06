<?php
/**
*   English language file for the Quizzer plugin.
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2018 Lee Garner <lee@leegarner.com>
*   @package    quizzer
*   @version    0.0.1
*   @license    http://opensource.org/licenses/gpl-2.0.php 
*               GNU Public License v2 or later
*   @filesource
*/

$LANG_QUIZ = array(
'menu_title' => 'Quizzer',
'admin_text' => 'Create and edit custom form fields',
'admin_title' => 'Quizzer Administration',
'quiz_id' => 'Quiz ID',
'quiz_name' => 'Quiz Name',
'add_quiz' => 'New Quiz',
'add_question' => 'New Question',
'list_quizzes' => 'List Quizzes',
'list_questions' => 'List Questions',
'questions' => 'Questions',
'question'  => 'Question',
'answers'   => 'Answers',
'num_answered' => '%d answered',
'correct'   => 'Correct',
'num_q'     => 'Number of Questions',
'scoring_levels' => 'Scoring Levels',
'begin'     => 'Begin Quiz',
'answer_msg' => 'Post-Answer Message',
'start_over' => 'Start Over',
'start_quiz' => 'Start Quiz',
'back_to_home' => 'Back to Home',
'new_quiz'  => 'New Quiz',
'next_q'    => 'Next Question',
'finish'    => 'Finish Quiz',
'your_score' => 'Your Score',
'action'    => 'Action',
'introtext' => 'Intro Text',
'introfields' => 'Text Fields',
'pass_msg' => 'Message if Passed',
'fail_msg' => 'Message if Failed',
'no_answers' => 'No Submissions',
'csvbyq'    => 'Export CSV by Question',
'csvbysubmitter' => 'Export CSV by Submitter',
'submitter' => 'Submitter',
'submitted' => 'Submitted',
//'name'      => 'Name',
'type'      => 'Type',
'enabled'   => 'Enabled',
'permissions'   => 'Permissions',
'user_group'    => 'Group that can fill out',
'confirm_quiz_delete' => 'Are you sure you want to delete this quiz? All associated results and data will be removed.',
'confirm_quiz_reset' => 'Are you sure you want to delete all of the results for this quiz?',
'confirm_delete' => 'Are you sure you want to delete this item?',
'checkbox'  => 'Checkbox',
'radio'     => 'Radio',
'hlp_quiz_edit' => 'Create or edit an existing quiz. When creating a quiz, the quiz must be saved before questions can be added to it.',
'hlp_quiz_list'  => 'Select a quiz to edit, or create a new quiz by clicking "New Quiz" above. Other available actions are shown in the selection under the "Action" column.',
'hlp_question_edit' => 'Editing the question definition.',
'required' => 'This field is required',
'pul_once' => 'One entry, No Edit',
'pul_edit' => 'One entry, Edit allowed',
'pul_mult' => 'Multiple Entries',
'onetime'  => 'Per-user Submission Limit',
'submissions' => 'Submissions',
//'results' => 'View Submissions',
'results' => 'Results by Submitter',
'resultsbyq' => 'Results by Question',
'reset' => 'Reset',
'preview' => 'preview',
'select' => 'select',
'score' => 'Score',
'msg_no_questions' => 'No questions were found, resetting the quiz.',
'partial_credit' => 'Partial Credit Allowed?',
'randomize' => 'Randomize Answers?',
);

$PLG_quizzer_MESSAGE1 = 'Could not locate quiz information, resetting to the start.';
$PLG_quizzer_MESSAGE2 = 'The form contains missing or invalid fields.';
$PLG_quizzer_MESSAGE3 = 'The form has been updated.';
$PLG_quizzer_MESSAGE4 = 'Error updating the Quizzer plugin version.';
$PLG_quizzer_MESSAGE5 = 'A database error occurred. Check your site\'s error.log';
$PLG_quizzer_MESSAGE6 = 'Your form has been created. You may now create fields.';
$PLG_quizzer_MESSAGE7 = 'Sorry, the maximum number of submissions has been reached.';

/** Language strings for the plugin configuration section */
$LANG_configsections['quizzer'] = array(
    'label' => 'Quizzer',
    'title' => 'Quizzer Configuration'
);

$LANG_configsubgroups['quizzer'] = array(
    'sg_main' => 'Main Settings'
);

$LANG_fs['quizzer'] = array(
    'fs_main' => 'General Settings',
);

$LANG_confignames['quizzer'] = array(
    'centerblock'  => 'Make Centerblock?',
    'fill_gid'  => 'Default group to fill quizzer',
);

// Note: entries 0, 1, and 12 are the same as in $LANG_configselects['Core']
$LANG_configselects['quizzer'] = array(
    0 => array('True' => 1, 'False' => 0),
    1 => array('True' => TRUE, 'False' => FALSE),
    2 => array('As Submitted' => 'submitorder', 'By Votes' => 'voteorder'),
    3 => array('Yes' => 1, 'No' => 0),
    6 => array('Normal' => 'normal', 'Blocks' => 'blocks'),
    9 => array('Never' => 0, 'If Submission Queue' => 1, 'Always' => 2),
    10 => array('Never' => 0, 'Always' => 1, 'Accepted' => 2, 'Rejected' => 3),
    12 => array('No access' => 0, 'Read-Only' => 2, 'Read-Write' => 3),
    13 => array('None' => 0, 'Left' => 1, 'Right' => 2, 'Both' => 3),
);

?>
