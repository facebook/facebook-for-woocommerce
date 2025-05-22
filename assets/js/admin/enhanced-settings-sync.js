/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */

jQuery(document).ready(function($) {
    /**
     * Handle the sync products button click event
     *
     * @since 3.5.0
     *
     * @param {object} event
     */
    $('#wc-facebook-enhanced-settings-sync-products').click(function(event) {
        event.preventDefault();
        var button = $(this);

        button.html('Syncing...');
        button.prop('disabled', true);

        var data = {
            action: "wc_facebook_sync_products",
            nonce: wc_facebook_enhanced_settings_sync.sync_products_nonce
        };

        $.post(wc_facebook_enhanced_settings_sync.ajax_url, data, function(response) {
            if (response.success) {
                button.html('Sync completed');
                button.prop('disabled', false);
            } else {
                button.html('Sync failed');
                button.prop('disabled', false);
            }
        }).fail(function() {
            button.html('Sync failed');
            button.prop('disabled', false);
        });
    });

    /**
     * Handle the sync coupons button click event
     *
     * @since 3.5.0
     *
     * @param {object} event
     */
    $('#wc-facebook-enhanced-settings-sync-coupons').click(function(event) {
        event.preventDefault();
        var button = $(this);

        button.html('Syncing...');
        button.prop('disabled', true);

        var data = {
            action: "wc_facebook_sync_coupons",
            nonce: wc_facebook_enhanced_settings_sync.sync_coupons_nonce
        };

        $.post(wc_facebook_enhanced_settings_sync.ajax_url, data, function(response) {
            if (response.success) {
                button.html('Sync completed');
                button.prop('disabled', false);
            } else {
                button.html('Sync failed');
                button.prop('disabled', false);
            }
        }).fail(function() {
            button.html('Sync failed');
            button.prop('disabled', false);
        });
    });

	/**
	 * Handle the sync shipping profiles button click event
	 *
	 * @since 3.5.0
	 *
	 * @param {object} event
	 */
	$('#wc-facebook-enhanced-settings-sync-shipping-profiles').click(function(event) {
		event.preventDefault();
		var button = $(this);

		button.html('Syncing...');
		button.prop('disabled', true);

		var data = {
			action: "wc_facebook_sync_shipping_profiles",
			nonce: wc_facebook_enhanced_settings_sync.sync_shipping_profiles_nonce
		};

		$.post(wc_facebook_enhanced_settings_sync.ajax_url, data, function(response) {
			if (response.success) {
				button.html('Sync completed');
				button.prop('disabled', false);
			} else {
				button.html('Sync failed');
				button.prop('disabled', false);
			}
		}).fail(function() {
			button.html('Sync failed');
			button.prop('disabled', false);
		});
	});

	/**
	 * Handle the sync navigation menu button click event
	 *
	 * @since 3.5.0
	 *
	 * @param {object} event
	 */
	$('#wc-facebook-enhanced-settings-sync-navigation-menu').click(function(event) {
		event.preventDefault();
		var button = $(this);

		button.html('Syncing...');
		button.prop('disabled', true);

		var data = {
			action: "wc_facebook_sync_navigation_menu",
			nonce: wc_facebook_enhanced_settings_sync.sync_navigation_menu_nonce
		};

		$.post(wc_facebook_enhanced_settings_sync.ajax_url, data, function(response) {
			if (response.success) {
				button.html('Sync completed');
				button.prop('disabled', false);
			} else {
				button.html('Sync failed');
				button.prop('disabled', false);
			}
		}).fail(function() {
			button.html('Sync failed');
			button.prop('disabled', false);
		});
	});
});
