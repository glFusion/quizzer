<?php
/**
*   Class to handle radio-button quiz questions
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2018 Lee Garner <lee@leegarner.com>
*   @package    quizzer
*   @version    0.0.1
*   @license    http://opensource.org/licenses/gpl-2.0.php
*               GNU Public License v2 or later
*   @filesource
*/
namespace Quizzer;

/**
*   Class for form fields
*/
class Question_radio extends Question
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
        return '<input id="ans_id_' . $a_id . '" type="radio" name="a_id" value="' . $a_id . '" ' . $disabled . ' ' . $sel . '/>';
    }


    /**
     * Verify the supplied answer ID against the correct value
     *
     * @param   integer $a_id   Answer ID
     * @return  boolean         True if correct, False if incorrect
     */
    public function Verify($a_id)
    {
        if ($this->Answers[$a_id]['correct'] == 1) {
            return true;
        } else {
            return false;
        }
    }


    /**
     * Get the ID of the correct answer.
     * Returns an array, even though only one radio button is correct,
     * to ensure uniform handling by the caller.
     *
     * @return   array      Array of correct answer IDs
     */
    public function getCorrectAnswers()
    {
        foreach ($this->Answers as $a_id => $ans) {
            if ($ans['correct']) {
                return array($a_id);
            }
        }
        return array(0);   // Failsafe, but should not happen
    }

}

?>
