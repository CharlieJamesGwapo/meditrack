<?php
// Enable all error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Clear any output
ob_start();

header('Content-Type: application/json');

try {
    // Test basic JSON output
    echo json_encode([
        'success' => true,
        'message' => 'Basic test working',
        'timestamp' => date('Y-m-d H:i:s'),
        'php_version' => PHP_VERSION,
        'post_data' => $_POST ?? [],
        'get_data' => $_GET ?? [],
        'server_info' => [
            'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
            'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'unknown',
            'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown'
        ]
    ]);
    
} catch (Exception $e) {
    ob_end_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Exception: ' . $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
}

// Always clean output buffer
ob_end_flush();
?>
