<?php
/**
 * One-Click Database Setup for Password Reset
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Password Reset - MediTrack</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #10b981;
            border-bottom: 3px solid #10b981;
            padding-bottom: 10px;
        }
        .success {
            background: #d1fae5;
            border-left: 4px solid #10b981;
            padding: 15px;
            margin: 20px 0;
            border-radius: 5px;
        }
        .error {
            background: #fee;
            border-left: 4px solid #ef4444;
            padding: 15px;
            margin: 20px 0;
            border-radius: 5px;
        }
        .info {
            background: #dbeafe;
            border-left: 4px solid #3b82f6;
            padding: 15px;
            margin: 20px 0;
            border-radius: 5px;
        }
        button {
            background: #10b981;
            color: white;
            border: none;
            padding: 15px 30px;
            font-size: 16px;
            border-radius: 5px;
            cursor: pointer;
            margin: 10px 5px;
        }
        button:hover {
            background: #059669;
        }
        .btn-secondary {
            background: #6b7280;
        }
        .btn-secondary:hover {
            background: #4b5563;
        }
        pre {
            background: #f9fafb;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        table th, table td {
            border: 1px solid #e5e7eb;
            padding: 10px;
            text-align: left;
        }
        table th {
            background: #f9fafb;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Password Reset System Setup</h1>

<?php
if (isset($_POST['setup'])) {
    try {
        require_once 'config/database.php';
        $database = new Database();
        $db = $database->getConnection();
        
        echo "<div class='info'>📊 Creating password_resets table...</div>";
        
        // Create the table
        $sql = "CREATE TABLE IF NOT EXISTS `password_resets` (
          `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
          `email` VARCHAR(255) NOT NULL,
          `otp` VARCHAR(6) NOT NULL,
          `reset_token` VARCHAR(64) NOT NULL,
          `verified` TINYINT(1) NOT NULL DEFAULT 0,
          `verified_at` DATETIME NULL DEFAULT NULL,
          `used` TINYINT(1) NOT NULL DEFAULT 0,
          `used_at` DATETIME NULL DEFAULT NULL,
          `expires_at` DATETIME NOT NULL,
          `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          INDEX `idx_email` (`email`),
          INDEX `idx_reset_token` (`reset_token`),
          INDEX `idx_otp` (`otp`),
          INDEX `idx_expires_at` (`expires_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        COMMENT='Stores OTP codes and reset tokens for password recovery'";
        
        $db->exec($sql);
        
        echo "<div class='success'>✅ Table created successfully!</div>";
        
        // Verify table exists
        $query = "SHOW TABLES LIKE 'password_resets'";
        $stmt = $db->query($query);
        $result = $stmt->fetch();
        
        if ($result) {
            echo "<div class='success'>✅ Verified: password_resets table exists</div>";
            
            // Show table structure
            $query = "DESCRIBE password_resets";
            $stmt = $db->query($query);
            $columns = $stmt->fetchAll();
            
            echo "<h3>📋 Table Structure:</h3>";
            echo "<table>";
            echo "<tr><th>Column</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
            foreach ($columns as $col) {
                echo "<tr>";
                echo "<td><strong>{$col['Field']}</strong></td>";
                echo "<td>{$col['Type']}</td>";
                echo "<td>{$col['Null']}</td>";
                echo "<td>{$col['Key']}</td>";
                echo "<td>" . ($col['Default'] ?? 'NULL') . "</td>";
                echo "</tr>";
            }
            echo "</table>";
            
            echo "<div class='success'>";
            echo "<h3>🎉 Setup Complete!</h3>";
            echo "<p>The password reset system is now ready to use.</p>";
            echo "<p><strong>Next steps:</strong></p>";
            echo "<ol>";
            echo "<li>Test the system: <a href='test-password-reset.php' target='_blank'>Run Tests</a></li>";
            echo "<li>Try password reset: <a href='pages/forgot-password.html' target='_blank'>Forgot Password Page</a></li>";
            echo "</ol>";
            echo "</div>";
        }
        
    } catch (PDOException $e) {
        echo "<div class='error'>";
        echo "<h3>❌ Database Error</h3>";
        echo "<p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
        echo "</div>";
    } catch (Exception $e) {
        echo "<div class='error'>";
        echo "<h3>❌ Error</h3>";
        echo "<p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
        echo "</div>";
    }
} else {
    // Show setup form
    ?>
    
    <div class="info">
        <h3>ℹ️ About This Setup</h3>
        <p>This will create the <code>password_resets</code> table in your database.</p>
        <p><strong>What will be created:</strong></p>
        <ul>
            <li>Table: <code>password_resets</code></li>
            <li>Columns: id, email, otp, reset_token, verified, expires_at, etc.</li>
            <li>Indexes for better performance</li>
        </ul>
    </div>

    <h3>📋 Current Status</h3>
    <?php
    try {
        require_once 'config/database.php';
        $database = new Database();
        $db = $database->getConnection();
        
        echo "<div class='success'>✅ Database connection successful</div>";
        
        // Check if table exists
        $query = "SHOW TABLES LIKE 'password_resets'";
        $stmt = $db->query($query);
        $result = $stmt->fetch();
        
        if ($result) {
            echo "<div class='success'>";
            echo "✅ Table <code>password_resets</code> already exists!<br>";
            echo "You can proceed to test the system.";
            echo "</div>";
            
            // Show existing table structure
            $query = "DESCRIBE password_resets";
            $stmt = $db->query($query);
            $columns = $stmt->fetchAll();
            
            echo "<h3>📋 Existing Table Structure:</h3>";
            echo "<table>";
            echo "<tr><th>Column</th><th>Type</th><th>Null</th><th>Key</th></tr>";
            foreach ($columns as $col) {
                echo "<tr>";
                echo "<td><strong>{$col['Field']}</strong></td>";
                echo "<td>{$col['Type']}</td>";
                echo "<td>{$col['Null']}</td>";
                echo "<td>{$col['Key']}</td>";
                echo "</tr>";
            }
            echo "</table>";
            
            echo "<p><a href='test-password-reset.php'><button class='btn-secondary'>Run Tests</button></a>";
            echo "<a href='pages/forgot-password.html'><button>Try Password Reset</button></a></p>";
            
        } else {
            echo "<div class='error'>";
            echo "❌ Table <code>password_resets</code> does not exist<br>";
            echo "Click the button below to create it.";
            echo "</div>";
            
            echo "<form method='post'>";
            echo "<button type='submit' name='setup'>🚀 Create Table Now</button>";
            echo "</form>";
        }
        
    } catch (Exception $e) {
        echo "<div class='error'>";
        echo "❌ Database connection failed: " . htmlspecialchars($e->getMessage());
        echo "</div>";
    }
    ?>

    <hr style="margin: 30px 0;">
    
    <h3>📚 Manual Setup (Alternative)</h3>
    <p>If you prefer to set up manually, run this SQL in phpMyAdmin:</p>
    <pre><?php echo htmlspecialchars(file_get_contents('setup-password-reset.sql')); ?></pre>
    
<?php } ?>

    </div>
</body>
</html>
