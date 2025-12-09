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
        if (get_option('fb_e2e_monitoring_enabled', false)) {
            add_filter('pre_http_request', [$this, 'intercept_http'], 10, 3);
        }

        // Register WP-CLI commands
        if (defined('WP_CLI') && WP_CLI) {
            WP_CLI::add_command('fb-monitor', [$this, 'cli_command']);
        }
    }

    /**
     * Intercept HTTP requests to Meta API
     */
    public function intercept_http($preempt, $args, $url) {
        // Only intercept Meta API batch calls
        if (strpos($url, 'graph.facebook.com') === false) {
            return $preempt;
        }

        if (strpos($url, 'items_batch') === false) {
            return $preempt;
        }

        // Parse request body
        $body = json_decode($args['body'] ?? '{}', true);
        $requests = $body['requests'] ?? [];

        // Log batch info
        $this->log_batch([
            'timestamp' => time(),
            'datetime' => date('Y-m-d H:i:s'),
            'url' => $url,
            'method' => $args['method'] ?? 'POST',
            'batch_size' => count($requests),
            'request_sample' => array_slice($requests, 0, 2) // First 2 for debugging
        ]);

        // Return mocked successful response
        return [
            'response' => [
                'code' => 200,
                'message' => 'OK'
            ],
            'headers' => [
                'content-type' => 'application/json'
            ],
            'body' => json_encode([
                'handles' => array_map(function($i) {
                    return 'mock_handle_' . time() . '_' . $i;
                }, range(0, count($requests) - 1))
            ])
        ];
    }

    /**
     * Append batch info to log file
     */
    private function log_batch($batch_info) {
        $log = ['batches' => [], 'summary' => []];

        if (file_exists(self::$log_file)) {
            $existing = file_get_contents(self::$log_file);
            $log = json_decode($existing, true) ?: $log;
        }

        $log['batches'][] = $batch_info;
        $log['summary'] = [
            'total_batches' => count($log['batches']),
            'total_products' => array_sum(array_column($log['batches'], 'batch_size')),
            'first_batch_time' => $log['batches'][0]['datetime'] ?? null,
            'last_batch_time' => $batch_info['datetime']
        ];

        file_put_contents(self::$log_file, json_encode($log, JSON_PRETTY_PRINT));
    }

    /**
     * WP-CLI command handler
     */
    public function cli_command($args, $assoc_args) {
        $action = $args[0] ?? '';

        switch ($action) {
            case 'enable':
                update_option('fb_e2e_monitoring_enabled', true);
                $this->clear_log();
                WP_CLI::success('Monitoring enabled and log cleared');
                break;

            case 'disable':
                update_option('fb_e2e_monitoring_enabled', false);
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
                $enabled = get_option('fb_e2e_monitoring_enabled', false);
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
