<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Unit\Admin\Notes;

use WooCommerce\Facebook\Admin\Notes\SettingsMoved;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;
use Automattic\WooCommerce\Admin\Notes\Note;

/**
 * Unit tests for SettingsMoved note class.
 *
 * @since 3.5.2
 */
class SettingsMovedTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

	/**
	 * Mock facebook_for_woocommerce instance.
	 *
	 * @var \WC_Facebookcommerce
	 */
	private $mock_plugin;

	/**
	 * Set up test environment.
	 */
	public function setUp(): void {
		parent::setUp();
		
		// Create a mock for the main plugin instance
		$this->mock_plugin = $this->getMockBuilder( \WC_Facebookcommerce::class )
			->disableOriginalConstructor()
			->getMock();
		
		// Mock the get_settings_url method
		$this->mock_plugin->method( 'get_settings_url' )
			->willReturn( 'https://example.com/wp-admin/admin.php?page=wc-settings&tab=facebook' );
			
		// Override the global function to return our mock
		add_filter( 'wc_facebook_for_woocommerce_instance', function() {
			return $this->mock_plugin;
		} );
	}

	/**
	 * Test that the class exists.
	 */
	public function test_class_exists() {
		$this->assertTrue( class_exists( 'WooCommerce\Facebook\Admin\Notes\SettingsMoved' ) );
	}

	/**
	 * Test NOTE_NAME constant.
	 */
	public function test_note_name_constant() {
		$this->assertEquals( 'facebook-for-woocommerce-settings-moved-to-marketing', SettingsMoved::NOTE_NAME );
	}

	/**
	 * Test should_display returns false when no last event.
	 */
	public function test_should_display_no_last_event() {
		// Mock the plugin to return null for last event
		$this->mock_plugin->method( 'get_last_event_from_history' )
			->willReturn( null );
		
		$this->assertFalse( SettingsMoved::should_display() );
	}

	/**
	 * Test should_display returns false when last event is not upgrade.
	 */
	public function test_should_display_non_upgrade_event() {
		// Mock the plugin to return a non-upgrade event
		$this->mock_plugin->method( 'get_last_event_from_history' )
			->willReturn( array(
				'name' => 'install',
				'data' => array( 'version' => '2.5.0' )
			) );
		
		$this->assertFalse( SettingsMoved::should_display() );
	}

	/**
	 * Test should_display returns false when upgrading from version >= 2.2.0.
	 */
	public function test_should_display_upgrade_from_newer_version() {
		// Mock the plugin to return an upgrade from 2.2.0 or higher
		$this->mock_plugin->method( 'get_last_event_from_history' )
			->willReturn( array(
				'name' => 'upgrade',
				'data' => array( 'from_version' => '2.3.0' )
			) );
		
		$this->assertFalse( SettingsMoved::should_display() );
	}

	/**
	 * Test should_display returns true when upgrading from version < 2.2.0.
	 */
	public function test_should_display_upgrade_from_older_version() {
		// Mock the plugin to return an upgrade from version < 2.2.0
		$this->mock_plugin->method( 'get_last_event_from_history' )
			->willReturn( array(
				'name' => 'upgrade',
				'data' => array( 'from_version' => '2.1.5' )
			) );
		
		$this->assertTrue( SettingsMoved::should_display() );
	}

	/**
	 * Test should_display with various version numbers.
	 */
	public function test_should_display_various_versions() {
		$test_cases = array(
			'1.0.0' => true,
			'1.9.9' => true,
			'2.0.0' => true,
			'2.1.0' => true,
			'2.1.9' => true,
			'2.2.0' => false,
			'2.2.1' => false,
			'2.3.0' => false,
			'3.0.0' => false,
		);
		
		foreach ( $test_cases as $version => $expected ) {
			$this->mock_plugin->method( 'get_last_event_from_history' )
				->willReturn( array(
					'name' => 'upgrade',
					'data' => array( 'from_version' => $version )
				) );
			
			$this->assertEquals( 
				$expected, 
				SettingsMoved::should_display(),
				"Version $version should return " . ( $expected ? 'true' : 'false' )
			);
		}
	}

	/**
	 * Test get_note returns properly configured Note object.
	 */
	public function test_get_note() {
		$note = SettingsMoved::get_note();
		
		$this->assertInstanceOf( Note::class, $note );
		$this->assertEquals( 'Facebook is now found under Marketing', $note->get_title() );
		$this->assertStringContainsString( 'Sync your products and reach customers', $note->get_content() );
		$this->assertEquals( Note::E_WC_ADMIN_NOTE_INFORMATIONAL, $note->get_type() );
		$this->assertEquals( SettingsMoved::NOTE_NAME, $note->get_name() );
		$this->assertEquals( 'facebook-for-woocommerce', $note->get_source() );
	}

	/**
	 * Test get_note includes correct action.
	 */
	public function test_get_note_action() {
		$note = SettingsMoved::get_note();
		$actions = $note->get_actions();
		
		$this->assertIsArray( $actions );
		$this->assertCount( 1, $actions );
		
		$action = $actions[0];
		$this->assertEquals( 'settings', $action->name );
		$this->assertEquals( 'Go to Facebook', $action->label );
		$this->assertEquals( 'https://example.com/wp-admin/admin.php?page=wc-settings&tab=facebook', $action->query );
	}

	/**
	 * Test version comparison logic for should_display edge cases.
	 */
	public function test_should_display_edge_case_versions() {
		// Test with exactly version 2.2.0 (boundary case)
		$this->mock_plugin->method( 'get_last_event_from_history' )
			->willReturn( array(
				'name' => 'upgrade',
				'data' => array( 'from_version' => '2.2.0' )
			) );
		$this->assertFalse( SettingsMoved::should_display() );
		
		// Test with version 2.1.99 (just below boundary)
		$this->mock_plugin->method( 'get_last_event_from_history' )
			->willReturn( array(
				'name' => 'upgrade',
				'data' => array( 'from_version' => '2.1.99' )
			) );
		$this->assertTrue( SettingsMoved::should_display() );
	}

	/**
	 * Test get_note returns consistent values across multiple calls.
	 */
	public function test_get_note_consistency() {
		$note1 = SettingsMoved::get_note();
		$note2 = SettingsMoved::get_note();
		
		// Both should return new instances with same values
		$this->assertNotSame( $note1, $note2 );
		$this->assertEquals( $note1->get_title(), $note2->get_title() );
		$this->assertEquals( $note1->get_content(), $note2->get_content() );
		$this->assertEquals( $note1->get_name(), $note2->get_name() );
	}

	/**
	 * Test get_note content contains expected text.
	 */
	public function test_get_note_content() {
		$note = SettingsMoved::get_note();
		$content = $note->get_content();
		
		$this->assertStringContainsString( 'Facebook', $content );
		$this->assertStringContainsString( 'Instagram', $content );
		$this->assertStringContainsString( 'Messenger', $content );
		$this->assertStringContainsString( 'WhatsApp', $content );
		$this->assertStringContainsString( 'Marketing', $content );
	}

	/**
	 * Clean up after tests.
	 */
	public function tearDown(): void {
		remove_all_filters( 'wc_facebook_for_woocommerce_instance' );
		parent::tearDown();
	}
} 