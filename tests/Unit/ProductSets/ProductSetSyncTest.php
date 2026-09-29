<?php
/** Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package MetaCommerce
 */

require_once __DIR__ . '/../../../includes/ProductSets/ProductSetSync.php';

use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithSafeFiltering;
use WooCommerce\Facebook\ProductSets\ProductSetSync;

/**
 * Class FeedUploadUtilsTest
 */
class ProductSetSyncTest extends AbstractWPUnitTestWithSafeFiltering {

    const FB_PRODUCT_SET_ID = "3720002385";

    const WC_CATEGORY_NAME_1 =  'Test Category 1';
    const WC_CATEGORY_NAME_2 =  'Test Category 2 (with special characters: &^%$#@!~|)';

    const FB_CATALOG_ID = '7891011';

    const SYNC_FLAG = ProductSetSync::SYNC_ALL_FLAG;

    public function setUp(): void {
        parent::setUp();

        // Product sets live in a connected catalog, so a store with an access token and a
        // catalog is the normal state for every case here. The tests that care about the
        // absence of either clear it themselves.
        update_option( \WooCommerce\Facebook\Handlers\Connection::OPTION_ACCESS_TOKEN, 'test-access-token' );
        facebook_for_woocommerce()->get_integration()->update_product_catalog_id( self::FB_CATALOG_ID );

        delete_transient( self::SYNC_FLAG );
    }

    public function tearDown(): void {
        delete_option( \WooCommerce\Facebook\Handlers\Connection::OPTION_ACCESS_TOKEN );
        facebook_for_woocommerce()->get_integration()->update_product_catalog_id( '' );

        delete_transient( self::SYNC_FLAG );

        parent::tearDown();
    }

	/* ------------------ Test Methods ------------------ */

    public function testCreate() {
        $wc_category = $this->createWPCategory();

        $product_set_sync = $this->getMockBuilder( ProductSetSyncTestable::class )
            ->setMethods(['get_fb_product_set_id','create_fb_product_set'])
            ->getMock();

        $product_set_sync->expects( $this->once() )
            ->method( 'get_fb_product_set_id' )
            ->with($wc_category)
            ->willReturn(null);
        $product_set_sync->expects( $this->once() )
            ->method( 'create_fb_product_set' );
        
        $product_set_sync->on_create_or_update_product_wc_category_callback( 
            $wc_category->term_id, 
            $wc_category->term_taxonomy_id, 
            array() 
        );
    }

    public function testUpdate() {
        $wc_category = $this->createWPCategory();

        $product_set_sync = $this->getMockBuilder( ProductSetSyncTestable::class )
            ->setMethods(['get_fb_product_set_id','update_fb_product_set'])
            ->getMock();

        $product_set_sync->expects( $this->once() )
            ->method( 'get_fb_product_set_id' )
            ->with($wc_category)
            ->willReturn(self::FB_PRODUCT_SET_ID);
        $product_set_sync->expects( $this->once() )
            ->method( 'update_fb_product_set' )
            ->with($wc_category, self::FB_PRODUCT_SET_ID);
        
        $product_set_sync->on_create_or_update_product_wc_category_callback( 
            $wc_category->term_id, 
            $wc_category->term_taxonomy_id, 
            array() 
        );
    }

    public function testDelete() {
        $wc_category = $this->createWPCategory();

        $product_set_sync = $this->getMockBuilder( ProductSetSyncTestable::class )
            ->setMethods(['get_fb_product_set_id','delete_fb_product_set'])
            ->getMock();

        $product_set_sync->expects( $this->once() )
            ->method( 'get_fb_product_set_id' )
            ->with($wc_category)
            ->willReturn(self::FB_PRODUCT_SET_ID);
        $product_set_sync->expects( $this->once() )
            ->method( 'delete_fb_product_set' )
            ->with(self::FB_PRODUCT_SET_ID);
        
        $product_set_sync->on_delete_wc_product_category_callback( 
            $wc_category->term_id, 
            $wc_category->term_taxonomy_id, 
            $wc_category,
            array() 
        );
    }


    public function testSyncAllProductSets() {
        $this->createWPCategory( self::WC_CATEGORY_NAME_1 );
        $this->createWPCategory( self::WC_CATEGORY_NAME_2 );

        $product_set_sync = $this->getMockBuilder( ProductSetSyncTestable::class )
            ->setMethods(['get_fb_product_set_id','create_fb_product_set'])
            ->getMock();

        $product_set_sync->expects( $this->atLeast(2) )
            ->method( 'get_fb_product_set_id' )
            ->willReturn(null);
        $product_set_sync->expects( $this->atLeast(2) )
            ->method( 'create_fb_product_set' );
        
        $product_set_sync->sync_all_product_sets();
    }

    /**
     * Regression: onboarding used to reach this with no catalog ID, and every category
     * turned into a Graph request against /{empty}/product_sets that came back 400.
     */
    public function testSyncAllProductSetsDoesNothingWithoutACatalog() {
        facebook_for_woocommerce()->get_integration()->update_product_catalog_id( '' );

        $this->createWPCategory( self::WC_CATEGORY_NAME_1 );

        $product_set_sync = $this->getMockBuilder( ProductSetSyncTestable::class )
            ->setMethods(['get_fb_product_set_id','create_fb_product_set','update_fb_product_set'])
            ->getMock();

        $product_set_sync->expects( $this->never() )->method( 'get_fb_product_set_id' );
        $product_set_sync->expects( $this->never() )->method( 'create_fb_product_set' );
        $product_set_sync->expects( $this->never() )->method( 'update_fb_product_set' );

        $product_set_sync->sync_all_product_sets();
    }

    /**
     * The daily run is rationed by a 24 hour transient. A run that could not do anything must
     * not spend that ration, or a catalog arriving later in the day waits until tomorrow.
     */
    public function testSyncAllProductSetsWithoutACatalogLeavesTheDailyRunAvailable() {
        facebook_for_woocommerce()->get_integration()->update_product_catalog_id( '' );

        $this->createWPCategory( self::WC_CATEGORY_NAME_1 );

        $product_set_sync = $this->getMockBuilder( ProductSetSyncTestable::class )
            ->setMethods(['get_fb_product_set_id','create_fb_product_set'])
            ->getMock();

        $product_set_sync->sync_all_product_sets();

        $this->assertFalse( get_transient( self::SYNC_FLAG ), 'A no-op run should not consume the day.' );

        // With a catalog in place the very next run goes ahead.
        facebook_for_woocommerce()->get_integration()->update_product_catalog_id( self::FB_CATALOG_ID );

        $product_set_sync->expects( $this->atLeastOnce() )
            ->method( 'get_fb_product_set_id' )
            ->willReturn(null);
        $product_set_sync->expects( $this->atLeastOnce() )
            ->method( 'create_fb_product_set' );

        $product_set_sync->sync_all_product_sets();
    }

    /**
     * The heartbeat gets one full pass a day, and the transient is what holds it to that.
     */
    public function testCategoryCallbacksDoNothingWithoutACatalog() {
        facebook_for_woocommerce()->get_integration()->update_product_catalog_id( '' );

        $wc_category = $this->createWPCategory();

        $product_set_sync = $this->getMockBuilder( ProductSetSyncTestable::class )
            ->setMethods(['get_fb_product_set_id','create_fb_product_set','update_fb_product_set','delete_fb_product_set'])
            ->getMock();

        $product_set_sync->expects( $this->never() )->method( 'get_fb_product_set_id' );
        $product_set_sync->expects( $this->never() )->method( 'create_fb_product_set' );
        $product_set_sync->expects( $this->never() )->method( 'update_fb_product_set' );
        $product_set_sync->expects( $this->never() )->method( 'delete_fb_product_set' );

        $product_set_sync->on_create_or_update_product_wc_category_callback(
            $wc_category->term_id,
            $wc_category->term_taxonomy_id,
            array()
        );
        $product_set_sync->on_delete_wc_product_category_callback(
            $wc_category->term_id,
            $wc_category->term_taxonomy_id,
            $wc_category,
            array()
        );
    }

    /**
     * A store that was never connected, was disconnected, or whose install failed closed has no
     * access token. The API client cannot be built without one, so the callbacks used to log an
     * "access token is missing" error on every category change.
     */
    public function testCategoryCallbacksDoNothingWhenDisconnected() {
        delete_option( \WooCommerce\Facebook\Handlers\Connection::OPTION_ACCESS_TOKEN );

        $wc_category = $this->createWPCategory();

        $product_set_sync = $this->getMockBuilder( ProductSetSyncTestable::class )
            ->setMethods(['get_fb_product_set_id','create_fb_product_set','update_fb_product_set','delete_fb_product_set'])
            ->getMock();

        $product_set_sync->expects( $this->never() )->method( 'get_fb_product_set_id' );
        $product_set_sync->expects( $this->never() )->method( 'create_fb_product_set' );
        $product_set_sync->expects( $this->never() )->method( 'update_fb_product_set' );
        $product_set_sync->expects( $this->never() )->method( 'delete_fb_product_set' );

        $product_set_sync->on_create_or_update_product_wc_category_callback(
            $wc_category->term_id,
            $wc_category->term_taxonomy_id,
            array()
        );
        $product_set_sync->on_delete_wc_product_category_callback(
            $wc_category->term_id,
            $wc_category->term_taxonomy_id,
            $wc_category,
            array()
        );
    }

    public function testSyncAllProductSetsDoesNothingWhenDisconnected() {
        delete_option( \WooCommerce\Facebook\Handlers\Connection::OPTION_ACCESS_TOKEN );

        $this->createWPCategory( self::WC_CATEGORY_NAME_1 );

        $product_set_sync = $this->getMockBuilder( ProductSetSyncTestable::class )
            ->setMethods(['get_fb_product_set_id','create_fb_product_set','update_fb_product_set'])
            ->getMock();

        $product_set_sync->expects( $this->never() )->method( 'get_fb_product_set_id' );
        $product_set_sync->expects( $this->never() )->method( 'create_fb_product_set' );
        $product_set_sync->expects( $this->never() )->method( 'update_fb_product_set' );

        $product_set_sync->sync_all_product_sets();

        $this->assertFalse( get_transient( self::SYNC_FLAG ), 'A no-op run should not consume the day.' );
    }

    public function testSyncAllProductSetsSkipsARunTheDayAlreadyHad() {
        set_transient( self::SYNC_FLAG, 'yes', DAY_IN_SECONDS - 1 );

        $this->createWPCategory( self::WC_CATEGORY_NAME_1 );

        $product_set_sync = $this->getMockBuilder( ProductSetSyncTestable::class )
            ->setMethods(['get_fb_product_set_id','create_fb_product_set','update_fb_product_set'])
            ->getMock();

        $product_set_sync->expects( $this->never() )->method( 'get_fb_product_set_id' );
        $product_set_sync->expects( $this->never() )->method( 'create_fb_product_set' );
        $product_set_sync->expects( $this->never() )->method( 'update_fb_product_set' );

        $product_set_sync->sync_all_product_sets();
    }

    /**
     * A queued sync exists because a catalog changed, so it runs even on a day the heartbeat has
     * already used up. Otherwise connecting a second catalog would show no product sets until
     * tomorrow, which is the wait this whole path exists to avoid.
     */
    public function testQueuedSyncAllProductSetsRunsOnADayTheHeartbeatAlreadyUsed() {
        set_transient( self::SYNC_FLAG, 'yes', DAY_IN_SECONDS - 1 );

        $this->createWPCategory( self::WC_CATEGORY_NAME_1 );

        $product_set_sync = $this->getMockBuilder( ProductSetSyncTestable::class )
            ->setMethods(['get_fb_product_set_id','create_fb_product_set'])
            ->getMock();

        $product_set_sync->expects( $this->atLeastOnce() )
            ->method( 'get_fb_product_set_id' )
            ->willReturn(null);
        $product_set_sync->expects( $this->atLeastOnce() )
            ->method( 'create_fb_product_set' );

        $product_set_sync->run_queued_sync_all_product_sets();
    }

    /**
     * Onboarding queues the sync instead of running it, so the categories are walked outside the
     * request that connected the catalog.
     */
    public function testScheduleSyncAllProductSetsQueuesTheSync() {
        if ( ! function_exists( 'as_enqueue_async_action' ) ) {
            $this->markTestSkipped( 'Action Scheduler is not loaded.' );
        }

        as_unschedule_all_actions( ProductSetSync::SYNC_ALL_ACTION );

        $product_set_sync = new ProductSetSyncTestable();
        $product_set_sync->schedule_sync_all_product_sets();

        $this->assertNotFalse(
            as_next_scheduled_action( ProductSetSync::SYNC_ALL_ACTION, array(), ProductSetSync::SYNC_ALL_ACTION_GROUP ),
            'Connecting a catalog should leave a queued sync behind.'
        );

        // The queued action runs the dedicated entry point, which bypasses the daily limit; the
        // heartbeat keeps the routine one, called with no arguments so the limit applies.
        $this->assertNotFalse(
            has_action( ProductSetSync::SYNC_ALL_ACTION, array( $product_set_sync, 'run_queued_sync_all_product_sets' ) )
        );
        $this->assertNotFalse(
            has_action( \WooCommerce\Facebook\Utilities\Heartbeat::DAILY, array( $product_set_sync, 'sync_all_product_sets' ) )
        );

        // A second settings update in the same state must not stack up another pass.
        $product_set_sync->schedule_sync_all_product_sets();

        $this->assertCount(
            1,
            as_get_scheduled_actions(
                array(
                    'hook'   => ProductSetSync::SYNC_ALL_ACTION,
                    'status' => \ActionScheduler_Store::STATUS_PENDING,
                ),
                'ids'
            )
        );

        as_unschedule_all_actions( ProductSetSync::SYNC_ALL_ACTION );
    }

    /**
     * The queued sync runs in an admin-ajax request, where Polylang narrows term queries to the
     * request's language. Every category is mirrored regardless of language, so the query lifts
     * that filter with an empty lang. Without WPML nothing is switched.
     */
    public function testSyncAllProductSetsQueriesCategoriesInEveryLanguage() {
        $this->createWPCategory( self::WC_CATEGORY_NAME_1 );

        $category_queries = array();
        $this->add_filter_with_safe_teardown( 'get_terms_args', function( $args, $taxonomies ) use ( &$category_queries ) {
            if ( in_array( ProductSetSync::WC_PRODUCT_CATEGORY_TAXONOMY, (array) $taxonomies, true ) && 'ID' === ( $args['orderby'] ?? '' ) ) {
                $category_queries[] = $args;
            }
            return $args;
        }, 10, 2 );

        $switches = array();
        $this->add_filter_with_safe_teardown( 'wpml_switch_language', function( $code ) use ( &$switches ) {
            $switches[] = $code;
            return $code;
        } );

        $product_set_sync = $this->getMockBuilder( ProductSetSyncTestable::class )
            ->setMethods(['get_fb_product_set_id','create_fb_product_set'])
            ->getMock();
        $product_set_sync->method( 'get_fb_product_set_id' )->willReturn( null );

        $product_set_sync->sync_all_product_sets();

        $this->assertCount( 1, $category_queries, 'The sync should query the product categories once.' );
        $this->assertSame( '', $category_queries[0]['lang'] ?? null, 'An empty lang lifts the Polylang language filter.' );
        $this->assertSame( array(), $switches, 'Without WPML no language switch happens.' );
    }

    /**
     * On WPML the sync mirrors the default language's categories, as the daily sync in WP-Cron
     * always has. The async runner can run in another language (the dispatching admin's), so the
     * query is pinned to the default language and the request language restored afterwards.
     */
    public function testSyncAllProductSetsPinsTheWpmlDefaultLanguageForTheQuery() {
        $this->createWPCategory( self::WC_CATEGORY_NAME_1 );

        // Stand in for WPML: the request runs in Croatian on a store whose default is English.
        $this->add_filter_with_safe_teardown( 'wpml_current_language', function() {
            return 'hr';
        } );
        $this->add_filter_with_safe_teardown( 'wpml_default_language', function() {
            return 'en';
        } );
        $switches = array();
        $this->add_filter_with_safe_teardown( 'wpml_switch_language', function( $code ) use ( &$switches ) {
            $switches[] = $code;
            return $code;
        } );

        $product_set_sync = $this->getMockBuilder( ProductSetSyncTestable::class )
            ->setMethods(['get_fb_product_set_id','create_fb_product_set'])
            ->getMock();
        $product_set_sync->method( 'get_fb_product_set_id' )->willReturn( null );

        $product_set_sync->sync_all_product_sets();

        $this->assertSame( array( 'en', 'hr' ), $switches, 'The query runs in the default language and the request language is restored.' );
    }

    /**
     * When the request already runs in WPML's default language nothing is switched, so the
     * common case (WP-Cron, WP-CLI) has no side effects.
     */
    public function testSyncAllProductSetsDoesNotSwitchWpmlWhenAlreadyOnTheDefaultLanguage() {
        $this->createWPCategory( self::WC_CATEGORY_NAME_1 );

        $this->add_filter_with_safe_teardown( 'wpml_current_language', function() {
            return 'en';
        } );
        $this->add_filter_with_safe_teardown( 'wpml_default_language', function() {
            return 'en';
        } );
        $switches = array();
        $this->add_filter_with_safe_teardown( 'wpml_switch_language', function( $code ) use ( &$switches ) {
            $switches[] = $code;
            return $code;
        } );

        $product_set_sync = $this->getMockBuilder( ProductSetSyncTestable::class )
            ->setMethods(['get_fb_product_set_id','create_fb_product_set'])
            ->getMock();
        $product_set_sync->method( 'get_fb_product_set_id' )->willReturn( null );

        $product_set_sync->sync_all_product_sets();

        $this->assertSame( array(), $switches, 'Already on the default language: no switch.' );
    }

    public function testProductSetData() {
        $wc_category = $this->createWPCategory();

        $product_set_sync = $this->getMockBuilder( ProductSetSyncTestable::class )
            ->setMethods(['get_fb_product_set_id','create_fb_product_set'])
            ->getMock();
        
        $data = $product_set_sync->build_fb_product_set_data( $wc_category );
        $this->assertEquals( self::WC_CATEGORY_NAME_1, $data['name'] );
        $this->assertEquals( $wc_category->term_taxonomy_id, $data['retailer_id'] );
        $this->assertEquals('{"and":[{"product_type":{"i_contains":"Test Category 1"}}]}', $data['filter'] );
        $this->assertEquals( '{"description":"This is a test category","external_url":"http:\/\/example.org\/?product_cat=test-category"}', $data['metadata'] );
    }

    /* ------------------ Utils Methods ------------------ */

    private function createWPCategory( $name = self::WC_CATEGORY_NAME_1 ) {
        $wc_category = wp_insert_term(
            $name,
            'product_cat', // taxonomy
            array(
                'description' => 'This is a test category',
                'slug' => 'test-category',
            )
        );

        return get_term( $wc_category['term_id'], ProductSetSync::WC_PRODUCT_CATEGORY_TAXONOMY );
    }
}

/**
 * A test-specific subclass of ProductSetSync to expose private methods for mocking.
 */
class ProductSetSyncTestable extends ProductSetSync {

    public function get_fb_product_set_id( $wc_category ) {
        return parent::get_fb_product_set_id( $wc_category );
    }

    public function create_fb_product_set( $wc_category ) {
        return parent::create_fb_product_set( $wc_category );
    }

    public function update_fb_product_set( $wc_category, $fb_product_set_id ) {
        return parent::update_fb_product_set( $wc_category, $fb_product_set_id );
    }

    public function delete_fb_product_set( $fb_product_set_id ) {
        return parent::delete_fb_product_set( $fb_product_set_id );
    }

    public function build_fb_product_set_data( $wc_category ) {
        return parent::build_fb_product_set_data( $wc_category );
    }
}
