<?php
/**
 * Facebook Commerce Recommendation Override for /fbcollection/
 * New URL Example: /fbcollection/?clicked_product_id=SKU123_123&shown_product_ids=SKU456_456,SKU789_789
 */

namespace WooCommerce\Facebook;

use WooCommerce\Facebook\Framework\Logger;

defined( 'ABSPATH' ) || exit;

/**
 * Collection Page class for handling /fbcollection/ endpoint
 */
class CollectionPage {

	/** @var string the website url suffix for the collection page */
	const ENDPOINT_PATH='/fbcollection/';

	/** @var string the field that should be used for sync'ing the collection page endpoint */
	const PRODUCT_FEED_FIELD='custom_label_4';

	public function __construct() {
		add_action( 'init', [ $this, 'register_rewrite_rule' ] );
		add_filter( 'query_vars', [ $this, 'add_query_vars' ] );
		add_action( 'woocommerce_product_query', [ $this, 'modify_product_query' ] );
	}

	/**
	 * Register /fbcollection/ as a virtual WooCommerce archive page.
	 */
	public function register_rewrite_rule() {
		add_rewrite_rule( '^fbcollection/?$', 'index.php?post_type=product&custom_fbcollection_page=1', 'top' );
	}

	/**
	 * Add custom query variable.
	 *
	 * @param array $vars Query variables.
	 * @return array
	 */
	public function add_query_vars( $vars ) {
		$vars[] = 'custom_fbcollection_page';
		return $vars;
	}

	/**
	 * Modify WooCommerce product query to inject custom product IDs.
	 *
	 * @param WP_Query $query The WooCommerce product query.
	 */
	public function modify_product_query( $query ) {
		if ( 1 !== intval( get_query_var( 'custom_fbcollection_page' ) ) ) {
			return;
		}

		// Debug logger to verify query filtering triggers
		Logger::log(
			'FBCollection Query Triggered: ' . print_r( $_GET, true ),
			[],
			array(
				'should_send_log_to_meta'        => false,
				'should_save_log_in_woocommerce' => true,
				'woocommerce_log_level'          => \WC_Log_Levels::DEBUG,
			)
		);

		// Helper to extract product_id from retailer_id format
		$extract_product_id = function ( $retailer_id ) {
			if ( false !== strpos( $retailer_id, '_' ) ) {
				$parts = explode( '_', $retailer_id );
				return absint( end( $parts ) );
			}
			return absint( $retailer_id );
		};

		// Parse clicked_product_id
		$clicked_product_id_raw = sanitize_text_field( wp_unslash( $_GET['clicked_product_id'] ?? '' ) );
		$clicked_product_id     = $extract_product_id( $clicked_product_id_raw );

		// Parse shown_product_ids
		$shown_product_ids_raw = explode( ',', sanitize_text_field( wp_unslash( $_GET['shown_product_ids'] ?? '' ) ) );
		$shown_product_ids     = array_map( $extract_product_id, array_map( 'sanitize_text_field', $shown_product_ids_raw ) );
		$shown_product_ids     = array_filter( $shown_product_ids ); // Remove empty/invalid
		$shown_product_ids     = array_slice( $shown_product_ids, 0, 30 ); // Limit to 30

		$final_product_ids = [];

		if ( $clicked_product_id && 'product' === get_post_type( $clicked_product_id ) ) {
			$final_product_ids[] = $clicked_product_id;
		}

		$valid_shown_ids = array_filter(
			$shown_product_ids,
			function ( $id ) use ( $clicked_product_id ) {
				if ( $clicked_product_id === $id ) {
					return false;
				}
				$product = wc_get_product( $id );
				return $product && $product->is_visible();
			}
		);

		$final_product_ids = array_merge( $final_product_ids, $valid_shown_ids );

		if ( ! empty( $final_product_ids ) ) {
			$query->set( 'post__in', $final_product_ids );
			$query->set( 'orderby', 'post__in' );
			$query->set( 'posts_per_page', count( $final_product_ids ) );
		} else {
			$query->set( 'orderby', 'popularity' );
			$query->set( 'posts_per_page', 8 );
		}
	}
}
