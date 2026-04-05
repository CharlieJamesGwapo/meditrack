<?php
// Test database connection and check admin user
require_once 'config/database.php';

echo "<h2>MediTrack Database Test</h2>";

try {
    $database = new Database();
    $db = $database->getConnection();
    
    echo "<p style='color: green;'>✓ Database connection successful!</p>";
    
    // Check if users table exists
    $stmt = $db->query("SHOW TABLES LIKE 'users'");
    if ($stmt->rowCount() > 0) {
        echo "<p style='color: green;'>✓ Users table exists</p>";
        
        // Check for admin user
        $stmt = $db->prepare("SELECT * FROM users WHERE username = 'admin'");
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $user = $stmt->fetch();
            echo "<p style='color: green;'>✓ Admin user found</p>";
            echo "<p>Username: " . $user['username'] . "</p>";
            echo "<p>Email: " . $user['email'] . "</p>";
            echo "<p>Role: " . $user['role'] . "</p>";
            echo "<p>Status: " . $user['status'] . "</p>";
            
            // Test password
            $testPassword = 'admin123';
            if (password_verify($testPassword, $user['password_hash'])) {
                echo "<p style='color: green;'>✓ Password 'admin123' is correct</p>";
            } else {
                echo "<p style='color: red;'>✗ Password verification failed</p>";
                echo "<p style='color: orange;'>Creating new password hash...</p>";
                
                // Update with correct password
                $newHash = password_hash('admin123', PASSWORD_BCRYPT);
                $updateStmt = $db->prepare("UPDATE users SET password_hash = :hash WHERE username = 'admin'");
                $updateStmt->execute([':hash' => $newHash]);
                
                echo "<p style='color: green;'>✓ Password updated! Try logging in again with admin/admin123</p>";
            }
        } else {
            echo "<p style='color: red;'>✗ Admin user not found</p>";
            echo "<p style='color: orange;'>Creating admin user...</p>";
            
            // Create admin user
            $password_hash = password_hash('admin123', PASSWORD_BCRYPT);
            $stmt = $db->prepare("INSERT INTO users (username, email, password_hash, role) VALUES ('admin', 'admin@meditrack.com', :hash, 'admin')");
            $stmt->execute([':hash' => $password_hash]);
            
            echo "<p style='color: green;'>✓ Admin user created! Login with: admin / admin123</p>";
        }
        
        // Show all users
        echo "<h3>All Users in Database:</h3>";
        $stmt = $db->query("SELECT username, email, role, status FROM users");
        $users = $stmt->fetchAll();
        
        if (count($users) > 0) {
            echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
            echo "<tr><th>Username</th><th>Email</th><th>Role</th><th>Status</th></tr>";
            foreach ($users as $u) {
                echo "<tr>";
                echo "<td>" . $u['username'] . "</td>";
                echo "<td>" . $u['email'] . "</td>";
                echo "<td>" . $u['role'] . "</td>";
                echo "<td>" . $u['status'] . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
        
    } else {
        echo "<p style='color: red;'>✗ Users table does not exist</p>";
        echo "<p style='color: orange;'>Please import the database schema:</p>";
        echo "<ol>";
        echo "<li>Open phpMyAdmin: <a href='http://localhost/phpmyadmin' target='_blank'>http://localhost/phpmyadmin</a></li>";
        echo "<li>Click 'Import' tab</li>";
        echo "<li>Choose file: database/schema.sql</li>";
        echo "<li>Click 'Go'</li>";
        echo "</ol>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Database error: " . $e->getMessage() . "</p>";
    echo "<p>Please check:</p>";
    echo "<ul>";
    echo "<li>MySQL is running in XAMPP</li>";
    echo "<li>Database 'meditrack' exists</li>";
    echo "<li>Database credentials in config/database.php are correct</li>";
    echo "</ul>";
}

echo "<hr>";
echo "<p><a href='index.html'>← Back to Home</a> | <a href='pages/login.html'>Go to Login</a></p>";
?>
