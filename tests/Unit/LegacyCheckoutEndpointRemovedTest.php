<?php
/**
 * Copyright (c) Meta, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 */

declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Unit;

use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;

/**
 * The legacy /fb-checkout endpoint was replaced by WooCommerce's native /checkout-link.
 */
class LegacyCheckoutEndpointRemovedTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

	/**
	 * Test that WordPress no longer routes /fb-checkout to the plugin.
	 */
	public function test_fb_checkout_is_not_routed() {
		global $wp_rewrite;

		$this->assertArrayNotHasKey( '^fb-checkout/?$', $wp_rewrite->extra_rules_top );
		$this->assertNotContains( 'fb_checkout', apply_filters( 'query_vars', array() ) );
	}
}
