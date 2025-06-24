<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Unit\Products;

use WooCommerce\Facebook\Products\GoogleProductTaxonomy;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;

/**
 * Unit tests for GoogleProductTaxonomy class.
 *
 * @since 3.5.2
 */
class GoogleProductTaxonomyTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

	/**
	 * Test that the class exists and can be instantiated.
	 */
	public function test_class_exists() {
		$this->assertTrue( class_exists( GoogleProductTaxonomy::class ) );
		$taxonomy = new GoogleProductTaxonomy();
		$this->assertInstanceOf( GoogleProductTaxonomy::class, $taxonomy );
	}

	/**
	 * Test that TAXONOMY constant is defined and is an array.
	 */
	public function test_taxonomy_constant_exists() {
		$this->assertTrue( defined( 'WooCommerce\Facebook\Products\GoogleProductTaxonomy::TAXONOMY' ) );
		$this->assertIsArray( GoogleProductTaxonomy::TAXONOMY );
		$this->assertNotEmpty( GoogleProductTaxonomy::TAXONOMY );
	}

	/**
	 * Test taxonomy structure - each entry should have required fields.
	 */
	public function test_taxonomy_structure() {
		$taxonomy = GoogleProductTaxonomy::TAXONOMY;
		
		foreach ( $taxonomy as $id => $category ) {
			// ID should be numeric
			$this->assertIsInt( $id, "Category ID {$id} should be numeric" );
			$this->assertGreaterThan( 0, $id, "Category ID should be positive" );
			
			// Each category should be an array
			$this->assertIsArray( $category, "Category {$id} should be an array" );
			
			// Required fields
			$this->assertArrayHasKey( 'label', $category, "Category {$id} missing 'label' field" );
			$this->assertArrayHasKey( 'options', $category, "Category {$id} missing 'options' field" );
			$this->assertArrayHasKey( 'parent', $category, "Category {$id} missing 'parent' field" );
			
			// Label should be a non-empty string
			$this->assertIsString( $category['label'], "Category {$id} label should be a string" );
			$this->assertNotEmpty( $category['label'], "Category {$id} label should not be empty" );
			
			// Options should be an array
			$this->assertIsArray( $category['options'], "Category {$id} options should be an array" );
			
			// Parent should be a string (can be empty for root categories)
			$this->assertIsString( $category['parent'], "Category {$id} parent should be a string" );
		}
	}

	/**
	 * Test that root categories have empty parent.
	 */
	public function test_root_categories_have_empty_parent() {
		$taxonomy = GoogleProductTaxonomy::TAXONOMY;
		$root_categories = array();
		
		foreach ( $taxonomy as $id => $category ) {
			if ( $category['parent'] === '' ) {
				$root_categories[] = $id;
			}
		}
		
		// Should have at least one root category
		$this->assertNotEmpty( $root_categories, 'Should have at least one root category' );
		
		// Common root categories we expect to see
		$expected_roots = array( 1, 166, 537 ); // Animals & Pet Supplies, Apparel & Accessories, Baby & Toddler
		foreach ( $expected_roots as $expected_id ) {
			if ( isset( $taxonomy[ $expected_id ] ) ) {
				$this->assertContains( $expected_id, $root_categories, "Category {$expected_id} should be a root category" );
			}
		}
	}

	/**
	 * Test parent-child relationships are valid.
	 */
	public function test_parent_child_relationships() {
		$taxonomy = GoogleProductTaxonomy::TAXONOMY;
		
		foreach ( $taxonomy as $id => $category ) {
			if ( $category['parent'] !== '' ) {
				// Parent should be a valid ID in the taxonomy
				$parent_id = (int) $category['parent'];
				$this->assertArrayHasKey( 
					$parent_id, 
					$taxonomy, 
					"Category {$id} references non-existent parent {$parent_id}" 
				);
				
				// Child should be in parent's options
				if ( isset( $taxonomy[ $parent_id ] ) ) {
					$this->assertArrayHasKey(
						$id,
						$taxonomy[ $parent_id ]['options'],
						"Category {$id} not found in parent {$parent_id} options"
					);
				}
			}
		}
	}

	/**
	 * Test that options reference valid categories.
	 */
	public function test_options_reference_valid_categories() {
		$taxonomy = GoogleProductTaxonomy::TAXONOMY;
		
		foreach ( $taxonomy as $id => $category ) {
			foreach ( $category['options'] as $option_id => $option_label ) {
				// Option ID should be numeric
				$this->assertIsInt( $option_id, "Option ID {$option_id} in category {$id} should be numeric" );
				
				// Option should exist in taxonomy
				$this->assertArrayHasKey( 
					$option_id, 
					$taxonomy, 
					"Option {$option_id} in category {$id} not found in taxonomy" 
				);
				
				// Option label should match the referenced category label
				$this->assertEquals(
					$taxonomy[ $option_id ]['label'],
					$option_label,
					"Option label mismatch for ID {$option_id} in category {$id}"
				);
			}
		}
	}

	/**
	 * Test specific known categories exist.
	 */
	public function test_known_categories_exist() {
		$taxonomy = GoogleProductTaxonomy::TAXONOMY;
		
		// Test some well-known categories
		$known_categories = array(
			1 => 'Animals & Pet Supplies',
			166 => 'Apparel & Accessories',
			537 => 'Baby & Toddler',
			1604 => 'Clothing',
			187 => 'Shoes',
			188 => 'Jewelry',
		);
		
		foreach ( $known_categories as $id => $expected_label ) {
			$this->assertArrayHasKey( $id, $taxonomy, "Category {$id} ({$expected_label}) should exist" );
			if ( isset( $taxonomy[ $id ] ) ) {
				$this->assertEquals( 
					$expected_label, 
					$taxonomy[ $id ]['label'],
					"Category {$id} label mismatch"
				);
			}
		}
	}

	/**
	 * Test that there are no duplicate IDs.
	 */
	public function test_no_duplicate_ids() {
		$taxonomy = GoogleProductTaxonomy::TAXONOMY;
		$ids = array_keys( $taxonomy );
		$unique_ids = array_unique( $ids );
		
		$this->assertCount( 
			count( $ids ), 
			$unique_ids, 
			'Taxonomy should not have duplicate IDs' 
		);
	}

	/**
	 * Test that labels contain valid characters.
	 */
	public function test_labels_contain_valid_characters() {
		$taxonomy = GoogleProductTaxonomy::TAXONOMY;
		
		foreach ( $taxonomy as $id => $category ) {
			// Labels should not contain HTML
			$this->assertEquals(
				$category['label'],
				strip_tags( $category['label'] ),
				"Category {$id} label should not contain HTML"
			);
			
			// Labels should be properly trimmed
			$this->assertEquals(
				$category['label'],
				trim( $category['label'] ),
				"Category {$id} label should be trimmed"
			);
			
			// Check for reasonable length
			$this->assertLessThan(
				200,
				strlen( $category['label'] ),
				"Category {$id} label seems too long"
			);
		}
	}

	/**
	 * Test category depth (no infinite loops).
	 */
	public function test_category_depth() {
		$taxonomy = GoogleProductTaxonomy::TAXONOMY;
		$max_depth = 10; // Reasonable maximum depth
		
		foreach ( $taxonomy as $id => $category ) {
			$depth = 0;
			$current_id = $id;
			$visited = array();
			
			while ( isset( $taxonomy[ $current_id ] ) && $taxonomy[ $current_id ]['parent'] !== '' ) {
				// Check for circular reference
				$this->assertNotContains( 
					$current_id, 
					$visited, 
					"Circular reference detected for category {$id}" 
				);
				
				$visited[] = $current_id;
				$current_id = (int) $taxonomy[ $current_id ]['parent'];
				$depth++;
				
				// Check maximum depth
				$this->assertLessThan( 
					$max_depth, 
					$depth, 
					"Category {$id} exceeds maximum depth of {$max_depth}" 
				);
			}
		}
	}

	/**
	 * Test that the taxonomy has a reasonable number of categories.
	 */
	public function test_taxonomy_size() {
		$taxonomy = GoogleProductTaxonomy::TAXONOMY;
		$count = count( $taxonomy );
		
		// Google Product Taxonomy typically has thousands of categories
		$this->assertGreaterThan( 1000, $count, 'Taxonomy should have at least 1000 categories' );
		$this->assertLessThan( 10000, $count, 'Taxonomy seems too large (over 10000 categories)' );
	}

	/**
	 * Test specific category hierarchies.
	 */
	public function test_specific_hierarchies() {
		$taxonomy = GoogleProductTaxonomy::TAXONOMY;
		
		// Test: Pet Supplies -> Cat Supplies -> Cat Food
		if ( isset( $taxonomy[2], $taxonomy[4], $taxonomy[3367] ) ) {
			// Pet Supplies (2) should be under Animals & Pet Supplies (1)
			$this->assertEquals( '1', $taxonomy[2]['parent'] );
			
			// Cat Supplies (4) should be under Pet Supplies (2)
			$this->assertEquals( '2', $taxonomy[4]['parent'] );
			
			// Cat Food (3367) should be under Cat Supplies (4)
			$this->assertEquals( '4', $taxonomy[3367]['parent'] );
		}
		
		// Test: Apparel & Accessories -> Clothing -> Dresses
		if ( isset( $taxonomy[166], $taxonomy[1604], $taxonomy[2271] ) ) {
			// Clothing (1604) should be under Apparel & Accessories (166)
			$this->assertEquals( '166', $taxonomy[1604]['parent'] );
			
			// Dresses (2271) should be under Clothing (1604)
			$this->assertEquals( '1604', $taxonomy[2271]['parent'] );
		}
	}

	/**
	 * Test that leaf categories have empty options.
	 */
	public function test_leaf_categories_have_empty_options() {
		$taxonomy = GoogleProductTaxonomy::TAXONOMY;
		$leaf_count = 0;
		$non_leaf_count = 0;
		
		foreach ( $taxonomy as $id => $category ) {
			if ( empty( $category['options'] ) ) {
				$leaf_count++;
			} else {
				$non_leaf_count++;
			}
		}
		
		// Should have both leaf and non-leaf categories
		$this->assertGreaterThan( 0, $leaf_count, 'Should have leaf categories' );
		$this->assertGreaterThan( 0, $non_leaf_count, 'Should have non-leaf categories' );
		
		// Most categories should be leaf categories
		$this->assertGreaterThan( $non_leaf_count, $leaf_count, 'Most categories should be leaf categories' );
	}

	/**
	 * Test encoding of special characters in labels.
	 */
	public function test_special_characters_in_labels() {
		$taxonomy = GoogleProductTaxonomy::TAXONOMY;
		
		// Look for categories with special characters
		$special_char_categories = array();
		foreach ( $taxonomy as $id => $category ) {
			if ( strpos( $category['label'], '&' ) !== false ||
			     strpos( $category['label'], '\'' ) !== false ||
			     strpos( $category['label'], '"' ) !== false ) {
				$special_char_categories[ $id ] = $category['label'];
			}
		}
		
		// If we have categories with special characters, verify they're properly encoded
		if ( ! empty( $special_char_categories ) ) {
			foreach ( $special_char_categories as $id => $label ) {
				// Check for common patterns
				if ( strpos( $label, '&' ) !== false ) {
					// & should be properly used (e.g., "Arts & Crafts")
					$this->assertMatchesRegularExpression( '/\s&\s/', $label, "Category {$id} should have properly spaced ampersand" );
				}
			}
		}
	}
} 