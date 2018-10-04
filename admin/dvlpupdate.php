<?php
/**
*   Apply updates to Quizzer during development.
*   Calls quizzer_upgrade() with "ignore_errors" set so repeated SQL statements
*   won't cause functions to abort.
*
*   Only updates from the previous released version.
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2018 Lee Garner <lee@leegarner.com>
*   @package    quizzer
*   @version    0.0.3
*   @since      0.0.3
*   @license    http://opensource.org/licenses/gpl-2.0.php
*               GNU Public License v2 or later
*   @filesource
*/

require_once '../../../lib-common.php';
if (!SEC_inGroup('Root')) {
    // Someone is trying to illegally access this page
    COM_errorLog("Someone has tried to access the Quizzer Development Code Upgrade Routine without proper permissions.  User id: {$_USER['uid']}, Username: {$_USER['username']}, IP: " . $_SERVER['REMOTE_ADDR'],1);
    $display  = COM_siteHeader();
    $display .= COM_startBlock($LANG27[12]);
    $display .= $LANG27[12];
    $display .= COM_endBlock();
    $display .= COM_siteFooter(true);
    echo $display;
    exit;
}

if (function_exists('CACHE_clear')) {
    CACHE_clear();
}
Quizzer\Cache::clear();

$_PLUGIN_INFO['quizzer']['pi_version'] = '0.0.1';
plugin_upgrade_quizzer(true);

header('Location: '.$_CONF['site_admin_url'].'/plugins.php?msg=600');
exit;

?>
