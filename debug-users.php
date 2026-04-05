<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug Users - MediTrack</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-50 p-8">
    <div class="max-w-6xl mx-auto">
        <h1 class="text-3xl font-bold text-center mb-8 text-red-700">
            <i class="fas fa-bug mr-3"></i>Debug Users Database
        </h1>
        
        <div class="bg-white rounded-lg shadow-lg p-6 mb-8">
            <h2 class="text-xl font-semibold mb-4">Check Users Table</h2>
            <button onclick="checkUsers()" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                Check Users
            </button>
            <div id="usersResult"></div>
        </div>

        <div class="bg-white rounded-lg shadow-lg p-6 mb-8">
            <h2 class="text-xl font-semibold mb-4">Create Admin User</h2>
            <button onclick="createAdmin()" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700">
                Create Admin User
            </button>
            <div id="adminResult"></div>
        </div>

        <div class="bg-white rounded-lg shadow-lg p-6">
            <h2 class="text-xl font-semibold mb-4">Test Password Hash</h2>
            <div class="mb-4">
                <input type="text" id="testPassword" value="admin123" class="px-3 py-2 border rounded">
                <button onclick="testPasswordHash()" class="bg-purple-600 text-white px-4 py-2 rounded hover:bg-purple-700 ml-2">
                    Generate Hash
                </button>
            </div>
            <div id="hashResult"></div>
        </div>
    </div>

    <script>
        async function checkUsers() {
            const resultDiv = document.getElementById('usersResult');
            resultDiv.innerHTML = '<div class="text-blue-600">Checking users...</div>';
            
            try {
                const response = await fetch('debug-check-users.php');
                const data = await response.json();
                
                if (data.success) {
                    let html = '<div class="mt-4">';
                    html += '<table class="w-full border-collapse border">';
                    html += '<tr class="bg-gray-100"><th class="border p-2">ID</th><th class="border p-2">Username</th><th class="border p-2">Email</th><th class="border p-2">Role</th><th class="border p-2">Active</th><th class="border p-2">Profile ID</th></tr>';
                    
                    data.users.forEach(user => {
                        html += `<tr>
                            <td class="border p-2">${user.id}</td>
                            <td class="border p-2">${user.username}</td>
                            <td class="border p-2">${user.email}</td>
                            <td class="border p-2">${user.role}</td>
                            <td class="border p-2">${user.is_active}</td>
                            <td class="border p-2">${user.profile_id}</td>
                        </tr>`;
                    });
                    
                    html += '</table></div>';
                    resultDiv.innerHTML = html;
                } else {
                    resultDiv.innerHTML = '<div class="text-red-600">Error: ' + data.message + '</div>';
                }
            } catch (error) {
                resultDiv.innerHTML = '<div class="text-red-600">Error: ' + error.message + '</div>';
            }
        }

        async function createAdmin() {
            const resultDiv = document.getElementById('adminResult');
            resultDiv.innerHTML = '<div class="text-blue-600">Creating admin user...</div>';
            
            try {
                const response = await fetch('debug-create-admin.php');
                const data = await response.json();
                
                if (data.success) {
                    resultDiv.innerHTML = '<div class="text-green-600">✓ Admin user created successfully!</div>';
                    checkUsers(); // Refresh users list
                } else {
                    resultDiv.innerHTML = '<div class="text-red-600">Error: ' + data.message + '</div>';
                }
            } catch (error) {
                resultDiv.innerHTML = '<div class="text-red-600">Error: ' + error.message + '</div>';
            }
        }

        function testPasswordHash() {
            const password = document.getElementById('testPassword').value;
            const hash = btoa(password); // Simple hash for testing
            document.getElementById('hashResult').innerHTML = `
                <div class="mt-4 p-3 bg-gray-100 rounded">
                    <strong>Password:</strong> ${password}<br>
                    <strong>Base64:</strong> ${hash}<br>
                    <strong>PHP password_hash:</strong> <?php echo password_hash('admin123', PASSWORD_DEFAULT); ?>
                </div>
            `;
        }

        // Auto-check users on page load
        window.addEventListener('load', () => {
            checkUsers();
        });
    </script>
</body>
</html>
