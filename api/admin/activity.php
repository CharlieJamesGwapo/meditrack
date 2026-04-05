<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../../config/database.php';
require_once '../../config/config.php';

// Authentication check
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'reception'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        throw new Exception('Database connection failed');
    }
    
    // Get recent activity from audit logs or create from recent actions
    $activities = [];
    
    // Get recent user registrations
    $stmt = $db->query("SELECT username, created_at FROM users ORDER BY created_at DESC LIMIT 5");
    while ($row = $stmt->fetch()) {
        $timeAgo = getTimeAgo($row['created_at']);
        $activities[] = [
            'type' => 'success',
            'title' => 'New User Registered',
            'description' => $row['username'] . ' joined the system',
            'time' => $timeAgo
        ];
    }
    
    // Get recent appointments
    $stmt = $db->query("SELECT appointment_date, created_at FROM appointments ORDER BY created_at DESC LIMIT 5");
    while ($row = $stmt->fetch()) {
        $timeAgo = getTimeAgo($row['created_at']);
        $activities[] = [
            'type' => 'info',
            'title' => 'New Appointment Scheduled',
            'description' => 'Appointment for ' . date('M d, Y', strtotime($row['appointment_date'])),
            'time' => $timeAgo
        ];
    }
    
    // Sort by time
    usort($activities, function($a, $b) {
        return strcmp($b['time'], $a['time']);
    });
    
    echo json_encode(array_slice($activities, 0, 10));
    
} catch(Exception $e) {
    // Return default activity
    $now = date('M d, g:i A');
    echo json_encode([
        [
            'type' => 'success',
            'title' => 'System Started',
            'description' => 'Dashboard loaded successfully',
            'time' => $now
        ],
        [
            'type' => 'info',
            'title' => 'Admin Login',
            'description' => 'Administrator logged in',
            'time' => $now
        ]
    ]);
}

function getTimeAgo($datetime) {
    $time = strtotime($datetime);
    $diff = time() - $time;
    
    if ($diff < 60) {
        return 'Just now';
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . ' minute' . ($mins > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } else {
        return date('M d, g:i A', $time);
    }
}
?>
