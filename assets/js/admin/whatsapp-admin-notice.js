jQuery(function ($) {
	$(document).on('click', '.wc-facebook-global-notice.is-dismissible .notice-dismiss', function () {
		$.post(WCFBAdminNotice.ajax_url, {
			action: 'wc_facebook_dismiss_notice',
			nonce: WCFBAdminNotice.nonce,
			notice_id: WCFBAdminNotice.notice_id
		});
	});
});
