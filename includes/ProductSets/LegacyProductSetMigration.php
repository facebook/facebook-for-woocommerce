<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license fo§und in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */

namespace WooCommerce\Facebook\ProductSets;

defined( 'ABSPATH' ) || exit;

use WC_Facebookcommerce_Utils;

/**
 * The legacy product set migration.
 */
class LegacyProductSetMigration {

	public static function migrate_legacy_fb_product_sets() {
		// Query legacy fb product sets
		global $wpdb;
		$fb_product_set_taxonomy_name = 'fb_product_set';
		$term_taxonomy_table = $wpdb->prefix . 'term_taxonomy';
		$terms_table = $wpdb->prefix . 'terms';
		$sql = $wpdb->prepare(
			"SELECT t.term_id, t.name, t.slug, tt.description
			FROM {$terms_table} t
			INNER JOIN {$term_taxonomy_table} tt ON t.term_id = tt.term_id
			WHERE tt.taxonomy = %s",
			$fb_product_set_taxonomy_name
		);
		$results = $wpdb->get_results($sql);
		
		// Migrate legacy fb product sets to dynamic product sets filter
		foreach ( $results as $result ) {
			$fb_product_set_id = get_term_meta( $result->term_id, 'fb_product_set_id', true );
			$wc_product_categories_ids = get_term_meta( $result->term_id, '_wc_facebook_product_cats', true );
			if (is_array($wc_product_categories_ids) && !empty($wc_product_categories_ids)) {
				$wc_categories = array();
				foreach ($wc_product_categories_ids as $cat_id) {
					$wc_category = get_term( $cat_id, 'product_cat' );
					if (!is_wp_error($wc_category) && $wc_category) {
						$wc_categories[] = $wc_category;
					}
				}
				self::update_fb_product_set( $fb_product_set_id, $result->name, $result->description, $wc_categories );
			}
		}
	}

	private static function update_fb_product_set( $fb_set_id, $fb_set_name, $fb_set_description, $wc_categories ) {
		// Build combined filter for multiple categories
		$filters = array();
		foreach ($wc_categories as $wc_category) {
			$wc_category_name = WC_Facebookcommerce_Utils::clean_string( get_term_field( 'name', $wc_category, 'product_cat' ) );
			$filters[] = array( 'product_type' => array( 'i_contains' => $wc_category_name ) );
		}
		$fb_product_set_data = array(
			'name'     => $fb_set_name,
			'filter'   => wp_json_encode( array( 'or' => $filters ) ),
			'metadata' => wp_json_encode( array( 'description' => $fb_set_description ) ),
		);

		// Send update request
		try {
			facebook_for_woocommerce()->get_api()->update_product_set_item( $fb_set_id, $fb_product_set_data );
		} catch ( \Exception $e ) {
			$message = sprintf( 'There was an error trying to update product set: %s', $e->getMessage() );
			facebook_for_woocommerce()->log( $message );
		}
	}
}
