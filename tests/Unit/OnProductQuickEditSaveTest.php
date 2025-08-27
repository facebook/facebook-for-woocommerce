<?php
declare( strict_types=1 );

require_once __DIR__ . '/../../facebook-commerce.php';

use WooCommerce\Facebook\Framework\Logger;
use WooCommerce\Facebook\Products;
use WooCommerce\Facebook\Products\Sync;

/**
 * Unit tests for on_product_quick_edit_save method.
 */
class OnProductQuickEditSaveTest extends \WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

	/**
	 * @var WC_Facebookcommerce
	 */
	private $facebook_for_woocommerce;

	/**
	 * @var WC_Facebookcommerce_Integration
	 */
	private $integration;

	/**
	 * @var Sync
	 */
	private $sync_handler;

	/**
	 * Runs before each test is executed.
	 */
	public function setUp(): void {
		parent::setUp();

		$this->facebook_for_woocommerce = $this->createMock( WC_Facebookcommerce::class );
		$this->sync_handler = $this->createMock( Sync::class );

		$this->facebook_for_woocommerce->method( 'get_products_sync_handler' )
			->willReturn( $this->sync_handler );

		// Mock get_product_sync_validator to return a validator that passes validation
		$product_validator = $this->createMock( \WooCommerce\Facebook\ProductSync\ProductValidator::class );
		$product_validator->method( 'validate_but_skip_status_check' )
			->willReturn( true );

		$this->facebook_for_woocommerce->method( 'get_product_sync_validator' )
			->willReturn( $product_validator );

		$this->integration = new WC_Facebookcommerce_Integration( $this->facebook_for_woocommerce );
	}

	/**
	 * Test simple product with published status gets synced.
	 *
	 * @return void
	 */
	public function test_published_simple_product_gets_synced() {
		$product = WC_Helper_Product::create_simple_product();
		$product->set_status( 'publish' );
		$product->save();

		// Enable sync for this product to pass the published_product_should_be_synced check
		update_post_meta( $product->get_id(), Products::get_product_sync_meta_key(), 'yes' );

		$this->sync_handler->expects( $this->once() )
			->method( 'create_or_update_products' )
			->with( [ $product->get_id() ] );

		$this->integration->on_product_quick_edit_save( $product );
	}

	/**
	 * Test variable product with published status syncs all children variations
	 *
	 * @return void
	 */
	public function test_published_variable_product_syncs_all_children() {
		$product = WC_Helper_Product::create_variation_product();
		$product->set_status( 'publish' );
		$product->save();

		// Enable sync for this product to pass the published_product_should_be_synced check
		update_post_meta( $product->get_id(), Products::get_product_sync_meta_key(), 'yes' );

		// Get all child variation IDs to verify they are all synced
		$children_ids = $product->get_children();
		$this->assertNotEmpty( $children_ids, 'Variable product should have child variations' );
		$this->assertGreaterThan( 1, count( $children_ids ), 'Variable product should have multiple variations' );

		// Expect sync to be called with all children IDs, not the parent ID
		$this->sync_handler->expects( $this->once() )
			->method( 'create_or_update_products' )
			->with( $children_ids );

		$this->integration->on_product_quick_edit_save( $product );
	}

	/**
	 * Test variable product with draft status does not get synced
	 *
	 * @return void
	 */
	public function test_draft_variable_product_does_not_sync() {
		$product = WC_Helper_Product::create_variation_product();
		$product->set_status( 'draft' );
		$product->save();

		$this->sync_handler->expects( $this->never() )
			->method( 'create_or_update_products' );

		$this->integration->on_product_quick_edit_save( $product );
	}

	/**
	 * Test draft product does not get synced.
	 *
	 * @return void
	 */
	public function test_draft_product_does_not_sync() {
		$product = WC_Helper_Product::create_simple_product();
		$product->set_status( 'draft' );
		$product->save();

		$this->sync_handler->expects( $this->never() )
			->method( 'create_or_update_products' );

		$this->integration->on_product_quick_edit_save( $product );
	}
}
