<?php

namespace WooCommerce\Facebook\Jobs;

use WC_Facebookcommerce;

defined( 'ABSPATH' ) || exit;

/**
 * Trait LoggingTrait
 *
 * Logging helper trait for jobs.
 *
 * @since 2.5.0
 */
trait LoggingTrait {

	/**
	 * Get the name/slug of the job.
	 *
	 * @return string
	 */
	abstract public function get_name(): string;

	/**
	 * Write a log entry using the plugin's logger.
	 *
	 * @param string $message
	 */
	protected function log( string $message ) {
		facebook_for_woocommerce()->log(
			$message,
			sprintf( '%s_%s', WC_Facebookcommerce::PLUGIN_ID, $this->get_name() )
		);
	}
}
