<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Delete Department - MediTrack</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="bg-gray-50 p-8">
    <div class="max-w-2xl mx-auto">
        <h1 class="text-3xl font-bold text-center mb-8 text-green-700">
            <i class="fas fa-trash mr-3"></i>Test Delete Department
        </h1>
        
        <div class="bg-white rounded-lg shadow-lg p-6 mb-8">
            <h2 class="text-xl font-semibold mb-4">Delete Department by ID</h2>
            
            <form id="deleteForm" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Department ID *</label>
                    <input type="number" id="deptId" required class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-green-500" placeholder="Enter department ID to delete">
                </div>
                
                <div class="flex justify-end space-x-3 mt-6">
                    <button type="button" onclick="loadDepartments()" class="px-6 py-2 border rounded-lg text-gray-700 hover:bg-gray-50">
                        Load Departments
                    </button>
                    <button type="submit" class="px-6 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">
                        <i class="fas fa-trash mr-2"></i>Delete Department
                    </button>
                </div>
            </form>
        </div>

        <div class="bg-white rounded-lg shadow-lg p-6 mb-8">
            <h2 class="text-xl font-semibold mb-4">Current Departments</h2>
            <div id="departmentsList" class="space-y-2"></div>
        </div>

        <div class="bg-white rounded-lg shadow-lg p-6">
            <h2 class="text-xl font-semibold mb-4">Test Results</h2>
            <div id="testResult" class="text-sm"></div>
        </div>
    </div>

    <script>
        async function loadDepartments() {
            try {
                const response = await fetch('api/departments/get-departments.php');
                const data = await response.json();
                
                if (data.success) {
                    const listDiv = document.getElementById('departmentsList');
                    listDiv.innerHTML = data.departments.map(dept => `
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded">
                            <div>
                                <span class="font-semibold">ID: ${dept.id}</span>
                                <span class="ml-4">${dept.name}</span>
                            </div>
                            <button onclick="deleteDepartment(${dept.id})" class="text-red-600 hover:text-red-800">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    `).join('');
                }
            } catch (error) {
                document.getElementById('departmentsList').innerHTML = '<div class="text-red-600">Error loading departments</div>';
            }
        }
        
        async function deleteDepartment(id) {
            const result = await Swal.fire({
                title: 'Delete Department?',
                text: `Delete department with ID: ${id}?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Yes, delete it!',
                cancelButtonText: 'Cancel'
            });
            
            if (result.isConfirmed) {
                try {
                    const response = await fetch('api/departments/delete-department.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ id: id })
                    });
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        Swal.fire('Deleted!', data.message, 'success');
                        loadDepartments(); // Reload list
                    } else {
                        Swal.fire('Error', data.message, 'error');
                    }
                } catch (error) {
                    Swal.fire('Error', 'Network error: ' + error.message, 'error');
                }
            }
        }
        
        document.getElementById('deleteForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const id = document.getElementById('deptId').value;
            if (!id) {
                Swal.fire('Error', 'Please enter a department ID', 'error');
                return;
            }
            
            await deleteDepartment(parseInt(id));
        });
        
        // Load departments on page load
        loadDepartments();
    </script>
</body>
</html>
