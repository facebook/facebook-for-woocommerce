<?php
/**
 * Plugin Name: FB E2E Batch Monitor (Test Only)
 * Description: Intercepts Meta API batch requests during E2E tests
 * Version: 1.0.0-poc
 * Author: E2E Test Suite
 */

if (!defined('ABSPATH')) exit;

class FB_E2E_Batch_Monitor {
    private static $log_file;
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        self::$log_file = '/tmp/fb-batch-monitor.json';

        // Only activate if explicitly enabled
        if (get_option('fb_e2e_test_batch_api_monitoring', false)) {
            // Use http_response filter to capture actual responses
            add_filter('http_response', [$this, 'capture_response'], 10, 3);
        }

        // Register WP-CLI commands
        if (defined('WP_CLI') && WP_CLI) {
            WP_CLI::add_command('fb-batch-api-monitor', [$this, 'cli_command']);
        }
    }

    /**
     * Capture actual responses from Meta API
     */
    public function capture_response($response, $args, $url) {
        // Only capture Meta API batch calls
        if (strpos($url, 'graph.facebook.com') === false) {
            return $response;
        }

        if (strpos($url, 'items_batch') === false) {
            return $response;
        }

        // Parse original request body to get batch size
        $body = json_decode($args['body'] ?? '{}', true);
        $requests = $body['requests'] ?? [];

        // Extract response details
        $response_code = wp_remote_retrieve_response_code($response);
        $response_message = wp_remote_retrieve_response_message($response);
        $response_body = wp_remote_retrieve_body($response);
        $response_body_decoded = json_decode($response_body, true);

        // Check for errors in response
        $has_error = false;
        $error_message = null;
        $error_code = null;

        if (is_wp_error($response)) {
            $has_error = true;
            $error_message = $response->get_error_message();
            $error_code = $response->get_error_code();
        } elseif ($response_code !== 200) {
            $has_error = true;
            $error_message = "HTTP $response_code: $response_message";
            $error_code = $response_code;
        } elseif (isset($response_body_decoded['error'])) {
            $has_error = true;
            $error_message = $response_body_decoded['error']['message'] ?? 'Unknown error';
            $error_code = $response_body_decoded['error']['code'] ?? 'unknown';
        }

        // Log batch info with response
        $this->log_batch([
            'timestamp' => time(),
            'datetime' => date('Y-m-d H:i:s'),
            'url' => $url,
            'method' => $args['method'] ?? 'POST',
            'batch_size' => count($requests),
            'request_sample' => array_slice($requests, 0, 2), // First 2 for debugging
            'response' => [
                'code' => $response_code,
                'message' => $response_message,
                'has_error' => $has_error,
                'error_message' => $error_message,
                'error_code' => $error_code,
                'handles' => $response_body_decoded['handles'] ?? [],
                'validation_status' => $response_body_decoded['validation_status'] ?? null,
            ]
        ]);

        return $response;
    }

    /**
     * Append batch info to log file with file locking to prevent race conditions
     */
    private function log_batch($batch_info) {
        $max_retries = 10;
        $retry_count = 0;

        while ($retry_count < $max_retries) {
            // Open file for reading and writing; create if not exists
            $fp = fopen(self::$log_file, 'c+');

            if (!$fp) {
                error_log('[FB Monitor] Failed to open log file: ' . self::$log_file);
                return;
            }

            // Try to acquire exclusive lock (LOCK_EX blocks until available)
            if (flock($fp, LOCK_EX)) {
                // Read current contents while holding lock
                rewind($fp);
                $contents = stream_get_contents($fp);

                $log = ['batches' => [], 'summary' => []];
                if (!empty($contents)) {
                    $decoded = json_decode($contents, true);
                    if ($decoded !== null) {
                        $log = $decoded;
                    }
                }

                // Append new batch info
                $log['batches'][] = $batch_info;
                $log['summary'] = [
                    'total_batches' => count($log['batches']),
                    'total_products' => array_sum(array_column($log['batches'], 'batch_size')),
                    'first_batch_time' => $log['batches'][0]['datetime'] ?? null,
                    'last_batch_time' => $batch_info['datetime']
                ];

                // Write back to file
                ftruncate($fp, 0); // Clear file contents
                rewind($fp);
                fwrite($fp, json_encode($log, JSON_PRETTY_PRINT));
                fflush($fp); // Ensure data is written

                // Release lock
                flock($fp, LOCK_UN);
                fclose($fp);

                return; // Success!
            }

            // Failed to get lock (shouldn't happen with LOCK_EX, but just in case)
            fclose($fp);
            $retry_count++;
            usleep(50000); // Wait 50ms before retry
        }

        error_log('[FB Monitor] Failed to acquire file lock after ' . $max_retries . ' retries');
    }

    /**
     * WP-CLI command handler
     */
    public function cli_command($args, $assoc_args) {
        $action = $args[0] ?? '';

        switch ($action) {
            case 'enable':
                update_option('fb_e2e_test_batch_api_monitoring', true);
                $this->clear_log();
                WP_CLI::success('Monitoring enabled and log cleared');
                break;

            case 'disable':
                update_option('fb_e2e_test_batch_api_monitoring', false);
                WP_CLI::success('Monitoring disabled');
                break;

            case 'get-log':
                if (file_exists(self::$log_file)) {
                    echo file_get_contents(self::$log_file);
                } else {
                    WP_CLI::error('No log file found');
                }
                break;

            case 'clear':
                $this->clear_log();
                WP_CLI::success('Log cleared');
                break;

            case 'status':
                $enabled = get_option('fb_e2e_test_batch_api_monitoring', false);
                $status = $enabled ? 'ENABLED' : 'DISABLED';
                WP_CLI::line("Monitoring: $status");
                if (file_exists(self::$log_file)) {
                    WP_CLI::line("Log file: " . self::$log_file);
                }
                break;

            default:
                WP_CLI::error("Unknown command. Use: enable, disable, get-log, clear, status");
        }
    }

    private function clear_log() {
        if (file_exists(self::$log_file)) {
            unlink(self::$log_file);
        }
    }
}

// Initialize
FB_E2E_Batch_Monitor::get_instance();
