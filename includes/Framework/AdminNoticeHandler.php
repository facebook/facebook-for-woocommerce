<?php
/**
 * Facebook for WooCommerce.
 */

namespace WooCommerce\Facebook\Framework;

defined( 'ABSPATH' ) || exit;

/**
 * Admin Notice Handler Class
 *
 * The purpose of this class is to provide a facility for displaying
 * conditional (often dismissible) admin notices during a single page
 * request
 */
class AdminNoticeHandler {

	/** @var Plugin the plugin */
	private $plugin;

	/** @var array associative array of id to notice text */
	private $admin_notices = [];

	/** @var boolean static member to enforce a single rendering of the admin notice placeholder element */
	private static $admin_notice_placeholder_rendered = false;

	/** @var boolean static member to enforce a single rendering of the admin notice javascript */
	private static $admin_notice_js_rendered = false;


	/**
	 * Initialize and setup the Admin Notice Handler
	 *
	 * @since 3.0.0
	 * @param Plugin $plugin The plugin instance.
	 */
	public function __construct( $plugin ) {

		$this->plugin = $plugin;

		// render any admin notices, delayed notices, and
		add_action( 'admin_notices', array( $this, 'render_admin_notices' ), 15 );
		add_action( 'admin_footer', array( $this, 'render_delayed_admin_notices' ), 15 );
		add_action( 'admin_footer', array( $this, 'render_admin_notice_js' ), 20 );

		// AJAX handler to dismiss any warning/error notices
		add_action( 'wp_ajax_wc_plugin_framework_' . $this->get_plugin()->get_id() . '_dismiss_notice', array( $this, 'handle_dismiss_notice' ) );
	}


	/**
	 * Adds the given $message as a dismissible notice identified by $message_id,
	 * unless the notice has been dismissed, or we're on the plugin settings page
	 *
	 * @since 3.0.0
	 * @param string $message the notice message to display
	 * @param string $message_id the message id
	 * @param array  $params {
	 *     Optional parameters.
	 *
	 *     @type bool $dismissible             If the notice should be dismissible
	 *     @type bool $always_show_on_settings If the notice should be forced to display on the
	 *                                         plugin settings page, regardless of `$dismissible`.
	 *     @type string $notice_class          Additional classes for the notice.
	 * }
	 */
	public function add_admin_notice( $message, $message_id, $params = [] ) {

		$params = wp_parse_args(
			$params,
			array(
				'dismissible'             => true,
				'always_show_on_settings' => true,
				'notice_class'            => 'updated',
			)
		);

		if ( $this->should_display_notice( $message_id, $params ) ) {
			$this->admin_notices[ $message_id ] = array(
				'message'  => $message,
				'rendered' => false,
				'params'   => $params,
			);
		}
	}


	/**
	 * Returns true if the identified notice hasn't been cleared, or we're on
	 * the plugin settings page (where notices are always displayed)
	 *
	 * @since 3.0.0
	 * @param string $message_id the message id
	 * @param array  $params {
	 *     Optional parameters.
	 *
	 *     @type bool $dismissible             If the notice should be dismissible
	 *     @type bool $always_show_on_settings If the notice should be forced to display on the
	 *                                         plugin settings page, regardless of `$dismissible`.
	 * }
	 * @return bool
	 */
	public function should_display_notice( $message_id, $params = [] ) {

		// bail out if user is not a shop manager
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return false;
		}

		$params = wp_parse_args(
			$params,
			array(
				'dismissible'             => true,
				'always_show_on_settings' => true,
			)
		);

		// if the notice is always shown on the settings page, and we're on the settings page
		if ( $params['always_show_on_settings'] && $this->get_plugin()->is_plugin_settings() ) {
			return true;
		}

		// non-dismissible, always display
		if ( ! $params['dismissible'] ) {
			return true;
		}

		// dismissible: display if notice has not been dismissed
		return ! $this->is_notice_dismissed( $message_id );
	}


	/**
	 * Render any admin notices, as well as the admin notice placeholder
	 *
	 * @since 3.0.0
	 * @param boolean $is_visible true if the notices should be immediately visible, false otherwise
	 */
	public function render_admin_notices( $is_visible = true ) {

		// default for actions
		if ( ! is_bool( $is_visible ) ) {
			$is_visible = true;
		}

		foreach ( $this->admin_notices as $message_id => $message_data ) {
			if ( ! $message_data['rendered'] ) {
				$message_data['params']['is_visible'] = $is_visible;
				$this->render_admin_notice( $message_data['message'], $message_id, $message_data['params'] );
				$this->admin_notices[ $message_id ]['rendered'] = true;
			}
		}

		if ( $is_visible && ! self::$admin_notice_placeholder_rendered ) {
			// placeholder for moving delayed notices up into place
			echo '<div class="js-wc-' . esc_attr( $this->get_plugin()->get_id_dasherized() ) . '-admin-notice-placeholder"></div>';
			self::$admin_notice_placeholder_rendered = true;
		}
	}


	/**
	 * Render any delayed admin notices, which have not yet already been rendered
	 *
	 * @since 3.0.0
	 */
	public function render_delayed_admin_notices() {
		$this->render_admin_notices( false );
	}


	/**
	 * Render a single admin notice
	 *
	 * @since 3.0.0
	 * @param string $message the notice message to display
	 * @param string $message_id the message id
	 * @param array  $params {
	 *     Optional parameters.
	 *
	 *     @type bool $dismissible             If the notice should be dismissible
	 *     @type bool $is_visible              If the notice should be immediately visible
	 *     @type bool $always_show_on_settings If the notice should be forced to display on the
	 *                                         plugin settings page, regardless of `$dismissible`.
	 *     @type string $notice_class          Additional classes for the notice.
	 * }
	 */
	public function render_admin_notice( $message, $message_id, $params = [] ) {

		$params = wp_parse_args(
			$params,
			array(
				'dismissible'             => true,
				'is_visible'              => true,
				'always_show_on_settings' => true,
				'notice_class'            => 'updated',
			)
		);

		$classes = array(
			'notice',
			'js-wc-plugin-framework-admin-notice',
			$params['notice_class'],
		);

		// maybe make this notice dismissible
		// uses a WP core class which handles the markup and styling
		if ( $params['dismissible'] && ( ! $params['always_show_on_settings'] || ! $this->get_plugin()->is_plugin_settings() ) ) {
			$classes[] = 'is-dismissible';
		}

		printf(
			'<div class="%1$s" data-plugin-id="%2$s" data-message-id="%3$s" %4$s><p>%5$s</p></div>',
			esc_attr( implode( ' ', $classes ) ),
			esc_attr( $this->get_plugin()->get_id() ),
			esc_attr( $message_id ),
			esc_html( ( ! $params['is_visible'] ) ? 'style="display:none;"' : '' ),
			wp_kses_post( $message )
		);
	}


	/**
	 * Render the javascript to handle the notice "dismiss" functionality
	 *
	 * @since 3.0.0
	 */
	public function render_admin_notice_js() {

		// if there were no notices, or we've already rendered the js, there's nothing to do
		if ( empty( $this->admin_notices ) || self::$admin_notice_js_rendered ) {
			return;
		}

		$plugin_slug = $this->get_plugin()->get_id_dasherized();

		self::$admin_notice_js_rendered = true;

		ob_start();
		?>

		// Log dismissed notices
		$( '.js-wc-plugin-framework-admin-notice' ).on( 'click.wp-dismiss-notice', '.notice-dismiss', function( e ) {

			var $notice = $( this ).closest( '.js-wc-plugin-framework-admin-notice' );

			log_dismissed_notice(
				$( $notice ).data( 'plugin-id' ),
				$( $notice ).data( 'message-id' )
			);

		} );

		// Log and hide legacy notices
		$( 'a.js-wc-plugin-framework-notice-dismiss' ).click( function( e ) {

			e.preventDefault();

			var $notice = $( this ).closest( '.js-wc-plugin-framework-admin-notice' );

			log_dismissed_notice(
				$( $notice ).data( 'plugin-id' ),
				$( $notice ).data( 'message-id' )
			);

			$( $notice ).fadeOut();

		} );

		function log_dismissed_notice( pluginID, messageID ) {

			$.get(
				ajaxurl,
				{
					action:    'wc_plugin_framework_' + pluginID + '_dismiss_notice',
					messageid: messageID
				}
			);
		}

		// move any delayed notices up into position .show();
		$( '.js-wc-plugin-framework-admin-notice:hidden' ).insertAfter( '.js-wc-<?php echo esc_js( $plugin_slug ); ?>-admin-notice-placeholder' ).show();
		<?php
		$javascript = ob_get_clean();

		wc_enqueue_js( $javascript );
	}


	/**
	 * Marks the identified admin notice as dismissed for the given user
	 *
	 * @since 3.0.0
	 * @param string $message_id the message identifier
	 * @param int    $user_id optional user identifier, defaults to current user
	 */
	public function dismiss_notice( $message_id, $user_id = null ) {
		if ( is_null( $user_id ) ) {
			$user_id = get_current_user_id();
		}
		$dismissed_notices                = $this->get_dismissed_notices( $user_id );
		$dismissed_notices[ $message_id ] = true;
		update_user_meta( $user_id, '_wc_plugin_framework_' . $this->get_plugin()->get_id() . '_dismissed_messages', $dismissed_notices );
		/**
		 * Admin Notice Dismissed Action.
		 *
		 * Fired when a user dismisses an admin notice.
		 *
		 * @since 3.0.0
		 * @param string $message_id notice identifier
		 * @param string|int $user_id
		 */
		do_action( 'wc_' . $this->get_plugin()->get_id() . '_dismiss_notice', $message_id, $user_id );
	}


	/**
	 * Marks the identified admin notice as not dismissed for the identified user
	 *
	 * @since 3.0.0
	 * @param string $message_id the message identifier
	 * @param int    $user_id optional user identifier, defaults to current user
	 */
	public function undismiss_notice( $message_id, $user_id = null ) {
		if ( is_null( $user_id ) ) {
			$user_id = get_current_user_id();
		}
		$dismissed_notices                = $this->get_dismissed_notices( $user_id );
		$dismissed_notices[ $message_id ] = false;
		update_user_meta( $user_id, '_wc_plugin_framework_' . $this->get_plugin()->get_id() . '_dismissed_messages', $dismissed_notices );
	}


	/**
	 * Returns true if the identified admin notice has been dismissed for the
	 * given user
	 *
	 * @since 3.0.0
	 * @param string $message_id the message identifier
	 * @param int    $user_id optional user identifier, defaults to current user
	 * @return boolean true if the message has been dismissed by the admin user
	 */
	public function is_notice_dismissed( $message_id, $user_id = null ) {
		$dismissed_notices = $this->get_dismissed_notices( $user_id );
		return isset( $dismissed_notices[ $message_id ] ) && $dismissed_notices[ $message_id ];
	}


	/**
	 * Returns the full set of dismissed notices for the user identified by
	 * $user_id, for this plugin
	 *
	 * @since 3.0.0
	 * @param int $user_id optional user identifier, defaults to current user
	 * @return array of message id to dismissed status (true or false)
	 */
	public function get_dismissed_notices( $user_id = null ) {
		if ( is_null( $user_id ) ) {
			$user_id = get_current_user_id();
		}
		$dismissed_notices = get_user_meta( $user_id, '_wc_plugin_framework_' . $this->get_plugin()->get_id() . '_dismissed_messages', true );
		if ( empty( $dismissed_notices ) ) {
			return [];
		} else {
			return $dismissed_notices;
		}
	}


	/** AJAX methods ******************************************************/


	/**
	 * Dismiss the identified notice
	 *
	 * @since 3.0.0
	 */
	public function handle_dismiss_notice() {
		if ( isset( $_REQUEST['messageid'] ) ) {
			$this->dismiss_notice( wc_clean( wp_unslash( $_REQUEST['messageid'] ) ) );
		}
	}


	/** Getter methods ******************************************************/


	/**
	 * Get the plugin
	 *
	 * @return Plugin returns the plugin instance
	 */
	protected function get_plugin() {
		return $this->plugin;
	}
}
