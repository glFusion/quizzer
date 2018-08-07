<?php
/**
*   Table definitions for the Quizzer plugin
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2018 Lee Garner <lee@leegarner.com>
*   @package    quizzer
*   @version    0.0.1
*   @license    http://opensource.org/licenses/gpl-2.0.php
*               GNU Public License v2 or later
*   @filesource
*/

/** @global array $_TABLES */
global $_TABLES;

$_SQL = array(
    'quizzer_quizzes' => "CREATE TABLE {$_TABLES['quizzer_quizzes']} (
        `id` varchar(40) NOT NULL DEFAULT '',
        `name` varchar(32) NOT NULL,
        `enabled` tinyint(1) NOT NULL DEFAULT '1',
        `owner_id` mediumint(8) unsigned NOT NULL DEFAULT '2',
        `group_id` mediumint(8) unsigned NOT NULL DEFAULT '1',
        `fill_gid` mediumint(8) unsigned NOT NULL DEFAULT '1',
        `onetime` tinyint(1) NOT NULL DEFAULT '0',
        `introtext` text DEFAULT '',
        `introfields` text DEFAULT '',
        PRIMARY KEY (`id`)
    )",

    'quizzer_results' => "CREATE TABLE {$_TABLES['quizzer_results']} (
        `results_id` int(11) NOT NULL AUTO_INCREMENT,
        `quiz_id` varchar(40) NOT NULL DEFAULT '',
        `instance_id` varchar(60),
        `uid` int(11) NOT NULL DEFAULT '0',
        `dt` int(11) NOT NULL DEFAULT '0',
        `approved` tinyint(1) DEFAULT '1',
        `ip` varchar(16) DEFAULT NULL,
        `token` varchar(40) NOT NULL DEFAULT '',
        PRIMARY KEY (`results_id`)
    )",

    'quizzer_questions' => "CREATE TABLE {$_TABLES['quizzer_questions']} (
        `q_id` int(11) NOT NULL AUTO_INCREMENT,
        `quiz_id` varchar(40) NOT NULL DEFAULT '',
        `type` varchar(32) NOT NULL DEFAULT 'radio',
        `enabled` tinyint(1) NOT NULL DEFAULT '1',
        `question` text,
        `help_msg` varchar(255) DEFAULT '',
        PRIMARY KEY (`q_id`)
    )",

    'quizzer_answers' => "CREATE TABLE {$_TABLES['quizzer_answers']} (
        `a_id` int(11) NOT NULL AUTO_INCREMENT,
        `q_id` int(11) NOT NULL,
        `value` text,
        `correct` tinyint(1) unsigned NOT NULL DEFAULT 0,
        PRIMARY KEY (`a_id`)
    )",

    'quizzer_values' => "CREATE TABLE {$_TABLES['quizzer_values']} (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `results_id` int(11) NOT NULL DEFAULT '0',
        `q_id` int(11) NOT NULL,
        `value` varchar(255) DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `res_q` (`results_id`,`q_id`)
    )",
);

?>
