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
 * Covers what a settings update does with the catalog ID it is given.
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
	 * The handler stores the catalog ID through a plain option write, and the integration reads it
	 * back from the same row, so the value it reports moves with the update.
	 */
	public function test_update_stores_the_catalog_id_the_integration_reports(): void {
		$integration = facebook_for_woocommerce()->get_integration();

		// A first connection has no catalog yet.
		$integration->update_product_catalog_id( '' );
		$this->assertSame( '', $integration->get_product_catalog_id() );

		$response = ( new Handler() )->handle_update(
			$this->create_update_request( array( 'product_catalog_id' => 'catalog-new' ) )
		);

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'catalog-new', $integration->get_product_catalog_id() );
	}

	/**
	 * The sync makes Graph calls for every product category, and the install is already committed
	 * on Meta's side by the time this request reaches it, so it goes to the queue rather than
	 * holding the response open for as long as the store has categories.
	 */
	public function test_new_catalog_id_queues_the_product_set_sync_instead_of_running_it(): void {
		facebook_for_woocommerce()->get_integration()->update_product_catalog_id( '' );

		$recorder = $this->create_product_sets_sync_recorder();
		$this->set_product_sets_sync_handler( $recorder );

		( new Handler() )->handle_update(
			$this->create_update_request( array( 'product_catalog_id' => 'catalog-new' ) )
		);

		$this->assertTrue( $recorder->was_scheduled, 'A new catalog ID should queue the product set sync.' );
		$this->assertFalse( $recorder->was_run_inline, 'The product set sync should not run inside the request.' );
	}

	/**
	 * An update that does not move the catalog leaves the sync alone.
	 */
	public function test_unchanged_catalog_id_does_not_queue_the_product_set_sync(): void {
		facebook_for_woocommerce()->get_integration()->update_product_catalog_id( 'catalog-existing' );

		$recorder = $this->create_product_sets_sync_recorder();
		$this->set_product_sets_sync_handler( $recorder );

		( new Handler() )->handle_update(
			$this->create_update_request( array( 'product_catalog_id' => 'catalog-existing' ) )
		);

		$this->assertFalse( $recorder->was_scheduled );
		$this->assertFalse( $recorder->was_run_inline );
	}

	/**
	 * Builds a stand-in for the product sets sync handler that records how it was asked to sync.
	 *
	 * @return object
	 */
	private function create_product_sets_sync_recorder() {
		return new class() {
			/** @var bool whether the sync was queued */
			public $was_scheduled = false;

			/** @var bool whether the sync was run in the current request */
			public $was_run_inline = false;

			/**
			 * Records that the sync was queued.
			 */
			public function schedule_sync_all_product_sets() {
				$this->was_scheduled = true;
			}

			/**
			 * Records that the sync ran here instead of being queued.
			 */
			public function sync_all_product_sets() {
				$this->was_run_inline = true;
			}
		};
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
