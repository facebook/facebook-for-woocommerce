<?php
/**
 * Unit Tests for the Sync class.
 */

namespace WooCommerce\Facebook\Tests\ProductSets;

use WP_UnitTestCase;
use WooCommerce\Facebook\ProductSets\Sync;

/**
 * Sync Unit Test class.
 */
class SyncTest extends WP_UnitTestCase {

    /**
	 * Instance of the Sync class that we are testing.
	 *
	 * @var \WooCommerce\Facebook\ProductSets\Sync The object to be tested.
	 */
	private $sync;

    /**
	 * Setup the test object for each test.
	 */
	public function setUp():void {
		$this->sync = new Sync();
	}

    /**
     * Tests maybe_sync_product_set method.
     */
    public function test_maybe_sync_product_set() {
        $this->assertTrue( true );
    }
}
