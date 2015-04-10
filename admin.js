(function($) {
	var registering = false;

	$u2f_reg = $("#u2f-register");
	$u2f_reg.click(function() {
		if( registering ) {
			return false;
		} else {
			registering = true;
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

				$u2f_reg.text('Please Wait')
					.append('<div class="circle">')
					.children(".circle")
					.append('<div class="semicircle">')
					.append('<div class="semicircle">')
					.append('<div class="semicircle">')
					.append('<div class="semicircle">');

				$.post(
					u2f_data.ajax_url,
					{
						action: "u2f_register",
						data: data
					}
				)
					.done(function( data, textStatus, jqXHR ){
						console.log('Ajax Response', jqXHR );

						if( data.success ) {
							history.pushState('', '', location.href + '&u2f_status=registered');

							console.log('Registered successfully');
							$u2f_reg.text('Registered');
							/**
							 * TODO
							 *
							 * * Update List Table
							 */
						} else {
							history.pushState('', '', location.href + '&u2f_status=failed');

							console.log('Error occured');
							$u2f_reg.text('Failed');

							if( data.errorCode ) {
								var meaning =	data.errorText.indexOf("Error code: 1") > -1 ? 'Some `Other Error` happened.'+'\n\n' :
										data.errorText.indexOf("Error code: 2") > -1 ? 'You did not allow the website do identify your security key.'+'\n\n' :
										data.errorText.indexOf("Error code: 3") > -1 ? 'Configuration Unsupported.'+'\n\n' :
										data.errorText.indexOf("Error code: 4") > -1 ? 'This security key already seems to be registered. Device Ineligible.'+'\n\n' :
										data.errorText.indexOf("Error code: 5") > -1 ? 'You probably did not find your security key. Timeout.'+'\n\n' :
										'';												
								alert(
									meaning + 'Sorry, we are failed to register your security key.\n'
									+ ' * Error Code: ' + data.errorCode + '\n'
									+ ' * Status Message, Browser-side Error Code: ' + data.errorText
								);
							} else {
								alert(
									'Sorry, we are failed to register your security key. '
									+ 'We have no detailed information. Please contact server administrator.'
								);
							}
						}
					})
					.fail(function( jqXHR, textStatus, errorThrown ){
						history.pushState('', '', location.href + '&u2f_status=failed');

						console.log('Ajax Response(Bad HTTP Status)', jqXHR );
						$u2f_reg.text('Failed');

						alert(
							'Sorry, we are failed to register your security key. '
							+ 'We have no detailed information. Please contact server administrator.'
						);
					})
					.always(function( data_jqXHR, textStatus, jqXHR_errorThrown ){
						registering = false;
					});
			});
		}, 1000);
	});

	if(typeof u2f === 'undefined') {
		$u2f_reg.text('Your browser doesn\'t support U2F API.')
			.removeClass('button-primary')
			.css('cursor', 'not-allowed')
			.unbind('click');
	}

})(jQuery);
