<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Unit\Debug;

use WooCommerce\Facebook\Debug\ProfilingLogger;
use WooCommerce\Facebook\Debug\ProfilingLoggerProcess;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;

/**
 * Unit tests for ProfilingLogger class.
 *
 * @since 3.5.2
 */
class ProfilingLoggerTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

	/**
	 * Test that the class exists.
	 */
	public function test_class_exists() {
		$this->assertTrue( class_exists( ProfilingLogger::class ) );
	}

	/**
	 * Test constructor with enabled flag.
	 */
	public function test_constructor_with_enabled_true() {
		$logger = new ProfilingLogger( true );
		
		// Use reflection to check the is_enabled property
		$reflection = new \ReflectionClass( $logger );
		$is_enabled_prop = $reflection->getProperty( 'is_enabled' );
		$is_enabled_prop->setAccessible( true );
		
		$this->assertTrue( $is_enabled_prop->getValue( $logger ) );
	}

	/**
	 * Test constructor with disabled flag.
	 */
	public function test_constructor_with_enabled_false() {
		$logger = new ProfilingLogger( false );
		
		// Use reflection to check the is_enabled property
		$reflection = new \ReflectionClass( $logger );
		$is_enabled_prop = $reflection->getProperty( 'is_enabled' );
		$is_enabled_prop->setAccessible( true );
		
		$this->assertFalse( $is_enabled_prop->getValue( $logger ) );
	}

	/**
	 * Test start method when enabled.
	 */
	public function test_start_when_enabled() {
		$logger = new ProfilingLogger( true );
		
		$process = $logger->start( 'test_process' );
		
		$this->assertInstanceOf( ProfilingLoggerProcess::class, $process );
	}

	/**
	 * Test start method when disabled.
	 */
	public function test_start_when_disabled() {
		$logger = new ProfilingLogger( false );
		
		$process = $logger->start( 'test_process' );
		
		$this->assertNull( $process );
	}

	/**
	 * Test starting the same process twice.
	 */
	public function test_start_same_process_twice() {
		$logger = new ProfilingLogger( true );
		
		// Start process first time
		$process1 = $logger->start( 'duplicate_process' );
		$this->assertInstanceOf( ProfilingLoggerProcess::class, $process1 );
		
		// Try to start same process again
		$process2 = $logger->start( 'duplicate_process' );
		$this->assertNull( $process2 );
	}

	/**
	 * Test stop method when enabled.
	 */
	public function test_stop_when_enabled() {
		$logger = new ProfilingLogger( true );
		
		// Start a process
		$logger->start( 'test_stop_process' );
		
		// Stop the process
		$stopped_process = $logger->stop( 'test_stop_process' );
		
		$this->assertInstanceOf( ProfilingLoggerProcess::class, $stopped_process );
	}

	/**
	 * Test stop method when disabled.
	 */
	public function test_stop_when_disabled() {
		$logger = new ProfilingLogger( false );
		
		$stopped_process = $logger->stop( 'test_process' );
		
		$this->assertNull( $stopped_process );
	}

	/**
	 * Test stopping a process that hasn't started.
	 */
	public function test_stop_process_not_started() {
		$logger = new ProfilingLogger( true );
		
		$stopped_process = $logger->stop( 'never_started_process' );
		
		$this->assertNull( $stopped_process );
	}

	/**
	 * Test multiple processes can be tracked simultaneously.
	 */
	public function test_multiple_processes_simultaneously() {
		$logger = new ProfilingLogger( true );
		
		// Start multiple processes
		$process1 = $logger->start( 'process_1' );
		$process2 = $logger->start( 'process_2' );
		$process3 = $logger->start( 'process_3' );
		
		$this->assertInstanceOf( ProfilingLoggerProcess::class, $process1 );
		$this->assertInstanceOf( ProfilingLoggerProcess::class, $process2 );
		$this->assertInstanceOf( ProfilingLoggerProcess::class, $process3 );
		
		// Stop them in different order
		$stopped2 = $logger->stop( 'process_2' );
		$stopped1 = $logger->stop( 'process_1' );
		$stopped3 = $logger->stop( 'process_3' );
		
		$this->assertInstanceOf( ProfilingLoggerProcess::class, $stopped1 );
		$this->assertInstanceOf( ProfilingLoggerProcess::class, $stopped2 );
		$this->assertInstanceOf( ProfilingLoggerProcess::class, $stopped3 );
	}

	/**
	 * Test process lifecycle with actual work.
	 */
	public function test_process_lifecycle_with_work() {
		$logger = new ProfilingLogger( true );
		
		// Start process
		$process = $logger->start( 'work_process' );
		$this->assertInstanceOf( ProfilingLoggerProcess::class, $process );
		
		// Do some work
		$data = array();
		for ( $i = 0; $i < 1000; $i++ ) {
			$data[] = str_repeat( 'test', 10 );
		}
		usleep( 5000 ); // 5ms delay
		
		// Stop process
		$stopped_process = $logger->stop( 'work_process' );
		$this->assertInstanceOf( ProfilingLoggerProcess::class, $stopped_process );
		
		// Verify the process tracked time and memory
		$this->assertGreaterThan( 0, $stopped_process->get_time_used() );
		$this->assertIsInt( $stopped_process->get_memory_used() );
	}

	/**
	 * Test that stopped processes are removed from active processes.
	 */
	public function test_stopped_processes_removed_from_active() {
		$logger = new ProfilingLogger( true );
		
		// Start and stop a process
		$logger->start( 'temp_process' );
		$logger->stop( 'temp_process' );
		
		// Try to stop it again - should fail
		$result = $logger->stop( 'temp_process' );
		$this->assertNull( $result );
	}

	/**
	 * Test process names with special characters.
	 */
	public function test_process_names_with_special_characters() {
		$logger = new ProfilingLogger( true );
		
		$special_names = array(
			'process-with-dashes',
			'process_with_underscores',
			'process.with.dots',
			'process:with:colons',
			'process/with/slashes',
			'process with spaces',
			'プロセス', // Japanese characters
			'process_123_numbers',
		);
		
		foreach ( $special_names as $name ) {
			$process = $logger->start( $name );
			$this->assertInstanceOf( ProfilingLoggerProcess::class, $process, "Failed to start process with name: $name" );
			
			$stopped = $logger->stop( $name );
			$this->assertInstanceOf( ProfilingLoggerProcess::class, $stopped, "Failed to stop process with name: $name" );
		}
	}

	/**
	 * Test empty process name.
	 */
	public function test_empty_process_name() {
		$logger = new ProfilingLogger( true );
		
		$process = $logger->start( '' );
		$this->assertInstanceOf( ProfilingLoggerProcess::class, $process );
		
		$stopped = $logger->stop( '' );
		$this->assertInstanceOf( ProfilingLoggerProcess::class, $stopped );
	}

	/**
	 * Test is_running method through reflection.
	 */
	public function test_is_running_protected_method() {
		$logger = new ProfilingLogger( true );
		
		// Use reflection to access protected is_running method
		$reflection = new \ReflectionClass( $logger );
		$is_running_method = $reflection->getMethod( 'is_running' );
		$is_running_method->setAccessible( true );
		
		// Test when process is not running
		$this->assertFalse( $is_running_method->invoke( $logger, 'not_running' ) );
		
		// Start a process
		$logger->start( 'running_process' );
		
		// Test when process is running
		$this->assertTrue( $is_running_method->invoke( $logger, 'running_process' ) );
		
		// Stop the process
		$logger->stop( 'running_process' );
		
		// Test after stopping
		$this->assertFalse( $is_running_method->invoke( $logger, 'running_process' ) );
	}
} 