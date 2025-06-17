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
	 * Test get_ids with valid data containing multiple IDs.
	 */
	public function test_get_ids_with_multiple_ids() {
		$response_data = json_encode( array(
			'data' => array(
				array( 'id' => '123456789' ),
				array( 'id' => '987654321' ),
				array( 'id' => 'abc123def' ),
			)
		) );
		
		$response = new Response( $response_data );
		
		$expected_ids = array( '123456789', '987654321', 'abc123def' );
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
		
		$this->assertEquals( array(), $response->get_ids() );
	}

	/**
	 * Test get_ids with null data field.
	 */
	public function test_get_ids_with_null_data() {
		$response_data = json_encode( array(
			'data' => null
		) );
		
		$response = new Response( $response_data );
		
		$this->assertEquals( array(), $response->get_ids() );
	}

	/**
	 * Test get_ids with items missing id field.
	 */
	public function test_get_ids_with_missing_id_fields() {
		$response_data = json_encode( array(
			'data' => array(
				array( 'id' => '123456789' ),
				array( 'name' => 'Product without ID' ),
				array( 'id' => '987654321' ),
				array( 'other_field' => 'value' ),
			)
		) );
		
		$response = new Response( $response_data );
		
		// Should only return items that have an 'id' field
		$expected_ids = array( '123456789', '987654321' );
		$this->assertEquals( $expected_ids, $response->get_ids() );
	}

	/**
	 * Test get_ids with various ID formats.
	 */
	public function test_get_ids_with_various_formats() {
		$response_data = json_encode( array(
			'data' => array(
				array( 'id' => '123456789' ),
				array( 'id' => 'abc-def-123' ),
				array( 'id' => 'product_group_456' ),
				array( 'id' => '' ), // Empty string ID
				array( 'id' => '0' ), // Zero as string
				array( 'id' => 123 ), // Numeric ID
			)
		) );
		
		$response = new Response( $response_data );
		
		// All IDs including empty string and zero should be returned
		$expected_ids = array( '123456789', 'abc-def-123', 'product_group_456', '', '0', 123 );
		$this->assertEquals( $expected_ids, $response->get_ids() );
	}

	/**
	 * Test get_ids with null id values.
	 */
	public function test_get_ids_with_null_id_values() {
		$response_data = json_encode( array(
			'data' => array(
				array( 'id' => '123456789' ),
				array( 'id' => null ),
				array( 'id' => '987654321' ),
			)
		) );
		
		$response = new Response( $response_data );
		
		// Null IDs should be included
		$expected_ids = array( '123456789', null, '987654321' );
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
		
		// Should return empty array when data is not an array
		$this->assertEquals( array(), $response->get_ids() );
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
		
		$expected_ids = array( '123456789', '987654321' );
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
		
		$this->assertEquals( array(), $response->get_ids() );
	}

	/**
	 * Test with malformed JSON.
	 */
	public function test_malformed_json() {
		$response = new Response( 'invalid json' );
		
		$this->assertEquals( array(), $response->get_ids() );
	}

	/**
	 * Test get_ids preserves order.
	 */
	public function test_get_ids_preserves_order() {
		$response_data = json_encode( array(
			'data' => array(
				array( 'id' => 'third' ),
				array( 'id' => 'first' ),
				array( 'id' => 'second' ),
				array( 'id' => 'fourth' ),
			)
		) );
		
		$response = new Response( $response_data );
		
		// IDs should be returned in the same order as in the response
		$expected_ids = array( 'third', 'first', 'second', 'fourth' );
		$this->assertEquals( $expected_ids, $response->get_ids() );
	}
} 