<?php
/**
 * Test file for variation custom video URL functionality
 *
 * @package FacebookCommerce
 */

namespace WooCommerce\Facebook\Tests\Unit;

/**
 * Test class for variation custom video URLs
 *
 * This test verifies that custom video URLs for product variations are properly:
 * 1. Saved to variation meta data
 * 2. Retrieved when preparing product data
 * 3. Included in the sync payload to Facebook
 * 4. Validated for proper URL format
 */
class VariationCustomVideoUrlTest extends \WP_UnitTestCase {

	/**
	 * Test that variation custom video URL is properly saved and retrieved.
	 *
	 * This test covers the bug report where videos appear as "missing" in Commerce Manager
	 * when a custom video URL is entered for a variation.
	 */
	public function test_variation_custom_video_url_saved_and_retrieved() {
		// Create a variable product with variations
		$variable_product = \WC_Helper_Product::create_variation_product();
		$variation        = wc_get_product( $variable_product->get_children()[0] );

		$custom_url = 'https://example.com/test-variation-video.mp4';

		// Set video source to 'custom'
		$variation->update_meta_data( \WooCommerce\Facebook\Products::PRODUCT_VIDEO_SOURCE_META_KEY, \WooCommerce\Facebook\Products::PRODUCT_VIDEO_SOURCE_CUSTOM );
		
		// Save custom video URL
		$variation->update_meta_data( \WC_Facebook_Product::FB_PRODUCT_VIDEO . '_custom_url', $custom_url );
		$variation->save_meta_data();

		// Verify meta data was saved correctly
		$saved_video_source = $variation->get_meta( \WooCommerce\Facebook\Products::PRODUCT_VIDEO_SOURCE_META_KEY );
		$this->assertEquals( \WooCommerce\Facebook\Products::PRODUCT_VIDEO_SOURCE_CUSTOM, $saved_video_source, 'Video source should be saved as "custom"' );

		$saved_custom_url = $variation->get_meta( \WC_Facebook_Product::FB_PRODUCT_VIDEO . '_custom_url' );
		$this->assertEquals( $custom_url, $saved_custom_url, 'Custom video URL should be saved correctly' );

		// Create Facebook product objects
		$parent_fb_product = new \WC_Facebook_Product( $variable_product );
		$fb_variation      = new \WC_Facebook_Product( $variation, $parent_fb_product );

		// Get all video URLs using the method that's called during sync
		$video_urls = $fb_variation->get_all_video_urls();

		// Verify video URLs are returned correctly
		$this->assertIsArray( $video_urls, 'Video URLs should be an array' );
		$this->assertCount( 1, $video_urls, 'Should have exactly one video URL' );
		$this->assertArrayHasKey( 'url', $video_urls[0], 'Video URL array should have "url" key' );
		$this->assertEquals( $custom_url, $video_urls[0]['url'], 'Video URL should match the saved custom URL' );

		// Clean up
		$variable_product->delete( true );
	}

	/**
	 * Test that variation custom video URL is included in items_batch sync payload.
	 */
	public function test_variation_custom_video_url_in_items_batch_payload() {
		// Create a variable product with variations
		$variable_product = \WC_Helper_Product::create_variation_product();
		$variation        = wc_get_product( $variable_product->get_children()[0] );

		$custom_url = 'https://example.com/test-batch-video.mp4';

		// Set video source to 'custom'
		$variation->update_meta_data( \WooCommerce\Facebook\Products::PRODUCT_VIDEO_SOURCE_META_KEY, \WooCommerce\Facebook\Products::PRODUCT_VIDEO_SOURCE_CUSTOM );
		
		// Save custom video URL
		$variation->update_meta_data( \WC_Facebook_Product::FB_PRODUCT_VIDEO . '_custom_url', $custom_url );
		$variation->save_meta_data();

		// Create Facebook product objects
		$parent_fb_product = new \WC_Facebook_Product( $variable_product );
		$fb_variation      = new \WC_Facebook_Product( $variation, $parent_fb_product );

		// Prepare product for items_batch sync (this is what gets sent to Facebook)
		$product_data = $fb_variation->prepare_product( null, \WC_Facebook_Product::PRODUCT_PREP_TYPE_ITEMS_BATCH );

		// Verify video field is included in the payload
		$this->assertArrayHasKey( 'video', $product_data, 'Product data should include "video" field' );
		$this->assertIsArray( $product_data['video'], 'Video field should be an array' );
		$this->assertCount( 1, $product_data['video'], 'Video array should have exactly one item' );
		$this->assertEquals( $custom_url, $product_data['video'][0]['url'], 'Video URL in payload should match custom URL' );

		// Clean up
		$variable_product->delete( true );
	}

	/**
	 * Test that invalid custom video URLs are rejected.
	 *
	 * This ensures that only valid URLs are sent to Facebook, preventing "missing video" errors.
	 */
	public function test_invalid_custom_video_url_is_rejected() {
		// Create a variable product with variations
		$variable_product = \WC_Helper_Product::create_variation_product();
		$variation        = wc_get_product( $variable_product->get_children()[0] );

		$invalid_url = 'not-a-valid-url';

		// Set video source to 'custom'
		$variation->update_meta_data( \WooCommerce\Facebook\Products::PRODUCT_VIDEO_SOURCE_META_KEY, \WooCommerce\Facebook\Products::PRODUCT_VIDEO_SOURCE_CUSTOM );
		
		// Save invalid video URL (this simulates what would be saved if validation wasn't in place)
		$variation->update_meta_data( \WC_Facebook_Product::FB_PRODUCT_VIDEO . '_custom_url', $invalid_url );
		$variation->save_meta_data();

		// Create Facebook product objects
		$parent_fb_product = new \WC_Facebook_Product( $variable_product );
		$fb_variation      = new \WC_Facebook_Product( $variation, $parent_fb_product );

		// Get all video URLs - invalid URL should be filtered out
		$video_urls = $fb_variation->get_all_video_urls();

		// Verify invalid URL is not included
		$this->assertIsArray( $video_urls, 'Video URLs should be an array' );
		$this->assertEmpty( $video_urls, 'Invalid video URL should be filtered out, resulting in empty array' );

		// Clean up
		$variable_product->delete( true );
	}

	/**
	 * Test that variation falls back to parent video when variation has no custom video.
	 */
	public function test_variation_falls_back_to_parent_video() {
		// Create a variable product with variations
		$variable_product = \WC_Helper_Product::create_variation_product();
		$variation        = wc_get_product( $variable_product->get_children()[0] );

		$parent_video_url = 'https://example.com/parent-video.mp4';

		// Set parent product video source to 'custom'
		$variable_product->update_meta_data( \WooCommerce\Facebook\Products::PRODUCT_VIDEO_SOURCE_META_KEY, \WooCommerce\Facebook\Products::PRODUCT_VIDEO_SOURCE_CUSTOM );
		$variable_product->update_meta_data( \WC_Facebook_Product::FB_PRODUCT_VIDEO . '_custom_url', $parent_video_url );
		$variable_product->save_meta_data();

		// Variation has no video set (video_source is empty)
		// This should trigger fallback to parent video

		// Create Facebook product objects
		$parent_fb_product = new \WC_Facebook_Product( $variable_product );
		$fb_variation      = new \WC_Facebook_Product( $variation, $parent_fb_product );

		// Prepare product - should include parent's video due to fallback
		$product_data = $fb_variation->prepare_product( null, \WC_Facebook_Product::PRODUCT_PREP_TYPE_ITEMS_BATCH );

		// Verify parent video is included via fallback
		$this->assertArrayHasKey( 'video', $product_data, 'Product data should include "video" field from parent' );
		$this->assertIsArray( $product_data['video'], 'Video field should be an array' );
		$this->assertEquals( $parent_video_url, $product_data['video'][0]['url'], 'Should use parent video as fallback' );

		// Clean up
		$variable_product->delete( true );
	}

	/**
	 * Test that variation-specific video takes precedence over parent video.
	 */
	public function test_variation_video_takes_precedence_over_parent() {
		// Create a variable product with variations
		$variable_product = \WC_Helper_Product::create_variation_product();
		$variation        = wc_get_product( $variable_product->get_children()[0] );

		$parent_video_url    = 'https://example.com/parent-video.mp4';
		$variation_video_url = 'https://example.com/variation-video.mp4';

		// Set parent product video
		$variable_product->update_meta_data( \WooCommerce\Facebook\Products::PRODUCT_VIDEO_SOURCE_META_KEY, \WooCommerce\Facebook\Products::PRODUCT_VIDEO_SOURCE_CUSTOM );
		$variable_product->update_meta_data( \WC_Facebook_Product::FB_PRODUCT_VIDEO . '_custom_url', $parent_video_url );
		$variable_product->save_meta_data();

		// Set variation-specific video
		$variation->update_meta_data( \WooCommerce\Facebook\Products::PRODUCT_VIDEO_SOURCE_META_KEY, \WooCommerce\Facebook\Products::PRODUCT_VIDEO_SOURCE_CUSTOM );
		$variation->update_meta_data( \WC_Facebook_Product::FB_PRODUCT_VIDEO . '_custom_url', $variation_video_url );
		$variation->save_meta_data();

		// Create Facebook product objects
		$parent_fb_product = new \WC_Facebook_Product( $variable_product );
		$fb_variation      = new \WC_Facebook_Product( $variation, $parent_fb_product );

		// Get video URLs - should use variation video, not parent
		$video_urls = $fb_variation->get_all_video_urls();

		// Verify variation video takes precedence
		$this->assertIsArray( $video_urls, 'Video URLs should be an array' );
		$this->assertCount( 1, $video_urls, 'Should have exactly one video URL' );
		$this->assertEquals( $variation_video_url, $video_urls[0]['url'], 'Should use variation video, not parent video' );

		// Clean up
		$variable_product->delete( true );
	}
}

