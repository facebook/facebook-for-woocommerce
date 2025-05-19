jQuery(function ($) {
	$(document).on('click', '.fb-wa-banner .wa-close-button', function (e) {
		e.preventDefault();
		$('.fb-wa-banner').remove();

		$.post(WCFBAdminBanner.ajax_url, {
			action: 'wc_facebook_dismiss_banner',
			nonce: WCFBAdminBanner.nonce,
			banner_id: WCFBAdminBanner.banner_id
		}).done(function (response) {
		});
	});
});
