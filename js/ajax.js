/**
*   Save a field from a defined AJAX-type form
*
*   @param  object  frm     Form
*/
var QUIZ_submit = function(frm_id)
{
    data = $("#"+frm_id).serialize();

    $.ajax({
        type: "POST",
        dataType: "json",
        url: glfusionSiteUrl + "/quizzer/ajax.php?action=saveresponse",
        data: data,
        success: function(result) {
            try {
                if (result.isvalid == 1) {
                    correct_ans = result.correct_ans;
                    submitted_ans = result.submitted_ans;
                    correct_div = document.getElementById("row_" + correct_ans);
                    correct_div.className = 'qz-answerrow qz-correct';
                    document.getElementById('answer_msg').style.display = "";
                    if (correct_ans == submitted_ans) {
                        // Mark the correct answer
                        status_div = document.getElementById("stat_" + correct_ans);
                        status_div.innerHTML = "<i class=\"uk-icon uk-icon-check uk-icon-medium qz-color-correct\"></i>";
                    } else {
                        status_div = document.getElementById("stat_" + submitted_ans);
                        status_div.innerHTML = "<i class=\"uk-icon uk-icon-close uk-icon-medium qz-color-incorrect\"></i>";
                        submitted_div = document.getElementById("row_" + submitted_ans);
                        submitted_div.className = "qz-answerrow qz-incorrect";
                    }
                    document.getElementById("btn_save").style.display = "none";
                    document.getElementById("btn_next").style.display = "";
                    if (result.answer_msg != "") {
                        ans_div = document.getElementById('answer_msg');
                        ans_div.innerHTML = result.answer_msg;
                        ans_div.style.display = "";
                    }
                } else {
                    window.location.replace(glfusionSiteUrl + '/quizzer/index.php?plugin=quizzer&msg=1');
                }
            } catch(err) {
            }
        }
    });
    return false;
};
