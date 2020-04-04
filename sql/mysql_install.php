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
global $_TABLES, $_SQL, $_QUIZ_UPGRADE_SQL;

$_SQL = array(
'quizzer_quizzes' => "CREATE TABLE {$_TABLES['quizzer_quizzes']} (
  `quizID` varchar(40) NOT NULL DEFAULT '',
  `quizName` varchar(32) NOT NULL DEFAULT '',
  `enabled` tinyint(1) NOT NULL DEFAULT '1',
  `owner_id` mediumint(8) unsigned NOT NULL DEFAULT '2',
  `group_id` mediumint(8) unsigned NOT NULL DEFAULT '1',
  `fill_gid` mediumint(8) unsigned NOT NULL DEFAULT '1',
  `onetime` tinyint(1) NOT NULL DEFAULT '0',
  `introtext` text,
  `introfields` text,
  `questionsAsked` int(2) NOT NULL DEFAULT '0',
  `levels` varchar(255) NOT NULL DEFAULT '0',
  `pass_msg` text,
  `fail_msg` text,
  `reward_id` int(11) unsigned NOT NULL DEFAULT '0',
  `reward_status` tinyint(1) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`quizID`)
) ENGINE=MyISAM",

'quizzer_results' => "CREATE TABLE {$_TABLES['quizzer_results']} (
  `resultID` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `quizID` varchar(40) NOT NULL DEFAULT '',
  `uid` int(11) NOT NULL DEFAULT '0',
  `ts` int(11) unsigned NOT NULL DEFAULT '0',
  `ip` varchar(16) DEFAULT NULL,
  `token` varchar(40) NOT NULL DEFAULT '',
  `introfields` text,
  `asked` int(3) unsigned NOT NULL DEFAULT '0',
  `questions` text,
  PRIMARY KEY (`resultID`)
) ENGINE=MyISAM",

'quizzer_questions' => "CREATE TABLE {$_TABLES['quizzer_questions']} (
  `questionID` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `quizID` varchar(40) NOT NULL DEFAULT '',
  `questionType` varchar(32) NOT NULL DEFAULT 'radio',
  `enabled` tinyint(1) NOT NULL DEFAULT '1',
  `questionText` text,
  `help_msg` varchar(255) DEFAULT '',
  `postAnswerMsg` text,
  `allowPartialCredit` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `randomizeAnswers` tinyint(1) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`questionID`),
  KEY `quiz_id` (`quizID`)
) ENGINE=MyISAM",

'quizzer_answers' => "CREATE TABLE {$_TABLES['quizzer_answers']} (
  `questionID` int(11) unsigned NOT NULL DEFAULT '0',
  `answerID` int(11) unsigned NOT NULL DEFAULT '0',
  `answerText` text,
  `is_correct` tinyint(1) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`questionID`,`answerID`)
) ENGINE=MyISAM",

'quizzer_values' => "CREATE TABLE {$_TABLES['quizzer_values']} (
  `resultID` int(11) unsigned NOT NULL DEFAULT '0',
  `orderby` int(3) unsigned NOT NULL DEFAULT '0',
  `questionID` int(11) unsigned NOT NULL DEFAULT '0',
  `value` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`resultID`,`questionID`),
  UNIQUE KEY `res_q` (`resultID`,`questionID`),
  KEY `res_orderby` (`resultID`,`orderby`)
) ENGINE=MyISAM",

'quizzer_rewards' => "CREATE TABLE {$_TABLES['quizzer_rewards']} (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(80) DEFAULT NULL,
  `type` varchar(20) DEFAULT NULL,
  `config` text,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM",
);

$_QUIZ_UPGRADE_SQL = array(
    '0.0.2' => array(
        "ALTER TABLE {$_TABLES['quizzer_quizzes']} ADD pass_msg TEXT",
    ),
    '0.0.3' => array(
        "ALTER TABLE {$_TABLES['quizzer_results']} ADD `asked` int(3) unsigned NOT NULL DEFAULT '0'",
        "ALTER TABLE {$_TABLES['quizzer_quizzes']} ADD fail_msg TEXT",
        "ALTER TABLE {$_TABLES['quizzer_quizzes']} ADD reward_id int(11) unsigned NOT NULL DEFAULT '0'",
        "ALTER TABLE {$_TABLES['quizzer_quizzes']} ADD reward_status tinyint(1) unsigned NOT NULL DEFAULT '4'",
        "CREATE TABLE {$_TABLES['quizzer_rewards']} (
          `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
          `name` varchar(80) DEFAULT NULL,
          `type` varchar(20) DEFAULT NULL,
          `config` text,
          PRIMARY KEY (`id`)
        ) ENGINE=MyISAM",
        "ALTER TABLE {$_TABLES['quizzer_values']} ADD orderby int(5) unsigned NOT NULL DEFAULT '0' AFTER res_id",
        "ALTER TABLE {$_TABLES['quizzer_values']} ADD key `res_orderby` (`res_id`, `orderby`)",
        "UPDATE {$_TABLES['quizzer_quizzes']} SET onetime = 1 WHERE onetime = 2",
        "ALTER TABLE {$_TABLES['quizzer_quizzes']} CHANGE id quizID varchar(40) NOT NULL DEFAULT ''",
        "ALTER TABLE {$_TABLES['quizzer_quizzes']} CHANGE name quizName varchar(32) NOT NULL DEFAULT ''",
        "ALTER TABLE {$_TABLES['quizzer_quizzes']} CHANGE num_q questionsAsked int(2 unsigned NOT NULL DEFAULT '0'",
        "ALTER TABLE {$_TABLES['quizzer_questions']} CHANGE q_id questionID int(11) unsigned NOT NULL AUTO_INCREMENT DEFAULT '0'",
        "ALTER TABLE {$_TABLES['quizzer_questions']} CHANGE quiz_id quizID varchar(40) NOT NULL DEFAULT ''",
        "ALTER TABLE {$_TABLES['quizzer_questions']} CHANGE type questionType varchar(32) NOT NULL DEFAULT 'radio'",
        "ALTER TABLE {$_TABLES['quizzer_questions']} CHANGE question questionText text",
        "ALTER TABLE {$_TABLES['quizzer_questions']} CHANGE answer_msg postAnswerMsg text",
        "ALTER TABLE {$_TABLES['quizzer_questions']} CHANGE partial_credit allowPartialCredit tinyint(1) unsigned NOT NULL DEFAULT '0'",
        "ALTER TABLE {$_TABLES['quizzer_questions']} CHANGE randomize randomizeAnswers tinyint(1) unsigned NOT NULL DEFAULT '0'",
        "ALTER TABLE {$_TABLES['quizzer_results']} CHANGE res_id resultID int(11) unsigned NOT NULL AUTO_INCREMENT DEFAULT '0'",
        "ALTER TABLE {$_TABLES['quizzer_results']} CHANGE quiz_id quizID varchar(40) NOT NULL DEFAULT ''",
        "ALTER TABLE {$_TABLES['quizzer_results']} CHANGE dt ts int(11) unsigned NOT NULL DEFAULT '0'",
        "ALTER TABLE {$_TABLES['quizzer_values']} CHANGE res_id resultID int(11) unsigned NOT NULL DEFAULT '0'",
        "ALTER TABLE {$_TABLES['quizzer_values']} CHANGE q_id questionID int(11) unsigned NOT NULL DEFAULT '0'",
    ),
);

?>
