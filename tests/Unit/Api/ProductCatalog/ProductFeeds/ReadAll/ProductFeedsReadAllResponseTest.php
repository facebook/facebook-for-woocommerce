<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Unit\API\ProductCatalog\ProductFeeds\ReadAll;

use WooCommerce\Facebook\API\ProductCatalog\ProductFeeds\ReadAll\Response;
use WooCommerce\Facebook\API\Response as ApiResponse;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;

/**
 * Unit tests for ProductFeeds ReadAll Response class.
 *
 * @since 3.5.2
 */
class ProductFeedsReadAllResponseTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

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
			'data' => [
				[ 'id' => 'feed_1', 'name' => 'Feed 1' ],
				[ 'id' => 'feed_2', 'name' => 'Feed 2' ]
			]
		] );
		$response = new Response( $data );
		
		$this->assertInstanceOf( Response::class, $response );
		$this->assertEquals( $data, $response->to_string() );
	}

	/**
	 * Test accessing data property with multiple feeds.
	 */
	public function test_data_property_access_multiple_feeds() {
		$feeds = [
			[
				'id' => '1068839467367301',
				'file_name' => 'WooCommerce Catalog - Feed 1',
				'name' => 'Primary Product Feed',
				'schedule' => 'DAILY',
				'url' => 'https://example.com/feed1.xml',
				'enabled' => true
			],
			[
				'id' => '1068839467367302',
				'file_name' => 'WooCommerce Catalog - Feed 2',
				'name' => 'Secondary Product Feed',
				'schedule' => 'HOURLY',
				'url' => 'https://example.com/feed2.xml',
				'enabled' => false
			]
		];
		$data = json_encode( [ 'data' => $feeds ] );
		$response = new Response( $data );
		
		$this->assertIsArray( $response->data );
		$this->assertCount( 2, $response->data );
		$this->assertEquals( $feeds, $response->data );
		
		// Check first feed
		$this->assertEquals( '1068839467367301', $response->data[0]['id'] );
		$this->assertEquals( 'Primary Product Feed', $response->data[0]['name'] );
		$this->assertTrue( $response->data[0]['enabled'] );
		
		// Check second feed
		$this->assertEquals( '1068839467367302', $response->data[1]['id'] );
		$this->assertEquals( 'Secondary Product Feed', $response->data[1]['name'] );
		$this->assertFalse( $response->data[1]['enabled'] );
	}

	/**
	 * Test with missing data property.
	 */
	public function test_missing_data_property() {
		$data = json_encode( [ 'id' => 'response_456' ] );
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
	 * Test with large number of feeds.
	 */
	public function test_large_number_of_feeds() {
		$feeds = [];
		for ( $i = 1; $i <= 100; $i++ ) {
			$feeds[] = [
				'id' => "feed_$i",
				'name' => "Product Feed $i",
				'schedule' => $i % 2 === 0 ? 'DAILY' : 'HOURLY',
				'enabled' => $i % 3 !== 0
			];
		}
		$data = json_encode( [ 'data' => $feeds ] );
		$response = new Response( $data );
		
		$this->assertIsArray( $response->data );
		$this->assertCount( 100, $response->data );
		$this->assertEquals( 'feed_1', $response->data[0]['id'] );
		$this->assertEquals( 'Product Feed 50', $response->data[49]['name'] );
		$this->assertEquals( 'feed_100', $response->data[99]['id'] );
	}

	/**
	 * Test with pagination data.
	 */
	public function test_with_pagination_data() {
		$data = json_encode( [
			'data' => [
				[ 'id' => 'feed_page1_1', 'name' => 'Feed Page 1 Item 1' ],
				[ 'id' => 'feed_page1_2', 'name' => 'Feed Page 1 Item 2' ],
				[ 'id' => 'feed_page1_3', 'name' => 'Feed Page 1 Item 3' ]
			],
			'paging' => [
				'cursors' => [
					'before' => 'QVFIUmJybjEwNU81U29oZAXdmcXl2MEhB',
					'after' => 'QVFIUmJybjEwNU81U29oZAXdmcXl2MEhC'
				],
				'next' => 'https://graph.facebook.com/v13.0/catalog/product_feeds?after=QVFIUmJybjEwNU81U29oZAXdmcXl2MEhC',
				'previous' => 'https://graph.facebook.com/v13.0/catalog/product_feeds?before=QVFIUmJybjEwNU81U29oZAXdmcXl2MEhB'
			]
		] );
		$response = new Response( $data );
		
		$this->assertIsArray( $response->data );
		$this->assertCount( 3, $response->data );
		$this->assertIsArray( $response->paging );
		$this->assertArrayHasKey( 'cursors', $response->paging );
		$this->assertArrayHasKey( 'next', $response->paging );
		$this->assertArrayHasKey( 'previous', $response->paging );
		$this->assertStringContainsString( 'after=', $response->paging['next'] );
		$this->assertStringContainsString( 'before=', $response->paging['previous'] );
	}

	/**
	 * Test with special characters in feed names.
	 */
	public function test_special_characters_in_feed_names() {
		$feeds = [
			[
				'id' => 'feed_special_1',
				'name' => "Feed & Co. <Special> \"Quotes\"",
				'file_name' => "O'Brien's Feed 'Test'"
			],
			[
				'id' => 'feed_special_2',
				'name' => 'Feed with <HTML> tags & entities',
				'file_name' => 'Feed "with" various \'quotes\''
			]
		];
		$data = json_encode( [ 'data' => $feeds ] );
		$response = new Response( $data );
		
		$this->assertEquals( "Feed & Co. <Special> \"Quotes\"", $response->data[0]['name'] );
		$this->assertEquals( "O'Brien's Feed 'Test'", $response->data[0]['file_name'] );
		$this->assertEquals( 'Feed with <HTML> tags & entities', $response->data[1]['name'] );
	}

	/**
	 * Test with Unicode characters in feed data.
	 */
	public function test_unicode_characters_in_feed_data() {
		$feeds = [
			[
				'id' => 'feed_unicode_1',
				'name' => '商品フィード 🌟 émojis',
				'url' => 'https://example.com/フィード/feed1.xml'
			],
			[
				'id' => 'feed_unicode_2',
				'name' => 'Ñoño Feed με ελληνικά',
				'url' => 'https://example.com/商品/feed2.xml'
			]
		];
		$data = json_encode( [ 'data' => $feeds ] );
		$response = new Response( $data );
		
		$this->assertEquals( '商品フィード 🌟 émojis', $response->data[0]['name'] );
		$this->assertEquals( 'https://example.com/フィード/feed1.xml', $response->data[0]['url'] );
		$this->assertEquals( 'Ñoño Feed με ελληνικά', $response->data[1]['name'] );
	}

	/**
	 * Test with null values in feed data.
	 */
	public function test_null_values_in_feed_data() {
		$feeds = [
			[
				'id' => 'feed_null_1',
				'name' => null,
				'url' => null,
				'enabled' => null
			],
			[
				'id' => 'feed_null_2',
				'name' => 'Valid Feed',
				'schedule' => null,
				'file_name' => null
			]
		];
		$data = json_encode( [ 'data' => $feeds ] );
		$response = new Response( $data );
		
		$this->assertEquals( 'feed_null_1', $response->data[0]['id'] );
		$this->assertNull( $response->data[0]['name'] );
		$this->assertNull( $response->data[0]['url'] );
		$this->assertNull( $response->data[0]['enabled'] );
		$this->assertEquals( 'Valid Feed', $response->data[1]['name'] );
		$this->assertNull( $response->data[1]['schedule'] );
	}

	/**
	 * Test with mixed schedule types.
	 */
	public function test_mixed_schedule_types() {
		$feeds = [
			[ 'id' => 'feed_1', 'schedule' => 'HOURLY' ],
			[ 'id' => 'feed_2', 'schedule' => 'DAILY' ],
			[ 'id' => 'feed_3', 'schedule' => 'WEEKLY' ],
			[ 'id' => 'feed_4', 'schedule' => 'MONTHLY' ],
			[ 'id' => 'feed_5', 'schedule' => 'CUSTOM' ],
			[ 'id' => 'feed_6', 'schedule' => null ]
		];
		$data = json_encode( [ 'data' => $feeds ] );
		$response = new Response( $data );
		
		$this->assertCount( 6, $response->data );
		$this->assertEquals( 'HOURLY', $response->data[0]['schedule'] );
		$this->assertEquals( 'DAILY', $response->data[1]['schedule'] );
		$this->assertEquals( 'WEEKLY', $response->data[2]['schedule'] );
		$this->assertEquals( 'MONTHLY', $response->data[3]['schedule'] );
		$this->assertEquals( 'CUSTOM', $response->data[4]['schedule'] );
		$this->assertNull( $response->data[5]['schedule'] );
	}

	/**
	 * Test ArrayAccess interface.
	 */
	public function test_array_access_interface() {
		$data = json_encode( [
			'data' => [
				[ 'id' => 'feed_1', 'name' => 'Test Feed 1' ],
				[ 'id' => 'feed_2', 'name' => 'Test Feed 2' ]
			],
			'success' => true
		] );
		$response = new Response( $data );
		
		// Test array access
		$this->assertIsArray( $response['data'] );
		$this->assertCount( 2, $response['data'] );
		$this->assertTrue( $response['success'] );
		
		// Test isset
		$this->assertTrue( isset( $response['data'] ) );
		$this->assertTrue( isset( $response['success'] ) );
		$this->assertFalse( isset( $response['nonexistent'] ) );
	}

	/**
	 * Test inherited get_id method.
	 */
	public function test_get_id_method() {
		$response_id = 'readall_response_id';
		$data = json_encode( [ 'id' => $response_id ] );
		$response = new Response( $data );
		
		$this->assertEquals( $response_id, $response->get_id() );
	}

	/**
	 * Test get_id method with missing id.
	 */
	public function test_get_id_method_missing_id() {
		$data = json_encode( [ 'data' => [] ] );
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
	 * Test with additional metadata properties.
	 */
	public function test_additional_metadata_properties() {
		$data = json_encode( [
			'data' => [
				[ 'id' => 'feed_1', 'name' => 'Feed 1' ]
			],
			'summary' => [
				'total_count' => 150,
				'active_count' => 120,
				'inactive_count' => 30
			],
			'metadata' => [
				'api_version' => 'v13.0',
				'generated_at' => '2023-01-01T00:00:00Z'
			]
		] );
		$response = new Response( $data );
		
		// Main data
		$this->assertCount( 1, $response->data );
		
		// Additional properties should be accessible
		$this->assertIsArray( $response->summary );
		$this->assertEquals( 150, $response->summary['total_count'] );
		$this->assertEquals( 120, $response->summary['active_count'] );
		$this->assertEquals( 30, $response->summary['inactive_count'] );
		
		$this->assertIsArray( $response->metadata );
		$this->assertEquals( 'v13.0', $response->metadata['api_version'] );
		$this->assertEquals( '2023-01-01T00:00:00Z', $response->metadata['generated_at'] );
	}

	/**
	 * Test with nested feed configuration.
	 */
	public function test_nested_feed_configuration() {
		$feeds = [
			[
				'id' => 'feed_complex',
				'name' => 'Complex Feed',
				'config' => [
					'format' => 'CSV',
					'delimiter' => ',',
					'encoding' => 'UTF-8',
					'columns' => [ 'id', 'title', 'price', 'availability' ]
				],
				'filters' => [
					'conditions' => [
						[ 'field' => 'availability', 'operator' => 'equals', 'value' => 'in stock' ],
						[ 'field' => 'price', 'operator' => 'greater_than', 'value' => 0 ]
					]
				],
				'stats' => [
					'last_upload' => [
						'timestamp' => '2023-12-01T10:00:00Z',
						'items_processed' => 1500,
						'items_succeeded' => 1450,
						'items_failed' => 50
					]
				]
			]
		];
		$data = json_encode( [ 'data' => $feeds ] );
		$response = new Response( $data );
		
		$feed = $response->data[0];
		$this->assertEquals( 'feed_complex', $feed['id'] );
		$this->assertIsArray( $feed['config'] );
		$this->assertEquals( 'CSV', $feed['config']['format'] );
		$this->assertCount( 4, $feed['config']['columns'] );
		$this->assertIsArray( $feed['filters'] );
		$this->assertCount( 2, $feed['filters']['conditions'] );
		$this->assertIsArray( $feed['stats'] );
		$this->assertEquals( 1500, $feed['stats']['last_upload']['items_processed'] );
	}

	/**
	 * Test with various date formats.
	 */
	public function test_various_date_formats() {
		$feeds = [
			[
				'id' => 'feed_dates',
				'created_time' => '2023-12-31T23:59:59+0000',
				'updated_time' => '2023-12-31T23:59:59Z',
				'last_modified' => '2023-12-31 23:59:59',
				'next_run' => 1704067199 // Unix timestamp
			]
		];
		$data = json_encode( [ 'data' => $feeds ] );
		$response = new Response( $data );
		
		$feed = $response->data[0];
		$this->assertEquals( '2023-12-31T23:59:59+0000', $feed['created_time'] );
		$this->assertEquals( '2023-12-31T23:59:59Z', $feed['updated_time'] );
		$this->assertEquals( '2023-12-31 23:59:59', $feed['last_modified'] );
		$this->assertEquals( 1704067199, $feed['next_run'] );
	}

	/**
	 * Test with empty string values.
	 */
	public function test_empty_string_values() {
		$feeds = [
			[
				'id' => 'feed_empty',
				'name' => '',
				'file_name' => '',
				'url' => '',
				'description' => ''
			]
		];
		$data = json_encode( [ 'data' => $feeds ] );
		$response = new Response( $data );
		
		$feed = $response->data[0];
		$this->assertEquals( '', $feed['name'] );
		$this->assertEquals( '', $feed['file_name'] );
		$this->assertEquals( '', $feed['url'] );
		$this->assertEquals( '', $feed['description'] );
	}

	/**
	 * Test with boolean and numeric values.
	 */
	public function test_boolean_and_numeric_values() {
		$feeds = [
			[
				'id' => 'feed_mixed',
				'enabled' => true,
				'is_processing' => false,
				'auto_update' => true,
				'product_count' => 5000,
				'error_count' => 0,
				'success_rate' => 99.5,
				'file_size_mb' => 12.75
			]
		];
		$data = json_encode( [ 'data' => $feeds ] );
		$response = new Response( $data );
		
		$feed = $response->data[0];
		$this->assertTrue( $feed['enabled'] );
		$this->assertFalse( $feed['is_processing'] );
		$this->assertTrue( $feed['auto_update'] );
		$this->assertEquals( 5000, $feed['product_count'] );
		$this->assertEquals( 0, $feed['error_count'] );
		$this->assertEquals( 99.5, $feed['success_rate'] );
		$this->assertEquals( 12.75, $feed['file_size_mb'] );
	}

} 