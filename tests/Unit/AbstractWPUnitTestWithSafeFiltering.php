<?php
/**
 * Abstract test case for unit tests.
 */

namespace WooCommerce\Facebook\Tests\Unit;

use WP_UnitTestCase;

/**
 * Abstract test case that provides filter management functionality.
 */
abstract class AbstractWPUnitTestWithSafeFiltering extends WP_UnitTestCase {
    /**
     * Store filter callbacks to remove them after tests
     * 
     * @var array
     */
    private $filter_callbacks = [];
    
    /**
     * Set up before each test.
     */
    public function setUp(): void {
        parent::setUp();
        $this->filter_callbacks = [];
    }
    
    /**
     * Clean up after each test.
     */
    public function tearDown(): void {
        // Remove specific filters that were added by this test
        foreach ($this->filter_callbacks as $hook => $callbacks) {
            foreach ($callbacks as $callback_data) {
                remove_filter($hook, $callback_data['callback'], $callback_data['priority']);
            }
        }
        
        parent::tearDown();
    }

    /**
     * Helper method to remove all filters for a specific hook safely
     *
     * @param string $hook The filter hook name to remove all callbacks for
     * @return void
     */
    protected function teardown_callback_category_safely($hook) {
        if (isset($this->filter_callbacks[$hook])) {
            foreach ($this->filter_callbacks[$hook] as $callback_data) {
                remove_filter($hook, $callback_data['callback'], $callback_data['priority']);
            }
            // Clear the tracking for this hook
            unset($this->filter_callbacks[$hook]);
        }
    }
    
    /**
     * Helper method to add a filter and store its callback for later removal
     *
     * @param string   $hook     The filter hook name
     * @param callable $callback The filter callback function
     * @param int      $priority The priority of the filter
     * @param int      $accepted_args The number of arguments the function accepts
     * @return object A simple object with remove() method for easy cleanup
     */
    protected function add_filter_with_safe_teardown($hook, $callback, $priority = 10, $accepted_args = 1) {
        add_filter($hook, $callback, $priority, $accepted_args);
        
        if (!isset($this->filter_callbacks[$hook])) {
            $this->filter_callbacks[$hook] = [];
        }
        
        $this->filter_callbacks[$hook][] = [
            'callback' => $callback,
            'priority' => $priority
        ];
        
        $self = $this;
        
        // Return a simple object with a remove method
        return new class($hook, $callback, $priority, $self) {
            private $hook;
            private $callback;
            private $priority;
            private $test_case;
            
            public function __construct($hook, $callback, $priority, $test_case) {
                $this->hook = $hook;
                $this->callback = $callback;
                $this->priority = $priority;
                $this->test_case = $test_case;
            }
            
            public function teardown_safely_immediately() {
                remove_filter($this->hook, $this->callback, $this->priority);
                $this->test_case->removeFilterFromTracking($this->hook, $this->callback, $this->priority);
            }
        };
    }
    
    /**
     * Remove a filter from the tracking array
     * 
     * @param string   $hook     The filter hook name
     * @param callable $callback The filter callback function
     * @param int      $priority The priority of the filter
     */
    public function removeFilterFromTracking($hook, $callback, $priority) {
        if (isset($this->filter_callbacks[$hook])) {
            foreach ($this->filter_callbacks[$hook] as $key => $callback_data) {
                if ($callback_data['callback'] === $callback && $callback_data['priority'] === $priority) {
                    unset($this->filter_callbacks[$hook][$key]);
                    break;
                }
            }
            
            // Clean up empty arrays
            if (empty($this->filter_callbacks[$hook])) {
                unset($this->filter_callbacks[$hook]);
            }
        }
    }
}
