<?php
/**
 * Test script to check and display doctors in database
 * Visit: http://localhost/meditrack/test-doctors.php
 */

require_once 'config/database.php';
require_once 'config/config.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Test Doctors - MediTrack</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f0fdf4; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; }
        h1 { color: #10b981; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: #10b981; color: white; }
        .success { color: #10b981; font-weight: bold; }
        .error { color: #ef4444; font-weight: bold; }
        .btn { background: #10b981; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; text-decoration: none; display: inline-block; margin: 10px 5px; }
        .btn:hover { background: #059669; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $database = new Database();
    $db = $database->getConnection();
    
    echo "<h1>🏥 MediTrack - Doctors Database Test</h1>";
    
    // Check database connection
    if (!$db) {
        echo "<p class='error'>❌ Database connection failed!</p>";
        exit;
    }
    
    echo "<p class='success'>✅ Database connected successfully</p>";
    
    // Count total doctors
    $countQuery = "SELECT COUNT(*) as total FROM doctors";
    $countStmt = $db->query($countQuery);
    $totalDoctors = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    echo "<h2>Total Doctors in Database: <span class='success'>{$totalDoctors}</span></h2>";
    
    // Count active doctors
    $activeQuery = "SELECT COUNT(*) as active FROM doctors WHERE status = 'active'";
    $activeStmt = $db->query($activeQuery);
    $activeDoctors = $activeStmt->fetch(PDO::FETCH_ASSOC)['active'];
    
    echo "<h2>Active Doctors: <span class='success'>{$activeDoctors}</span></h2>";
    
    if ($totalDoctors == 0) {
        echo "<p class='error'>⚠️ No doctors found in database!</p>";
        echo "<p>The database needs to be populated with doctor data.</p>";
        echo "<a href='install.php' class='btn'>Run Database Setup</a>";
    } else {
        // Display all doctors
        $query = "SELECT 
                    d.id,
                    d.full_name,
                    d.specialization,
                    d.department,
                    d.qualification,
                    d.experience_years,
                    d.consultation_fee,
                    d.status,
                    u.email
                  FROM doctors d
                  LEFT JOIN users u ON d.user_id = u.id
                  ORDER BY d.full_name ASC";
        
        $stmt = $db->query($query);
        $doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<h2>All Doctors:</h2>";
        echo "<table>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Specialization</th>
                    <th>Department</th>
                    <th>Experience</th>
                    <th>Fee (₱)</th>
                    <th>Status</th>
                    <th>Email</th>
                </tr>";
        
        foreach ($doctors as $doctor) {
            $statusClass = $doctor['status'] == 'active' ? 'success' : 'error';
            echo "<tr>
                    <td>{$doctor['id']}</td>
                    <td><strong>{$doctor['full_name']}</strong></td>
                    <td>{$doctor['specialization']}</td>
                    <td>{$doctor['department']}</td>
                    <td>{$doctor['experience_years']} years</td>
                    <td>₱" . number_format($doctor['consultation_fee'], 2) . "</td>
                    <td class='{$statusClass}'>" . strtoupper($doctor['status']) . "</td>
                    <td>{$doctor['email']}</td>
                  </tr>";
        }
        
        echo "</table>";
    }
    
    // Test API endpoint
    echo "<h2>API Test:</h2>";
    echo "<p>Testing: <code>api/patient/get-doctors.php</code></p>";
    
    $apiUrl = 'http://' . $_SERVER['HTTP_HOST'] . '/meditrack/api/patient/get-doctors.php';
    echo "<p><a href='{$apiUrl}' target='_blank' class='btn'>Test API Endpoint</a></p>";
    
    echo "<h2>Quick Actions:</h2>";
    echo "<a href='pages/patient-dashboard.html' class='btn'>Go to Patient Dashboard</a>";
    echo "<a href='install.php' class='btn'>Re-run Database Setup</a>";
    
} catch (Exception $e) {
    echo "<p class='error'>❌ Error: " . $e->getMessage() . "</p>";
}

echo "</div></body></html>";
?>
