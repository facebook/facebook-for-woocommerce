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
            error_log("DEBUG_E2E: Missing testId or eventType");
            return false;
        }

        error_log("DEBUG_E2E: log_event called - testId: $testId, eventType: $eventType");

        // __DIR__ is /path-to-plugin/tests/e2e/lib
        // dirname(__DIR__) is /path-to-plugin/tests/e2e
        // So we append /captured-events to get /path-to-plugin/tests/e2e/captured-events
        $capturedDir = dirname(__DIR__) . '/captured-events';

        error_log("DEBUG_E2E: Target directory: $capturedDir");
        error_log("DEBUG_E2E: Directory exists: " . (file_exists($capturedDir) ? 'YES' : 'NO'));
        error_log("DEBUG_E2E: Is directory: " . (is_dir($capturedDir) ? 'YES' : 'NO'));
        error_log("DEBUG_E2E: Is writable: " . (is_writable($capturedDir) ? 'YES' : 'NO'));
        error_log("DEBUG_E2E: Is symlink: " . (is_link($capturedDir) ? 'YES' : 'NO'));

        // If it's a symlink, resolve it
        if (is_link($capturedDir)) {
            $realPath = readlink($capturedDir);
            error_log("DEBUG_E2E: Symlink points to: $realPath");
            error_log("DEBUG_E2E: Real path exists: " . (file_exists($realPath) ? 'YES' : 'NO'));
            error_log("DEBUG_E2E: Real path is writable: " . (is_writable($realPath) ? 'YES' : 'NO'));
        }

        // Create directory if it doesn't exist (skip if it's a symlink that already exists)
        if (!file_exists($capturedDir)) {
            error_log("DEBUG_E2E: Attempting to create directory");
            if (!@mkdir($capturedDir, 0777, true)) {
                $lastError = error_get_last();
                error_log("DEBUG_E2E: FAILED - Cannot create dir: $capturedDir");
                error_log("DEBUG_E2E: Error: " . ($lastError ? $lastError['message'] : 'unknown'));
                return false;
            }
            error_log("DEBUG_E2E: Directory created successfully");
        } else {
            error_log("DEBUG_E2E: Directory already exists (good!)");
        }

        // Verify it's writable
        if (!is_writable($capturedDir)) {
            error_log("DEBUG_E2E: WARNING - Directory not writable, attempting chmod");
            @chmod($capturedDir, 0777);
            if (!is_writable($capturedDir)) {
                error_log("DEBUG_E2E: FAILED - Directory still not writable after chmod");
                return false;
            }
            error_log("DEBUG_E2E: chmod succeeded");
        }

        error_log("DEBUG_E2E: Using dir: $capturedDir");

        $filePath = $capturedDir . '/' . $eventType . '-' . $testId . '.json';
        error_log("DEBUG_E2E: Writing to: $filePath");

        // Read existing events or create empty array
        $events = [];
        if (file_exists($filePath)) {
            error_log("DEBUG_E2E: File exists, reading existing events");
            $contents = file_get_contents($filePath);
            $events = json_decode($contents, true) ?: [];
            error_log("DEBUG_E2E: Found " . count($events) . " existing event(s)");
        }

        // Add event with timestamp
        $eventData['capturedAt'] = microtime(true) * 1000;
        $events[] = $eventData;

        // Write back
        $success = file_put_contents($filePath, json_encode($events, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        if ($success) {
            error_log("DEBUG_E2E: ✅ Event logged successfully - wrote $success bytes");
        } else {
            error_log("DEBUG_E2E: ❌ Failed to write file");
            $lastError = error_get_last();
            error_log("DEBUG_E2E: Error: " . ($lastError ? $lastError['message'] : 'unknown'));
        }

        return $success !== false;
    }
}
