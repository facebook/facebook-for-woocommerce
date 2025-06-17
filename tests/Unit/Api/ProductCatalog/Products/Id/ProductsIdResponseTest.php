<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Unit\Api\ProductCatalog\Products\Id;

use WooCommerce\Facebook\API\ProductCatalog\Products\Id\Response;
use WooCommerce\Facebook\API\Response as ApiResponse;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;

/**
 * Unit tests for ProductCatalog Products Id Response class.
 *
 * @since 3.5.2
 */
class ProductsIdResponseTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

	/**
	 * Test that the class exists.
	 */
	public function test_class_exists() {
		$this->assertTrue( class_exists( Response::class ) );
	}

	/**
	 * Test that the class extends ApiResponse.
	 */
	public function test_class_extends_api_response() {
		$response = new Response( '{}' );
		$this->assertInstanceOf( ApiResponse::class, $response );
	}

	/**
	 * Test get_facebook_product_group_id with valid data.
	 */
	public function test_get_facebook_product_group_id_with_valid_data() {
		$response_data = json_encode( array(
			'id' => '123456789',
			'product_group' => array(
				'id' => '987654321'
			)
		) );
		
		$response = new Response( $response_data );
		
		$this->assertEquals( '987654321', $response->get_facebook_product_group_id() );
	}

	/**
	 * Test get_facebook_product_group_id with missing product_group.
	 */
	public function test_get_facebook_product_group_id_with_missing_product_group() {
		$response_data = json_encode( array(
			'id' => '123456789'
		) );
		
		$response = new Response( $response_data );
		
		// Should return empty string when product_group is missing
		$this->assertEquals( '', $response->get_facebook_product_group_id() );
	}

	/**
	 * Test get_facebook_product_group_id with missing id in product_group.
	 */
	public function test_get_facebook_product_group_id_with_missing_id() {
		$response_data = json_encode( array(
			'id' => '123456789',
			'product_group' => array(
				'name' => 'Test Product Group'
			)
		) );
		
		$response = new Response( $response_data );
		
		// Should return empty string when id is missing in product_group
		$this->assertEquals( '', $response->get_facebook_product_group_id() );
	}

	/**
	 * Test get_facebook_product_group_id with null product_group.
	 */
	public function test_get_facebook_product_group_id_with_null_product_group() {
		$response_data = json_encode( array(
			'id' => '123456789',
			'product_group' => null
		) );
		
		$response = new Response( $response_data );
		
		// Should return empty string when product_group is null
		$this->assertEquals( '', $response->get_facebook_product_group_id() );
	}

	/**
	 * Test get_facebook_product_group_id with empty response.
	 */
	public function test_get_facebook_product_group_id_with_empty_response() {
		$response_data = json_encode( array() );
		
		$response = new Response( $response_data );
		
		// Should return empty string when response is empty
		$this->assertEquals( '', $response->get_facebook_product_group_id() );
	}

	/**
	 * Test get_facebook_product_group_id with various ID formats.
	 */
	public function test_get_facebook_product_group_id_with_various_formats() {
		$test_cases = array(
			'numeric_string' => '123456789',
			'alphanumeric'   => 'abc123def456',
			'with_underscores' => 'group_123_456',
			'with_dashes'    => 'group-123-456',
			'empty_string'   => '',
			'zero'           => '0',
		);
		
		foreach ( $test_cases as $description => $group_id ) {
			$response_data = json_encode( array(
				'product_group' => array(
					'id' => $group_id
				)
			) );
			
			$response = new Response( $response_data );
			
			$this->assertEquals( $group_id, $response->get_facebook_product_group_id(), "Failed for: {$description}" );
		}
	}

	/**
	 * Test accessing response data properties.
	 */
	public function test_response_data_properties() {
		$response_data = json_encode( array(
			'id' => '123456789',
			'product_group' => array(
				'id' => '987654321',
				'name' => 'Test Group'
			),
			'other_field' => 'other_value'
		) );
		
		$response = new Response( $response_data );
		
		// Test accessing properties through magic getter
		$this->assertEquals( '123456789', $response->id );
		$this->assertIsArray( $response->product_group );
		$this->assertEquals( '987654321', $response->product_group['id'] );
		$this->assertEquals( 'Test Group', $response->product_group['name'] );
		$this->assertEquals( 'other_value', $response->other_field );
	}

	/**
	 * Test with malformed JSON.
	 */
	public function test_with_malformed_json() {
		$response = new Response( 'invalid json' );
		
		// Should return empty string when JSON is invalid
		$this->assertEquals( '', $response->get_facebook_product_group_id() );
	}

	/**
	 * Test with nested product group data.
	 */
	public function test_with_nested_product_group_data() {
		$response_data = json_encode( array(
			'id' => '123456789',
			'product_group' => array(
				'id' => '987654321',
				'retailer_id' => 'SKU123',
				'availability' => 'in stock',
				'additional_info' => array(
					'category' => 'Electronics',
					'brand' => 'TestBrand'
				)
			)
		) );
		
		$response = new Response( $response_data );
		
		// Should still correctly extract the product group ID
		$this->assertEquals( '987654321', $response->get_facebook_product_group_id() );
		
		// Verify other data is accessible
		$this->assertEquals( 'SKU123', $response->product_group['retailer_id'] );
		$this->assertEquals( 'in stock', $response->product_group['availability'] );
	}
} 