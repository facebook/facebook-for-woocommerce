/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 */

jQuery(function ($) {
	$(document).on('click', '.fb-wa-banner .wa-close-button', function (e) {
		e.preventDefault();

		$.post(WCFBAdminBanner.ajax_url, {
			action: 'wc_facebook_dismiss_banner',
			nonce: WCFBAdminBanner.nonce,
			banner_id: WCFBAdminBanner.banner_id
		}).done(function (response) {
			$('.fb-wa-banner').remove();
		});
	});
});
