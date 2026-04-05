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

    $period = sanitizeInput($_GET['period'] ?? 'daily');

    // Appointment stats by period
    switch ($period) {
        case 'weekly':
            $groupBy   = "YEARWEEK(appointment_date,1)";
            $labelExpr = "CONCAT('Week ', WEEK(appointment_date,1), ' ', YEAR(appointment_date))";
            $limit     = 12;
            break;
        case 'monthly':
            $groupBy   = "DATE_FORMAT(appointment_date, '%Y-%m')";
            $labelExpr = "DATE_FORMAT(appointment_date, '%b %Y')";
            $limit     = 12;
            break;
        default: // daily
            $groupBy   = "appointment_date";
            $labelExpr = "appointment_date";
            $limit     = 30;
            break;
    }

    $stmt = $db->query("
        SELECT $labelExpr as label,
               COUNT(*) as total,
               SUM(status='completed') as completed,
               SUM(status='cancelled') as cancelled,
               SUM(status='scheduled') as scheduled
        FROM appointments
        GROUP BY $groupBy
        ORDER BY $groupBy DESC
        LIMIT $limit
    ");
    $appointment_stats = array_reverse($stmt->fetchAll());

    // Gender distribution
    $stmt = $db->query("SELECT gender, COUNT(*) as count FROM patients WHERE gender IS NOT NULL GROUP BY gender");
    $gender_distribution = $stmt->fetchAll();

    // Age distribution
    $age_brackets = ['0-17' => 0, '18-30' => 0, '31-45' => 0, '46-60' => 0, '60+' => 0];
    $stmt = $db->query("SELECT TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) as age FROM patients WHERE date_of_birth IS NOT NULL");
    foreach ($stmt->fetchAll() as $row) {
        $age = (int) $row['age'];
        if ($age < 18)      $age_brackets['0-17']++;
        elseif ($age <= 30) $age_brackets['18-30']++;
        elseif ($age <= 45) $age_brackets['31-45']++;
        elseif ($age <= 60) $age_brackets['46-60']++;
        else                $age_brackets['60+']++;
    }
    $age_distribution = array_map(
        fn($bracket, $count) => ['bracket' => $bracket, 'count' => $count],
        array_keys($age_brackets), array_values($age_brackets)
    );

    // Completion rate
    $stmt = $db->query("SELECT COUNT(*) as total, SUM(status='completed') as completed FROM appointments");
    $row  = $stmt->fetch();
    $completion_rate = $row['total'] > 0 ? round($row['completed'] / $row['total'] * 100, 1) : 0;

    sendJSON([
        'success'            => true,
        'period'             => $period,
        'appointment_stats'  => $appointment_stats,
        'gender_distribution'=> $gender_distribution,
        'age_distribution'   => $age_distribution,
        'completion_rate'    => $completion_rate
    ]);

} catch (Exception $e) {
    error_log("admin reports-data error: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Failed to load report data'], 500);
}
