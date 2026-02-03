/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package MetaCommerce
 */
/*
 *  Ajax helper function.
 *  Takes optional payload for POST and optional callback.
 */
function ajax(action, payload = null, callback = null, failcallback = null) {
	var data = Object.assign( {}, {
		'action': action,
	}, payload );

	// Since  Wordpress 2.8 ajaxurl is always defined in admin header and
	// points to admin-ajax.php
	jQuery.post(
		ajaxurl,
		data,
		function(response) {
			if (callback) {
				callback( response );
			}
		}
	).fail(
		function(errorResponse){
			if (failcallback) {
				failcallback( errorResponse );
			}
		}
	);
}

window.fb_woo_infobanner_post_click = function (){
	console.log( "Woo infobanner post tip click!" );
	return ajax(
		'ajax_woo_infobanner_post_click',
		{
			"_ajax_nonce": wc_facebook_infobanner_jsx.nonce
		},
	);
};

window.fb_woo_infobanner_post_xout = function() {
	console.log( "Woo infobanner post tip xout!" );
	return ajax(
		'ajax_woo_infobanner_post_xout',
		{
			"_ajax_nonce": wc_facebook_infobanner_jsx.nonce
		},
	);
};
