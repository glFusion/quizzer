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
use glFusion\Database\Database;
use glFusion\Log\Log;

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
    global $_CONF_QUIZ, $_PLUGIN_INFO, $_TABLES;

    $db = Database::getInstance();

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
        Log::write('system', Log::INFO, "Updating Plugin to $current_ver");
        if (!QUIZ_do_upgrade_sql($current_ver, $dvlp)) return false;
        if (!QUIZ_do_set_version($current_ver)) return false;
    }

    if (!COM_checkVersion($current_ver, '0.0.4')) {
        $current_ver = '0.0.4';
        Log::write('system', Log::INFO, "Updating Plugin to $current_ver");
        if (!QUIZ_do_upgrade_sql($current_ver, $dvlp)) return false;
        if (!QUIZ_do_set_version($current_ver)) return false;
    }

    if (!COM_checkVersion($current_ver, '0.0.5')) {
        $current_ver = '0.0.5';
        Log::write('system', Log::INFO, "Updating Plugin to $current_ver");

        // Map the admin feature to the Root group
        $ft_id = (int)$db->getItem(
            $_TABLES['features'],
            'ft_id',
            array('ft_name' => 'quizzer.admin'),
            array(Database::INTEGER)
        );
        if ($ft_id > 0) {
            try {
                $db->conn->executeStatement(
                    "INSERT IGNORE INTO {$_TABLES['access']}
                        (acc_ft_id, acc_grp_id)
                        VALUES
                        (?, 1)",
                    array($ft_id),
                    array(Database::INTEGER)
                );
            } catch (\Throwable $e) {
                Log::write('system', Log::ERROR, __FUNCTION__ . ': ' . $e->getMessage());
            }
        }
        if (!QUIZ_do_upgrade_sql($current_ver, $dvlp)) return false;
        if (!QUIZ_do_set_version($current_ver)) return false;
    }

    if (!COM_checkVersion($current_ver, $code_ver)) {
        if (!QUIZ_do_set_version($code_ver)) return false;
    }
    include_once 'install_defaults.php';
    plugin_updateconfig_quizzer();
    QUIZ_remove_old_files();
    Log::write('system', Log::INFO, 'Successfully updated the Quizzer plugin');
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
    global $_TABLES, $_CONF_QUIZ, $_QUIZ_UPGRADE_SQL, $_DB_dbms, $_VARS;

    // If no sql statements passed in, return success
    if (
        !isset($_QUIZ_UPGRADE_SQL[$version]) ||
        !is_array($_QUIZ_UPGRADE_SQL[$version])
    ) {
        return true;
    }

    if (
        $_DB_dbms == 'mysql' &&
        isset($_VARS['database_engine']) &&
        $_VARS['database_engine'] == 'InnoDB'
    ) {
        $use_innodb = true;
    } else {
        $use_innodb = false;
    }

    $db = Database::getInstance();

    // Execute SQL now to perform the upgrade
    Log::write('system', Log::INFO, "--Updating Quizzer to version $version");
    foreach ($_QUIZ_UPGRADE_SQL[$version] as $sql) {
        if ($use_innodb) {
            $sql = str_replace('MyISAM', 'InnoDB', $sql);
        }
        Log::write('system', Log::INFO, "Quizzer Plugin $version update: Executing SQL => $sql");
        try {
            $db->conn->executeStatement($sql);
        } catch (\Throwable $e) {
            Log::write('system', Log::ERROR, __FUNCTION__ . ': ' . $e->getMessage());
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

    try {
        Database::getInstance()->conn->executeStatement(
            "UPDATE {$_TABLES['plugins']} SET
            pi_version = ?,
            pi_gl_version = ?,
            pi_homepage = ?
            WHERE pi_name = ?",
            array(
                $_CONF_QUIZ['pi_version'],
                $_CONF_QUIZ['gl_version'],
                $_CONF_QUIZ['pi_url'],
                $_CONF_QUIZ['pi_name'],
            ),
            array(
                Database::STRING,
                Database::STRING,
                Database::STRING,
                Database::STRING,
            )
        );
    } catch (\Throwable $e) {
        Log::write('system', Log::ERROR, __FUNCTION__ . ': ' . $e->getMessage());
        return false;
    }
    return true;
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

