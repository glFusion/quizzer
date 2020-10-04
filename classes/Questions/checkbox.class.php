<?php
/**
 * Class to handle checkbox quiz questions.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2018 Lee Garner <lee@leegarner.com>
 * @package     quizzer
 * @version     v0.0.4
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Quizzer\Questions;
use Quizzer\Value;


/**
 * Class for checkbox questions.
 * Multiple answers are allowed.
 */
class checkbox extends \Quizzer\Question
{
    /**
     * Create the input selection for one answer.
     * Does not display the text for the answer, only the input element.
     *
     * @param   integer $a_id   Answer ID
     * @return  string          HTML for input element
     */
    protected function makeSelection($a_id)
    {
        // Show the answer as disabled and checked if the answer has already
        // been submitted.
        if ($this->have_answer > 0) {
            $disabled = 'disabled="disabled"';
            $sel = $this->have_answer == $a_id ? 'checked="checked"' : '';
        } else {
            $disabled = '';
            $sel = '';
        }
        return '<input id="ans_id_' . $a_id . '" type="checkbox" name="a_id[]" value="' . $a_id . '" ' . $disabled . ' ' . $sel . '/>';
    }


    /**
     * Verify the supplied answer ID against the correct value.
     * For this question type the return may be 1 or 0 or, if partial
     * credit is allowed, the percentage of correct boxes selected.
     *
     * @param   array   $submitted  Submitted answer IDs
     * @return  float       Numeric score
     */
    public function Verify($submitted)
    {
        $correct = 0;
        if (!Value::isValidAnswer($submitted)) {
            return $correct;
        }

        $possible = count($this->Answers);
        foreach ($this->Answers as $id=>$ans) {
            switch ($ans->isCorrect()) {
            case 1:
                if (in_array($id, $submitted)) {
                    $correct++;
                }
                break;
            case 0:
                if (!in_array($id, $submitted)) {
                    $correct++;
                }
            }
        }
        if ($this->allowsPartialCredit()) {
            return ($correct / $possible);
        } else {
            return ($correct == $possible) ? 1 : 0;
        }
    }


    /**
     * Get an array of correct answer IDs
     *
     * @return   array      Array of correct answer IDs
     */
    public function getCorrectAnswers()
    {
        $retval = array();
        foreach ($this->Answers as $a_id => $ans) {
            if ($ans->isCorrect()) {
                $retval[] = $a_id;
            }
        }
        if (empty($retval)) {
            $retval = array(0);   // Failsafe, but should not happen
        }
        return $retval;
    }


    /**
     * Check if this question type allows partial credit.
     * Used to determine whether the partial credit option is shown on the
     * question definition form.
     *
     * @return  boolean     True if partial credit is allowed
     */
    public function allowPartial()
    {
        return true;
    }

}

?>
