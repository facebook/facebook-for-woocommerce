<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Unit\Handlers;

use WooCommerce\Facebook\Handlers\WebHook;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;

/**
 * Unit tests for WebHook handler class.
 *
 * @since 3.5.2
 */
class WebHookTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

	/**
	 * Test that the class exists and can be instantiated.
	 */
	public function test_class_exists() {
		$this->assertTrue( class_exists( WebHook::class ) );
		$webhook = new WebHook();
		$this->assertInstanceOf( WebHook::class, $webhook );
	}

	/**
	 * Test that the WEBHOOK_PAGE_ID constant is defined.
	 */
	public function test_webhook_page_id_constant() {
		$this->assertEquals( 'wc-facebook-webhook', WebHook::WEBHOOK_PAGE_ID );
	}

	/**
	 * Test constructor adds the correct action hook.
	 */
	public function test_constructor_adds_action_hook() {
		// Remove any existing hooks
		remove_all_actions( 'rest_api_init' );
		
		$webhook = new WebHook();
		
		// Check that the action was added
		$this->assertTrue( has_action( 'rest_api_init' ) !== false );
		$this->assertEquals( 10, has_action( 'rest_api_init', array( $webhook, 'init_webhook_endpoint' ) ) );
	}

	/**
	 * Test init_webhook_endpoint registers the REST route.
	 */
	public function test_init_webhook_endpoint_registers_route() {
		// Skip if register_rest_route doesn't exist
		if ( ! function_exists( 'register_rest_route' ) ) {
			$this->markTestSkipped( 'register_rest_route function not available' );
		}
		
		$webhook = new WebHook();
		
		// Mock the REST API server
		global $wp_rest_server;
		$wp_rest_server = new \WP_REST_Server();
		
		// Initialize REST API
		do_action( 'rest_api_init', $wp_rest_server );
		
		// Get registered routes
		$routes = $wp_rest_server->get_routes();
		
		// Check that our route is registered
		$this->assertArrayHasKey( '/wc-facebook/v1/webhook', $routes );
		
		// Check route configuration
		$route_config = $routes['/wc-facebook/v1/webhook'];
		$this->assertIsArray( $route_config );
		$this->assertNotEmpty( $route_config );
		
		// Check that both GET and POST methods are supported
		$methods = [];
		foreach ( $route_config as $endpoint ) {
			if ( isset( $endpoint['methods'] ) ) {
				// Methods might be a bitmask, array, or string
				if ( is_array( $endpoint['methods'] ) ) {
					$methods = array_merge( $methods, $endpoint['methods'] );
				} elseif ( is_int( $endpoint['methods'] ) ) {
					// Handle bitmask
					if ( $endpoint['methods'] & \WP_REST_Server::READABLE ) {
						$methods[] = 'GET';
					}
					if ( $endpoint['methods'] & \WP_REST_Server::CREATABLE ) {
						$methods[] = 'POST';
					}
				} elseif ( is_string( $endpoint['methods'] ) ) {
					// Handle comma-separated string
					$method_list = explode( ',', $endpoint['methods'] );
					foreach ( $method_list as $method ) {
						$methods[] = trim( $method );
					}
				}
			}
		}
		
		// The route should support both GET and POST
		// Check if we have both methods in some form
		$has_get = in_array( 'GET', $methods ) || in_array( 'GET,POST', $methods ) || in_array( 'POST,GET', $methods );
		$has_post = in_array( 'POST', $methods ) || in_array( 'GET,POST', $methods ) || in_array( 'POST,GET', $methods );
		
		$this->assertTrue( $has_get, 'Route should support GET method' );
		$this->assertTrue( $has_post, 'Route should support POST method' );
	}

	/**
	 * Test permission_callback with admin user.
	 */
	public function test_permission_callback_with_admin() {
		$webhook = new WebHook();
		
		// Create and set admin user
		$admin_user = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_user );
		
		$this->assertTrue( $webhook->permission_callback() );
	}

	/**
	 * Test permission_callback with shop manager.
	 */
	public function test_permission_callback_with_shop_manager() {
		$webhook = new WebHook();
		
		// Create and set shop manager user
		$shop_manager = $this->factory->user->create( array( 'role' => 'shop_manager' ) );
		wp_set_current_user( $shop_manager );
		
		$this->assertTrue( $webhook->permission_callback() );
	}

	/**
	 * Test permission_callback with regular user.
	 */
	public function test_permission_callback_with_regular_user() {
		$webhook = new WebHook();
		
		// Create and set subscriber user
		$subscriber = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber );
		
		$this->assertFalse( $webhook->permission_callback() );
	}

	/**
	 * Test permission_callback with no user.
	 */
	public function test_permission_callback_with_no_user() {
		$webhook = new WebHook();
		
		// Set no current user
		wp_set_current_user( 0 );
		
		$this->assertFalse( $webhook->permission_callback() );
	}

	/**
	 * Test webhook_callback with empty body.
	 */
	public function test_webhook_callback_with_empty_body() {
		$webhook = new WebHook();
		
		// Create mock request with empty body
		$request = $this->createMock( \WP_REST_Request::class );
		$request->expects( $this->once() )
			->method( 'get_body' )
			->willReturn( '' );
		
		$response = $webhook->webhook_callback( $request );
		
		$this->assertInstanceOf( \WP_REST_Response::class, $response );
		$this->assertEquals( 204, $response->get_status() );
		$this->assertNull( $response->get_data() );
	}

	/**
	 * Test webhook_callback with null JSON body.
	 */
	public function test_webhook_callback_with_null_json() {
		$webhook = new WebHook();
		
		// Create mock request with null JSON
		$request = $this->createMock( \WP_REST_Request::class );
		$request->expects( $this->once() )
			->method( 'get_body' )
			->willReturn( 'null' );
		
		$response = $webhook->webhook_callback( $request );
		
		$this->assertInstanceOf( \WP_REST_Response::class, $response );
		$this->assertEquals( 204, $response->get_status() );
		$this->assertNull( $response->get_data() );
	}

	/**
	 * Test webhook_callback with valid JSON body.
	 */
	public function test_webhook_callback_with_valid_json() {
		$webhook = new WebHook();
		
		// Track if action was fired
		$action_fired = false;
		$action_data = null;
		
		// Add action listener
		add_action( 'fbe_webhook', function( $data ) use ( &$action_fired, &$action_data ) {
			$action_fired = true;
			$action_data = $data;
		} );
		
		// Create mock request with valid JSON
		$test_data = array( 'test' => 'data', 'foo' => 'bar' );
		$request = $this->createMock( \WP_REST_Request::class );
		$request->expects( $this->once() )
			->method( 'get_body' )
			->willReturn( json_encode( $test_data ) );
		
		$response = $webhook->webhook_callback( $request );
		
		// Check response
		$this->assertInstanceOf( \WP_REST_Response::class, $response );
		$this->assertEquals( 200, $response->get_status() );
		$this->assertNull( $response->get_data() );
		
		// Check action was fired
		$this->assertTrue( $action_fired );
		$this->assertEquals( (object) $test_data, $action_data );
		
		// Clean up
		remove_all_actions( 'fbe_webhook' );
	}

	/**
	 * Test webhook_callback with complex JSON structure.
	 */
	public function test_webhook_callback_with_complex_json() {
		$webhook = new WebHook();
		
		// Track if action was fired
		$action_fired = false;
		$action_data = null;
		
		// Add action listener
		add_action( 'fbe_webhook', function( $data ) use ( &$action_fired, &$action_data ) {
			$action_fired = true;
			$action_data = $data;
		} );
		
		// Create complex test data
		$test_data = array(
			'event' => 'product_update',
			'data' => array(
				'id' => 123,
				'name' => 'Test Product',
				'price' => 99.99,
				'attributes' => array(
					'color' => 'red',
					'size' => 'large'
				)
			),
			'timestamp' => time()
		);
		
		$request = $this->createMock( \WP_REST_Request::class );
		$request->expects( $this->once() )
			->method( 'get_body' )
			->willReturn( json_encode( $test_data ) );
		
		$response = $webhook->webhook_callback( $request );
		
		// Check response
		$this->assertInstanceOf( \WP_REST_Response::class, $response );
		$this->assertEquals( 200, $response->get_status() );
		
		// Check action was fired with correct data
		$this->assertTrue( $action_fired );
		$this->assertEquals( json_decode( json_encode( $test_data ) ), $action_data );
		
		// Clean up
		remove_all_actions( 'fbe_webhook' );
	}

	/**
	 * Test webhook_callback with invalid JSON.
	 */
	public function test_webhook_callback_with_invalid_json() {
		$webhook = new WebHook();
		
		// Track if action was fired
		$action_fired = false;
		
		// Add action listener
		add_action( 'fbe_webhook', function( $data ) use ( &$action_fired ) {
			$action_fired = true;
		} );
		
		// Create mock request with invalid JSON
		$request = $this->createMock( \WP_REST_Request::class );
		$request->expects( $this->once() )
			->method( 'get_body' )
			->willReturn( '{ invalid json' );
		
		$response = $webhook->webhook_callback( $request );
		
		// Check response (should be 204 because json_decode returns null)
		$this->assertInstanceOf( \WP_REST_Response::class, $response );
		$this->assertEquals( 204, $response->get_status() );
		
		// Check action was not fired
		$this->assertFalse( $action_fired );
		
		// Clean up
		remove_all_actions( 'fbe_webhook' );
	}

	/**
	 * Test webhook_callback with empty object.
	 */
	public function test_webhook_callback_with_empty_object() {
		$webhook = new WebHook();
		
		// Track if action was fired
		$action_fired = false;
		
		// Add action listener
		add_action( 'fbe_webhook', function( $data ) use ( &$action_fired ) {
			$action_fired = true;
		} );
		
		// Create mock request with empty object
		$request = $this->createMock( \WP_REST_Request::class );
		$request->expects( $this->once() )
			->method( 'get_body' )
			->willReturn( '{}' );
		
		$response = $webhook->webhook_callback( $request );
		
		// Check response
		$this->assertInstanceOf( \WP_REST_Response::class, $response );
		$this->assertEquals( 200, $response->get_status() );
		
		// Check action was fired (empty object is still valid)
		$this->assertTrue( $action_fired );
		
		// Clean up
		remove_all_actions( 'fbe_webhook' );
	}

	/**
	 * Test webhook_callback with array JSON.
	 */
	public function test_webhook_callback_with_array_json() {
		$webhook = new WebHook();
		
		// Track if action was fired
		$action_data = null;
		
		// Add action listener
		add_action( 'fbe_webhook', function( $data ) use ( &$action_data ) {
			$action_data = $data;
		} );
		
		// Create mock request with array JSON
		$test_array = array( 'item1', 'item2', 'item3' );
		$request = $this->createMock( \WP_REST_Request::class );
		$request->expects( $this->once() )
			->method( 'get_body' )
			->willReturn( json_encode( $test_array ) );
		
		$response = $webhook->webhook_callback( $request );
		
		// Check response
		$this->assertInstanceOf( \WP_REST_Response::class, $response );
		$this->assertEquals( 200, $response->get_status() );
		
		// Check action was fired with array data
		$this->assertEquals( $test_array, $action_data );
		
		// Clean up
		remove_all_actions( 'fbe_webhook' );
	}
} 