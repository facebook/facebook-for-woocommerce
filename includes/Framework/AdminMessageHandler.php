<?php
/**
 * Facebook for WooCommerce.
 */

namespace WooCommerce\Facebook\Framework;

defined( 'ABSPATH' ) || exit;

/**
 * Admin Message Handler Class
 *
 * This class provides a reusable WordPress admin messaging facility for setting
 * and displaying messages and error messages across admin page requests without
 * resorting to passing the messages as query vars.
 *
 * ## Usage
 *
 * To use simple instantiate the class then set one or more messages:
 *
 * `
 * $admin_message_handler = new WP_Admin_Message_Handler( __FILE__ );
 * $admin_message_handler->add_message( 'Hello World!' );
 * `
 *
 * Then show the messages wherever you need, either with the built-in method
 * or by writing your own:
 *
 * `$admin_message_handler->show_messages();`
 *
 * @version 1.0.1
 */
class AdminMessageHandler {


	/** Transient message prefix */
	const MESSAGE_TRANSIENT_PREFIX = '_wp_admin_message_';

	/** The message id GET name */
	const MESSAGE_ID_GET_NAME = 'wpamhid';


	/** @var string unique message identifier, defaults to __FILE__ unless otherwise set */
	private $message_id;

	/** @var array array of messages */
	private $messages = [];

	/** @var array array of error messages */
	private $errors = [];

	/** @var array array of warning messages */
	private $warnings = [];

	/** @var array array of info messages */
	private $infos = [];


	/**
	 * Construct and initialize the admin message handler class
	 *
	 * @since 1.0.0
	 * @param string $message_id optional message id.  Best practice is to set
	 *        this to a unique identifier based on the client plugin, such as __FILE__
	 */
	public function __construct( $message_id = null ) {
		$this->message_id = $message_id;
		// load any available messages
		$this->load_messages();
		add_filter( 'wp_redirect', array( $this, 'redirect' ), 1, 2 );
	}


	/**
	 * Persist messages
	 *
	 * @since 1.0.0
	 * @return boolean true if any messages were set, false otherwise
	 */
	public function set_messages() {
		// any messages to persist?
		if ( $this->message_count() > 0 || $this->info_count() > 0 || $this->warning_count() > 0 || $this->error_count() > 0 ) {
			set_transient(
				self::MESSAGE_TRANSIENT_PREFIX . $this->get_message_id(),
				array(
					'errors'   => $this->errors,
					'warnings' => $this->warnings,
					'infos'    => $this->infos,
					'messages' => $this->messages,
				),
				60 * 60
			);
			return true;
		}
		return false;
	}


	/**
	 * Loads messages
	 *
	 * @since 1.0.0
	 */
	public function load_messages() {
		$message_id_get_name = isset( $_GET[ self::MESSAGE_ID_GET_NAME ] ) ? wc_clean( wp_unslash( $_GET[ self::MESSAGE_ID_GET_NAME ] ) ) : false;
		if ( $message_id_get_name && $this->get_message_id() === $message_id_get_name ) {
			$memo = get_transient( self::MESSAGE_TRANSIENT_PREFIX . $message_id_get_name );
			if ( isset( $memo['errors'] ) ) {
				$this->errors = $memo['errors'];
			}
			if ( isset( $memo['warnings'] ) ) {
				$this->warnings = $memo['warnings'];
			}
			if ( isset( $memo['infos'] ) ) {
				$this->infos = $memo['infos'];
			}
			if ( isset( $memo['messages'] ) ) {
				$this->messages = $memo['messages'];
			}
			$this->clear_messages( $message_id_get_name );
		}
	}


	/**
	 * Clear messages and errors
	 *
	 * @since 1.0.0
	 * @param string $id the messages identifier
	 */
	public function clear_messages( $id ) {
		delete_transient( self::MESSAGE_TRANSIENT_PREFIX . $id );
	}


	/**
	 * Add an error message.
	 *
	 * @since 1.0.0
	 * @param string $error error message
	 */
	public function add_error( $error ) {
		$this->errors[] = $error;
	}


	/**
	 * Adds a warning message.
	 *
	 * @since 5.1.0
	 *
	 * @param string $message warning message to add
	 */
	public function add_warning( $message ) {
		$this->warnings[] = $message;
	}


	/**
	 * Adds a info message.
	 *
	 * @since 5.1.0
	 *
	 * @param string $message info message to add
	 */
	public function add_info( $message ) {
		$this->infos[] = $message;
	}


	/**
	 * Add a message.
	 *
	 * @since 1.0.0
	 * @param string $message the message to add
	 */
	public function add_message( $message ) {
		$this->messages[] = $message;
	}


	/**
	 * Get error count.
	 *
	 * @since 1.0.0
	 * @return int error message count
	 */
	public function error_count() {
		return count( $this->errors );
	}


	/**
	 * Gets the warning message count.
	 *
	 * @since 5.1.0
	 *
	 * @return int warning message count
	 */
	public function warning_count() {
		return count( $this->warnings );
	}


	/**
	 * Gets the info message count.
	 *
	 * @since 5.1.0
	 *
	 * @return int info message count
	 */
	public function info_count() {
		return count( $this->infos );
	}


	/**
	 * Get message count.
	 *
	 * @since 1.0.0
	 * @return int message count
	 */
	public function message_count() {
		return count( $this->messages );
	}


	/**
	 * Get error messages
	 *
	 * @since 1.0.0
	 * @return array of error message strings
	 */
	public function get_errors() {
		return $this->errors;
	}


	/**
	 * Get an error message
	 *
	 * @since 1.0.0
	 * @param int $index the error index
	 * @return string the error message
	 */
	public function get_error( $index ) {
		return isset( $this->errors[ $index ] ) ? $this->errors[ $index ] : '';
	}


	/**
	 * Gets all warning messages.
	 *
	 * @since 5.1.0
	 *
	 * @return array
	 */
	public function get_warnings() {
		return $this->warnings;
	}


	/**
	 * Gets a specific warning message.
	 *
	 * @since 5.1.0
	 *
	 * @param int $index warning message index
	 * @return string
	 */
	public function get_warning( $index ) {
		return isset( $this->warnings[ $index ] ) ? $this->warnings[ $index ] : '';
	}


	/**
	 * Gets all info messages.
	 *
	 * @since 5.1.0
	 *
	 * @return array
	 */
	public function get_infos() {
		return $this->infos;
	}


	/**
	 * Gets a specific info message.
	 *
	 * @since 5.0.0
	 *
	 * @param int $index info message index
	 * @return string
	 */
	public function get_info( $index ) {
		return isset( $this->infos[ $index ] ) ? $this->infos[ $index ] : '';
	}


	/**
	 * Get messages
	 *
	 * @since 1.0.0
	 * @return array of message strings
	 */
	public function get_messages() {
		return $this->messages;
	}


	/**
	 * Get a message
	 *
	 * @since 1.0.0
	 * @param int $index the message index
	 * @return string the message
	 */
	public function get_message( $index ) {
		return isset( $this->messages[ $index ] ) ? $this->messages[ $index ] : '';
	}


	/**
	 * Render the errors and messages.
	 *
	 * @since 1.0.0
	 * @param array $params {
	 *     Optional parameters.
	 *
	 *     @type array $capabilities Any user capabilities to check if the user is allowed to view the messages,
	 *                               default: `manage_woocommerce`
	 * }
	 */
	public function show_messages( $params = [] ) {
		$params                  = wp_parse_args(
			$params,
			array(
				'capabilities' => array(
					'manage_woocommerce',
				),
			)
		);
		$check_user_capabilities = [];
		// check if user has at least one capability that allows to see messages
		foreach ( $params['capabilities'] as $capability ) {
			$check_user_capabilities[] = current_user_can( $capability );
		}
		// bail out if user has no minimum capabilities to see messages
		if ( ! in_array( true, $check_user_capabilities, true ) ) {
			return;
		}
		$output = '';
		if ( $this->error_count() > 0 ) {
			$output .= '<div id="wp-admin-message-handler-error" class="notice-error notice"><ul><li><strong>' . implode( '</strong></li><li><strong>', $this->get_errors() ) . '</strong></li></ul></div>';
		}
		if ( $this->warning_count() > 0 ) {
			$output .= '<div id="wp-admin-message-handler-warning"  class="notice-warning notice"><ul><li><strong>' . implode( '</strong></li><li><strong>', $this->get_warnings() ) . '</strong></li></ul></div>';
		}
		if ( $this->info_count() > 0 ) {
			$output .= '<div id="wp-admin-message-handler-warning"  class="notice-info notice"><ul><li><strong>' . implode( '</strong></li><li><strong>', $this->get_infos() ) . '</strong></li></ul></div>';
		}
		if ( $this->message_count() > 0 ) {
			$output .= '<div id="wp-admin-message-handler-message"  class="notice-success notice"><ul><li><strong>' . implode( '</strong></li><li><strong>', $this->get_messages() ) . '</strong></li></ul></div>';
		}
		echo wp_kses_post( $output );
	}


	/**
	 * Redirection hook which persists messages into session data.
	 *
	 * @since 1.0.0
	 * @param string $location  the URL to redirect to
	 * @param int    $_status    the http status
	 * @return string the URL to redirect to
	 */
	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
	public function redirect( $location, $_status ) {
		// add the admin message id param to the
		if ( $this->set_messages() ) {
			$location = add_query_arg( self::MESSAGE_ID_GET_NAME, $this->get_message_id(), $location );
		}

		// nosemgrep: audit.php.wp.security.xss.query-arg
		return $location;
	}


	/**
	 * Generate a unique id to identify the messages
	 *
	 * @since 1.0.0
	 * @return string unique identifier
	 */
	protected function get_message_id() {
		if ( ! isset( $this->message_id ) ) {
			$this->message_id = __FILE__;
		}
		return wp_create_nonce( $this->message_id );
	}
}
