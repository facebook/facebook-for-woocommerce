<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 */

declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Unit\API\Plugin\Settings;

use WooCommerce\Facebook\API\CommerceIntegration\Client as FinalizeClient;
use WooCommerce\Facebook\API\CommerceIntegration\Finalize\Response as FinalizeResponse;
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
	/**
	 * Onboarding does not go through handle_update(): the Commerce Extension install lands on
	 * handle_finalize_install(), which deposits the token, asks Meta for the connected assets
	 * and only then applies them. The catalog it stores is the one the endpoint returned, so
	 * that is the value the rest of the request, and the queued sync, must see.
	 */
	public function test_finalize_install_stores_the_catalog_id_the_integration_reports(): void {
		$integration = facebook_for_woocommerce()->get_integration();

		// A first connection has no catalog yet.
		$integration->update_product_catalog_id( '' );
		$this->assertSame( '', $integration->get_product_catalog_id() );

		$response = ( new Handler( $this->create_finalize_client( 'catalog-new' ) ) )->handle_finalize_install(
			$this->create_finalize_install_request()
		);

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'catalog-new', $integration->get_product_catalog_id(), 'The catalog the endpoint returned should win over the install message.' );
	}

	public function test_finalize_install_queues_the_product_set_sync_instead_of_running_it(): void {
		facebook_for_woocommerce()->get_integration()->update_product_catalog_id( '' );

		$recorder = $this->create_product_sets_sync_recorder();
		$this->set_product_sets_sync_handler( $recorder );

		( new Handler( $this->create_finalize_client( 'catalog-new' ) ) )->handle_finalize_install(
			$this->create_finalize_install_request()
		);

		$this->assertTrue( $recorder->was_scheduled, 'A newly connected catalog should queue the product set sync.' );
		$this->assertFalse( $recorder->was_run_inline, 'The product set sync should not run inside the finalize-install request.' );
	}

	/**
	 * Stubs the finalize-install endpoint with a successful response carrying the given catalog.
	 *
	 * The integration ID it returns matches the stored one so the update does not also trigger
	 * the metadata feed uploads, which are not what these tests are about.
	 *
	 * @param string $catalog_id Catalog ID the endpoint should report.
	 * @return FinalizeClient
	 */
	private function create_finalize_client( string $catalog_id ): FinalizeClient {
		$this->add_filter_with_safe_teardown(
			'wc_facebook_external_business_id',
			function () {
				return 'store-ebid-123';
			},
			10,
			2
		);
		$this->mock_set_option( \WC_Facebookcommerce_Integration::OPTION_COMMERCE_PARTNER_INTEGRATION_ID, 'cpi-existing' );

		$client = $this->createMock( FinalizeClient::class );
		$client->method( 'finalize_install' )->willReturn(
			new FinalizeResponse(
				wp_json_encode(
					array(
						'id'                            => 'cpi-existing',
						'external_business_id'          => 'store-ebid-123',
						'installation_status'           => 'ACCESS_TOKEN_DEPOSITED',
						'commerce_merchant_settings_id' => 'cms-new',
						'catalog_id'                    => $catalog_id,
						'pixel_id'                      => 'pixel-new',
					)
				)
			)
		);

		return $client;
	}

	/**
	 * The install message the Commerce Extension posts, carrying its own (legacy) asset IDs.
	 *
	 * @return \WP_REST_Request
	 */
	private function create_finalize_install_request(): \WP_REST_Request {
		return $this->create_update_request(
			array(
				'access_token'                    => 'fresh-suat',
				'commerce_partner_integration_id' => 'cpi-existing',
				'product_catalog_id'              => 'catalog-legacy',
				'pixel_id'                        => 'pixel-legacy',
				'installed_features'              => array(),
			)
		);
	}

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
