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

use WooCommerce\Facebook\RolloutSwitches;
use WooCommerce\Facebook\Utilities\Heartbeat;

/**
 * The product set sync handler.
 *
 * @since 3.4.9
 */
class ProductSetSync {

	// Product category taxonomy used by WooCommerce
	const WC_PRODUCT_CATEGORY_TAXONOMY = 'product_cat';

	// Product tag taxonomy used by WooCommerce
	const WC_PRODUCT_TAG_TAXONOMY = 'product_tag';

	// Prefix to be used in fb product tag names
	const FB_PRODUCT_TAG_PREFIX = 'wc_tag_id_';

	/**
	 * ProductSetSync constructor.
	 */
	public function __construct() {
		$this->add_hooks();
	}


	/**
	 * Adds needed hooks to support product set sync.
	 */
	private function add_hooks() {
		/**
		 * Sets up hooks to synchronize WooCommerce category mutations (create, update, delete) with Meta catalog's product sets in real-time.
		 */
		add_action( 'create_' . self::WC_PRODUCT_CATEGORY_TAXONOMY, array( $this, 'on_create_or_update_product_wc_category_callback' ), 99, 3 );
		add_action( 'edited_' . self::WC_PRODUCT_CATEGORY_TAXONOMY, array( $this, 'on_create_or_update_product_wc_category_callback' ), 99, 3 );
		add_action( 'delete_' . self::WC_PRODUCT_CATEGORY_TAXONOMY, array( $this, 'on_delete_wc_product_category_callback' ), 99, 4 );

		/**
		 * Sets up hooks to synchronize WooCommerce product tags mutations (create, update, delete) with Meta catalog's product sets in real-time.
		 */
		add_action( 'create_' . self::WC_PRODUCT_TAG_TAXONOMY, array( $this, 'on_create_or_update_wc_product_tag_callback' ), 99, 3 );
		add_action( 'edited_' . self::WC_PRODUCT_TAG_TAXONOMY, array( $this, 'on_create_or_update_wc_product_tag_callback' ), 99, 3 );
		add_action( 'delete_' . self::WC_PRODUCT_TAG_TAXONOMY, array( $this, 'on_delete_wc_product_tag_callback' ), 99, 4 );

		/**
		 * Schedules a daily sync of all WooCommerce categories and tags to ensure any missed real-time updates are captured.
		 */
		add_action( Heartbeat::DAILY, array( $this, 'sync_all_product_sets' ) );
	}

	/**
	 * @since 3.4.9
	 *
	 * @param int   $term_id Term ID.
	 * @param int   $tt_id Term taxonomy ID.
	 * @param array $args Arguments.
	 */
	public function on_create_or_update_product_wc_category_callback( $term_id, $tt_id, $args ) {
		$this->on_create_or_update_term_callback_impl( $term_id, self::WC_PRODUCT_CATEGORY_TAXONOMY );
	}

	/**
	 * @since 3.4.9
	 *
	 * @param int     $term_id Term ID.
	 * @param int     $tt_id Term taxonomy ID.
	 * @param WP_Term $deleted_term Copy of the already-deleted term.
	 * @param array   $object_ids List of term object IDs.
	 */
	public function on_delete_wc_product_category_callback( $term_id, $tt_id, $deleted_term, $object_ids ) {
		$this->on_delete_term_callback_impl( $deleted_term );
	}

	/**
	 * @since 3.4.9
	 *
	 * @param int   $term_id Term ID.
	 * @param int   $tt_id Term taxonomy ID.
	 * @param array $args Arguments.
	 */
	public function on_create_or_update_wc_product_tag_callback( $term_id, $tt_id, $args ) {
		$this->on_create_or_update_term_callback_impl( $term_id, self::WC_PRODUCT_TAG_TAXONOMY );
	}

	/**
	 * @since 3.4.9
	 *
	 * @param int     $term_id Term ID.
	 * @param int     $tt_id Term taxonomy ID.
	 * @param WP_Term $deleted_term Copy of the already-deleted term.
	 * @param array   $object_ids List of term object IDs.
	 */
	public function on_delete_wc_product_tag_callback( $term_id, $tt_id, $deleted_term, $object_ids ) {
		$this->on_delete_term_callback_impl( $deleted_term );
	}

	/**
	 * @since 3.4.9
	 */
	public function sync_all_product_sets() {
		try {
			if ( ! $this->is_sync_enabled() ) {
				return;
			}

			$this->sync_all_product_sets_for_taxonomy( self::WC_PRODUCT_CATEGORY_TAXONOMY );
			$this->sync_all_product_sets_for_taxonomy( self::WC_PRODUCT_TAG_TAXONOMY );
		} catch ( \Exception $exception ) {
			$this->log_exception( $exception );
		}
	}

	private function on_create_or_update_term_callback_impl( $term_id, $wc_taxonomy ) {
		try {
			if ( ! $this->is_sync_enabled() ) {
				return;
			}

			$wc_term = get_term( $term_id, $wc_taxonomy );
			$fb_product_set_id = $this->get_fb_product_set_id( $wc_term );
			if ( ! empty( $fb_product_set_id ) ) {
				$this->update_fb_product_set( $wc_term, $fb_product_set_id, $wc_taxonomy );
			} else {
				$this->create_fb_product_set( $wc_term, $wc_taxonomy );
			}
		} catch ( \Exception $exception ) {
			$this->log_exception( $exception );
		}
	}

	private function on_delete_term_callback_impl( $deleted_term ) {
		try {
			if ( ! $this->is_sync_enabled() ) {
				return;
			}

			$fb_product_set_id = $this->get_fb_product_set_id( $deleted_term );
			if ( ! empty( $fb_product_set_id ) ) {
				$this->delete_fb_product_set( $fb_product_set_id );
			}
		} catch ( \Exception $exception ) {
			$this->log_exception( $exception );
		}
	}

	protected function is_sync_enabled() {
		return facebook_for_woocommerce()->get_rollout_switches()->is_switch_enabled(
			RolloutSwitches::SWITCH_PRODUCT_SETS_SYNC_ENABLED
		);
	}

	private function log_exception( \Exception $exception ) {
		facebook_for_woocommerce()->log(
			'ProductSetSync exception' .
				': exception_code : ' . $exception->getCode() .
				'; exception_class : ' . get_class( $exception ) .
				': exception_message : ' . $exception->getMessage() .
				'; exception_trace : ' . $exception->getTraceAsString(),
			null,
			\WC_Log_Levels::ERROR
		);
	}

	/**
	 * Important. This is ID from the WC term to be used as a retailer ID for the FB product set
	 *
	 * @param WP_Term $wc_term The WooCommerce term object.
	 */
	private function get_retailer_id( $wc_term ) {
		return $wc_term->term_taxonomy_id;
	}

	/**
	 * Important. This gets a product tag to be used in fb product items and fb sets filters
	 *
	 * @param WP_Term $wc_tag The WooCommerce atg object.
	 */
	public static function get_fb_product_tag( $wc_tag ) {
		return self::FB_PRODUCT_TAG_PREFIX . $wc_tag->term_taxonomy_id;
	}

	protected function get_fb_product_set_id( $wc_term ) {
		$retailer_id   = $this->get_retailer_id( $wc_term );
		$fb_catalog_id = facebook_for_woocommerce()->get_integration()->get_product_catalog_id();

		try {
			$response = facebook_for_woocommerce()->get_api()->read_product_set_item( $fb_catalog_id, $retailer_id );
		} catch ( \Exception $e ) {
			$message = sprintf( 'There was an error trying to get product set data in a catalog: %s', $e->getMessage() );
			facebook_for_woocommerce()->log( $message );

			/**
			 * Re-throw the exception to prevent potential issues, such as creating duplicate sets.
			 */
			throw $e;
		}

		return $response->get_product_set_id();
	}

	protected function build_fb_product_set_data( $wc_term, $wc_taxonomy ) {
		$wc_term_name          = get_term_field( 'name', $wc_term, $wc_taxonomy );
		$wc_term_description   = get_term_field( 'description', $wc_term, $wc_taxonomy );
		$wc_term_url           = get_term_link( $wc_term, $wc_taxonomy );
		$wc_term_thumbnail_id  = get_term_meta( $wc_term, 'thumbnail_id', true );
		$wc_term_thumbnail_url = wp_get_attachment_image_src( $wc_term_thumbnail_id );

		$fb_product_set_metadata = array();
		if ( ! empty( $wc_term_thumbnail_url ) ) {
			$fb_product_set_metadata['cover_image_url'] = $wc_term_thumbnail_url;
		}
		if ( ! empty( $wc_term_description ) ) {
			$fb_product_set_metadata['description'] = $wc_term_description;
		}
		if ( ! empty( $wc_term_url ) ) {
			$fb_product_set_metadata['external_url'] = $wc_term_url;
		}

		if ( self::WC_PRODUCT_CATEGORY_TAXONOMY === $wc_taxonomy ) {
			$fb_set_filter = wp_json_encode( array( 'and' => array( array( 'product_type' => array( 'i_contains' => $wc_term_name ) ) ) ) );
		} else {
			$fb_set_filter = wp_json_encode( array( 'and' => array( array( 'tags' => array( 'eq' => self::get_fb_product_tag( $wc_term ) ) ) ) ) );
		}

		$fb_product_set_data = array(
			'name'        => $wc_term_name,
			'filter'      => $fb_set_filter,
			'retailer_id' => $this->get_retailer_id( $wc_term ),
			'metadata'    => wp_json_encode( $fb_product_set_metadata ),
		);

		return $fb_product_set_data;
	}

	protected function create_fb_product_set( $wc_term, $wc_taxonomy ) {
		$fb_product_set_data = $this->build_fb_product_set_data( $wc_term, $wc_taxonomy );
		$fb_catalog_id       = facebook_for_woocommerce()->get_integration()->get_product_catalog_id();

		try {
			facebook_for_woocommerce()->get_api()->create_product_set_item( $fb_catalog_id, $fb_product_set_data );
		} catch ( \Exception $e ) {
			$message = sprintf( 'There was an error trying to create product set: %s', $e->getMessage() );
			facebook_for_woocommerce()->log( $message );
		}
	}

	protected function update_fb_product_set( $wc_term, $fb_product_set_id, $wc_taxonomy ) {
		$fb_product_set_data = $this->build_fb_product_set_data( $wc_term, $wc_taxonomy );

		try {
			facebook_for_woocommerce()->get_api()->update_product_set_item( $fb_product_set_id, $fb_product_set_data );
		} catch ( \Exception $e ) {
			$message = sprintf( 'There was an error trying to update product set: %s', $e->getMessage() );
			facebook_for_woocommerce()->log( $message );
		}
	}

	protected function delete_fb_product_set( $fb_product_set_id ) {
		try {
			$allow_live_deletion = true;
			facebook_for_woocommerce()->get_api()->delete_product_set_item( $fb_product_set_id, $allow_live_deletion );
		} catch ( \Exception $e ) {
			$message = sprintf( 'There was an error trying to delete product set in a catalog: %s', $e->getMessage() );
			facebook_for_woocommerce()->log( $message );
		}
	}

	private function sync_all_product_sets_for_taxonomy( $wc_taxonomy ) {
		$wc_terms = get_terms(
			array(
				'taxonomy'   => $wc_taxonomy,
				'hide_empty' => false,
				'orderby'    => 'ID',
				'order'      => 'ASC',
			)
		);

		foreach ( $wc_terms as $wc_term ) {
			try {
				$fb_product_set_id = $this->get_fb_product_set_id( $wc_term );
				if ( ! empty( $fb_product_set_id ) ) {
					$this->update_fb_product_set( $wc_term, $fb_product_set_id, $wc_taxonomy );
				} else {
					$this->create_fb_product_set( $wc_term, $wc_taxonomy );
				}
			} catch ( \Exception $exception ) {
				$this->log_exception( $exception );
			}
		}
	}
}
