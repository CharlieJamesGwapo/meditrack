<?php
require_once 'config/database.php';

echo "<h2>Fix Doctors and Reception</h2>";

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Create doctors table if not exists
    $db->exec("CREATE TABLE IF NOT EXISTS doctors (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT UNIQUE,
        full_name VARCHAR(100) NOT NULL,
        specialization VARCHAR(100) NOT NULL,
        qualification VARCHAR(255),
        license_number VARCHAR(50) UNIQUE,
        contact_number VARCHAR(20),
        email VARCHAR(100),
        department VARCHAR(100),
        consultation_fee DECIMAL(10, 2) DEFAULT 0.00,
        experience_years INT DEFAULT 0,
        profile_image VARCHAR(255),
        bio TEXT,
        status ENUM('active', 'on_leave', 'inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_specialization (specialization),
        INDEX idx_department (department)
    ) ENGINE=InnoDB");
    
    echo "<p style='color: green;'>✓ Doctors table created/verified</p>";
    
    // Check and add doctor profiles for existing doctor users
    $stmt = $db->query("SELECT id, username, email FROM users WHERE role = 'doctor'");
    $doctorUsers = $stmt->fetchAll();
    
    $doctorsAdded = 0;
    foreach ($doctorUsers as $user) {
        // Check if doctor profile exists
        $checkStmt = $db->prepare("SELECT id FROM doctors WHERE user_id = :user_id");
        $checkStmt->execute([':user_id' => $user['id']]);
        
        if ($checkStmt->rowCount() === 0) {
            // Add doctor profile based on username
            $doctorData = [
                'dr.smith' => [
                    'full_name' => 'Dr. John Smith',
                    'specialization' => 'Cardiologist',
                    'qualification' => 'MD, DM Cardiology',
                    'license' => 'MED123456',
                    'phone' => '+1234567890',
                    'department' => 'Cardiology',
                    'fee' => 150.00,
                    'experience' => 15
                ],
                'dr.johnson' => [
                    'full_name' => 'Dr. Sarah Johnson',
                    'specialization' => 'General Physician',
                    'qualification' => 'MBBS, MD',
                    'license' => 'MED123457',
                    'phone' => '+1234567891',
                    'department' => 'General Medicine',
                    'fee' => 100.00,
                    'experience' => 10
                ],
                'dr.williams' => [
                    'full_name' => 'Dr. Michael Williams',
                    'specialization' => 'Orthopedic Surgeon',
                    'qualification' => 'MS Orthopedics',
                    'license' => 'MED123458',
                    'phone' => '+1234567892',
                    'department' => 'Orthopedics',
                    'fee' => 200.00,
                    'experience' => 12
                ]
            ];
            
            $data = $doctorData[$user['username']] ?? [
                'full_name' => 'Dr. ' . ucfirst($user['username']),
                'specialization' => 'General Physician',
                'qualification' => 'MBBS',
                'license' => 'MED' . rand(100000, 999999),
                'phone' => '+1234567890',
                'department' => 'General Medicine',
                'fee' => 100.00,
                'experience' => 5
            ];
            
            $insertStmt = $db->prepare("INSERT INTO doctors (user_id, full_name, specialization, qualification, license_number, contact_number, email, department, consultation_fee, experience_years) 
                VALUES (:user_id, :full_name, :specialization, :qualification, :license, :phone, :email, :department, :fee, :experience)");
            
            $insertStmt->execute([
                ':user_id' => $user['id'],
                ':full_name' => $data['full_name'],
                ':specialization' => $data['specialization'],
                ':qualification' => $data['qualification'],
                ':license' => $data['license'],
                ':phone' => $data['phone'],
                ':email' => $user['email'],
                ':department' => $data['department'],
                ':fee' => $data['fee'],
                ':experience' => $data['experience']
            ]);
            
            echo "<p style='color: green;'>✓ Added doctor profile for: {$user['username']} ({$data['full_name']})</p>";
            $doctorsAdded++;
        }
    }
    
    if ($doctorsAdded === 0) {
        echo "<p style='color: blue;'>ℹ All doctor profiles already exist</p>";
    }
    
    // Show all doctors
    echo "<h3>All Doctors in System:</h3>";
    $stmt = $db->query("SELECT d.*, u.username, u.email as user_email FROM doctors d JOIN users u ON d.user_id = u.id");
    $doctors = $stmt->fetchAll();
    
    if (count($doctors) > 0) {
        echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
        echo "<tr><th>Username</th><th>Full Name</th><th>Specialization</th><th>Department</th><th>Fee</th><th>Status</th></tr>";
        foreach ($doctors as $doc) {
            echo "<tr>";
            echo "<td>{$doc['username']}</td>";
            echo "<td>{$doc['full_name']}</td>";
            echo "<td>{$doc['specialization']}</td>";
            echo "<td>{$doc['department']}</td>";
            echo "<td>\${$doc['consultation_fee']}</td>";
            echo "<td>{$doc['status']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No doctors found</p>";
    }
    
    echo "<hr>";
    echo "<h3>✅ All Fixed!</h3>";
    echo "<p>You can now:</p>";
    echo "<ul>";
    echo "<li>Login as doctor: dr.smith / admin123</li>";
    echo "<li>Login as reception: reception / admin123</li>";
    echo "<li>Login as admin: admin / admin123</li>";
    echo "</ul>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Error: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<p><a href='pages/login.html'>← Go to Login</a> | <a href='pages/admin-dashboard.html'>Admin Dashboard</a></p>";
?>
