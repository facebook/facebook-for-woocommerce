<?php
/**
 * Set up Facebook task.
 *
 * Adds a set up facebook task to the task list.
 *
 * @package MetaCommerce
 */

namespace WooCommerce\Facebook\Admin\Tasks;

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Admin\Features\OnboardingTasks\Task;

/**
 * Setup Task class.
 */
class Setup extends Task {

	/**
	 * Get the ID of the task.
	 *
	 * @return string
	 */
	public function get_id() {
		return 'setup-facebook';
	}

	/**
	 * Get the title for the task.
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'Advertise your products across Meta\'s platforms, including Facebook, Instagram, and WhatsApp', 'facebook-for-woocommerce' );
	}

	/**
	 * Get the content for the task.
	 *
	 * @return string
	 */
	public function get_content() {
		return '';
	}

	/**
	 * Get the time required to perform the task.
	 *
	 * @return string
	 */
	public function get_time() {
		return esc_html__( '20 minutes', 'facebook-for-woocommerce' );
	}

	/**
	 * Get the action URL for the task.
	 *
	 * @return string
	 */
	public function get_action_url() {
		return facebook_for_woocommerce()->get_settings_url();
	}

	/**
	 * Check if the task is complete.
	 *
	 * @return bool
	 */
	public function is_complete() {
		return facebook_for_woocommerce()->get_connection_handler()->is_connected();
	}

	/**
	 * Parent ID. This method is abstract in WooCommerce 6.1.x, 6.2.x and 6.3.x. This implementation is for backward compatibility for these versions.
	 *
	 * @return string
	 */
	public function get_parent_id() {
		if ( method_exists( get_parent_class( $this ), 'get_parent_id' ) ) {
			return parent::get_parent_id();
		}

		return 'extended'; // The parent task list id.
	}
}
