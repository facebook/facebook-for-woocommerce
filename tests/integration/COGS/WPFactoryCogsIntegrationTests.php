<?php

declare(strict_types=1);

namespace WooCommerce\Facebook\Tests\Integration\COGS;

use WooCommerce\Facebook\Tests\Integration\IntegrationTestCase;
use WooCommerce\Facebook\Integrations\CostOfGoods\WPFactoryCogsProvider;
use WooCommerce\Facebook\Integrations\CostOfGoods\CostOfGoods;
use WooCommerce\Facebook\Integrations\IntegrationIsNotAvailableException;
use WC_Product_Variation;
use WC_Product_Variable;

class WPFactoryCogsIntegrationTests extends IntegrationTestCase
{
	/**
	 * Set up test environment
	 */
	public function setUp(): void
	{
		parent::setUp();
		$response = wp_remote_get( 'https://downloads.wordpress.org/plugin/cost-of-goods-for-woocommerce.zip' );
		$plugin_zip = wp_upload_bits( 'cost-of-goods-for-woocommerce.zip', null, wp_remote_retrieve_body( $response ) );
		if ( ! class_exists( 'Plugin_Upgrader ' ) ) {
			require_once ABSPATH . 'wp-admin/includes/admin.php';
		}
		$upgrader = new Plugin_Upgrader();
		$result = $upgrader->install( $plugin_zip['file'] );
		
		if ( is_wp_error( $result ) ) {
			throw new \Exception('Cannot install/enable WPFactory plugin');
		}
		
		$this->disable_facebook_sync();
	}

	private function enable_wpfactory_cogs_plugin() {
		// wc_get_container()
		// ->get( 'Automattic\WooCommerce\Internal\Features\FeaturesController' )
		// ->change_feature_enable('cost_of_goods_sold', true);
		activate_plugin( 'cost-of-goods-for-woocommerce/cost-of-goods-for-woocommerce.php' );
	}

	public function test_wpfactory() {
		$this->enable_wpfactory_cogs_plugin();
		$instance = new WPFactoryCogsProvider();

		$this->assertTrue($instance->is_available(), 'WPFactory is not active');
	}

	public function test_given_wpfactory_cogs_is_disabled_when_wpfactory_provider_is_available_called_then_it_returns_false() {
		$this->assertFalse((new WPFactoryCogsProvider())->is_available(), 'WPFactory COGS is expected to be disabled');
		$this->expectException(IntegrationIsNotAvailableException::class);
		$instance->get_cogs_value($this->create_simple_product());
	}

	public function test_given_wpfactory_cogs_is_enabled_when_wpfactory_provider_is_available_called_then_it_returns_true() {
		$this->enable_cogs_in_woo_settings();
		$this->assertTrue((new WPFactoryCogsProvider())->is_available(), 'WPFactory COGS is expected to be enabled');
	}

	/**
	 * Clean up after each test
	 */
	public function tearDown(): void
	{
		parent::tearDown();
	}
}
