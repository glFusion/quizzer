<?php
/**
 * Class to provide admin and user-facing menus.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2019 Lee Garner <lee@leegarner.com>
 * @package     quizzer
 * @version     v0.0.3
 * @since       v0.0.3
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Quizzer;

/**
 * Class to provide admin and user-facing menus.
 * @package shop
 */
class Menu
{
    /**
     * Create the administrator menu.
     *
     * @param   string  $view   View being shown, so set the help text
     * @param   string  $help_text  Optional help text key
     * @param   string  $other_text Optional other text to display
     * @return  string      Administrator menu
     */
    public static function Admin($view ='', $help_text = '', $other_text='')
    {
        global $_CONF, $LANG_QUIZ, $_CONF_QUIZ, $LANG01;

        // Import administration functions
        USES_lib_admin();

        $menu_arr = array ();
        if ($help_text == '') {
            $help_text = 'admin_text';
        }

        if ($view == 'listquizzes') {
            $menu_arr[] = array(
                'url' => QUIZ_ADMIN_URL . '/index.php?action=editquiz',
                'text' => $LANG_QUIZ['add_quiz'],
            );
        } else {
            $menu_arr[] = array(
                'url' => QUIZ_ADMIN_URL . '/index.php?view=listquizzes',
                'text' => $LANG_QUIZ['list_quizzes'],
            );
        }

        $menu_arr[] = array(
            'url' => $_CONF['site_admin_url'],
            'text' => $LANG01[53],
        );

        $text = $LANG_QUIZ[$help_text];
        if (!empty($other_text)) {
            $text .= '<br />' . $other_text;
        }
        return ADMIN_createMenu($menu_arr, $text, plugin_geticon_quizzer());
    }

}

?>


