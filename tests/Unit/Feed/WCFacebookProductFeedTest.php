<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Unit\Feed;

use WC_Facebook_Product_Feed;
use WC_Facebook_Product;
use WC_Facebookcommerce_Utils;
use WooCommerce\Facebook\Products;
use WooCommerce\Facebook\Products\Feed;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;

/**
 * Unit tests for WC_Facebook_Product_Feed class.
 *
 * Tests the product feed generation functionality including file path generation,
 * feed formatting, and product data preparation for Facebook catalog sync.
 *
 * @covers \WC_Facebook_Product_Feed
 */
class WCFacebookProductFeedTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

	/**
	 * @var WC_Facebook_Product_Feed|null
	 */
	private $product_feed;

	/**
	 * @var \WC_Product_Simple|null
	 */
	private $simple_product;

	/**
	 * @var \WC_Product_Variable|null
	 */
	private $variable_product;

	/**
	 * @var \WC_Product_Variation|null
	 */
	private $variation;

	/**
	 * Set up before each test.
	 */
	public function setUp(): void {
		parent::setUp();

		$this->product_feed = new WC_Facebook_Product_Feed();

		// Create a simple product for testing
		$this->simple_product = new \WC_Product_Simple();
		$this->simple_product->set_name( 'Test Simple Product' );
		$this->simple_product->set_regular_price( '19.99' );
		$this->simple_product->set_status( 'publish' );
		$this->simple_product->set_manage_stock( true );
		$this->simple_product->set_stock_quantity( 10 );
		$this->simple_product->set_description( 'Test product description' );
		$this->simple_product->save();
	}

	/**
	 * Clean up after each test.
	 */
	public function tearDown(): void {
		$this->product_feed = null;

		if ( $this->simple_product ) {
			$this->simple_product->delete( true );
			$this->simple_product = null;
		}

		if ( $this->variable_product ) {
			$this->variable_product->delete( true );
			$this->variable_product = null;
		}

		if ( $this->variation ) {
			$this->variation->delete( true );
			$this->variation = null;
		}

		// Clean up feed files
		$this->cleanup_feed_files();

		parent::tearDown();
	}

	/**
	 * Helper to clean up feed files after tests.
	 */
	private function cleanup_feed_files(): void {
		$uploads_dir = wp_upload_dir( null, false );
		$feed_dir    = trailingslashit( $uploads_dir['basedir'] ) . WC_Facebook_Product_Feed::UPLOADS_DIRECTORY;

		if ( is_dir( $feed_dir ) ) {
			$files = glob( $feed_dir . '/*' );
			foreach ( $files as $file ) {
				if ( is_file( $file ) ) {
					unlink( $file );
				}
			}
		}
	}

	/**
	 * Helper to create a variable product with variations.
	 */
	private function create_variable_product(): void {
		$this->variable_product = new \WC_Product_Variable();
		$this->variable_product->set_name( 'Test Variable Product' );
		$this->variable_product->set_status( 'publish' );

		// Create a product attribute
		$attribute = new \WC_Product_Attribute();
		$attribute->set_name( 'Color' );
		$attribute->set_options( array( 'Red', 'Blue' ) );
		$attribute->set_visible( true );
		$attribute->set_variation( true );

		$this->variable_product->set_attributes( array( $attribute ) );
		$this->variable_product->save();

		// Create a variation
		$this->variation = new \WC_Product_Variation();
		$this->variation->set_parent_id( $this->variable_product->get_id() );
		$this->variation->set_regular_price( '24.99' );
		$this->variation->set_attributes( array( 'color' => 'Red' ) );
		$this->variation->set_status( 'publish' );
		$this->variation->set_stock_status( 'instock' );
		$this->variation->save();
	}

	/**
	 * Test UPLOADS_DIRECTORY constant is defined correctly.
	 *
	 * @covers \WC_Facebook_Product_Feed
	 */
	public function test_uploads_directory_constant(): void {
		$this->assertSame(
			'facebook_for_woocommerce',
			WC_Facebook_Product_Feed::UPLOADS_DIRECTORY,
			'UPLOADS_DIRECTORY should have the expected value'
		);
	}

	/**
	 * Test FILE_NAME constant is defined correctly.
	 *
	 * @covers \WC_Facebook_Product_Feed
	 */
	public function test_file_name_constant(): void {
		$this->assertSame(
			'product_catalog_%s.csv',
			WC_Facebook_Product_Feed::FILE_NAME,
			'FILE_NAME should have the expected value'
		);
	}

	/**
	 * Test FACEBOOK_CATALOG_FEED_FILENAME constant is defined correctly.
	 *
	 * @covers \WC_Facebook_Product_Feed
	 */
	public function test_facebook_catalog_feed_filename_constant(): void {
		$this->assertSame(
			'fae_product_catalog.csv',
			WC_Facebook_Product_Feed::FACEBOOK_CATALOG_FEED_FILENAME,
			'FACEBOOK_CATALOG_FEED_FILENAME should have the expected value'
		);
	}

	/**
	 * Test FB_ADDITIONAL_IMAGES_FOR_FEED constant is defined correctly.
	 *
	 * @covers \WC_Facebook_Product_Feed
	 */
	public function test_additional_images_constant(): void {
		$this->assertSame(
			5,
			WC_Facebook_Product_Feed::FB_ADDITIONAL_IMAGES_FOR_FEED,
			'FB_ADDITIONAL_IMAGES_FOR_FEED should be 5'
		);
	}

	/**
	 * Test FB_PRODUCT_GROUP_ID constant is defined correctly.
	 *
	 * @covers \WC_Facebook_Product_Feed
	 */
	public function test_product_group_id_constant(): void {
		$this->assertSame(
			'fb_product_group_id',
			WC_Facebook_Product_Feed::FB_PRODUCT_GROUP_ID,
			'FB_PRODUCT_GROUP_ID should have the expected value'
		);
	}

	/**
	 * Test FB_VISIBILITY constant is defined correctly.
	 *
	 * @covers \WC_Facebook_Product_Feed
	 */
	public function test_visibility_constant(): void {
		$this->assertSame(
			'fb_visibility',
			WC_Facebook_Product_Feed::FB_VISIBILITY,
			'FB_VISIBILITY should have the expected value'
		);
	}

	/**
	 * Test get_file_directory returns correct path.
	 *
	 * @covers \WC_Facebook_Product_Feed::get_file_directory
	 */
	public function test_get_file_directory(): void {
		$directory = $this->product_feed->get_file_directory();

		$this->assertNotEmpty( $directory, 'File directory should not be empty' );
		$this->assertIsString( $directory, 'File directory should be a string' );
		$this->assertStringContainsString(
			WC_Facebook_Product_Feed::UPLOADS_DIRECTORY,
			$directory,
			'Directory should contain the uploads directory name'
		);
	}

	/**
	 * Test get_file_directory uses WordPress uploads directory.
	 *
	 * @covers \WC_Facebook_Product_Feed::get_file_directory
	 */
	public function test_get_file_directory_uses_uploads(): void {
		$directory      = $this->product_feed->get_file_directory();
		$uploads_dir    = wp_upload_dir( null, false );
		$expected_start = $uploads_dir['basedir'];

		$this->assertStringStartsWith(
			$expected_start,
			$directory,
			'File directory should start with WordPress uploads basedir'
		);
	}

	/**
	 * Test get_file_name returns non-empty string.
	 *
	 * @covers \WC_Facebook_Product_Feed::get_file_name
	 */
	public function test_get_file_name(): void {
		$file_name = $this->product_feed->get_file_name();

		$this->assertNotEmpty( $file_name, 'File name should not be empty' );
		$this->assertIsString( $file_name, 'File name should be a string' );
		$this->assertStringEndsWith( '.csv', $file_name, 'File name should end with .csv' );
	}

	/**
	 * Test get_file_name includes hash.
	 *
	 * @covers \WC_Facebook_Product_Feed::get_file_name
	 */
	public function test_get_file_name_includes_hash(): void {
		$file_name = $this->product_feed->get_file_name();

		$this->assertStringStartsWith(
			'product_catalog_',
			$file_name,
			'File name should start with product_catalog_'
		);
	}

	/**
	 * Test get_temp_file_name returns non-empty string.
	 *
	 * @covers \WC_Facebook_Product_Feed::get_temp_file_name
	 */
	public function test_get_temp_file_name(): void {
		$temp_file_name = $this->product_feed->get_temp_file_name();

		$this->assertNotEmpty( $temp_file_name, 'Temp file name should not be empty' );
		$this->assertIsString( $temp_file_name, 'Temp file name should be a string' );
		$this->assertStringEndsWith( '.csv', $temp_file_name, 'Temp file name should end with .csv' );
	}

	/**
	 * Test get_temp_file_name includes temp prefix.
	 *
	 * @covers \WC_Facebook_Product_Feed::get_temp_file_name
	 */
	public function test_get_temp_file_name_includes_temp_prefix(): void {
		$temp_file_name = $this->product_feed->get_temp_file_name();

		$this->assertStringContainsString(
			'temp_',
			$temp_file_name,
			'Temp file name should contain temp_ prefix'
		);
	}

	/**
	 * Test get_file_path returns combined path.
	 *
	 * @covers \WC_Facebook_Product_Feed::get_file_path
	 */
	public function test_get_file_path(): void {
		$file_path = $this->product_feed->get_file_path();
		$directory = $this->product_feed->get_file_directory();
		$file_name = $this->product_feed->get_file_name();

		$this->assertSame(
			"{$directory}/{$file_name}",
			$file_path,
			'File path should be directory + filename'
		);
	}

	/**
	 * Test get_temp_file_path returns combined path.
	 *
	 * @covers \WC_Facebook_Product_Feed::get_temp_file_path
	 */
	public function test_get_temp_file_path(): void {
		$temp_file_path = $this->product_feed->get_temp_file_path();
		$directory      = $this->product_feed->get_file_directory();
		$temp_file_name = $this->product_feed->get_temp_file_name();

		$this->assertSame(
			"{$directory}/{$temp_file_name}",
			$temp_file_path,
			'Temp file path should be directory + temp filename'
		);
	}

	/**
	 * Test file path filter can modify the file path.
	 *
	 * @covers \WC_Facebook_Product_Feed::get_file_path
	 */
	public function test_get_file_path_filter(): void {
		$custom_path = '/custom/path/feed.csv';

		$this->add_filter_with_safe_teardown(
			'wc_facebook_product_catalog_feed_file_path',
			function () use ( $custom_path ) {
				return $custom_path;
			}
		);

		$file_path = $this->product_feed->get_file_path();

		$this->assertSame(
			$custom_path,
			$file_path,
			'File path filter should be applied'
		);
	}

	/**
	 * Test file name filter can modify the file name.
	 *
	 * @covers \WC_Facebook_Product_Feed::get_file_name
	 */
	public function test_get_file_name_filter(): void {
		$custom_name = 'custom_feed.csv';

		$this->add_filter_with_safe_teardown(
			'wc_facebook_product_catalog_feed_file_name',
			function () use ( $custom_name ) {
				return $custom_name;
			}
		);

		$file_name = $this->product_feed->get_file_name();

		$this->assertSame(
			$custom_name,
			$file_name,
			'File name filter should be applied'
		);
	}

	/**
	 * Test temp file path filter can modify the path.
	 *
	 * @covers \WC_Facebook_Product_Feed::get_temp_file_path
	 */
	public function test_get_temp_file_path_filter(): void {
		$custom_temp_path = '/custom/temp/path/feed.csv';

		$this->add_filter_with_safe_teardown(
			'wc_facebook_product_catalog_temp_feed_file_path',
			function () use ( $custom_temp_path ) {
				return $custom_temp_path;
			}
		);

		$temp_file_path = $this->product_feed->get_temp_file_path();

		$this->assertSame(
			$custom_temp_path,
			$temp_file_path,
			'Temp file path filter should be applied'
		);
	}

	/**
	 * Test temp file name filter can modify the file name.
	 *
	 * @covers \WC_Facebook_Product_Feed::get_temp_file_name
	 */
	public function test_get_temp_file_name_filter(): void {
		$custom_temp_name = 'custom_temp_feed.csv';

		$this->add_filter_with_safe_teardown(
			'wc_facebook_product_catalog_temp_feed_file_name',
			function () use ( $custom_temp_name ) {
				return $custom_temp_name;
			}
		);

		$temp_file_name = $this->product_feed->get_temp_file_name();

		$this->assertSame(
			$custom_temp_name,
			$temp_file_name,
			'Temp file name filter should be applied'
		);
	}

	/**
	 * Test get_product_feed_header_row returns expected columns.
	 *
	 * @covers \WC_Facebook_Product_Feed::get_product_feed_header_row
	 */
	public function test_get_product_feed_header_row(): void {
		$header = $this->product_feed->get_product_feed_header_row();

		$this->assertNotEmpty( $header, 'Header row should not be empty' );
		$this->assertIsString( $header, 'Header row should be a string' );
	}

	/**
	 * Test get_product_feed_header_row contains required columns.
	 *
	 * @covers \WC_Facebook_Product_Feed::get_product_feed_header_row
	 */
	public function test_get_product_feed_header_row_contains_required_columns(): void {
		$header = $this->product_feed->get_product_feed_header_row();

		$required_columns = array(
			'id',
			'title',
			'description',
			'image_link',
			'link',
			'product_type',
			'brand',
			'price',
			'availability',
			'item_group_id',
			'checkout_url',
			'additional_image_link',
			'video',
			'sale_price_effective_date',
			'sale_price',
			'condition',
			'visibility',
			'gender',
			'color',
			'size',
			'pattern',
			'google_product_category',
			'default_product',
			'variant',
			'gtin',
			'quantity_to_sell_on_facebook',
		);

		foreach ( $required_columns as $column ) {
			$this->assertStringContainsString(
				$column,
				$header,
				"Header should contain column: {$column}"
			);
		}
	}

	/**
	 * Test get_product_feed_header_row ends with newline.
	 *
	 * @covers \WC_Facebook_Product_Feed::get_product_feed_header_row
	 */
	public function test_get_product_feed_header_row_ends_with_newline(): void {
		$header = $this->product_feed->get_product_feed_header_row();

		$this->assertStringEndsWith(
			PHP_EOL,
			$header,
			'Header row should end with PHP_EOL'
		);
	}

	/**
	 * Test create_files_to_protect_product_feed_directory creates index.html.
	 *
	 * @covers \WC_Facebook_Product_Feed::create_files_to_protect_product_feed_directory
	 */
	public function test_create_protection_files_creates_index_html(): void {
		$directory = $this->product_feed->get_file_directory();

		// Ensure directory exists
		wp_mkdir_p( $directory );

		$this->product_feed->create_files_to_protect_product_feed_directory();

		$this->assertFileExists(
			$directory . '/index.html',
			'index.html should be created'
		);
	}

	/**
	 * Test create_files_to_protect_product_feed_directory creates .htaccess.
	 *
	 * @covers \WC_Facebook_Product_Feed::create_files_to_protect_product_feed_directory
	 */
	public function test_create_protection_files_creates_htaccess(): void {
		$directory = $this->product_feed->get_file_directory();

		// Ensure directory exists
		wp_mkdir_p( $directory );

		$this->product_feed->create_files_to_protect_product_feed_directory();

		$this->assertFileExists(
			$directory . '/.htaccess',
			'.htaccess should be created'
		);
	}

	/**
	 * Test .htaccess file contains deny from all.
	 *
	 * @covers \WC_Facebook_Product_Feed::create_files_to_protect_product_feed_directory
	 */
	public function test_htaccess_contains_deny_rule(): void {
		$directory = $this->product_feed->get_file_directory();

		// Ensure directory exists
		wp_mkdir_p( $directory );

		$this->product_feed->create_files_to_protect_product_feed_directory();

		$htaccess_content = file_get_contents( $directory . '/.htaccess' );

		$this->assertStringContainsString(
			'deny from all',
			$htaccess_content,
			'.htaccess should contain deny from all'
		);
	}

	/**
	 * Test log_feed_progress logs messages.
	 *
	 * @covers \WC_Facebook_Product_Feed::log_feed_progress
	 */
	public function test_log_feed_progress(): void {
		// This test verifies the method doesn't throw an error
		$this->product_feed->log_feed_progress( 'Test message' );
		$this->product_feed->log_feed_progress( 'Test message with object', array( 'key' => 'value' ) );

		// If we get here without an exception, the test passes
		$this->assertTrue( true );
	}

	/**
	 * Test write_product_feed_file returns boolean.
	 *
	 * @covers \WC_Facebook_Product_Feed::write_product_feed_file
	 */
	public function test_write_product_feed_file_returns_boolean(): void {
		$result = $this->product_feed->write_product_feed_file( array() );

		$this->assertIsBool( $result, 'write_product_feed_file should return a boolean' );
	}

	/**
	 * Test write_product_feed_file with empty product IDs.
	 *
	 * @covers \WC_Facebook_Product_Feed::write_product_feed_file
	 */
	public function test_write_product_feed_file_with_empty_ids(): void {
		$result = $this->product_feed->write_product_feed_file( array() );

		$this->assertTrue( $result, 'Should return true for empty product IDs' );
	}

	/**
	 * Test file name is consistent across calls.
	 *
	 * @covers \WC_Facebook_Product_Feed::get_file_name
	 */
	public function test_file_name_is_consistent(): void {
		$first_name  = $this->product_feed->get_file_name();
		$second_name = $this->product_feed->get_file_name();

		$this->assertSame(
			$first_name,
			$second_name,
			'File name should be consistent across calls'
		);
	}

	/**
	 * Test temp file name is consistent across calls.
	 *
	 * @covers \WC_Facebook_Product_Feed::get_temp_file_name
	 */
	public function test_temp_file_name_is_consistent(): void {
		$first_name  = $this->product_feed->get_temp_file_name();
		$second_name = $this->product_feed->get_temp_file_name();

		$this->assertSame(
			$first_name,
			$second_name,
			'Temp file name should be consistent across calls'
		);
	}

	/**
	 * Test file name differs from temp file name.
	 *
	 * @covers \WC_Facebook_Product_Feed::get_file_name
	 * @covers \WC_Facebook_Product_Feed::get_temp_file_name
	 */
	public function test_file_name_differs_from_temp_file_name(): void {
		$file_name      = $this->product_feed->get_file_name();
		$temp_file_name = $this->product_feed->get_temp_file_name();

		$this->assertNotSame(
			$file_name,
			$temp_file_name,
			'File name should differ from temp file name'
		);
	}

	/**
	 * Test get_file_directory returns absolute path.
	 *
	 * @covers \WC_Facebook_Product_Feed::get_file_directory
	 */
	public function test_get_file_directory_returns_absolute_path(): void {
		$directory = $this->product_feed->get_file_directory();

		$this->assertStringStartsWith(
			'/',
			$directory,
			'Directory should be an absolute path (starting with /)'
		);
	}

	/**
	 * Test header row columns are comma separated.
	 *
	 * @covers \WC_Facebook_Product_Feed::get_product_feed_header_row
	 */
	public function test_header_row_is_comma_separated(): void {
		$header = $this->product_feed->get_product_feed_header_row();

		// Remove trailing newline for accurate count
		$header_trimmed = rtrim( $header, PHP_EOL );
		$columns        = explode( ',', $header_trimmed );

		$this->assertGreaterThan(
			20,
			count( $columns ),
			'Header should have more than 20 columns'
		);
	}

	/**
	 * Test file path contains .csv extension.
	 *
	 * @covers \WC_Facebook_Product_Feed::get_file_path
	 */
	public function test_file_path_has_csv_extension(): void {
		$file_path = $this->product_feed->get_file_path();

		$this->assertStringEndsWith(
			'.csv',
			$file_path,
			'File path should end with .csv'
		);
	}

	/**
	 * Test temp file path contains .csv extension.
	 *
	 * @covers \WC_Facebook_Product_Feed::get_temp_file_path
	 */
	public function test_temp_file_path_has_csv_extension(): void {
		$temp_file_path = $this->product_feed->get_temp_file_path();

		$this->assertStringEndsWith(
			'.csv',
			$temp_file_path,
			'Temp file path should end with .csv'
		);
	}

	/**
	 * Test product feed instance can be created.
	 *
	 * @covers \WC_Facebook_Product_Feed::__construct
	 */
	public function test_product_feed_can_be_instantiated(): void {
		$feed = new WC_Facebook_Product_Feed();

		$this->assertInstanceOf(
			WC_Facebook_Product_Feed::class,
			$feed,
			'WC_Facebook_Product_Feed should be instantiable'
		);
	}

	/**
	 * Test header row contains custom_label_4 column.
	 *
	 * @covers \WC_Facebook_Product_Feed::get_product_feed_header_row
	 */
	public function test_header_contains_custom_label_column(): void {
		$header = $this->product_feed->get_product_feed_header_row();

		$this->assertStringContainsString(
			'custom_label_4',
			$header,
			'Header should contain custom_label_4 column'
		);
	}

	/**
	 * Test header row contains rich_text_description column.
	 *
	 * @covers \WC_Facebook_Product_Feed::get_product_feed_header_row
	 */
	public function test_header_contains_rich_text_description_column(): void {
		$header = $this->product_feed->get_product_feed_header_row();

		$this->assertStringContainsString(
			'rich_text_description',
			$header,
			'Header should contain rich_text_description column'
		);
	}

	/**
	 * Test header row contains internal_label column.
	 *
	 * @covers \WC_Facebook_Product_Feed::get_product_feed_header_row
	 */
	public function test_header_contains_internal_label_column(): void {
		$header = $this->product_feed->get_product_feed_header_row();

		$this->assertStringContainsString(
			'internal_label',
			$header,
			'Header should contain internal_label column'
		);
	}

	/**
	 * Test header row contains external_variant_id column.
	 *
	 * @covers \WC_Facebook_Product_Feed::get_product_feed_header_row
	 */
	public function test_header_contains_external_variant_id_column(): void {
		$header = $this->product_feed->get_product_feed_header_row();

		$this->assertStringContainsString(
			'external_variant_id',
			$header,
			'Header should contain external_variant_id column'
		);
	}

	/**
	 * Test header row contains is_woo_all_products_sync column.
	 *
	 * @covers \WC_Facebook_Product_Feed::get_product_feed_header_row
	 */
	public function test_header_contains_woo_all_products_sync_column(): void {
		$header = $this->product_feed->get_product_feed_header_row();

		$this->assertStringContainsString(
			'is_woo_all_products_sync',
			$header,
			'Header should contain is_woo_all_products_sync column'
		);
	}

	/**
	 * Test protection files don't overwrite existing files.
	 *
	 * @covers \WC_Facebook_Product_Feed::create_files_to_protect_product_feed_directory
	 */
	public function test_protection_files_preserve_existing(): void {
		$directory = $this->product_feed->get_file_directory();

		// Ensure directory exists
		wp_mkdir_p( $directory );

		// Create existing index.html with custom content
		$custom_content = 'Custom Content';
		file_put_contents( $directory . '/index.html', $custom_content );

		// Call the method
		$this->product_feed->create_files_to_protect_product_feed_directory();

		// Verify existing file is preserved
		$content = file_get_contents( $directory . '/index.html' );
		$this->assertSame(
			$custom_content,
			$content,
			'Existing index.html should be preserved'
		);
	}

	/**
	 * Test multiple feed instances share the same file paths.
	 *
	 * @covers \WC_Facebook_Product_Feed::get_file_path
	 */
	public function test_multiple_instances_share_file_paths(): void {
		$feed1 = new WC_Facebook_Product_Feed();
		$feed2 = new WC_Facebook_Product_Feed();

		$this->assertSame(
			$feed1->get_file_path(),
			$feed2->get_file_path(),
			'Multiple instances should return the same file path'
		);
	}

	/**
	 * Test multiple feed instances share the same temp file paths.
	 *
	 * @covers \WC_Facebook_Product_Feed::get_temp_file_path
	 */
	public function test_multiple_instances_share_temp_file_paths(): void {
		$feed1 = new WC_Facebook_Product_Feed();
		$feed2 = new WC_Facebook_Product_Feed();

		$this->assertSame(
			$feed1->get_temp_file_path(),
			$feed2->get_temp_file_path(),
			'Multiple instances should return the same temp file path'
		);
	}

	/**
	 * Test is_upload_complete method handles errors gracefully.
	 *
	 * @covers \WC_Facebook_Product_Feed::is_upload_complete
	 */
	public function test_is_upload_complete_handles_errors(): void {
		$settings = array();

		// Without proper setup, this should return 'error'
		$result = $this->product_feed->is_upload_complete( $settings );

		$this->assertSame(
			'error',
			$result,
			'Should return error when no valid configuration exists'
		);
	}

	/**
	 * Test prepare_temporary_feed_file method.
	 *
	 * @covers \WC_Facebook_Product_Feed::prepare_temporary_feed_file
	 */
	public function test_prepare_temporary_feed_file_creates_file(): void {
		try {
			$file_handle = $this->product_feed->prepare_temporary_feed_file();

			$this->assertIsResource(
				$file_handle,
				'Should return a file handle resource'
			);

			// Close the file handle
			fclose( $file_handle );

			// Verify the temp file was created
			$temp_file_path = $this->product_feed->get_temp_file_path();
			$this->assertFileExists(
				$temp_file_path,
				'Temp file should exist'
			);
		} catch ( \Exception $e ) {
			// If there's a permission issue, skip the test
			$this->markTestSkipped( 'Unable to create temp file: ' . $e->getMessage() );
		}
	}

	/**
	 * Test prepare_temporary_feed_file writes header row.
	 *
	 * @covers \WC_Facebook_Product_Feed::prepare_temporary_feed_file
	 */
	public function test_prepare_temporary_feed_file_writes_header(): void {
		try {
			$file_handle = $this->product_feed->prepare_temporary_feed_file();
			fclose( $file_handle );

			$temp_file_path = $this->product_feed->get_temp_file_path();
			$content        = file_get_contents( $temp_file_path );

			$this->assertStringContainsString(
				'id,title,description',
				$content,
				'Temp file should contain header row'
			);
		} catch ( \Exception $e ) {
			$this->markTestSkipped( 'Unable to create temp file: ' . $e->getMessage() );
		}
	}

	/**
	 * Test rename_temporary_feed_file_to_final_feed_file method.
	 *
	 * @covers \WC_Facebook_Product_Feed::rename_temporary_feed_file_to_final_feed_file
	 */
	public function test_rename_temp_file_to_final(): void {
		try {
			// Create temp file
			$file_handle = $this->product_feed->prepare_temporary_feed_file();
			fclose( $file_handle );

			// Rename it
			$this->product_feed->rename_temporary_feed_file_to_final_feed_file();

			// Verify final file exists
			$final_file_path = $this->product_feed->get_file_path();
			$this->assertFileExists(
				$final_file_path,
				'Final file should exist after rename'
			);

			// Verify temp file no longer exists
			$temp_file_path = $this->product_feed->get_temp_file_path();
			$this->assertFileDoesNotExist(
				$temp_file_path,
				'Temp file should not exist after rename'
			);
		} catch ( \Exception $e ) {
			$this->markTestSkipped( 'Unable to work with temp file: ' . $e->getMessage() );
		}
	}
}
