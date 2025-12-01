<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Integration\COGS;

use WooCommerce\Facebook\Tests\Integration\IntegrationTestCase;
use WooCommerce\Facebook\API\Pixel\Events\Request;
use WooCommerce\Facebook\API;
use WooCommerce\Facebook\Events\Event;

/**
 * Integration tests for Pixel Events API Request.
 *
 * RUNNING THE TESTS:
 * ==================
 * Basic testing (test credentials - auth will fail but validates HTTP layer):
 *   ./run-tests-php82.sh --testsuite=integration --filter=RequestIntegrationTest
 *
 * Full integration testing (with valid Facebook credentials):
 *   export FB_TEST_ACCESS_TOKEN="your_real_token"
 *   export FB_TEST_PIXEL_ID="your_real_pixel_id"
 *   ./run-tests-php82.sh --testsuite=integration --filter=RequestIntegrationTest
 *
 *  * Steps to get your TEST_ACCESS_TOKEN
 *  1. Go to events manager
 *  2. Select "Datasets" tab from left panel
 *  3. Select your business
 *  4. Go to the Test events tab -> Graph API Explorer
 *  5. Copy Access Token
 */
class CogsIntegrationTests extends IntegrationTestCase {

	/**
	 * @var API
	 */
	private $api;

	/**
	 * @var bool Whether we have valid credentials for full integration testing
	 */
	private $has_valid_credentials = false;

	/**
	 * Set up test environment
	 */
	public function setUp(): void {
		parent::setUp();

		$this->disable_facebook_sync();
	}

	/**
	 * Placeholder. These tests should be added:
	 * 1. Testing WooC integration with older WooC versions
	 * 2. WPFactory plugin is installed but inactive
	 * 3. WooC Cogs is Disabled / Enabled. When WooC Cogs is disabled, WooCCogsProvider should return false in is_available
	 * 4. Test for Simple & Variable products
	 */
	public function test_Given_Single_Purcahse_Event_When_SendingEvent_Then_RequestContainsValues() {
		
		$this->assertTrue(false);
	}

	private function enable_cogs_in_woo_settings() {

	}

	private function disable_cogs_in_woo_settings() {
		
	}

	private function set_up_variable_product () {

		$size_attribute = new \WC_Product_Attribute();
		$size_attribute->set_name( 'Size' );
		$size_attribute->set_options( [ 'Small', 'Medium', 'Large' ] );
		$size_attribute->set_visible( true );
		$size_attribute->set_variation( true );

		$color_attribute = new \WC_Product_Attribute();
		$color_attribute->set_name( 'Color' );
		$color_attribute->set_options( [ 'Red', 'Blue', 'Black' ] );
		$color_attribute->set_visible( true );
		$color_attribute->set_variation( true );

		$variable_product = $this->create_variable_product([
			'Size' => $size_attribute,
			'Color' => $color_attribute
		]);

		$variations = [
			[ 'attributes' => [ 'size' => 'Small', 'color' => 'Red' ], 'price' => '29.99' ],
			[ 'attributes' => [ 'size' => 'Medium', 'color' => 'Blue' ], 'price' => '34.99' ],
			[ 'attributes' => [ 'size' => 'Large', 'color' => 'Black' ], 'price' => '39.99' ]
		];

		$created_variations = [];
		foreach ( $variations as $variation_data ) {
			$variation = new \WC_Product_Variation();
			$variation->set_parent_id( $variable_product->get_id() );
			$variation->set_attributes( $variation_data['attributes'] );
			$variation->set_regular_price( $variation_data['price'] );
			$variation->set_status( 'publish' );
			$variation->save();
			$created_variations[] = $variation;
		}

		return $created_variations;
	}

	public function test_should_not_use_value_if_cogs_is_zero(){
		$this->assertTrue(false);
	}

	private function set_up_simple_products( $count ) {
		$this->disable_facebook_sync();
		$products = [];

		for ( $i = 1; $i <= $count; $i++ ) {
			$products[] = $this->create_simple_product([
				'name' => "Simple Product {$i}",
				'regular_price' => (10 + $i) . '.99',
				'sku' => "Simple-{$i}",
				'status' => 'publish'
			]);
		}
		return $products;
	}

	/**
	 * Clean up after each test
	 */
	public function tearDown(): void {
		parent::tearDown();
	}
}
