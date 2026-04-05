<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Direct Fix - MediTrack</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-50 p-8">
    <div class="max-w-4xl mx-auto">
        <h1 class="text-3xl font-bold text-center mb-8 text-red-700">
            <i class="fas fa-hammer mr-3"></i>Direct Password Fix
        </h1>
        
        <div class="bg-white rounded-lg shadow-lg p-6">
            <h2 class="text-xl font-semibold mb-4">Fix Admin Password Now</h2>
            <p class="text-gray-600 mb-4">This will directly update the admin password to work with "admin123"</p>
            
            <button onclick="directFix()" class="bg-red-600 text-white px-6 py-3 rounded hover:bg-red-700 text-lg">
                <i class="fas fa-wrench mr-2"></i>Fix Admin Password
            </button>
            
            <div id="result" class="mt-6"></div>
        </div>
    </div>

    <script>
        async function directFix() {
            const resultDiv = document.getElementById('result');
            resultDiv.innerHTML = '<div class="text-blue-600 text-lg">Fixing admin password...</div>';
            
            try {
                const response = await fetch('direct-password-fix.php');
                const data = await response.json();
                
                if (data.success) {
                    resultDiv.innerHTML = `
                        <div class="text-green-600 text-lg">✓ Password Fixed Successfully!</div>
                        <div class="mt-4 p-4 bg-green-50 rounded">
                            <div class="font-semibold">Login Credentials:</div>
                            <div>Username: <strong>admin</strong></div>
                            <div>Password: <strong>admin123</strong></div>
                        </div>
                        <div class="mt-4">
                            <button onclick="testLogin()" class="bg-green-600 text-white px-4 py-2 rounded">
                                Test Login Now
                            </button>
                        </div>
                        <div id="testResult" class="mt-4"></div>
                    `;
                } else {
                    resultDiv.innerHTML = '<div class="text-red-600 text-lg">✗ Error: ' + data.message + '</div>';
                }
            } catch (error) {
                resultDiv.innerHTML = '<div class="text-red-600 text-lg">✗ Error: ' + error.message + '</div>';
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
                        <div class="text-green-600">✓ LOGIN SUCCESSFUL!</div>
                        <div class="text-sm mt-2">Welcome ${data.user.full_name}</div>
                        <div class="text-sm">You can now access the main system</div>
                        <div class="mt-2">
                            <a href="index.html" class="bg-blue-600 text-white px-4 py-2 rounded inline-block">
                                Go to Main System
                            </a>
                        </div>
                    `;
                } else {
                    testDiv.innerHTML = '<div class="text-red-600">✗ Login still failed: ' + data.message + '</div>';
                }
            } catch (error) {
                testDiv.innerHTML = '<div class="text-red-600">✗ Error: ' + error.message + '</div>';
            }
        }
    </script>
</body>
</html>
