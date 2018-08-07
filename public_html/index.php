<?php
/**
*   Home page for the Quizzer plugin.
*   Used to either display a specific form, or to save the user-entered data.
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2010-2018 Lee Garner <lee@leegarner.com>
*   @package    forms
*   @version    0.3.1
*   @license    http://opensource.org/licenses/gpl-2.0.php
*               GNU Public License v2 or later
*   @filesource
*/

require_once '../lib-common.php';
if (!in_array('forms', $_PLUGINS)) {
    COM_404();
}

USES_forms_functions();

$content = '';
$action = '';
$actionval = '';
$expected = array(
    'savedata', 'saveintro', 'results', 'mode', 'print', 'startquiz', 'next_q',
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
    $action = 'startquiz';
    COM_setArgNames(array('quiz_id'));
    $quiz_id = COM_getArgument('quiz_id');
} else {
    $quiz_id = isset($_REQUEST['quiz_id']) ? $_REQUEST['quiz_id'] : '';
}
$q_id = isset($_REQUEST['q_id']) ? (int)$_REQUEST['q_id'] : 0;

if ($action == 'mode') $action = $actionval;

switch ($action) {
case 'saveintro':
    $R = new Quizzer\Result();
    $R->Create($quiz_id, $_POST['intro']);
    SESS_setVar('quizzer_resultset', $R->id);
    echo COM_refresh(QUIZ_PI_URL . '/index.php?startquiz=x&q_id=1');
    break;

case 'savedata':
    $F = new \Quizzer\Form($_POST['quiz_id']);
    $redirect = str_replace('{site_url}', $_CONF['site_url'], $F->redirect);
    $errmsg = $F->SaveData($_POST);
    if (empty($errmsg)) {
        // Success
        if ($F->onsubmit & QUIZ_ACTION_DISPLAY) {
            $redirect = QUIZ_PI_URL . '/index.php?results=x&res_id=' .
                    $F->res_id;
            if ($F->onsubmit & QUIZ_ACTION_STORE) {
                $redirect .= '&token=' . $F->Result->Token();
            }
        } elseif (empty($redirect)) {
            $redirect = $_CONF['site_url'];
        }
        $u = parse_url($redirect);
        if ($F->submit_msg != '') {
            LGLIB_storeMessage($F->submit_msg);
            $msg = '';
        } else {
            $msg = isset($_POST['submit_msg']) ? $_POST['submit_msg'] : '1';
        }
        $q = array();
        if (!empty($u['query'])) {
            parse_str($u['query'], $q);
        }
        $q['msg'] = $msg;
        $q['plugin'] = $_CONF_QUIZ['pi_name'];
        $q['quiz_id'] = $F->id;
        //$redirect = $u['scheme'].'://'.$u['host'].$u['path'].'?';
        $q_arr = array();
        foreach($q as $key=>$value) {
            $q_arr[] = "$key=" . urlencode($value);
        }
        $sep = strpos($redirect, '?') ? '&' : '?';
        $redirect .= $sep . join('&', $q_arr);
        echo COM_refresh($redirect);
    } else {
        $msg = '2';
        if (!isset($_POST['referrer']) || empty($_POST['referrer'])) {
            $_POST['referrer'] = $_SERVER['HTTP_REFERER'];
        }
        $_POST['forms_error_msg'] = $errmsg;
        QUIZ_showForm($_POST['quiz_id']);
    }
    exit;
    break;

case 'results':
    $res_id = isset($_REQUEST['res_id']) ? (int)$_REQUEST['res_id'] : 0;
    $quiz_id = isset($_REQUEST['quiz_id']) ? $_REQUEST['quiz_id'] : '';
    $token  = isset($_GET['token']) ? $_GET['token'] : '';
    echo COM_siteHeader();
    if ($res_id > 0 && $quiz_id != '') {
        $F = new \Quizzer\Form($quiz_id);
        $F->ReadData($res_id);
        if (($F->Result->uid == $_USER['uid'] && $F->Result->Token() == $token)
                || plugin_isadmin_forms()) {
            $content .= '<h1>';
            $content .= $F->submit_msg == '' ? $LANG_FORMS['def_submit_msg'] :
                    $F->submit_msg;
            $content .= '</h1>';
            $content .= $F->Prt($res_id);
            $content .= '<hr />' . LB;
            $content .= '<center><a href="' . QUIZ_PI_URL .
                '/index.php?print=x&res_id=' . $res_id . '&quiz_id=' . $quiz_id .
                '" target="_blank">' .
                '<img src="' . $_CONF['layout_url'] .
                '/images/print.png" border="0" title="' .
                $LANG01[65] . '"></a></center>';
        }
    }
    echo $content;
    echo COM_siteFooter();
    exit;
    break;

case 'print':
    $res_id = isset($_REQUEST['res_id']) ? (int)$_REQUEST['res_id'] : 0;
    $quiz_id = isset($_GET['quiz_id']) ? $_GET['quiz_id'] : '';
    if ($quiz_id != '' && $res_id > 0) {
        $F = Quizzer\Form::getInstance($quiz_id);
        $F->ReadData($res_id);
        if ((!empty($F->Result) && $F->Result->uid == $_USER['uid']) ||
                plugin_isadmin_forms() ) {
            $content .= $F->Prt($res_id, true);
        }
        echo $content;
        exit;
    }
    break;

case 'next_q':
    echo "here";die;
    $q_id++;
case 'startquiz':
default:
    if ($quiz_id == '') {
        // Missing form ID, we don't know what to do.
        $Q = Quizzer\Quiz::getFirst();
    } else {
        $Q = Quizzer\Quiz::getInstance($quiz_id);
    }
    if (!$Q->isNew) {
        $content .= $Q->Render($q_id);
    }
    break;
}

echo COM_siteHeader();
echo $content;
echo COM_siteFooter();
exit;

/**
*   Display a form
*
*   @param  integer $quiz_id     Form ID
*   @return string              HTML for the displayed form
*/
function QUIZ_showForm($quiz_id, $modal = false)
{
    global $_CONF_QUIZ, $_CONF;

    // Instantiate the form and make sure the current user has access
    // to fill it out
    $F = new \Quizzer\Form($quiz_id, QUIZ_ACCESS_FILL);

    $blocks = $modal ? 0 : -1;
    echo \Quizzer\QUIZ_siteHeader($F->name, '', $blocks);
    if (isset($_GET['msg']) && !empty($_GET['msg'])) {
        echo COM_showMessage(
                COM_applyFilter($_GET['msg'], true), $_CONF_QUIZ['pi_name']);
    }
    if ($F->id != '' && $F->access && $F->enabled) {
        echo $F->Render();
    } else {
        $msg = $F->noaccess_msg;
        if (!empty($msg)) {
            echo $msg;
        } else {
            echo COM_refresh($_CONF['site_url']);
        }
    }
    $blocks = $modal ? 0 : -1;
    echo \Quizzer\QUIZ_siteFooter($blocks);
}

?>
