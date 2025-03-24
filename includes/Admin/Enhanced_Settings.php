<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */

namespace WooCommerce\Facebook\Admin;

use Automattic\WooCommerce\Admin\Features\Features as WooAdminFeatures;
use WooCommerce\Facebook\Admin\Settings_Screens;
use WooCommerce\Facebook\Admin\Settings_Screens\Shops;
use WooCommerce\Facebook\Framework\Helper;
use WooCommerce\Facebook\Framework\Plugin\Exception as PluginException;

defined( 'ABSPATH' ) || exit;

/**
 * The enchanced admin settings handler.
 *
 * @since 3.2.0
 */
class Enhanced_Settings {

	/** @var string */
	const PAGE_ID = 'wc-facebook';

	/** @var Abstract_Settings_Screen[] */
	private $screens;

	/**
	 * The enhanced settings constructor.
	 *
	 * @since 3.2.0
	 */
	public function __construct() {
		$this->screens = $this->build_menu_item_array();
		add_action( 'admin_menu', array( $this, 'add_menu_item' ) );
	}

	/**
	 * Build the menu item array.
	 *
	 * @since 3.2.10
	 */
	private function build_menu_item_array(): array {
		return [ Settings_Screens\Shops::ID => new Settings_Screens\Shops() ];
	}

	/**
	 * Add Facebook menu item.
	 *
	 * @since 3.2.10
	 */
	public function add_menu_item() {
		$root_menu_item = $this->root_menu_item();

		add_submenu_page(
			$root_menu_item,
			__( 'Facebook for WooCommerce', 'facebook-for-woocommerce' ),
			__( 'Facebook', 'facebook-for-woocommerce' ),
			'manage_woocommerce',
			self::PAGE_ID,
			[ $this, 'render' ],
			5
		);

		$this->connect_to_enhanced_admin( $this->is_marketing_enabled() ? 'marketing_page_wc-facebook' : 'woocommerce_page_wc-facebook' );
	}

	/**
	 * Get root menu item.
	 *
	 * @since 3.2.10
	 *
	 * @return string
	 */
	public function root_menu_item() {
		if ( $this->is_marketing_enabled() ) {
			return 'woocommerce-marketing';
		}

		return 'woocommerce';
	}

	/**
	 * Check if marketing feature is enabled.
	 *
	 * @since 3.2.10
	 *
	 * @return bool
	 */
	public function is_marketing_enabled() {
		if ( class_exists( WooAdminFeatures::class ) ) {
			return WooAdminFeatures::is_enabled( 'marketing' );
		}

		return is_callable( '\Automattic\WooCommerce\Admin\Loader::is_feature_enabled' )
				&& \Automattic\WooCommerce\Admin\Loader::is_feature_enabled( 'marketing' );
	}

	/**
	 * Enable enhanced admin support for the main Facebook settings page.
	 *
	 * @since 3.2.10
	 *
	 * @param string $screen_id
	 */
	private function connect_to_enhanced_admin( $screen_id ) {
		if ( is_callable( 'wc_admin_connect_page' ) ) {
			wc_admin_connect_page(
				array(
					'id'        => self::PAGE_ID,
					'screen_id' => $screen_id,
					'path'      => add_query_arg( 'page', self::PAGE_ID, 'admin.php' ),
					'title'     => [ __( 'Facebook for WooCommerce', 'facebook-for-woocommerce' ) ],
				)
			);
		}
	}

	/**
	 * Render the enhanced settings page.
	 *
	 * @since 3.2.10
	 */
	public function render() {
		$screen = $this->get_screen( Settings_Screens\Shops::ID );
		?>
		<div class="wrap woocommerce">
			<?php facebook_for_woocommerce()->get_message_handler()->show_messages(); ?>
			<?php if ( $screen ) : ?>
				<h1 class="screen-reader-text"><?php echo esc_html( $screen->get_title() ); ?></h1>
				<p><?php echo wp_kses_post( $screen->get_description() ); ?></p>
				<?php $screen->render(); ?>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Get the enhanced settings screen object based on ID.
	 *
	 * @since 3.2.10
	 *
	 * @param string $screen_id
	 * @return Abstract_Settings_Screen | null
	 */
	public function get_screen( $screen_id ) {
		$screens = $this->get_screens();
		return ! empty( $screens[ $screen_id ] ) && $screens[ $screen_id ] instanceof Abstract_Settings_Screen ? $screens[ $screen_id ] : null;
	}

	/**
	 * Get all available screens.
	 *
	 * @since 3.2.10
	 *
	 * @return Abstract_Settings_Screen[]
	 */
	public function get_screens() {
		/**
		 * Filter the admin settings screens.
		 *
		 * @since 3.2.10
		 *
		 * @param array $screens
		 */
		$screens = (array) apply_filters( 'wc_facebook_admin_settings_screens', $this->screens, $this );

		$screens = array_filter(
			$screens,
			function ( $value ) {
				return $value instanceof Abstract_Settings_Screen;
			}
		);
		return $screens;
	}
}
