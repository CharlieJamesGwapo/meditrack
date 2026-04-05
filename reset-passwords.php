<?php
/**
 * Reset All User Passwords to Default
 * Visit: http://localhost/meditrack/reset-passwords.php
 */

require_once 'config/database.php';
require_once 'config/config.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Reset Passwords - MediTrack</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f0fdf4; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #10b981; }
        .success { color: #10b981; font-weight: bold; background: #dcfce7; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .error { color: #ef4444; font-weight: bold; background: #fee2e2; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .warning { color: #f59e0b; font-weight: bold; background: #fef3c7; padding: 15px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #f59e0b; }
        .btn { background: #10b981; color: white; padding: 12px 24px; border: none; border-radius: 5px; cursor: pointer; text-decoration: none; display: inline-block; margin: 10px 5px; font-size: 16px; }
        .btn:hover { background: #059669; }
        .btn-danger { background: #ef4444; }
        .btn-danger:hover { background: #dc2626; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: #10b981; color: white; }
        code { background: #f3f4f6; padding: 2px 6px; border-radius: 3px; font-family: monospace; }
        .info-box { background: #dbeafe; border-left: 4px solid #3b82f6; padding: 15px; margin: 20px 0; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $database = new Database();
    $db = $database->getConnection();
    
    echo "<h1>🔐 Reset User Passwords</h1>";
    
    if (!$db) {
        echo "<p class='error'>❌ Database connection failed!</p>";
        exit;
    }
    
    echo "<p class='success'>✅ Database connected successfully</p>";
    
    // Check if reset was requested
    if (isset($_POST['reset_passwords'])) {
        echo "<h2>Resetting Passwords...</h2>";
        
        // Default passwords for each role
        $defaultPasswords = [
            'admin' => 'admin123',
            'reception' => 'reception123',
            'dr.smith' => 'doctor123',
            'dr.johnson' => 'doctor123',
            'dr.williams' => 'doctor123',
            'patient1' => 'patient123'
        ];
        
        $resetCount = 0;
        $errors = [];
        
        foreach ($defaultPasswords as $username => $password) {
            try {
                // Generate password hash
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                
                // Update user password
                $updateQuery = "UPDATE users SET password_hash = :password_hash WHERE username = :username";
                $updateStmt = $db->prepare($updateQuery);
                $updateStmt->bindParam(':password_hash', $passwordHash);
                $updateStmt->bindParam(':username', $username);
                
                if ($updateStmt->execute()) {
                    if ($updateStmt->rowCount() > 0) {
                        echo "<p class='success'>✅ Reset password for: <strong>{$username}</strong> → <code>{$password}</code></p>";
                        $resetCount++;
                    } else {
                        echo "<p class='warning'>⚠️ User not found: <strong>{$username}</strong></p>";
                    }
                }
            } catch (Exception $e) {
                $errors[] = "Error resetting {$username}: " . $e->getMessage();
            }
        }
        
        echo "<div class='success' style='font-size: 18px; margin: 30px 0;'>";
        echo "<h3>✅ Password Reset Complete!</h3>";
        echo "<p><strong>{$resetCount}</strong> passwords were successfully reset.</p>";
        echo "</div>";
        
        if (!empty($errors)) {
            echo "<div class='error'>";
            echo "<h4>Errors:</h4>";
            foreach ($errors as $error) {
                echo "<p>• {$error}</p>";
            }
            echo "</div>";
        }
        
        echo "<div class='info-box'>";
        echo "<h3>📋 Updated Credentials:</h3>";
        echo "<table>";
        echo "<tr><th>Username</th><th>Password</th><th>Role</th></tr>";
        echo "<tr><td><code>admin</code></td><td><code>admin123</code></td><td>Admin</td></tr>";
        echo "<tr><td><code>reception</code></td><td><code>reception123</code></td><td>Reception</td></tr>";
        echo "<tr><td><code>dr.smith</code></td><td><code>doctor123</code></td><td>Doctor</td></tr>";
        echo "<tr><td><code>dr.johnson</code></td><td><code>doctor123</code></td><td>Doctor</td></tr>";
        echo "<tr><td><code>dr.williams</code></td><td><code>doctor123</code></td><td>Doctor</td></tr>";
        echo "<tr><td><code>patient1</code></td><td><code>patient123</code></td><td>Patient</td></tr>";
        echo "</table>";
        echo "</div>";
        
        echo "<h3>Next Steps:</h3>";
        echo "<a href='test-login.php' class='btn'>Test Login</a>";
        echo "<a href='pages/login.html' class='btn'>Go to Login Page</a>";
        
    } else {
        // Show current users and reset form
        $query = "SELECT username, email, role, status FROM users ORDER BY role, username";
        $stmt = $db->query($query);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<div class='warning'>";
        echo "<h3>⚠️ WARNING</h3>";
        echo "<p>This will reset passwords for the following default users to their default values:</p>";
        echo "<ul>";
        echo "<li><strong>admin</strong> → admin123</li>";
        echo "<li><strong>reception</strong> → reception123</li>";
        echo "<li><strong>dr.smith</strong> → doctor123</li>";
        echo "<li><strong>dr.johnson</strong> → doctor123</li>";
        echo "<li><strong>dr.williams</strong> → doctor123</li>";
        echo "<li><strong>patient1</strong> → patient123</li>";
        echo "</ul>";
        echo "<p><strong>This action cannot be undone!</strong></p>";
        echo "</div>";
        
        echo "<h2>Current Users in Database:</h2>";
        echo "<table>";
        echo "<tr><th>Username</th><th>Email</th><th>Role</th><th>Status</th></tr>";
        
        foreach ($users as $user) {
            $statusClass = $user['status'] == 'active' ? 'success' : 'error';
            echo "<tr>";
            echo "<td><strong>{$user['username']}</strong></td>";
            echo "<td>{$user['email']}</td>";
            echo "<td>" . strtoupper($user['role']) . "</td>";
            echo "<td class='{$statusClass}'>" . strtoupper($user['status']) . "</td>";
            echo "</tr>";
        }
        
        echo "</table>";
        
        echo "<h2>Reset Passwords</h2>";
        echo "<form method='POST' onsubmit='return confirm(\"Are you sure you want to reset all default user passwords? This cannot be undone!\");'>";
        echo "<button type='submit' name='reset_passwords' class='btn btn-danger' style='font-size: 18px; padding: 15px 30px;'>";
        echo "🔄 Reset All Default Passwords";
        echo "</button>";
        echo "</form>";
        
        echo "<div class='info-box' style='margin-top: 30px;'>";
        echo "<h3>ℹ️ What This Does:</h3>";
        echo "<p>This script will reset the passwords for the default system users (admin, doctors, reception, patient1) to their default values.</p>";
        echo "<p><strong>Users not in the default list will NOT be affected.</strong></p>";
        echo "<p>After resetting, you can login with:</p>";
        echo "<ul>";
        echo "<li>Username: <code>admin</code> | Password: <code>admin123</code></li>";
        echo "<li>Username: <code>dr.smith</code> | Password: <code>doctor123</code></li>";
        echo "<li>Username: <code>patient1</code> | Password: <code>patient123</code></li>";
        echo "</ul>";
        echo "</div>";
    }
    
    echo "<h3>Quick Actions:</h3>";
    echo "<a href='test-login.php' class='btn'>Test Login</a>";
    echo "<a href='pages/login.html' class='btn'>Login Page</a>";
    echo "<a href='pages/register.html' class='btn'>Register New User</a>";
    
} catch (Exception $e) {
    echo "<p class='error'>❌ Error: " . $e->getMessage() . "</p>";
}

echo "</div></body></html>";
?>
