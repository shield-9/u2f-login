jQuery(document).ready(function($) {
	var registering = false;

	$u2f_reg = $("#u2f-register");
	$u2f_reg.click(function() {
		if( registering ) {
			return false;
		} else {
			registering = !registering;
		}

		u2f_data.request = JSON.parse(u2f_data.request);
		u2f_data.sigs    = JSON.parse(u2f_data.sigs);

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
				$.post(
					u2f_data.ajax_url,
					{
						action: "u2f_register",
						data: data
					}
				)
					.done(function( data ){
						console.log('Woo!', data );
					})
					.fail(function( data ){
						console.log('Oops!');
					});
			//	$('#bind-data').val(JSON.stringify(data));
			//	$('#bind-form').submit();
			});
		}, 1000);
	});
});
