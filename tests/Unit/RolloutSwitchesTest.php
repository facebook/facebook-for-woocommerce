<?php
use PHPUnit\Framework\TestCase;
use WooCommerce\Facebook\Admin\Settings_Screens\Connection;
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
		$mock_connection_handler = $this->getMockBuilder( Connection::class )
			->disableOriginalConstructor()
			->setMethods( array( 'get_external_business_id', 'is_connected', 'get_access_token' ) )
			->getMock();
		$mock_connection_handler->expects( $this->any() )->method( 'get_external_business_id' )->willReturn( $this->external_business_id );
		$mock_connection_handler->expects( $this->any() )->method( 'get_access_token' )->willReturn( $this->access_token );
		$prop_connection_handler->setValue( $plugin, $mock_connection_handler );
		// setup API
		$prop_api = $plugin_ref_obj->getProperty( 'api' );
		$prop_api->setAccessible( true );
		$mock_api = $this->getMockBuilder( API::class )->disableOriginalConstructor()->setMethods( array( 'do_remote_request' ) )->getMock();
		$mock_api->expects( $this->any() )->method( 'do_remote_request' )->willReturn(
			array('body' => json_encode(array(
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
}
