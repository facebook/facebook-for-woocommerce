<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Unit\API\ProductCatalog\ProductFeeds\Read;

use WooCommerce\Facebook\API\ProductCatalog\ProductFeeds\Read\Response;
use WooCommerce\Facebook\API\Response as ApiResponse;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;

/**
 * Unit tests for ProductFeeds Read Response class.
 *
 * @since 3.5.2
 */
class ProductFeedsReadResponseTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

	/**
	 * Test that the class exists and can be instantiated.
	 */
	public function test_class_exists() {
		$this->assertTrue( class_exists( Response::class ) );
	}

	/**
	 * Test that Response extends ApiResponse.
	 */
	public function test_extends_api_response() {
		$response = new Response( '{}' );
		$this->assertInstanceOf( ApiResponse::class, $response );
	}

	/**
	 * Test instantiation and to_string method.
	 */
	public function test_instantiation_and_to_string() {
		$data = json_encode( [ 
			'id' => 'feed_123',
			'data' => [
				'name' => 'Product Feed',
				'schedule' => 'daily'
			]
		] );
		$response = new Response( $data );
		
		$this->assertInstanceOf( Response::class, $response );
		$this->assertEquals( $data, $response->to_string() );
	}

	/**
	 * Test accessing data property with single feed.
	 */
	public function test_data_property_access_single_feed() {
		$feed_data = [
			'id' => '1068839467367301',
			'file_name' => 'WooCommerce Catalog - Feed',
			'name' => 'WooCommerce Catalog - Feed',
			'schedule' => 'DAILY',
			'url' => 'https://example.com/feed.xml',
			'enabled' => true,
			'created_time' => '2023-01-15T10:30:00+0000'
		];
		$data = json_encode( [ 'data' => $feed_data ] );
		$response = new Response( $data );
		
		$this->assertIsArray( $response->data );
		$this->assertEquals( $feed_data, $response->data );
		$this->assertEquals( '1068839467367301', $response->data['id'] );
		$this->assertEquals( 'WooCommerce Catalog - Feed', $response->data['file_name'] );
		$this->assertEquals( 'WooCommerce Catalog - Feed', $response->data['name'] );
		$this->assertEquals( 'DAILY', $response->data['schedule'] );
		$this->assertEquals( 'https://example.com/feed.xml', $response->data['url'] );
		$this->assertTrue( $response->data['enabled'] );
		$this->assertEquals( '2023-01-15T10:30:00+0000', $response->data['created_time'] );
	}

	/**
	 * Test with missing data property.
	 */
	public function test_missing_data_property() {
		$data = json_encode( [ 'id' => 'feed_456' ] );
		$response = new Response( $data );
		
		$this->assertNull( $response->data );
	}

	/**
	 * Test with empty data array.
	 */
	public function test_empty_data_array() {
		$data = json_encode( [ 'data' => [] ] );
		$response = new Response( $data );
		
		$this->assertIsArray( $response->data );
		$this->assertEmpty( $response->data );
	}

	/**
	 * Test with empty object.
	 */
	public function test_empty_object() {
		$response = new Response( '{}' );
		
		$this->assertNull( $response->data );
	}

	/**
	 * Test with multiple feeds and various schedule types.
	 */
	public function test_multiple_feeds_with_various_schedules() {
		$feeds = [
			[
				'id' => 'feed_001',
				'name' => 'Primary Feed',
				'schedule' => 'HOURLY',
				'enabled' => true
			],
			[
				'id' => 'feed_002',
				'name' => 'Secondary Feed',
				'schedule' => 'DAILY',
				'enabled' => false
			],
			[
				'id' => 'feed_003',
				'name' => 'Backup Feed',
				'schedule' => 'WEEKLY',
				'enabled' => true
			],
			[
				'id' => 'feed_004',
				'name' => 'Monthly Feed',
				'schedule' => 'MONTHLY',
				'enabled' => true
			]
		];
		$data = json_encode( [ 'data' => $feeds ] );
		$response = new Response( $data );
		
		$this->assertIsArray( $response->data );
		$this->assertCount( 4, $response->data );
		
		// Test array access
		$this->assertEquals( 'feed_001', $response->data[0]['id'] );
		$this->assertEquals( 'Secondary Feed', $response->data[1]['name'] );
		$this->assertEquals( 'WEEKLY', $response->data[2]['schedule'] );
		$this->assertEquals( 'MONTHLY', $response->data[3]['schedule'] );
		
		// Test schedule variety
		$schedules = array_column( $response->data, 'schedule' );
		$this->assertContains( 'HOURLY', $schedules );
		$this->assertContains( 'DAILY', $schedules );
		$this->assertContains( 'WEEKLY', $schedules );
		$this->assertContains( 'MONTHLY', $schedules );
	}

	/**
	 * Test with pagination data.
	 */
	public function test_with_pagination_data() {
		$data = json_encode( [
			'data' => [
				[ 'id' => 'feed_1', 'name' => 'Feed 1' ],
				[ 'id' => 'feed_2', 'name' => 'Feed 2' ]
			],
			'paging' => [
				'cursors' => [
					'before' => 'BEFORE_CURSOR_STRING',
					'after' => 'AFTER_CURSOR_STRING'
				],
				'next' => 'https://graph.facebook.com/v13.0/catalog/feeds?after=AFTER_CURSOR_STRING'
			]
		] );
		$response = new Response( $data );
		
		$this->assertIsArray( $response->data );
		$this->assertCount( 2, $response->data );
		$this->assertIsArray( $response->paging );
		$this->assertEquals( 'BEFORE_CURSOR_STRING', $response->paging['cursors']['before'] );
		$this->assertEquals( 'AFTER_CURSOR_STRING', $response->paging['cursors']['after'] );
		$this->assertStringContainsString( 'after=AFTER_CURSOR_STRING', $response->paging['next'] );
	}

	/**
	 * Test with special characters in feed names.
	 */
	public function test_special_characters_in_feed_names() {
		$special_name = "Feed & Co. <Special> \"Quotes\" 'Apostrophes'";
		$data = json_encode( [
			'data' => [
				'id' => 'feed_special',
				'name' => $special_name,
				'file_name' => $special_name
			]
		] );
		$response = new Response( $data );
		
		$this->assertEquals( $special_name, $response->data['name'] );
		$this->assertEquals( $special_name, $response->data['file_name'] );
	}

	/**
	 * Test with Unicode characters in feed data.
	 */
	public function test_unicode_characters_in_feed_data() {
		$unicode_name = '商品フィード 🌟 émojis';
		$unicode_url = 'https://example.com/フィード/feed.xml';
		$data = json_encode( [
			'data' => [
				'name' => $unicode_name,
				'url' => $unicode_url
			]
		] );
		$response = new Response( $data );
		
		$this->assertEquals( $unicode_name, $response->data['name'] );
		$this->assertEquals( $unicode_url, $response->data['url'] );
	}

	/**
	 * Test with null values in data.
	 */
	public function test_null_values_in_data() {
		$data = json_encode( [
			'data' => [
				'id' => 'feed_null',
				'name' => null,
				'url' => null,
				'enabled' => null,
				'schedule' => null
			]
		] );
		$response = new Response( $data );
		
		$this->assertEquals( 'feed_null', $response->data['id'] );
		$this->assertNull( $response->data['name'] );
		$this->assertNull( $response->data['url'] );
		$this->assertNull( $response->data['enabled'] );
		$this->assertNull( $response->data['schedule'] );
	}

	/**
	 * Test ArrayAccess interface.
	 */
	public function test_array_access_interface() {
		$data = json_encode( [
			'id' => 'feed_array_test',
			'data' => [
				'name' => 'Test Feed',
				'enabled' => true
			]
		] );
		$response = new Response( $data );
		
		// Test array access
		$this->assertEquals( 'feed_array_test', $response['id'] );
		$this->assertEquals( [ 'name' => 'Test Feed', 'enabled' => true ], $response['data'] );
		
		// Test isset
		$this->assertTrue( isset( $response['id'] ) );
		$this->assertTrue( isset( $response['data'] ) );
		$this->assertFalse( isset( $response['nonexistent'] ) );
	}

	/**
	 * Test inherited get_id method.
	 */
	public function test_get_id_method() {
		$feed_id = 'feed_get_id_test';
		$data = json_encode( [ 'id' => $feed_id ] );
		$response = new Response( $data );
		
		$this->assertEquals( $feed_id, $response->get_id() );
	}

	/**
	 * Test get_id method with missing id.
	 */
	public function test_get_id_method_missing_id() {
		$data = json_encode( [ 'data' => [ 'name' => 'Feed' ] ] );
		$response = new Response( $data );
		
		$this->assertNull( $response->get_id() );
	}

	/**
	 * Test that the class has no additional public methods.
	 */
	public function test_no_additional_public_methods() {
		$reflection = new \ReflectionClass( Response::class );
		$public_methods = $reflection->getMethods( \ReflectionMethod::IS_PUBLIC );
		
		// Filter out inherited methods
		$own_methods = array_filter( $public_methods, function( $method ) {
			return $method->getDeclaringClass()->getName() === Response::class;
		} );
		
		// Should have no methods of its own (empty class extending ApiResponse)
		$this->assertCount( 0, $own_methods );
	}

	/**
	 * Test with additional undocumented properties.
	 */
	public function test_additional_properties() {
		$data = json_encode( [
			'id' => 'feed_extra',
			'data' => [
				'name' => 'Extra Feed',
				'enabled' => true
			],
			'success' => true,
			'timestamp' => '2023-01-01T00:00:00Z',
			'catalog_id' => 'catalog_123'
		] );
		$response = new Response( $data );
		
		// Documented properties
		$this->assertEquals( 'feed_extra', $response->id );
		$this->assertEquals( [ 'name' => 'Extra Feed', 'enabled' => true ], $response->data );
		
		// Additional properties should also be accessible
		$this->assertTrue( $response->success );
		$this->assertEquals( '2023-01-01T00:00:00Z', $response->timestamp );
		$this->assertEquals( 'catalog_123', $response->catalog_id );
	}

	/**
	 * Test with nested configuration data.
	 */
	public function test_nested_configuration_data() {
		$data = json_encode( [
			'data' => [
				'id' => 'feed_nested',
				'name' => 'Nested Config Feed',
				'config' => [
					'delimiter' => ',',
					'encoding' => 'UTF-8',
					'quoted' => true,
					'columns' => [
						'id',
						'title',
						'description',
						'price'
					]
				],
				'filters' => [
					'availability' => 'in stock',
					'condition' => 'new'
				]
			]
		] );
		$response = new Response( $data );
		
		$this->assertEquals( 'feed_nested', $response->data['id'] );
		$this->assertIsArray( $response->data['config'] );
		$this->assertEquals( ',', $response->data['config']['delimiter'] );
		$this->assertEquals( 'UTF-8', $response->data['config']['encoding'] );
		$this->assertTrue( $response->data['config']['quoted'] );
		$this->assertCount( 4, $response->data['config']['columns'] );
		$this->assertIsArray( $response->data['filters'] );
		$this->assertEquals( 'in stock', $response->data['filters']['availability'] );
	}

	/**
	 * Test with various date formats.
	 */
	public function test_various_date_formats() {
		$data = json_encode( [
			'data' => [
				'created_time' => '2023-12-31T23:59:59+0000',
				'updated_time' => '2023-12-31T23:59:59Z',
				'last_upload_time' => '2023-12-31 23:59:59',
				'next_scheduled_upload_time' => 1704067199 // Unix timestamp
			]
		] );
		$response = new Response( $data );
		
		$this->assertEquals( '2023-12-31T23:59:59+0000', $response->data['created_time'] );
		$this->assertEquals( '2023-12-31T23:59:59Z', $response->data['updated_time'] );
		$this->assertEquals( '2023-12-31 23:59:59', $response->data['last_upload_time'] );
		$this->assertEquals( 1704067199, $response->data['next_scheduled_upload_time'] );
	}

	/**
	 * Test with empty string values.
	 */
	public function test_empty_string_values() {
		$data = json_encode( [
			'data' => [
				'name' => '',
				'file_name' => '',
				'url' => '',
				'schedule' => ''
			]
		] );
		$response = new Response( $data );
		
		$this->assertEquals( '', $response->data['name'] );
		$this->assertEquals( '', $response->data['file_name'] );
		$this->assertEquals( '', $response->data['url'] );
		$this->assertEquals( '', $response->data['schedule'] );
	}

	/**
	 * Test with boolean values in data.
	 */
	public function test_boolean_values_in_data() {
		$data = json_encode( [
			'data' => [
				'enabled' => true,
				'is_uploading' => false,
				'has_errors' => false,
				'requires_review' => true,
				'auto_update' => true
			]
		] );
		$response = new Response( $data );
		
		$this->assertTrue( $response->data['enabled'] );
		$this->assertFalse( $response->data['is_uploading'] );
		$this->assertFalse( $response->data['has_errors'] );
		$this->assertTrue( $response->data['requires_review'] );
		$this->assertTrue( $response->data['auto_update'] );
	}

	/**
	 * Test with numeric values.
	 */
	public function test_numeric_values() {
		$data = json_encode( [
			'data' => [
				'product_count' => 1500,
				'upload_rate_per_hour' => 1000,
				'file_size_bytes' => 2500000,
				'success_rate' => 98.5,
				'average_processing_time' => 45.75
			]
		] );
		$response = new Response( $data );
		
		$this->assertEquals( 1500, $response->data['product_count'] );
		$this->assertEquals( 1000, $response->data['upload_rate_per_hour'] );
		$this->assertEquals( 2500000, $response->data['file_size_bytes'] );
		$this->assertEquals( 98.5, $response->data['success_rate'] );
		$this->assertEquals( 45.75, $response->data['average_processing_time'] );
	}

	/**
	 * Test with very long feed ID.
	 */
	public function test_very_long_feed_id() {
		$long_id = '1234567890123456789012345678901234567890';
		$data = json_encode( [
			'data' => [
				'id' => $long_id,
				'name' => 'Feed with long ID'
			]
		] );
		$response = new Response( $data );
		
		$this->assertEquals( $long_id, $response->data['id'] );
	}
} 