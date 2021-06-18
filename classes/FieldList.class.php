<?php
/**
 * Class to create custom admin list fields.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2021 Lee Garner <lee@leegarner.com>
 * @package     quizzer
 * @version     v0.0.4
 * @since       v0.0.4
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Quizzer;


/**
 * Class to handle custom fields.
 * @package quizzer
 */
class FieldList // extends \glFusion\FieldList
{
    private static $t = NULL;

    protected static function init()
    {
        global $_CONF;

        static $t = NULL;
        if (self::$t === NULL) {
            $t = new \Template($_CONF['path'] .'/plugins/quizzer/templates/');
            $t->set_file('field','fieldlist.thtml');
        } else {
            $t->unset_var('attributes');
            $t->unset_var('output');
        }
        return $t;
    }


    /**
     * Create a quiz preview link.
     *
     * @param   array   $args   Argument array
     * @return  string      HTML for field
     */
    public static function preview($args)
    {
        $t = self::init();

        $t->set_block('field','field-preview');
        if (isset($args['url'])) {
            $t->set_var('url', $args['url']);
        } else {
            $t->set_var('url','#');
        }

        if (isset($args['attr']) && is_array($args['attr'])) {
            $t->set_block('field','attr','attributes');
            foreach($args['attr'] AS $name => $value) {
                $t->set_var(array(
                    'name' => $name,
                    'value' => $value)
                );
                $t->parse('attributes','attr',true);
            }
        }
        $t->parse('output','field-preview');
        return $t->finish($t->get_var('output'));
    }

}
