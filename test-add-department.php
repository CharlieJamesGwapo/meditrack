<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Add Department - MediTrack</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="bg-gray-50 p-8">
    <div class="max-w-2xl mx-auto">
        <h1 class="text-3xl font-bold text-center mb-8 text-green-700">
            <i class="fas fa-hospital mr-3"></i>Test Add Department
        </h1>
        
        <div class="bg-white rounded-lg shadow-lg p-6 mb-8">
            <h2 class="text-xl font-semibold mb-4">Add New Department</h2>
            
            <form id="testDepartmentForm" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Department Name *</label>
                        <input type="text" id="testName" required class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-green-500" placeholder="e.g., Cardiology">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Department Code *</label>
                        <input type="text" id="testCode" required class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-green-500" placeholder="e.g., CARD">
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                    <textarea id="testDescription" rows="3" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-green-500" placeholder="Department description..."></textarea>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Head of Department</label>
                        <input type="text" id="testHead" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-green-500" placeholder="Dr. Name">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Contact Number</label>
                        <input type="tel" id="testContact" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-green-500" placeholder="+1234567890">
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Location/Floor</label>
                    <input type="text" id="testLocation" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-green-500" placeholder="e.g., Building A, 3rd Floor">
                </div>
                
                <div class="flex justify-end space-x-3 mt-6">
                    <button type="button" onclick="clearForm()" class="px-6 py-2 border rounded-lg text-gray-700 hover:bg-gray-50">Clear</button>
                    <button type="submit" class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
                        <i class="fas fa-save mr-2"></i>Add Department
                    </button>
                </div>
            </form>
        </div>

        <div class="bg-white rounded-lg shadow-lg p-6">
            <h2 class="text-xl font-semibold mb-4">Test Results</h2>
            <div id="testResult" class="text-sm"></div>
        </div>
    </div>

    <script>
        document.getElementById('testDepartmentForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const resultDiv = document.getElementById('testResult');
            resultDiv.innerHTML = '<div class="text-blue-600">Testing add department...</div>';
            
            const formData = {
                name: document.getElementById('testName').value,
                code: document.getElementById('testCode').value,
                description: document.getElementById('testDescription').value,
                head: document.getElementById('testHead').value,
                contact: document.getElementById('testContact').value,
                location: document.getElementById('testLocation').value
            };
            
            try {
                const response = await fetch('api/departments/debug-add-department.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(formData)
                });
                
                const data = await response.json();
                
                if (data.success) {
                    resultDiv.innerHTML = `
                        <div class="text-green-600 font-semibold">✓ Success!</div>
                        <div class="text-sm mt-2">
                            <div>Message: ${data.message}</div>
                            <div>Department ID: ${data.department_id}</div>
                        </div>
                        ${data.debug ? `
                            <div class="mt-4 p-3 bg-gray-100 rounded text-xs">
                                <div class="font-semibold">Debug Info:</div>
                                <pre class="whitespace-pre-wrap">${JSON.stringify(data.debug, null, 2)}</pre>
                            </div>
                        ` : ''}
                        <div class="mt-4">
                            <button onclick="clearForm()" class="bg-blue-600 text-white px-4 py-2 rounded">
                                Add Another Department
                            </button>
                        </div>
                    `;
                } else {
                    resultDiv.innerHTML = `
                        <div class="text-red-600 font-semibold">✗ Failed!</div>
                        <div class="text-sm mt-2">Error: ${data.message}</div>
                        ${data.debug ? `
                            <div class="mt-4 p-3 bg-gray-100 rounded text-xs">
                                <div class="font-semibold">Debug Info:</div>
                                <pre class="whitespace-pre-wrap">${JSON.stringify(data.debug, null, 2)}</pre>
                            </div>
                        ` : ''}
                    `;
                }
            } catch (error) {
                resultDiv.innerHTML = `
                    <div class="text-red-600 font-semibold">✗ Network Error!</div>
                    <div class="text-sm mt-2">Error: ${error.message}</div>
                `;
            }
        });
        
        function clearForm() {
            document.getElementById('testDepartmentForm').reset();
            document.getElementById('testResult').innerHTML = '<div class="text-gray-600">Form cleared. Ready to test again.</div>';
        }
        
        // Initialize
        document.getElementById('testResult').innerHTML = '<div class="text-gray-600">Ready to test add department functionality.</div>';
    </script>
</body>
</html>
