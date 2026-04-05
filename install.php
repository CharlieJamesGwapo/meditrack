<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MediTrack Installation</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <div class="min-h-screen flex items-center justify-center py-12 px-4">
        <div class="max-w-2xl w-full">
            <div class="text-center mb-8">
                <i class="fas fa-heartbeat text-purple-600 text-5xl mb-4"></i>
                <h1 class="text-4xl font-bold text-gray-900">MediTrack Installation</h1>
                <p class="text-gray-600 mt-2">Healthcare Management System Setup</p>
            </div>

            <div class="bg-white shadow-lg rounded-lg p-8">
                <?php
                $step = isset($_GET['step']) ? $_GET['step'] : 'check';
                
                if ($step === 'check') {
                    // Step 1: Check requirements
                    ?>
                    <h2 class="text-2xl font-bold mb-6">System Requirements Check</h2>
                    
                    <?php
                    $checks = [
                        'PHP Version >= 7.4' => version_compare(PHP_VERSION, '7.4.0', '>='),
                        'MySQL Extension' => extension_loaded('mysqli') || extension_loaded('pdo_mysql'),
                        'JSON Extension' => extension_loaded('json'),
                        'GD Extension (for QR)' => extension_loaded('gd'),
                        'Config Directory Writable' => is_writable(__DIR__ . '/config'),
                        'Composer Installed' => file_exists(__DIR__ . '/vendor/autoload.php')
                    ];
                    
                    $allPassed = true;
                    foreach ($checks as $check => $passed) {
                        if (!$passed) $allPassed = false;
                        echo '<div class="flex items-center justify-between p-3 mb-2 rounded ' . ($passed ? 'bg-green-50' : 'bg-red-50') . '">';
                        echo '<span class="text-sm">' . $check . '</span>';
                        echo '<i class="fas fa-' . ($passed ? 'check-circle text-green-600' : 'times-circle text-red-600') . '"></i>';
                        echo '</div>';
                    }
                    ?>
                    
                    <?php if (!$allPassed): ?>
                        <div class="mt-6 p-4 bg-yellow-50 border-l-4 border-yellow-400 text-yellow-700">
                            <p class="font-bold">Action Required:</p>
                            <ul class="list-disc ml-5 mt-2 text-sm">
                                <?php if (!$checks['Composer Installed']): ?>
                                    <li>Run <code class="bg-yellow-100 px-2 py-1 rounded">composer install</code> in the project directory</li>
                                <?php endif; ?>
                                <?php if (!$checks['GD Extension (for QR)']): ?>
                                    <li>Enable GD extension in php.ini</li>
                                <?php endif; ?>
                                <?php if (!$checks['Config Directory Writable']): ?>
                                    <li>Make config directory writable</li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    <?php else: ?>
                        <div class="mt-6">
                            <a href="?step=database" class="block w-full bg-purple-600 text-white text-center py-3 px-4 rounded-lg hover:bg-purple-700 font-semibold">
                                Continue to Database Setup <i class="fas fa-arrow-right ml-2"></i>
                            </a>
                        </div>
                    <?php endif; ?>
                    
                <?php } elseif ($step === 'database') { ?>
                    <!-- Step 2: Database Configuration -->
                    <h2 class="text-2xl font-bold mb-6">Database Configuration</h2>
                    
                    <div class="mb-6 p-4 bg-blue-50 border-l-4 border-blue-400 text-blue-700">
                        <p class="font-bold">Database Setup Instructions:</p>
                        <ol class="list-decimal ml-5 mt-2 text-sm space-y-1">
                            <li>Make sure MySQL is running in XAMPP</li>
                            <li>Open phpMyAdmin: <a href="http://localhost/phpmyadmin" target="_blank" class="underline">http://localhost/phpmyadmin</a></li>
                            <li>Click "Import" tab</li>
                            <li>Choose file: <code class="bg-blue-100 px-2 py-1 rounded">database/schema.sql</code></li>
                            <li>Click "Go" to import</li>
                        </ol>
                    </div>

                    <form method="POST" action="?step=test" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Database Host</label>
                            <input type="text" name="db_host" value="localhost" required 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Database Name</label>
                            <input type="text" name="db_name" value="meditrack" required 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Database Username</label>
                            <input type="text" name="db_user" value="root" required 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Database Password</label>
                            <input type="password" name="db_pass" value="" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md">
                            <p class="text-xs text-gray-500 mt-1">Leave empty if no password (XAMPP default)</p>
                        </div>
                        <div class="flex space-x-2">
                            <a href="?step=check" class="flex-1 bg-gray-500 text-white text-center py-3 px-4 rounded-lg hover:bg-gray-600 font-semibold">
                                <i class="fas fa-arrow-left mr-2"></i> Back
                            </a>
                            <button type="submit" class="flex-1 bg-purple-600 text-white py-3 px-4 rounded-lg hover:bg-purple-700 font-semibold">
                                Test Connection <i class="fas fa-arrow-right ml-2"></i>
                            </button>
                        </div>
                    </form>
                    
                <?php } elseif ($step === 'test') {
                    // Step 3: Test database connection
                    $db_host = $_POST['db_host'] ?? 'localhost';
                    $db_name = $_POST['db_name'] ?? 'meditrack';
                    $db_user = $_POST['db_user'] ?? 'root';
                    $db_pass = $_POST['db_pass'] ?? '';
                    
                    try {
                        $conn = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass);
                        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                        
                        // Check if tables exist
                        $stmt = $conn->query("SHOW TABLES");
                        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
                        $requiredTables = ['users', 'patients', 'doctors', 'appointments', 'visits'];
                        $tablesExist = count(array_intersect($requiredTables, $tables)) === count($requiredTables);
                        
                        ?>
                        <h2 class="text-2xl font-bold mb-6">Database Connection Test</h2>
                        
                        <div class="p-4 bg-green-50 border-l-4 border-green-400 text-green-700 mb-6">
                            <p class="font-bold flex items-center">
                                <i class="fas fa-check-circle mr-2"></i> Database Connection Successful!
                            </p>
                        </div>
                        
                        <?php if ($tablesExist): ?>
                            <div class="p-4 bg-green-50 border-l-4 border-green-400 text-green-700 mb-6">
                                <p class="font-bold flex items-center">
                                    <i class="fas fa-check-circle mr-2"></i> Database Tables Found!
                                </p>
                                <p class="text-sm mt-2">All required tables are present in the database.</p>
                            </div>
                            
                            <div class="space-y-4">
                                <h3 class="font-semibold text-lg">Installation Complete! 🎉</h3>
                                <p class="text-gray-600">Your MediTrack system is ready to use.</p>
                                
                                <div class="bg-blue-50 p-4 rounded-lg">
                                    <p class="font-semibold mb-2">Default Login Credentials:</p>
                                    <div class="text-sm space-y-1">
                                        <p><strong>Admin:</strong> admin / admin123</p>
                                        <p><strong>Reception:</strong> reception / admin123</p>
                                        <p><strong>Doctor:</strong> dr.smith / admin123</p>
                                    </div>
                                </div>
                                
                                <div class="flex space-x-2">
                                    <a href="index.html" class="flex-1 bg-purple-600 text-white text-center py-3 px-4 rounded-lg hover:bg-purple-700 font-semibold">
                                        Go to Homepage <i class="fas fa-home ml-2"></i>
                                    </a>
                                    <a href="pages/login.html" class="flex-1 bg-green-600 text-white text-center py-3 px-4 rounded-lg hover:bg-green-700 font-semibold">
                                        Login <i class="fas fa-sign-in-alt ml-2"></i>
                                    </a>
                                </div>
                                
                                <div class="mt-4 p-3 bg-yellow-50 border border-yellow-200 rounded text-sm">
                                    <p class="font-semibold text-yellow-800">Security Reminder:</p>
                                    <p class="text-yellow-700">Please delete or rename this install.php file for security.</p>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="p-4 bg-red-50 border-l-4 border-red-400 text-red-700 mb-6">
                                <p class="font-bold flex items-center">
                                    <i class="fas fa-exclamation-circle mr-2"></i> Database Tables Not Found!
                                </p>
                                <p class="text-sm mt-2">Please import the database schema first.</p>
                            </div>
                            <a href="?step=database" class="block w-full bg-purple-600 text-white text-center py-3 px-4 rounded-lg hover:bg-purple-700 font-semibold">
                                <i class="fas fa-arrow-left mr-2"></i> Back to Database Setup
                            </a>
                        <?php endif; ?>
                        
                        <?php
                    } catch (PDOException $e) {
                        ?>
                        <h2 class="text-2xl font-bold mb-6">Database Connection Test</h2>
                        
                        <div class="p-4 bg-red-50 border-l-4 border-red-400 text-red-700 mb-6">
                            <p class="font-bold flex items-center">
                                <i class="fas fa-times-circle mr-2"></i> Connection Failed!
                            </p>
                            <p class="text-sm mt-2">Error: <?php echo htmlspecialchars($e->getMessage()); ?></p>
                        </div>
                        
                        <div class="mb-6 p-4 bg-yellow-50 border-l-4 border-yellow-400 text-yellow-700">
                            <p class="font-bold">Troubleshooting:</p>
                            <ul class="list-disc ml-5 mt-2 text-sm">
                                <li>Make sure MySQL is running in XAMPP</li>
                                <li>Check if database 'meditrack' exists</li>
                                <li>Verify username and password are correct</li>
                                <li>Import database/schema.sql in phpMyAdmin</li>
                            </ul>
                        </div>
                        
                        <a href="?step=database" class="block w-full bg-purple-600 text-white text-center py-3 px-4 rounded-lg hover:bg-purple-700 font-semibold">
                            <i class="fas fa-arrow-left mr-2"></i> Try Again
                        </a>
                        <?php
                    }
                }
                ?>
            </div>

            <div class="mt-6 text-center text-gray-600 text-sm">
                <p>MediTrack Healthcare Management System v1.0</p>
                <p class="mt-2">
                    <a href="README.md" class="text-purple-600 hover:underline">Documentation</a> | 
                    <a href="SETUP_GUIDE.md" class="text-purple-600 hover:underline">Setup Guide</a>
                </p>
            </div>
        </div>
    </div>
</body>
</html>
