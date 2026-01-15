<?php
/**
 * Event Logger - Event logging for CAPI E2E tests
 *
 * Location: tests/e2e/helpers/php/event-logger.php
 *
 * Usage:
 * 1. For Pixel events: helpers/js/events/capture.js writes directly to filesystem. Does not use this class
 * 2. For CAPI events: PHP code calls E2E_Event_Logger::log_event()
 */
class E2E_Event_Logger {

    /**
     * Normalize custom_data fields to proper types
     *
     * @param array $data Event data
     * @return array Normalized event data
     */
    private static function normalize_event_data($data) {

        if (!isset($data['custom_data'])) {
            return $data;
        }


        array_walk_recursive($data['custom_data'], function(&$value) {
            if (is_string($value)) {
                $decoded = json_decode($value, true);
                if (json_last_error() === JSON_ERROR_NONE && $decoded !== $value) {
                    $value = $decoded;
                }
            }
        });

        return $data;
    }

    /**
     * Log CAPI event to JSON file (simple array of events)
     *
     * @param string $testId Test identifier
     * @param string $eventType 'capi' (pixel writes separately)
     * @param array $eventData Event data to log
     * @return bool Success
     */
    public static function log_event($testId, $eventType, $eventData) {
        if (empty($testId) || empty($eventType)) {
            return false;
        }

        // dirname(__DIR__) is /path-to-plugin/tests/e2e
        // So we append /captured-events to get /path-to-plugin/tests/e2e/captured-events
        $capturedDir = dirname(__DIR__) . '/captured-events';

        if (!file_exists($capturedDir) && !@mkdir($capturedDir, 0755, true)) {
            return false;
        }

        $filePath = $capturedDir . '/' . $eventType . '-' . $testId . '.json';

        // Read existing events or create empty array
        $events = [];
        if (file_exists($filePath)) {
            $contents = file_get_contents($filePath);
            $events = json_decode($contents, true) ?: [];
        }

        // Normalize event data before storing
        $eventData = self::normalize_event_data($eventData);


        // Add event with timestamp
        $eventData['capturedAt'] = microtime(true) * 1000;
        $events[] = $eventData;

        // Write back
        $success = file_put_contents($filePath, json_encode($events, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $success !== false;
    }
}
