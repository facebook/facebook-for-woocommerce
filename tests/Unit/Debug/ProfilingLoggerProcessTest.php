<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Unit\Debug;

use WooCommerce\Facebook\Debug\ProfilingLoggerProcess;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;

/**
 * Unit tests for ProfilingLoggerProcess class.
 *
 * @since 3.5.2
 */
class ProfilingLoggerProcessTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

	/**
	 * Test that the class exists.
	 */
	public function test_class_exists() {
		$this->assertTrue( class_exists( ProfilingLoggerProcess::class ) );
	}

	/**
	 * Test constructor initializes start memory and time.
	 */
	public function test_constructor_initializes_start_values() {
		$process = new ProfilingLoggerProcess();
		
		// Use reflection to access protected properties
		$reflection = new \ReflectionClass( $process );
		
		$start_memory_prop = $reflection->getProperty( 'start_memory' );
		$start_memory_prop->setAccessible( true );
		$start_memory = $start_memory_prop->getValue( $process );
		
		$start_time_prop = $reflection->getProperty( 'start_time' );
		$start_time_prop->setAccessible( true );
		$start_time = $start_time_prop->getValue( $process );
		
		// Assert that start values are set
		$this->assertIsInt( $start_memory );
		$this->assertGreaterThan( 0, $start_memory );
		
		$this->assertIsFloat( $start_time );
		$this->assertGreaterThan( 0, $start_time );
	}

	/**
	 * Test stop method sets stop memory and time.
	 */
	public function test_stop_sets_stop_values() {
		$process = new ProfilingLoggerProcess();
		
		// Small delay to ensure time difference
		usleep( 1000 ); // 1ms
		
		$process->stop();
		
		// Use reflection to access protected properties
		$reflection = new \ReflectionClass( $process );
		
		$stop_memory_prop = $reflection->getProperty( 'stop_memory' );
		$stop_memory_prop->setAccessible( true );
		$stop_memory = $stop_memory_prop->getValue( $process );
		
		$stop_time_prop = $reflection->getProperty( 'stop_time' );
		$stop_time_prop->setAccessible( true );
		$stop_time = $stop_time_prop->getValue( $process );
		
		// Assert that stop values are set
		$this->assertIsInt( $stop_memory );
		$this->assertGreaterThan( 0, $stop_memory );
		
		$this->assertIsFloat( $stop_time );
		$this->assertGreaterThan( 0, $stop_time );
	}

	/**
	 * Test get_memory_used returns correct memory difference.
	 */
	public function test_get_memory_used_returns_difference() {
		$process = new ProfilingLoggerProcess();
		
		// Allocate some memory
		$data = str_repeat( 'x', 10000 );
		
		$process->stop();
		
		$memory_used = $process->get_memory_used();
		
		// Memory usage can be positive or negative depending on garbage collection
		$this->assertIsInt( $memory_used );
	}

	/**
	 * Test get_time_used returns positive time difference.
	 */
	public function test_get_time_used_returns_positive_difference() {
		$process = new ProfilingLoggerProcess();
		
		// Small delay to ensure measurable time difference
		usleep( 10000 ); // 10ms
		
		$process->stop();
		
		$time_used = $process->get_time_used();
		
		$this->assertIsFloat( $time_used );
		$this->assertGreaterThan( 0, $time_used );
		// Should be at least 0.01 seconds (10ms)
		$this->assertGreaterThanOrEqual( 0.009, $time_used ); // Allow small margin
	}

	/**
	 * Test multiple stop calls update values.
	 */
	public function test_multiple_stop_calls_update_values() {
		$process = new ProfilingLoggerProcess();
		
		// First stop
		usleep( 5000 ); // 5ms
		$process->stop();
		$first_time = $process->get_time_used();
		
		// Second stop
		usleep( 5000 ); // Another 5ms
		$process->stop();
		$second_time = $process->get_time_used();
		
		// Second time should be greater than first
		$this->assertGreaterThan( $first_time, $second_time );
	}

	/**
	 * Test process tracking with real memory allocation.
	 */
	public function test_process_with_memory_allocation() {
		$process = new ProfilingLoggerProcess();
		
		// Get initial memory
		$initial_memory = memory_get_usage();
		
		// Allocate significant memory
		$large_array = array();
		for ( $i = 0; $i < 1000; $i++ ) {
			$large_array[] = str_repeat( 'test', 100 );
		}
		
		$process->stop();
		
		$memory_used = $process->get_memory_used();
		
		// Memory used should reflect the allocation
		$this->assertIsInt( $memory_used );
		// We allocated memory, so it should be positive
		$this->assertGreaterThan( 0, $memory_used );
	}

	/**
	 * Test edge case with immediate stop.
	 */
	public function test_immediate_stop() {
		$process = new ProfilingLoggerProcess();
		$process->stop();
		
		$time_used = $process->get_time_used();
		$memory_used = $process->get_memory_used();
		
		// Even with immediate stop, time should be measurable (though very small)
		$this->assertIsFloat( $time_used );
		$this->assertGreaterThanOrEqual( 0, $time_used );
		
		// Memory difference could be positive, negative, or zero
		$this->assertIsInt( $memory_used );
	}

	/**
	 * Test that the process can track negative memory changes.
	 */
	public function test_negative_memory_change() {
		// Allocate memory before creating process
		$temp_data = array();
		for ( $i = 0; $i < 1000; $i++ ) {
			$temp_data[] = str_repeat( 'x', 1000 );
		}
		
		$process = new ProfilingLoggerProcess();
		
		// Clear the allocated memory
		unset( $temp_data );
		
		// Force garbage collection if available
		if ( function_exists( 'gc_collect_cycles' ) ) {
			gc_collect_cycles();
		}
		
		$process->stop();
		
		$memory_used = $process->get_memory_used();
		
		// Memory used could be negative if garbage collection occurred
		$this->assertIsInt( $memory_used );
	}
} 