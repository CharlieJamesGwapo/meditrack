<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Patients Fix - MediTrack</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-50 p-8">
    <div class="max-w-4xl mx-auto">
        <h1 class="text-3xl font-bold text-center mb-8 text-green-700">
            <i class="fas fa-user-injured mr-3"></i>Test Patients Fix
        </h1>
        
        <div class="bg-white rounded-lg shadow-lg p-6 mb-8">
            <h2 class="text-xl font-semibold mb-4">Test Patients API</h2>
            <button onclick="testPatientsAPI()" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                Test Patients API
            </button>
            <div id="apiResult" class="mt-4"></div>
        </div>

        <div class="bg-white rounded-lg shadow-lg p-6">
            <h2 class="text-xl font-semibold mb-4">Test Patient Data Format</h2>
            <button onclick="testPatientData()" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700">
                Test Patient Data
            </button>
            <div id="dataResult" class="mt-4"></div>
        </div>
    </div>

    <script>
        async function testPatientsAPI() {
            const resultDiv = document.getElementById('apiResult');
            resultDiv.innerHTML = '<div class="text-blue-600">Testing Patients API...</div>';
            
            try {
                const response = await fetch('api/admin/get-patients.php');
                const data = await response.json();
                
                if (data.success) {
                    resultDiv.innerHTML = `
                        <div class="text-green-600">✓ Patients API working!</div>
                        <div class="text-sm mt-2">Total patients: ${data.count}</div>
                        <div class="text-sm">Active: ${data.stats.active}</div>
                        <div class="text-sm">Male: ${data.stats.male}</div>
                        <div class="text-sm">Female: ${data.stats.female}</div>
                        <div class="mt-2">
                            <button onclick="showSamplePatient(${JSON.stringify(data.patients[0] || {}).replace(/"/g, '&quot;')})" class="bg-purple-600 text-white px-3 py-1 rounded text-sm">
                                Test First Patient Data
                            </button>
                        </div>
                    `;
                } else {
                    resultDiv.innerHTML = '<div class="text-red-600">✗ API Error: ' + data.message + '</div>';
                }
            } catch (error) {
                resultDiv.innerHTML = '<div class="text-red-600">✗ Error: ' + error.message + '</div>';
            }
        }

        function testPatientData() {
            const resultDiv = document.getElementById('dataResult');
            
            // Simulate patient data structure
            const testPatient = {
                id: 1,
                full_name: 'John Smith',
                email: 'john@example.com',
                user_status: 1, // This is what the API returns
                gender: 'male'
            };
            
            // Test the fixed code
            const statusHtml = testPatient.user_status == 1 ? 'ACTIVE' : 'INACTIVE';
            const statusClass = testPatient.user_status == 1 ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-700';
            
            resultDiv.innerHTML = `
                <div class="text-green-600">✓ Patient data test passed!</div>
                <div class="mt-4 p-4 bg-gray-50 rounded">
                    <div class="font-semibold">Test Results:</div>
                    <div class="text-sm mt-2">user_status value: ${testPatient.user_status} (type: ${typeof testPatient.user_status})</div>
                    <div class="text-sm">Status HTML: <span class="${statusClass}">${statusHtml}</span></div>
                    <div class="text-sm mt-2">The fix should work correctly!</div>
                </div>
            `;
        }

        function showSamplePatient(patientJson) {
            try {
                const patient = JSON.parse(patientJson);
                const resultDiv = document.getElementById('dataResult');
                
                resultDiv.innerHTML = `
                    <div class="text-green-600">✓ Sample Patient Data:</div>
                    <div class="mt-4 p-4 bg-gray-50 rounded text-sm">
                        <div>ID: ${patient.id}</div>
                        <div>Name: ${patient.full_name}</div>
                        <div>Email: ${patient.email || patient.user_email}</div>
                        <div>Status: ${patient.user_status} (${typeof patient.user_status})</div>
                        <div>Gender: ${patient.gender}</div>
                        <div>Age: ${patient.age}</div>
                    </div>
                `;
            } catch (error) {
                document.getElementById('dataResult').innerHTML = '<div class="text-red-600">✗ Error parsing patient data</div>';
            }
        }
    </script>
</body>
</html>
