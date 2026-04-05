<?php
session_start();
require_once '../../config/database.php';
require_once '../../config/config.php';

header('Content-Type: application/json');

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    sendJSON(['success' => false, 'message' => 'Unauthorized'], 401);
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $range = $_GET['range'] ?? 'today';
    
    // Calculate date range
    $startDate = '';
    $endDate = date('Y-m-d');
    
    switch($range) {
        case 'today':
            $startDate = date('Y-m-d');
            break;
        case 'week':
            $startDate = date('Y-m-d', strtotime('-7 days'));
            break;
        case 'month':
            $startDate = date('Y-m-d', strtotime('-30 days'));
            break;
        case 'year':
            $startDate = date('Y-m-d', strtotime('-365 days'));
            break;
        default:
            $startDate = date('Y-m-d');
    }
    
    // Overview Statistics
    $overview = [
        'totalAppointments' => 0,
        'totalPatients' => 0,
        'totalDoctors' => 0,
        'totalDepartments' => 0
    ];
    
    // Total Appointments in range
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM appointments WHERE appointment_date BETWEEN :start AND :end");
    $stmt->execute([':start' => $startDate, ':end' => $endDate]);
    $overview['totalAppointments'] = (int)$stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Total Patients
    $stmt = $db->query("SELECT COUNT(*) as count FROM patients");
    $overview['totalPatients'] = (int)$stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Total Doctors
    $stmt = $db->query("SELECT COUNT(*) as count FROM doctors");
    $overview['totalDoctors'] = (int)$stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Total Departments
    $stmt = $db->query("SELECT COUNT(DISTINCT department) as count FROM doctors WHERE department IS NOT NULL");
    $overview['totalDepartments'] = (int)$stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Appointments Trend (last 7 days)
    $trendLabels = [];
    $trendData = [];
    
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $dayName = date('D', strtotime("-$i days"));
        $trendLabels[] = $dayName;
        
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM appointments WHERE appointment_date = :date");
        $stmt->execute([':date' => $date]);
        $trendData[] = (int)$stmt->fetch(PDO::FETCH_ASSOC)['count'];
    }
    
    $appointmentsTrend = [
        'labels' => $trendLabels,
        'data' => $trendData
    ];
    
    // Status Distribution
    $statusLabels = [];
    $statusData = [];
    
    $stmt = $db->prepare("SELECT status, COUNT(*) as count FROM appointments WHERE appointment_date BETWEEN :start AND :end GROUP BY status");
    $stmt->execute([':start' => $startDate, ':end' => $endDate]);
    $statuses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($statuses as $status) {
        $statusLabels[] = ucfirst($status['status']);
        $statusData[] = (int)$status['count'];
    }
    
    // If no data, provide empty arrays
    if (empty($statusLabels)) {
        $statusLabels = ['No Data'];
        $statusData = [0];
    }
    
    $statusDistribution = [
        'labels' => $statusLabels,
        'data' => $statusData
    ];
    
    // Department Analytics
    $deptLabels = [];
    $deptData = [];
    
    $stmt = $db->prepare("
        SELECT d.department, COUNT(a.id) as count 
        FROM doctors d 
        LEFT JOIN appointments a ON d.id = a.doctor_id AND a.appointment_date BETWEEN :start AND :end
        WHERE d.department IS NOT NULL
        GROUP BY d.department
        ORDER BY count DESC
        LIMIT 5
    ");
    $stmt->execute([':start' => $startDate, ':end' => $endDate]);
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($departments as $dept) {
        $deptLabels[] = $dept['department'];
        $deptData[] = (int)$dept['count'];
    }
    
    // If no data, provide empty arrays
    if (empty($deptLabels)) {
        $deptLabels = ['No Data'];
        $deptData = [0];
    }
    
    $departmentAnalytics = [
        'labels' => $deptLabels,
        'data' => $deptData
    ];
    
    // Patient Demographics (Age Distribution)
    $ageLabels = ['18-25', '26-35', '36-45', '46-55', '56+'];
    $ageData = [0, 0, 0, 0, 0];
    
    $stmt = $db->query("
        SELECT 
            YEAR(CURDATE()) - YEAR(date_of_birth) - (DATE_FORMAT(CURDATE(), '%m%d') < DATE_FORMAT(date_of_birth, '%m%d')) as age
        FROM patients
        WHERE date_of_birth IS NOT NULL
    ");
    $ages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($ages as $row) {
        $age = (int)$row['age'];
        if ($age >= 18 && $age <= 25) $ageData[0]++;
        else if ($age >= 26 && $age <= 35) $ageData[1]++;
        else if ($age >= 36 && $age <= 45) $ageData[2]++;
        else if ($age >= 46 && $age <= 55) $ageData[3]++;
        else if ($age >= 56) $ageData[4]++;
    }
    
    $demographics = [
        'labels' => $ageLabels,
        'data' => $ageData
    ];
    
    // Doctor Performance
    $doctorPerformance = [];
    
    $stmt = $db->prepare("
        SELECT 
            d.full_name as name,
            d.department,
            COUNT(DISTINCT a.id) as appointments,
            COUNT(DISTINCT a.patient_id) as patients,
            COALESCE(AVG(CASE WHEN a.status = 'completed' THEN 5 ELSE 4 END), 4.5) as rating
        FROM doctors d
        LEFT JOIN appointments a ON d.id = a.doctor_id AND a.appointment_date BETWEEN :start AND :end
        GROUP BY d.id, d.full_name, d.department
        HAVING appointments > 0
        ORDER BY appointments DESC
        LIMIT 10
    ");
    $stmt->execute([':start' => $startDate, ':end' => $endDate]);
    $doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($doctors as $doctor) {
        $doctorPerformance[] = [
            'name' => $doctor['name'],
            'department' => $doctor['department'] ?? 'General',
            'appointments' => (int)$doctor['appointments'],
            'patients' => (int)$doctor['patients'],
            'rating' => round((float)$doctor['rating'], 1)
        ];
    }
    
    // Prepare response
    $response = [
        'success' => true,
        'overview' => $overview,
        'appointmentsTrend' => $appointmentsTrend,
        'statusDistribution' => $statusDistribution,
        'departmentAnalytics' => $departmentAnalytics,
        'demographics' => $demographics,
        'doctorPerformance' => $doctorPerformance
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log("Reports data error: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Error fetching reports data: ' . $e->getMessage()], 500);
}
