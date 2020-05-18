jQuery(document).ready(function($){

	// Check that it's a post

	if( $('body').hasClass('single-post') ) {

		var data = {
			'action' 	: 'lpv_check_views',
			'post_id'	: limit_post_views.post_id
		};

		$.post(limit_post_views.ajaxurl, data, function( response ){

			if( response.data >= 5 ) {

				swal({

					title: "Thanks for reading!",
					html:
						'You\'ve read five articles already. To see more, please enter your email address.<br>' +
						'<input id="swal-input-firstname" placeholder="First Name" class="swal2-input" style="display: inline-block; width: 48.5%;">' +
						'<input id="swal-input-lastname" placeholder="Last Name" class="swal2-input" style="display: inline-block; width: 48.5%; margin-left: 2%;">' +
						'<input id="swal-input-email" placeholder="Email address" class="swal2-input">',
					allowOutsideClick: false,
					allowEscapeKey: false,
					onOpen: function () {

    					$('#swal-input-firstname').focus();

    					if(window.ga && ga.create) {

    						gtag('event', 'Form Shown', {
								'event_category': 'Optin',
							});

    					}

  					},
					preConfirm: function () {

					    return new Promise(function (resolve) {
					      	resolve([
					        	$('#swal-input-firstname').val(),
					        	$('#swal-input-lastname').val(),
					        	$('#swal-input-email').val()
					    	])
						})
					},

				}).then((result) => {

					swal(
						"Thanks!",
						"We appreciate your support",
						"success"
					);

					var data = {
						'action'	 : 'lpv_popup_submit',
						'data'		 : JSON.stringify(result.value)
					};

					$.post(limit_post_views.ajaxurl, data);

    					if(window.ga && ga.create) {
							gtag('event', 'Form Submitted', {
								'event_category': 'Optin',
							});
    					}

				});

			}

		});

	}

});