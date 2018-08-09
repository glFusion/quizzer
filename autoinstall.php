<?php
/**
*   Provides automatic installation of the Quizzer plugin.
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2010 Lee Garner <lee@leegarner.com>
*   @package    quizzer
*   @version    0.0.1
*   @license    http://opensource.org/licenses/gpl-2.0.php
*               GNU Public License v2 or later
*   @filesource
*/

if (!defined ('GVERSION')) {
    die ('This file can not be used on its own.');
}

/** @global string $_DB_dbms */
global $_DB_dbms;

/** Include plugin functions */
require_once __DIR__ . '/functions.inc';
/** Include database definitions */
require_once __DIR__ . '/sql/' . $_DB_dbms . '_install.php';

/** Plugin installation options
*   @global array $INSTALL_plugin['quizzer']
*/
$INSTALL_plugin['quizzer'] = array(
    'installer' => array('type' => 'installer',
            'version' => '1',
            'mode' => 'install'),

    'plugin' => array('type' => 'plugin',
            'name'      => $_CONF_QUIZ['pi_name'],
            'ver'       => $_CONF_QUIZ['pi_version'],
            'gl_ver'    => $_CONF_QUIZ['gl_version'],
            'url'       => $_CONF_QUIZ['pi_url'],
            'display'   => $_CONF_QUIZ['pi_display_name']),

    array('type' => 'table',
            'table'     => $_TABLES['quizzer_quizzes'],
            'sql'       => $_SQL['quizzer_quizzes']),

    array('type' => 'table',
            'table'     => $_TABLES['quizzer_questions'],
            'sql'       => $_SQL['quizzer_questions']),

    array('type' => 'table',
            'table'     => $_TABLES['quizzer_results'],
            'sql'       => $_SQL['quizzer_results']),

    array('type' => 'table',
            'table'     => $_TABLES['quizzer_values'],
            'sql'       => $_SQL['quizzer_values']),

   array('type' => 'group',
            'group' => 'quizzer Admin',
            'desc' => 'Users in this group can administer the Quizzer plugin',
            'variable' => 'admin_group_id',
            'admin' => true,
            'addroot' => true),

    array('type' => 'feature',
            'feature' => 'quizzer.admin',
            'desc' => 'Quizzer Administration access',
            'variable' => 'admin_feature_id'),

    array('type' => 'mapping',
            'group' => 'admin_group_id',
            'feature' => 'admin_feature_id',
            'log' => 'Adding Admin feature to the admin group'),
);

/**
* Puts the datastructures for this plugin into the glFusion database
* Note: Corresponding uninstall routine is in functions.inc
* @return   boolean True if successful False otherwise
*/
function plugin_install_quizzer()
{
    global $INSTALL_plugin, $_CONF_QUIZ;

    COM_errorLog("Attempting to install the {$_CONF_QUIZ['pi_display_name']} plugin", 1);

    $ret = INSTALLER_install($INSTALL_plugin[$_CONF_QUIZ['pi_name']]);
    if ($ret > 0) {
        return false;
    }

    return true;
}


/**
*   Loads the configuration records for the Online Config Manager
*
*   @return   boolean     true = proceed with install, false = an error occured
*/
function plugin_load_configuration_quizzer()
{
    global $_CONF, $_CONF_QUIZ, $_TABLES;

    require_once __DIR__ . '/install_defaults.php';

    // Get the admin group ID that was saved previously.
    $group_id = (int)DB_getItem($_TABLES['groups'], 'grp_id',
            "grp_name='{$_CONF_QUIZ['pi_name']} Admin'");
    return plugin_initconfig_quizzer($group_id);
}

?>
