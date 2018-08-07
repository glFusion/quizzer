/*  Toggle DB fields based on checkbox actions.
*/
var FRMtoggleEnabled = function(cbox, id, type, component) {
    oldval = cbox.checked ? 0 : 1;
    var dataS = {
        "action" : "toggleEnabled",
        "id": id,
        "type": type,
        "oldval": oldval,
        "var": component,
    };
    data = $.param(dataS);
    $.ajax({
        type: "POST",
        dataType: "json",
        url: site_admin_url + "/plugins/forms/ajax.php",
        data: data,
        success: function(result) {
            cbox.checked = result.newval == 1 ? true : false;
            try {
                $.UIkit.notify("<i class='uk-icon-check'></i>&nbsp;" + result.statusMessage, {timeout: 1000,pos:'top-center'});
            }
            catch(err) {
                alert(result.statusMessage);
            }
        }
    });
    return false;
};
