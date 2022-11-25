/*  Toggle DB fields based on checkbox actions.
*/
var QUIZtoggleEnabled = function(cbox, id, type, component) {
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
        type: "post",
        dataType: "json",
        url: site_admin_url + "/plugins/quizzer/ajax.php",
        data: data,
        success: function(result) {
            cbox.checked = result.newval == 1 ? true : false;
            try {
				var icon = '<i class="uk-icon uk-icon-check"></i>&nbsp;';
				if (typeof UIkit.notify === 'function') {
					// uikit v2 theme
				    UIkit.notify(icon + result.statusMessage, {timeout: 1000});
				} else if (typeof UIkit.notification === 'function') {
					// uikit v3 theme
					UIkit.notification({
				        message: icon + result.statusMessage,
					    timeout: 1000,
						status: 'success',
					});
				} else {
					alert(result.statusMessage);
				}
			}
            catch(err) {
                alert(result.statusMessage);
            }
        }
    });
    return false;
};
