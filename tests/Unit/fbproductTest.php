<?php
declare(strict_types=1);


class fbproductTest extends WP_UnitTestCase {
	private $parent_fb_product;

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

		add_filter( 'facebook_for_woocommerce_fb_product_description', function( $description ) {
			return 'filtered description';
		});

		$description = $facebook_product->get_fb_description();
		$this->assertEquals( $description, 'filtered description' );

		remove_all_filters( 'facebook_for_woocommerce_fb_product_description' );

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
		$this->assertEquals($product_data['category'], 173);
		foreach ($expected_attributes as $key => $value) {
			$this->assertEquals($product_data[$key], $value);
		}
	}

    public function test_prepare_product_with_default_fields() {
        // test when no fb specific fields are set
        $product_data = $this->fb_product->prepare_product();

        $this->assertArrayHasKey('custom_fields', $product_data);
        $this->assertEquals(false, $product_data['custom_fields']['has_fb_description']);
        $this->assertEquals(false, $product_data['custom_fields']['has_fb_price']);
        $this->assertEquals(false, $product_data['custom_fields']['has_fb_image']);
    }

    public function test_prepare_product_with_custom_fields() {
        // Set facebook specific fields
        $fb_description = 'Facebook specific description';
        $fb_price = '15';
        $fb_image = 'https:example.com/fb-image.jpg';

        update_post_meta($this->product->get_id(), WC_Facebook_Product::FB_PRODUCT_DESCRIPTION, $fb_description);
        update_post_meta($this->product->get_id(), WC_Facebook_Product::FB_PRODUCT_PRICE, $fb_price);
        update_post_meta($this->product->get_id(), WC_Facebook_Product::FB_PRODUCT_IMAGE, $fb_image);

        $product_data = $this->fb_product->prepare_product();

        $this->assertArrayHasKey('custom_fields', $product_data);
        $this->assertEquals(true, $product_data['custom_fields']['has_fb_description']);
        $this->assertEquals(true, $product_data['custom_fields']['has_fb_price']);
        $this->assertEquals(true, $product_data['custom_fields']['has_fb_image']);
    }

    public function test_prepare_product_with_mixed_fields() {
        // Set only facebook description
        $fb_description = 'Facebook specific description';

        update_post_meta($this->product->get_id(), WC_Facebook_Product::FB_PRODUCT_DESCRIPTION, $fb_description);

        $product_data = $this->fb_product->prepare_product();

        $this->assertArrayHasKey('custom_fields', $product_data);
        $this->assertEquals(true, $product_data['custom_fields']['has_fb_description']);
        $this->assertEquals(false, $product_data['custom_fields']['has_fb_price']);
        $this->assertEquals(false, $product_data['custom_fields']['has_fb_image']);
    }

    public function test_prepare_product_items_batch() {
        // Test the PRODUCT_PREP_TYPE_ITEMS_BATCH preparation type
        $fb_description = 'Facebook specific description';

        update_post_meta($this->product->get_id(), WC_Facebook_Product::FB_PRODUCT_DESCRIPTION, $fb_description);

        $product_data = $this->fb_product->prepare_product(null, WC_Facebook_Product::PRODUCT_PREP_TYPE_ITEMS_BATCH);

        $this->assertArrayHasKey('custom_fields', $product_data);
        $this->assertEquals(true, $product_data['custom_fields']['has_fb_description']);
        $this->assertEquals(false, $product_data['custom_fields']['has_fb_price']);
        $this->assertEquals(false, $product_data['custom_fields']['has_fb_image']);

        // Also verify the main product data structure for items batch
        $this->assertArrayHasKey('title', $product_data);
        $this->assertArrayHasKey('description', $product_data);
        $this->assertArrayHasKey('image_link', $product_data);
    }
}
