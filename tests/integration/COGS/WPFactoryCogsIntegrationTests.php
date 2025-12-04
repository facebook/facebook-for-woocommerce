<?php

declare(strict_types=1);

namespace WooCommerce\Facebook\Tests\Integration\COGS;

use WooCommerce\Facebook\Tests\Integration\IntegrationTestCase;
use WooCommerce\Facebook\Integrations\CostOfGoods\WPFactoryCogsProvider;
use WooCommerce\Facebook\Integrations\CostOfGoods\CostOfGoods;
use WooCommerce\Facebook\Integrations\IntegrationIsNotAvailableException;
use WC_Product_Variation;
use WC_Product_Variable;
use \Plugin_Upgrader;

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
		$upgrader = new \Plugin_Upgrader();
		$result = $upgrader->install( $plugin_zip['file'] );
		// Check for errors
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

	private function disable_cogs_in_woo_settings() {
		
	}

	public function test_wpfactory() {
		$this->enable_wpfactory_cogs_plugin();
		$instance = new WPFactoryCogsProvider();

		$this->assertTrue($instance->is_available(), 'WPFactory is not active');
	}

	/**
	 * Clean up after each test
	 */
	public function tearDown(): void
	{
		parent::tearDown();
	}
}
