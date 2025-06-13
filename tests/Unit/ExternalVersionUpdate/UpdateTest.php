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
}
