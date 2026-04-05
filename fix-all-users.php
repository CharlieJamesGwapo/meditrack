<?php
require_once 'config/database.php';

echo "<h2>Fix All Users - Complete Setup</h2>";

try {
    $database = new Database();
    $db = $database->getConnection();
    
    echo "<h3>Step 1: Checking Database Tables</h3>";
    
    // Create all necessary tables
    $tables = [
        'users' => "CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) UNIQUE NOT NULL,
            email VARCHAR(100) UNIQUE NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            role ENUM('patient', 'reception', 'doctor', 'admin') NOT NULL DEFAULT 'patient',
            status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            last_login TIMESTAMP NULL,
            INDEX idx_username (username),
            INDEX idx_email (email),
            INDEX idx_role (role)
        ) ENGINE=InnoDB",
        
        'doctors' => "CREATE TABLE IF NOT EXISTS doctors (
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
        ) ENGINE=InnoDB"
    ];
    
    foreach ($tables as $tableName => $createSQL) {
        $db->exec($createSQL);
        echo "<p style='color: green;'>✓ Table '$tableName' created/verified</p>";
    }
    
    echo "<h3>Step 2: Creating/Updating Users</h3>";
    
    // Correct password hash for 'admin123'
    $correctHash = password_hash('admin123', PASSWORD_BCRYPT);
    
    $users = [
        ['username' => 'admin', 'email' => 'admin@meditrack.com', 'role' => 'admin'],
        ['username' => 'reception', 'email' => 'reception@meditrack.com', 'role' => 'reception'],
        ['username' => 'dr.smith', 'email' => 'dr.smith@meditrack.com', 'role' => 'doctor'],
        ['username' => 'dr.johnson', 'email' => 'dr.johnson@meditrack.com', 'role' => 'doctor'],
        ['username' => 'dr.williams', 'email' => 'dr.williams@meditrack.com', 'role' => 'doctor']
    ];
    
    foreach ($users as $userData) {
        // Check if user exists
        $checkStmt = $db->prepare("SELECT id, password_hash FROM users WHERE username = :username");
        $checkStmt->execute([':username' => $userData['username']]);
        
        if ($checkStmt->rowCount() > 0) {
            // Update existing user with correct password
            $user = $checkStmt->fetch();
            $updateStmt = $db->prepare("UPDATE users SET password_hash = :hash, email = :email, role = :role, status = 'active' WHERE username = :username");
            $updateStmt->execute([
                ':hash' => $correctHash,
                ':email' => $userData['email'],
                ':role' => $userData['role'],
                ':username' => $userData['username']
            ]);
            echo "<p style='color: blue;'>↻ Updated user: {$userData['username']} (password reset to 'admin123')</p>";
        } else {
            // Insert new user
            $insertStmt = $db->prepare("INSERT INTO users (username, email, password_hash, role, status) VALUES (:username, :email, :hash, :role, 'active')");
            $insertStmt->execute([
                ':username' => $userData['username'],
                ':email' => $userData['email'],
                ':hash' => $correctHash,
                ':role' => $userData['role']
            ]);
            echo "<p style='color: green;'>✓ Created user: {$userData['username']}</p>";
        }
    }
    
    echo "<h3>Step 3: Creating Doctor Profiles</h3>";
    
    $doctorProfiles = [
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
    
    foreach ($doctorProfiles as $username => $profile) {
        // Get user_id
        $userStmt = $db->prepare("SELECT id FROM users WHERE username = :username");
        $userStmt->execute([':username' => $username]);
        $user = $userStmt->fetch();
        
        if ($user) {
            // Check if doctor profile exists
            $checkDocStmt = $db->prepare("SELECT id FROM doctors WHERE user_id = :user_id");
            $checkDocStmt->execute([':user_id' => $user['id']]);
            
            if ($checkDocStmt->rowCount() > 0) {
                // Update existing profile
                $updateDocStmt = $db->prepare("UPDATE doctors SET 
                    full_name = :full_name,
                    specialization = :specialization,
                    qualification = :qualification,
                    license_number = :license,
                    contact_number = :phone,
                    email = :email,
                    department = :department,
                    consultation_fee = :fee,
                    experience_years = :experience,
                    status = 'active'
                    WHERE user_id = :user_id");
                $updateDocStmt->execute([
                    ':full_name' => $profile['full_name'],
                    ':specialization' => $profile['specialization'],
                    ':qualification' => $profile['qualification'],
                    ':license' => $profile['license'],
                    ':phone' => $profile['phone'],
                    ':email' => $username . '@meditrack.com',
                    ':department' => $profile['department'],
                    ':fee' => $profile['fee'],
                    ':experience' => $profile['experience'],
                    ':user_id' => $user['id']
                ]);
                echo "<p style='color: blue;'>↻ Updated doctor profile: {$profile['full_name']}</p>";
            } else {
                // Insert new profile
                $insertDocStmt = $db->prepare("INSERT INTO doctors (user_id, full_name, specialization, qualification, license_number, contact_number, email, department, consultation_fee, experience_years, status) 
                    VALUES (:user_id, :full_name, :specialization, :qualification, :license, :phone, :email, :department, :fee, :experience, 'active')");
                $insertDocStmt->execute([
                    ':user_id' => $user['id'],
                    ':full_name' => $profile['full_name'],
                    ':specialization' => $profile['specialization'],
                    ':qualification' => $profile['qualification'],
                    ':license' => $profile['license'],
                    ':phone' => $profile['phone'],
                    ':email' => $username . '@meditrack.com',
                    ':department' => $profile['department'],
                    ':fee' => $profile['fee'],
                    ':experience' => $profile['experience']
                ]);
                echo "<p style='color: green;'>✓ Created doctor profile: {$profile['full_name']}</p>";
            }
        }
    }
    
    echo "<h3>Step 4: Verification - Testing Passwords</h3>";
    
    $testPassword = 'admin123';
    $stmt = $db->query("SELECT username, password_hash FROM users");
    $allUsers = $stmt->fetchAll();
    
    foreach ($allUsers as $user) {
        if (password_verify($testPassword, $user['password_hash'])) {
            echo "<p style='color: green;'>✓ Password verified for: {$user['username']}</p>";
        } else {
            echo "<p style='color: red;'>✗ Password FAILED for: {$user['username']}</p>";
        }
    }
    
    echo "<hr>";
    echo "<h3>✅ ALL USERS FIXED AND READY!</h3>";
    
    echo "<div style='background: #e3f2fd; padding: 20px; border-radius: 8px; margin: 20px 0;'>";
    echo "<h4>Login Credentials (All passwords: admin123):</h4>";
    echo "<table border='1' cellpadding='10' style='border-collapse: collapse; background: white;'>";
    echo "<tr><th>Role</th><th>Username</th><th>Password</th><th>Login Link</th></tr>";
    echo "<tr><td><strong>Admin</strong></td><td>admin</td><td>admin123</td><td><a href='pages/login.html' target='_blank'>Login</a></td></tr>";
    echo "<tr><td><strong>Reception</strong></td><td>reception</td><td>admin123</td><td><a href='pages/login.html' target='_blank'>Login</a></td></tr>";
    echo "<tr><td><strong>Doctor</strong></td><td>dr.smith</td><td>admin123</td><td><a href='pages/login.html' target='_blank'>Login</a></td></tr>";
    echo "<tr><td><strong>Doctor</strong></td><td>dr.johnson</td><td>admin123</td><td><a href='pages/login.html' target='_blank'>Login</a></td></tr>";
    echo "<tr><td><strong>Doctor</strong></td><td>dr.williams</td><td>admin123</td><td><a href='pages/login.html' target='_blank'>Login</a></td></tr>";
    echo "</table>";
    echo "</div>";
    
    echo "<h4>All Doctors in System:</h4>";
    $docStmt = $db->query("SELECT d.*, u.username FROM doctors d JOIN users u ON d.user_id = u.id");
    $doctors = $docStmt->fetchAll();
    
    if (count($doctors) > 0) {
        echo "<table border='1' cellpadding='8' style='border-collapse: collapse; background: white;'>";
        echo "<tr><th>Username</th><th>Full Name</th><th>Specialization</th><th>Department</th><th>Fee</th><th>Status</th></tr>";
        foreach ($doctors as $doc) {
            echo "<tr>";
            echo "<td><strong>{$doc['username']}</strong></td>";
            echo "<td>{$doc['full_name']}</td>";
            echo "<td>{$doc['specialization']}</td>";
            echo "<td>{$doc['department']}</td>";
            echo "<td>\${$doc['consultation_fee']}</td>";
            echo "<td style='color: green;'>{$doc['status']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    echo "<hr>";
    echo "<div style='background: #fff3cd; padding: 15px; border-radius: 8px; margin: 20px 0;'>";
    echo "<h4>⚠️ Important:</h4>";
    echo "<p><strong>All passwords have been reset to: admin123</strong></p>";
    echo "<p>You can now login with any of the accounts above!</p>";
    echo "</div>";
    
    echo "<p style='margin-top: 30px;'>";
    echo "<a href='pages/login.html' style='background: #6366f1; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: bold;'>→ Go to Login Page</a>";
    echo "</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red; background: #fee; padding: 15px; border-radius: 8px;'>";
    echo "<strong>✗ Error:</strong> " . $e->getMessage();
    echo "</p>";
    echo "<p>Please make sure:</p>";
    echo "<ul>";
    echo "<li>MySQL is running in XAMPP</li>";
    echo "<li>Database 'meditrack' exists</li>";
    echo "<li>You have imported database/schema.sql</li>";
    echo "</ul>";
}
?>
