<?php
/**
*   Table definitions and other static config variables.
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2010-2018 Lee Garner <lee@leegarner.com>
*   @package    quizzer
*   @version    0.4.0
*   @license    http://opensource.org/licenses/gpl-2.0.php 
*               GNU Public License v2 or later
*   @filesource
*/

/**
*   Global array of table names from glFusion
*   @global array $_TABLES
*/
global $_TABLES;

/**
*   Global table name prefix
*   @global string $_DB_table_prefix
*/
global $_DB_table_prefix;

$_TABLES['quizzer_quizzes']    = $_DB_table_prefix . 'quizzer_quizzes';
$_TABLES['quizzer_questions']    = $_DB_table_prefix . 'quizzer_questions';
$_TABLES['quizzer_answers']    = $_DB_table_prefix . 'quizzer_answers';
$_TABLES['quizzer_results']   = $_DB_table_prefix . 'quizzer_results';
$_TABLES['quizzer_values']   = $_DB_table_prefix . 'quizzer_values';

/**
*   Global configuration array
*   @global array $_CONF_QUIZ
*/
global $_CONF_QUIZ;
$_CONF_QUIZ['pi_name']           = 'quizzer';
$_CONF_QUIZ['pi_version']        = '0.0.1';
$_CONF_QUIZ['gl_version']        = '1.7.0';
$_CONF_QUIZ['pi_url']            = 'http://www.leegarner.com';
$_CONF_QUIZ['pi_display_name']   = 'Quizzer';

?>
