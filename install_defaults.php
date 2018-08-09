<?php
/**
*   Configuration defaults for the Quizzer plugin
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2018 Lee Garner <lee@leegarner.com>
*   @package    quizzer
*   @version    0.0.1
*   @license    http://opensource.org/licenses/gpl-2.0.php 
*               GNU Public License v2 or later
*   @filesource
*/

if (!defined('GVERSION')) {
    die('This file can not be used on its own!');
}

/**
 *  Default settings for the Quizzer plugin.
 *
 *  Initial Installation Defaults used when loading the online configuration
 *  records. These settings are only used during the initial installation
 *  and not referenced any more once the plugin is installed
 *
 *  @global array $_QZ_DEFAULT;
 *
 */
global $_QZ_DEFAULT, $_CONF_QUIZ;
$_QZ_DEFAULT = array(
    'fill_gid'      => 13,  // logged-in users
    'centerblock'   => 0,
);

/**
 *  Initialize Profile plugin configuration
 *
 *  Creates the database entries for the configuation if they don't already
 *  exist. Initial values will be taken from $_CONF_QUIZ if available (e.g. from
 *  an old config.php), uses $_QZ_DEFAULT otherwise.
 *
 *  @param  integer $group_id   Group ID to use as the plugin's admin group
 *  @return boolean             true: success; false: an error occurred
 */
function plugin_initconfig_quizzer($group_id = 0)
{
    global $_CONF, $_CONF_QUIZ, $_QZ_DEFAULT;

    $c = config::get_instance();
    if (!$c->group_exists($_CONF_QUIZ['pi_name'])) {

        $c->add('sg_main', NULL, 'subgroup', 0, 0, NULL, 0, true, $_CONF_QUIZ['pi_name']);

        $c->add('fs_main', NULL, 'fieldset', 0, 0, NULL, 0, true, $_CONF_QUIZ['pi_name']);
        $c->add('centerblock', $_QZ_DEFAULT['centerblock'],
                'select', 0, 0, 3, 10, true, $_CONF_QUIZ['pi_name']);
        $c->add('fill_gid', $_QZ_DEFAULT['fill_gid'],
                'select', 0, 0, 0, 20, true, $_CONF_QUIZ['pi_name']);
    }

    return true;
}

?>
