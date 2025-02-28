<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WC_Facebookcommerce_WarmConfig
 *
 * This class stores pre-configured Facebook Pixel settings that can be used
 * to initialize the Facebook Pixel when user settings are not yet available.
 * It serves as a configuration warmer for Facebook integration with WooCommerce.
 */
class WC_Facebookcommerce_WarmConfig {
	/**
	 * Facebook Pixel ID for tracking
	 *
	 * @var string|null
	 */
	public static $fb_warm_pixel_id = null;

	/**
	 * Whether advanced matching is enabled for the Facebook Pixel
	 *
	 * @var bool|null
	 */
	public static $fb_warm_is_advanced_matching_enabled = null;

	/**
	 * Whether server-side (S2S) events are enabled
	 *
	 * @var bool|null
	 */
	public static $fb_warm_use_s2s = null;

	/**
	 * Facebook API access token
	 *
	 * @var string|null
	 */
	public static $fb_warm_access_token = null;
}
