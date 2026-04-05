<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Test - MediTrack</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <style>
        .test-result { padding: 1rem; border-radius: 0.5rem; margin: 0.5rem 0; }
        .test-pass { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; }
        .test-fail { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; }
        .test-pending { background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; }
    </style>
</head>
<body class="bg-gray-50 p-8">
    <div class="max-w-4xl mx-auto">
        <h1 class="text-3xl font-bold text-center mb-8 text-green-700">
            <i class="fas fa-user-check mr-3"></i>MediTrack Login Test
        </h1>
        
        <div class="bg-white rounded-lg shadow-lg p-6 mb-8">
            <h2 class="text-xl font-semibold mb-4">Test Database Connection</h2>
            <button onclick="testDatabase()" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                Test Database Connection
            </button>
            <div id="dbResult"></div>
        </div>

        <div class="bg-white rounded-lg shadow-lg p-6 mb-8">
            <h2 class="text-xl font-semibold mb-4">Test Login API</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium mb-2">Username</label>
                    <input type="text" id="testUsername" value="admin" class="w-full px-3 py-2 border rounded">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-2">Password</label>
                    <input type="password" id="testPassword" value="admin123" class="w-full px-3 py-2 border rounded">
                </div>
            </div>
            <button onclick="testLogin()" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700">
                Test Login
            </button>
            <div id="loginResult"></div>
        </div>

        <div class="bg-white rounded-lg shadow-lg p-6">
            <h2 class="text-xl font-semibold mb-4">Default Login Credentials</h2>
            <div class="space-y-2 text-sm">
                <div><strong>Admin:</strong> admin / admin123</div>
                <div><strong>Doctor:</strong> dr_juan_santos / admin123</div>
                <div><strong>Patient:</strong> patient_john_smith / admin123</div>
            </div>
        </div>
    </div>

    <script>
        async function testDatabase() {
            const resultDiv = document.getElementById('dbResult');
            resultDiv.innerHTML = '<div class="test-pending">Testing database connection...</div>';
            
            try {
                const response = await fetch('api/auth/check-session.php');
                const data = await response.json();
                
                if (response.ok) {
                    resultDiv.innerHTML = '<div class="test-pass">✓ Database connection successful</div>';
                } else {
                    resultDiv.innerHTML = '<div class="test-fail">✗ Database connection failed: ' + data.message + '</div>';
                }
            } catch (error) {
                resultDiv.innerHTML = '<div class="test-fail">✗ Database connection failed: ' + error.message + '</div>';
            }
        }

        async function testLogin() {
            const resultDiv = document.getElementById('loginResult');
            const username = document.getElementById('testUsername').value;
            const password = document.getElementById('testPassword').value;
            
            resultDiv.innerHTML = '<div class="test-pending">Testing login...</div>';
            
            try {
                // Test debug version first
                console.log('Testing debug login...');
                const debugResponse = await fetch('api/auth/login-debug.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        username: username,
                        password: password
                    })
                });
                
                const debugData = await debugResponse.json();
                console.log('Debug response:', debugData);
                
                if (debugData.success) {
                    resultDiv.innerHTML = `
                        <div class="test-pass">
                            ✓ Login successful!
                            <br>User: ${debugData.user.full_name} (${debugData.user.role})
                            <br>Email: ${debugData.user.email}
                            <br><small>Debug mode - check console for details</small>
                        </div>
                    `;
                } else {
                    resultDiv.innerHTML = `
                        <div class="test-fail">
                            ✗ Login failed: ${debugData.message}
                            ${debugData.debug ? '<br><small>Debug: ' + JSON.stringify(debugData.debug) + '</small>' : ''}
                        </div>
                    `;
                }
                
                // Also test normal login
                console.log('Testing normal login...');
                const normalResponse = await fetch('api/auth/login.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        username: username,
                        password: password
                    })
                });
                
                const normalData = await normalResponse.json();
                console.log('Normal response:', normalData);
                
            } catch (error) {
                resultDiv.innerHTML = '<div class="test-fail">✗ Login failed: ' + error.message + '</div>';
                console.error('Login error:', error);
            }
        }

        // Auto-test database on page load
        window.addEventListener('load', () => {
            testDatabase();
        });
    </script>
</body>
</html>
