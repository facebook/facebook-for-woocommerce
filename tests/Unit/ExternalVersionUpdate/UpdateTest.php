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
use WooCommerce\Facebook\Tests\SafelyUpdateOptionsTestTrait;

/**
 * The External version update unit test class.
 */
class UpdateTest extends WP_UnitTestCase {

	use SafelyUpdateOptionsTestTrait;

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
	 * Test should_update_version
	 */
	public function test_should_update_version() {
		$plugin = facebook_for_woocommerce();
		/**
		 * Set the $plugin->connection_handler and $plugin->api access to true. This will allow us
		 * to assign the mock objects to these properties.
		 */
		$plugin_ref_obj          = new ReflectionObject( $plugin );
		$prop_connection_handler = $plugin_ref_obj->getProperty( 'connection_handler' );
		$prop_connection_handler->setAccessible( true );

		// Connection Handler mock object to return is_connected as false.
		$mock_connection_handler = $this->getMockBuilder( Connection::class )
									->disableOriginalConstructor()
									->setMethods( array( 'is_connected' ) )
									->getMock();
		$mock_connection_handler->expects( $this->any() )->method( 'is_connected' )->willReturn( false );
		$prop_connection_handler->setValue( $plugin, $mock_connection_handler );
		$this->set_option_safely_only_for_this_test( 'facebook_for_woocommerce_latest_version_sent_to_server', '0.0.0' ); // Reset the option.
		$should_update2 = $this->update->should_update_version();
		$this->assertFalse( $should_update2 );

		// Connection Handler mock object to return is_connected as true.
		$mock_connection_handler = $this->getMockBuilder( Connection::class )
									->disableOriginalConstructor()
									->setMethods( array( 'is_connected' ) )
									->getMock();
		$mock_connection_handler->expects( $this->any() )->method( 'is_connected' )->willReturn( true );
		$prop_connection_handler->setValue( $plugin, $mock_connection_handler );
		$this->set_option_safely_only_for_this_test( 'facebook_for_woocommerce_latest_version_sent_to_server', WC_Facebookcommerce_Utils::PLUGIN_VERSION );
		$should_update3 = $this->update->should_update_version();
		$this->assertTrue( $should_update3 ); // Because the versions match.

		$this->set_option_safely_only_for_this_test( 'facebook_for_woocommerce_latest_version_sent_to_server', '0.0.0' ); // Reset the option.
		$should_update4 = $this->update->should_update_version();
		$this->assertTrue( $should_update4 );
	}

	/**
	 * Test send new version to facebook.
	 */
	public function test_maybe_update_external_plugin_version() {
		$plugin = facebook_for_woocommerce();

		/**
		 * Set the $plugin->connection_handler and $plugin->api access to true. This will allow us
		 * to assign the mock objects to these properties.
		 */
		$plugin_ref_obj          = new ReflectionObject( $plugin );
		$prop_connection_handler = $plugin_ref_obj->getProperty( 'connection_handler' );
		$prop_connection_handler->setAccessible( true );

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

		$updated = $this->update->maybe_update_external_plugin_version();

		// Assert request data.
		$expected_request = array(
			'fbe_external_business_id' => 'dummy-business-id',
			'business_config'          => array(
				'external_client' => array(
					'version_id' => WC_Facebookcommerce_Utils::PLUGIN_VERSION,
					'is_multisite' => false,
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

		// For the subsequent request, no update request should be made.
		$updated_second_time = $this->update->maybe_update_external_plugin_version();
		$this->assertFalse( $updated_second_time, 'Failed asserting that the update plugin request was not made.' );

		// Now the mock API object will return a WP_Error.
		$mock_api2 = $this->getMockBuilder( API::class )->disableOriginalConstructor()->setMethods( array( 'do_remote_request' ) )->getMock();
		$mock_api2->expects( $this->any() )->method( 'do_remote_request' )->willReturn( new WP_Error( 'dummy-code', 'dummy-message', array( 'data' => 'dummy data' ) ) );
		$prop_api->setValue( $plugin, $mock_api2 );

		// Assert handling failed API response.
		$this->set_option_safely_only_for_this_test( 'facebook_for_woocommerce_latest_version_sent_to_server', '0.0.0' ); // Reset the version to pass the should_update_version check.
		$updated3 = $this->update->maybe_update_external_plugin_version();
		$this->assertFalse( $updated3 ); // API failed response is handled.
		$this->assertNotEquals( WC_Facebookcommerce_Utils::PLUGIN_VERSION, get_option( 'facebook_for_woocommerce_latest_version_sent_to_server' ) ); // API failed response should not update the option.

		// Now the mock API object will throw a Plugin Exception.
		$mock_api3 = $this->getMockBuilder( API::class )->disableOriginalConstructor()->setMethods( array( 'perform_request' ) )->getMock();
		$mock_api3->expects( $this->any() )->method( 'perform_request' )->willThrowException( new PluginException( 'Dummy Plugin Exception' ) );
		$prop_api->setValue( $plugin, $mock_api3 );

		// Assert PluginException Handling.
		$this->set_option_safely_only_for_this_test( 'facebook_for_woocommerce_latest_version_sent_to_server', '0.0.0' ); // Reset the version to pass the should_update_version check.
		$updated4 = $this->update->maybe_update_external_plugin_version();
		$this->assertFalse( $updated4 ); // API failed response is handled.
		$this->assertNotEquals( WC_Facebookcommerce_Utils::PLUGIN_VERSION, get_option( 'facebook_for_woocommerce_latest_version_sent_to_server' ) ); // API failed response should not update the option.

		// Now the mock API object will throw an ApiException.
		$mock_api4 = $this->getMockBuilder( API::class )->disableOriginalConstructor()->setMethods( array( 'perform_request' ) )->getMock();
		$mock_api4->expects( $this->any() )->method( 'perform_request' )->willThrowException( new ApiException( 'Dummy API Exception' ) );
		$prop_api->setValue( $plugin, $mock_api4 );

		// Assert ApiException Handling.
		$this->set_option_safely_only_for_this_test( 'facebook_for_woocommerce_latest_version_sent_to_server', '0.0.0' ); // Reset the version to pass the should_update_version check.
		$updated5 = $this->update->maybe_update_external_plugin_version();
		$this->assertFalse( $updated5 ); // API failed response is handled.
		$this->assertNotEquals( WC_Facebookcommerce_Utils::PLUGIN_VERSION, get_option( 'facebook_for_woocommerce_latest_version_sent_to_server' ) ); // API failed response should not update the option.
	}
}
