<?php
/** Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */

require_once __DIR__ . '/../../../includes/ProductSets/ProductSetSync.php';

use WP_UnitTestCase;
use WooCommerce\Facebook\ProductSets\ProductSetSync;
use WooCommerce\Facebook\ProductSets\ProductSetSource;

/**
 * Class FeedUploadUtilsTest
 */
class ProductSetSyncTest extends WP_UnitTestCase {

    const FB_PRODUCT_SET_ID = "3720002385";

    const WC_CATEGORY_NAME_1 =  'Test Category 1';
    const WC_CATEGORY_NAME_2 =  'Test Category 2';

	/* ------------------ Test Methods ------------------ */

	public function provide_test_data() {
		return [
			[
				ProductSetSync::WC_PRODUCT_CATEGORY_TAXONOMY
			],
			[
				ProductSetSync::WC_PRODUCT_TAG_TAXONOMY
			]
		];
	}

	/**
	 * @dataProvider provide_test_data
	 */
    public function testCreate( $wc_taxonomy ) {
        $wc_term = $this->createWCTerm( $wc_taxonomy );

        $product_set_sync = $this->getMockBuilder( ProductSetSyncTestable::class )
            ->setMethods(['is_sync_enabled', 'get_fb_product_set_id','create_fb_product_set'])
            ->getMock();

		$product_set_sync->expects( $this->once() )
            ->method( 'is_sync_enabled' )
            ->willReturn(true);
        $product_set_sync->expects( $this->once() )
            ->method( 'get_fb_product_set_id' )
            ->with( $wc_term )
            ->willReturn(null);
        $product_set_sync->expects( $this->once() )
            ->method( 'create_fb_product_set' );
        
		if ( ProductSetSync::WC_PRODUCT_CATEGORY_TAXONOMY === $wc_taxonomy ) {
			$product_set_sync->on_create_or_update_product_wc_category_callback( 
				$wc_term->term_id,
				$wc_term->term_taxonomy_id,
				array() 
			);
		} else {
			$product_set_sync->on_create_or_update_wc_product_tag_callback( 
				$wc_term->term_id,
				$wc_term->term_taxonomy_id,
				array() 
			);
		}
    }

	/**
	 * @dataProvider provide_test_data
	 */
    public function testUpdate( $wc_taxonomy ) {
        $wc_term = $this->createWCTerm( $wc_taxonomy );

        $product_set_sync = $this->getMockBuilder( ProductSetSyncTestable::class )
            ->setMethods(['is_sync_enabled', 'get_fb_product_set_id','update_fb_product_set'])
            ->getMock();

		$product_set_sync->expects( $this->once() )
            ->method( 'is_sync_enabled' )
            ->willReturn(true);        
        $product_set_sync->expects( $this->once() )
            ->method( 'get_fb_product_set_id' )
            ->with($wc_term)
            ->willReturn(self::FB_PRODUCT_SET_ID);
        $product_set_sync->expects( $this->once() )
            ->method( 'update_fb_product_set' )
            ->with($wc_term, self::FB_PRODUCT_SET_ID);
		
		if ( ProductSetSync::WC_PRODUCT_CATEGORY_TAXONOMY === $wc_taxonomy ) {
			$product_set_sync->on_create_or_update_product_wc_category_callback( 
				$wc_term->term_id, 
				$wc_term->term_taxonomy_id, 
				array() 
			);
		} else {
			$product_set_sync->on_create_or_update_wc_product_tag_callback( 
				$wc_term->term_id,
				$wc_term->term_taxonomy_id,
				array() 
			);
		}
    }

	/**
	 * @dataProvider provide_test_data
	 */
    public function testDelete( $wc_taxonomy ) {
        $wc_term = $this->createWCTerm( $wc_taxonomy );

        $product_set_sync = $this->getMockBuilder( ProductSetSyncTestable::class )
            ->setMethods(['is_sync_enabled', 'get_fb_product_set_id','delete_fb_product_set'])
            ->getMock();

		$product_set_sync->expects( $this->once() )
            ->method( 'is_sync_enabled' )
            ->willReturn(true);
        $product_set_sync->expects( $this->once() )
            ->method( 'get_fb_product_set_id' )
            ->with($wc_term)
            ->willReturn(self::FB_PRODUCT_SET_ID);
        $product_set_sync->expects( $this->once() )
            ->method( 'delete_fb_product_set' )
            ->with(self::FB_PRODUCT_SET_ID);
        
		if ( ProductSetSync::WC_PRODUCT_CATEGORY_TAXONOMY === $wc_taxonomy ) {
			$product_set_sync->on_delete_wc_product_category_callback( 
				$wc_term->term_id, 
				$wc_term->term_taxonomy_id, 
				$wc_term,
				array() 
			);
		} else {
            $product_set_sync->on_delete_wc_product_tag_callback( 
				$wc_term->term_id, 
				$wc_term->term_taxonomy_id, 
				$wc_term,
				array() 
			);
		}
    }

    /**
	 * @dataProvider provide_test_data
	 */
    public function testSyncDisabled( $wc_taxonomy ) {
        $wc_term = $this->createWCTerm( $wc_taxonomy );

        $product_set_sync = $this->getMockBuilder( ProductSetSyncTestable::class )
            ->setMethods(['is_sync_enabled', 'get_fb_product_set_id','create_fb_product_set'])
            ->getMock();

		$product_set_sync->expects( $this->once() )
            ->method( 'is_sync_enabled' )
            ->willReturn(false);
        $product_set_sync->expects( $this->never() )
            ->method( 'get_fb_product_set_id' );
        $product_set_sync->expects( $this->never() )
            ->method( 'create_fb_product_set' );
        
        $product_set_sync->on_create_or_update_product_wc_category_callback( 
            $wc_term->term_id, 
            $wc_term->term_taxonomy_id, 
            array() 
        );
    }

    public function testSyncAllProductSets() {
        $this->createWCTerm( ProductSetSync::WC_PRODUCT_CATEGORY_TAXONOMY, self::WC_CATEGORY_NAME_1 );
        $this->createWCTerm( ProductSetSync::WC_PRODUCT_CATEGORY_TAXONOMY, self::WC_CATEGORY_NAME_2 );
        $this->createWCTerm( ProductSetSync::WC_PRODUCT_TAG_TAXONOMY, self::WC_CATEGORY_NAME_1 );
        $this->createWCTerm( ProductSetSync::WC_PRODUCT_TAG_TAXONOMY, self::WC_CATEGORY_NAME_2 );

        $product_set_sync = $this->getMockBuilder( ProductSetSyncTestable::class )
            ->setMethods(['is_sync_enabled', 'get_fb_product_set_id','create_fb_product_set'])
            ->getMock();

		$product_set_sync->expects( $this->exactly(1) )
            ->method( 'is_sync_enabled' )
            ->willReturn(true);
        $product_set_sync->expects( $this->atLeast(4) )
            ->method( 'get_fb_product_set_id' )
            ->willReturn(null);
        $product_set_sync->expects( $this->atLeast(4) )
            ->method( 'create_fb_product_set' );
        
        $product_set_sync->sync_all_product_sets();
    }

    /**
	 * @dataProvider provide_test_data
	 */
    public function testProductSetData( $wc_taxonomy ) {
		$wc_term = $this->createWCTerm( $wc_taxonomy );

        $product_set_sync = $this->getMockBuilder( ProductSetSyncTestable::class )
            ->setMethods(['is_sync_enabled', 'get_fb_product_set_id','create_fb_product_set'])
            ->getMock();
        
        $data = $product_set_sync->build_fb_product_set_data( $wc_term, $wc_taxonomy );

        $this->assertEquals( self::WC_CATEGORY_NAME_1, $data['name'] );
        $this->assertEquals( $wc_term->term_taxonomy_id, $data['retailer_id'] );
        
		if ( ProductSetSync::WC_PRODUCT_CATEGORY_TAXONOMY === $wc_taxonomy ) {
            $this->assertEquals('{"and":[{"product_type":{"i_contains":"Test Category 1"}}]}', $data['filter'] );
            $this->assertEquals( '{"description":"<p>This is a test category<\/p>\n","external_url":"http:\/\/example.org\/?product_cat=test-category"}', $data['metadata'] );
        } else {
            $this->assertEquals('{"and":[{"tags":{"eq":"wc_tag_id_'.$wc_term->term_taxonomy_id.'"}}]}', $data['filter'] );
            $this->assertEquals( '{"description":"<p>This is a test category<\/p>\n","external_url":"http:\/\/example.org\/?product_tag=test-category"}', $data['metadata'] );
        }
    }

    /* ------------------ Utils Methods ------------------ */

    private function createWCTerm( $wc_taxonomy, $name = self::WC_CATEGORY_NAME_1 ) {

        $wc_term = wp_insert_term(
            $name,
            $wc_taxonomy,
            array(
                'description' => 'This is a test category',
                'slug' => 'test-category',
            )
        );

        return get_term( $wc_term['term_id'], $wc_taxonomy );
    }
}

/**
 * A test-specific subclass of ProductSetSync to expose private methods for mocking.
 */
class ProductSetSyncTestable extends ProductSetSync {
    
    public function is_sync_enabled() {
        return parent::is_sync_enabled();
    }

    public function get_fb_product_set_id( $wc_category ) {
        return parent::get_fb_product_set_id( $wc_category );
    }

    public function create_fb_product_set( $wc_term, $wc_taxonomy ) {
        return parent::create_fb_product_set( $wc_term, $wc_taxonomy );
    }

    public function update_fb_product_set( $wc_term, $fb_product_set_id, $wc_taxonomy ) {
        return parent::update_fb_product_set( $wc_term, $fb_product_set_id, $wc_taxonomy );
    }

    public function delete_fb_product_set( $fb_product_set_id ) {
        return parent::delete_fb_product_set( $fb_product_set_id );
    }

    public function build_fb_product_set_data( $wc_term, $wc_taxonomy ) {
        return parent::build_fb_product_set_data( $wc_term, $wc_taxonomy );
    }
}
