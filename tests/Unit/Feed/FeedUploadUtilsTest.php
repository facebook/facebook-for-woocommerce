<?php
/** Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */

require_once __DIR__ . '/../../../includes/Feed/FeedUploadUtils.php';

/**
 * Class FeedUploadUtilsTest
 *
 * Sets up environment to test various logic in FeedUploadUtils
 */
class FeedUploadUtilsTest extends WP_UnitTestCase {

	/** @var int Shop page ID */
	protected static $shop_page_id;

	/**
	 * Set up the test environment: force pretty permalinks, configure site options,
	 * create a Shop page, and add high–priority filters to force expected URLs.
	 */
	public function setUp(): void {
		parent::setUp();

		// Force a pretty permalink structure.
		add_filter( 'pre_option_permalink_structure', function () {
			return '/%postname%/';
		} );
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
		add_filter( 'woocommerce_get_page_permalink', [ $this, 'forceShopPermalink' ], 9999, 2 );
		add_filter( 'get_permalink', [ $this, 'forceGetPermalink' ], 9999, 2 );
		add_filter( 'post_type_link', [ $this, 'forcePostTypeLink' ], 9999, 3 );
		add_filter( 'woocommerce_product_get_permalink', [ $this, 'forceProductPermalink' ], 9999, 2 );
	}

	/**
	 * Clean up filters and rewrite rules.
	 */
	public function tearDown(): void {
		remove_filter( 'woocommerce_get_page_permalink', [ $this, 'forceShopPermalink' ], 9999 );
		remove_filter( 'get_permalink', [ $this, 'forceGetPermalink' ], 9999 );
		remove_filter( 'post_type_link', [ $this, 'forcePostTypeLink' ], 9999 );
		remove_filter( 'woocommerce_product_get_permalink', [ $this, 'forceProductPermalink' ], 9999 );
		flush_rewrite_rules();
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

	/* ------------------ Test Methods ------------------ */

	public function test_get_ratings_and_reviews_data_valid_review() {
		// Create a product.
		$product_id = self::factory()->post->create( [
			'post_type'   => 'product',
			'post_status' => 'publish',
			'post_title'  => 'Test Product',
			'post_name'   => 'test-product'
		] );
		update_post_meta( $product_id, '_sku', 'SKU123' );

		// Create a review comment.
		$comment_id = self::factory()->comment->create( [
			'comment_post_ID' => $product_id,
			'comment_date'    => '2023-10-01 10:00:00',
			'comment_content' => 'Awesome product!',
			'comment_author'  => 'John Doe',
			'user_id'         => 0,
		] );
		update_comment_meta( $comment_id, 'rating', 5 );

		$result = \WooCommerce\Facebook\Feed\FeedUploadUtils::get_ratings_and_reviews_data( [] );

		$expected_review = [
			'aggregator'                      => 'woocommerce',
			'store.name'                      => 'Test Store',
			'store.id'                        => '123456789',
			'store.storeUrls'                 => "['https://example.com/shop/']",
			'review_id'                       => (string) $comment_id,
			'rating'                          => 5,
			'title'                           => null,
			'content'                         => 'Awesome product!',
			'created_at'                      => '2023-10-01 10:00:00',
			'reviewer.name'                   => 'John Doe',
			'reviewer.reviewerID'             => "0",
			'reviewer.isAnonymous'            => 'true',
			'product.name'                    => 'Test Product',
			'product.url'                     => 'https://example.com/product/test-product',
			'product.productIdentifiers.skus' => "['SKU123']",
		];

		$this->assertCount( 1, $result, 'Expected one review returned.' );
		$this->assertEquals( $expected_review, $result[0], 'Review output does not match expected data.' );
	}

	public function test_get_ratings_and_reviews_data_non_product_review() {
		// Create a non-product post.
		$post_id = self::factory()->post->create( [
			'post_type'   => 'post',
			'post_status' => 'publish',
			'post_title'  => 'Non Product Post',
			'post_name'   => 'non-product-post'
		] );

		// Create a comment for the non-product.
		$comment_id = self::factory()->comment->create( [
			'comment_post_ID' => $post_id,
			'comment_date'    => '2023-10-01 10:00:00',
			'comment_content' => 'This comment is not associated with a product.',
			'comment_author'  => 'Jane Doe',
			'user_id'         => 2,
		] );
		update_comment_meta( $comment_id, 'rating', 4 );

		$result = \WooCommerce\Facebook\Feed\FeedUploadUtils::get_ratings_and_reviews_data( [] );
		$this->assertEmpty( $result, 'Expected no review for a non-product comment.' );
	}

	public function test_get_ratings_and_reviews_data_no_rating_review() {
		// Create a product.
		$product_id = self::factory()->post->create( [
			'post_type'   => 'product',
			'post_status' => 'publish',
			'post_title'  => 'Test Product 300',
			'post_name'   => 'test-product-300'
		] );
		update_post_meta( $product_id, '_sku', 'SKU300' );

		// Create a comment without a valid rating.
		$comment_id = self::factory()->comment->create( [
			'comment_post_ID' => $product_id,
			'comment_date'    => '2023-10-01 10:00:00',
			'comment_content' => 'I did not rate this product.',
			'comment_author'  => 'Alice',
			'user_id'         => 3,
		] );

		$result = \WooCommerce\Facebook\Feed\FeedUploadUtils::get_ratings_and_reviews_data( [] );
		$this->assertEmpty( $result, 'Expected no review when rating is missing.' );
	}

	public function test_get_ratings_and_reviews_data_invalid_product() {
		// Create a comment referring to a non-existent product.
		$invalid_product_id = 999999;
		$comment_id         = self::factory()->comment->create( [
			'comment_post_ID' => $invalid_product_id,
			'comment_date'    => '2023-10-01 12:00:00',
			'comment_content' => 'Product does not exist.',
			'comment_author'  => 'Bob',
			'user_id'         => 4,
		] );
		update_comment_meta( $comment_id, 'rating', 3 );

		$result = \WooCommerce\Facebook\Feed\FeedUploadUtils::get_ratings_and_reviews_data( [] );
		$this->assertEmpty( $result, 'Expected no review for comment with invalid product.' );
	}

	public function test_get_coupons_data_valid_coupon_with_target_product() {
		// Create a target product.
		$product1 = new WC_Product_Simple();
		$product1->set_name('Included Product 1');
		$product1->set_slug('included-product-1');
		$product1->set_status('publish');
		$product1->set_sku('product-sku-1');
		$product1->save();
		$included_product1 = $product1->get_id();

		// Create a coupon with a valid coupon code.
		$coupon_id = self::factory()->post->create([
			'post_type'   => 'shop_coupon',
			'post_status' => 'publish',
			'post_title'  => 'COUPON-CODE-1',
		]);
		// Set coupon meta so that it is valid and a percentage discount.
		update_post_meta( $coupon_id, 'discount_type', 'percent' );
		update_post_meta( $coupon_id, 'coupon_amount', '15' ); // 15% discount
		update_post_meta( $coupon_id, 'free_shipping', 'no' );
		update_post_meta( $coupon_id, 'usage_limit', '' );
		update_post_meta( $coupon_id, 'limit_usage_to_x_items', '' );
		update_post_meta( $coupon_id, 'maximum_amount', '' );
		update_post_meta( $coupon_id, 'email_restrictions', array() );
		update_post_meta( $coupon_id, 'product_ids', array( $product1->get_id() ) );

		$query_args = [
			'post_type'      => 'shop_coupon',
			'post_status'    => 'publish',
			'posts_per_page' => -1, // retrieve all items
		];

		$result = \WooCommerce\Facebook\Feed\FeedUploadUtils::get_coupons_data( $query_args );

		// Verify that one coupon is returned.
		$this->assertCount( 1, $result, 'Should have returned one coupon in the feed data.' );
		$coupon_data = $result[0];

		// Build the expected coupon shape according to how FeedUploadUtils outputs the data.
		$expected_coupon = [
			'offer_id'                              => $coupon_id,              // coupon ID as an integer
			'title'                                 => 'coupon-code-1',         // lowercased coupon post title
			'value_type'                            => 'PERCENTAGE',
			'percent_off'                           => '15',                    // as a string
			'fixed_amount_off'                      => '',                      // empty string output
			'application_type'                      => 'BUYER_APPLIED',
			'target_type'                           => 'LINE_ITEM',
			'target_granularity'                    => 'ITEM_LEVEL',
			'target_selection'                      => 'SPECIFIC_PRODUCTS',
			'start_date_time'                       => $coupon_data['start_date_time'], // use the output from the coupon post date/time
			'end_date_time'                         => '',
			'coupon_codes'                          => ['coupon-code-1'],
			'public_coupon_code'                    => '',
			'target_filter'                         => '{"or":[{"retailer_id":{"eq":"product-sku-1_'.$product1->get_id().'"}}]}',
			'target_product_retailer_ids'           => '',
			'target_product_group_retailer_ids'     => '',
			'target_product_set_retailer_ids'       => '',
			'redeem_limit_per_user'                 => 0,
			'min_subtotal'                          => '',
			'min_quantity'                          => '',
			'offer_terms'                           => '',
			'redemption_limit_per_seller'           => 0,
			'target_quantity'                       => '',
			'prerequisite_filter'                   => '',
			'prerequisite_product_retailer_ids'     => '',
			'prerequisite_product_group_retailer_ids' => '',
			'prerequisite_product_set_retailer_ids'   => '',
			'exclude_sale_priced_products'          => false,
			'target_shipping_option_types'          => '',
		];

		// Assert that the coupon data exactly matches the expected shape.
		$this->assertEquals( $expected_coupon, $coupon_data, 'Coupon feed data does not match expected data structure.' );
	}

	public function test_get_coupons_data_coupon_with_included_excluded_products() {
		// Create products for inclusion and exclusion.
		$product1 = new WC_Product_Simple();
		$product1->set_name('Included Product 1');
		$product1->set_slug('included-product-1');
		$product1->set_status('publish');
		$product1->set_sku('product-sku-1');
		$product1->save();
		$included_product1 = $product1->get_id();

		$product2 = new WC_Product_Simple();
		$product2->set_name('Included Product 2');
		$product2->set_slug('included-product-2');
		$product2->set_status('publish');
		$product2->set_sku('product-sku-2');
		$product2->save();
		$included_product2 = $product2->get_id();

		$product3 = new WC_Product_Simple();
		$product3->set_name('Excluded Product');
		$product3->set_slug('excluded-product');
		$product3->set_status('publish');
		$product3->set_sku('product-sku-3');
		$product3->save();
		$excluded_product = $product3->get_id();

		// Create a coupon with both included and excluded product and category restrictions.
		$coupon_id = self::factory()->post->create([
			'post_type'   => 'shop_coupon',
			'post_status' => 'publish',
			'post_title'  => 'COUPON-INCL-EXCL',
		]);
		// Set coupon meta so that it is valid with a percentage discount.
		update_post_meta( $coupon_id, 'discount_type', 'percent' );
		update_post_meta( $coupon_id, 'coupon_amount', '20' ); // 20% discount
		update_post_meta( $coupon_id, 'free_shipping', 'no' );
		update_post_meta( $coupon_id, 'usage_limit', '' );
		update_post_meta( $coupon_id, 'limit_usage_to_x_items', '' );
		update_post_meta( $coupon_id, 'maximum_amount', '' );
		update_post_meta( $coupon_id, 'email_restrictions', array() );
		// Set product restrictions.
		update_post_meta( $coupon_id, 'product_ids', array( $included_product1, $included_product2 ) );
		update_post_meta( $coupon_id, 'exclude_product_ids', array( $excluded_product ) );

		$query_args = [
			'post_type'      => 'shop_coupon',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
		];

		$result = \WooCommerce\Facebook\Feed\FeedUploadUtils::get_coupons_data( $query_args );
		$this->assertCount( 1, $result, 'Should have returned one coupon in the feed data.' );
		$coupon_data = $result[0];

		// Build the expected coupon shape.
		$expected_coupon = [
			'offer_id'                              => $coupon_id,                          // coupon ID as an integer
			'title'                                 => 'coupon-incl-excl',                  // lowercased coupon post title
			'value_type'                            => 'PERCENTAGE',
			'percent_off'                           => '20',                                // as a string
			'fixed_amount_off'                      => '',                                  // empty string output
			'application_type'                      => 'BUYER_APPLIED',
			'target_type'                           => 'LINE_ITEM',
			'target_granularity'                    => 'ITEM_LEVEL',
			'target_selection'                      => 'SPECIFIC_PRODUCTS',
			'start_date_time'                       => $coupon_data['start_date_time'],     // use the generated start date/time
			'end_date_time'                         => '',
			'coupon_codes'                          => ['coupon-incl-excl'],                // coupon_codes as an array containing the title
			'public_coupon_code'                    => '',
			// Here we expect a non-empty target filter due to product and category restrictions.
			'target_filter'                         => '{"and":[{"or":[{"retailer_id":{"eq":"product-sku-1_'.$product1->get_id().'"}},{"retailer_id":{"eq":"product-sku-2_'.$product2->get_id().'"}}]},{"and":[{"retailer_id":{"neq":"product-sku-3_'.$product3->get_id().'"}}]}]}',
			'target_product_retailer_ids'           => '',
			'target_product_group_retailer_ids'     => '',
			'target_product_set_retailer_ids'       => '',
			'redeem_limit_per_user'                 => 0,
			'min_subtotal'                          => '',
			'min_quantity'                          => '',
			'offer_terms'                           => '',
			'redemption_limit_per_seller'           => 0,
			'target_quantity'                       => '',
			'prerequisite_filter'                   => '',
			'prerequisite_product_retailer_ids'     => '',
			'prerequisite_product_group_retailer_ids' => '',
			'prerequisite_product_set_retailer_ids'   => '',
			'exclude_sale_priced_products'          => false,
			'target_shipping_option_types'          => '',
		];

		$this->assertEquals( $expected_coupon, $coupon_data, 'Coupon feed data with included/excluded restrictions does not match expected data structure.' );
	}

	public function test_get_coupons_data_invalid_coupon() {
		// Create a coupon that fails validity for multiple reasons:
		// - No coupon code (empty post_title)
		// - Both free_shipping enabled and a discount amount provided
		// - Has email restrictions
		// - Has both included and excluded product brand restrictions.
		$coupon_id = self::factory()->post->create([
			'post_type'   => 'shop_coupon',
			'post_status' => 'publish',
			'post_title'  => '',  // No code provided
		]);
		update_post_meta( $coupon_id, 'discount_type', 'percent' );
		update_post_meta( $coupon_id, 'coupon_amount', '10' );
		update_post_meta( $coupon_id, 'free_shipping', 'yes' );  // Conflicting: free shipping + amount
		update_post_meta( $coupon_id, 'email_restrictions', array( 'test@example.com' ) );
		update_post_meta( $coupon_id, 'product_brands', array( 'brand1' ) );
		update_post_meta( $coupon_id, 'exclude_product_brands', array( 'brand2' ) );

		$query_args = [
			'post_type'      => 'shop_coupon',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
		];

		$result = \WooCommerce\Facebook\Feed\FeedUploadUtils::get_coupons_data( $query_args );

		// Expect that the coupon is filtered out as invalid and thus not included in the feed.
		$this->assertEmpty( $result, 'Expected no coupon to be returned for an invalid coupon configuration.' );
	}
}
