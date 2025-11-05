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
     * Log an event to JSON file (race-condition safe with file locking)
     *
     * @param string $testId Test identifier
     * @param string $eventType 'pixel' or 'capi'
     * @param array $eventData Event data to log
     * @return bool Success
     */
    public static function log_event($testId, $eventType, $eventData) {
        if (empty($testId) || empty($eventType)) {
            error_log("DEBUG_E2E: Missing testId or eventType"); // DEBUG_E2E
            return false;
        }
        
        error_log("DEBUG_E2E: log_event called - testId: $testId, eventType: $eventType"); // DEBUG_E2E
        
        // Use absolute path - check multiple possible locations
        $possibleDirs = [
            '/tmp/wordpress/wp-content/plugins/facebook-for-woocommerce/tests/e2e/captured-events',
            dirname(__DIR__) . '/captured-events'
        ];
        
        error_log("DEBUG_E2E: Checking directories: " . implode(', ', $possibleDirs)); // DEBUG_E2E
        
        $capturedDir = null;
        foreach ($possibleDirs as $dir) {
            error_log("DEBUG_E2E: Checking dir: $dir"); // DEBUG_E2E
            if (file_exists($dir)) {
                error_log("DEBUG_E2E: Dir exists: $dir"); // DEBUG_E2E
                $capturedDir = $dir;
                break;
            } else if (@mkdir($dir, 0755, true)) {
                error_log("DEBUG_E2E: Created dir: $dir"); // DEBUG_E2E
                $capturedDir = $dir;
                break;
            } else {
                error_log("DEBUG_E2E: Failed to create dir: $dir"); // DEBUG_E2E
            }
        }
        
        if (!$capturedDir) {
            error_log("DEBUG_E2E: FAILED - No directory available"); // DEBUG_E2E
            return false;
        }
        
        $filePath = $capturedDir . '/' . $testId . '.json';
        error_log("DEBUG_E2E: Will write to: $filePath"); // DEBUG_E2E

        // Open file with exclusive lock (blocks until available)
        $fp = fopen($filePath, 'c+'); // c+ = open for read/write, create if not exists
        if (!$fp) return false;

        // Acquire exclusive lock (prevents race conditions)
        if (!flock($fp, LOCK_EX)) {
            fclose($fp);
            return false;
        }

        // Read current contents (now that we have the lock)
        $contents = stream_get_contents($fp);
        if ($contents === false || $contents === '') {
            // File is empty - create new structure
            $fileData = [
                'testId' => $testId,
                'timestamp' => time() * 1000,
                'pixel' => [],
                'capi' => []
            ];
        } else {
            // Parse existing data
            $fileData = json_decode($contents, true);
            if ($fileData === null) {
                // JSON parse error - reset
                $fileData = [
                    'testId' => $testId,
                    'timestamp' => time() * 1000,
                    'pixel' => [],
                    'capi' => []
                ];
            }
        }

        // Add event with capture timestamp
        $eventData['capturedAt'] = microtime(true) * 1000;
        if ($eventType === 'pixel') {
            $fileData['pixel'][] = $eventData;
        } else {
            $fileData['capi'][] = $eventData;
        }

        // Write back to file (still locked)
        ftruncate($fp, 0); // Clear file
        rewind($fp);       // Go to start
        fwrite($fp, json_encode($fileData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        // Release lock and close
        flock($fp, LOCK_UN);
        fclose($fp);

        return true;
    }
}

// ============================================================================
// HTTP ENDPOINT (when accessed directly via browser/fetch)
// ============================================================================

// Only run HTTP endpoint code if this file is being accessed directly (not included via require)
if (php_sapi_name() !== 'cli' && basename($_SERVER['SCRIPT_FILENAME']) === 'Logger.php') {
    // Read JSON body
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    $testId = $data['testId'] ?? null;
    $eventType = $data['eventType'] ?? null;
    $eventData = $data['eventData'] ?? null;

    if (!$testId || !$eventType || !$eventData) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing testId, eventType, or eventData']);
        exit;
    }

    // Log event
    $success = E2E_Event_Logger::log_event($testId, $eventType, $eventData);

    if ($success) {
        http_response_code(200);
        echo json_encode(['success' => true]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to log event']);
    }
    exit;
}
