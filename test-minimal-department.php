<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Minimal Test Add Department - MediTrack</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="bg-gray-50 p-8">
    <div class="max-w-2xl mx-auto">
        <h1 class="text-3xl font-bold text-center mb-8 text-green-700">
            <i class="fas fa-hospital mr-3"></i>Minimal Add Department Test
        </h1>
        
        <div class="bg-white rounded-lg shadow-lg p-6 mb-8">
            <h2 class="text-xl font-semibold mb-4">Test Minimal API</h2>
            
            <form id="minimalForm" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Department Name *</label>
                    <input type="text" id="minName" required class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-green-500" placeholder="e.g., Cardiology">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                    <textarea id="minDescription" rows="3" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-green-500" placeholder="Department description..."></textarea>
                </div>
                
                <div class="flex justify-end space-x-3 mt-6">
                    <button type="button" onclick="clearMinimalForm()" class="px-6 py-2 border rounded-lg text-gray-700 hover:bg-gray-50">Clear</button>
                    <button type="submit" class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
                        <i class="fas fa-save mr-2"></i>Test Minimal API
                    </button>
                </div>
            </form>
        </div>

        <div class="bg-white rounded-lg shadow-lg p-6">
            <h2 class="text-xl font-semibold mb-4">Test Results</h2>
            <div id="minimalResult" class="text-sm"></div>
        </div>
    </div>

    <script>
        document.getElementById('minimalForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const resultDiv = document.getElementById('minimalResult');
            resultDiv.innerHTML = '<div class="text-blue-600">Testing minimal API...</div>';
            
            const formData = {
                name: document.getElementById('minName').value,
                description: document.getElementById('minDescription').value
            };
            
            try {
                console.log('Sending data:', formData);
                
                const response = await fetch('api/departments/minimal-add-department.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(formData)
                });
                
                console.log('Response status:', response.status);
                console.log('Response headers:', [...response.headers.entries()]);
                
                const responseText = await response.text();
                console.log('Raw response:', responseText);
                
                let data;
                try {
                    data = JSON.parse(responseText);
                } catch (parseError) {
                    console.error('JSON parse error:', parseError);
                    data = { success: false, message: 'Invalid JSON response', raw: responseText };
                }
                
                if (data.success) {
                    resultDiv.innerHTML = `
                        <div class="text-green-600 font-semibold">✓ Success!</div>
                        <div class="text-sm mt-2">
                            <div>Message: ${data.message}</div>
                            <div>Department ID: ${data.department_id}</div>
                            <div>Received Data: ${JSON.stringify(data.received_data)}</div>
                        </div>
                        <div class="mt-4">
                            <button onclick="clearMinimalForm()" class="bg-blue-600 text-white px-4 py-2 rounded">
                                Test Another Department
                            </button>
                        </div>
                    `;
                } else {
                    resultDiv.innerHTML = `
                        <div class="text-red-600 font-semibold">✗ Failed!</div>
                        <div class="text-sm mt-2">Error: ${data.message}</div>
                        ${data.error_info ? `<div>Error Info: ${JSON.stringify(data.error_info)}</div>` : ''}
                        <div class="mt-2 p-3 bg-gray-100 rounded">
                            <div class="font-semibold">Raw Response:</div>
                            <pre class="whitespace-pre-wrap text-xs">${responseText}</pre>
                        </div>
                    `;
                }
            } catch (error) {
                resultDiv.innerHTML = `
                    <div class="text-red-600 font-semibold">✗ Network Error!</div>
                    <div class="text-sm mt-2">Error: ${error.message}</div>
                `;
            }
        });
        
        function clearMinimalForm() {
            document.getElementById('minimalForm').reset();
            document.getElementById('minimalResult').innerHTML = '<div class="text-gray-600">Form cleared. Ready to test again.</div>';
        }
        
        // Initialize
        document.getElementById('minimalResult').innerHTML = '<div class="text-gray-600">Ready to test minimal add department API.</div>';
    </script>
</body>
</html>
