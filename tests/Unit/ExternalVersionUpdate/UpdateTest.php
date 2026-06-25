<?php
/**
 * Unit tests related to external version update.
 */

namespace WooCommerce\Facebook\Tests\ExternalVersionUpdate;

use WooCommerce\Facebook\ExternalVersionUpdate\Update;
use WooCommerce\Facebook\Handlers\Connection;
use WooCommerce\Facebook\API;
use WooCommerce\Facebook\Framework\Plugin\Exception as PluginException;
use WooCommerce\Facebook\Framework\Api\Exception as ApiException;
use WooCommerce\Facebook\API\FBE\Configuration\Update\Response;
use WP_UnitTestCase;
use ReflectionObject;
use WC_Facebookcommerce_Utils;
use WP_Error;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;
use WooCommerce\Facebook\Utilities\Heartbeat;
use WooCommerce\Facebook\Handlers\PluginRender;

/**
 * The External version update unit test class.
 */
class UpdateTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

	/**
	 * Instance of the Update class that we are testing.
	 *
	 * @var \WooCommerce\Facebook\ExternalVersionUpdate\Update The object to be tested.
	 */
	private $update;
	
	/**
	 * Original connection handler from the plugin.
	 *
	 * @var Connection
	 */
	private $original_connection_handler;
	
	/**
	 * Original API instance from the plugin.
	 *
	 * @var API
	 */
	private $original_api;
	
	/**
	 * ReflectionProperty for connection_handler.
	 *
	 * @var \ReflectionProperty
	 */
	private $prop_connection_handler;
	
	/**
	 * ReflectionProperty for api.
	 *
	 * @var \ReflectionProperty
	 */
	private $prop_api;

	/**
	 * Setup the test object for each test.
	 */
	public function setUp():void {
		$plugin = facebook_for_woocommerce();
		$plugin_ref_obj = new ReflectionObject( $plugin );
		
		// Set up reflection properties
		$this->prop_connection_handler = $plugin_ref_obj->getProperty( 'connection_handler' );
		$this->prop_connection_handler->setAccessible( true );
		$this->original_connection_handler = $this->prop_connection_handler->getValue( $plugin );
		
		$this->prop_api = $plugin_ref_obj->getProperty( 'api' );
		$this->prop_api->setAccessible( true );
		$this->original_api = $this->prop_api->getValue( $plugin );
		
		$this->update = new Update();
	}
	
	/**
	 * Tear down after each test.
	 */
	public function tearDown():void {
		// Restore original values
		$plugin = facebook_for_woocommerce();
		$this->prop_connection_handler->setValue( $plugin, $this->original_connection_handler );
		$this->prop_api->setValue( $plugin, $this->original_api );
		
		parent::tearDown();
	}

	/**
	 * Test send new version to facebook.
	 */
	public function test_send_new_version_to_facebook_server() {
		$plugin = facebook_for_woocommerce();
		$plugin->init_admin();

		/**
		 * Set the $plugin->connection_handler and $plugin->api access to true. This will allow us
		 * to assign the mock objects to these properties.
		 */
		$plugin_ref_obj          = new ReflectionObject( $plugin );
		$prop_connection_handler = $plugin_ref_obj->getProperty( 'connection_handler' );
		$prop_connection_handler->setAccessible( true );

		// Set up plugin render properties
		$prop_plugin_render_handler = $plugin_ref_obj->getProperty( 'plugin_render_handler' );
		$prop_plugin_render_handler->setAccessible( true );

		$prop_api = $plugin_ref_obj->getProperty( 'api' );
		$prop_api->setAccessible( true );

		// Create the mock connection handler object to return a dummy business id and is_connected true.
		$mock_connection_handler = $this->getMockBuilder( Connection::class )
									->disableOriginalConstructor()
									->setMethods( array( 'get_external_business_id', 'is_connected' ) )
									->getMock();
		$mock_connection_handler->expects( $this->any() )->method( 'get_external_business_id' )->willReturn( 'dummy-business-id' );
		$mock_connection_handler->expects( $this->any() )->method( 'is_connected' )->willReturn( true );
		$prop_connection_handler->setValue( $plugin, $mock_connection_handler );

		// Mock render handler
		$mock_plugin_render_handler = $this->getMockBuilder( Connection::class )
									->disableOriginalConstructor()
									->setMethods( array( 'is_master_sync_on' ) )
									->getMock();
		$mock_plugin_render_handler->expects( $this->any() )->method( 'is_master_sync_on' )->willReturn( true );
		$prop_plugin_render_handler->setValue($plugin,$mock_plugin_render_handler);

		// Create the mock api object that will return an array, meaning a successful response.
		$mock_api = $this->getMockBuilder( API::class )->disableOriginalConstructor()->setMethods( array( 'do_remote_request' ) )->getMock();
		$mock_api->expects( $this->any() )->method( 'do_remote_request' )->willReturn(
			array(
				'response' => array(
					'code'    => '200',
					'message' => 'dummy-response',
				),
			)
		);
		$prop_api->setValue( $plugin, $mock_api );

		$updated = $this->update->send_new_version_to_facebook_server();

		// Assert request data.
		$expected_request = array(
			'fbe_external_business_id' => 'dummy-business-id',
			'business_config'          => array(
				'external_client' => array(
					'version_id' => WC_Facebookcommerce_Utils::PLUGIN_VERSION,
					'is_multisite' => false,
					'is_woo_all_products_opted_out' => false
				),
			),
		);
		$actual_request   = $plugin->get_api()->get_request();
		$this->assertEquals( $expected_request, $actual_request->get_data(), 'Failed asserting request data.' );

		// Assert correct response.
		$actual_response = $plugin->get_api()->get_response();
		$this->assertInstanceOf( Response::class, $actual_response );

		// Assert the request was made and the latest version sent to server option is updated.
		$this->assertTrue( $updated, 'Failed asserting that the update plugin request was made.' );
		$this->assertEquals( WC_Facebookcommerce_Utils::PLUGIN_VERSION, get_option( 'facebook_for_woocommerce_latest_version_sent_to_server' ), 'Failed asserting that latest version sent to server is the same as the plugin version.' );

		// Now the mock API object will return a WP_Error.
		$mock_api2 = $this->getMockBuilder( API::class )->disableOriginalConstructor()->setMethods( array( 'do_remote_request' ) )->getMock();
		$mock_api2->expects( $this->any() )->method( 'do_remote_request' )->willReturn( new WP_Error( 'dummy-code', 'dummy-message', array( 'data' => 'dummy data' ) ) );
		$prop_api->setValue( $plugin, $mock_api2 );

		// Now the mock API object will throw a Plugin Exception.
		$mock_api3 = $this->getMockBuilder( API::class )->disableOriginalConstructor()->setMethods( array( 'perform_request' ) )->getMock();
		$mock_api3->expects( $this->any() )->method( 'perform_request' )->willThrowException( new PluginException( 'Dummy Plugin Exception' ) );
		$prop_api->setValue( $plugin, $mock_api3 );

		// Now the mock API object will throw an ApiException.
		$mock_api4 = $this->getMockBuilder( API::class )->disableOriginalConstructor()->setMethods( array( 'perform_request' ) )->getMock();
		$mock_api4->expects( $this->any() )->method( 'perform_request' )->willThrowException( new ApiException( 'Dummy API Exception' ) );
		$prop_api->setValue( $plugin, $mock_api4 );
	}

	/**
	 * Test that the constructor registers the necessary hooks.
	 *
	 * @covers \WooCommerce\Facebook\ExternalVersionUpdate\Update::__construct
	 */
	public function test_constructor_registers_hooks() {
		// Create a new instance to trigger constructor
		$update = new Update();

		// Verify daily heartbeat action is registered for send_new_version_to_facebook_server
		$this->assertNotFalse(
			has_action( Heartbeat::DAILY, array( $update, 'send_new_version_to_facebook_server' ) ),
			'Failed asserting that daily heartbeat action is registered for send_new_version_to_facebook_server'
		);

		// Verify hourly heartbeat action is registered for send_plugin_config_to_facebook_server
		$this->assertNotFalse(
			has_action( Heartbeat::HOURLY, array( $update, 'send_plugin_config_to_facebook_server' ) ),
			'Failed asserting that hourly heartbeat action is registered for send_plugin_config_to_facebook_server'
		);
	}

	/**
	 * Test send_new_version_to_facebook_server when plugin is not connected.
	 *
	 * @covers \WooCommerce\Facebook\ExternalVersionUpdate\Update::send_new_version_to_facebook_server
	 */
	public function test_send_new_version_when_not_connected() {
		$plugin = facebook_for_woocommerce();

		// Mock connection handler to return is_connected = false
		$mock_connection_handler = $this->getMockBuilder( Connection::class )
			->disableOriginalConstructor()
			->setMethods( array( 'is_connected' ) )
			->getMock();
		$mock_connection_handler->expects( $this->once() )
			->method( 'is_connected' )
			->willReturn( false );

		$this->prop_connection_handler->setValue( $plugin, $mock_connection_handler );

		// Call the method
		$result = $this->update->send_new_version_to_facebook_server();

		// Assert that the method returns early (null or no value)
		$this->assertNull( $result, 'Failed asserting that method returns null when not connected' );
	}

	/**
	 * Test send_new_version_to_facebook_server when transient flag is already set.
	 *
	 * @covers \WooCommerce\Facebook\ExternalVersionUpdate\Update::send_new_version_to_facebook_server
	 */
	public function test_send_new_version_when_transient_flag_set() {
		$plugin = facebook_for_woocommerce();

		// Set the transient flag
		set_transient( '_wc_facebook_for_woocommerce_external_version_update_flag', 'yes', 12 * HOUR_IN_SECONDS );

		// Mock connection handler to return is_connected = true
		$mock_connection_handler = $this->getMockBuilder( Connection::class )
			->disableOriginalConstructor()
			->setMethods( array( 'is_connected' ) )
			->getMock();
		$mock_connection_handler->expects( $this->once() )
			->method( 'is_connected' )
			->willReturn( true );

		$this->prop_connection_handler->setValue( $plugin, $mock_connection_handler );

		// Mock API to ensure it's not called
		$mock_api = $this->getMockBuilder( API::class )
			->disableOriginalConstructor()
			->setMethods( array( 'update_plugin_version_configuration' ) )
			->getMock();
		$mock_api->expects( $this->never() )
			->method( 'update_plugin_version_configuration' );

		$this->prop_api->setValue( $plugin, $mock_api );

		// Call the method
		$result = $this->update->send_new_version_to_facebook_server();

		// Assert that the method returns early
		$this->assertNull( $result, 'Failed asserting that method returns null when transient flag is set' );

		// Clean up transient
		delete_transient( '_wc_facebook_for_woocommerce_external_version_update_flag' );
	}

	/**
	 * Test send_new_version_to_facebook_server when API returns an error.
	 *
	 * @covers \WooCommerce\Facebook\ExternalVersionUpdate\Update::send_new_version_to_facebook_server
	 */
	public function test_send_new_version_when_api_has_error() {
		$plugin = facebook_for_woocommerce();

		// Mock connection handler
		$mock_connection_handler = $this->getMockBuilder( Connection::class )
			->disableOriginalConstructor()
			->setMethods( array( 'is_connected', 'get_external_business_id' ) )
			->getMock();
		$mock_connection_handler->expects( $this->once() )
			->method( 'is_connected' )
			->willReturn( true );
		$mock_connection_handler->expects( $this->once() )
			->method( 'get_external_business_id' )
			->willReturn( 'test-business-id' );

		$this->prop_connection_handler->setValue( $plugin, $mock_connection_handler );

		// Mock API response with error
		$mock_response = $this->getMockBuilder( Response::class )
			->disableOriginalConstructor()
			->setMethods( array( 'has_api_error' ) )
			->getMock();
		$mock_response->expects( $this->once() )
			->method( 'has_api_error' )
			->willReturn( true );

		$mock_api = $this->getMockBuilder( API::class )
			->disableOriginalConstructor()
			->setMethods( array( 'update_plugin_version_configuration' ) )
			->getMock();
		$mock_api->expects( $this->once() )
			->method( 'update_plugin_version_configuration' )
			->willReturn( $mock_response );

		$this->prop_api->setValue( $plugin, $mock_api );

		// Store the original option value
		$original_option = get_option( Update::LATEST_VERSION_SENT );

		// Call the method
		$result = $this->update->send_new_version_to_facebook_server();

		// Assert that the method returns false
		$this->assertFalse( $result, 'Failed asserting that method returns false when API has error' );

		// Assert that the option was NOT updated
		$this->assertEquals(
			$original_option,
			get_option( Update::LATEST_VERSION_SENT ),
			'Failed asserting that option was not updated when API has error'
		);
	}

	/**
	 * Test send_plugin_config_to_facebook_server when transient flag is already set.
	 *
	 * @covers \WooCommerce\Facebook\ExternalVersionUpdate\Update::send_plugin_config_to_facebook_server
	 */
	public function test_send_plugin_config_when_transient_flag_set() {
		$plugin = facebook_for_woocommerce();

		// Set the transient flag
		set_transient( '_wc_facebook_for_woocommerce_send_plugin_config_flag', 'yes', 3 * HOUR_IN_SECONDS );

		// Mock API to ensure it's not called
		$mock_api = $this->getMockBuilder( API::class )
			->disableOriginalConstructor()
			->setMethods( array( 'log_to_meta' ) )
			->getMock();
		$mock_api->expects( $this->never() )
			->method( 'log_to_meta' );

		$this->prop_api->setValue( $plugin, $mock_api );

		// Call the method
		$this->update->send_plugin_config_to_facebook_server();

		// Clean up transient
		delete_transient( '_wc_facebook_for_woocommerce_send_plugin_config_flag' );
	}

	/**
	 * Test send_plugin_config_to_facebook_server success scenario.
	 *
	 * @covers \WooCommerce\Facebook\ExternalVersionUpdate\Update::send_plugin_config_to_facebook_server
	 */
	public function test_send_plugin_config_to_facebook_server_success() {
		$plugin = facebook_for_woocommerce();

		// Ensure transient is not set
		delete_transient( '_wc_facebook_for_woocommerce_send_plugin_config_flag' );

		// Mock integration
		$mock_integration = $this->getMockBuilder( \WC_Facebookcommerce_Integration::class )
			->disableOriginalConstructor()
			->setMethods( array( 'is_product_sync_enabled', 'get_product_count' ) )
			->getMock();
		$mock_integration->expects( $this->any() )
			->method( 'is_product_sync_enabled' )
			->willReturn( true );
		$mock_integration->expects( $this->any() )
			->method( 'get_product_count' )
			->willReturn( 100 );

		// Set up plugin integration
		$plugin_ref_obj = new ReflectionObject( $plugin );
		$prop_integration = $plugin_ref_obj->getProperty( 'integration' );
		$prop_integration->setAccessible( true );
		$prop_integration->setValue( $plugin, $mock_integration );

		// Mock successful API response
		$mock_response = new \stdClass();
		$mock_response->success = true;

		$mock_api = $this->getMockBuilder( API::class )
			->disableOriginalConstructor()
			->setMethods( array( 'log_to_meta' ) )
			->getMock();
		$mock_api->expects( $this->once() )
			->method( 'log_to_meta' )
			->willReturn( $mock_response );

		$this->prop_api->setValue( $plugin, $mock_api );

		// Call the method
		$this->update->send_plugin_config_to_facebook_server();

		// Assert that the transient flag was set
		$this->assertEquals(
			'yes',
			get_transient( '_wc_facebook_for_woocommerce_send_plugin_config_flag' ),
			'Failed asserting that transient flag was set'
		);

		// Clean up
		delete_transient( '_wc_facebook_for_woocommerce_send_plugin_config_flag' );
	}

	/**
	 * Test send_plugin_config_to_facebook_server when API returns error.
	 *
	 * @covers \WooCommerce\Facebook\ExternalVersionUpdate\Update::send_plugin_config_to_facebook_server
	 */
	public function test_send_plugin_config_when_api_returns_error() {
		$plugin = facebook_for_woocommerce();

		// Ensure transient is not set
		delete_transient( '_wc_facebook_for_woocommerce_send_plugin_config_flag' );

		// Mock integration
		$mock_integration = $this->getMockBuilder( \WC_Facebookcommerce_Integration::class )
			->disableOriginalConstructor()
			->setMethods( array( 'is_product_sync_enabled', 'get_product_count' ) )
			->getMock();
		$mock_integration->expects( $this->any() )
			->method( 'is_product_sync_enabled' )
			->willReturn( true );
		$mock_integration->expects( $this->any() )
			->method( 'get_product_count' )
			->willReturn( 100 );

		// Set up plugin integration
		$plugin_ref_obj = new ReflectionObject( $plugin );
		$prop_integration = $plugin_ref_obj->getProperty( 'integration' );
		$prop_integration->setAccessible( true );
		$prop_integration->setValue( $plugin, $mock_integration );

		// Mock unsuccessful API response
		$mock_response = new \stdClass();
		$mock_response->success = false;

		$mock_api = $this->getMockBuilder( API::class )
			->disableOriginalConstructor()
			->setMethods( array( 'log_to_meta' ) )
			->getMock();
		$mock_api->expects( $this->once() )
			->method( 'log_to_meta' )
			->willReturn( $mock_response );

		$this->prop_api->setValue( $plugin, $mock_api );

		// Call the method - it should handle the error gracefully
		$this->update->send_plugin_config_to_facebook_server();

		// The method should still set the transient flag even on error
		$this->assertEquals(
			'yes',
			get_transient( '_wc_facebook_for_woocommerce_send_plugin_config_flag' ),
			'Failed asserting that transient flag was set even on API error'
		);

		// Clean up
		delete_transient( '_wc_facebook_for_woocommerce_send_plugin_config_flag' );
	}

	/**
	 * Test send_plugin_config_to_facebook_server when exception is thrown.
	 *
	 * @covers \WooCommerce\Facebook\ExternalVersionUpdate\Update::send_plugin_config_to_facebook_server
	 */
	public function test_send_plugin_config_when_exception_thrown() {
		$plugin = facebook_for_woocommerce();

		// Ensure transient is not set
		delete_transient( '_wc_facebook_for_woocommerce_send_plugin_config_flag' );

		// Mock API to throw exception
		$mock_api = $this->getMockBuilder( API::class )
			->disableOriginalConstructor()
			->setMethods( array( 'log_to_meta' ) )
			->getMock();
		$mock_api->expects( $this->once() )
			->method( 'log_to_meta' )
			->willThrowException( new \Exception( 'Test exception' ) );

		$this->prop_api->setValue( $plugin, $mock_api );

		// Call the method - it should catch the exception and log it
		$this->update->send_plugin_config_to_facebook_server();

		// The method should still set the transient flag even on exception
		$this->assertEquals(
			'yes',
			get_transient( '_wc_facebook_for_woocommerce_send_plugin_config_flag' ),
			'Failed asserting that transient flag was set even on exception'
		);

		// Clean up
		delete_transient( '_wc_facebook_for_woocommerce_send_plugin_config_flag' );
	}
}
