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
use WooCommerce\Facebook\AdvertiseASC\AscNotSupportedException;
use WooCommerce\Facebook\AdvertiseASC\NonDiscriminationNotAcceptedException;
use WooCommerce\Facebook\Framework\Api\Exception as ApiException;

defined( 'ABSPATH' ) || exit;

/**
 * This class creates a wrapper for interacting with ASC NewBuyers campaign type.
 *
 * @since x.x.x
 */
class NewBuyers extends CampaignHandler {

	/** @var string holding the ID for this campangin type */
	const ID = 'new-buyers';

	/** @var string holding the FB Pixel ID */
	private $facebook_pixel_id;

	public function __construct() {
		parent::__construct( 'New Buyers' );
	}

	/**
	 * Gets the minimum allowed daily budget for this campaign type.
	 *
	 * @since x.x.x
	 * @return int
	 */
	public function get_allowed_min_daily_budget() {
		// they would need to spend 1K/month to get good result from ASC.
		return 34 * $this->conversion_rate;
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

	/**
	 * Gets the list of selected countries. If there is no active ad, it will return a list containing 'US'
	 *
	 * @since x.x.x
	 * @return array
	 */
	public function get_selected_countries() {
		return $this->get_ad_targeting();
	}

	/**
	 * Creates a Facebook Ad Campaign.
	 *
	 * @since x.x.x
	 * @param mixed $props is an object with values for: state, daily budget, country list, and ad message.
	 * @throws AscNotSupportedException In case ASC campaign creation is not supported.
	 * @throws NonDiscriminationNotAcceptedException In case Non-Discrimination agreement is not accepted in AdsManager.
	 * @throws PluginException If there was any unexpected errors from APIs.
	 */
	public function create_asc_campaign( $props ) {

		$status       = ( 'true' === $props['state'] ) ? 1 : 0;
		$daily_budget = $props['daily_budget'];
		$ad_message   = $props['ad_message'];
		$country_list = $props['country'];

		$this->facebook_pixel_id = $this->integration->get_facebook_pixel_id();

		try {

			$this->campaign = $this->setup_campaign();

		} catch ( ApiException $e ) {

			if ( $e->getCode() === 2 ) {

				throw new AscNotSupportedException();

			} else {

				$message = $this->get_escaped_translation( 'An exception happened trying to setup the New Buyers campaign for the first time. ' . $e->getMessage() );
				\WC_Facebookcommerce_Utils::log( $message );
				throw new PluginException( $message );

			}
		}

		try {

			$this->adset      = $this->setup_adset( $this->facebook_pixel_id, $this->campaign['id'], $daily_budget * 100, $country_list );
			$this->ad         = $this->setup_ad( $this->adset['id'], $this->ad_name, $this->facebook_page_id, $ad_message, $this->default_product_set, $this->store_url );
			$this->adcreative = $this->fetch_adcreative( $this->ad['adcreatives']['data'][0]['id'] );

		} catch ( ApiException $e ) {

			$message = $this->get_escaped_translation( 'An exception happened trying to setup the New Buyers campaign objects for the first time. ' . $e->getMessage() );
			\WC_Facebookcommerce_Utils::log( $message );
			if ( str_contains( $e->getMessage(), 'non-discrimination' ) ) {
				throw new NonDiscriminationNotAcceptedException();
			} else {
				throw new PluginException( $message );
			}
		}

		$this->update_stored_data();

		if ( $status ) {
			$this->set_ad_status( $status );
		}

		$this->get_insights();
	}

	/**
	 * Updates the running Ad Campaign.
	 *
	 * @since x.x.x
	 * @param mixed $data is an object with values for: state, daily budget, country list, and ad message.
	 * @return bool
	 */
	public function update_asc_campaign( $data ) {

		if ( ! $data ) {
			return true;
		}

		$message      = $data['ad_message'];
		$countries    = $data['country'] ?? array();
		$daily_budget = $data['daily_budget'];
		$status       = ( 'true' === $data['state'] ) ? 1 : 0;

		$this->apply_creative_changes( $message );

		$this->apply_adset_changes( $countries, $daily_budget );

		$this->set_ad_status( $status );

		return true;
	}

	protected function get_id() {

		return self::ID;
	}

	protected function get_adcreative_creation_params( $ad_creative_name, $page_id, $store_url, $ad_message, $product_set_id ) {

		$properties = array(
			'name'              => $ad_creative_name,
			'product_set_id'    => $product_set_id,
			'object_story_spec' => array(
				'page_id'       => $page_id,
				'template_data' => array(
					'description' => '{{product.price}}',
					'link'        => $store_url,
					'message'     => $ad_message,
					'name'        => '{{product.name | titleize}}',
				),
			),
		);

		if ( $this->instagram_actor_id ) {
			$properties['object_story_spec']['instagram_actor_id'] = $this->instagram_actor_id;
		}

		return $properties;
	}

	private function setup_campaign() {

		$properties = array(
			'name'                  => $this->campaign_name,
			'special_ad_categories' => array(),
			'objective'             => 'OUTCOME_SALES',
			'smart_promotion_type'  => 'AUTOMATED_SHOPPING_ADS',
			'status'                => 'PAUSED',
		);
		try {

			return $this->create_campaign( $properties );

		} catch ( ApiException $e ) {

			if ( $e->getCode() === 2 ) {

				throw new AscNotSupportedException();

			} else {

				$message = $this->get_escaped_translation( 'An error happened trying to setup the New Buyers campaign. ' . $e->getMessage() );
				\WC_Facebookcommerce_Utils::log( $message );
				throw new PluginException( $message );

			}
		}
	}

	private function setup_adset( $installed_pixel, $campaign_id, $daily_budget, $selected_countries ) {

		$properties = $this->get_adset_creation_params( $this->adset_name, $campaign_id, $installed_pixel, $daily_budget, $selected_countries );
		return $this->create_adset( $properties );
	}

	private function get_adset_creation_params( $adset_name, $campaign_id, $pixel_id, $daily_budget, $selected_countries ) {
		$promoted_object = array(
			'pixel_id'          => $pixel_id,
			'custom_event_type' => 'PURCHASE',
		);

		$targeting = array(
			'geo_locations' => array(
				'countries' => $selected_countries,
			),
		);

		return array(
			'name'                                => $adset_name,
			'campaign_id'                         => $campaign_id,
			'promoted_object'                     => $promoted_object,
			'daily_budget'                        => $daily_budget,
			'existing_customer_budget_percentage' => '0',
			'billing_event'                       => 'IMPRESSIONS',
			'bid_strategy'                        => 'LOWEST_COST_WITHOUT_CAP',
			'targeting'                           => $targeting,
		);
	}

	private function get_ad_creation_params( $ad_name, $adset_id, $creative ) {

		return array(
			'name'     => $ad_name,
			'adset_id' => $adset_id,
			'creative' => $creative,
		);
	}

	private function setup_ad( $adset_id, $ad_name, $page_id, $ad_description_message, $product_set_id, $store_url ) {

		$creative = $this->get_adcreative_creation_params( $this->ad_creative_name, $page_id, $store_url, $ad_description_message, $product_set_id );
		return $this->create_ad( $this->get_ad_creation_params( $ad_name, $adset_id, $creative ) );
	}

	private function apply_creative_changes( $message ) {

		$ad_message      = $message ? $message : $this->adcreative['body'];
		$old_creative_id = $this->adcreative['id'];
		$old_ad_id       = $this->ad['id'];

		if ( $message ) {

			$creative_props = $this->get_adcreative_creation_params( $this->ad_creative_name, $this->facebook_page_id, $this->store_url, $ad_message, $this->default_product_set );

			$new_ad = '';
			try {

				$new_ad = $this->create_ad( $this->get_ad_creation_params( $this->ad_name, $this->adset['id'], $creative_props ) );

			} catch ( ApiException $e ) {

				$message = $this->get_escaped_translation( 'Error applying creative changes .' . $e->getMessage() );
				\WC_Facebookcommerce_Utils::log( $message );
				throw new PluginException( $message );

			}

			$this->ad         = $new_ad;
			$this->adcreative = $this->fetch_adcreative( $this->ad['adcreatives']['data'][0]['id'] );

			$this->update_stored_data();

			$this->delete_item( $old_ad_id );
			$this->delete_item( $old_creative_id );
		}
	}

	private function apply_adset_changes( $countries, $daily_budget ) {

		$props            = array();
		$new_daily_budget = $daily_budget ? $daily_budget : $this->adset['daily_budget'];

		if ( $daily_budget ) {
			$props['daily_budget'] = $new_daily_budget * 100;
		}

		if ( $countries ) {
			$props['targeting'] = array(
				'geo_locations' => array(
					'countries' => $countries,
				),
			);
		}

		try {

			$this->update_adset( $props );

		} catch ( ApiException $e ) {

			$message = $this->get_escaped_translation( 'Error applying adset changes .' . $e->getMessage() );
			\WC_Facebookcommerce_Utils::log( $message );
			throw new PluginException( $message );

		}
	}

	private function get_ad_targeting() {
		if ( ! $this->is_running() ) {
			return array( 'US' );
		}
		return $this->adset['targeting']['geo_locations']['countries'];
	}
}
