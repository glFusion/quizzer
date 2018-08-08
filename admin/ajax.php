<?php
/**
 *  Common AJAX functions
 *
 *  @author     Lee Garner <lee@leegarner.com>
 *  @copyright  Copyright (c) 2010 Lee Garner <lee@leegarner.com>
 *  @package    quizzer
 *  @version    0.0.1
 *  @license    http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 *  @filesource
 */

/**
 *  Include required glFusion common functions
 */
require_once '../../../lib-common.php';

// This is for administrators only
if (!plugin_isadmin_quizzer()) {
    COM_accessLog("User {$_USER['username']} tried to illegally access the quizzer AJAX functions.");
    exit;
}

switch ($_POST['action']) {
case 'toggleEnabled':
    $oldval = $_POST['oldval'] == 0 ? 0 : 1;
    $newval = 99;
    $var = trim($_POST['var']);  // sanitized via switch below
    COM_errorLog("toggling $var from $oldval");
    $id = DB_escapeString($_POST['id']);
    if (isset($_POST['type'])) {
        switch ($_POST['type']) {
        case 'quiz':
            $newval = \Quizzer\Quiz::toggle($_POST['id'], 'enabled', $_POST['oldval']);
            break;
        case 'question':
            $newval = \Quizzer\Question::toggle($_POST['id'], 'enabled', $_POST['oldval']);
            break;
        default:
            break;
        }
    }

    $result = array(
        'status' => $newval == $oldval ? false : true,
        'statusMessage' => $newval == $oldval ? $LANG_QUIZ['toggle_failure'] :
                $LANG_QUIZ['toggle_success'],
        'newval' => $newval,
    );
    
    header('Content-Type: text/xml');
    header("Cache-Control: no-cache, must-revalidate");
    //A date in the past
    header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
    echo json_encode($result);
    break;

default:
    exit;
}

?>
