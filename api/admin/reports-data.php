<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../../config/database.php';
require_once '../../config/config.php';

// Check authentication
if (!isLoggedIn() || getCurrentUserRole() !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
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
    $stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE role = 'doctor'");
    $overview['totalDoctors'] = (int)$stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Total Departments
    $stmt = $db->query("SELECT COUNT(*) as count FROM departments");
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
        SELECT 
            d.name as dept_name,
            COUNT(a.id) as appointment_count 
        FROM departments d
        LEFT JOIN doctors doc ON d.name = doc.department
        LEFT JOIN appointments a ON doc.id = a.doctor_id AND a.appointment_date BETWEEN :start AND :end
        GROUP BY d.id, d.name
        ORDER BY appointment_count DESC
        LIMIT 8
    ");
    $stmt->execute([':start' => $startDate, ':end' => $endDate]);
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($departments as $dept) {
        $deptLabels[] = $dept['dept_name'];
        $deptData[] = (int)$dept['appointment_count'];
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
            u.username as name,
            d.department as department,
            COUNT(DISTINCT a.id) as appointments,
            COUNT(DISTINCT a.patient_id) as patients,
            COALESCE(AVG(CASE WHEN a.status = 'completed' THEN 5 ELSE 4 END), 4.5) as rating
        FROM users u
        LEFT JOIN doctors d ON d.user_id = u.id AND u.role = 'doctor'
        LEFT JOIN appointments a ON d.id = a.doctor_id AND a.appointment_date BETWEEN :start AND :end
        WHERE u.role = 'doctor'
        GROUP BY u.id, u.username, d.department
        HAVING appointments > 0
        ORDER BY appointments DESC, patients DESC
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
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>
