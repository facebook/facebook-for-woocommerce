<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Unit\Events;

use ReflectionClass;
use WooCommerce\Facebook\Events\Event;
use WooCommerce\Facebook\Events\FacebookSignalsState;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;

/**
 * Tests for Event behaviour when FacebookSignalsState is held.
 *
 * Covers session-write suppression for _fbc/_fbp, the click-ID priority
 * chain, the fbclid-change detection helper, and browser-ID fallback logic.
 */
class EventSignalsStateTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

	/** @var array|null Original $_SESSION backup. */
	private $original_session;

	/** @var array Superglobal keys to save/restore. */
	private $saved_cookie = array();
	private $saved_get    = array();

	/** @var string|null */
	private $original_user_agent;

	/** @var mixed Original param_builder to restore after tests. */
	private $original_param_builder;

	public function setUp(): void {
		parent::setUp();

		$this->original_user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Test)';

		if ( ! isset( $_SESSION ) ) {
			$_SESSION = array();
		}
		$this->original_session = $_SESSION;

		unset( $_COOKIE['_fbc'], $_COOKIE['_fbp'] );
		unset( $_GET['fbclid'] );
		unset( $_SESSION['_fbc'], $_SESSION['_fbp'] );

		FacebookSignalsState::release();

		$this->original_param_builder = $this->install_null_param_builder();
	}

	public function tearDown(): void {
		$_SESSION = $this->original_session;
		unset( $_COOKIE['_fbc'], $_COOKIE['_fbp'] );
		unset( $_GET['fbclid'] );

		if ( null === $this->original_user_agent ) {
			unset( $_SERVER['HTTP_USER_AGENT'] );
		} else {
			$_SERVER['HTTP_USER_AGENT'] = $this->original_user_agent;
		}

		FacebookSignalsState::release();
		$this->restore_tracker_state( $this->original_param_builder );

		parent::tearDown();
	}

	// ------------------------------------------------------------------
	// Helpers
	// ------------------------------------------------------------------

	/**
	 * Replaces the static param_builder with a stub that returns null and
	 * clears cached values so Event falls through to cookie/session/query-
	 * string paths.
	 *
	 * @return mixed The original param_builder value to restore later.
	 */
	private function install_null_param_builder() {
		$ref = new ReflectionClass( \WC_Facebookcommerce_EventsTracker::class );

		$stub = new class() {
			public function getFbc() {
				return null;
			}
			public function getFbp() {
				return null;
			}
			public function getCookiesToSet() {
				return array();
			}
		};

		$pb = $ref->getProperty( 'param_builder' );
		$pb->setAccessible( true );
		$original = $pb->getValue();
		$pb->setValue( null, $stub );

		foreach ( array( 'cached_fbc', 'cached_fbp' ) as $prop ) {
			$rp = $ref->getProperty( $prop );
			$rp->setAccessible( true );
			$rp->setValue( null, null );
		}

		return $original;
	}

	/**
	 * Restores the original param_builder and clears caches.
	 */
	private function restore_tracker_state( $original_param_builder ): void {
		$ref = new ReflectionClass( \WC_Facebookcommerce_EventsTracker::class );

		$pb = $ref->getProperty( 'param_builder' );
		$pb->setAccessible( true );
		$pb->setValue( null, $original_param_builder );

		foreach ( array( 'cached_fbc', 'cached_fbp' ) as $prop ) {
			$rp = $ref->getProperty( $prop );
			$rp->setAccessible( true );
			$rp->setValue( null, null );
		}
	}

	/**
	 * Call the protected has_fbclid_changed() via reflection.
	 */
	private function call_has_fbclid_changed( string $cookie_fbc, ?string $request_fbclid ): bool {
		$ref    = new ReflectionClass( Event::class );
		$method = $ref->getMethod( 'has_fbclid_changed' );
		$method->setAccessible( true );

		return $method->invoke( null, $cookie_fbc, $request_fbclid );
	}

	// ------------------------------------------------------------------
	// 1. Session not written when signals are held
	// ------------------------------------------------------------------

	public function test_session_fbc_not_written_when_held(): void {
		FacebookSignalsState::hold();
		$_COOKIE['_fbc'] = 'fb.1.1000000.testclickid';

		new Event( array( 'event_name' => 'PageView' ) );

		$this->assertArrayNotHasKey( '_fbc', $_SESSION );
	}

	public function test_session_fbp_not_written_when_held(): void {
		FacebookSignalsState::hold();
		$_COOKIE['_fbp'] = 'fb.1.1000000.999888777';

		new Event( array( 'event_name' => 'PageView' ) );

		$this->assertArrayNotHasKey( '_fbp', $_SESSION );
	}

	public function test_session_fbc_not_written_when_held_via_fbclid(): void {
		FacebookSignalsState::hold();
		$_GET['fbclid'] = 'newclickid123';

		new Event( array( 'event_name' => 'PageView' ) );

		$this->assertArrayNotHasKey( '_fbc', $_SESSION );
	}

	// ------------------------------------------------------------------
	// 2. Session written normally when signals are active
	// ------------------------------------------------------------------

	public function test_session_fbc_written_when_not_held(): void {
		$_COOKIE['_fbc'] = 'fb.1.1000000.testclickid';

		new Event( array( 'event_name' => 'PageView' ) );

		$this->assertArrayHasKey( '_fbc', $_SESSION );
		$this->assertEquals( 'fb.1.1000000.testclickid', $_SESSION['_fbc'] );
	}

	public function test_session_fbp_written_when_not_held(): void {
		$_COOKIE['_fbp'] = 'fb.1.1000000.999888777';

		new Event( array( 'event_name' => 'PageView' ) );

		$this->assertArrayHasKey( '_fbp', $_SESSION );
		$this->assertEquals( 'fb.1.1000000.999888777', $_SESSION['_fbp'] );
	}

	// ------------------------------------------------------------------
	// 3. New fbclid overrides stale cookie
	// ------------------------------------------------------------------

	public function test_new_fbclid_overrides_stale_cookie_fbc(): void {
		$_COOKIE['_fbc'] = 'fb.1.1000000.oldfbclid';
		$_GET['fbclid']  = 'newfbclid';

		$event     = new Event( array( 'event_name' => 'PageView' ) );
		$user_data = $event->get_user_data();

		$this->assertStringContainsString( 'newfbclid', $user_data['click_id'] );
		$this->assertStringNotContainsString( 'oldfbclid', $user_data['click_id'] );
	}

	// ------------------------------------------------------------------
	// 4. Same fbclid preserves existing cookie
	// ------------------------------------------------------------------

	public function test_same_fbclid_preserves_existing_cookie(): void {
		$_COOKIE['_fbc'] = 'fb.1.1000000.samefbclid';
		$_GET['fbclid']  = 'samefbclid';

		$event     = new Event( array( 'event_name' => 'PageView' ) );
		$user_data = $event->get_user_data();

		$this->assertEquals( 'fb.1.1000000.samefbclid', $user_data['click_id'] );
	}

	// ------------------------------------------------------------------
	// 5. Click-ID fallback priority chain
	// ------------------------------------------------------------------

	public function test_click_id_returns_cookie_when_no_fbclid(): void {
		$_COOKIE['_fbc'] = 'fb.1.2000000.cookieclickid';

		$event     = new Event( array( 'event_name' => 'PageView' ) );
		$user_data = $event->get_user_data();

		$this->assertEquals( 'fb.1.2000000.cookieclickid', $user_data['click_id'] );
	}

	public function test_click_id_falls_back_to_session(): void {
		$_SESSION['_fbc'] = 'fb.1.3000000.sessionclickid';

		$event     = new Event( array( 'event_name' => 'PageView' ) );
		$user_data = $event->get_user_data();

		$this->assertEquals( 'fb.1.3000000.sessionclickid', $user_data['click_id'] );
	}

	public function test_click_id_returns_null_when_nothing_available(): void {
		$event     = new Event( array( 'event_name' => 'PageView' ) );
		$user_data = $event->get_user_data();

		$this->assertNull( $user_data['click_id'] );
	}

	// ------------------------------------------------------------------
	// 6. Browser-ID fallback priority chain
	// ------------------------------------------------------------------

	public function test_browser_id_returns_cookie_value(): void {
		$_COOKIE['_fbp'] = 'fb.1.4000000.cookiebrowserid';

		$event     = new Event( array( 'event_name' => 'PageView' ) );
		$user_data = $event->get_user_data();

		$this->assertEquals( 'fb.1.4000000.cookiebrowserid', $user_data['browser_id'] );
	}

	public function test_browser_id_falls_back_to_session(): void {
		$_SESSION['_fbp'] = 'fb.1.5000000.sessionbrowserid';

		$event     = new Event( array( 'event_name' => 'PageView' ) );
		$user_data = $event->get_user_data();

		$this->assertEquals( 'fb.1.5000000.sessionbrowserid', $user_data['browser_id'] );
	}

	public function test_browser_id_returns_null_when_nothing_available(): void {
		$event     = new Event( array( 'event_name' => 'PageView' ) );
		$user_data = $event->get_user_data();

		$this->assertNull( $user_data['browser_id'] );
	}

	// ------------------------------------------------------------------
	// Event still populates user_data in-memory even when held
	// ------------------------------------------------------------------

	public function test_event_has_click_id_in_memory_when_held(): void {
		FacebookSignalsState::hold();
		$_COOKIE['_fbc'] = 'fb.1.6000000.inmemorytest';

		$event     = new Event( array( 'event_name' => 'PageView' ) );
		$user_data = $event->get_user_data();

		$this->assertEquals( 'fb.1.6000000.inmemorytest', $user_data['click_id'] );
	}

	public function test_event_has_browser_id_in_memory_when_held(): void {
		FacebookSignalsState::hold();
		$_COOKIE['_fbp'] = 'fb.1.6000000.inmemorytest';

		$event     = new Event( array( 'event_name' => 'PageView' ) );
		$user_data = $event->get_user_data();

		$this->assertEquals( 'fb.1.6000000.inmemorytest', $user_data['browser_id'] );
	}

	// ------------------------------------------------------------------
	// 7. Session suppressed even during admin/cron when held
	// ------------------------------------------------------------------

	public function test_session_not_written_when_held_during_admin(): void {
		FacebookSignalsState::hold();
		$_COOKIE['_fbc'] = 'fb.1.7000000.admintest';
		$_COOKIE['_fbp'] = 'fb.1.7000000.adminbrowser';

		// Simulate an admin context.
		set_current_screen( 'edit-post' );
		$this->assertTrue( is_admin() );

		new Event( array( 'event_name' => 'Purchase' ) );

		$this->assertArrayNotHasKey( '_fbc', $_SESSION );
		$this->assertArrayNotHasKey( '_fbp', $_SESSION );

		set_current_screen( 'front' );
	}

	// ------------------------------------------------------------------
	// 9. has_fbclid_changed() edge cases
	// ------------------------------------------------------------------

	public function test_has_fbclid_changed_returns_false_for_null_fbclid(): void {
		$this->assertFalse(
			$this->call_has_fbclid_changed( 'fb.1.1000000.abc', null )
		);
	}

	public function test_has_fbclid_changed_returns_true_for_malformed_cookie(): void {
		$this->assertTrue(
			$this->call_has_fbclid_changed( 'malformed', 'anyfbclid' )
		);
	}

	public function test_has_fbclid_changed_returns_false_for_same_fbclid(): void {
		$this->assertFalse(
			$this->call_has_fbclid_changed( 'fb.1.1000000.sameid', 'sameid' )
		);
	}

	public function test_has_fbclid_changed_returns_true_for_different_fbclid(): void {
		$this->assertTrue(
			$this->call_has_fbclid_changed( 'fb.1.1000000.oldid', 'newid' )
		);
	}

	public function test_has_fbclid_changed_handles_fbclid_with_dots(): void {
		$this->assertFalse(
			$this->call_has_fbclid_changed( 'fb.1.1000000.dotted.fbclid.value', 'dotted.fbclid.value' )
		);

		$this->assertTrue(
			$this->call_has_fbclid_changed( 'fb.1.1000000.dotted.fbclid.value', 'dotted.fbclid.other' )
		);
	}
}
