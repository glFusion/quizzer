<?php
/**
 * Common AJAX functions.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2010-2022 Lee Garner <lee@leegarner.com>
 * @package     quizzer
 * @version     v0.2.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */

/**
 *  Include required glFusion common functions
 */
require_once '../../../lib-common.php';
use glFusion\Log\Log;

// This is for administrators only
if (!plugin_isadmin_quizzer()) {
    Log::write(
        'system',
        Log::ERROR,
        "User {$_USER['username']} tried to illegally access the quizzer AJAX functions."
    );
    exit;
}

$Request = Quizzer\Models\Request::getInstance();
if (!$Request->isAjax()) {
    COM_404();
}

switch ($Request->getString('action')) {
case 'toggleEnabled':
    $oldval = $Request->getInt('oldval');
    $newval = 99;
    $id = $Request->getString('id');
    switch ($Request->getString('type')) {
    case 'quiz':
        $newval = \Quizzer\Quiz::toggle($id, 'enabled', $oldval);
        break;
    case 'question':
        $newval = \Quizzer\Question::toggle((int)$id, 'enabled', $oldval);
        break;
    }

    $result = array(
        'status' => $newval == $oldval ? false : true,
        'statusMessage' => $newval == $oldval ? $LANG_QUIZ['toggle_failure'] :
                $LANG_QUIZ['toggle_success'],
        'newval' => $newval,
    );

    header('Content-Type: text/json');
    header("Cache-Control: no-cache, must-revalidate");
    //A date in the past
    header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
    echo json_encode($result);
    break;

default:
    exit;
}

