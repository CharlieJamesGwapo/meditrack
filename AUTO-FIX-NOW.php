<?php
/**
 * AUTOMATIC FIX FOR PASSWORD RESET
 * Just open this file in your browser and it will fix everything!
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auto-Fix Password Reset - MediTrack</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .pulse { animation: pulse 2s infinite; }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
    </style>
</head>
<body class="bg-gradient-to-br from-green-50 to-blue-50 min-h-screen flex items-center justify-center p-4">
    <div class="max-w-2xl w-full bg-white rounded-2xl shadow-2xl p-8">
        
        <?php
        if (!isset($_POST['fix_now'])) {
            // Show the fix button
            ?>
            <div class="text-center">
                <div class="inline-block bg-red-100 p-6 rounded-full mb-6">
                    <i class="fas fa-exclamation-triangle text-red-600 text-6xl"></i>
                </div>
                
                <h1 class="text-4xl font-bold text-gray-900 mb-4">Password Reset is Broken!</h1>
                <p class="text-xl text-gray-600 mb-8">The database table is missing. Let's fix it automatically.</p>
                
                <div class="bg-yellow-50 border-l-4 border-yellow-500 p-6 mb-8 text-left">
                    <h3 class="font-bold text-yellow-900 mb-2">What's Wrong?</h3>
                    <p class="text-yellow-800 text-sm">The <code class="bg-yellow-200 px-2 py-1 rounded">password_resets</code> table doesn't exist in your database.</p>
                </div>
                
                <form method="POST" class="space-y-4">
                    <button type="submit" name="fix_now" 
                            class="w-full bg-gradient-to-r from-green-500 to-green-600 text-white text-2xl font-bold py-6 px-8 rounded-xl hover:from-green-600 hover:to-green-700 transition transform hover:scale-105 shadow-lg">
                        <i class="fas fa-magic mr-3"></i>
                        FIX IT NOW!
                    </button>
                    
                    <p class="text-sm text-gray-500">Click the button above to automatically create the database table</p>
                </form>
            </div>
            <?php
        } else {
            // Execute the fix
            ?>
            <div class="text-center">
                <div class="inline-block bg-blue-100 p-6 rounded-full mb-6 pulse">
                    <i class="fas fa-cog fa-spin text-blue-600 text-6xl"></i>
                </div>
                
                <h1 class="text-3xl font-bold text-gray-900 mb-4">Fixing Password Reset...</h1>
                
                <div class="text-left space-y-4 mb-8">
                    <?php
                    try {
                        // Include database connection
                        require_once 'config/database.php';
                        
                        echo '<div class="flex items-center p-4 bg-blue-50 rounded-lg">';
                        echo '<i class="fas fa-spinner fa-spin text-blue-600 mr-3"></i>';
                        echo '<span class="text-blue-800">Connecting to database...</span>';
                        echo '</div>';
                        
                        $database = new Database();
                        $db = $database->getConnection();
                        
                        if (!$db) {
                            throw new Exception('Failed to connect to database');
                        }
                        
                        echo '<div class="flex items-center p-4 bg-green-50 rounded-lg">';
                        echo '<i class="fas fa-check-circle text-green-600 mr-3"></i>';
                        echo '<span class="text-green-800">✓ Connected to database</span>';
                        echo '</div>';
                        
                        echo '<div class="flex items-center p-4 bg-blue-50 rounded-lg">';
                        echo '<i class="fas fa-spinner fa-spin text-blue-600 mr-3"></i>';
                        echo '<span class="text-blue-800">Creating password_resets table...</span>';
                        echo '</div>';
                        
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
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
                        
                        $db->exec($sql);
                        
                        echo '<div class="flex items-center p-4 bg-green-50 rounded-lg">';
                        echo '<i class="fas fa-check-circle text-green-600 mr-3"></i>';
                        echo '<span class="text-green-800">✓ Table created successfully!</span>';
                        echo '</div>';
                        
                        // Verify table exists
                        $query = "SHOW TABLES LIKE 'password_resets'";
                        $stmt = $db->query($query);
                        $result = $stmt->fetch();
                        
                        if ($result) {
                            echo '<div class="flex items-center p-4 bg-green-50 rounded-lg">';
                            echo '<i class="fas fa-check-circle text-green-600 mr-3"></i>';
                            echo '<span class="text-green-800">✓ Verified table exists</span>';
                            echo '</div>';
                            
                            // Show table structure
                            $query = "DESCRIBE password_resets";
                            $stmt = $db->query($query);
                            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            
                            echo '<div class="mt-6 p-4 bg-gray-50 rounded-lg">';
                            echo '<h3 class="font-bold text-gray-900 mb-3">Table Structure:</h3>';
                            echo '<div class="overflow-x-auto">';
                            echo '<table class="min-w-full text-sm">';
                            echo '<thead class="bg-gray-200"><tr>';
                            echo '<th class="px-4 py-2 text-left">Column</th>';
                            echo '<th class="px-4 py-2 text-left">Type</th>';
                            echo '<th class="px-4 py-2 text-left">Key</th>';
                            echo '</tr></thead><tbody>';
                            
                            foreach ($columns as $col) {
                                echo '<tr class="border-t">';
                                echo '<td class="px-4 py-2 font-mono">' . htmlspecialchars($col['Field']) . '</td>';
                                echo '<td class="px-4 py-2">' . htmlspecialchars($col['Type']) . '</td>';
                                echo '<td class="px-4 py-2">' . htmlspecialchars($col['Key']) . '</td>';
                                echo '</tr>';
                            }
                            
                            echo '</tbody></table></div></div>';
                            
                            // SUCCESS!
                            echo '</div>'; // Close space-y-4
                            
                            echo '<div class="mt-8 p-8 bg-gradient-to-r from-green-500 to-green-600 rounded-xl text-white text-center">';
                            echo '<i class="fas fa-check-circle text-6xl mb-4"></i>';
                            echo '<h2 class="text-3xl font-bold mb-2">SUCCESS!</h2>';
                            echo '<p class="text-xl mb-6">Password reset is now working!</p>';
                            echo '<a href="pages/forgot-password.html" class="inline-block bg-white text-green-600 px-8 py-4 rounded-lg font-bold text-lg hover:bg-green-50 transition">';
                            echo '<i class="fas fa-key mr-2"></i>Test Password Reset Now';
                            echo '</a>';
                            echo '</div>';
                            
                            echo '<div class="mt-6 p-4 bg-blue-50 rounded-lg">';
                            echo '<h3 class="font-bold text-blue-900 mb-2">Next Steps:</h3>';
                            echo '<ol class="list-decimal list-inside text-blue-800 space-y-1 text-sm">';
                            echo '<li>Go to the forgot password page</li>';
                            echo '<li>Enter a registered email address</li>';
                            echo '<li>Check your email for the OTP code</li>';
                            echo '<li>Enter the OTP to verify</li>';
                            echo '<li>Create your new password</li>';
                            echo '</ol>';
                            echo '</div>';
                            
                        } else {
                            throw new Exception('Table was not created properly');
                        }
                        
                    } catch (Exception $e) {
                        echo '<div class="flex items-center p-4 bg-red-50 rounded-lg">';
                        echo '<i class="fas fa-times-circle text-red-600 mr-3"></i>';
                        echo '<span class="text-red-800">✗ Error: ' . htmlspecialchars($e->getMessage()) . '</span>';
                        echo '</div>';
                        
                        echo '</div>'; // Close space-y-4
                        
                        echo '<div class="mt-8 p-6 bg-red-50 border-l-4 border-red-500 rounded">';
                        echo '<h3 class="font-bold text-red-900 mb-2">Manual Fix Required</h3>';
                        echo '<p class="text-red-800 text-sm mb-4">The automatic fix failed. Please run this SQL in phpMyAdmin:</p>';
                        echo '<div class="bg-gray-900 text-green-400 p-4 rounded font-mono text-xs overflow-x-auto">';
                        echo htmlspecialchars($sql);
                        echo '</div>';
                        echo '<a href="http://localhost/phpmyadmin" target="_blank" class="inline-block mt-4 bg-red-600 text-white px-6 py-3 rounded-lg hover:bg-red-700 transition">';
                        echo '<i class="fas fa-external-link-alt mr-2"></i>Open phpMyAdmin';
                        echo '</a>';
                        echo '</div>';
                    }
                    ?>
            </div>
            <?php
        }
        ?>
        
    </div>
</body>
</html>
