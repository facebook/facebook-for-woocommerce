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
            error_log("DEBUG_E2E: Missing testId or eventType"); // DEBUG_E2E
            return false;
        }
        
        error_log("DEBUG_E2E: log_event called - testId: $testId, eventType: $eventType"); // DEBUG_E2E
        
        // Use env var if set (for CI), otherwise use local path
        $capturedDir = getenv('E2E_CAPTURED_EVENTS_DIR') ?: dirname(__DIR__) . '/captured-events';
        
        if (!file_exists($capturedDir) && !@mkdir($capturedDir, 0755, true)) {
            error_log("DEBUG_E2E: FAILED - Cannot create dir: $capturedDir"); // DEBUG_E2E
            return false;
        }
        
        error_log("DEBUG_E2E: Using dir: $capturedDir"); // DEBUG_E2E
        
        $filePath = $capturedDir . '/' . $eventType . '-' . $testId . '.json';
        error_log("DEBUG_E2E: Writing to: $filePath"); // DEBUG_E2E

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
        
        if ($success) {
            error_log("DEBUG_E2E: Event logged successfully"); // DEBUG_E2E
        } else {
            error_log("DEBUG_E2E: Failed to write file"); // DEBUG_E2E
        }

        return $success !== false;
    }
}
