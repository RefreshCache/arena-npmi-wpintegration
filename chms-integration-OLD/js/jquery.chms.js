jQuery(document).ready(function($) {
	$(".chmswslink").click(function(evt) {
		evt.preventDefault();
		var wsUri = $(this).attr('href');
		var linkSource = $(this).attr('id');
		$.ajax({
			type: "post",
			url: ChMSWS.ajaxurl,
			data: { action: "chmsws", _ajax_nonce: ChMSWS.ajaxkey, chms_ws_uri: wsUri },
			success: function(json) {
				ChMSWS.ajaxkey = json.ajaxkey;
				if (ChmsWsSuccess) ChmsWsSuccess(json, linkSource);
				else alert('no success method found');
			},
			error: function(json) {
				if (ChmsWsError) ChmsWsError(json, linkSource);
				else alert('no error method found');
			}
		});
	});
});
