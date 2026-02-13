<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */

use WooCommerce\Facebook\Events\Event;

/**
 * Class WC_Facebookcommerce_Pixel
 *
 * This class initializes the Facebook Pixel and provides methods to track events.
 */
class WC_Facebookcommerce_Pixel {


		const SETTINGS_KEY     = 'facebook_config';
		const PIXEL_ID_KEY     = 'pixel_id';
		const USE_PII_KEY      = 'use_pii';
		const USE_S2S_KEY      = 'use_s2s';
		const ACCESS_TOKEN_KEY = 'access_token';

		/**
		 * Cache key for pixel script block output.
		 *
		 * @var string cache key.
		 * */
		const PIXEL_RENDER = 'pixel_render';

		/**
		 * Cache key for pixel noscript block output.
		 *
		 * @var string cache key.
		 * */
		const NO_SCRIPT_RENDER = 'no_script_render';

		/**
		 * Script render memoization helper.
		 *
		 * @var array Cache array.
		 */
	public static $render_cache = [];

	/**
	 * Queued pixel events for isolated script execution.
	 *
	 * Events are collected here and output via wp_localize_script() to an external
	 * JS file, preventing errors from other plugins breaking pixel tracking.
	 *
	 * @var array Queued events array.
	 */
	private static $static_events = [];

	/**
	 * Whether external script has been enqueued.
	 *
	 * @var bool
	 */
	private static $script_enqueued = false;

	/**
	 * Whether hooks have been initialized.
	 *
	 * @var bool
	 */
	private static $hooks_initialized = false;

		/**
		 * User information.
		 *
		 * @var array Information array.
		 */
		private $user_info;

		/**
		 * The name of the last event.
		 *
		 * @var string Event name.
		 */
		private $last_event;

		/**
		 * Class constructor.
		 *
		 * @param array $user_info User information array.
		 */
	public function __construct( $user_info = [] ) {
		$this->user_info  = $user_info;
		$this->last_event = '';
	}

	/**
	 * Initialize hooks for external JavaScript event handling.
	 * Uses wp_localize_script() + external JS file to prevent JavaScript errors
	 * from other plugins breaking our pixel tracking.
	 */
	public static function init_external_js_hooks() {
		if ( self::$hooks_initialized ) {
			return;
		}

		self::$hooks_initialized = true;

		// Deferred events from previous page are loaded via WC_Facebookcommerce_Utils::print_deferred_events()
		// which is hooked to wp_head in facebook-commerce-events-tracker.php.

		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_pixel_events_script' ) );

		// Pass event data to JavaScript before footer scripts.
		add_action( 'wp_footer', array( __CLASS__, 'localize_pixel_events_data' ), 5 );
	}

	/**
	 * Enqueues the external pixel events script.
	 * External script runs in isolated context - not affected by other plugin errors.
	 */
	public static function enqueue_pixel_events_script() {
		if ( self::$script_enqueued ) {
			return;
		}

		$pixel_id = self::get_pixel_id();
		if ( empty( $pixel_id ) ) {
			return;
		}

		self::$script_enqueued = true;

		wp_enqueue_script(
			'wc-facebook-pixel-events',
			plugins_url( 'assets/js/frontend/pixel-events.js', __FILE__ ),
			array(),
			WC_Facebookcommerce_Utils::PLUGIN_VERSION,
			true  // Load in footer, after fbq is initialized.
		);
	}

	/**
	 * Passes static events data to the frontend JavaScript.
	 * Uses wp_localize_script() to pass data (not code) to the external script.
	 */
	public static function localize_pixel_events_data() {
		if ( ! self::$script_enqueued || empty( self::$static_events ) ) {
			return;
		}

		$pixel_id = self::get_pixel_id();

		wp_localize_script(
			'wc-facebook-pixel-events',
			'wc_facebook_pixel_data',
			array(
				'pixelId'      => esc_js( $pixel_id ),
				'staticEvents' => self::$static_events,
				'agentString'  => Event::get_platform_identifier(),
			)
		);
	}

	/**
	 * Adds an event to the static events array for immediate frontend execution.
	 * Events are stored as DATA, not executable code.
	 *
	 * @param string $event_name The name of the event to track.
	 * @param array  $params     Event parameters.
	 * @param string $method     The fbq method to use (track, trackCustom, etc.).
	 * @param string $event_id   Optional event ID for deduplication.
	 */
	public static function add_static_event( $event_name, $params, $method = 'track', $event_id = '' ) {
		// Initialize hooks if not already done.
		self::init_external_js_hooks();

		$event_data = array(
			'name'   => $event_name,
			'params' => $params,
			'method' => $method,
		);

		if ( ! empty( $event_id ) ) {
			$event_data['eventId'] = $event_id;
		}

		self::$static_events[] = $event_data;
	}

	/**
	 * Adds an event to the deferred events queue for next page load.
	 * Used for isolated pixel execution when events need to be deferred (e.g., AddToCart with redirect).
	 *
	 * @param string $event_name The name of the event to track.
	 * @param array  $params     Event parameters.
	 * @param string $method     The fbq method to use (track, trackCustom, etc.).
	 * @param string $event_id   Optional event ID for deduplication.
	 */
	public static function add_deferred_static_event( $event_name, $params, $method = 'track', $event_id = '' ) {
		$event_data = array(
			'name'   => $event_name,
			'params' => $params,
			'method' => $method,
		);

		if ( ! empty( $event_id ) ) {
			$event_data['eventId'] = $event_id;
		}

		WC_Facebookcommerce_Utils::add_deferred_event( $event_data );
	}

	/**
	 * Prepares event parameters for pixel tracking.
	 *
	 * Extracts event_id, unwraps custom_data, and applies build_params().
	 *
	 * @param array  $params     Raw event parameters.
	 * @param string $event_name The name of the event.
	 * @return array ['params' => array, 'event_id' => string]
	 */
	private static function prepare_event_params( $params, $event_name ) {
		$event_id = '';

		// Do not send the event name in the params.
		if ( isset( $params['event_name'] ) ) {
			unset( $params['event_name'] );
		}

		/**
		 * If possible, send the event ID to avoid duplication.
		 *
		 * @see https://developers.facebook.com/docs/marketing-api/server-side-api/deduplicate-pixel-and-server-side-events#deduplication-best-practices
		 */
		if ( isset( $params['event_id'] ) ) {
			$event_id = $params['event_id'];
			unset( $params['event_id'] );
		}

		// If custom_data is set, extract it (send only the inner data).
		if ( isset( $params['custom_data'] ) ) {
			$params = $params['custom_data'];
		}

		// Apply build_params() to add version info and apply filters.
		$params = self::build_params( $params, $event_name );

		return array(
			'params'   => $params,
			'event_id' => $event_id,
		);
	}

		/**
		 * Initialize pixelID.
		 */
	public static function initialize() {
		if ( ! is_admin() ) {
			return;
		}

		// Initialize PixelID in storage - this will only need to happen when the user is an admin.
		$pixel_id = self::get_pixel_id();
		if ( ! WC_Facebookcommerce_Utils::is_valid_id( $pixel_id ) &&
		class_exists( 'WC_Facebookcommerce_WarmConfig' ) ) {
			$fb_warm_pixel_id = WC_Facebookcommerce_WarmConfig::$fb_warm_pixel_id;

			// phpcs:disable Universal.Operators.StrictComparisons.LooseEqual
			if ( WC_Facebookcommerce_Utils::is_valid_id( $fb_warm_pixel_id ) &&
			(int) $fb_warm_pixel_id == $fb_warm_pixel_id ) {
				$fb_warm_pixel_id = (string) $fb_warm_pixel_id;
				self::set_pixel_id( $fb_warm_pixel_id );
			}
		}

		$is_advanced_matching_enabled = self::get_use_pii_key();
		//phpcs:disable Universal.Operators.StrictComparisons.LooseEqual
		if ( null == $is_advanced_matching_enabled &&
		class_exists( 'WC_Facebookcommerce_WarmConfig' ) ) {
			$fb_warm_is_advanced_matching_enabled =
			WC_Facebookcommerce_WarmConfig::$fb_warm_is_advanced_matching_enabled;
			if ( is_bool( $fb_warm_is_advanced_matching_enabled ) ) {
				self::set_use_pii_key( $fb_warm_is_advanced_matching_enabled ? 1 : 0 );
			}
		}
	}


		/**
		 * Gets Facebook Pixel init code.
		 *
		 * Init code might contain additional information to help matching website users with facebook users.
		 * Information is hashed in JS side using SHA256 before sending to Facebook.
		 *
		 * @return string
		 */
	private function get_pixel_init_code() {

		$agent_string = Event::get_platform_identifier();

		/**
		 * Filters Facebook Pixel init code.
		 *
		 * @param string $js_code
		 */
		return apply_filters(
			'facebook_woocommerce_pixel_init',
			sprintf(
				"fbq('init', '%s', %s, %s);\n",
				esc_js( self::get_pixel_id() ),
				wp_json_encode( $this->user_info, JSON_PRETTY_PRINT | JSON_FORCE_OBJECT ),
				wp_json_encode( array( 'agent' => $agent_string ), JSON_PRETTY_PRINT | JSON_FORCE_OBJECT )
			)
		);
	}


		/**
		 * Gets the Facebook Pixel code scripts.
		 *
		 * @return string HTML scripts
		 *
		 * phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
		 */
	public function pixel_base_code() {

		$pixel_id = self::get_pixel_id();

		// Bail if no ID or already rendered.
		if ( empty( $pixel_id ) || ! empty( self::$render_cache[ self::PIXEL_RENDER ] ) ) {
			return '';
		}

		self::$render_cache[ self::PIXEL_RENDER ] = true;

		ob_start();

		?>
			<script <?php echo self::get_script_attributes(); ?>>
				!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?
					n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;
					n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;
					t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,
					document,'script','https://connect.facebook.net/en_US/fbevents.js');
			</script>
			<!-- WooCommerce Facebook Integration Begin -->
			<script <?php echo self::get_script_attributes(); ?>>

				<?php echo $this->get_pixel_init_code(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

				document.addEventListener( 'DOMContentLoaded', function() {
					// Insert placeholder for events injected when a product is added to the cart through AJAX.
					document.body.insertAdjacentHTML( 'beforeend', '<div class=\"wc-facebook-pixel-event-placeholder\"></div>' );
				}, false );

			</script>
			<!-- WooCommerce Facebook Integration End -->
			<?php

			return ob_get_clean();
	}


		/**
		 * Gets Facebook Pixel code noscript part to avoid W3 validation errors.
		 *
		 * @return string
		 */
	public function pixel_base_code_noscript() {

		$pixel_id = self::get_pixel_id();

		if ( empty( $pixel_id ) || ! empty( self::$render_cache[ self::NO_SCRIPT_RENDER ] ) ) {
			return '';
		}

		self::$render_cache[ self::NO_SCRIPT_RENDER ] = true;

		ob_start();

		?>
			<!-- Facebook Pixel Code -->
			<noscript>
				<img
					height="1"
					width="1"
					style="display:none"
					alt="fbpx"
					src="https://www.facebook.com/tr?id=<?php echo esc_attr( $pixel_id ); ?>&ev=PageView&noscript=1"
				/>
			</noscript>
			<!-- End Facebook Pixel Code -->
			<?php

			return ob_get_clean();
	}


		/**
		 * Determines if the last event in the current thread matches a given event.
		 *
		 * @since 1.11.0
		 *
		 * @param string $event_name
		 * @return bool
		 */
	public function is_last_event( $event_name ) {

		return $event_name === $this->last_event;
	}


		/**
		 * Gets the JavaScript code to track an event.
		 *
		 * Updates the last event property and returns the code.
		 *
		 * Use {@see \WC_Facebookcommerce_Pixel::inject_event()} to print or enqueue the code.
		 *
		 * @since 1.10.2
		 *
		 * @param string $event_name The name of the event to track.
		 * @param array  $params     Custom event parameters.
		 * @param string $method     Name of the pixel's fbq() function to call.
		 * @return string
		 */
	public function get_event_code( $event_name, $params, $method = 'track' ) {

		$this->last_event = $event_name;

		return self::build_event( $event_name, $params, $method );
	}


		/**
		 * Gets the JavaScript code to track an event wrapped in <script> tag.
		 *
		 * @see \WC_Facebookcommerce_Pixel::get_event_code()
		 *
		 * @since 1.10.2
		 *
		 * @param string $event_name The name of the event to track.
		 * @param array  $params     Custom event parameters.
		 * @param string $method     Name of the pixel's fbq() function to call.
		 * @return string
		 *
		 * phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
		 */
	public function get_event_script( $event_name, $params, $method = 'track' ) {

		ob_start();

		?>
			<!-- Facebook Pixel Event Code -->
			<script <?php echo self::get_script_attributes(); ?>>
			<?php echo $this->get_event_code( $event_name, $params, $method ); ?>
			</script>
			<!-- End Facebook Pixel Event Code -->
			<?php

			return ob_get_clean();
	}

	/**
	 * Prints or enqueues the JavaScript code to track an event.
	 * Preferred method to inject events in a page.
	 *
	 * Supports two execution modes controlled by rollout switch:
	 * - Isolated execution (switch ON): Uses external JS via wp_localize_script() to prevent
	 *   other plugins' JavaScript errors from breaking pixel tracking.
	 * - Legacy execution (switch OFF): Uses wc_enqueue_js() for inline script output.
	 *
	 * @see \WC_Facebookcommerce_Pixel::build_event()
	 *
	 * @param string $event_name The name of the event to track.
	 * @param array  $params     Custom event parameters.
	 * @param string $method     Name of the pixel's fbq() function to call.
	 *
	 * phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
	 */
	public function inject_event( $event_name, $params, $method = 'track' ) {
		// Note: We will be adding a consent mechanism that checks user consent
		// before inject_event() is called.
		if ( WC_Facebookcommerce_Utils::is_woocommerce_integration() ) {
			// Check rollout switch for isolated pixel execution.
			// When enabled, pixel events are output via external JS file (wp_localize_script)
			// to prevent other plugins' JavaScript errors from breaking pixel tracking.
			$is_isolated_pixel_execution_enabled = facebook_for_woocommerce()->get_rollout_switches()->is_switch_enabled(
				\WooCommerce\Facebook\RolloutSwitches::SWITCH_ISOLATED_PIXEL_EXECUTION_ENABLED
			);

			// If we have add to cart redirect enabled, we must defer the AddToCart events to render them the next page load.
			$is_redirect    = 'yes' === get_option( 'woocommerce_cart_redirect_after_add', 'no' );
			$is_add_to_cart = 'AddToCart' === $event_name;
			$is_deferred    = $is_redirect && $is_add_to_cart;

			if ( $is_isolated_pixel_execution_enabled ) {
				// Isolated execution: Use external JS via wp_localize_script.
				// Set last_event here since we don't call get_event_code() in this path.
				$this->last_event = $event_name;
				[ 'params' => $event_params, 'event_id' => $event_id ] = self::prepare_event_params( $params, $event_name );

				if ( $is_deferred ) {
					// Store event data for next page load.
					self::add_deferred_static_event( $event_name, $event_params, $method, $event_id );
				} else {
					// Queue event for this page's external script.
					self::add_static_event( $event_name, $event_params, $method, $event_id );
				}
			} else {
				// Legacy execution: Use wc_enqueue_js for inline script.
				$code = $this->get_event_code( $event_name, self::build_params( $params, $event_name ), $method );

				if ( $is_deferred ) {
					// Store JS code string for inline script at print time.
					WC_Facebookcommerce_Utils::add_deferred_event( $code );
				} else {
					WC_Facebookcommerce_Utils::wc_enqueue_js( $code );
				}
			}
		} else {
			printf( $this->get_event_script( $event_name, self::build_params( $params, $event_name ), $method ) ); // phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped
		}
	}

		/**
		 * Gets the JavaScript code to track a conditional event wrapped in <script> tag.
		 *
		 * @see \WC_Facebookcommerce_Pixel::get_event_code()
		 *
		 * @since 1.10.2
		 *
		 * @param string $event_name    The name of the event to track.
		 * @param array  $params        Custom event parameters.
		 * @param string $listener      Name of the JavaScript event to listen for.
		 * @param string $jsonified_pii JavaScript code representing an object of data for Advanced Matching.
		 * @return string
		 *
		 * phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
		 */
	public function get_conditional_event_script( $event_name, $params, $listener, $jsonified_pii ) {

		$code             = self::build_event( $event_name, $params, 'track' );
		$this->last_event = $event_name;

		/**
		 * TODO: use the settings stored by {@see \WC_Facebookcommerce_Integration}.
		 * The use_pii setting here is currently always disabled regardless of
		 * the value configured in the plugin settings page {WV-2020-01-02}.
		 */

		// Prepends fbq(...) with pii information to the injected code.
		if ( $jsonified_pii && get_option( self::SETTINGS_KEY )[ self::USE_PII_KEY ] ) {
			$this->user_info = '%s';
			$code            = sprintf( $this->get_pixel_init_code(), '" || ' . $jsonified_pii . ' || "' ) . $code;
		}

		ob_start();

		?>
			<!-- Facebook Pixel Event Code -->
			<script <?php echo self::get_script_attributes(); ?>>
				document.addEventListener( '<?php echo esc_js( $listener ); ?>', function (event) {
				<?php echo $code; ?>
				}, false );
			</script>
			<!-- End Facebook Pixel Event Code -->
			<?php

			return ob_get_clean();
	}


		/**
		 * Prints the JavaScript code to track a conditional event.
		 *
		 * The tracking code will be executed when the given JavaScript event is triggered.
		 *
		 * @param string $event_name    Name of the event.
		 * @param array  $params        Custom event parameters.
		 * @param string $listener      Name of the JavaScript event to listen for.
		 * @param string $jsonified_pii JavaScript code representing an object of data for Advanced Matching.
		 * @return string
		 */
	public function inject_conditional_event( $event_name, $params, $listener, $jsonified_pii = '' ) {

		// phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped
		return $this->get_conditional_event_script( $event_name, self::build_params( $params, $event_name ), $listener, $jsonified_pii );
	}


		/**
		 * Gets the JavaScript code to track a conditional event that is only triggered one time wrapped in <script> tag.
		 *
		 * @internal
		 *
		 * @since 1.10.2
		 *
		 * @param string $event_name     The name of the event to track.
		 * @param array  $params         Custom event parameters.
		 * @param string $listened_event Name of the JavaScript event to listen for.
		 * @return string
		 *
		 * phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
		 */
	public function get_conditional_one_time_event_script( $event_name, $params, $listened_event ) {

		$code = $this->get_event_code( $event_name, $params );

		ob_start();

		?>
			<!-- Facebook Pixel Event Code -->
			<script <?php echo self::get_script_attributes(); ?>>
				function handle<?php echo $event_name; ?>Event() {
				<?php echo $code; ?>
					// Some weird themes (hi, Basel) are running this script twice, so two listeners are added and we need to remove them after running one.
					jQuery( document.body ).off( '<?php echo esc_js( $listened_event ); ?>', handle<?php echo $event_name; ?>Event );
				}

				jQuery( document.body ).one( '<?php echo esc_js( $listened_event ); ?>', handle<?php echo $event_name; ?>Event );
			</script>
			<!-- End Facebook Pixel Event Code -->
			<?php

			return ob_get_clean();
	}


		/**
		 * Builds an event.
		 *
		 * @see \WC_Facebookcommerce_Pixel::inject_event() for the preferred method to inject an event.
		 *
		 * @param string $event_name Event name.
		 * @param array  $params     Event params.
		 * @param string $method     Optional, defaults to 'track'.
		 * @return string
		 */
	public static function build_event( $event_name, $params, $method = 'track' ) {
		// Reuse shared param preparation logic.
		[ 'params' => $event_params, 'event_id' => $event_id ] = self::prepare_event_params( $params, $event_name );

		if ( ! empty( $event_id ) ) {
			$event = sprintf(
				"/* %s Facebook Integration Event Tracking */\n" .
				"fbq('set', 'agent', '%s', '%s');\n" .
				"fbq('%s', '%s', %s, %s);",
				WC_Facebookcommerce_Utils::get_integration_name(),
				Event::get_platform_identifier(),
				self::get_pixel_id(),
				esc_js( $method ),
				esc_js( $event_name ),
				wp_json_encode( $event_params, JSON_PRETTY_PRINT | JSON_FORCE_OBJECT ),
				wp_json_encode( array( 'eventID' => $event_id ), JSON_PRETTY_PRINT | JSON_FORCE_OBJECT )
			);

		} else {

				$event = sprintf(
					"/* %s Facebook Integration Event Tracking */\n" .
					"fbq('set', 'agent', '%s', '%s');\n" .
					"fbq('%s', '%s', %s);",
					WC_Facebookcommerce_Utils::get_integration_name(),
					Event::get_platform_identifier(),
					self::get_pixel_id(),
					esc_js( $method ),
					esc_js( $event_name ),
					wp_json_encode( $event_params, JSON_PRETTY_PRINT | JSON_FORCE_OBJECT )
				);
		}

		return $event;
	}


		/**
		 * Gets an array with version_info for pixel fires.
		 *
		 * Parameters provided by users should not be overwritten by this function.
		 *
		 * @since 1.10.2
		 *
		 * @param array  $params User defined parameters.
		 * @param string $event  The event name the params are for.
		 * @return array
		 */
	private static function build_params( $params = [], $event = '' ) {

		$params = array_replace( Event::get_version_info(), $params );

		/**
		 * Filters the parameters for the pixel code.
		 *
		 * @since 1.10.2
		 *
		 * @param array $params User defined parameters.
		 * @param string $event The event name.
		 */
		return (array) apply_filters( 'wc_facebook_pixel_params', $params, $event );
	}


		/**
		 * Gets script tag attributes.
		 *
		 * @since 1.10.2
		 *
		 * @return string
		 */
	private static function get_script_attributes() {

		$script_attributes = '';

		/**
		 * Filters Facebook Pixel script attributes.
		 *
		 * @since 1.10.2
		 *
		 * @param array $custom_attributes
		 */
		$custom_attributes = (array) apply_filters( 'wc_facebook_pixel_script_attributes', array( 'type' => 'text/javascript' ) );

		foreach ( $custom_attributes as $tag => $value ) {
			$script_attributes .= ' ' . $tag . '="' . esc_attr( $value ) . '"';
		}

		return $script_attributes;
	}

		/**
		 * Get the PixelId.
		 */
	public static function get_pixel_id() {
		$fb_options = self::get_options();
		if ( ! $fb_options ) {
			return '';
		}
		return isset( $fb_options[ self::PIXEL_ID_KEY ] ) ?
			$fb_options[ self::PIXEL_ID_KEY ] : '';
	}

		/**
		 * Set the PixelId.
		 *
		 * @param string $pixel_id PixelId.
		 */
	public static function set_pixel_id( $pixel_id ) {
		$fb_options = self::get_options();

		if ( isset( $fb_options[ self::PIXEL_ID_KEY ] )
			&& $fb_options[ self::PIXEL_ID_KEY ] === $pixel_id ) {
			return;
		}

		$fb_options[ self::PIXEL_ID_KEY ] = $pixel_id;
		update_option( self::SETTINGS_KEY, $fb_options );
	}

		/**
		 * Check if PII key use is enabled.
		 */
	public static function get_use_pii_key() {
		$fb_options = self::get_options();
		if ( ! $fb_options ) {
			return null;
		}
		return isset( $fb_options[ self::USE_PII_KEY ] ) ?
			$fb_options[ self::USE_PII_KEY ] : null;
	}

		/**
		 * Enable or disable use of PII key.
		 *
		 * @param string $use_pii PII key.
		 */
	public static function set_use_pii_key( $use_pii ) {
		$fb_options = self::get_options();

		if ( isset( $fb_options[ self::USE_PII_KEY ] )
			&& $fb_options[ self::USE_PII_KEY ] === $use_pii ) {
			return;
		}

		$fb_options[ self::USE_PII_KEY ] = $use_pii;
		update_option( self::SETTINGS_KEY, $fb_options );
	}

		/**
		 * Check if S2S is set.
		 */
	public static function get_use_s2s() {
		$fb_options = self::get_options();
		if ( ! $fb_options ) {
			return false;
		}
		return isset( $fb_options[ self::USE_S2S_KEY ] ) ?
			$fb_options[ self::USE_S2S_KEY ] : false;
	}

		/**
		 * Enable or disable use of S2S key.
		 *
		 * @param string $use_s2s S2S setting.
		 */
	public static function set_use_s2s( $use_s2s ) {
		$fb_options = self::get_options();

		if ( isset( $fb_options[ self::USE_S2S_KEY ] )
			&& $fb_options[ self::USE_S2S_KEY ] === $use_s2s ) {
			return;
		}

		$fb_options[ self::USE_S2S_KEY ] = $use_s2s;
		update_option( self::SETTINGS_KEY, $fb_options );
	}

		/**
		 * Get access token.
		 */
	public static function get_access_token() {
		$fb_options = self::get_options();
		if ( ! $fb_options ) {
			return '';
		}
		return isset( $fb_options[ self::ACCESS_TOKEN_KEY ] ) ?
			$fb_options[ self::ACCESS_TOKEN_KEY ] : '';
	}

		/**
		 * Set access token.
		 *
		 * @param string $access_token Access token.
		 */
	public static function set_access_token( $access_token ) {
		$fb_options = self::get_options();

		if ( isset( $fb_options[ self::ACCESS_TOKEN_KEY ] )
			&& $fb_options[ self::ACCESS_TOKEN_KEY ] === $access_token ) {
			return;
		}

		$fb_options[ self::ACCESS_TOKEN_KEY ] = $access_token;
		update_option( self::SETTINGS_KEY, $fb_options );
	}

		/**
		 * Get WooCommerce/Wordpress information.
		 */
	private static function get_version_info() {
		global $wp_version;

		if ( WC_Facebookcommerce_Utils::is_woocommerce_integration() ) {
			return array(
				'source'        => 'woocommerce',
				'version'       => WC()->version,
				'pluginVersion' => WC_Facebookcommerce_Utils::PLUGIN_VERSION,
			);
		}

		return array(
			'source'        => 'wordpress',
			'version'       => $wp_version,
			'pluginVersion' => WC_Facebookcommerce_Utils::PLUGIN_VERSION,
		);
	}

		/**
		 * Get PixelID related settings.
		 */
	public static function get_options() {

		$default_options = array(
			self::PIXEL_ID_KEY     => '0',
			self::USE_PII_KEY      => 0,
			self::USE_S2S_KEY      => false,
			self::ACCESS_TOKEN_KEY => '',
		);

		$fb_options = get_option( self::SETTINGS_KEY );

		if ( ! is_array( $fb_options ) ) {
			$fb_options = $default_options;
		} else {
			foreach ( $default_options as $key => $value ) {
				if ( ! isset( $fb_options[ $key ] ) ) {
					$fb_options[ $key ] = $value;
				}
			}
		}

		return $fb_options;
	}

		/**
		 * Gets the logged in user info
		 *
		 * @return string[]
		 */
	public function get_user_info() {
		return $this->user_info;
	}
}
