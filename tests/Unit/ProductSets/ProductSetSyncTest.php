<?php
/** Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
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

	/* ------------------ Test Methods ------------------ */

    public function testCreate() {
        $wc_category = $this->createWPCategory();

        $product_set_sync = $this->getMockBuilder( ProductSetSyncTestable::class )
            ->setMethods(['get_fb_product_set_id','create_fb_product_set','sync_products_in_category'])
            ->getMock();

        $product_set_sync->expects( $this->once() )
            ->method( 'get_fb_product_set_id' )
            ->with($wc_category)
            ->willReturn(null);
        $product_set_sync->expects( $this->once() )
            ->method( 'create_fb_product_set' );
        $product_set_sync->expects( $this->once() )
            ->method( 'sync_products_in_category' )
            ->with($wc_category);
        
        $product_set_sync->on_create_or_update_product_wc_category_callback( 
            $wc_category->term_id, 
            $wc_category->term_taxonomy_id, 
            array() 
        );
    }

    public function testUpdate() {
        $wc_category = $this->createWPCategory();

        $product_set_sync = $this->getMockBuilder( ProductSetSyncTestable::class )
            ->setMethods(['get_fb_product_set_id','update_fb_product_set','sync_products_in_category'])
            ->getMock();

        $product_set_sync->expects( $this->once() )
            ->method( 'get_fb_product_set_id' )
            ->with($wc_category)
            ->willReturn(self::FB_PRODUCT_SET_ID);
        $product_set_sync->expects( $this->once() )
            ->method( 'update_fb_product_set' )
            ->with($wc_category, self::FB_PRODUCT_SET_ID);
        $product_set_sync->expects( $this->once() )
            ->method( 'sync_products_in_category' )
            ->with($wc_category);
        
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

    public function sync_products_in_category( $wc_category ) {
        return parent::sync_products_in_category( $wc_category );
    }
}
