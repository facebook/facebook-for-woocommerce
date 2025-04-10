<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests;

use ReflectionClass;

/**
 * Tests for SafelyUpdateOptionsTestTrait.
 *
 * Verifies that the trait correctly records the original state of WordPress options
 * it modifies and that the @after hook restores them (implicitly tested by test isolation).
 *
 * @package WooCommerce\Facebook\Tests
 * @covers \WooCommerce\Facebook\Tests\SafelyUpdateOptionsTestTrait
 */
class SafelyUpdateOptionsTestTraitTest extends AbstractWPUnitTestWithSafeFiltering {

	use SafelyUpdateOptionsTestTrait;

	private const TEST_OPTION_KEY_1 = 'test_safe_option_trait_1';
	private const TEST_OPTION_KEY_2 = 'test_safe_option_trait_2';
	private const TEST_OPTION_VALUE_ORIGINAL = 'original_trait_test_value';
	private const TEST_OPTION_VALUE_NEW = 'new_trait_test_value';

	/**
	 * Ensure options are clean before each test method in this class.
	 * This runs *after* the trait's @before method but before the test method.
	 */
	public function setUp(): void {
		parent::setUp(); // Call parent setup.
		// Explicitly delete options used in this test class to ensure a clean slate,
		// regardless of the trait's state or potential previous test failures.
		delete_option( self::TEST_OPTION_KEY_1 );
		delete_option( self::TEST_OPTION_KEY_2 );
	}

	/**
	 * Clean up options after each test method in this class.
	 * This runs *after* the test method but *before* the trait's @after method.
	 * Helps ensure the environment is clean for the *next* test's setUp.
	 */
	public function tearDown(): void {
		// Explicitly delete options used by this test class.
		delete_option( self::TEST_OPTION_KEY_1 );
		delete_option( self::TEST_OPTION_KEY_2 );
		parent::tearDown(); // Call parent teardown.
	}

	/**
	 * Helper method to get the recorded original options using reflection.
	 *
	 * @return array<string, mixed>
	 * @throws \ReflectionException
	 */
	private function get_recorded_original_options(): array {
		$reflection = new ReflectionClass( $this );
		$originalOptionsProp = $reflection->getProperty('original_options');
		// No need for setAccessible(true) if the test class uses the trait.
		// $originalOptionsProp->setAccessible(true);
		return $originalOptionsProp->getValue( $this );
	}

	/**
	 * @test
	 * Verify setting a previously non-existent option records its non-existence.
	 */
	public function it_should_record_non_existence_when_setting_new_option(): void {
		$this->assertFalse( get_option( self::TEST_OPTION_KEY_1 ), 'Pre-condition: Option should not exist.' );

		$result = $this->set_option_safely_only_for_this_test( self::TEST_OPTION_KEY_1, self::TEST_OPTION_VALUE_NEW );

		$this->assertTrue( $result, 'update_option should return true.' );
		$this->assertEquals( self::TEST_OPTION_VALUE_NEW, get_option( self::TEST_OPTION_KEY_1 ), 'Option should have the new value during the test.' );

		$recorded_options = $this->get_recorded_original_options();
		$this->assertArrayHasKey( self::TEST_OPTION_KEY_1, $recorded_options, 'Trait should have recorded the option key.' );
		$this->assertEquals( '__OPTION_DOES_NOT_EXIST__', $recorded_options[ self::TEST_OPTION_KEY_1 ], 'Trait should record that the option did not exist.' );
		// Trait's @after hook will run and delete this option.
	}

	/**
	 * @test
	 * Verify updating an existing option records its original value.
	 */
	public function it_should_record_original_value_when_updating_existing_option(): void {
		update_option( self::TEST_OPTION_KEY_1, self::TEST_OPTION_VALUE_ORIGINAL );
		$this->assertEquals( self::TEST_OPTION_VALUE_ORIGINAL, get_option( self::TEST_OPTION_KEY_1 ), 'Pre-condition: Option should have original value.' );

		$result = $this->set_option_safely_only_for_this_test( self::TEST_OPTION_KEY_1, self::TEST_OPTION_VALUE_NEW );

		$this->assertTrue( $result, 'update_option should return true.' );
		$this->assertEquals( self::TEST_OPTION_VALUE_NEW, get_option( self::TEST_OPTION_KEY_1 ), 'Option should have the new value during the test.' );

		$recorded_options = $this->get_recorded_original_options();
		$this->assertArrayHasKey( self::TEST_OPTION_KEY_1, $recorded_options, 'Trait should have recorded the option key.' );
		$this->assertEquals( self::TEST_OPTION_VALUE_ORIGINAL, $recorded_options[ self::TEST_OPTION_KEY_1 ], 'Trait should record the original value.' );
		// Trait's @after hook will run and restore the original value.
	}

	/**
	 * @test
	 * Verify deleting an existing option records its original value.
	 */
	public function it_should_record_original_value_when_deleting_existing_option(): void {
		update_option( self::TEST_OPTION_KEY_1, self::TEST_OPTION_VALUE_ORIGINAL );
		$this->assertEquals( self::TEST_OPTION_VALUE_ORIGINAL, get_option( self::TEST_OPTION_KEY_1 ), 'Pre-condition: Option should have original value.' );

		$result = $this->remove_option_safely_only_for_this_test( self::TEST_OPTION_KEY_1 );

		$this->assertTrue( $result, 'delete_option should return true for existing option.' );
		$this->assertFalse( get_option( self::TEST_OPTION_KEY_1 ), 'Option should be deleted during the test.' );

		$recorded_options = $this->get_recorded_original_options();
		$this->assertArrayHasKey( self::TEST_OPTION_KEY_1, $recorded_options, 'Trait should have recorded the option key.' );
		$this->assertEquals( self::TEST_OPTION_VALUE_ORIGINAL, $recorded_options[ self::TEST_OPTION_KEY_1 ], 'Trait should record the original value before deletion.' );
		// Trait's @after hook will run and restore the original value.
	}

	/**
	 * @test
	 * Verify "deleting" a non-existent option records its non-existence.
	 */
	public function it_should_record_non_existence_when_deleting_non_existent_option(): void {
		$this->assertFalse( get_option( self::TEST_OPTION_KEY_1 ), 'Pre-condition: Option should not exist.' );

		$result = $this->remove_option_safely_only_for_this_test( self::TEST_OPTION_KEY_1 );

		// delete_option returns false if the key doesn't exist.
		$this->assertFalse( $result, 'delete_option should return false for non-existent option.' );
		$this->assertFalse( get_option( self::TEST_OPTION_KEY_1 ), 'Option should remain non-existent during the test.' );

		$recorded_options = $this->get_recorded_original_options();
		$this->assertArrayHasKey( self::TEST_OPTION_KEY_1, $recorded_options, 'Trait should have recorded the option key.' );
		$this->assertEquals( '__OPTION_DOES_NOT_EXIST__', $recorded_options[ self::TEST_OPTION_KEY_1 ], 'Trait should record that the option did not exist.' );
		// Trait's @after hook will run and try to delete the option (no-op).
	}

	/**
	 * @test
	 * Verify multiple safe operations within a single test are recorded correctly.
	 */
	public function it_should_record_multiple_changes_correctly(): void {
		// Initial state: key1 exists, key2 does not.
		update_option( self::TEST_OPTION_KEY_1, self::TEST_OPTION_VALUE_ORIGINAL );
		$this->assertEquals( self::TEST_OPTION_VALUE_ORIGINAL, get_option( self::TEST_OPTION_KEY_1 ), 'Pre-condition 1 failed.' );
		$this->assertFalse( get_option( self::TEST_OPTION_KEY_2 ), 'Pre-condition 2 failed.' );

		// Perform multiple safe operations.
		$this->set_option_safely_only_for_this_test( self::TEST_OPTION_KEY_1, self::TEST_OPTION_VALUE_NEW ); // Update existing.
		$this->set_option_safely_only_for_this_test( self::TEST_OPTION_KEY_2, self::TEST_OPTION_VALUE_NEW ); // Set new.

		// Check values during test.
		$this->assertEquals( self::TEST_OPTION_VALUE_NEW, get_option( self::TEST_OPTION_KEY_1 ), 'Option 1 should be updated.' );
		$this->assertEquals( self::TEST_OPTION_VALUE_NEW, get_option( self::TEST_OPTION_KEY_2 ), 'Option 2 should be set.' );

		// Check recorded original values.
		$recorded_options = $this->get_recorded_original_options();
		$this->assertCount( 2, $recorded_options, 'Should have recorded two options.' );
		$this->assertArrayHasKey( self::TEST_OPTION_KEY_1, $recorded_options );
		$this->assertEquals( self::TEST_OPTION_VALUE_ORIGINAL, $recorded_options[ self::TEST_OPTION_KEY_1 ], 'Original value for key 1 should be recorded.' );
		$this->assertArrayHasKey( self::TEST_OPTION_KEY_2, $recorded_options );
		$this->assertEquals( '__OPTION_DOES_NOT_EXIST__', $recorded_options[ self::TEST_OPTION_KEY_2 ], 'Non-existence for key 2 should be recorded.' );
		// Trait's @after hook will restore key1 and delete key2.
	}

	/**
	 * @test
	 * Verify that calling set multiple times only records the *first* original value.
	 */
	public function it_should_only_record_the_very_first_value_when_setting_multiple_times(): void {
		update_option( self::TEST_OPTION_KEY_1, self::TEST_OPTION_VALUE_ORIGINAL );

		// Set multiple times.
		$this->set_option_safely_only_for_this_test( self::TEST_OPTION_KEY_1, 'intermediate_value' );
		$this->set_option_safely_only_for_this_test( self::TEST_OPTION_KEY_1, self::TEST_OPTION_VALUE_NEW );

		$this->assertEquals( self::TEST_OPTION_VALUE_NEW, get_option( self::TEST_OPTION_KEY_1 ), 'Option should have the final value set.' );

		$recorded_options = $this->get_recorded_original_options();
		$this->assertArrayHasKey( self::TEST_OPTION_KEY_1, $recorded_options );
		$this->assertEquals( self::TEST_OPTION_VALUE_ORIGINAL, $recorded_options[ self::TEST_OPTION_KEY_1 ], 'Trait should record the very first original value, not intermediate ones.' );
		// Trait's @after hook will restore to the original value.
	}

	 /**
	 * @test
	 * Verify that calling remove multiple times only records the *first* original value.
	 */
	public function it_should_only_record_the_very_first_value_when_removing_multiple_times(): void {
		update_option( self::TEST_OPTION_KEY_1, self::TEST_OPTION_VALUE_ORIGINAL );

		// Remove multiple times.
		$this->remove_option_safely_only_for_this_test( self::TEST_OPTION_KEY_1 );
		$this->remove_option_safely_only_for_this_test( self::TEST_OPTION_KEY_1 ); // Second remove should be no-op for recording.

		$this->assertFalse( get_option( self::TEST_OPTION_KEY_1 ), 'Option should be deleted.' );

		$recorded_options = $this->get_recorded_original_options();
		$this->assertArrayHasKey( self::TEST_OPTION_KEY_1, $recorded_options );
		$this->assertEquals( self::TEST_OPTION_VALUE_ORIGINAL, $recorded_options[ self::TEST_OPTION_KEY_1 ], 'Trait should record the very first original value before deletion.' );
		// Trait's @after hook will restore the original value.
	}
} 