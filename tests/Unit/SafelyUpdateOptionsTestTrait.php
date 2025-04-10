<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests;

/**
 * Trait SafelyUpdateOptionsTestTrait.
 *
 * Provides methods for safely managing WordPress options during unit tests.
 * Records original option values before they are updated and restores them
 * during tear down.
 */
trait SafelyUpdateOptionsTestTrait {

	/**
	 * Stores the original values of options modified during a test.
	 *
	 * @var array<string, mixed|false> Key-value pairs. False indicates the option did not exist initially.
	 */
	private array $original_options = [];

	/**
	 * A special value to indicate that an option did not exist before being set.
	 */
	// private const OPTION_DOES_NOT_EXIST = '__OPTION_DOES_NOT_EXIST__'; // Removed invalid constant

	/**
	 * Set up before each test.
	 *
	 * Resets the record of original options.
     * Due to the use of the before annotation, this method is called before each test function.
	 *
	 * @before
	 */
	protected function setup_options_safely_trait(): void {
		$this->original_options = [];
	}

	/**
	 * Tear down after each test.
	 *
	 * Restores all modified options to their original values.
     * Due to the use of the after annotation, this method is called after each test function.
	 *
	 * @after
	 */
	protected function tear_down_options_safely_trait(): void {
		foreach ( $this->original_options as $key => $original_value ) {
			if ( '__OPTION_DOES_NOT_EXIST__' === $original_value ) { // Use literal string
				delete_option( $key );
			} else {
				update_option( $key, $original_value );
			}
		}
		// Reset for the next test, although setup_options_trait should handle this too.
		$this->original_options = [];
	}

	/**
	 * Safely update a WordPress option for the duration of a test.
	 *
	 * Records the original value (or lack thereof) before updating.
	 *
	 * @param string $key   The option name.
	 * @param mixed  $value The new option value.
	 * @return bool True if the value was updated, false otherwise.
	 */
	protected function set_option_safely_only_for_this_test( string $key, mixed $value ): bool {
		if ( ! array_key_exists( $key, $this->original_options ) ) {
			$current_value = get_option( $key, '__OPTION_DOES_NOT_EXIST__' ); // Use literal string
			$this->original_options[ $key ] = $current_value;
		}

		return update_option( $key, $value );
	}

	/**
	 * Safely delete a WordPress option for the duration of a test.
	 *
	 * Records the original value (or lack thereof) before deleting.
	 *
	 * @param string $key The option name.
	 * @return bool True if the option was deleted, false otherwise.
	 */
	protected function remove_option_safely_only_for_this_test( string $key ): bool {
		if ( ! array_key_exists( $key, $this->original_options ) ) {
			$current_value = get_option( $key, '__OPTION_DOES_NOT_EXIST__' ); // Use literal string
			$this->original_options[ $key ] = $current_value;
		}

		return delete_option( $key );
	}
} 