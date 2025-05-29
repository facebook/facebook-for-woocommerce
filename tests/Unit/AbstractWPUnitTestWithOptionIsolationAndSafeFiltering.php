<?php
/**
 * Abstract test case for unit tests that require option isolation.
 */

namespace WooCommerce\Facebook\Tests;

use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithSafeFiltering;

/**
 * Abstract test case that provides isolation for WordPress options (get_option/update_option).
 *
 * This class intercepts calls to `get_option` and `update_option` during tests,
 * storing values in a local array instead of the database. The state is reset
 * after each test method.
 */
abstract class AbstractWPUnitTestWithOptionIsolationAndSafeFiltering extends AbstractWPUnitTestWithSafeFiltering {

	/**
	 * Stores mocked option values during a test.
	 * Format: [ 'option_name' => 'option_value' ]
	 *
	 * @var array
	 */
	protected $mocked_options = [];

	/**
	 * Stores the original values of options that were mocked.
	 * Used to restore state if necessary, though typically cleared in tearDown.
	 * Format: [ 'option_name' => 'original_value' ]
	 *
	 * @var array
	 */
	protected $original_option_values = [];

	/**
	 * Set up before each test.
	 *
	 * Initializes the mocked options array and sets up filters to intercept
	 * get_option and update_option calls.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->mocked_options = [];
		$this->original_option_values = [];

		// Intercept any attempt to get an option.
		$this->add_filter_with_safe_teardown('pre_option', function( $value, $option_name, $default ) {
			return $this->filter_pre_option( $value, $option_name, $default );
		}, 10, 3);

		// Intercept any attempt to update an option.
		// We hook into the specific option filter first if available.
		$this->add_filter_with_safe_teardown('pre_update_option', function( $value, $option_name, $old_value ) {
			return $this->filter_pre_update_option( $value, $option_name, $old_value );
		}, 10, 3);

		// Add specific filters for each option being updated.
        // Note: This requires knowing the option name *before* it's updated.
        // The generic 'pre_update_option' filter above handles cases where
        // the specific filter isn't set up beforehand by a test.
		// We might need a more dynamic way if tests update arbitrary options.
	}

	/**
	 * Clean up after each test.
	 *
	 * Clears the mocked options array and restores original values
	 * (though parent::tearDown typically handles filter removal).
	 */
	public function tearDown(): void {
		// Clear mocked options to ensure isolation between tests.
		$this->mocked_options = [];
		$this->original_option_values = [];

		// Parent tearDown will remove the filters added in setUp.
		parent::tearDown();
	}

	/**
	 * Filter callback for 'pre_option'.
	 *
	 * Checks if the requested option has been mocked. If so, returns the
	 * mocked value. Otherwise, returns false to let WordPress continue.
	 *
	 * @param mixed  $value       The value to return instead of the option value. Default false.
	 * @param string $option_name Name of the option.
	 * @param mixed  $default     Default value to return if the option does not exist.
	 * @return mixed Mocked value if set, otherwise false.
	 */
	protected function filter_pre_option( $value, $option_name, $default ) {
		if ( array_key_exists( $option_name, $this->mocked_options ) ) {
			// Return the mocked value. Use null for 'not found' to distinguish from false.
			return $this->mocked_options[ $option_name ] ?? null;
		}

		// If not mocked, let WordPress handle it (might return $default).
		// Returning false allows the original get_option logic to proceed.
		return false;
	}

	/**
	 * Filter callback for 'pre_update_option'.
	 *
	 * Intercepts the update, stores the new value in the local mock array,
	 * and prevents the database update by returning the old value.
	 *
	 * @param mixed  $value     The new value of the option.
	 * @param string $option_name Name of the option.
	 * @param mixed  $old_value The old option value.
	 * @return mixed The $old_value to effectively cancel the database update.
	 */
	protected function filter_pre_update_option( $value, $option_name, $old_value ) {
        // Store the value being set in our mock array
		$this->mocked_options[ $option_name ] = $value;

		// Store the original value if we haven't already
		if ( ! array_key_exists( $option_name, $this->original_option_values ) ) {
			// Note: $old_value provided by the filter might not be the true DB value
			// if another filter ran before this one. For simplicity here, we use it.
			// A more robust solution might fetch the actual value before adding the filter.
			$this->original_option_values[ $option_name ] = $old_value;
		}

		// Return the $old_value to prevent the actual database update.
		// Returning null or false might also work depending on WP version,
		// but returning $old_value is documented behavior for short-circuiting.
		return $old_value;
	}

	/**
	 * Directly set a mocked option value for testing purposes.
	 *
	 * This is useful for setting up the initial state before an action.
	 *
	 * @param string $option_name The name of the option to mock.
	 * @param mixed  $value       The value to set for the mocked option.
	 */
	protected function mock_set_option( string $option_name, $value ): void {
		if ( ! array_key_exists( $option_name, $this->original_option_values ) ) {
			$this->original_option_values[ $option_name ] = get_option( $option_name, null ); // Store original before overriding
		}
		$this->mocked_options[ $option_name ] = $value;
	}

	/**
	 * Get a mocked option value.
	 *
	 * @param string $option_name The name of the option.
	 * @param mixed  $default     The default value if the option isn't mocked.
	 * @return mixed The mocked value or the default.
	 */
	protected function mock_get_option( string $option_name, $default = false ) {
		return array_key_exists( $option_name, $this->mocked_options ) ? $this->mocked_options[ $option_name ] : $default;
	}

    /**
     * Get all mocked options.
     *
     * @return array
     */
    protected function mock_get_all_options(): array {
        return $this->mocked_options;
    }

	/**
	 * Assert that an option was "updated" (mocked) with a specific value during the test.
	 *
	 * @param string $option_name    The name of the option.
	 * @param mixed  $expected_value The expected value.
	 * @param string $message        Optional assertion message.
	 */
	protected function assertOptionUpdated( string $option_name, $expected_value, string $message = '' ): void {
		$this->assertTrue(
			array_key_exists( $option_name, $this->mocked_options ),
			$message ?: "Failed asserting that option '{$option_name}' was updated (mocked)."
		);
		$this->assertSame(
			$expected_value,
			$this->mocked_options[ $option_name ],
			$message ?: "Failed asserting that option '{$option_name}' was updated (mocked) with the expected value."
		);
	}

	/**
	 * Assert that an option was *not* "updated" (mocked) during the test.
	 *
	 * @param string $option_name The name of the option.
	 * @param string $message     Optional assertion message.
	 */
	protected function assertOptionNotUpdated( string $option_name, string $message = '' ): void {
		$this->assertFalse(
			array_key_exists( $option_name, $this->mocked_options ),
			$message ?: "Failed asserting that option '{$option_name}' was not updated (mocked)."
		);
	}
}