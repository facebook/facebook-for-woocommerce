<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 */

namespace WooCommerce\Facebook\Tests\Admin\Settings_Screens;

use WooCommerce\Facebook\Admin\Enhanced_Settings;
use WooCommerce\Facebook\Admin\Settings_Screens\Product_Attributes;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;

/**
 * Class Product_AttributesTest
 *
 * @package WooCommerce\Facebook\Tests\Unit\Admin\Settings_Screens
 */
class Product_AttributesTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

	/** @var string the handle the screen enqueues */
	private const HANDLE = 'facebook-for-woocommerce-product-attributes';

	/**
	 * @var Product_Attributes
	 */
	private $product_attributes;

	/**
	 * Set up the test environment.
	 */
	public function setUp(): void {
		parent::setUp();

		$this->product_attributes = new Product_Attributes();
		$this->product_attributes->initHook();
	}

	/**
	 * Tear down the test environment.
	 */
	public function tearDown(): void {
		unset( $_GET['page'], $_GET['tab'], $_REQUEST['page'], $_REQUEST['tab'] );

		wp_dequeue_script( self::HANDLE );
		wp_deregister_script( self::HANDLE );
		wp_dequeue_style( self::HANDLE );
		wp_deregister_style( self::HANDLE );

		parent::tearDown();
	}

	/**
	 * Puts the request on the Attribute Mapping tab so enqueue_assets() runs.
	 */
	private function visit_screen(): void {
		$_GET['page']     = Enhanced_Settings::PAGE_ID;
		$_REQUEST['page'] = Enhanced_Settings::PAGE_ID;
		$_GET['tab']      = Product_Attributes::ID;
		$_REQUEST['tab']  = Product_Attributes::ID;
	}

	/**
	 * Regression: the screen used to enqueue assets/build/admin/product-attributes.js, which has
	 * no webpack entry and so is never built. Every visit to the tab requested a missing file, and
	 * WordPress served its 404 template for it — firing a bogus PageView on the way.
	 *
	 * The screen's behavior is printed inline instead, so the handle must carry no source at all.
	 */
	public function test_enqueue_assets_does_not_request_an_unbuilt_script(): void {
		$this->visit_screen();

		$this->product_attributes->enqueue_assets();

		$this->assertTrue( wp_script_is( self::HANDLE, 'enqueued' ), 'The screen should still enqueue its handle.' );

		$src = wp_scripts()->registered[ self::HANDLE ]->src;

		$this->assertEmpty( $src, 'The handle must have no source, or the browser requests a file that is never built.' );
	}

	/**
	 * The source-less handle is what pulls select2 onto the page: this screen lives under
	 * Marketing, which is not a WooCommerce screen ID, so WooCommerce does not enqueue
	 * wc-enhanced-select here on its own. Dropping the dependency would silently turn the
	 * mapping dropdowns back into plain selects.
	 */
	public function test_enqueue_assets_keeps_its_script_dependencies(): void {
		$this->visit_screen();

		$this->product_attributes->enqueue_assets();

		$deps = wp_scripts()->registered[ self::HANDLE ]->deps;

		$this->assertContains( 'wc-enhanced-select', $deps );
		$this->assertContains( 'jquery-tiptip', $deps );
		$this->assertContains( 'jquery', $deps );
	}

	/**
	 * Inline scripts are attached to the handle, so they are dropped if the handle goes away.
	 */
	public function test_enqueue_assets_still_attaches_the_notice_dismissal_script(): void {
		$this->visit_screen();

		$this->product_attributes->enqueue_assets();

		$inline = wp_scripts()->get_data( self::HANDLE, 'after' );

		$this->assertNotEmpty( $inline, 'The dismissal handler should still be attached to the handle.' );
		$this->assertStringContainsString( 'fb_dismiss_attribute_notice', implode( '', (array) $inline ) );
	}

	/**
	 * The stylesheet is a real file and should keep being enqueued.
	 */
	public function test_enqueue_assets_enqueues_an_existing_stylesheet(): void {
		$this->visit_screen();

		$this->product_attributes->enqueue_assets();

		$this->assertTrue( wp_style_is( self::HANDLE, 'enqueued' ) );

		$src  = wp_styles()->registered[ self::HANDLE ]->src;
		$path = str_replace(
			facebook_for_woocommerce()->get_plugin_url(),
			dirname( __DIR__, 4 ),
			$src
		);

		$this->assertFileExists( $path, 'The screen should only enqueue stylesheets that exist on disk.' );
	}

	/**
	 * Nothing is enqueued away from the Attribute Mapping tab.
	 */
	public function test_enqueue_assets_does_nothing_on_other_screens(): void {
		$_GET['page']     = Enhanced_Settings::PAGE_ID;
		$_REQUEST['page'] = Enhanced_Settings::PAGE_ID;
		$_GET['tab']      = 'some-other-tab';
		$_REQUEST['tab']  = 'some-other-tab';

		$this->product_attributes->enqueue_assets();

		$this->assertFalse( wp_script_is( self::HANDLE, 'enqueued' ) );
		$this->assertFalse( wp_style_is( self::HANDLE, 'enqueued' ) );
	}
}
