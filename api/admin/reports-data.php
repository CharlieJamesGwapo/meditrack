<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendJSON(['success' => false, 'message' => 'Method not allowed'], 405);
}
if (!isLoggedIn() || !hasRole('admin')) {
    sendJSON(['success' => false, 'message' => 'Unauthorized'], 401);
}

try {
    $database = new Database();
    $db = $database->getConnection();

    $range = sanitizeInput($_GET['range'] ?? $_GET['period'] ?? 'week');

    // Date range filter
    switch ($range) {
        case 'today':
            $dateFilter = "appointment_date = CURDATE()";
            break;
        case 'month':
            $dateFilter = "appointment_date >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)";
            break;
        case 'year':
            $dateFilter = "appointment_date >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)";
            break;
        default: // week
            $dateFilter = "appointment_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
            break;
    }

    // Overview stats
    $stmt = $db->query("SELECT COUNT(*) as total FROM appointments WHERE $dateFilter");
    $totalAppointments = (int)$stmt->fetch()['total'];

    $stmt = $db->query("SELECT COUNT(*) as total FROM patients");
    $totalPatients = (int)$stmt->fetch()['total'];

    // Status distribution
    $stmt = $db->query("SELECT status, COUNT(*) as count FROM appointments WHERE $dateFilter GROUP BY status ORDER BY count DESC");
    $statusRows = $stmt->fetchAll();
    $statusLabels = [];
    $statusData = [];
    foreach ($statusRows as $row) {
        $statusLabels[] = ucfirst($row['status']);
        $statusData[] = (int)$row['count'];
    }

    // Last 7 days trend
    $stmt = $db->query("SELECT DATE_FORMAT(appointment_date, '%d') as label, COUNT(*) as count FROM appointments WHERE appointment_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) GROUP BY appointment_date ORDER BY appointment_date ASC");
    $trendRows = $stmt->fetchAll();
    $trendLabels = [];
    $trendData = [];
    foreach ($trendRows as $row) {
        $trendLabels[] = $row['label'];
        $trendData[] = (int)$row['count'];
    }

    // Age distribution
    $ageBrackets = ['0-17' => 0, '18-30' => 0, '31-45' => 0, '46-60' => 0, '60+' => 0];
    $stmt = $db->query("SELECT TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) as age FROM patients WHERE date_of_birth IS NOT NULL");
    foreach ($stmt->fetchAll() as $row) {
        $age = (int)$row['age'];
        if ($age < 18)      $ageBrackets['0-17']++;
        elseif ($age <= 30) $ageBrackets['18-30']++;
        elseif ($age <= 45) $ageBrackets['31-45']++;
        elseif ($age <= 60) $ageBrackets['46-60']++;
        else                $ageBrackets['60+']++;
    }

    sendJSON([
        'success' => true,
        'overview' => [
            'totalAppointments' => $totalAppointments,
            'totalPatients' => $totalPatients
        ],
        'statusDistribution' => [
            'labels' => $statusLabels,
            'data' => $statusData
        ],
        'appointmentsTrend' => [
            'labels' => $trendLabels,
            'data' => $trendData
        ],
        'demographics' => [
            'labels' => array_keys($ageBrackets),
            'data' => array_values($ageBrackets)
        ]
    ]);

} catch (Exception $e) {
    error_log("admin reports-data error: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Failed to load report data'], 500);
}
