<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Unit;

use WooCommerce\Facebook\AJAX;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;

/**
 * Unit tests for AJAX class - focused on sync_modified_products() core logic.
 *
 * @since 3.5.5
 */
class SyncModifyAJAXTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

	/**
	 * The AJAX instance under test.
	 *
	 * @var AJAX
	 */
	private $ajax;

	/**
	 * Set up before each test.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->ajax = new AJAX();
	}

	/**
	 * Test that nonce verification is enforced.
	 */
	public function test_sync_modified_products_requires_valid_nonce() {
		$this->mock_facebook_for_woocommerce_with_successful_sync();
		$this->mock_check_admin_referer_failure();
		$this->mock_wp_send_json_error();

		$this->ajax->sync_modified_products();

		// Should fail due to nonce verification
		$this->assertTrue( $GLOBALS['wp_send_json_error_called'] );
	}

	/**
	 * Test successful sync execution.
	 */
	public function test_sync_modified_products_success() {
		$this->mock_facebook_for_woocommerce_with_successful_sync();
		$this->mock_check_admin_referer_success();
		$this->mock_wp_send_json_success();

		$this->ajax->sync_modified_products();

		$this->assertTrue( $GLOBALS['wp_send_json_success_called'] );
		$this->assertTrue( $GLOBALS['sync_handler_called'] );
	}

	/**
	 * Test exception handling during sync.
	 */
	public function test_sync_modified_products_handles_exceptions() {
		$this->mock_facebook_for_woocommerce_with_sync_exception();
		$this->mock_check_admin_referer_success();
		$this->mock_wp_send_json_error();

		$this->ajax->sync_modified_products();

		$this->assertTrue( $GLOBALS['wp_send_json_error_called'] );
		$this->assertEquals( 'Test sync exception', $GLOBALS['wp_send_json_error_data'] );
	}

	/**
	 * Test that logging occurs during successful sync.
	 */
	public function test_sync_modified_products_logs_events() {
		$this->mock_facebook_for_woocommerce_with_successful_sync();
		$this->mock_check_admin_referer_success();
		$this->mock_wp_send_json_success();
		$this->mock_logger();

		$this->ajax->sync_modified_products();

		$logs = $GLOBALS['logger_logs'] ?? array();
		$this->assertCount( 2, $logs );
		$this->assertEquals( 'Starting AJAX sync of modified products', $logs[0]['message'] );
		$this->assertEquals( 'Completed AJAX sync of modified products', $logs[1]['message'] );
	}

	/**
	 * Test that error logging occurs during exceptions.
	 */
	public function test_sync_modified_products_logs_errors() {
		$this->mock_facebook_for_woocommerce_with_sync_exception();
		$this->mock_check_admin_referer_success();
		$this->mock_wp_send_json_error();
		$this->mock_logger();

		$this->ajax->sync_modified_products();

		$logs = $GLOBALS['logger_logs'] ?? array();
		$this->assertCount( 2, $logs );
		$this->assertEquals( 'Starting AJAX sync of modified products', $logs[0]['message'] );
		$this->assertEquals( 'Error syncing modified products via AJAX', $logs[1]['message'] );
		$this->assertEquals( 'Test sync exception', $logs[1]['context']['error_message'] );
	}

	/**
	 * Test that sync handler is not called when nonce fails.
	 */
	public function test_sync_modified_products_skips_handler_when_nonce_fails() {
		$this->mock_facebook_for_woocommerce_with_successful_sync();
		$this->mock_check_admin_referer_failure();
		$this->mock_wp_send_json_error();

		$this->ajax->sync_modified_products();

		$this->assertFalse( $GLOBALS['sync_handler_called'] ?? false );
	}

	/**
	 * Test integration availability check.
	 */
	public function test_sync_modified_products_handles_missing_integration() {
		$this->mock_facebook_for_woocommerce_with_missing_integration();
		$this->mock_wp_send_json_error();

		$this->ajax->sync_modified_products();

		$this->assertTrue( $GLOBALS['wp_send_json_error_called'] );
	}

	/**
	 * Helper method to mock facebook_for_woocommerce with successful sync.
	 */
	private function mock_facebook_for_woocommerce_with_successful_sync() {
		$this->mock_facebook_for_woocommerce_function();

		// Create anonymous class with the required method
		$mock_sync_handler = new class() {
			public function create_or_update_modified_products() {
				$GLOBALS['sync_handler_called'] = true;
			}
		};

		$mock_main = new class( $mock_sync_handler ) {
			private $sync_handler;

			public function __construct( $sync_handler ) {
				$this->sync_handler = $sync_handler;
			}

			public function get_products_sync_handler() {
				return $this->sync_handler;
			}
		};

		$GLOBALS['mock_facebook_for_woocommerce'] = $mock_main;
	}

	/**
	 * Helper method to mock facebook_for_woocommerce with sync exception.
	 */
	private function mock_facebook_for_woocommerce_with_sync_exception() {
		$this->mock_facebook_for_woocommerce_function();

		// Create anonymous class with the required method that throws exception
		$mock_sync_handler = new class() {
			public function create_or_update_modified_products() {
				throw new \Exception( 'Test sync exception' );
			}
		};

		$mock_main = new class( $mock_sync_handler ) {
			private $sync_handler;

			public function __construct( $sync_handler ) {
				$this->sync_handler = $sync_handler;
			}

			public function get_products_sync_handler() {
				return $this->sync_handler;
			}
		};

		$GLOBALS['mock_facebook_for_woocommerce'] = $mock_main;
	}

	/**
	 * Helper method to mock facebook_for_woocommerce with missing integration.
	 */
	private function mock_facebook_for_woocommerce_with_missing_integration() {
		$this->mock_facebook_for_woocommerce_function();

		$mock_main = new class() {
			public function get_products_sync_handler() {
				return null;
			}
		};

		$GLOBALS['mock_facebook_for_woocommerce'] = $mock_main;
	}

	/**
	 * Helper method to mock the facebook_for_woocommerce function.
	 */
	private function mock_facebook_for_woocommerce_function() {
		if ( ! function_exists( 'facebook_for_woocommerce' ) ) {
			function facebook_for_woocommerce() {
				return $GLOBALS['mock_facebook_for_woocommerce'] ?? null;
			}
		}
	}

	/**
	 * Helper method to mock successful nonce verification.
	 */
	private function mock_check_admin_referer_success() {
		if ( ! function_exists( 'check_admin_referer' ) ) {
			function check_admin_referer( $action = -1, $query_arg = '_wpnonce' ) {
				return true;
			}
		}
	}

	/**
	 * Helper method to mock failed nonce verification.
	 */
	private function mock_check_admin_referer_failure() {
		if ( ! function_exists( 'check_admin_referer' ) ) {
			function check_admin_referer( $action = -1, $query_arg = '_wpnonce' ) {
				throw new \Exception( 'Nonce verification failed' );
			}
		}
	}

	/**
	 * Helper method to mock wp_send_json_success.
	 */
	private function mock_wp_send_json_success() {
		if ( ! function_exists( 'wp_send_json_success' ) ) {
			function wp_send_json_success( $data = null, $status_code = null, $options = 0 ) {
				$GLOBALS['wp_send_json_success_called'] = true;
				$GLOBALS['wp_send_json_success_data'] = $data;
			}
		}
	}

	/**
	 * Helper method to mock wp_send_json_error.
	 */
	private function mock_wp_send_json_error() {
		if ( ! function_exists( 'wp_send_json_error' ) ) {
			function wp_send_json_error( $data = null, $status_code = null, $options = 0 ) {
				$GLOBALS['wp_send_json_error_called'] = true;
				$GLOBALS['wp_send_json_error_data'] = $data;
			}
		}
	}

	/**
	 * Helper method to mock the Logger.
	 */
	private function mock_logger() {
		if ( ! class_exists( '\WooCommerce\Facebook\Framework\Logger' ) ) {
			eval( '
				namespace WooCommerce\Facebook\Framework;
				class Logger {
					public static function log( $message, $context = array(), $options = array(), $exception = null ) {
						$GLOBALS["logger_logs"][] = array(
							"message" => $message,
							"context" => $context,
							"options" => $options,
							"exception" => $exception
						);
					}
				}
			' );
		}

		$GLOBALS['logger_logs'] = array();
	}

	/**
	 * Helper method to mock WordPress translation function.
	 */
	private function mock_wordpress_functions() {
		if ( ! function_exists( '__' ) ) {
			function __( $text, $domain = 'default' ) {
				return $text;
			}
		}
	}

	/**
	 * Clean up globals after each test.
	 */
	public function tearDown(): void {
		unset( $GLOBALS['mock_facebook_for_woocommerce'] );
		unset( $GLOBALS['wp_send_json_success_called'] );
		unset( $GLOBALS['wp_send_json_success_data'] );
		unset( $GLOBALS['wp_send_json_error_called'] );
		unset( $GLOBALS['wp_send_json_error_data'] );
		unset( $GLOBALS['sync_handler_called'] );
		unset( $GLOBALS['logger_logs'] );

		parent::tearDown();
	}
}
