<?php
/** Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */

require_once __DIR__ . '/../../../includes/Feed/FeedUploadUtils.php';

use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;

/**
 * Class FeedUploadUtilsTest
 *
 * Sets up environment to test various logic in FeedUploadUtils
 */
abstract class FeedDataTestBase extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

	/** @var int Shop page ID */
	protected static $shop_page_id;

	/**
	 * Set up the test environment: force pretty permalinks, configure site options,
	 * create a Shop page, and add high–priority filters to force expected URLs.
	 */
	public function setUp(): void {
		parent::setUp();

		\WP_Mock::setUp();

		// Force a pretty permalink structure.
		$this->add_filter_with_safe_teardown('pre_option_permalink_structure', function () {
			return '/%postname%/';
		});

		update_option( 'permalink_structure', '/%postname%/' );
		global $wp_rewrite;
		if ( ! ( $wp_rewrite instanceof WP_Rewrite ) ) {
			$wp_rewrite = new WP_Rewrite();
		}
		$wp_rewrite->init();
		$wp_rewrite->set_permalink_structure( '/%postname%/' );
		flush_rewrite_rules();

		// Set basic site options.
		update_option( 'blogname', 'Test Store' );
		update_option( 'wc_facebook_commerce_merchant_settings_id', '123456789' );
		update_option( 'siteurl', 'https://example.com' );
		update_option( 'home', 'https://example.com' );

		// Create and register the Shop page.
		self::$shop_page_id = self::factory()->post->create( [
			'post_type'   => 'page',
			'post_status' => 'publish',
			'post_title'  => 'Shop',
			'post_name'   => 'shop'
		] );
		update_option( 'woocommerce_shop_page_id', self::$shop_page_id );
		flush_rewrite_rules();

		// Add high–priority filters to force URLs.
		$this->add_filter_with_safe_teardown('woocommerce_get_page_permalink', [ $this, 'forceShopPermalink' ], 9999, 2);
		$this->add_filter_with_safe_teardown('get_permalink', [ $this, 'forceGetPermalink' ], 9999, 2);
		$this->add_filter_with_safe_teardown('post_type_link', [ $this, 'forcePostTypeLink' ], 9999, 3);
		$this->add_filter_with_safe_teardown('woocommerce_product_get_permalink', [ $this, 'forceProductPermalink' ], 9999, 2);
	}

	/**
	 * Clean up filters and rewrite rules.
	 */
	public function tearDown(): void {
		flush_rewrite_rules();
		\WP_Mock::tearDown();
		// No need to manually remove filters, parent tearDown will handle it
		parent::tearDown();
	}

	/**
	 * Helper: Return forced product URL for any product post.
	 *
	 * @param WP_Post $post
	 *
	 * @return string|false
	 */
	private function getForcedProductUrl( WP_Post $post ) {
		if ( 'product' !== $post->post_type || empty( $post->post_name ) ) {
			return false;
		}

		return sprintf( 'https://example.com/product/%s', $post->post_name );
	}

	/**
	 * Force the shop page URL.
	 *
	 * @param string $permalink Original permalink.
	 * @param mixed $page Page identifier.
	 *
	 * @return string
	 */
	public function forceShopPermalink( string $permalink, $page ): string {
		return 'shop' === $page ? 'https://example.com/shop/' : $permalink;
	}

	/**
	 * Force get_permalink() output.
	 *
	 * @param string $url Original URL.
	 * @param WP_Post $post The post object.
	 *
	 * @return string
	 */
	public function forceGetPermalink( string $url, WP_Post $post ): string {
		if ( ! is_object( $post ) ) {
			return $url;
		}
		// Check for Shop page.
		$shop_page_id = absint( get_option( 'woocommerce_shop_page_id' ) );
		if ( absint( $post->ID ) === $shop_page_id ) {
			return 'https://example.com/shop/';
		}
		// Check for forced product URL.
		$forced_url = $this->getForcedProductUrl( $post );

		return $forced_url ? $forced_url : $url;
	}

	/**
	 * Force post_type_link() output for products.
	 *
	 * @param string $url Original URL.
	 * @param WP_Post $post The post object.
	 *
	 * @return string
	 */
	public function forcePostTypeLink( string $url, WP_Post $post ) {
		$forced_url = $this->getForcedProductUrl( $post );

		return $forced_url ?? $url;
	}

	/**
	 * Force WooCommerce product permalink.
	 *
	 * @param string $permalink Original product permalink.
	 * @param WC_Product $product The product object.
	 *
	 * @return string
	 */
	public function forceProductPermalink( string $permalink, WC_Product $product ): string {
		$post       = get_post( $product->get_id() );
		$forced_url = $post ? $this->getForcedProductUrl( $post ) : false;

		return $forced_url ?? $permalink;
	}
}
