<?php
declare(strict_types=1);


class fbUtilsTest extends \WP_UnitTestCase {

    /** @var \WC_Product */
    private $product;

    public function setUp(): void {
        parent::setUp();
        
        // Create a simple product for testing
        $this->product = new \WC_Product_Simple();
        $this->product->set_name('Test Product');
        $this->product->set_regular_price('10.00');
        $this->product->set_sku('test-product-' . uniqid());
        $this->product->save();
    }

    public function tearDown(): void {
        // Clean up the test product
        if ($this->product && $this->product->get_id()) {
            wp_delete_post($this->product->get_id(), true);
        }
        
        parent::tearDown();
    }

    public function testRemoveHtmlTags() {
        $string = '<p>Hello World!</p>';
        $expectedOutput = 'Hello World!';
        $actualOutput = WC_Facebookcommerce_Utils::clean_string($string, true);
        $this->assertEquals($expectedOutput, $actualOutput);
    } 

    public function testKeepHtmlTags() {
        $string = '<p>Hello World!</p>';
        $expectedOutput = '<p>Hello World!</p>';
        $actualOutput = WC_Facebookcommerce_Utils::clean_string($string, false);
        $this->assertEquals($expectedOutput, $actualOutput);
    }

    public function testReplaceSpecialCharacters() {
        $string = 'Hello &amp; World!';
        $expectedOutput = 'Hello & World!';
        $actualOutput = WC_Facebookcommerce_Utils::clean_string($string, true);
        $this->assertEquals($expectedOutput, $actualOutput);
    }

    public function testEmptyString() {
        $string = '';
        $expectedOutput = '';
        $actualOutput = WC_Facebookcommerce_Utils::clean_string($string, true);
        $this->assertEquals($expectedOutput, $actualOutput);
    }

    public function testNullString() {
        $string = null;
        $expectedOutput = null;
        $actualOutput = WC_Facebookcommerce_Utils::clean_string($string, true);
        $this->assertEquals($expectedOutput, $actualOutput);
    }

    	/**
	 * Test is_woocommerce_attribute_summary method with various inputs
	 */
	public function test_is_woocommerce_attribute_summary() {
		// Test cases that should be detected as attribute summaries
		$attribute_summaries = [
			'1: kids',
			'Size: Large',
			'Color: Red',
			'Size: Large, Color: Red',
			'pa_color: Blue',
			'age_group: adults',
			'1: kids, 2: summer',
			'Brand: Nike, Size: XL, Color: Black',
			'material: cotton',
			'gender: female',
			'123: test',
			'pa_size: medium',
		];

		foreach ($attribute_summaries as $summary) {
			$this->assertTrue(
				\WC_Facebookcommerce_Utils::is_woocommerce_attribute_summary($summary),
				"'{$summary}' should be detected as an attribute summary"
			);
		}

		// Test cases that should NOT be detected as attribute summaries
		$real_descriptions = [
			'This is a genuine product description.',
			'A high-quality item with excellent features.',
			'Features include comfort and style.',
			'Made with premium materials',
			'Perfect for kids and adults alike',
			'Available in multiple sizes',
			'',
			'Short description without colons',
			'This product has: great features', // Contains colon but not in attribute format
			'Description with, commas but no colons',
			'Multi-line description
			with line breaks',
			'Very long description that exceeds typical attribute length and contains various punctuation marks!',
		];

		foreach ($real_descriptions as $description) {
			$this->assertFalse(
				\WC_Facebookcommerce_Utils::is_woocommerce_attribute_summary($description),
				"'{$description}' should NOT be detected as an attribute summary"
			);
		}
	}

	/**
	 * Test is_woocommerce_attribute_summary with edge cases
	 */
	public function test_is_woocommerce_attribute_summary_edge_cases() {
		// Test empty and null values
		$this->assertFalse(\WC_Facebookcommerce_Utils::is_woocommerce_attribute_summary(''));
		$this->assertFalse(\WC_Facebookcommerce_Utils::is_woocommerce_attribute_summary(null));

		// Test whitespace handling
		$this->assertTrue(\WC_Facebookcommerce_Utils::is_woocommerce_attribute_summary('  Size: Large  '));
		$this->assertTrue(\WC_Facebookcommerce_Utils::is_woocommerce_attribute_summary("\tColor: Red\n"));

		// Test complex attribute patterns
		$this->assertTrue(\WC_Facebookcommerce_Utils::is_woocommerce_attribute_summary('Size: Large, Material: Cotton, Color: Blue'));
		$this->assertTrue(\WC_Facebookcommerce_Utils::is_woocommerce_attribute_summary('pa_brand: Nike, pa_size: XL'));

		// Test borderline cases that should be detected
		$this->assertTrue(\WC_Facebookcommerce_Utils::is_woocommerce_attribute_summary('A: B'));
		$this->assertTrue(\WC_Facebookcommerce_Utils::is_woocommerce_attribute_summary('1: 2'));

		// Test cases that look like attributes but aren't
		$this->assertFalse(\WC_Facebookcommerce_Utils::is_woocommerce_attribute_summary('Time: 3:30 PM'));
		$this->assertFalse(\WC_Facebookcommerce_Utils::is_woocommerce_attribute_summary('Ratio: 1:2:3'));
		$this->assertFalse(\WC_Facebookcommerce_Utils::is_woocommerce_attribute_summary('URL: https://example.com'));
	}

	/**
	 * Test is_woocommerce_attribute_summary with numeric attribute names
	 */
	public function test_is_woocommerce_attribute_summary_numeric_attributes() {
		// These should be detected as numeric attribute summaries
		$numeric_attributes = [
			'1: kids',
			'2: adults',
			'123: test',
			'1: value, 2: another',
			'999: long_value_name',
		];

		foreach ($numeric_attributes as $attr) {
			$this->assertTrue(
				\WC_Facebookcommerce_Utils::is_woocommerce_attribute_summary($attr),
				"'{$attr}' should be detected as a numeric attribute summary"
			);
		}
	}

	/**
	 * Test is_woocommerce_attribute_summary with WooCommerce prefix patterns
	 */
	public function test_is_woocommerce_attribute_summary_pa_prefix() {
		// These should be detected as WooCommerce attribute patterns
		$pa_attributes = [
			'pa_color: red',
			'pa_size: large',
			'pa_material: cotton',
			'pa_brand: nike',
			'pa_123: value',
		];

		foreach ($pa_attributes as $attr) {
			$this->assertTrue(
				\WC_Facebookcommerce_Utils::is_woocommerce_attribute_summary($attr),
				"'{$attr}' should be detected as a pa_ attribute summary"
			);
		}
	}

	/**
	 * Test is_woocommerce_attribute_summary with common Facebook attribute names
	 */
	public function test_is_woocommerce_attribute_summary_common_names() {
		// These should be detected as common Facebook attribute patterns
		$common_attributes = [
			'size: large',
			'color: blue',
			'brand: nike',
			'material: cotton',
			'style: modern',
			'type: shirt',
			'gender: unisex',
			'age_group: adult',
			'SIZE: LARGE', // Test case insensitivity
			'Color: Red', // Test mixed case
		];

		foreach ($common_attributes as $attr) {
			$this->assertTrue(
				\WC_Facebookcommerce_Utils::is_woocommerce_attribute_summary($attr),
				"'{$attr}' should be detected as a common attribute summary"
			);
		}
	}

	/**
	 * Test is_woocommerce_attribute_summary with multiple attributes
	 */
	public function test_is_woocommerce_attribute_summary_multiple_attributes() {
		// These should be detected as multi-attribute summaries
		$multi_attributes = [
			'Size: Large, Color: Blue',
			'Brand: Nike, Size: XL, Color: Black',
			'1: kids, 2: summer, 3: outdoor',
			'pa_color: red, pa_size: medium',
			'material: cotton, color: blue, size: large',
			'type: shirt, gender: male, age_group: adult',
		];

		foreach ($multi_attributes as $attr) {
			$this->assertTrue(
				\WC_Facebookcommerce_Utils::is_woocommerce_attribute_summary($attr),
				"'{$attr}' should be detected as a multi-attribute summary"
			);
		}
	}

	/**
	 * Test is_woocommerce_attribute_summary integration with get_fb_short_description
	 */
	public function test_is_woocommerce_attribute_summary_integration() {
		// Create a variation product to test the integration
		$variable_product = WC_Helper_Product::create_variation_product();
		$variation = wc_get_product($variable_product->get_children()[0]);

		// Create a mock post object with attribute summary in post_excerpt
		$post_data = (object) [
			'post_excerpt' => '1: kids, Color: Red',
			'post_content' => 'Real product description',
			'post_title' => 'Test Product'
		];

		// Mock the get_post_data method to return our test data
		$fb_product = new class($variation) extends \WC_Facebook_Product {
			private $mock_post_data;

			public function set_mock_post_data($data) {
				$this->mock_post_data = $data;
			}

			public function get_post_data() {
				return $this->mock_post_data;
			}
		};

		$fb_product->set_mock_post_data($post_data);

		// Test that attribute summary is detected and skipped
		$short_description = $fb_product->get_fb_short_description();
		
		// Should not return the attribute summary
		$this->assertNotEquals('1: kids, Color: Red', $short_description);
		
		// Should return empty string since no valid short description found
		$this->assertEquals('', $short_description);

		// Test with a real description that shouldn't be detected as attribute summary
		$post_data->post_excerpt = 'This is a real product description with features.';
		$fb_product->set_mock_post_data($post_data);
		
		$short_description = $fb_product->get_fb_short_description();
		$this->assertEquals('This is a real product description with features.', $short_description);
	}

	/**
	 * Test is_woocommerce_attribute_summary with malformed patterns
	 */
	public function test_is_woocommerce_attribute_summary_malformed_patterns() {
		// These should NOT be detected as attribute summaries
		$malformed_patterns = [
			': value', // Missing attribute name
			'attribute :', // Missing value
			'attribute: ', // Empty value
			': ', // Both missing
			'no colon here',
			'multiple:colons:here',
			'Space Before: Colon',
			'attribute:value:extra', // Too many parts
		];

		foreach ($malformed_patterns as $pattern) {
			$this->assertFalse(
				\WC_Facebookcommerce_Utils::is_woocommerce_attribute_summary($pattern),
				"'{$pattern}' should NOT be detected as an attribute summary"
			);
		}
	}

	/**
	 * Test get_product_category_ids returns array for valid product
	 */
	public function test_get_product_category_ids_returns_array_for_valid_product() {
		$result = \WC_Facebookcommerce_Utils::get_product_category_ids($this->product->get_id());
		$this->assertIsArray($result);
	}

	/**
	 * Test get_product_category_ids returns empty array for invalid product ID
	 */
	public function test_get_product_category_ids_returns_empty_array_for_invalid_product() {
		$result = \WC_Facebookcommerce_Utils::get_product_category_ids(999999999);
		$this->assertIsArray($result);
		$this->assertEmpty($result);
	}

	/**
	 * Test get_product_category_ids returns category IDs for product with categories
	 */
	public function test_get_product_category_ids_returns_category_ids() {
		// Create a category
		$category_id = wp_insert_term('Test Category', 'product_cat');
		$category_id = $category_id['term_id'];

		// Assign category to product
		$this->product->set_category_ids([$category_id]);
		$this->product->save();

		$result = \WC_Facebookcommerce_Utils::get_product_category_ids($this->product->get_id());

		$this->assertIsArray($result);
		$this->assertContains($category_id, $result);

		// Cleanup
		wp_delete_term($category_id, 'product_cat');
	}
}
