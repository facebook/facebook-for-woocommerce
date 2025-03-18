<?php

namespace WooCommerce\Facebook\Debug;

defined( 'ABSPATH' ) || exit;

/**
 * Class ProfilingLoggerProcess
 */
class ProfilingLoggerProcess {

	/** @var int */
	protected $start_memory;

	/** @var float */
	protected $start_time;

	/** @var int */
	protected $stop_memory;

	/** @var float */
	protected $stop_time;

	/**
	 * ProfileLoggerProcess constructor.
	 */
	public function __construct() {
		$this->start_memory = memory_get_usage();
		$this->start_time   = microtime( true );
	}

	/**
	 * Call when the process has stopped.
	 */
	public function stop() {
		$this->stop_memory = memory_get_usage();
		$this->stop_time   = microtime( true );
	}

	/**
	 * @return int
	 */
	public function get_memory_used() {
		return $this->stop_memory - $this->start_memory;
	}

	/**
	 * @return float
	 */
	public function get_time_used() {
		return $this->stop_time - $this->start_time;
	}
}
