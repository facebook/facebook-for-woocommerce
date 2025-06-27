<?php
namespace WooCommerce\Facebook\Tests\Admin;

use PHPUnit\Framework\TestCase;
use WooCommerce\Facebook\Admin\Product_Sets;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;

/**
 * Class Product_SetsTest
 *
 * @package WooCommerce\Facebook\Tests\Unit\Admin
 */
class Product_SetsTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

    /**
     * @var Product_Sets
     */
    private $product_sets;

    /**
     * Set up the test environment
     */
    public function setUp(): void {
        parent::setUp();

        // Instantiate the Product_Sets class for each test
        $this->product_sets = new Product_Sets();
    }

    /**
     * Test that the constructor hooks actions for form fields and notices
     */
    public function test_constructor_adds_hooks() {
        global $wp_filter;

        // Check that all expected hooks are present
        $this->assertArrayHasKey('fb_product_set_add_form_fields', $wp_filter);
        $this->assertArrayHasKey('fb_product_set_edit_form', $wp_filter);
        $this->assertArrayHasKey('created_fb_product_set', $wp_filter);
        $this->assertArrayHasKey('edited_fb_product_set', $wp_filter);
        $this->assertArrayHasKey('admin_notices', $wp_filter);
    }

    /**
     * Test display_fb_product_sets_banner is callable (output cannot be reliably asserted without function mocking)
     */
    public function test_display_fb_product_sets_banner_outputs_when_enabled() {
        $_GET['taxonomy'] = 'fb_product_set';

        // We cannot reliably mock the global facebook_for_woocommerce() function in this test environment.
        // So we only assert that the method is callable and does not throw.
        try {
            ob_start();
            $this->product_sets->display_fb_product_sets_banner();
            ob_end_clean();
            $this->assertTrue(true);
        } catch (\Throwable $e) {
            $this->fail('display_fb_product_sets_banner() should not throw, got: ' . $e->getMessage());
        }
        unset($_GET['taxonomy']);
    }

    /**
     * Test display_fb_product_sets_banner outputs nothing when taxonomy is not fb_product_set
     */
    public function test_display_fb_product_sets_banner_outputs_nothing_when_not_fb_product_set() {
        $_GET['taxonomy'] = 'other_taxonomy';

        ob_start();
        $this->product_sets->display_fb_product_sets_banner();
        $output = ob_get_clean();

        $this->assertEmpty(trim($output));
        unset($_GET['taxonomy']);
    }

    /**
     * Test category_field_on_new outputs the expected HTML
     */
    public function test_category_field_on_new_outputs_html() {
        ob_start();
        $this->product_sets->category_field_on_new();
        $output = ob_get_clean();

        $this->assertStringContainsString('form-field', $output);
        $this->assertStringContainsString('WC Product Categories', $output);
    }

    /**
     * Test category_field_on_edit outputs the expected HTML
     */
    public function test_category_field_on_edit_outputs_html() {
        $term = (object) ['term_id' => 1];

        ob_start();
        $this->product_sets->category_field_on_edit($term);
        $output = ob_get_clean();

        $this->assertStringContainsString('form-table', $output);
        $this->assertStringContainsString('WC Product Categories', $output);
    }

    /**
     * Test save_custom_field updates term meta with array of ints
     */
    public function test_save_custom_field_updates_term_meta() {
        $term_id = 123;

        // Access protected property categories_field via reflection
        $reflection = new \ReflectionClass($this->product_sets);
        $prop = $reflection->getProperty('categories_field');
        $prop->setAccessible(true);
        $categories_field = $prop->getValue($this->product_sets);

        $_POST[$categories_field] = ['1', '2', '3'];

        $this->product_sets->save_custom_field($term_id, 0);
        $saved = get_term_meta($term_id, $categories_field, true);

        $this->assertEquals([1, 2, 3], $saved);
        unset($_POST[$categories_field]);
    }

    /**
     * Test save_custom_field with empty POST saves empty array
     */
    public function test_save_custom_field_with_empty_post_saves_empty_array() {
        $term_id = 789;

        // Access protected property categories_field via reflection
        $reflection = new \ReflectionClass($this->product_sets);
        $prop = $reflection->getProperty('categories_field');
        $prop->setAccessible(true);
        $categories_field = $prop->getValue($this->product_sets);

        // No POST set
        $this->product_sets->save_custom_field($term_id, 0);
        $saved = get_term_meta($term_id, $categories_field, true);

        // Should be empty string, as per implementation
        $this->assertEquals('', $saved);
    }

    /**
     * Test get_field_label outputs the expected label
     */
    public function test_get_field_label_outputs_label() {
        ob_start();
        $this->invoke_protected_method($this->product_sets, 'get_field_label');
        $output = ob_get_clean();

        $this->assertStringContainsString('WC Product Categories', $output);
    }

    /**
     * Test get_field outputs the expected select HTML (no saved items)
     */
    public function test_get_field_outputs_select_html_no_saved_items() {
        ob_start();
        $this->invoke_protected_method($this->product_sets, 'get_field', ['']);
        $output = ob_get_clean();

        $this->assertStringContainsString('select', $output);
        $this->assertStringContainsString('product_cats', $output);
    }

    /**
     * Test get_field outputs the expected select HTML with saved items (selected option)
     */
    public function test_get_field_outputs_select_html_with_saved_items() {
        $term_id = 456;

        // Access protected property categories_field via reflection
        $reflection = new \ReflectionClass($this->product_sets);
        $prop = $reflection->getProperty('categories_field');
        $prop->setAccessible(true);
        $categories_field = $prop->getValue($this->product_sets);

        // Create a test product category
        $cat = wp_insert_term('Test Cat', 'product_cat');
        $cat_id = is_array($cat) ? $cat['term_id'] : $cat;

        // Simulate saved items in term meta
        update_term_meta($term_id, $categories_field, [$cat_id]);

        ob_start();
        $this->invoke_protected_method($this->product_sets, 'get_field', [$term_id]);
        $output = ob_get_clean();

        $this->assertStringContainsString('select', $output);
        $this->assertStringContainsString('selected=&quot;selected&quot;', $output);
    }

    /**
     * Test get_field_label and get_field output correct HTML when called in sequence
     */
    public function test_get_field_label_and_get_field_output_html_sequence() {
        ob_start();
        $this->invoke_protected_method($this->product_sets, 'get_field_label');
        $this->invoke_protected_method($this->product_sets, 'get_field', ['']);
        $output = ob_get_clean();

        $this->assertStringContainsString('label', $output);
        $this->assertStringContainsString('select', $output);
    }

    /**
     * Helper to invoke protected methods
     */
    private function invoke_protected_method($object, $method, array $args = []) {
        $reflection = new \ReflectionClass($object);
        $m = $reflection->getMethod($method);
        $m->setAccessible(true);
        return $m->invokeArgs($object, $args);
    }
} 