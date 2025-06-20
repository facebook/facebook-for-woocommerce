<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Unit\Api\ProductCatalog\ProductGroups\Read;

use WooCommerce\Facebook\API\ProductCatalog\ProductGroups\Read\Response;
use WooCommerce\Facebook\API\Response as ApiResponse;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;

/**
 * Unit tests for ProductCatalog ProductGroups Read Response class.
 *
 * @since 3.5.2
 */
class ProductGroupsReadResponseTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

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
	 * Test get_ids with valid data containing multiple items.
	 */
	public function test_get_ids_with_multiple_items() {
		$response_data = json_encode( array(
			'data' => array(
				array( 
					'id' => '123456789',
					'retailer_id' => 'SKU001'
				),
				array( 
					'id' => '987654321',
					'retailer_id' => 'SKU002'
				),
				array( 
					'id' => 'abc123def',
					'retailer_id' => 'SKU003'
				),
			)
		) );
		
		$response = new Response( $response_data );
		
		$expected_ids = array(
			'SKU001' => '123456789',
			'SKU002' => '987654321',
			'SKU003' => 'abc123def'
		);
		$this->assertEquals( $expected_ids, $response->get_ids() );
	}

	/**
	 * Test get_ids with empty data array.
	 */
	public function test_get_ids_with_empty_data() {
		$response_data = json_encode( array(
			'data' => array()
		) );
		
		$response = new Response( $response_data );
		
		$this->assertEquals( array(), $response->get_ids() );
	}

	/**
	 * Test get_ids with missing data field.
	 */
	public function test_get_ids_with_missing_data() {
		$response_data = json_encode( array(
			'other_field' => 'value'
		) );
		
		$response = new Response( $response_data );
		
		// When data field is missing, $this->data is null, causing foreach error
		// We need to suppress the warning and check the result
		$result = @$response->get_ids();
		$this->assertEquals( array(), $result );
	}

	/**
	 * Test get_ids with null data field.
	 */
	public function test_get_ids_with_null_data() {
		$response_data = json_encode( array(
			'data' => null
		) );
		
		$response = new Response( $response_data );
		
		// When data is null, foreach will throw an error
		// We need to suppress the warning and check the result
		$result = @$response->get_ids();
		$this->assertEquals( array(), $result );
	}

	/**
	 * Test get_ids with items missing retailer_id or id fields.
	 */
	public function test_get_ids_with_missing_fields() {
		$response_data = json_encode( array(
			'data' => array(
				array( 
					'id' => '123456789',
					'retailer_id' => 'SKU001'
				),
				array( 
					'id' => '987654321'
					// Missing retailer_id - will cause PHP notice/warning
				),
				array( 
					'retailer_id' => 'SKU003'
					// Missing id - will cause PHP notice/warning
				),
				array( 
					'other_field' => 'value'
					// Missing both - will cause PHP notice/warning
				),
			)
		) );
		
		$response = new Response( $response_data );
		
		// The implementation doesn't check if fields exist, so it will attempt to access them
		// This causes PHP notices/warnings. We suppress them with @
		$result = @$response->get_ids();
		
		// Despite the warnings, PHP will use null for missing array keys
		$expected = array(
			'SKU001' => '123456789',
			'' => null,  // Last item with missing retailer_id (empty string key) overwrites previous ones
			'SKU003' => null,   // Missing id becomes null value
		);
		$this->assertEquals( $expected, $result );
	}

	/**
	 * Test get_ids with various retailer_id and id formats.
	 */
	public function test_get_ids_with_various_formats() {
		$response_data = json_encode( array(
			'data' => array(
				array( 
					'id' => '123456789',
					'retailer_id' => 'simple_sku'
				),
				array( 
					'id' => 'abc-def-123',
					'retailer_id' => 'SKU-WITH-DASHES'
				),
				array( 
					'id' => 'product_group_456',
					'retailer_id' => 'sku_with_underscores'
				),
				array( 
					'id' => '',
					'retailer_id' => 'empty_id'
				),
				array( 
					'id' => '0',
					'retailer_id' => ''  // Empty retailer_id
				),
				array( 
					'id' => 123,  // Numeric ID
					'retailer_id' => 456  // Numeric retailer_id
				),
			)
		) );
		
		$response = new Response( $response_data );
		
		$expected_ids = array(
			'simple_sku' => '123456789',
			'SKU-WITH-DASHES' => 'abc-def-123',
			'sku_with_underscores' => 'product_group_456',
			'empty_id' => '',
			'' => '0',  // Empty string key is valid
			456 => 123  // Numeric keys are valid
		);
		$this->assertEquals( $expected_ids, $response->get_ids() );
	}

	/**
	 * Test get_ids with null values.
	 */
	public function test_get_ids_with_null_values() {
		$response_data = json_encode( array(
			'data' => array(
				array( 
					'id' => '123456789',
					'retailer_id' => 'SKU001'
				),
				array( 
					'id' => null,
					'retailer_id' => 'SKU002'
				),
				array( 
					'id' => '987654321',
					'retailer_id' => null
				),
			)
		) );
		
		$response = new Response( $response_data );
		
		// The implementation doesn't filter out null values
		$expected_ids = array(
			'SKU001' => '123456789',
			'SKU002' => null,       // null id is kept
			'' => '987654321'       // null retailer_id becomes empty string key
		);
		$this->assertEquals( $expected_ids, $response->get_ids() );
	}

	/**
	 * Test get_ids with non-array data field.
	 */
	public function test_get_ids_with_non_array_data() {
		$response_data = json_encode( array(
			'data' => 'not an array'
		) );
		
		$response = new Response( $response_data );
		
		// When data is not an array, foreach will throw an error
		// We need to suppress the warning and check the result
		$result = @$response->get_ids();
		$this->assertEquals( array(), $result );
	}

	/**
	 * Test with complete response including pagination.
	 */
	public function test_complete_response_with_pagination() {
		$response_data = json_encode( array(
			'data' => array(
				array( 
					'id' => '123456789',
					'retailer_id' => 'SKU123',
					'availability' => 'in stock'
				),
				array( 
					'id' => '987654321',
					'retailer_id' => 'SKU456',
					'availability' => 'out of stock'
				),
			),
			'paging' => array(
				'cursors' => array(
					'before' => 'BEFORE_CURSOR',
					'after' => 'AFTER_CURSOR'
				),
				'next' => 'https://graph.facebook.com/v12.0/...'
			)
		) );
		
		$response = new Response( $response_data );
		
		$expected_ids = array(
			'SKU123' => '123456789',
			'SKU456' => '987654321'
		);
		$this->assertEquals( $expected_ids, $response->get_ids() );
		
		// Verify we can access other response data
		$this->assertIsArray( $response->data );
		$this->assertCount( 2, $response->data );
		$this->assertIsArray( $response->paging );
	}

	/**
	 * Test with empty JSON response.
	 */
	public function test_empty_json_response() {
		$response = new Response( '{}' );
		
		// Empty JSON means no data field, which is null, causing foreach error
		$result = @$response->get_ids();
		$this->assertEquals( array(), $result );
	}

	/**
	 * Test with malformed JSON.
	 */
	public function test_malformed_json() {
		$response = new Response( 'invalid json' );
		
		// Malformed JSON means no data field, which is null, causing foreach error
		$result = @$response->get_ids();
		$this->assertEquals( array(), $result );
	}

	/**
	 * Test get_ids preserves order and handles duplicate retailer_ids.
	 */
	public function test_get_ids_with_duplicate_retailer_ids() {
		$response_data = json_encode( array(
			'data' => array(
				array( 
					'id' => 'first_id',
					'retailer_id' => 'SKU001'
				),
				array( 
					'id' => 'second_id',
					'retailer_id' => 'SKU002'
				),
				array( 
					'id' => 'third_id',
					'retailer_id' => 'SKU001'  // Duplicate retailer_id
				),
				array( 
					'id' => 'fourth_id',
					'retailer_id' => 'SKU003'
				),
			)
		) );
		
		$response = new Response( $response_data );
		
		// Later entries with duplicate retailer_id will overwrite earlier ones
		$expected_ids = array(
			'SKU001' => 'third_id',  // Overwritten by the third entry
			'SKU002' => 'second_id',
			'SKU003' => 'fourth_id'
		);
		$this->assertEquals( $expected_ids, $response->get_ids() );
	}
} 