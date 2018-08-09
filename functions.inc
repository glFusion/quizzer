<?php
/**
*   Public API functions for the Quizzer plugin for glFusion.
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2010-2017 Lee Garner <lee@leegarner.com>
*   @package    quizzer
*   @version    0.3.1
*   @license    http://opensource.org/licenses/gpl-2.0.php
*               GNU Public License v2 or later
*   @filesource
*/

if (!defined ('GVERSION')) {
    die ('This file can not be used on its own.');
}

/** Load the plugin configuration */
require_once $_CONF['path_system'] . 'classes/config.class.php';
$frm_config = config::get_instance();
$_CONF_QUIZ = $frm_config->get_config('quizzer');

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

spl_autoload_register(function($class)
{
    if (strpos($class, 'Quizzer\\') === 0) {
        COM_errorLog("Loading class $class");
        $class = str_replace('Quizzer\\', '', $class);
        $path = __DIR__ . '/classes/' . $class . '.class.php';
        if (file_exists($path)) {
            include $path;
        }
    }
});


// Import plugin-specific functions
function USES_quizzer_functions()
{
    require_once __DIR__ . '/quizzer_functions.inc.php';
}


/**
*   Called by the plugin Editor to display the current plugin code version.
*   This may be different than the version installed and registered currently.
*   If newer then you may want to run the update.
*
*   @return   string  version number
*/
function plugin_chkVersion_quizzer()
{
    global $_CONF_QUIZ;
    return $_CONF_QUIZ['pi_version'];
}


/**
*   This will put an option for quizzer administration in the command
*   and control area
*   @return   mixed   array(title, url, icon), or false when not allowed
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
* Returns the items for this plugin that should appear on the main menu
*
* NOTE: this MUST return the url/value pairs in the following format
* $<arrayname>[<label>] = <url>
*
* @return    array   Associative array of menu items to be added to menu
*/
function plugin_getmenuitems_quizzer()
{
    global $LANG_QUIZ;

    return array($LANG_QUIZ['start_quiz'] => QUIZ_PI_URL);
}


/**
*   Check if the current user is a plugin admin
*
*   @see    SEC_hasRights()
*   @return boolean     True if an admin, False if not
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
*   Put an option for quizzer management on the admin-only menu.
*
*   @return   mixed   array(title, url, num. banner), or void when not allowed
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
*   Get the user menu option for this plugin.
*   Returns an option to administer the user's quizzer, if they own any.
*
*   @return array   Array of menu items or blank if user has no quizzer
*/
/*function plugin_getuseroption_quizzer()
{
    global $LANG_QUIZ, $_CONF, $_CONF_QUIZ, $_USER, $_GROUPS, $_TABLES;

    $uid = (int)$_USER['uid'];
    if ($uid < 2) return '';
    $groups = implode(',', $_GROUPS);

    $form_cnt = (int)DB_getItem($_TABLES['quizzer_quizzes'], 'COUNT(*)',
                    "owner_id='{$uid}' OR group_id IN ({$groups})");
    if ($form_cnt < 1) return '';

    $menu = array($LANG_QUIZ['menu_title'],
            QUIZ_ADMIN_URL . '/index.php?view=listquizzer',
            $form_cnt);
    return $menu;
}*/


/**
*   Update the Quizzer plugin.
*
*   @return integer Number of message to display (true = generic success msg)
*/
function plugin_upgrade_quizzer()
{
    require_once __DIR__ . '/upgrade.inc.php';
    return QUIZ_do_upgrade();
}


/**
*   Returns the URL of the plugin's icon
*
*   @return   string      URL of the icon
*/
function plugin_geticon_quizzer()
{
    return QUIZ_PI_URL . '/images/quizzer.png';
}


/**
*   Automatic uninstall function for plugins
*
*   This code is automatically uninstalling the plugin.
*   It passes an array to the core code function that removes
*   tables, groups, features and php blocks from the tables.
*   Additionally, this code can perform special actions that cannot be
*   foreseen by the core code (interactions with other plugins for example)
*
*   @return array       Elements to be removed
*/
function plugin_autouninstall_quizzer()
{
    global $_CONF_QUIZ;
    $out = array (
        // give the name of the tables, without $_TABLES[]
        'tables' => array(
                'quizzer_quizzes',
                'quizzer_questions',
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
*   Get additional Javascript into the site header
*
*   @return array List of paths to Javascript files
*/
function plugin_getheaderjs_quizzer()
{
    return array(
        __DIR__ . '/js/toggleEnabled.js',
        __DIR__ . '/js/ajax.js',
    );
}


/**
*   Get additional CSS into the site header.
*   Checks first for a layout-specific version, then a custom plugin version,
*   and finally the default if nothing else found.
*
*   @return array   List of paths to Javascript files
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
*   Create the centerblock.
*
*   @uses   EVLIST_cb_content()
*   @param  string  $where  Position (top, etc.)
*   @param  string  $page   Page currently being displayed
*   @param  string  $topic  Topic currently being displayed
*   @return string          HTML for centerblock
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
    $Q = Quizzer\Quiz::getFirst();
    if (!$Q->isNew) {
        // If the quiz exists, render the question
        $content = COM_siteHeader();
        $content .= $Q->Render();
        $content .= COM_siteFooter();
    }
    return $content;
}


/**
*   Returns a name=>value array of options broken out from the given string.
*
*   @param  mixed   $str    Array, or serialized array, of options
*   @return array           Associative array of option=>value pairs
*/
function QUIZ_getOpts($str)
{
    $A = array();
    if (is_string($str)) {
        // convert to an array
        $A = @unserialize($str);
        if (!$A)
            $A = array();
    } elseif (is_array($str)) {
        // already an array, return unchanged
        $A = $str;
    } else {
        // invalid argument
        $A = NULL;
    }
    return $A;
}


/**
*   Serializes an array of options and returns the resulting string.
*   An empty string is returned on error.
*   Options must already be validated for the type of record to be saved.
*
*   @param  array   $A      Array of name=>value pairs
*   @return string          Serialized string representing the array
*/
function QUIZ_setOpts($A)
{
    $str = '';
    if (is_array($A)) {
        $str = @serialize($A);
        if (!$str) $str = '';
    }
    return $str;
}


/**
*   Get the URL to the localized configuration help file.
*
*   @return array   Array of (url, window_type)
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
*   Get the link to a documentation file.
*   Returns the URL to the file, or to the directory if no file requested.
*
*   @see    plugin_getconfigelementhelp_subscription()
*   @param  string  $file       Optional HTML filename
*   @param  string  $doclang    Documentation language, defaults to english
*   @return string              URL to document directory or file
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
*   Support merging local accounts into remote accounts
*
*   @param  integer $origUID    Original (remote) user ID
*   @param  integer $destUID    Merged (local) user ID
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
*   Select group for "default user group" config variable
*
*   @uses   QUIZ_GetGroups()
*   @return array Array of groups
*/
function plugin_configmanager_select_fill_gid_quizzer()
{
    global $_CONF_QUIZ;
    return FRM_GetGroups($_CONF_QUIZ['fill_gid']);
}


/**
*   Create an array of groups.
*
*   @param  boolean $nogroup    True if "No Group" is an option.
*   @return array               Array of groups as name=>ID
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
*   Helper function to get a template object.
*
*   @param  string  $name   Base name of template file, e.g. "editor"
*   @param  string  $id     ID of template, e.g. "editform"
*   @param  string  $path   Optional path if not "templates/"
*   @return string      Full filename, e.g. "editor.uikit.thtml"
*/
function QUIZ_getTemplate($name, $id='', $path='')
{
    global $_SYSTEM;

    // Put parameters into an array if not already.
    if (!is_array($name) && !empty($id)) {
        $templates = array($id => $name);
    } elseif (is_array($name)) {
        $templates = $name;
    }

    // Add additional path info if needed
    if (substr($path, 0, 9) != 'templates') {
        $path = 'templates/' . $path;
    }

    // May use "switch" later, for now there are only two options
    $tpltype = $_SYSTEM['framework'] == 'uikit' ? '.uikit' : '';
    foreach ($templates as $t_id =>$t_name) {
        if (file_exists(__DIR__ . "/$path/$t_name$tpltype.thtml")) {
            $templates[$t_id] = "$t_name$tpltype.thtml";
        } else {
            $templates[$t_id] = "$t_name.thtml";
        }
    }
    $T = new Template(__DIR__ . '/' . $path);
    $T->set_file($templates);
    return $T;
}


/**
*   Get an array variable, first checking whether it's set
*
*   @param  array   $A          Array where var should be
*   @param  string  $varname    Name of array element
*   @param  string  $type       Type of return value
*   @param  mixed   $default    Default value, if any
*   @return     Array element or NULL, cast to $type
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

$_CONF_QUIZ['_is_uikit'] = $_SYSTEM['framework'] == 'uikit' ? true : false;
$_CONF_QUIZ['_iconset'] = $_CONF_QUIZ['_is_uikit'] ? 'uk-icon' : 'fa fa';

?>
