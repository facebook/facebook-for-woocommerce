<?php
/**
 * Facebook for WooCommerce
 */

namespace WooCommerce\Facebook\Framework\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * WooCommerce Compatibility Utility Class
 *
 * The unfortunate purpose of this class is to provide a single point of
 * compatibility functions for dealing with supporting multiple versions
 * of WooCommerce and various extensions.
 *
 * The expected procedure is to remove methods from this class, using the
 * latest ones directly in code, as support for older versions of WooCommerce
 * are dropped.
 *
 * Current Compatibility
 * + Core 3.0.9 - 3.7.x
 * + Subscriptions 2.2.x
 *
 * // TODO: move to /compatibility
 *
 * @since 2.0.0
 */
class Compatibility {

	/**
	 * Retrieves a list of the latest available WooCommerce versions.
	 *
	 * Excludes betas, release candidates and development versions.
	 * Versions are sorted from most recent to least recent.
	 *
	 * @since 5.4.1
	 *
	 * @return string[] array of semver strings
	 */
	public static function get_latest_wc_versions() {
		$latest_wc_versions = get_transient( 'sv_wc_plugin_wc_versions' );
		if ( ! is_array( $latest_wc_versions ) ) {
			/** @link https://codex.wordpress.org/WordPress.org_API */
			$wp_org_request = wp_remote_get( 'https://api.wordpress.org/plugins/info/1.0/woocommerce.json', [ 'timeout' => 1 ] );
			if ( is_array( $wp_org_request ) && isset( $wp_org_request['body'] ) ) {
				$plugin_info = json_decode( $wp_org_request['body'], true );
				if ( is_array( $plugin_info ) && ! empty( $plugin_info['versions'] ) && is_array( $plugin_info['versions'] ) ) {
					$latest_wc_versions = [];
					// reverse array as WordPress supplies oldest version first, newest last
					foreach ( array_keys( array_reverse( $plugin_info['versions'] ) ) as $wc_version ) {
						// skip trunk, release candidates, betas and other non-final or irregular versions
						if (
							is_string( $wc_version )
							&& '' !== $wc_version
							&& is_numeric( $wc_version[0] )
							&& false === strpos( $wc_version, '-' )
						) {
							$latest_wc_versions[] = $wc_version;
						}
					}
					set_transient( 'sv_wc_plugin_wc_versions', $latest_wc_versions, WEEK_IN_SECONDS );
				}
			}
		}
		return is_array( $latest_wc_versions ) ? $latest_wc_versions : [];
	}

	/**
	 * Gets the version of the currently installed WooCommerce.
	 *
	 * @since 3.0.0
	 *
	 * @return string|null Woocommerce version number or null if undetermined
	 */
	public static function get_wc_version() {
		return defined( 'WC_VERSION' ) ? constant( 'WC_VERSION' ) : null;
	}


	/**
	 * Determines if the installed version of WooCommerce is equal or greater than a given version.
	 *
	 * @since 4.7.3
	 *
	 * @param string $version version number to compare
	 * @return bool
	 */
	public static function is_wc_version_gte( $version ) {

		$wc_version = self::get_wc_version();

		return $wc_version && version_compare( $wc_version, $version, '>=' );
	}


	/**
	 * Determines whether the enhanced admin is available.
	 *
	 * This checks both for WooCommerce v4.0+ and the underlying package availability.
	 *
	 * @since 5.6.0
	 *
	 * @return bool
	 */
	public static function is_enhanced_admin_available() {
		return self::is_wc_version_gte( '4.0' ) && function_exists( 'wc_admin_url' );
	}


	/**
	 * Converts a shorthand byte value to an integer byte value.
	 *
	 * Wrapper for wp_convert_hr_to_bytes(), moved to load.php in WordPress 4.6 from media.php
	 *
	 * Based on ActionScheduler's compat wrapper for the same function:
	 * ActionScheduler_Compatibility::convert_hr_to_bytes()
	 *
	 * @link https://secure.php.net/manual/en/function.ini-get.php
	 * @link https://secure.php.net/manual/en/faq.using.php#faq.using.shorthandbytes
	 *
	 * @since 5.3.1
	 *
	 * @param string $value A (PHP ini) byte value, either shorthand or ordinary.
	 * @return int An integer byte value.
	 */
	public static function convert_hr_to_bytes( $value ) {

		if ( function_exists( 'wp_convert_hr_to_bytes' ) ) {

			return wp_convert_hr_to_bytes( $value );
		}

		$value = strtolower( trim( $value ) );
		$bytes = (int) $value;

		if ( false !== strpos( $value, 'g' ) ) {

			$bytes *= GB_IN_BYTES;

		} elseif ( false !== strpos( $value, 'm' ) ) {

			$bytes *= MB_IN_BYTES;

		} elseif ( false !== strpos( $value, 'k' ) ) {

			$bytes *= KB_IN_BYTES;
		}

		// deal with large (float) values which run into the maximum integer size
		return min( $bytes, PHP_INT_MAX );
	}
}
