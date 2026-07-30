/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 */

jQuery(function ($) {
	$(document).on('click', '.wc-facebook-global-notice.is-dismissible .notice-dismiss', function () {
		$.post(WCFBAdminNotice.ajax_url, {
			action: 'wc_facebook_dismiss_notice',
			nonce: WCFBAdminNotice.nonce,
			notice_id: WCFBAdminNotice.notice_id
		});
	});
});
