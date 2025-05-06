<?php
declare( strict_types=1 );

use WooCommerce\Facebook\Checkout;

/**
 * Checkout unit test class.
 */
class CheckoutTest extends WP_UnitTestCase {

    /**
     * Instance of the Checkout class.
     *
     * @var Checkout
     */
    protected $checkout;

    /**
     * Set up the test environment.
     *
     * @return void
     */
    public function setUp(): void {
        parent::setUp();
        $this->checkout = new Checkout();
        $this->checkout->add_checkout_permalink_rewrite_rule();

        flush_rewrite_rules();
    }

    /**
     * Test if hooks are added correctly.
     *
     * @return void
     */
    public function test_hooks_are_added() {
        $this->assertGreaterThan(0, has_action('init', [$this->checkout, 'add_checkout_permalink_rewrite_rule']));
        $this->assertGreaterThan(0, has_filter('query_vars', [$this->checkout, 'add_checkout_permalink_query_var']));
        $this->assertGreaterThan(0, has_filter('template_include', [$this->checkout, 'load_checkout_permalink_template']));
    }

    /**
     * Test if query vars are added correctly.
     *
     * @return void
     */
    public function test_add_checkout_permalink_query_var() {
        $vars = apply_filters('query_vars', []);

        $this->assertContains('fb_checkout', $vars);
        $this->assertContains('products', $vars);
        $this->assertContains('coupon', $vars);
    }

    /**
     * Test if rewrite rules are added correctly.
     *
     * @return void
     */
    public function test_add_checkout_permalink_rewrite_rule() {
        global $wp_rewrite;

        $wp_rewrite->flush_rules();
        $rules = $wp_rewrite->wp_rewrite_rules();

        $this->assertIsArray($rules, 'Rewrite rules should be an array');
        $this->assertArrayHasKey('^fb-checkout/?$', $rules);
        $this->assertEquals('index.php?fb_checkout=1', $rules['^fb-checkout/?$']);
    }

    /**
     * Test if rewrite rules are flushed on activation.
     *
     * @return void
     */
    public function test_flush_rewrite_rules_on_activation() {
        $this->checkout->flush_rewrite_rules_on_activation();
        $this->assertTrue(did_action('flush_rewrite_rules'));
    }

    /**
     * Test if rewrite rules are flushed on deactivation.
     *
     * @return void
     */
    public function test_flush_rewrite_rules_on_deactivation() {
        $this->checkout->flush_rewrite_rules_on_deactivation();
        $this->assertTrue(did_action('flush_rewrite_rules'));
    }
}
