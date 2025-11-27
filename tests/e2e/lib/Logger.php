<?php
/**
 * Logger - Single file for all event logging
 *
 * Usage:
 * 1. HTTP endpoint (for Pixel from browser): POST to this file
 * 2. Direct call (for CAPI from PHP): require + call log_event()
 */

class E2E_Event_Logger {

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

        // __DIR__ is /path-to-plugin/tests/e2e/lib
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

        // Add event with timestamp
        $eventData['capturedAt'] = microtime(true) * 1000;
        $events[] = $eventData;

        // Write back
        $success = file_put_contents($filePath, json_encode($events, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $success !== false;
    }
}
