<?php
/**
*   Upgrade routines for the Quizzer plugin
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2018 Lee Garner <lee@leegarner.com>
*   @package    quizzer
*   @version    0.0.2
*   @license    http://opensource.org/licenses/gpl-2.0.php 
*               GNU Public License v2 or later
*   @filesource
*/

// Required to get the config values
global $_CONF, $_CONF_QUIZ;

/**
 *  Make the installation default values available to these functions.
 */
require_once __DIR__ . '/install_defaults.php';

global $_DB_dbms;
require_once __DIR__ . "/sql/{$_DB_dbms}_install.php";

/**
*   Perform the upgrade starting at the current version.
*
*   @param  string  $current_ver    Current installed version to be upgraded
*   @return integer                 Error code, 0 for success
*/
function QUIZ_do_upgrade()
{
    global $_CONF_QUIZ, $_PLUGIN_INFO;

    if (isset($_PLUGIN_INFO[$_CONF_QUIZ['pi_name']])) {
        if (is_array($_PLUGIN_INFO[$_CONF_QUIZ['pi_name']])) {
            // glFusion > 1.6.5
            $current_ver = $_PLUGIN_INFO[$_CONF_QUIZ['pi_name']]['pi_version'];
        } else {
            // legacy
            $current_ver = $_PLUGIN_INFO[$_CONF_QUIZ['pi_name']];
        }
    } else {
        return false;
    }
    $code_ver = plugin_chkVersion_quizzer();

    /*
     * For reference for the next non-code-only update
     * if (!COM_checkVersion($current_ver, '0.3.1')) {
        $current_ver = '0.3.1';
        COM_errorLog("Updating Plugin to $current_ver");
        if (!QUIZ_do_upgrade_sql($current_ver)) return false;
        if (!QUIZ_do_set_version($current_ver)) return false;
    }*/

    if (!COM_checkVersion($current_ver, $code_ver)) {
        if (!QUIZ_do_set_version($code_ver)) return false;
    }
    COM_errorLog('Successfully updated the Quizzer plugin');
    return true;
}


/**
*   Actually perform any sql updates.
*   If there are no SQL statements, then SUCCESS is returned.
*
*   @param  string  $version    Version being upgraded TO
*   @param  array   $sql        Array of SQL statement(s) to execute
*   @return boolean     True for success, False for failure
*/
function QUIZ_do_upgrade_sql($version, $sql='')
{
    global $_TABLES, $_CONF_QUIZ, $_QUIZ_UPGRADE_SQL;

    // If no sql statements passed in, return success
    if (!isset($_QUIZ_UPGRADE_SQL[$version]) || 
            !is_array($_QUIZ_UPGRADE_SQL[$version]))
        return true;

    // Execute SQL now to perform the upgrade
    COM_errorLOG("--Updating Quizzer to version $version");
    foreach ($_QUIZ_UPGRADE_SQL[$version] as $sql) {
        COM_errorLOG("Quizzer Plugin $version update: Executing SQL => $sql");
        DB_query($sql, '1');
        if (DB_error()) {
            COM_errorLog("SQL Error during Quizzer plugin update",1);
            return false;
        }
    }
    return true;
}


/**
*   Update the plugin version number in the database.
*   Called at each version upgrade to keep up to date with
*   successful upgrades.
*
*   @param  string  $ver    New version to set
*   @return boolean         True on success, False on failure
*/
function QUIZ_do_set_version($ver)
{
    global $_TABLES, $_CONF_QUIZ;

    // now update the current version number.
    $sql = "UPDATE {$_TABLES['plugins']} SET
            pi_version = '{$_CONF_QUIZ['pi_version']}',
            pi_gl_version = '{$_CONF_QUIZ['gl_version']}',
            pi_homepage = '{$_CONF_QUIZ['pi_url']}'
        WHERE pi_name = '{$_CONF_QUIZ['pi_name']}'";

    $res = DB_query($sql, 1);
    if (DB_error()) {
        COM_errorLog("Error updating the {$_CONF_QUIZ['pi_display_name']} Plugin version",1);
        return false;
    } else {
        return true;
    }
}

?>
