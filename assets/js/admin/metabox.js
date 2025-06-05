/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */

/*
 *  Ajax helper function.
 *  Takes optional payload for POST and optional callback.
 */
function ajax(action, payload = null, cb = null, failcb = null) {
	var data = Object.assign( {}, {
		'action': action,
	}, payload);

	// Since  Wordpress 2.8 ajaxurl is always defined in admin header and
	// points to admin-ajax.php
	jQuery.post(
		ajaxurl,
		data,
		function(response) {
			if (cb) {
				cb( response );
			}
		}
	).fail(
		function(errorResponse){
			if (failcb) {
				failcb( errorResponse );
			}
		}
	);
}

