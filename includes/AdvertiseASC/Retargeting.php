<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */

namespace WooCommerce\Facebook\AdvertiseASC;

use WooCommerce\Facebook\Framework\Plugin\Exception as PluginException;
use WooCommerce\Facebook\Framework\Api\Exception as ApiException;

defined( 'ABSPATH' ) || exit;

/**
 * This class creates a wrapper for interacting with ASC Retargeting campaign type.
 *
 * @since x.x.x
 */
class Retargeting extends CampaignHandler {

	/** @var string holding the ID for this campangin type */
	const ID                   = 'retargeting';
	const VISIT_PERIOD_SECONDS = 12 * 24 * 3600;

	/** @var string the default message shown as Ad Message */
	private $default_creative_message = 'These great products are still waiting for you!';

	public function __construct() {
		$this->default_creative_message = __( 'These great products are still waiting for you!', 'facebook-for-woocommerce' );
		parent::__construct( 'Retargeting' );
	}

	/**
	 * Updates the running Ad Campaign.
	 *
	 * @since x.x.x
	 * @param mixed $data is an object with values for: state, daily budget, and ad message.
	 * @return bool
	 */
	public function update_asc_campaign( $data ) {

		$status           = ( 'true' === $props['state'] ) ? 1 : 0;
		$new_daily_budget = $data['daily_budget'];
		$new_ad_message   = $data['ad_message'];
		$this->apply_adset_changes( $new_daily_budget * 100 );
		$this->apply_adcreative_changes( $new_ad_message );

		if ( $status ) {
			$this->set_ad_status( $status );
		}
		return true;
	}

	/**
	 * Creates a Facebook Ad Campaign.
	 *
	 * @since x.x.x
	 * @param mixed $props is an object with values for: state, daily budget, and ad message.
	 */
	public function create_asc_campaign( $props ) {

		$status           = ( 'true' === $props['state'] ) ? 1 : 0;
		$this->campaign   = $this->setup_campaign( $this->product_catalog_id );
		$this->adset      = $this->setup_adset( $this->campaign['id'], $this->bid_amount, $props['daily_budget'] * 100, $this->default_product_set, self::VISIT_PERIOD_SECONDS );
		$this->adcreative = $this->setup_adcreative( $this->ad_creative_name, $this->facebook_page_id, $this->store_url, $props['ad_message'], $this->default_product_set );
		$this->ad         = $this->setup_ad();

		$this->update_stored_data();

		if ( $status ) {
			$this->set_ad_status( $status );
		}

		$this->get_insights();
	}

	/**
	 * Gets the minimum allowed daily budget for this campaign type.
	 *
	 * @since x.x.x
	 * @return int
	 */
	public function get_allowed_min_daily_budget() {
		return $this->get_min_daily_budget();
	}

	/**
	 * Gets the list of selected countries. For this campaign type it only returns an empty array.
	 *
	 * @since x.x.x
	 * @return array
	 */
	public function get_selected_countries() {
		return array();
	}

	/**
	 * Gets the campaign type.
	 *
	 * @since x.x.x
	 * @return string
	 */
	public function get_campaign_type(): string {

		return self::ID;
	}

	protected function get_adcreative_creation_params( $name, $page_id, $link, $message, $product_set_id ) {

		$properties = array(
			'name'              => $name,
			'product_set_id'    => $product_set_id,
			'object_story_spec' => array(
				'page_id'              => $page_id,
				'multi_share_end_card' => true,
				'show_multiple_images' => false,
				'template_data'        => array(
					'description'    => '{{product.current_price strip_zeros}}',
					'link'           => $link,
					'message'        => $message,
					'name'           => '{{product.name | titleize}}',
					'call_to_action' => array( 'type' => 'SHOP_NOW' ),
				),

			),
		);

		if ( $this->instagram_actor_id ) {
			$properties['object_story_spec']['instagram_actor_id'] = $this->instagram_actor_id;
		}

		return $properties;
	}

	protected function get_id() {

		return self::ID;
	}

	protected function setup_campaign( string $catalog_id ) {

		$properties = array(
			'name'                  => $this->campaign_name,
			'objective'             => 'PRODUCT_CATALOG_SALES',
			'special_ad_categories' => array(),
			'promoted_object'       => array(
				'product_catalog_id' => $catalog_id,
			),
			'status'                => 'PAUSED',
		);

		return $this->create_campaign( $properties );
	}

	private function apply_adcreative_changes( $new_message ) {

		$old_creative_id = $this->adcreative['id'];
		$message         = $new_message ? $new_message : $this->adcreative['body'];

		if ( $new_message ) {

			$new_ad_creative = '';
			try {

				$new_ad_creative = $this->setup_adcreative( $this->ad_creative_name, $this->facebook_page_id, $this->store_url, $message, $this->default_product_set );
				$this->update_ad( array( 'creative' => array( 'creative_id' => $this->adcreative['id'] ) ) );

			} catch ( ApiException $e ) {

				$message = $this->get_escaped_translation( 'There was an error trying to apply the changes to adcreative and ad. ' . $e->getMessage() );
				\WC_Facebookcommerce_Utils::log( $message );
				throw new PluginException( $message );

			}

			$this->adcreative = $new_ad_creative;

			$this->update_stored_data();

			try {
				$this->delete_item( $old_creative_id );
			} catch ( ApiException $e ) {

				$message = sprintf( 'Unable to delete item: ' . $old_creative_id . '. error: ' . $e->getMessage() );
				\WC_Facebookcommerce_Utils::log( $message );
			}
		}
	}

	private function apply_adset_changes( $daily_budget ) {

		$new_daily_budget = $daily_budget ? $daily_budget : $this->adset['daily_budget'];

		if ( $daily_budget ) {
			$props                 = array();
			$props['daily_budget'] = $new_daily_budget;
			$props['targeting']    = $this->get_adset_targeting_creation_params( $this->default_product_set, self::VISIT_PERIOD_SECONDS );
			try {

				$this->update_adset( $props );
				$this->update_stored_data();

			} catch ( ApiException $e ) {

				$message = $this->get_escaped_translation( 'There was an error trying to apply the changes to the adset.' . $e->getMessage() );
				\WC_Facebookcommerce_Utils::log( $message );
				throw new PluginException( $message );

			}
		}
	}

	private function get_adset_targeting_creation_params( $product_set_id, $seconds ) {

		return array(
			'age_max'                => 65,
			'age_min'                => 18,
			'targeting_optimization' => 'none',
			'product_audience_specs' => array(
				array(
					'product_set_id' => $product_set_id,
					'inclusions'     => array(
						array(
							'retention_seconds' => $seconds,
							'rule'              => array(
								'event' => array(
									'eq' => 'ViewContent',
								),
							),
						),
						array(
							'retention_seconds' => $seconds,
							'rule'              => array(
								'event' => array(
									'eq' => 'AddToCart',
								),
							),
						),
					),
					'exclusions'     => array(
						array(
							'retention_seconds' => $seconds,
							'rule'              => array(
								'event' => array(
									'eq' => 'Purchase',
								),
							),
						),
					),
				),
			),
		);
	}

	private function setup_adset( $campaign_id, $bid_amount, $daily_budget, $product_set_id, $visit_period_seconds ) {

		$params = array(
			'name'              => $this->adset_name,
			'bid_amount'        => $bid_amount,
			'billing_event'     => 'IMPRESSIONS',
			'daily_budget'      => $daily_budget,
			'optimization_goal' => 'OFFSITE_CONVERSIONS',
			'campaign_id'       => $campaign_id,
			'promoted_object'   => array(
				'product_set_id' => $product_set_id,
			),
			'status'            => 'PAUSED',
			'targeting'         => $this->get_adset_targeting_creation_params( $product_set_id, $visit_period_seconds ),
		);

		return $this->create_adset( $params );
	}

	private function setup_adcreative( string $ad_creative_name, string $page_id, string $link, string $message, string $product_set_id ) {

		$properties = $this->get_adcreative_creation_params( $ad_creative_name, $page_id, $link, $message, $product_set_id );

		return $this->create_adcreative( $properties );
	}

	private function setup_ad() {

		$properties = array(
			'name'     => $this->ad_name,
			'adset_id' => $this->adset['id'],
			'creative' => array( 'creative_id' => $this->adcreative['id'] ),
			'status'   => parent::STATUS_PAUSED,
		);

		return $this->create_ad( $properties );
	}
}
