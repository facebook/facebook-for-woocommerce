<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package MetaCommerce
 */

namespace WooCommerce\Facebook\API\Plugin\WhatsAppSettings\OnboardingComplete;

use WooCommerce\Facebook\API\Plugin\Request as RESTRequest;
use WooCommerce\Facebook\API\Plugin\Traits\JS_Exposable;

defined( 'ABSPATH' ) || exit;

/**
 * WhatsApp Settings Onboarding-Complete REST API Request.
 *
 * Backs the WA_CONNECT push signal: the onboarding iframe posts a
 * "connection complete" message to the parent, whose JS handler calls this
 * endpoint so the plugin can persist that onboarding finished (the write side
 * of the customer_events gate's onboarding flag). The signal carries no
 * payload — reaching this endpoint means "onboarding complete" — so there are
 * no parameters.
 *
 * @since 3.7.5
 */
class Request extends RESTRequest {

	use JS_Exposable;

	/**
	 * Gets the API endpoint for this request.
	 *
	 * @since 3.7.5
	 *
	 * @return string
	 */
	public function get_endpoint() {
		return 'whatsapp_settings/onboarding_complete';
	}

	/**
	 * Gets the HTTP method for this request.
	 *
	 * @since 3.7.5
	 *
	 * @return string
	 */
	public function get_method() {
		return 'POST';
	}

	/**
	 * Gets the parameter schema for this request.
	 *
	 * @since 3.7.5
	 *
	 * @return array Array of parameters with their types and whether they're required
	 */
	public function get_param_schema() {
		return [
			// No parameters: reaching this endpoint means onboarding is complete.
		];
	}

	/**
	 * Gets the JavaScript function name for this request.
	 *
	 * @since 3.7.5
	 *
	 * @return string
	 */
	public function get_js_function_name() {
		return 'notifyWhatsAppOnboardingComplete';
	}

	/**
	 * Validate the request.
	 *
	 * @since 3.7.5
	 *
	 * @return true|\WP_Error True if valid, WP_Error otherwise.
	 */
	public function validate() {
		// No parameters to validate.
		return true;
	}
}
