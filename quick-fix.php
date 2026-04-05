<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quick Fix - MediTrack</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-50 p-8">
    <div class="max-w-4xl mx-auto">
        <h1 class="text-3xl font-bold text-center mb-8 text-red-700">
            <i class="fas fa-tools mr-3"></i>Quick Database Fix
        </h1>
        
        <div class="bg-white rounded-lg shadow-lg p-6 mb-8">
            <h2 class="text-xl font-semibold mb-4">Step 1: Check Database</h2>
            <button onclick="checkDatabase()" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                Check Database Status
            </button>
            <div id="dbStatus" class="mt-4"></div>
        </div>

        <div class="bg-white rounded-lg shadow-lg p-6 mb-8">
            <h2 class="text-xl font-semibold mb-4">Step 2: Fix Users</h2>
            <button onclick="fixUsers()" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700">
                Create/Update Admin User
            </button>
            <div id="userFix" class="mt-4"></div>
        </div>

        <div class="bg-white rounded-lg shadow-lg p-6">
            <h2 class="text-xl font-semibold mb-4">Step 3: Test Login</h2>
            <button onclick="testLogin()" class="bg-purple-600 text-white px-4 py-2 rounded hover:bg-purple-700">
                Test Admin Login
            </button>
            <div id="loginTest" class="mt-4"></div>
        </div>
    </div>

    <script>
        async function checkDatabase() {
            const statusDiv = document.getElementById('dbStatus');
            statusDiv.innerHTML = '<div class="text-blue-600">Checking database...</div>';
            
            try {
                const response = await fetch('quick-check-db.php');
                const data = await response.json();
                
                if (data.success) {
                    statusDiv.innerHTML = `
                        <div class="text-green-600">✓ Database connected</div>
                        <div class="text-sm mt-2">Users found: ${data.user_count}</div>
                        <div class="text-sm">Admin exists: ${data.admin_exists ? 'Yes' : 'No'}</div>
                    `;
                } else {
                    statusDiv.innerHTML = '<div class="text-red-600">✗ Database error: ' + data.message + '</div>';
                }
            } catch (error) {
                statusDiv.innerHTML = '<div class="text-red-600">✗ Error: ' + error.message + '</div>';
            }
        }

        async function fixUsers() {
            const fixDiv = document.getElementById('userFix');
            fixDiv.innerHTML = '<div class="text-blue-600">Creating admin user...</div>';
            
            try {
                const response = await fetch('quick-fix-users.php');
                const data = await response.json();
                
                if (data.success) {
                    fixDiv.innerHTML = `
                        <div class="text-green-600">✓ Admin user created/updated!</div>
                        <div class="text-sm mt-2">Username: admin</div>
                        <div class="text-sm">Password: admin123</div>
                        <div class="text-sm">Role: admin</div>
                    `;
                } else {
                    fixDiv.innerHTML = '<div class="text-red-600">✗ Error: ' + data.message + '</div>';
                }
            } catch (error) {
                fixDiv.innerHTML = '<div class="text-red-600">✗ Error: ' + error.message + '</div>';
            }
        }

        async function testLogin() {
            const testDiv = document.getElementById('loginTest');
            testDiv.innerHTML = '<div class="text-blue-600">Testing login...</div>';
            
            try {
                const response = await fetch('api/auth/login.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        username: 'admin',
                        password: 'admin123'
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    testDiv.innerHTML = `
                        <div class="text-green-600">✓ Login successful!</div>
                        <div class="text-sm mt-2">User: ${data.user.full_name}</div>
                        <div class="text-sm">Role: ${data.user.role}</div>
                        <div class="text-sm">Email: ${data.user.email}</div>
                    `;
                } else {
                    testDiv.innerHTML = '<div class="text-red-600">✗ Login failed: ' + data.message + '</div>';
                }
            } catch (error) {
                testDiv.innerHTML = '<div class="text-red-600">✗ Error: ' + error.message + '</div>';
            }
        }

        // Auto-check on load
        window.addEventListener('load', () => {
            checkDatabase();
        });
    </script>
</body>
</html>
