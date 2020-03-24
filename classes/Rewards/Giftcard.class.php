<?php
/**
 * Class to create gift card rewards for passing scores.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2019 Lee Garner <lee@leegarner.com>
 * @package     quizzer
 * @version     v0.0.3
 * @since       v0.0.1
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Quizzer\Rewards;


/**
 * Class for caching quizzes, questions and results.
 * @package quizzer
 */
class Giftcard extends \Quizzer\Reward
{
    /**
     * Constructor. Sets basic variables names and types.
     *
     * @param   mixed   $A      Array of parameters.
     */
    public function __construct($A = array())
    {
        $this->type = 'Giftcard';
        $this->cfgFields =  array(
            'expiration' => 'int',
            'amount' => 'float',
        );
        parent::__construct($A);
    }


    /**
     * Create a gift card reward and provide the message to show the user.
     *
     * @param   integer $uid    User ID of recipient
     * @return  string      Text message to display
     */
    public function createReward($uid = 0)
    {
        global $_USER;

        if ($uid == 0) $uid = $_USER['uid'];
        $uid = (int)$uid;
        $msg = '';
        $args = array(
            'members'   => array($uid),
            'expires'  => $this->getConfig('expiration'),
            'amount' => $this->getConfig('amount'),
        );
        $status = PLG_invokeService(
            'shop',
            'sendcards',
            $args, $output, $svc_msg
        );
        if ($status == PLG_RET_OK) {
            $link = COM_createLink(
                'here',
                $output[$uid]['link']
            );
            $msg .= '<br />You got a gift card. Click ' . $link . ' to redeem.';
        }
        return $msg;
    }


    public function getType()
    {
        return 'Giftcard';
    }


    public function getDscp()
    {
        global $LANG_QUIZ;
        return $LANG_QUIZ['giftcard'];
    }

}

?>
