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
                    answers = result.answers;
                    //correct_div = document.getElementById("row_" + correct_ans);
                    //correct_div.className = 'qz-answerrow qz-correct';
                    document.getElementById('answer_msg').style.display = "";
                    for (var ans in result.answers) {
                        obj = result.answers[ans];
                        div = document.getElementById("row_" + ans);
                        // Disable the checkbox/radio button
                        document.getElementById("ans_id_" + ans).disabled=true;
                        if (obj.is_correct == 1) {
                            if (obj.submitted == 1) {
                                status_div = document.getElementById("stat_" + ans);
                                status_div.innerHTML = "<i class=\"uk-icon uk-icon-check uk-icon-medium qz-color-correct\"></i>";
                            }
                            div.className = 'qz-answerrow qz-correct';
                        } else if (obj.is_correct == 0 && obj.submitted == 1) {
                            status_div = document.getElementById("stat_" + ans);
                            status_div.innerHTML = "<i class=\"uk-icon uk-icon-close uk-icon-medium qz-color-incorrect\"></i>";
                        }
                    }
                    document.getElementById("btn_save").style.display = "none";
                    document.getElementById("btn_next").style.display = "";
                    if (result.answer_msg != "") {
                        ans_div = document.getElementById('answer_msg');
                        ans_div.innerHTML = result.answer_msg;
                        ans_div.style.display = "";
                    }
                } else {
                    $.UIkit.notify("<i class='uk-icon uk-icon-close uk-text-danger'></i>&nbsp;" + result.answer_msg, {timeout: 5000,pos:'top-center'});
                }
            } catch(err) {
            }
        }
    });
    return false;
};

/**
*   Save a field from a defined AJAX-type form
*
*   @param  object  frm     Form
*/
var QZADM_changeQType = function(newtype)
{
    var x = document.getElementsByClassName("ans_corr");
    for (var i = 0; i < x.length; i++) {
        document.getElementById(x[i].id).type = newtype;
        if (newtype == "checkbox") {
            varname = "correct[" + x[i].id + "]";
        } else {
            varname = "correct";
        }
        document.getElementById(x[i].id).name = varname;
    }
    if (newtype == "checkbox") {
        document.getElementById("partial_credit_div").style.display = "";
    } else {
        document.getElementById("partial_credit_div").style.display = "none";
    }

}


