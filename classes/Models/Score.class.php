<?php
/**
 * Class to manage quiz grades
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2020 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v0.0.5
 * @since       v0.0.5
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Quizzer\Models;


/**
 * Class for quiz grades.
 * @package quizzer
 */
class Score
{
    /** Passing grade.
     */
    public const PASSED = 0;

    /** Passed, but needs work.
     */
    public const WARNING = 1;

    /** Failing grade.
     */
    public const FAILED = 2;

    /** CSS class names for the possile scores.
     * @var array */
    public static $CSS = array(
        'success',
        'warning',
        'danger',
    );

    /** Resulting grade, from the constants above.
     * @var integer */
    public $grade = self::FAILED;

    /** Actual percentage scored.
     * @var float */
    public $percent = 0;


    /**
     * Get the CSS string to be added to "uk-text-" for the grade.
     *
     * @retuen  string      CSS value from the $CSS array
     */
    public function getCSS()
    {
        if (isset(self::$CSS[$this->grade])) {
            return self::$CSS[$this->grade];
        } else {
            // In case of an invalid grade value
            return 'danger';
        }
    }

}

?>
