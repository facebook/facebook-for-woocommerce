<?php
/**
 * Simple endpoint to log Pixel events to debug.log
 * Called from Playwright tests
 */

// Get debug.log path
$debug_log = dirname(dirname(dirname(__DIR__))) . '/debug.log';

// Get POST data
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if ($data && isset($data['testId']) && isset($data['eventName'])) {
    $log_entry = sprintf(
        '[FBTEST|%s] PIXEL|%s|%s|%s',
        $data['testId'],
        $data['eventName'],
        $data['eventId'] ?? 'unknown',
        json_encode($data)
    );

    error_log($log_entry, 3, $debug_log);

    http_response_code(200);
    echo json_encode(['success' => true]);
} else {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields']);
}
