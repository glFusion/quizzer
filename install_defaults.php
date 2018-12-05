<?php
/**
 * Configuration defaults for the Quizzer plugin.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2018 Lee Garner <lee@leegarner.com>
 * @package     quizzer
 * @version     v0.0.1
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */

if (!defined('GVERSION')) {
    die('This file can not be used on its own!');
}

/**
 * Default settings for the Quizzer plugin.
 *
 * Initial Installation Defaults used when loading the online configuration
 * records. These settings are only used during the initial installation
 * and not referenced any more once the plugin is installed
 *
 * @global  array   $quizzerConfigData;
 */
global $quizzerConfigData;
$quizzerConfigData = array(
    array(
        'name' => 'sg_main',
        'default_value' => NULL,
        'type' => 'subgroup',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => NULL,
        'sort' => 0,
        'set' => true,
        'group' => 'quizzer',
    ),
    array(
        'name' => 'fs_main',
        'default_value' => NULL,
        'type' => 'fieldset',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => NULL,
        'sort' => 0,
        'set' => true,
        'group' => 'quizzer',
    ),
    array(
        'name' => 'centerblock',
        'default_value' => 0,
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 3,
        'sort' => 10,
        'set' => true,
        'group' => 'quizzer',
    ),
    array(
        'name' => 'fill_gid',
        'default_value' => 13,
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 0,     // uses helper function
        'sort' => 20,
        'set' => true,
        'group' => 'quizzer',
    ),
);


/**
 * Initialize Quizzer plugin configuration.
 * Creates the database entries for the configuation if they don't exist.
 *
 * @param   integer $group_id   Group ID to use as the plugin's admin group
 * @return  boolean             true: success; false: an error occurred
 */
function plugin_initconfig_quizzer($group_id = 0)
{
    global $quizzerConfigData;

    $c = config::get_instance();
    if (!$c->group_exists('quizzer')) {
        USES_lib_install();
        foreach ($quizzerConfigData AS $cfgItem) {
            _addConfigItem($cfgItem);
        }
    }
    return true;
}


/**
 * Sync the configuration in the DB to the above configs.
 */
function plugin_updateconfig_quizzer()
{
    global $quizzerConfigData;

    USES_lib_install();
    _update_config('quizzer', $quizzerConfigData);
}

?>
