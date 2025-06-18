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
	 * Test possibly_add_or_delete_note adds note when should_display is true.
	 */
	public function test_possibly_add_or_delete_note_adds_note() {
		// Mock should_display to return true
		$this->mock_plugin->method( 'get_last_event_from_history' )
			->willReturn( array(
				'name' => 'upgrade',
				'data' => array( 'from_version' => '2.1.0' )
			) );
		
		// Mock note_exists to return false
		$mock_note_exists = function() {
			return false;
		};
		add_filter( 'wc_facebook_settings_moved_note_exists', $mock_note_exists );
		
		// Mock possibly_add_note to track if it was called
		$add_note_called = false;
		$mock_add_note = function() use ( &$add_note_called ) {
			$add_note_called = true;
		};
		add_filter( 'wc_facebook_settings_moved_possibly_add_note', $mock_add_note );
		
		SettingsMoved::possibly_add_or_delete_note();
		
		$this->assertTrue( $add_note_called );
		
		// Clean up
		remove_filter( 'wc_facebook_settings_moved_note_exists', $mock_note_exists );
		remove_filter( 'wc_facebook_settings_moved_possibly_add_note', $mock_add_note );
	}

	/**
	 * Test possibly_add_or_delete_note deletes note when should_display is false.
	 */
	public function test_possibly_add_or_delete_note_deletes_note() {
		// Mock should_display to return false
		$this->mock_plugin->method( 'get_last_event_from_history' )
			->willReturn( array(
				'name' => 'upgrade',
				'data' => array( 'from_version' => '2.3.0' )
			) );
		
		// Mock note_exists to return true
		$mock_note_exists = function() {
			return true;
		};
		add_filter( 'wc_facebook_settings_moved_note_exists', $mock_note_exists );
		
		// Mock possibly_delete_note to track if it was called
		$delete_note_called = false;
		$mock_delete_note = function() use ( &$delete_note_called ) {
			$delete_note_called = true;
		};
		add_filter( 'wc_facebook_settings_moved_possibly_delete_note', $mock_delete_note );
		
		SettingsMoved::possibly_add_or_delete_note();
		
		$this->assertTrue( $delete_note_called );
		
		// Clean up
		remove_filter( 'wc_facebook_settings_moved_note_exists', $mock_note_exists );
		remove_filter( 'wc_facebook_settings_moved_possibly_delete_note', $mock_delete_note );
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