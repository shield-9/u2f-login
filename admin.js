jQuery(document).ready(function($) {
	$u2f_reg = $("#u2f-register");
	$u2f_reg.click(function() {
		setTimeout(function() {
			var request = {"challenge": "CR-2c5Pq9GkgIG9IwAiPLa2jLaXNXlIRY858gbY3zKE", "version": "U2F_V2", "appId": "https://www.extendwings.com"};
			console.log("Register: ", request);
			$u2f_reg.text('Now insert (and tap) your Security Key');

/*
			u2f.register([request], [], function(data) {
				console.log("Register callback", data);
				$('#bind-data').val(JSON.stringify(data));
				$('#bind-form').submit();
			});
*/
		}, 1000);
	});
});
