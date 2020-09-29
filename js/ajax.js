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

