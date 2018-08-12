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
  `introtext` text,
  `introfields` text,
  `num_q` int(2) unsigned NOT NULL DEFAULT '0',
  `levels` varchar(255) NOT NULL DEFAULT '0',
  `pass_msg` text,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM",

'quizzer_results' => "CREATE TABLE {$_TABLES['quizzer_results']} (
  `res_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `quiz_id` varchar(40) NOT NULL DEFAULT '',
  `uid` int(11) NOT NULL DEFAULT '0',
  `dt` int(11) NOT NULL DEFAULT '0',
  `ip` varchar(16) DEFAULT NULL,
  `token` varchar(40) NOT NULL DEFAULT '',
  `introfields` text,
  `asked` int(3) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`res_id`)
) ENGINE=MyISAM",

'quizzer_questions' => "CREATE TABLE {$_TABLES['quizzer_questions']} (
  `q_id` int(11) NOT NULL AUTO_INCREMENT,
  `quiz_id` varchar(40) NOT NULL DEFAULT '',
  `type` varchar(32) NOT NULL DEFAULT 'radio',
  `enabled` tinyint(1) NOT NULL DEFAULT '1',
  `question` text,
  `help_msg` varchar(255) DEFAULT '',
  `answer_msg` text,
  PRIMARY KEY (`q_id`)
) ENGINE=MyISAM",

'quizzer_answers' => "CREATE TABLE {$_TABLES['quizzer_answers']} (
  `q_id` int(11) NOT NULL,
  `a_id` int(11) unsigned NOT NULL DEFAULT '0',
  `value` text,
  `correct` tinyint(1) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`q_id`,`a_id`)
) ENGINE=MyISAM",

'quizzer_values' => "CREATE TABLE {$_TABLES['quizzer_values']} (
  `res_id` int(11) unsigned NOT NULL DEFAULT '0',
  `q_id` int(11) unsigned NOT NULL DEFAULT '0',
  `value` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`res_id`,`q_id`),
  UNIQUE KEY `res_q` (`res_id`,`q_id`)
) ENGINE=MyISAM",
);

$_QUIZ_UPGRADE_SQL = array(
    '0.0.2' => array(
        "ALTER TABLE {$_TABLES['quizzer_quizzes']}
            ADD pass_msg TEXT",
        ),
    '0.0.3' => array(
        "ALTER TABLE {$_TABLES['quizzer_results']}
            ADD `asked` int(3) unsigned NOT NULL DEFAULT '0'",
        ),
);

?>
