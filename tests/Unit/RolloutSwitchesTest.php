<?php
use PHPUnit\Framework\TestCase;
use WooCommerce\Facebook\API;
use WooCommerce\Facebook\Framework\Api\Exception as ApiException;
use WooCommerce\Facebook\RolloutSwitches;

class RolloutSwitchesTest extends \WooCommerce\Facebook\Tests\AbstractWPUnitTestWithSafeFiltering {

	/**
	 * Facebook Graph API endpoint.
	 *
	 * @var string
	 */
	private $endpoint = Api::GRAPH_API_URL;

	/**
	 * Facebook Graph API version.
	 *
	 * @var string
	 */
	private $version = Api::API_VERSION;

	/**
	 * @var Api
	 */
	private $api;

	private $access_token = 'test-access-token';
	private $external_business_id = '726635365295186';

	/**
	 * Runs before each test is executed.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->api = new Api( $this->access_token );
	}

	public function test_api() {
		$response = function( $result, $parsed_args, $url ) {
			$this->assertEquals( 'GET', $parsed_args['method'] );
			$url_params = "access_token={$this->access_token}&fbe_external_business_id={$this->external_business_id}";
			$path = "fbe_business/fbe_rollout_switches";
			$this->assertEquals( "{$this->endpoint}{$this->version}/{$path}?{$url_params}", $url );
			return [
				'body'     => '{"data":[{"switch": "switch_a","enabled":"1"}, {"switch": "switch_b","enabled":""}, {"switch": "switch_c","enabled":"1"}]}',
				'response' => [
					'code'    => 200,
					'message' => 'OK',
				],
			];
		};
		$this->add_filter_with_safe_teardown( 'pre_http_request', $response, 10, 3 );

		$response = $this->api->get_rollout_switches( $this->external_business_id );
		$this->assertEquals([
			[
				'switch' => 'switch_a',
				'enabled' => '1',
			],
			[
				'switch' => 'switch_b',
				'enabled' => '',
			],
			[
				'switch' => 'switch_c',
				'enabled' => '1',
			],
		], $response->get_data());
	}

	public function test_plugin() {

		// mock the active filters to test business values
		$plugin = facebook_for_woocommerce();
		$plugin_ref_obj          = new ReflectionObject( $plugin );
		// setup connection handler
		$prop_connection_handler = $plugin_ref_obj->getProperty( 'connection_handler' );
		$prop_connection_handler->setAccessible( true );
		$mock_connection_handler = $this->getMockBuilder( 'stdClass' )
			->addMethods( array( 'get_external_business_id', 'is_connected', 'get_access_token' ) )
			->getMock();
		$mock_connection_handler->expects( $this->any() )->method( 'get_external_business_id' )->willReturn( $this->external_business_id );
		$mock_connection_handler->expects( $this->any() )->method( 'get_access_token' )->willReturn( $this->access_token );
		$mock_connection_handler->expects( $this->any() )->method( 'is_connected' )->willReturn( true );
		$prop_connection_handler->setValue( $plugin, $mock_connection_handler );
		// setup API
		$prop_api = $plugin_ref_obj->getProperty( 'api' );
		$prop_api->setAccessible( true );
		$mock_api = $this->getMockBuilder( API::class )->disableOriginalConstructor()->setMethods( array( 'do_remote_request' ) )->getMock();
		$mock_api->expects( $this->any() )->method( 'do_remote_request' )->willReturn(
			array('body' => wp_json_encode(array(
				'data' => array(
					array('switch' => 'switch_a','enabled' => '1'),
					array('switch' => 'switch_b', 'enabled' => ''),
					array( 'switch' => 'switch_c', 'enabled' => '1'),
				)
			))));
		$prop_api->setValue( $plugin, $mock_api );

		$switch_mock = $this->getMockBuilder(RolloutSwitches::class)
			->setConstructorArgs( array( $plugin ) )
            ->onlyMethods(['is_switch_active'])
            ->getMock();
		$switch_mock->expects($this->any())->method('is_switch_active')
			->willReturnCallback(function($switch_name) {
				switch ($switch_name) {
					case 'switch_a':
					case 'switch_b':
					case 'switch_d':
						return true;
					default:
						return false;
				}
			});
		$switch_mock->init();

		// If the switch is not active -> FALSE (independent of the response being true)
		$this->assertEquals( $switch_mock->is_switch_enabled("switch_c"), false );

		// If the feature is active and in the response -> response value
		$this->assertEquals( $switch_mock->is_switch_enabled("switch_a"), true );
		$this->assertEquals( $switch_mock->is_switch_enabled("switch_b"), false );

		// If the switch is active but not in the response -> TRUE
		$this->assertEquals( $switch_mock->is_switch_enabled("switch_d"), true );
	}

	public function test_multiple_images_switch_constant_exists() {
		// Test that the new multiple images switch constant exists
		$this->assertTrue(defined('WooCommerce\Facebook\RolloutSwitches::SWITCH_MULTIPLE_IMAGES_ENABLED'));
		$this->assertEquals('woo_variant_multiple_images_enabled', RolloutSwitches::SWITCH_MULTIPLE_IMAGES_ENABLED);
	}

	public function test_multiple_images_switch_in_active_switches() {
		$plugin = facebook_for_woocommerce();
		$rollout_switches = new RolloutSwitches($plugin);

		// Test that the multiple images switch is in the active switches list
		$active_switches = $rollout_switches->get_active_switches();
		$this->assertContains(RolloutSwitches::SWITCH_MULTIPLE_IMAGES_ENABLED, $active_switches);
	}

	public function test_multiple_images_switch_behavior() {
		// mock the active filters to test multiple images switch behavior
		$plugin = facebook_for_woocommerce();
		$plugin_ref_obj = new ReflectionObject($plugin);

		// setup connection handler
		$prop_connection_handler = $plugin_ref_obj->getProperty('connection_handler');
		$prop_connection_handler->setAccessible(true);
		$mock_connection_handler = $this->getMockBuilder('stdClass')
			->addMethods(array('get_external_business_id', 'is_connected', 'get_access_token'))
			->getMock();
		$mock_connection_handler->expects($this->any())->method('get_external_business_id')->willReturn($this->external_business_id);
		$mock_connection_handler->expects($this->any())->method('get_access_token')->willReturn($this->access_token);
		$mock_connection_handler->expects($this->any())->method('is_connected')->willReturn(true);
		$prop_connection_handler->setValue($plugin, $mock_connection_handler);

		// setup API
		$prop_api = $plugin_ref_obj->getProperty('api');
		$prop_api->setAccessible(true);
		$mock_api = $this->getMockBuilder(API::class)->disableOriginalConstructor()->setMethods(array('do_remote_request'))->getMock();
		$mock_api->expects($this->any())->method('do_remote_request')->willReturn(
			array('body' => wp_json_encode(array(
				'data' => array(
					array('switch' => RolloutSwitches::SWITCH_MULTIPLE_IMAGES_ENABLED, 'enabled' => '1'),
				)
			)))
		);
		$prop_api->setValue($plugin, $mock_api);

		$switch_mock = $this->getMockBuilder(RolloutSwitches::class)
			->setConstructorArgs(array($plugin))
			->onlyMethods(['is_switch_active'])
			->getMock();
		$switch_mock->expects($this->any())->method('is_switch_active')
			->willReturnCallback(function($switch_name) {
				return $switch_name === RolloutSwitches::SWITCH_MULTIPLE_IMAGES_ENABLED;
			});
		$switch_mock->init();

		// Test that multiple images switch is enabled when returned from API
		$this->assertTrue($switch_mock->is_switch_enabled(RolloutSwitches::SWITCH_MULTIPLE_IMAGES_ENABLED));
	}

	public function test_multiple_images_switch_disabled() {
		// Test behavior when multiple images switch is disabled
		$plugin = facebook_for_woocommerce();
		$plugin_ref_obj = new ReflectionObject($plugin);

		// setup connection handler
		$prop_connection_handler = $plugin_ref_obj->getProperty('connection_handler');
		$prop_connection_handler->setAccessible(true);
		$mock_connection_handler = $this->getMockBuilder('stdClass')
			->addMethods(array('get_external_business_id', 'is_connected', 'get_access_token'))
			->getMock();
		$mock_connection_handler->expects($this->any())->method('get_external_business_id')->willReturn($this->external_business_id);
		$mock_connection_handler->expects($this->any())->method('get_access_token')->willReturn($this->access_token);
		$mock_connection_handler->expects($this->any())->method('is_connected')->willReturn(true);
		$prop_connection_handler->setValue($plugin, $mock_connection_handler);

		// setup API to return switch as disabled
		$prop_api = $plugin_ref_obj->getProperty('api');
		$prop_api->setAccessible(true);
		$mock_api = $this->getMockBuilder(API::class)->disableOriginalConstructor()->setMethods(array('do_remote_request'))->getMock();
		$mock_api->expects($this->any())->method('do_remote_request')->willReturn(
			array('body' => wp_json_encode(array(
				'data' => array(
					array('switch' => RolloutSwitches::SWITCH_MULTIPLE_IMAGES_ENABLED, 'enabled' => ''),
				)
			)))
		);
		$prop_api->setValue($plugin, $mock_api);

		$switch_mock = $this->getMockBuilder(RolloutSwitches::class)
			->setConstructorArgs(array($plugin))
			->onlyMethods(['is_switch_active'])
			->getMock();
		$switch_mock->expects($this->any())->method('is_switch_active')
			->willReturnCallback(function($switch_name) {
				return $switch_name === RolloutSwitches::SWITCH_MULTIPLE_IMAGES_ENABLED;
			});
		$switch_mock->init();

		// Test that multiple images switch is disabled when returned as disabled from API
		$this->assertFalse($switch_mock->is_switch_enabled(RolloutSwitches::SWITCH_MULTIPLE_IMAGES_ENABLED));
	}

	/**
	 * Test that $flag_name is updated with version and continues execution on version update
	 * instead of returning early from the transient check.
	 */
	public function test_version_update_bypasses_transient_check_and_continues_execution() {
		// Mock the plugin to test version update behavior
		$plugin = facebook_for_woocommerce();
		$plugin_ref_obj = new ReflectionObject($plugin);

		// Setup connection handler
		$prop_connection_handler = $plugin_ref_obj->getProperty('connection_handler');
		$prop_connection_handler->setAccessible(true);
		$mock_connection_handler = $this->getMockBuilder('stdClass')
			->addMethods(array('get_external_business_id', 'is_connected', 'get_access_token'))
			->getMock();
		$mock_connection_handler->expects($this->any())->method('get_external_business_id')->willReturn($this->external_business_id);
		$mock_connection_handler->expects($this->any())->method('get_access_token')->willReturn($this->access_token);
		$mock_connection_handler->expects($this->any())->method('is_connected')->willReturn(true);
		$prop_connection_handler->setValue($plugin, $mock_connection_handler);

		// Setup API mock to track if it gets called (indicating execution continued)
		$prop_api = $plugin_ref_obj->getProperty('api');
		$prop_api->setAccessible(true);
		$mock_api = $this->getMockBuilder(API::class)
			->disableOriginalConstructor()
			->setMethods(array('get_rollout_switches'))
			->getMock();

		// Set up successful API response
		$mock_response = $this->getMockBuilder('stdClass')
			->addMethods(array('get_data'))
			->getMock();
		$mock_response->expects($this->exactly(2))->method('get_data')->willReturn(array(
			array('switch' => 'test_switch', 'enabled' => '1')
		));

		// Expect get_rollout_switches to be called twice (once for each version)
		$mock_api->expects($this->exactly(2))
			->method('get_rollout_switches')
			->with($this->external_business_id)
			->willReturn($mock_response);

		$prop_api->setValue($plugin, $mock_api);

		// Create RolloutSwitches instance
		$rollout_switches = new RolloutSwitches($plugin);

		// Mock plugin version for first run - set version to "1.0.0"
		$version_prop = $plugin_ref_obj->getProperty('version');
		$version_prop->setAccessible(true);
		$version_prop->setValue($plugin, '1.0.0');

		// Clear any existing transients to start fresh
		delete_transient('_wc_facebook_for_woocommerce_rollout_switch_flag_1.0.0');
		delete_transient('_wc_facebook_for_woocommerce_rollout_switch_flag_2.0.0');

		// First call with version 1.0.0 - should execute and set transient
		$rollout_switches->init();

		// Verify the transient was set for version 1.0.0
		$this->assertEquals('yes', get_transient('_wc_facebook_for_woocommerce_rollout_switch_flag_1.0.0'));

		// Simulate version update to "2.0.0"
		$version_prop->setValue($plugin, '2.0.0');

		// Second call with version 2.0.0 - should bypass the old transient check and execute again
		// because the flag_name has changed to include the new version
		$rollout_switches->init();

		// Verify new transient was set for version 2.0.0
		$this->assertEquals('yes', get_transient('_wc_facebook_for_woocommerce_rollout_switch_flag_2.0.0'));

		// Verify old transient is still there (different key)
		$this->assertEquals('yes', get_transient('_wc_facebook_for_woocommerce_rollout_switch_flag_1.0.0'));

		// Cleanup
		delete_transient('_wc_facebook_for_woocommerce_rollout_switch_flag_1.0.0');
		delete_transient('_wc_facebook_for_woocommerce_rollout_switch_flag_2.0.0');
	}

	public function test_plugin_when_failing() {

		// mock the active filters to test business values
		$plugin = facebook_for_woocommerce();
		$plugin_ref_obj          = new ReflectionObject( $plugin );
		// setup connection handler
		$prop_connection_handler = $plugin_ref_obj->getProperty( 'connection_handler' );
		$prop_connection_handler->setAccessible( true );
		$mock_connection_handler = $this->getMockBuilder( 'stdClass' )
			->addMethods( array( 'get_external_business_id', 'is_connected', 'get_access_token' ) )
			->getMock();
		$mock_connection_handler->expects( $this->any() )->method( 'get_external_business_id' )->willReturn( $this->external_business_id );
		$mock_connection_handler->expects( $this->any() )->method( 'get_access_token' )->willReturn( $this->access_token );
		$mock_connection_handler->expects( $this->any() )->method( 'is_connected' )->willReturn( true );
		$prop_connection_handler->setValue( $plugin, $mock_connection_handler );
		// setup API
		$prop_api = $plugin_ref_obj->getProperty( 'api' );
		$prop_api->setAccessible( true );
		$mock_api = $this->getMockBuilder( API::class )->disableOriginalConstructor()->setMethods( array( 'do_remote_request' ) )->getMock();
		$mock_api->expects( $this->any() )->method( 'do_remote_request' )->willReturn(
			array('body' => wp_json_encode(array())));
		$prop_api->setValue( $plugin, $mock_api );

		$switch_mock = $this->getMockBuilder(RolloutSwitches::class)
			->setConstructorArgs( array( $plugin ) )
            ->onlyMethods(['is_switch_active', 'get_active_switches'])
            ->getMock();
		$switch_mock->expects($this->any())->method('is_switch_active')
			->willReturnCallback(function($switch_name) {
				switch ($switch_name) {
					case 'switch_a':
					case 'switch_b':
					case 'switch_d':
						return true;
					default:
						return false;
				}
			});
		$switch_mock->expects($this->any())->method('get_active_switches')
			->willReturnCallback(function() {
				return array(
					'switch_a',
					'switch_b',
					'switch_d',
				);
			});
		$switch_mock->init();

		// If the switch is not active -> FALSE (independent of the response being true)
		$this->assertEquals( $switch_mock->is_switch_enabled("switch_c"), false );

		// If the feature is active and in the response -> response value
		$this->assertEquals( $switch_mock->is_switch_enabled("switch_a"), false );
		$this->assertEquals( $switch_mock->is_switch_enabled("switch_b"), false );

		// If the switch is active but not in the response -> TRUE
		$this->assertEquals( $switch_mock->is_switch_enabled("switch_d"), false );
	}
}
