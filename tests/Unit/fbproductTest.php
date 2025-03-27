<?php
declare(strict_types=1);


class fbproductTest extends \WooCommerce\Facebook\Tests\Unit\AbstractWPUnitTestWithSafeFiltering {
	private $parent_fb_product;

	/** @var \WC_Product_Simple */
	protected $product;

	/** @var \WC_Facebook_Product */
	protected $fb_product;

	public function setUp(): void {
		parent::setUp();

		// creating a simple product
		$this->product = new \WC_Product_Simple();
		$this->product->set_name('Test Product');
		$this->product->set_regular_price('10');
		$this->product->save();

		$this->fb_product = new WC_Facebook_Product($this->product);
	}

	public function tearDown(): void {
		parent::tearDown();
		$this->product->delete(true);
	}

	/**
	 * Test it gets description from post meta.
	 * @return void
	 */
	public function test_get_fb_description_from_post_meta() {
		$product = WC_Helper_Product::create_simple_product();

		$facebook_product = new \WC_Facebook_Product( $product );
		$facebook_product->set_description( 'fb description' );
		$description = $facebook_product->get_fb_description();

		$this->assertEquals( $description, 'fb description');
	}

	/**
	 * Test it gets description from parent product if it is a variation.
	 * @return void
	 */
	public function test_get_fb_description_variable_product() {
		$variable_product = WC_Helper_Product::create_variation_product();
		$variable_product->set_description('parent description');
		$variable_product->save();

		$parent_fb_product = new \WC_Facebook_Product($variable_product);
		$variation         = wc_get_product($variable_product->get_children()[0]);

		$facebook_product = new \WC_Facebook_Product( $variation, $parent_fb_product );
		$description      = $facebook_product->get_fb_description();
		$this->assertEquals( $description, 'parent description' );

		$variation->set_description( 'variation description' );
		$variation->save();

		$description = $facebook_product->get_fb_description();
		$this->assertEquals( $description, 'variation description' );
	}

	/**
	 * Tests that if no description is found from meta or variation, it gets description from post
	 *
	 * @return void
	 */
	public function test_get_fb_description_from_post_content() {
		$product = WC_Helper_Product::create_simple_product();

		// Gets description from title
		$facebook_product = new \WC_Facebook_Product( $product );
		$description      = $facebook_product->get_fb_description();

		$this->assertEquals( $description, get_post( $product->get_id() )->post_title );

		// Gets description from excerpt (product short description)
		$product->set_short_description( 'short description' );
		$product->save();

		$description = $facebook_product->get_fb_description();
		$this->assertEquals( $description, get_post( $product->get_id() )->post_excerpt );

		// Gets description from content (product description)
		$product->set_description( 'product description' );
		$product->save();

		$description = $facebook_product->get_fb_description();
		$this->assertEquals( $description, get_post( $product->get_id() )->post_content );

		// Gets description from excerpt ignoring content when short mode is set
		add_option(
			WC_Facebookcommerce_Integration::SETTING_PRODUCT_DESCRIPTION_MODE,
			WC_Facebookcommerce_Integration::PRODUCT_DESCRIPTION_MODE_SHORT
		);

		$facebook_product = new \WC_Facebook_Product( $product );
		$description      = $facebook_product->get_fb_description();
		$this->assertEquals( $description, get_post( $product->get_id() )->post_excerpt );
	}

	/**
	 * Test it filters description.
	 * @return void
	 */
	public function test_filter_fb_description() {
		$product = WC_Helper_Product::create_simple_product();
		$facebook_product = new \WC_Facebook_Product( $product );
		$facebook_product->set_description( 'fb description' );

		$filter = $this->add_filter_with_safe_teardown( 'facebook_for_woocommerce_fb_product_description', function( $description ) {
			return 'filtered description';
		});

		$description = $facebook_product->get_fb_description();
		$this->assertEquals( $description, 'filtered description' );

		// Remove the filter early
		$filter->teardown_safely_immediately();

		$description = $facebook_product->get_fb_description();
		$this->assertEquals( $description, 'fb description' );
	}

	/**
	 * Test Data Provider for sale_price related fields
	 */
	public function provide_sale_price_data() {
		return [
			[
				11.5,
				null,
				null,
				1150,
				'11.5 USD',
				'',
				'',
				'',
			],
			[
				0,
				null,
				null,
				0,
				'0 USD',
				'',
				'',
				'',
			],
			[
				null,
				null,
				null,
				0,
				'',
				'',
				'',
				'',
			],
			[
				null,
				'2024-08-08',
				'2024-08-18',
				0,
				'',
				'',
				'',
				'',
			],
			[
				11,
				'2024-08-08',
				null,
				1100,
				'11 USD',
				'2024-08-08T00:00:00+00:00/2038-01-17T23:59+00:00',
				'2024-08-08T00:00:00+00:00',
				'2038-01-17T23:59+00:00',
			],
			[
				11,
				null,
				'2024-08-08',
				1100,
				'11 USD',
				'1970-01-29T00:00+00:00/2024-08-08T00:00:00+00:00',
				'1970-01-29T00:00+00:00',
				'2024-08-08T00:00:00+00:00',
			],
			[
				11,
				'2024-08-08',
				'2024-08-09',
				1100,
				'11 USD',
				'2024-08-08T00:00:00+00:00/2024-08-09T00:00:00+00:00',
				'2024-08-08T00:00:00+00:00',
				'2024-08-09T00:00:00+00:00',
			],
		];
	}

	/**
	 * Test that sale_price related fields are being set correctly while preparing product.
	 *
	 * @dataProvider provide_sale_price_data
	 * @return void
	 */
	public function test_sale_price_and_effective_date(
		$salePrice,
		$sale_price_start_date,
		$sale_price_end_date,
		$expected_sale_price,
		$expected_sale_price_for_batch,
		$expected_sale_price_effective_date,
		$expected_sale_price_start_date,
		$expected_sale_price_end_date
	) {
		$product          = WC_Helper_Product::create_simple_product();
		$facebook_product = new \WC_Facebook_Product( $product );
		$facebook_product->set_sale_price( $salePrice );
		$facebook_product->set_date_on_sale_from( $sale_price_start_date );
		$facebook_product->set_date_on_sale_to( $sale_price_end_date );

		$product_data = $facebook_product->prepare_product( $facebook_product->get_id(), \WC_Facebook_Product::PRODUCT_PREP_TYPE_ITEMS_BATCH );
		$this->assertEquals( $product_data['sale_price'], $expected_sale_price_for_batch );
		$this->assertEquals( $product_data['sale_price_effective_date'], $expected_sale_price_effective_date );

		$product_data = $facebook_product->prepare_product( $facebook_product->get_id(), \WC_Facebook_Product::PRODUCT_PREP_TYPE_FEED );
		$this->assertEquals( $product_data['sale_price'], $expected_sale_price );
		$this->assertEquals( $product_data['sale_price_start_date'], $expected_sale_price_start_date );
		$this->assertEquals( $product_data['sale_price_end_date'], $expected_sale_price_end_date );
	}

	/**
	 * Test quantity_to_sell_on_facebook is populated when manage stock is enabled for simple product
	 * @return void
	 */
	public function test_quantity_to_sell_on_facebook_when_manage_stock_is_on_for_simple_product() {
		$woo_product = WC_Helper_Product::create_simple_product();
		$woo_product->set_manage_stock('yes');
		$woo_product->set_stock_quantity(128);

		$fb_product = new \WC_Facebook_Product( $woo_product );
		$data = $fb_product->prepare_product();

		$this->assertEquals( $data['quantity_to_sell_on_facebook'], 128 );
	}

	/**
	 * Test quantity_to_sell_on_facebook is not populated when manage stock is disabled for simple product
	 * @return void
	 */
	public function test_quantity_to_sell_on_facebook_when_manage_stock_is_off_for_simple_product() {
		$woo_product = WC_Helper_Product::create_simple_product();
		$woo_product->set_manage_stock('no');

		$fb_product = new \WC_Facebook_Product( $woo_product );
		$data = $fb_product->prepare_product();

		$this->assertEquals(isset($data['quantity_to_sell_on_facebook']), false);
	}

	/**
	 * Test quantity_to_sell_on_facebook is populated when manage stock is enabled for variable product
	 * @return void
	 */
	public function test_quantity_to_sell_on_facebook_when_manage_stock_is_on_for_variable_product() {
		$woo_product = WC_Helper_Product::create_variation_product();
		$woo_product->set_manage_stock('yes');
		$woo_product->set_stock_quantity(128);

		$woo_variation = wc_get_product($woo_product->get_children()[0]);
		$woo_variation->set_manage_stock('yes');
		$woo_variation->set_stock_quantity(23);

		$fb_parent_product = new \WC_Facebook_Product($woo_product);
		$fb_product = new \WC_Facebook_Product( $woo_variation, $fb_parent_product );

		$data = $fb_product->prepare_product();

		$this->assertEquals( $data['quantity_to_sell_on_facebook'], 23 );
	}

	/**
	 * Test quantity_to_sell_on_facebook is not populated when manage stock is disabled for variable product and disabled for its parent
	 * @return void
	 */
	public function test_quantity_to_sell_on_facebook_when_manage_stock_is_off_for_variable_product_and_off_for_parent() {
		$woo_product = WC_Helper_Product::create_variation_product();
		$woo_product->set_manage_stock('no');

		$woo_variation = wc_get_product($woo_product->get_children()[0]);
		$woo_product->set_manage_stock('no');

		$fb_parent_product = new \WC_Facebook_Product($woo_product);
		$fb_product = new \WC_Facebook_Product( $woo_variation, $fb_parent_product );

		$data = $fb_product->prepare_product();

		$this->assertEquals(isset($data['quantity_to_sell_on_facebook']), false);
	}

	/**
	 * Test quantity_to_sell_on_facebook is not populated when manage stock is disabled for variable product and enabled for its parent
	 * @return void
	 */
	public function test_quantity_to_sell_on_facebook_when_manage_stock_is_off_for_variable_product_and_on_for_parent() {
		$woo_product = WC_Helper_Product::create_variation_product();
		$woo_product->set_manage_stock('yes');
		$woo_product->set_stock_quantity(128);
		$woo_product->save();

		$woo_variation = wc_get_product($woo_product->get_children()[0]);
		$woo_variation->set_manage_stock('no');
		$woo_variation->save();

		$fb_parent_product = new \WC_Facebook_Product($woo_product);
		$fb_product = new \WC_Facebook_Product( $woo_variation, $fb_parent_product );

		$data = $fb_product->prepare_product();

		$this->assertEquals( $data['quantity_to_sell_on_facebook'], 128 );
	}

	/**
	 * Test GTIN is added for simple product
	 * @return void
	 */
	public function test_gtin_for_simple_product_set() {
		$woo_product = WC_Helper_Product::create_simple_product();
		$woo_product->set_global_unique_id(9504000059446);

		$fb_product = new \WC_Facebook_Product( $woo_product );
		$data = $fb_product->prepare_product();

		$this->assertEquals( $data['gtin'], 9504000059446 );
	}

	/**
	 * Test GTIN is not added for simple product
	 * @return void
	 */
	public function test_gtin_for_simple_product_unset() {
		$woo_product = WC_Helper_Product::create_simple_product();
		$fb_product = new \WC_Facebook_Product( $woo_product );
		$data = $fb_product->prepare_product();
		$this->assertEquals(isset($data['gtin']), false);
	}

	/**
	 * Test GTIN is added for variable product
	 * @return void
	 */
	public function test_gtin_for_variable_product_set() {
		$woo_product = WC_Helper_Product::create_variation_product();
		$woo_variation = wc_get_product($woo_product->get_children()[0]);
		$woo_variation->set_global_unique_id(9504000059446);

		$fb_parent_product = new \WC_Facebook_Product($woo_product);
		$fb_product = new \WC_Facebook_Product( $woo_variation, $fb_parent_product );
		$data = $fb_product->prepare_product();

		$this->assertEquals( $data['gtin'], 9504000059446 );
	}

	/**
	 * Test GTIN is not added for variable product
	 * @return void
	 */
	public function test_gtin_for_variable_product_unset() {
		$woo_product = WC_Helper_Product::create_variation_product();
		$woo_variation = wc_get_product($woo_product->get_children()[0]);

		$fb_parent_product = new \WC_Facebook_Product($woo_product);
		$fb_product = new \WC_Facebook_Product( $woo_variation, $fb_parent_product );
		$data = $fb_product->prepare_product();

		$this->assertEquals(isset($data['gtin']), false);
	}

	/**
	 * Test Data Provider for product category attributes
	 */
	public function provide_category_data()
	{
		return [
			// Only FB attributes
			[
				173,
				array(
				),
				array(
					"size" => "medium",
					"gender" => "female"
				),
				array(
					"size" => "medium",
					"gender" => "female"
				),
			],
			// Only Woo attributes
			[
				173,
				array(
					"size" => "medium",
					"gender" => "female"
				),
				array(
				),
				array(
					"size" => "medium",
					"gender" => "female"
				),
			],
			// Both Woo and FB attributes
			[
				173,
				array(
					"color" => "black",
					"material" => "cotton"
				),
				array(
					"size" => "medium",
					"gender" => "female"
				),
				array(
					"color" => "black",
					"material" => "cotton",
					"size" => "medium",
					"gender" => "female"
				),
			],
			// Woo attributes with space, '-' and different casing of enum attribute
			[
				173,
				array(
					"age group" => "Teen",
					"is-costume" => "yes",
					"Sunglasses Width" => "narrow"
				),
				array(
				),
				array(
					"age_group" => "Teen",
					"is_costume" => "yes",
					"sunglasses_width" => "narrow"
				),
			],
			// FB attributes overriding Woo attributes
			[
				173,
				array(
					"age_group" => "teen",
					"size" => "medium",
				),
				array(
					"age_group" => "toddler",
					"size" => "large",
				),
				array(
					"age_group" => "toddler",
					"size" => "large",
				),
			],
		];
	}

	/**
	 * Test that attribute related fields are being set correctly while preparing product.
	 *
	 * @dataProvider provide_category_data
	 * @return void
	 */
	public function test_enhanced_catalog_fields_from_attributes(
		$category_id,
		$woo_attributes,
		$fb_attributes,
		$expected_attributes
	) {
		$product          = WC_Helper_Product::create_simple_product();
		$product->update_meta_data('_wc_facebook_google_product_category', $category_id);

		// Set Woo attributes
		$attributes = array();
		$position = 0;
		foreach ($woo_attributes as $key => $value) {
			$attribute = new WC_Product_Attribute();
			$attribute->set_id(0);
			$attribute->set_name($key);
			$attribute->set_options(array($value));
			$attribute->set_position($position++);
			$attribute->set_visible(1);
			$attribute->set_variation(0);
			$attributes[] = $attribute;
		}
		$product->set_attributes($attributes);

		// Set FB sttributes
		foreach ($fb_attributes as $key => $value) {
			$product->update_meta_data('_wc_facebook_enhanced_catalog_attributes_'.$key, $value);
		}
		$product->save_meta_data();

		// Prepare Product and validate assertions
		$facebook_product = new \WC_Facebook_Product($product);
		$product_data = $facebook_product->prepare_product(
			$facebook_product->get_id(),
			\WC_Facebook_Product::PRODUCT_PREP_TYPE_ITEMS_BATCH
		);
		$this->assertEquals($product_data['google_product_category'], $category_id);
		foreach ($expected_attributes as $key => $value) {
			$this->assertEquals($product_data[$key], $value);
		}

		$product_data = $facebook_product->prepare_product(
			$facebook_product->get_id(),
			\WC_Facebook_Product::PRODUCT_PREP_TYPE_FEED
		);
		$this->assertEquals($product_data['google_product_category'], $category_id);
		foreach ($expected_attributes as $key => $value) {
			$this->assertEquals($product_data[$key], $value);
		}
	}

	public function test_prepare_product_with_video_field() {
		// Set facebook specific fields
		$video_urls = [
			'https://example.com/video1.mp4',
			'https://example.com/video2.mp4',
		];

		$expected_video_urls = array_map(function($url) {
			return ['url' => $url];
		}, $video_urls);

		update_post_meta($this->product->get_id(), WC_Facebook_Product::FB_PRODUCT_VIDEO, $video_urls);
		$product_data = $this->fb_product->prepare_product(null, WC_Facebook_Product::PRODUCT_PREP_TYPE_ITEMS_BATCH);
		
		$this->assertArrayHasKey('video', $product_data);
		$this->assertEquals($expected_video_urls, $product_data['video']);
	}

	public function test_set_product_video_urls() {
        // Prepare attachment IDs
        $attachment_ids = '123,456';
    
        // Mock get_video_urls_from_attachment_ids function
        $this->fb_product = $this->getMockBuilder(WC_Facebook_Product::class)
            ->setConstructorArgs([$this->product])
            ->setMethods(['get_video_urls_from_attachment_ids'])
            ->getMock();
    
        $this->fb_product->method('get_video_urls_from_attachment_ids')
            ->willReturnCallback(function($id) {
             switch ($id) {
                 case '123':
                     return 'http://example.com/video1.mp4';
                 case '456':
                     return 'http://example.com/video2.mp4';
                 default:
                     return '';
             }
            });
        
        // Set the video URLs in post meta
        $video_urls = array_filter(array_map([$this->fb_product, 'get_video_urls_from_attachment_ids'], explode(',', $attachment_ids)));
        update_post_meta( $this->fb_product->get_id(), WC_Facebook_Product::FB_PRODUCT_VIDEO, $video_urls );
    
        // Get the saved video URLs from post meta
        $saved_video_urls = get_post_meta( $this->product->get_id(), WC_Facebook_Product::FB_PRODUCT_VIDEO, true );
		
        // Assert that the saved video URLs match the expected values
        $this->assertEquals( $saved_video_urls, $video_urls);

		// Assert that the saved video URLs are an array
		$this->assertIsArray($saved_video_urls);

		// Assert that the saved video URLs have the correct count
		$this->assertCount(2, $saved_video_urls);

		// Assert that the saved video URLs do not contain any empty strings
		$this->assertNotContains('', $saved_video_urls);
    }

    public function test_prepare_product_items_batch() {
        // Test the PRODUCT_PREP_TYPE_ITEMS_BATCH preparation type
        $fb_description = 'Facebook specific description';

        update_post_meta($this->product->get_id(), WC_Facebook_Product::FB_PRODUCT_DESCRIPTION, $fb_description);

        $product_data = $this->fb_product->prepare_product(null, WC_Facebook_Product::PRODUCT_PREP_TYPE_ITEMS_BATCH);

        // Also verify the main product data structure for items batch
        $this->assertArrayHasKey('title', $product_data);
        $this->assertArrayHasKey('description', $product_data);
        $this->assertArrayHasKey('image_link', $product_data);
    }

		
	/**
	 * Test it gets rich text description from post meta.
	 * @return void
	 */
	public function test_get_rich_text_description_from_post_meta() {
		$product = WC_Helper_Product::create_simple_product();

		$facebook_product = new \WC_Facebook_Product( $product );
		$facebook_product->set_rich_text_description( 'rich text description' );
		$rich_text_description = $facebook_product->get_rich_text_description();

		$this->assertEquals( $rich_text_description,  'rich text description' );
	}	
	
	/**
	 * Tests for get_rich_text_description() method
	 */
	public function test_get_rich_text_description() {
		// Test 1: Gets rich text description from fb_description if set
		$product = WC_Helper_Product::create_simple_product();
		$facebook_product = new \WC_Facebook_Product($product);
		$facebook_product->set_description('fb description test');
		
		$description = $facebook_product->get_rich_text_description();
		$this->assertEquals('fb description test', $description);

		// Test 2: Gets rich text description from rich_text_description if set
		$facebook_product->set_rich_text_description('<p>rich text description test</p>');
		$description = $facebook_product->get_rich_text_description();
		$this->assertEquals('<p>rich text description test</p>', $description);

		// Test 3: Gets rich text description from post meta
		update_post_meta($product->get_id(), \WC_Facebook_Product::FB_RICH_TEXT_DESCRIPTION, '<p>meta description test</p>');
		$new_facebook_product = new \WC_Facebook_Product($product); // Create new instance to clear cached values
		$description = $new_facebook_product->get_rich_text_description();
		$this->assertEquals('<p>meta description test</p>', $description);

		// Test 4: For variations, gets description from variation first
		$variable_product = WC_Helper_Product::create_variation_product();
		$variation = wc_get_product($variable_product->get_children()[0]);
		$variation->set_description('<p>variation description</p>');
		$variation->save();
		
		$parent_fb_product = new \WC_Facebook_Product($variable_product);
		$facebook_product = new \WC_Facebook_Product($variation, $parent_fb_product);
		$description = $facebook_product->get_rich_text_description();
		$this->assertEquals('<p>variation description</p>', $description);

		// Test 5: Falls back to post content if no other description is set
		$product = WC_Helper_Product::create_simple_product();
		$product->set_description('<p>product content description</p>');
		$product->save();
		
		$facebook_product = new \WC_Facebook_Product($product);
		$description = $facebook_product->get_rich_text_description();
		$this->assertEquals('<p>product content description</p>', $description);

		// Test 6: Falls back to post excerpt if content is empty and sync_short_description is true
		add_option(
			WC_Facebookcommerce_Integration::SETTING_PRODUCT_DESCRIPTION_MODE,
			WC_Facebookcommerce_Integration::PRODUCT_DESCRIPTION_MODE_SHORT
		);
		
		$product->set_description('');
		$product->set_short_description('<p>short description test</p>');
		$product->save();
		
		$facebook_product = new \WC_Facebook_Product($product);
		$description = $facebook_product->get_rich_text_description();
		$this->assertEquals('<p>short description test</p>', $description);

		// Test 7: Applies filters
		$filter = $this->add_filter_with_safe_teardown('facebook_for_woocommerce_fb_rich_text_description', function($description) {
			return '<p>filtered description</p>';
		});
		
		$description = $facebook_product->get_rich_text_description();
		$this->assertEquals('<p>filtered description</p>', $description);
		
		// Remove the filter early
		$filter->teardown_safely_immediately();
		
		delete_option(WC_Facebookcommerce_Integration::SETTING_PRODUCT_DESCRIPTION_MODE);
	}

	/**
	 * Test HTML preservation in rich text description
	 */
	public function test_rich_text_description_html_preservation() {
		$product = WC_Helper_Product::create_simple_product();
		$facebook_product = new \WC_Facebook_Product($product);

		$html_content = '
			<div class="product-description">
				<h2>Product Features</h2>
				<p>This is a <strong>premium</strong> product with:</p>
				<ul>
					<li>Feature 1</li>
					<li>Feature 2</li>
				</ul>
				<table>
					<tr>
						<th>Size</th>
						<th>Color</th>
					</tr>
					<tr>
						<td>Large</td>
						<td>Blue</td>
					</tr>
				</table>
			</div>
		';

		$facebook_product->set_rich_text_description($html_content);
		$description = $facebook_product->get_rich_text_description();
		
		// Test HTML structure is preserved
		$this->assertStringContainsString('<div class="product-description">', $description);
		$this->assertStringContainsString('<h2>', $description);
		$this->assertStringContainsString('<strong>', $description);
		$this->assertStringContainsString('<ul>', $description);
		$this->assertStringContainsString('<li>', $description);
		$this->assertStringContainsString('<table>', $description);
		$this->assertStringContainsString('<tr>', $description);
		$this->assertStringContainsString('<th>', $description);
		$this->assertStringContainsString('<td>', $description);
	}

	/**
	 * Test empty rich text description fallback behavior
	 */
	public function test_empty_rich_text_description_fallback() {
		$product = WC_Helper_Product::create_simple_product();
		$facebook_product = new \WC_Facebook_Product($product);
		
		// Ensure rich_text_description is empty
		$facebook_product->set_rich_text_description('');
		
		// Test fallback to post meta
		update_post_meta($product->get_id(), \WC_Facebook_Product::FB_RICH_TEXT_DESCRIPTION, '<p>fallback description</p>');
		$description = $facebook_product->get_rich_text_description();
		$this->assertEquals('<p>fallback description</p>', $description);
		
		// Test behavior when both rich_text_description and post meta are empty
		delete_post_meta($product->get_id(), \WC_Facebook_Product::FB_RICH_TEXT_DESCRIPTION);
		$description = $facebook_product->get_rich_text_description();
		$this->assertEquals('', $description);
	}

	/**
	 * Test rich text description handling for variable products and variations
	 */
	public function test_rich_text_description_variants() {
		// Create variable product with variation
		$variable_product = WC_Helper_Product::create_variation_product();
		$variation = wc_get_product($variable_product->get_children()[0]);
		
		// Set up parent product
		$parent_fb_product = new \WC_Facebook_Product($variable_product);
		
		// Set the rich text description using post meta for the parent
		update_post_meta($variable_product->get_id(), \WC_Facebook_Product::FB_RICH_TEXT_DESCRIPTION, '<p>parent rich text</p>');
		
		// Test 1: Variation inherits parent's rich text description when empty
		$facebook_product = new \WC_Facebook_Product($variation, $parent_fb_product);
		$description = $facebook_product->get_rich_text_description();
		$this->assertEquals('<p>parent rich text</p>', $description);
		
		// Test 2: Variation uses its own rich text description when set
		$variation_fb_product = new \WC_Facebook_Product($variation, $parent_fb_product);
		$variation_fb_product->set_rich_text_description('<p>variation rich text</p>');
		$description = $variation_fb_product->get_rich_text_description();
		$this->assertEquals('<p>variation rich text</p>', $description);
		
		// // Test 3: Variation uses its post meta when set
		// update_post_meta($variation->get_id(), \WC_Facebook_Product::FB_RICH_TEXT_DESCRIPTION, '<p>variation meta rich text</p>');
		// $new_variation_product = new \WC_Facebook_Product($variation, $parent_fb_product);
		// $description = $new_variation_product->get_rich_text_description();
		// $this->assertEquals('<p>variation meta rich text</p>', $description);
		
		// Test 4: Fallback chain for variations
		delete_post_meta($variation->get_id(), \WC_Facebook_Product::FB_RICH_TEXT_DESCRIPTION);
	}

	/**
	 * Test Brand is added for simple product 
	 * @return void
	 */
	public function test_brand_for_simple_product_set() {
		$woo_product = WC_Helper_Product::create_simple_product();
		$facebook_product = new \WC_Facebook_Product( $woo_product );
		$facebook_product->set_fb_brand('Nike');
		$facebook_product->save();

		$fb_product = new \WC_Facebook_Product( $woo_product );
		$data = $fb_product->prepare_product();

		$this->assertEquals( $data['brand'], 'Nike' );
	}

	/**
	 * Test MPN is added for simple product 
	 * @return void
	 */
	public function test_mpn_for_simple_product_set() {
		$woo_product = WC_Helper_Product::create_simple_product();
		$facebook_product = new \WC_Facebook_Product( $woo_product );
		$facebook_product->set_fb_mpn('123456789');
		$facebook_product->save();

		$fb_product = new \WC_Facebook_Product( $woo_product );
		$data = $fb_product->prepare_product();

		$this->assertEquals( $data['mpn'], '123456789' );
	}

	/**
	 * Test MPN is added for variable product 
	 * @return void
	 */
	public function test_mpn_for_variable_product_set() {
		$woo_product = WC_Helper_Product::create_variation_product();
		$woo_variation = wc_get_product($woo_product->get_children()[0]);
		$facebook_product = new \WC_Facebook_Product( $woo_variation, new \WC_Facebook_Product( $woo_product ) );
		$facebook_product->set_fb_mpn('987654321');
		$facebook_product->save();

		$fb_product = new \WC_Facebook_Product( $woo_variation, new \WC_Facebook_Product( $woo_product ) );
		$data = $fb_product->prepare_product();

		$this->assertEquals( $data['mpn'], '987654321' );
	}

	/**
	 * Test it gets brand from parent product if it is a variation.
	 * @return void
	 */
	public function test_get_fb_brand_variable_products() {
		// Create a variable product and set the brand for the parent
		$variable_product = WC_Helper_Product::create_variation_product();
		$facebook_product_parent = new \WC_Facebook_Product($variable_product);
		$facebook_product_parent->set_fb_brand('Nike');
		$facebook_product_parent->save();

		// Get the variation product
		$variation = wc_get_product($variable_product->get_children()[0]);

		// Create a Facebook product instance for the variation
		$facebook_product_variation = new \WC_Facebook_Product($variation);

		// Retrieve the brand from the variation
		$brand = $facebook_product_variation->get_fb_brand();
		$this->assertEquals($brand, 'Nike');

		// Set a different brand for the variation
		$facebook_product_variation->set_fb_brand('Adidas');
		$facebook_product_variation->save();

		// Retrieve the brand again and check if it reflects the new value
		$brand = $facebook_product_variation->get_fb_brand();
		$this->assertEquals($brand, 'Adidas');
	}

	/**
	 * Test external_update_time is populated
	 * @return void
	 */
	public function test_external_update_time_set() {
		$woo_product = WC_Helper_Product::create_simple_product();

		$timestamp = time();
		$woo_product->set_date_modified($timestamp);

		$fb_product = new \WC_Facebook_Product( $woo_product );
		$data = $fb_product->prepare_product();

		$this->assertEquals( $data['external_update_time'], $timestamp);
	}

	/**
	 * Test external_update_time is not populated
	 * @return void
	 */
	public function test_external_update_time_unset() {
		$woo_product = WC_Helper_Product::create_simple_product();
		$woo_product->set_date_modified(null);

		$fb_product = new \WC_Facebook_Product( $woo_product );
		$data = $fb_product->prepare_product();

		$this->assertEquals(isset($data['external_update_time']), false);
	}
}
