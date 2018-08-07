<?php
/**
*   Plugin-specific functions for the quizzer plugin
*   Load by calling USES_quizzer_functions()
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2010-2011 Lee Garner <lee@leegarner.com>
*   @package    quizzer
*   @version    0.2.3
*   @license    http://opensource.org/licenses/gpl-2.0.php 
*               GNU Public License v2 or later
*   @filesource
*/

/**
*   Display the results for a given form in tabular format.
*
*   @param  string  $frm_id     ID of form to display
*   @param  mixed   $fieldlist  Normal user- array of field names, false for all
*                               Admin user- true, always gets all fields
*   @param  string  $instance_id    Specific instance ID, e.g. story ID
*   @return string              HTML for form results table
*/
function QUIZ_ResultsTable($quiz_id, $fieldlist=false, $instance_id = '')
{
    global $_TABLES, $_USER, $_GROUPS;

    $retval = '';
    $fields = array();

    // Instantiate the form to verify View Results access
    $Q = new Quizzer\Quiz($quiz_id, QUIZ_ACCESS_VIEW);

    // Return nothing if the form is invalid (e.g. no access)
    if ($Q->id == '' || !$Q->access)
        return '';

    // Get the form results. We've already verified this user's access to
    // the form by instantiating it.
    $sql = "SELECT * FROM {$_TABLES['quizzer_results']} 
            WHERE quiz_id='$quiz_id'
            ORDER BY dt ASC";
    //echo $sql;die;
    $res = DB_query($sql, 1);
    if (DB_numRows($res) < 1)
        return '';          // Nothing to show

    if ($fieldlist === true) {
        $T = new \Template(__DIR__ . '/templates/admin');
        $isAdmin = true;
    } else {
        $T = new \Template(__DIR__ . '/templates');
        $isAdmin = false;
        if (is_array($fieldlist)) {
            $fields = $fieldlist;
        }
    }

    $T->set_file('formresults', 'results.thtml');
    $T->set_var(array(
        'quiz_id'    => $Q->id,
        'quiz_name'  => $Q->name,
        'isAdmin'   => $isAdmin,
    ) );

    // Create the table headers
    $T->set_block('formresults', 'Headers', 'header');

    // Go through the fields and unset any that shouldn't be shown in the
    // results table, based on type and permissions
    foreach ($Frm->fields as $fldname=>$Fld) {
        $show_field = true;     // assume it will be shown

        if (!$Fld->enabled || $Fld->type == 'static') {
            $show_field = false;
        } elseif (!empty($fields) && !in_array($Fld->name, $fields)) {
            // If we have a field list, and this isn't in it, block it.
            $show_field = false;
        } elseif ($Fld->results_gid != $Frm->results_gid &&
                !in_array($Fld->results_gid, $_GROUPS)) {
            // If the user doesn't have permission to see this result, block it.
            // The form's permission has already been checked.
            $show_field = false;
        }

        // If field is ok to show, then set it's header.  Otherwise, unset it
        // which will also remove it from the loop that follows.
        if ($show_field) {
            $T->set_var('fld_name', 
                    $Fld->prompt == '' ? $Fld->name : $Fld->prompt);
            $T->parse('header', 'Headers', true);
        } else {
            unset($Frm->fields[$fldname]);
        }
    }

    // Create each data row
    $T->set_block('formresults', 'DataRows', 'dataRow');
    while ($A = DB_fetchArray($res, false)) {
        $R = new Result($A);
        $R->GetValues($Frm->fields);

        // Admins always see the submitter & date, others only if requested
        if ($isAdmin) {
            $T->set_var('res_id', $R->id);
        }
        if ($isAdmin || $fieldlist == 'all' || in_array('res_user', $fields)) {
            $T->set_var('res_user', COM_getDisplayName($R->uid));
        }
        if ($isAdmin || $fieldlist == 'all' || in_array('res_date', $fields)) {
            $T->set_var('res_date', strftime('%Y-%m-%d %H:%M', $R->dt));
        }

        $T->set_block('formresults', 'Fields', 'fldData');
        foreach ($Frm->fields as $Fld) {
            //$Fld->GetValue($R->id);
            //$T->set_var('fld_value', htmlspecialchars($Fld->value_text));
            $T->set_var('fld_value', $Fld->displayValue($Frm->fields));
            $T->parse('fldData', 'Fields', true);
        }

        $T->parse('dataRow', 'DataRows', true);
        $T->clear_var('fldData');
    }
    $T->parse('output', 'formresults');
    $retval .= $T->finish($T->get_var('output'));
    return $retval;
}


function QUIZ_UserDropdown($sel=0, $users='', $not='')
{
    global $_TABLES;

    $sel = (int)$sel;

    $sql = "SELECT uid,username
            FROM {$_TABLES['users']} 
            WHERE uid <> 1 ";
    if ($users != '') {
        $not = $not == '' ? '' : 'NOT ';
        $sql .= "AND uid $not IN (" . 
                DB_escapeString($users). ") ";
    }
    $sql .= " ORDER BY username ASC";
    $result = DB_query($sql);
    if (!$result)
        return '';

    $retval = '';
    while ($row = DB_fetcharray($result)) {
        $selected = $row['uid'] == $sel ? ' selected' : '';
        $retval .= "<option value=\"{$row['uid']}\" $selected>{$row['username']}</option>\n";
    }
    return $retval;

}


function QUIZ_GroupDropdown($group_id, $access)
{
    global $_TABLES;

    $groupdd = '';

    if ($access == 3) {
        $usergroups = SEC_getUserGroups();

        foreach ($usergroups as $ug_name => $ug_id) {
            $groupdd .= '<option value="' . $ug_id . '"';
            if ($group_id == $ug_id) {
                $groupdd .= ' selected="selected"';
            }
            $groupdd .= '>' . $ug_name . '</option>' . LB;
        }
    } else {
        // They can't set the group then
        $groupdd .= DB_getItem ($_TABLES['groups'], 'grp_name',
                                "grp_id = '".DB_escapeString($group_id)."'")
                 . '<input type="hidden" name="group_id" value="' . $group_id
                 . '" />';
    }

    return $groupdd;
}


/**
*   Show the site header, with or without left blocks according to config.
*
*   @see    COM_siteHeader()
*   @param  string  $subject    Text for page title (ad title, etc)
*   @param  string  $meta       Other meta info
*   @return string              HTML for site header
*/
function QUIZ_siteHeader($subject='', $meta='', $blocks = -1)
{
    global $_CONF_QUIZ, $LANG_QUIZ;

    $retval = '';

    $blocks = $blocks > -1 ? $blocks : $_CONF_QUIZ['displayblocks'];
    switch($blocks) {
    case 2:     // right only
    case 0:     // none
        $retval .= COM_siteHeader('none', $subject, $meta);
        break;

    case 1:     // left only
    case 3:     // both
    default :
        $retval .= COM_siteHeader('menu', $subject, $meta);
        break;
    }
    return $retval;
}


/**
*   Show the site footer, with or without right blocks according to config.
*   If zero is given as an argument, then COM_siteFooter() is called to
*   finish output but is not displayed. This is so a popup form will not have
*   the complete site content but only the form.
*
*   @see    COM_siteFooter()
*   @param  integer $blocks Zero to hide sitefooter
*   @return string          HTML for site header
*/
function QUIZ_siteFooter($blocks = -1)
{
    global $_CONF_QUIZ, $_CONF;

    $retval = '';

    if ($blocks == 0) {
        // Run siteFooter to finish the page, but return nothing
        COM_siteFooter();
        return;
    }

    if ($_CONF['show_right_blocks']) {
        $retval .= COM_siteFooter(true);
        return $retval;
    }
    $blocks = $blocks > -1 ? $blocks : $_CONF_QUIZ['displayblocks'];
    switch($blocks) {
    case 2 : // right only
    case 3 : // left and right
        $retval .= COM_siteFooter(true);
        break;

    case 0: // none
    case 1: // left only
    default :
        $retval .= COM_siteFooter();
        break;
    }
    return $retval;
}

?>
