<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 */

declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Unit\API\Plugin\Settings;

use WooCommerce\Facebook\API\Plugin\Settings\Handler;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;

/**
 * Covers the catalog ID a settings update leaves behind for the rest of the request.
 *
 * These tests use no Meta entities; all IDs are opaque local fixtures.
 */
class HandlerCatalogIdTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

	/** @var mixed the plugin's real product sets sync handler */
	private $original_product_sets_sync_handler;

	/**
	 * Set up the test environment.
	 */
	public function setUp(): void {
		parent::setUp();

		$this->original_product_sets_sync_handler = facebook_for_woocommerce()->get_product_sets_sync_handler();

		// The full product batch sync is the other thing a catalog change kicks off, and it is not
		// what these tests are about.
		$this->add_filter_with_safe_teardown(
			'facebook_for_woocommerce_block_full_batch_api_sync',
			function () {
				return true;
			}
		);
	}

	/**
	 * Tear down the test environment.
	 */
	public function tearDown(): void {
		$this->set_product_sets_sync_handler( $this->original_product_sets_sync_handler );

		facebook_for_woocommerce()->get_integration()->update_product_catalog_id( '' );

		parent::tearDown();
	}

	/**
	 * Regression: the integration memoizes the catalog ID on first read, and the handler reads it
	 * to decide whether the update warrants a sync. Storing the new ID with a plain update_option()
	 * left that memoized copy holding the old value for the rest of the request.
	 */
	public function test_update_refreshes_the_catalog_id_the_integration_reports(): void {
		$integration = facebook_for_woocommerce()->get_integration();

		// A first connection has no catalog yet. Reading it here is what the handler does before
		// writing, and is what used to pin an empty string in memory.
		$integration->update_product_catalog_id( '' );
		$this->assertSame( '', $integration->get_product_catalog_id() );

		$response = ( new Handler() )->handle_update(
			$this->create_update_request( array( 'product_catalog_id' => 'catalog-new' ) )
		);

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'catalog-new', $integration->get_product_catalog_id() );
	}

	/**
	 * The stale copy mattered because the product set sync runs inline, in the same request, right
	 * after the write. It has to see the catalog the merchant just connected, not the one before.
	 */
	public function test_inline_product_set_sync_sees_the_new_catalog_id(): void {
		facebook_for_woocommerce()->get_integration()->update_product_catalog_id( '' );

		$recorder = new class() {
			/** @var string|null catalog ID visible when the sync was invoked */
			public $catalog_id_at_sync_time = null;

			/** @var bool whether the sync was invoked at all */
			public $was_called = false;

			/**
			 * Records what the sync would have addressed instead of calling Meta.
			 */
			public function sync_all_product_sets() {
				$this->was_called              = true;
				$this->catalog_id_at_sync_time = facebook_for_woocommerce()->get_integration()->get_product_catalog_id();
			}
		};

		$this->set_product_sets_sync_handler( $recorder );

		( new Handler() )->handle_update(
			$this->create_update_request( array( 'product_catalog_id' => 'catalog-new' ) )
		);

		$this->assertTrue( $recorder->was_called, 'A new catalog ID should trigger the product set sync.' );
		$this->assertSame( 'catalog-new', $recorder->catalog_id_at_sync_time );
	}

	/**
	 * An update that does not move the catalog leaves the sync alone.
	 */
	public function test_unchanged_catalog_id_does_not_trigger_the_product_set_sync(): void {
		facebook_for_woocommerce()->get_integration()->update_product_catalog_id( 'catalog-existing' );

		$recorder = new class() {
			/** @var bool whether the sync was invoked */
			public $was_called = false;

			/**
			 * Records that the sync ran.
			 */
			public function sync_all_product_sets() {
				$this->was_called = true;
			}
		};

		$this->set_product_sets_sync_handler( $recorder );

		( new Handler() )->handle_update(
			$this->create_update_request( array( 'product_catalog_id' => 'catalog-existing' ) )
		);

		$this->assertFalse( $recorder->was_called );
	}

	/**
	 * Swaps the plugin's product sets sync handler.
	 *
	 * @param mixed $handler Replacement handler.
	 * @return void
	 */
	private function set_product_sets_sync_handler( $handler ): void {
		$reflection = new \ReflectionClass( facebook_for_woocommerce() );
		$property   = $reflection->getProperty( 'product_sets_sync_handler' );
		$property->setAccessible( true );
		$property->setValue( facebook_for_woocommerce(), $handler );
	}

	/**
	 * Creates a REST request mock with the supplied update data.
	 *
	 * An access token is always present because the update endpoint rejects requests without one.
	 *
	 * @param array $data Request data.
	 * @return \WP_REST_Request
	 */
	private function create_update_request( array $data ): \WP_REST_Request {
		$data = array_merge( array( 'access_token' => 'test-access-token' ), $data );

		$request = $this->createMock( \WP_REST_Request::class );
		$request->method( 'get_json_params' )->willReturn( $data );
		$request->method( 'get_params' )->willReturn( $data );

		return $request;
	}
}
