<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * Plugin Name: Meta for WooCommerce
 * Plugin URI: https://github.com/woocommerce/facebook-for-woocommerce/
 * Description: Grow your business on Meta platforms! Use this official plugin to help sell more of your products using Facebook and Instagram. After completing the setup, you'll be ready to create ads that promote your products and you can also create a shop section on your Page where customers can browse your products.
 * Author: Meta
 * Author URI: https://www.meta.com/
 * Version: 3.6.2
 * Requires at least: 5.6
 * Requires PHP: 7.4
 * Text Domain: facebook-for-woocommerce
 * Requires Plugins: woocommerce
 * Tested up to: 6.9.4
 * WC requires at least: 6.4
 * WC tested up to: 10.6.1
 *
 * @package MetaCommerce
 */

require_once __DIR__ . '/vendor/autoload.php';

use Automattic\WooCommerce\Grow\Tools\CompatChecker\v0_0_1\Checker;
use Automattic\WooCommerce\Utilities\FeaturesUtil;

defined( 'ABSPATH' ) || exit;

// HPOS compatibility declaration.
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( FeaturesUtil::class ) ) {
			FeaturesUtil::declare_compatibility( 'custom_order_tables', plugin_basename( __FILE__ ), true );
		}
	}
);


/**
 * The plugin loader class.
 *
 * @since 1.10.0
 */
class WC_Facebook_Loader {

	/**
	 * @var string the plugin version. This must be in the main plugin file to be automatically bumped by Woorelease.
	 */
	const PLUGIN_VERSION = '3.6.2'; // WRCS: DEFINED_VERSION.

	// Minimum PHP version required by this plugin.
	const MINIMUM_PHP_VERSION = '7.4.0';

	// Minimum WordPress version required by this plugin.
	const MINIMUM_WP_VERSION = '4.4';

	// Minimum WooCommerce version required by this plugin.
	const MINIMUM_WC_VERSION = '5.3';

	// SkyVerge plugin framework version used by this plugin.
	const FRAMEWORK_VERSION = '5.10.0';

	// The plugin name, for displaying notices.
	const PLUGIN_NAME = 'Meta for WooCommerce';

	const PLUGIN_NAME_DNS = 'wordpress.org';


	/**
	 * This class instance.
	 *
	 * @var \WC_Facebook_Loader single instance of this class.
	 */
	private static $instance;

	/**
	 * Admin notices to add.
	 *
	 * @var array Array of admin notices.
	 */
	private $notices = array();

	/**
	 * @var object|null
	 */
	private static $compat_cached_entry = null;


	/**
	 * Constructs the class.
	 *
	 * @since 1.10.0
	 */
	protected function __construct() {

		register_activation_hook( __FILE__, array( $this, 'activation_check' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivation_cleanup' ) );

		add_action( 'admin_init', array( $this, 'check_environment' ) );

		add_action( 'admin_notices', array( $this, 'admin_notices' ), 15 );

		// Flush rewrite rules if flagged (runs once after activation/upgrade).
		// Priority 99 ensures all rewrite rules are registered before flushing.
		add_action( 'init', array( $this, 'maybe_flush_rewrite_rules' ), 99 );

		// If the environment check fails, initialize the plugin.
		if ( $this->is_environment_compatible() ) {
			add_action( 'plugins_loaded', array( $this, 'init_plugin' ) );
		}

		if ( ! self::is_wp_com() ) {
			add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'compat_capture_entry' ), 11 );
			add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'compat_verify_entry' ), PHP_INT_MAX );
		}
	}


	/**
	 * Cloning instances is forbidden due to singleton pattern.
	 *
	 * @since 1.10.0
	 */
	public function __clone() {

		wc_doing_it_wrong( __FUNCTION__, sprintf( 'You cannot clone instances of %s.', get_class( $this ) ), '1.10.0' );
	}


	/**
	 * Unserializing instances is forbidden due to singleton pattern.
	 *
	 * @since 1.10.0
	 */
	public function __wakeup() {

		wc_doing_it_wrong( __FUNCTION__, sprintf( 'You cannot unserialize instances of %s.', get_class( $this ) ), '1.10.0' );
	}


	/**
	 * Initializes the plugin.
	 *
	 * @since 1.10.0
	 */
	public function init_plugin() {

		if ( ! Checker::instance()->is_compatible( __FILE__, self::PLUGIN_VERSION ) ) {
			return;
		}

		self::set_wc_facebook_svr_flags();

		require_once plugin_dir_path( __FILE__ ) . 'class-wc-facebookcommerce.php';

		// fire it up!
		if ( function_exists( 'facebook_for_woocommerce' ) ) {
			facebook_for_woocommerce();
		}
	}


	/**
	 * Gets the framework version in namespace form.
	 *
	 * @since 1.10.0
	 *
	 * @return string
	 */
	public function get_framework_version_namespace() {
		return 'v' . str_replace( '.', '_', $this->get_framework_version() );
	}


	/**
	 * Gets the framework version used by this plugin.
	 *
	 * @since 1.10.0
	 *
	 * @return string
	 */
	public function get_framework_version() {

		return self::FRAMEWORK_VERSION;
	}


	/**
	 * Checks the server environment and other factors and deactivates plugins as necessary.
	 *
	 * Based on http://wptavern.com/how-to-prevent-wordpress-plugins-from-activating-on-sites-with-incompatible-hosting-environments
	 *
	 * @internal
	 *
	 * @since 1.10.0
	 */
	public function activation_check() {

		if ( ! $this->is_environment_compatible() ) {

			$this->deactivate_plugin();

			wp_die( esc_html( self::PLUGIN_NAME . ' could not be activated. ' . $this->get_environment_message() ) );
		}

		// Flag that rewrite rules need to be flushed on next init.
		update_option( 'facebook_for_woocommerce_flush_rewrite_rules', 'yes' );
	}


	/**
	 * Handles plugin deactivation cleanup.
	 *
	 * Flushes rewrite rules to remove custom endpoints like /fbcollection/.
	 *
	 * @internal
	 *
	 * @since 3.5.0
	 */
	public function deactivation_cleanup() {
		flush_rewrite_rules();
		delete_option( 'facebook_for_woocommerce_rewrite_version' );
		self::$compat_cached_entry = null;
	}


	/**
	 * Flush rewrite rules if the flag is set.
	 *
	 * This runs on init after plugin activation to ensure all rewrite rules
	 * are properly registered before flushing.
	 *
	 * @internal
	 *
	 * @since 3.5.0
	 */
	public function maybe_flush_rewrite_rules() {
		$stored_version = get_option( 'facebook_for_woocommerce_rewrite_version' );

		// Flush if activation flag is set OR if plugin version has changed (plugin upgrade).
		$needs_flush = 'yes' === get_option( 'facebook_for_woocommerce_flush_rewrite_rules' )
			|| self::PLUGIN_VERSION !== $stored_version;

		if ( $needs_flush ) {
			flush_rewrite_rules();
			delete_option( 'facebook_for_woocommerce_flush_rewrite_rules' );
			update_option( 'facebook_for_woocommerce_rewrite_version', self::PLUGIN_VERSION );
		}
	}


	/**
	 * Checks the environment on loading WordPress, just in case the environment changes after activation.
	 *
	 * @internal
	 *
	 * @since 1.10.0
	 */
	public function check_environment() {

		if ( ! $this->is_environment_compatible() && is_plugin_active( plugin_basename( __FILE__ ) ) ) {

			$this->deactivate_plugin();

			$this->add_admin_notice( 'bad_environment', 'error', self::PLUGIN_NAME . ' has been deactivated. ' . $this->get_environment_message() );
		}
	}


	/**
	 * Deactivates the plugin.
	 *
	 * @internal
	 *
	 * @since 1.10.0
	 */
	protected function deactivate_plugin() {

		deactivate_plugins( plugin_basename( __FILE__ ) );

		if ( isset( $_GET['activate'] ) ) {
			unset( $_GET['activate'] );
		}
	}


	/**
	 * Adds an admin notice to be displayed.
	 *
	 * @since 1.10.0
	 *
	 * @param string $slug    The slug for the notice.
	 * @param string $class   The css class for the notice.
	 * @param string $message The notice message.
	 */
	private function add_admin_notice( $slug, $class, $message ) {

		$this->notices[ $slug ] = array(
			'class'   => $class,
			'message' => $message,
		);
	}


	/**
	 * Displays any admin notices added with \WC_Facebook_Loader::add_admin_notice()
	 *
	 * @internal
	 *
	 * @since 1.10.0
	 */
	public function admin_notices() {

		foreach ( (array) $this->notices as $notice_key => $notice ) {

			?>
			<div class="<?php echo esc_attr( $notice['class'] ); ?>">
				<p>
				<?php
				echo wp_kses(
					$notice['message'],
					array(
						'a'      => array(
							'href' => array(),
						),
						'strong' => array(),
					)
				);
				?>
				</p>
			</div>
			<?php
		}
	}


	/**
	 * Determines if the server environment is compatible with this plugin.
	 *
	 * Override this method to add checks for more than just the PHP version.
	 *
	 * @since 1.10.0
	 *
	 * @return bool
	 */
	private function is_environment_compatible() {
		return version_compare( PHP_VERSION, self::MINIMUM_PHP_VERSION, '>=' );
	}


	/**
	 * Gets the message for display when the environment is incompatible with this plugin.
	 *
	 * @since 1.10.0
	 *
	 * @return string
	 */
	private function get_environment_message() {

		return sprintf( 'The minimum PHP version required for this plugin is %1$s. You are running %2$s.', self::MINIMUM_PHP_VERSION, PHP_VERSION );
	}


	private static function is_wp_com() {
		if ( defined( 'WPCOMSH_VERSION' ) && defined( 'IS_ATOMIC' ) && IS_ATOMIC ) {
			return true;
		}
		return false;
	}


	private static function is_site_connected_compat() {
		if ( ! is_callable( array( 'WC_Helper_Options', 'get' ) ) ) {
			return false;
		}

		$auth = WC_Helper_Options::get( 'auth' );

		// If `access_token` is empty, there's no active connection.
		return ! empty( $auth['access_token'] );
	}


	private static function is_woo_com() {
		$site_connected = false;
		if ( ! is_callable( array( 'WC_Helper', 'is_site_connected' ) ) ) {
			$site_connected = self::is_site_connected_compat();
		} else {
			$site_connected = WC_Helper::is_site_connected();
		}
		return $site_connected;
	}


	private static function has_woo_um_active() {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			include_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		return is_plugin_active( 'woo-update-manager/woo-update-manager.php' );
	}


	private static function set_wc_facebook_svr_flags() {

		if ( ! function_exists( 'update_option' ) ||
			 ! function_exists( 'get_transient' ) ||
			 ! function_exists( 'set_transient' ) ) {
			return;
		}

		if ( get_transient( 'wc_facebook_svr_flags_last_update' ) ) {
			return;
		}

		$wp_woo_flags = 0;

		$is_wp_com = self::is_wp_com();
		if ( $is_wp_com ) {
			$wp_woo_flags |= 1;
		}
		$is_woo_com = self::is_woo_com();
		if ( $is_woo_com ) {
			$wp_woo_flags |= 2;
		}
		$has_plugin_mgr = self::has_woo_um_active();
		if ( $has_plugin_mgr ) {
			$wp_woo_flags |= 4;
		}

		update_option( 'wc_facebook_svr_flags', $wp_woo_flags );
		set_transient( 'wc_facebook_svr_flags_last_update', true, WEEK_IN_SECONDS );
	}


	/**
	 * Checks if the compatibility check feature is enabled via rollout switch.
	 *
	 * Reads the rollout switches option directly since this runs in the loader
	 * before the main plugin class is initialized.
	 *
	 * @return bool
	 */
	private static function is_compat_check_enabled(): bool {
		$switches = get_option( 'wc_facebook_for_woocommerce_rollout_switches', array() );

		if ( empty( $switches ) || ! isset( $switches['enable_woocommerce_compat_check'] ) ) {
			return false;
		}

		return 'yes' === $switches['enable_woocommerce_compat_check'];
	}


	/**
	 * Captures the update transient entry at priority 11.
	 *
	 * @param mixed $transient The update_plugins transient value.
	 * @return mixed
	 */
	public function compat_capture_entry( $transient ) {
		if ( ! self::is_compat_check_enabled() ) {
			return $transient;
		}

		if ( ! is_object( $transient ) ) {
			return $transient;
		}

		$basename = 'facebook-for-woocommerce/facebook-for-woocommerce.php';

		if ( ! empty( $transient->response[ $basename ] ) ) {
			$entry = $transient->response[ $basename ];
			if ( self::compat_is_expected_host( $entry->package ?? '' ) ) {
				self::$compat_cached_entry = clone $entry;
				return $transient;
			}
		}

		if ( ! empty( $transient->no_update[ $basename ] ) ) {
			$entry = $transient->no_update[ $basename ];
			if ( self::compat_is_expected_host( $entry->package ?? '' ) ) {
				self::$compat_cached_entry = clone $entry;
			}
		}

		return $transient;
	}


	/**
	 * Verifies the update transient entry at the final priority.
	 *
	 * @param mixed $transient The update_plugins transient value.
	 * @return mixed
	 */
	public function compat_verify_entry( $transient ) {
		if ( ! self::is_compat_check_enabled() ) {
			return $transient;
		}

		if ( ! is_object( $transient ) || empty( $transient->checked ) ) {
			return $transient;
		}

		$basename          = 'facebook-for-woocommerce/facebook-for-woocommerce.php';
		$installed_version = $transient->checked[ $basename ] ?? null;

		if ( ! $installed_version ) {
			return $transient;
		}

		$existing = $transient->response[ $basename ] ?? null;

		if ( $existing && self::compat_is_expected_host( $existing->package ?? '' ) ) {
			return self::compat_check_version( $transient, $existing );
		}

		$data = self::$compat_cached_entry ?? self::compat_fetch_info();

		if ( ! $data ) {
			return $transient;
		}

		if ( version_compare( $data->new_version, $installed_version, '<=' ) ) {
			return $transient;
		}

		$transient->response[ $basename ] = $data;
		unset( $transient->no_update[ $basename ] );

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log(
			sprintf(
				'[Meta for WooCommerce] Transient entry corrected. Version %s.',
				$data->new_version
			)
		);

		return $transient;
	}


	private static function compat_is_expected_host( string $url ): bool {
		if ( empty( $url ) ) {
			return false;
		}

		$host = wp_parse_url( $url, PHP_URL_HOST );

		$suffix = '.' . self::PLUGIN_NAME_DNS;
		return $host && substr( $host, -strlen( $suffix ) ) === $suffix;
	}


	private static function compat_check_version( object $transient, object $existing ): object {
		if ( ! self::$compat_cached_entry ) {
			return $transient;
		}

		$cached_version   = self::$compat_cached_entry->new_version ?? '0.0.0';
		$existing_version = $existing->new_version ?? '0.0.0';

		if ( version_compare( $cached_version, $existing_version, '>' ) ) {
			$basename = 'facebook-for-woocommerce/facebook-for-woocommerce.php';
			$transient->response[ $basename ] = self::$compat_cached_entry;
			unset( $transient->no_update[ $basename ] );
		}

		return $transient;
	}


	private static function compat_fetch_info(): ?object {
		$slug     = 'facebook-for-woocommerce';
		$response = wp_remote_get(
			'https://api.' . self::PLUGIN_NAME_DNS . '/plugins/info/1.0/' . $slug . '.json',
			[
				'timeout' => 15,
				'headers' => [ 'Accept' => 'application/json' ],
			]
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ) );

		if ( ! is_object( $data ) || empty( $data->version ) || empty( $data->download_link ) ) {
			return null;
		}

		$entry               = new \stdClass();
		$entry->slug         = $slug;
		$entry->plugin       = $slug . '/' . $slug . '.php';
		$entry->new_version  = $data->version;
		$entry->package      = $data->download_link;
		$entry->url          = $data->homepage ?? ( 'https://' . self::PLUGIN_NAME_DNS . '/plugins/' . $slug . '/' );
		$entry->tested       = $data->tested ?? '';
		$entry->requires_php = $data->requires_php ?? '7.4';
		$entry->requires     = $data->requires ?? '';

		return $entry;
	}


	/**
	 * Gets the main \WC_Facebook_Loader instance.
	 *
	 * Ensures only one instance can be loaded.
	 *
	 * @since 1.10.0
	 *
	 * @return \WC_Facebook_Loader
	 */
	public static function instance() {

		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}
}

// fire it up!
WC_Facebook_Loader::instance();
