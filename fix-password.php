<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fix Password - MediTrack</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-50 p-8">
    <div class="max-w-4xl mx-auto">
        <h1 class="text-3xl font-bold text-center mb-8 text-orange-700">
            <i class="fas fa-key mr-3"></i>Fix Admin Password
        </h1>
        
        <div class="bg-white rounded-lg shadow-lg p-6 mb-8">
            <h2 class="text-xl font-semibold mb-4">Current Admin User</h2>
            <button onclick="checkCurrent()" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                Check Current Admin
            </button>
            <div id="currentInfo" class="mt-4"></div>
        </div>

        <div class="bg-white rounded-lg shadow-lg p-6 mb-8">
            <h2 class="text-xl font-semibold mb-4">Fix Password</h2>
            <button onclick="fixPassword()" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700">
                Fix Admin Password
            </button>
            <div id="fixResult" class="mt-4"></div>
        </div>

        <div class="bg-white rounded-lg shadow-lg p-6">
            <h2 class="text-xl font-semibold mb-4">Test Login</h2>
            <button onclick="testLogin()" class="bg-purple-600 text-white px-4 py-2 rounded hover:bg-purple-700">
                Test Login (admin/admin123)
            </button>
            <div id="testResult" class="mt-4"></div>
        </div>
    </div>

    <script>
        async function checkCurrent() {
            const infoDiv = document.getElementById('currentInfo');
            infoDiv.innerHTML = '<div class="text-blue-600">Checking current admin...</div>';
            
            try {
                const response = await fetch('check-admin.php');
                const data = await response.json();
                
                if (data.success) {
                    infoDiv.innerHTML = `
                        <div class="text-green-600">✓ Admin user found</div>
                        <div class="text-sm mt-2">ID: ${data.user.id}</div>
                        <div class="text-sm">Username: ${data.user.username}</div>
                        <div class="text-sm">Email: ${data.user.email}</div>
                        <div class="text-sm">Role: ${data.user.role}</div>
                        <div class="text-sm">Active: ${data.user.is_active}</div>
                        <div class="text-sm">Password Hash: ${data.user.password_hash.substring(0, 20)}...</div>
                    `;
                } else {
                    infoDiv.innerHTML = '<div class="text-red-600">✗ Error: ' + data.message + '</div>';
                }
            } catch (error) {
                infoDiv.innerHTML = '<div class="text-red-600">✗ Error: ' + error.message + '</div>';
            }
        }

        async function fixPassword() {
            const fixDiv = document.getElementById('fixResult');
            fixDiv.innerHTML = '<div class="text-blue-600">Fixing password...</div>';
            
            try {
                const response = await fetch('fix-admin-password.php');
                const data = await response.json();
                
                if (data.success) {
                    fixDiv.innerHTML = `
                        <div class="text-green-600">✓ Password fixed!</div>
                        <div class="text-sm mt-2">New hash generated</div>
                        <div class="text-sm">Username: admin</div>
                        <div class="text-sm">Password: admin123</div>
                    `;
                    // Check current again to show new hash
                    setTimeout(checkCurrent, 1000);
                } else {
                    fixDiv.innerHTML = '<div class="text-red-600">✗ Error: ' + data.message + '</div>';
                }
            } catch (error) {
                fixDiv.innerHTML = '<div class="text-red-600">✗ Error: ' + error.message + '</div>';
            }
        }

        async function testLogin() {
            const testDiv = document.getElementById('testResult');
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
                        <div class="text-sm mt-2">Welcome ${data.user.full_name}</div>
                        <div class="text-sm">Role: ${data.user.role}</div>
                        <div class="text-sm">You can now access the system</div>
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
            checkCurrent();
        });
    </script>
</body>
</html>
