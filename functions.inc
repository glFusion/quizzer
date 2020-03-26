<?php
/**
 * Public API functions for the Quizzer plugin for glFusion.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2018-2020 Lee Garner <lee@leegarner.com>
 * @package     quizzer
 * @version     v0.0.3
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */

if (!defined ('GVERSION')) {
    die ('This file can not be used on its own.');
}

/** Load the plugin configuration */
global $_CONF_QUIZ;
$_CONF_QUIZ = config::get_instance()->get_config('quizzer');

/** Include static plugin config values */
require_once 'quizzer.php';

/** Define the absolute path to the plugin's directory */
define('QUIZ_PI_PATH', __DIR__);
/** Define the base public url for the plugin */
define('QUIZ_PI_URL', $_CONF['site_url'] . '/' . $_CONF_QUIZ['pi_name']);
/** Define the administration url for the plugin */
define('QUIZ_ADMIN_URL',
    $_CONF['site_admin_url'] . '/plugins/' . $_CONF_QUIZ['pi_name']);

define('QUIZ_ACCESS_FILL', 1);   // access to fill out form
define('QUIZ_ACCESS_VIEW', 2);   // access to view results
define('QUIZ_ACCESS_ADMIN', 4);  // admin access

if (file_exists(__DIR__ . '/config.php')) {
/** Include local config values and overrides */
    include_once __DIR__ . '/config.php';
}

/** Determine the correct language file, falling back to English as default. */
$langpath = __DIR__ . '/language';
$langfile = $_CONF['language'] . '.php';
if (!file_exists("$langpath/$langfile")) {
    $langfile = 'english.php';
}

/** Include plugin language file */
require_once "$langpath/$langfile";
if (file_exists("$langpath/custom/$langfile")) {
    /** Include custom language file, if any */
    include_once "$langpath/custom/$langfile";
}

spl_autoload_register(function ($class)
{
    // project-specific namespace prefix
    $prefix = 'Quizzer\\';

    // base directory for the namespace prefix
    $base_dir = __DIR__ . '/classes/';

    // does the class use the namespace prefix?
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        // no, move to the next registered autoloader
        return;
    }

    // get the relative class name
    $relative_class = substr($class, $len);

    // replace the namespace prefix with the base directory, replace namespace
    // separators with directory separators in the relative class name, append
    // with .php
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.class.php';

    // if the file exists, require it
    if (file_exists($file)) {
        require $file;
    }
});

/**
 * Called by the plugin Editor to display the current plugin code version.
 * This may be different than the version installed and registered currently.
 * If newer then you may want to run the update.
 *
 * @return  string  Version number
 */
function plugin_chkVersion_quizzer()
{
    global $_CONF_QUIZ;
    return $_CONF_QUIZ['pi_version'];
}


/**
 * This will put an option for quizzer administration in the command and control area.
 *
 * @return  mixed   array(title, url, icon), or false when not allowed
 */
function plugin_cclabel_quizzer()
{
    global $LANG_QUIZ;

    if (plugin_isadmin_quizzer()) {
        return array($LANG_QUIZ['menu_title'],
                QUIZ_ADMIN_URL . '/index.php',
                plugin_geticon_quizzer());
    } else {
        return false;
    }
}


/**
 * Returns the items for this plugin that should appear on the main menu.
 *
 * NOTE: this MUST return the url/value pairs in the following format
 * $<arrayname>[<label>] = <url>
 *
 * @return  array   Associative array of menu items to be added to menu
 */
function plugin_getmenuitems_quizzer()
{
    global $LANG_QUIZ;

    return array($LANG_QUIZ['start_quiz'] => QUIZ_PI_URL);
}


/**
 * Check if the current user is a plugin admin.
 *
 * @uses    SEC_hasRights()
 * @return  boolean     True if an admin, False if not
 */
function plugin_isadmin_quizzer()
{
    static $retval = NULL;
    if ($retval === NULL) {
        $retval = SEC_hasRights('quizzer.admin');
    }
    return $retval;
}


/**
 * Put an option for quizzer management on the admin-only menu.
 *
 * @return  mixed   array(title, url, num. banner), or void when not allowed
 */
function plugin_getadminoption_quizzer()
{
    global  $LANG_QUIZ;

    if (plugin_isadmin_quizzer()) {
        return array ($LANG_QUIZ['menu_title'],
                QUIZ_ADMIN_URL . '/index.php',
                );
    } else {
        return false;
    }
}


/**
 * Update the Quizzer plugin.
 *
 * @param   boolean $dvlp   True if errors should be ignored
 * @return  boolean     True on success, False on failure
 */
function plugin_upgrade_quizzer($dvlp=false)
{
    require_once __DIR__ . '/upgrade.inc.php';
    return QUIZ_do_upgrade($dvlp);
}


/**
 * Returns the URL of the plugin's icon.
 *
 * @return  string      URL of the icon
 */
function plugin_geticon_quizzer()
{
    return QUIZ_PI_URL . '/images/quizzer.png';
}


/**
 * Automatic uninstall function for plugins.
 *
 * This code is automatically uninstalling the plugin.
 * It passes an array to the core code function that removes
 * tables, groups, features and php blocks from the tables.
 * Additionally, this code can perform special actions that cannot be
 * foreseen by the core code (interactions with other plugins for example)
 *
 * @return  array       Elements to be removed
 */
function plugin_autouninstall_quizzer()
{
    global $_CONF_QUIZ;
    $out = array (
        // give the name of the tables, without $_TABLES[]
        'tables' => array(
            'quizzer_quizzes',
            'quizzer_questions',
            'quizzer_answers',
            'quizzer_values',
            'quizzer_results',
        ),
        // give the full name of the group, as in the db
        'groups' => array('quizzer Admin'),
        // give the full name of the feature, as in the db
        'features' => array('quizzer.admin'),
        // give the full name of the block, including 'phpblock_', etc
        'php_blocks' => array(),
        // give all vars with their name
        'vars'=> array()
    );
    return $out;
}


/**
 * Run scheduled tasks.
 * - Delete old carts that aren't assigned to any user.
 * - Call PLG_itemDeleted to notify plugins that an item is not available
 */
function plugin_runScheduledTask_quizzer()
{
    global $_CONF_QUIZ;

    if ($_CONF_QUIZ['purge_null_results'] > 0) {
        Quizzer\Result::purgeNulls();
    }
}


/**
 * Get additional Javascript into the site header.
 *
 * @return  array   List of paths to Javascript files
 */
function plugin_getheaderjs_quizzer()
{
    return array(
        __DIR__ . '/js/toggleEnabled.js',
        __DIR__ . '/js/ajax.js',
    );
}


/**
 * Get additional CSS into the site header.
 * Checks first for a layout-specific version, then a custom plugin version,
 * and finally the default if nothing else found.
 *
 * @return  array   List of paths to Javascript files
 */
function plugin_getheadercss_quizzer()
{
    global $_CONF, $_CONF_QUIZ;

    $styles = array();
    $files = array('style.css');

    $layoutpath = $_CONF['path_layout'].'plugins/'.$_CONF_QUIZ['pi_name'].'/';
    foreach ($files as $file) {
        if (@file_exists($layoutpath . $file)) {
            // check for custom layout first
            $styles[] = $layoutpath . $file;
        } elseif (@file_exists(__DIR__ . '/css/custom/' . $file)) {
            // then custom plugin style
            $styles[] = __DIR__ . '/css/custom/' . $file;
        } else {
            // finally use the default plugin style
            $styles[] = __DIR__ . '/css/' . $file;
        }
    }
    return $styles;
}


/**
 * Create the centerblock.
 *
 * @param   string  $where  Current position (top, etc.)
 * @param   string  $page   Page currently being displayed
 * @param   string  $topic  Topic currently being displayed
 * @return  string          HTML for centerblock
 */
function plugin_centerblock_quizzer($where, $page, $topic = '')
{
    global $_CONF_QUIZ;

    // If we show only on the homepage, check if that's where we are
    // If a topic is being displayed, then we're not on the homepage
    if ($_CONF_QUIZ['centerblock'] == 0 ||
            ($page > 1 || !empty($topic)) ) {
        return '';
    }

    $content = '';
    $Q = \Quizzer\Quiz::getFirst();
    if (!$Q->isNew) {
        // If the quiz exists, render the question
        $content = COM_siteHeader();
        $content .= $Q->Render();
        $content .= COM_siteFooter();
    }
    return $content;
}


/**
 * Get the URL to the localized configuration help file.
 *
 * @param   string  $option     Config element ID to retrieve
 * @param   string  $doclang    Language, default = English
 * @return  array   Array of (url, window_type)
 */
function plugin_getconfigelementhelp_quizzer($option, $doclang='english')
{
    if (version_compare(GVERSION, '1.6.0', '<')) {
        $file = 'config.legacy.html';
    } else {
        $file = 'config.html';
    }
    $doc_url = QUIZ_getDocURL($file, $doclang);
    if (empty($doc_url))
        return false;

    if (!empty($option)) {
        $doc_url .= '#desc_' . $option;
    }

    return array($doc_url, 0);
}


/**
 * Get the link to a documentation file.
 * Returns the URL to the file, or to the directory if no file requested.
 *
 * @see     plugin_getconfigelementhelp_quizzer()
 * @param   string  $file       Optional HTML filename
 * @param   string  $doclang    Documentation language, defaults to english
 * @return  string              URL to document directory or file
 */
function QUIZ_getDocURL($file = '', $doclang='')
{
    global $_CONF, $_CONF_QUIZ;

    if ($doclang == '') $doclang = $_CONF['language'];
    $doc_path = $_CONF['path_html'] . $_CONF_QUIZ['pi_name'] .
                "/docs/$doclang/";
    $doc_url = '';

    if ($file != '') {
        if (!is_file($doc_path . $file)) {
            $doclang = 'english';
        }
        $doc_url = QUIZ_PI_URL . "/docs/$doclang/$file";
    } else {
        if (!is_dir($doc_path)) {
            $doclang = 'english';
        }
        $doc_url = QUIZ_PI_URL . "/docs/$doclang/";
    }

    return $doc_url;
}


/**
 * Support merging local accounts into remote accounts.
 * Chages the user ID in the results table.
 *
 * @param   integer $origUID    Original (remote) user ID
 * @param   integer $destUID    Merged (local) user ID
 */
function plugin_user_move_quizzer($origUID, $destUID)
{
    global $_TABLES;

    $origUID = (int)$origUID;
    $destUID = (int)$destUID;

    DB_query("UPDATE {$_TABLES['quizzer_results']}
            SET uid = $destUID WHERE uid = $origUID", 1);
}


/**
 * Select group for "default user group" config variable.
 *
 * @uses    QUIZ_GetGroups()
 * @return  array Array of groups
 */
function plugin_configmanager_select_fill_gid_quizzer()
{
    global $_CONF_QUIZ;
    return QUIZ_GetGroups($_CONF_QUIZ['fill_gid']);
}


/**
 * Create an array of groups.
 *
 * @param   integer $selected   ID of selected group
 * @return  array               Array of groups as name=>ID
 */
function QUIZ_GetGroups($selected=0)
{
    global $_TABLES;

    $A = array();

    $query = "SELECT grp_id, grp_name FROM {$_TABLES['groups']}";
    $result = DB_query($query, 1);
    if ($result && DB_numRows($result) > 0) {
        while ($row = DB_fetchArray($result)) {
            $row['grp_name'] = htmlspecialchars($row['grp_name']);
            $A[$row['grp_name']] = $row['grp_id'];
        }
    }
    return $A;
}


/**
 * Get an array variable, first checking whether it's set.
 *
 * @param   array   $A          Array where var should be
 * @param   string  $varname    Name of array element
 * @param   string  $type       Type of return value
 * @param   mixed   $default    Default value, if any
 * @return      Array element or NULL, cast to $type
 */
function QUIZ_getVar($A, $varname, $type = 'string', $default = NULL)
{
    if (!isset($A[$varname])) {
        return $default;
    } else {
        $val = $A[$varname];
    }
    switch ($type) {
    case 'int':
    case 'integer':
        return (int)$val;
        break;
    case 'bool':
        return (bool)$val;
        break;
    case 'float':
        return (float)$val;
        break;
    case 'array':
        if ($val === NULL) {
            $val = array();
        } elseif (!is_array($val)) {
            $val = array($val);
        }
        return $val;
        break;
    case 'string':
    default:
        return (string)$val;
        break;
    }
}

?>
