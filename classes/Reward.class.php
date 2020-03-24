<?php
/**
 * Class to create rewards for taking or passing a quiz.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2019-2020 Lee Garner <lee@leegarner.com>
 * @package     quizzer
 * @version     v0.0.3
 * @since       v0.0.1
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Quizzer;


/**
 * Base class to handle quiz rewards.
 * @package quizzer
 */
class Reward
{
    /** Record ID of the reward.
     * @var integer */
    protected $id = 0;

    /** Name of the reward. Short text like 'gc_25' for $25 Gift Card.
     * @var string */
    protected $name = '';

    /** Type of the reward. Matches a class name under the Rewards/ directory.
     * First letter is uppercase.
     * @var string */
    protected $type = '';

    /** Array of config field names and types depending on the reward type.
     * @var array */
    protected $cfgFields = array();

    /** Name of the class, used for selections.
     * @var string */
    protected $cls_name = '';

    /** Friendly description of the reward.
     * @var string */
    protected $cls_dscp = '';


    /**
     * Initizize the reward object. Sets the properties if supplied.
     *
     * @param   array   $A      Optional array of values to set
     */
    public function __construct($A=NULL)
    {
        if (is_array($A)) {
            $this->setVars($A);
        }
    }


    /**
     * Get the configuration field.
     *
     * @param   array   $A  Array of parameters ?
     * @return  array   Contents of the protected `$fields` var
     */
    public function getConfigFields($A=array())
    {
        return $this->cfgFields;
    }


    /**
     * Get the reward object for a specific quiz.
     *
     * @param   string  $quiz_id    Quiz ID
     * @return  object      Reward object used by the quiz
     */
    public static function getReward($quiz_id)
    {
        // temp, only giftcards work
        return new Quizzer\Rewards\GiftCard;
    }


    /**
     * Get a specific reward item by ID.
     *
     * @param   integer $id     DB record ID
     * @return  object      New reward object
     */
    public static function getById($id)
    {
        global $_TABLES;

        $sql = "SELECT * FROM {$_TABLES['quizzer_rewards']}
            WHERE id = " . (int)$id;
        $res = DB_query($sql);
        if ($res) {
            $A = DB_fetchArray($res, false);
            return self::getInstance($A['type'], $A);
        } else {
            return new self;
        }
    }


    /**
     * Get an instance of a reward by type.
     *
     * @param   string  $type   Type of reward
     * @param   array   $A      Optional array of values to set
     * @return  object      New reward object
     */
    public static function getInstance($type='', $A=NULL)
    {
        if ($type !== NULL) {
            $cls = 'Quizzer\Rewards\\' . ucfirst($type);
            if (class_exists($cls)) {
                return new $cls($A);
            }
        }
        // Return a generic Reward object by default so functions will work
        return new self($A);
    }


    /**
     * Set the property values from an array, either DB or from a form.
     *
     * @param   array   $A      Array of values
     * @param   boolean $fromDB True if $A is a DB record, False if from a form
     * @return  object  $this
     */
    private function setVars($A, $fromDB=true)
    {
        $this->type = $A['type'];
        $this->name = $A['name'];
        $this->id = (int)$A['id'];
        if ($fromDB) {
            $this->config = json_decode($A['config'], true);
        } else {
            $this->config = array();
            foreach ($this->cfgFields as $key=>$type) {
                switch ($type) {
                case 'int':
                    $this->config[$key] = (int)$A[$key];
                    break;
                case 'float':
                    $this->config[$key] = (float)$A[$key];
                    break;
                default:
                    $this->config[$key] = $A[$key];
                    break;
                }
            }
        }
        return $this;
    }


    /**
     * Get a config item, sanitized according to the item type.
     *
     * @param   string  $key    Config item name
     * @return  mixed       Item value
     */
    public function getConfig($key)
    {
        if (
            !isset($this->cfgFields[$key]) ||
            !isset($this->config[$key])
        ) {
            return NULL;
        }

        switch ($this->cfgFields[$key]) {
        case 'float':
            $retval = (float)$this->config[$key];
            break;
        case 'int':
            $retval = (int)$this->config[$key];
            break;
        default:
            $retval = $this->config[$key];
            break;
        }
        return $retval;
    }


    /**
     * Edit a particular reward record.
     *
     * @return  string      HTML for the editing form
     */
    public function Edit()
    {
        $T = new \Template(__DIR__ . '/../templates/admin');
        $T->set_file(array(
            'form'  => 'editreward.thtml',
            'tips'  => 'tooltipster.thtml',
        ) );

        $T->set_block('form', 'ConfigRow', 'row');
        foreach ($this->cfgFields as $key=>$type) {
            $T->set_var(array(
                'cfg_text'  => $this->getLang($key),
                'cfg_name'  => $key,
                'cfg_val'   => $this->getConfig($key),
            ) );
            $T->parse('row', 'ConfigRow', true);
        }
        $T->set_var(array(
            'r_id'  => $this->id,
            'name'  => $this->name,
            'doc_url' => QUIZ_getDocURL('editreward.html'),
            'admin_url' => QUIZ_ADMIN_URL,
        ) );

        // Scan the Rewards/ directory to get all available types
        $files = glob(__DIR__ . '/Rewards/*.class.php');
        if (is_array($files)) {
            $T->set_block('form', 'TypeOptions', 'TO');
            foreach ($files as $fullpath) {
                $parts = explode('/', $fullpath);
                list($class,$x1,$x2) = explode('.', $parts[count($parts)-1]);
                // Instantiate just to ensure it's a valid object
                $R = self::getInstance($class);
                if (is_object($R)) {
                    $T->set_var(array(
                        'type' => $class,
                        'dscp' => $R->getLang($class),
                        'sel' => $class == $this->type ? 'selected="selected"' : '',
                    ) );
                    $T->parse('TO', 'TypeOptions', true);
                }
            }
        }
        $T->parse('tooltipster_js', 'tips');
        $T->parse('output', 'form');
        $retval .= $T->finish($T->get_var('output'));
        return $retval;
    }


    /**
     * Save a reward record.
     *
     * @param   array   $A  Optional array of values
     * @return  boolean     True on success, False on error
     */
    public function Save($A = NULL)
    {
        global $_TABLES;

        if (is_array($A)) {
            $this->setVars($A, false);
        }
        $name = DB_escapeString($this->name);
        $type = DB_escapeString($this->type);
        $config  = DB_escapeString(json_encode($this->config));
        $id = (int)$this->id;
        $sql = "INSERT INTO {$_TABLES['quizzer_rewards']} SET
            id = $id,
            name = '$name',
            type = '$type',
            config = '$config'
            ON DUPLICATE KEY UPDATE
            name = '$name',
            config = '$config'";
        //echo $sql;die;
        DB_query($sql);
        if (!DB_error()) {
            if ($this->id == 0) {
                $this->id = DB_insertID();
            }
            return true;
        } else {
            COM_setMsg(__CLASS__ . ' An error occurred saving the reward');
            return false;
        }
    }


    /**
     * Delete a reward. Also resets the reward ID for all quizzes using it.
     *
     * @return  object  $this, with the record ID reset to zero
     */
    public function Delete()
    {
        global $_TABLES;

        // Clear the specified reward from all quizzes
        Quiz::resetReward($this->id);
        // Delete the reward record
        DB_delete($_TABLES['quizzer_rewards'], 'id', $this->id);
        // Rest the ID to zero to prevent use.
        $this->id = 0;
        return $this;
    }


    /**
     * Get an option list to select the reward type.
     *
     * @param   integer $sel    Currently-selected option
     * @return  string      Option elements for a select list
     */
    public function optionList($sel=0)
    {
        global $_TABLES;

        return COM_optionList(
            $_TABLES['quizzer_rewards'],
            'id,name',
            (int)$sel,
            1
        );
    }


    /**
     * Dummy function if a reward type is not defined or not applicable.
     *
     * @return  string      Reward message to the quiz taker
     */
    public function createReward($uid)
    {
        return '';
    }


    /**
     * Uses lib-admin to list the quizzer definitions and allow updating.
     *
     * @return  string  HTML for the list
     */
    public static function adminList()
    {
        global $_CONF, $_TABLES, $LANG_ADMIN, $LANG_QUIZ, $perm_sql;

        // Import administration functions
        USES_lib_admin();

        $retval = '';

        $header_arr = array(
            array(
                'text' => 'ID',
                'field' => 'id',
                'sort' => true,
            ),
            array(
                'text' => $LANG_ADMIN['edit'],
                'field' => 'edit',
                'sort' => false,
                'align' => 'center',
            ),
            array(
                'text' => $LANG_ADMIN['copy'],
                'field' => 'copy',
                'sort' => false,
                'align' => 'center',
            ),
            array(
                'text' => 'Name',
                'field' => 'name',
                'sort' => true,
                'align' => 'left',
            ),
            array(
                'text' => 'Type',
                'field' => 'type',
                'sort' => true,
            ),
            array(
                'text' => 'Config',
                'field' => 'config',
                'sort' => false,
            ),
        );
        $sql = "SELECT * FROM {$_TABLES['quizzer_rewards']}";
        $text_arr = array();
        $query_arr = array(
            'table' => 'quizzer_rewards',
            'sql' => $sql,
            'query_fields' => array(),
            'default_filter' => ''
        );
        $defsort_arr = array('field' => 'id', 'direction' => 'ASC');
        $form_arr = array();
        $retval .= COM_createLink(
            $LANG_QUIZ['new_reward'],
            QUIZ_ADMIN_URL . '/index.php?editreward=0',
            array(
                'class' => 'uk-button uk-button-success',
                'style' => 'float:left',
            )
        );
        $retval .= ADMIN_list(
            'quizzer_rewards',
            array(__CLASS__,  'getAdminField'),
            $header_arr,
            $text_arr, $query_arr, $defsort_arr, '', '', '', $form_arr
        );
        return $retval;
    }


    /**
     * Determine what to display in the admin list for each field.
     *
     * @param   string  $fieldname  Name of the field, from database
     * @param   mixed   $fieldvalue Value of the current field
     * @param   array   $A          Array of all name/field pairs
     * @param   array   $icon_arr   Array of system icons
     * @return  string              HTML for the field cell
     */
    public static function getAdminField($fieldname, $fieldvalue, $A, $icon_arr)
    {
        global $_CONF, $LANG_ACCESS, $LANG_QUIZ, $_TABLES, $_CONF_QUIZ, $_LANG_ADMIN;

        $retval = '';

        switch($fieldname) {
        case 'edit':
            $url = QUIZ_ADMIN_URL . "/index.php?editreward={$A['id']}";
            $retval = COM_createLink(
                $_CONF_QUIZ['icons']['edit'],
                $url
            );
            break;

        case 'copy':
            $url = QUIZ_ADMIN_URL . "/index.php?copyreward={$A['id']}";
            $retval = COM_createLink(
                $_CONF_QUIZ['icons']['copy'],
                $url
            );
            break;

        case 'config':
            $i = 0;
            $cfg = json_decode($fieldvalue,true);
            foreach ($cfg as $name=>$val) {
                if (++$i > 3) {
                    break;
                }
                $retval .= "$name: $val ";
            }
            break;

        default:
            $retval = $fieldvalue;
            break;
        }
        return $retval;
    }


    /**
     * Get the language string corresponding to a config item name.
     * Used for the configuration form.
     *
     * @param   string  $key    Config item name
     * @return  string      Language string, or $key if not found
     */
    protected function getLang($key)
    {
        global $LANG_QUIZ;

        if (isset($LANG_QUIZ[$key])) {
            return $LANG_QUIZ[$key];
        } else {
            return $key;
        }
    }

}

?>
