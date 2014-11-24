jQuery(document).ready(function($) {
	var registering = false;

	$u2f_reg = $("#u2f-register");
	$u2f_reg.click(function() {
		if( registering ) {
			return false;
		} else {
			registering = !registering;
		}

		setTimeout(function() {
			console.log("Register: ", u2f_data.request);
			$u2f_reg.text('Now insert (and tap) your Security Key')
				.removeClass('button-primary')
				.append('<div class="circle">')
				.children(".circle")
				.append('<div class="semicircle">')
				.append('<div class="semicircle">')
				.append('<div class="semicircle">')
				.append('<div class="semicircle">');

			u2f.register([u2f_data.request], u2f_data.sigs, function(data) {
				console.log("Register callback", data);
			//	$('#bind-data').val(JSON.stringify(data));
			//	$('#bind-form').submit();
			});
		}, 1000);
	});
});
