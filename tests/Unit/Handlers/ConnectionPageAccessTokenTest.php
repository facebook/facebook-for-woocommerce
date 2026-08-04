<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package MetaCommerce
 */

declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Unit\Handlers;

use WooCommerce\Facebook\Handlers\Connection;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;

/**
 * Tests compatibility behavior for stored legacy page access tokens.
 */
class ConnectionPageAccessTokenTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

	/**
	 * Ensures an install webhook updates the page ID without changing the legacy token.
	 */
	public function test_install_webhook_preserves_legacy_page_access_token(): void {
		$plugin = $this->createMock( \WC_Facebookcommerce::class );
		$plugin->method( 'log' );

		update_option( \WC_Facebookcommerce_Integration::OPTION_PAGE_ACCESS_TOKEN, 'legacy_page_token' );

		$data = (object) array(
			'object' => Connection::WEBHOOK_SUBSCRIBED_OBJECT,
			'entry'  => array(
				(object) array(
					'uid'     => 'system_user_id',
					'changes' => array(
						(object) array(
							'field' => Connection::WEBHOOK_SUBSCRIBED_FIELD,
							'value' => (object) array(
								'access_token'          => 'system_user_token',
								'merchant_access_token' => 'merchant_token',
								'pages'                => array( 'page_id' ),
							),
						),
					),
				),
			),
		);

		$connection = new Connection( $plugin );
		$connection->fbe_install_webhook( $data );

		$this->assertSame( 'page_id', get_option( \WC_Facebookcommerce_Integration::SETTING_FACEBOOK_PAGE_ID ) );
		$this->assertSame( 'legacy_page_token', get_option( \WC_Facebookcommerce_Integration::OPTION_PAGE_ACCESS_TOKEN ) );
	}
}
