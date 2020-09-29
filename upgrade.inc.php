<?php
/**
 * Upgrade routines for the Quizzer plugin.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2018 Lee Garner <lee@leegarner.com>
 * @package     quizzer
 * @version     v0.0.2
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */

// Required to get the config values
global $_CONF, $_CONF_QUIZ;

/**
 * Make the installation default values available to these functions.
 */
require_once __DIR__ . '/install_defaults.php';

global $_DB_dbms;
require_once __DIR__ . "/sql/{$_DB_dbms}_install.php";

/**
 * Perform the upgrade starting at the current version.
 *
 * @param   boolean $dvlp   True for development update
 * @return  integer                 Error code, 0 for success
 */
function QUIZ_do_upgrade($dvlp=false)
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

    if (!COM_checkVersion($current_ver, '0.0.3')) {
        $current_ver = '0.0.3';
        COM_errorLog("Updating Plugin to $current_ver");
        if (!QUIZ_do_upgrade_sql($current_ver, $dvlp)) return false;
        if (!QUIZ_do_set_version($current_ver)) return false;
    }

    if (!COM_checkVersion($current_ver, '0.0.4')) {
        $current_ver = '0.0.4';
        COM_errorLog("Updating Plugin to $current_ver");
        if (!QUIZ_do_upgrade_sql($current_ver, $dvlp)) return false;
        if (!QUIZ_do_set_version($current_ver)) return false;
    }

    if (!COM_checkVersion($current_ver, $code_ver)) {
        if (!QUIZ_do_set_version($code_ver)) return false;
    }
    include_once 'install_defaults.php';
    plugin_updateconfig_quizzer();
    QUIZ_remove_old_files();
    COM_errorLog('Successfully updated the Quizzer plugin');
    return true;
}


/**
 * Actually perform any sql updates.
 * If there are no SQL statements, then SUCCESS is returned.
 *
 * @param   string  $version    Version being upgraded TO
 * @param   boolean $ignore_errors  True to ignore SQL errors and continue
 * @return  boolean     True for success, False for failure
 */
function QUIZ_do_upgrade_sql($version, $ignore_errors=false)
{
    global $_TABLES, $_CONF_QUIZ, $_QUIZ_UPGRADE_SQL;

    // If no sql statements passed in, return success
    if (
        !isset($_QUIZ_UPGRADE_SQL[$version]) ||
        !is_array($_QUIZ_UPGRADE_SQL[$version])
    ) {
        return true;
    }

    // Execute SQL now to perform the upgrade
    COM_errorLog("--Updating Quizzer to version $version");
    foreach ($_QUIZ_UPGRADE_SQL[$version] as $sql) {
        COM_errorLog("Quizzer Plugin $version update: Executing SQL => $sql");
        DB_query($sql, '1');
        if (DB_error()) {
            COM_errorLog("SQL Error during Quizzer plugin update",1);
            if (!$ignore_errors) return false;
        }
    }
    return true;
}


/**
 * Update the plugin version number in the database.
 * Called at each version upgrade to keep up to date with
 * successful upgrades.
 *
 * @param   string  $ver    New version to set
 * @return  boolean         True on success, False on failure
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


/**
 * Remove deprecated files.
 * No return, and errors here don't really matter
 */
function QUIZ_remove_old_files()
{
    global $_CONF, $_CONF_QUIZ;

    $paths = array(
        __DIR__ => array(
        ),
        // public_html/classifieds
        $_CONF['path_html'] . $_CONF_QUIZ['pi_name'] => array(
            // 1.3.0
            'docs/english/config.legacy.html',
        ),
        // admin/plugins/classifieds
        $_CONF['path_html'] . 'admin/plugins/' . $_CONF_QUIZ['pi_name'] => array(
        ),
    );

    foreach ($paths as $path=>$files) {
        foreach ($files as $file) {
            // Remove the file or directory
            QUIZ_rmdir("$path/$file");
        }
    }
}


/**
 * Remove a file, or recursively remove a directory.
 *
 * @param   string  $dir    Directory name
 */
function QUIZ_rmdir($dir)
{
    if (is_dir($dir)) {
        $objects = scandir($dir);
        foreach ($objects as $object) {
            if ($object != "." && $object != "..") {
                if (is_dir($dir . '/' . $object)) {
                    QUIZ_rmdir($dir . '/' . $object);
                } else {
                    @unlink($dir . '/' . $object);
                }
            }
        }
        @rmdir($dir);
    } elseif (is_file($dir)) {
        @unlink($dir);
    }
}

?>
