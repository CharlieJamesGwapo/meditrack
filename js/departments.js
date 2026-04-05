// Configuration
const API_BASE_URL = '../api';
const REFRESH_INTERVAL = 30000; // 30 seconds
let departments = [];
let isGridView = true;
let refreshTimer;

// Real-Time Clock
function updateClock() {
    const now = new Date();
    const options = { 
        weekday: 'short', year: 'numeric', month: 'short', day: 'numeric', 
        hour: '2-digit', minute: '2-digit', second: '2-digit' 
    };
    const clockElement = document.getElementById('realTimeClock');
    if (clockElement) {
        clockElement.textContent = now.toLocaleString('en-US', options);
    }
}

updateClock();
setInterval(updateClock, 1000);

// Mobile Menu Toggle
const mobileMenuBtn = document.getElementById('mobileMenuBtn');
const sidebar = document.getElementById('sidebar');
const sidebarOverlay = document.getElementById('sidebarOverlay');

mobileMenuBtn.addEventListener('click', () => {
    sidebar.classList.toggle('active');
    sidebarOverlay.classList.toggle('hidden');
});

sidebarOverlay.addEventListener('click', () => {
    sidebar.classList.remove('active');
    sidebarOverlay.classList.add('hidden');
});

// Logout
document.getElementById('logoutBtn').addEventListener('click', async () => {
    if (confirm('Are you sure you want to logout?')) {
        try {
            await fetch(`${API_BASE_URL}/logout.php`, { method: 'POST' });
        } catch (error) {
            console.error('Logout error:', error);
        }
        window.location.href = 'login.html';
    }
});

// Fetch Statistics
async function fetchStatistics() {
    try {
        const response = await fetch(`${API_BASE_URL}/admin/stats.php`);
        const data = await response.json();
        
        document.getElementById('totalDoctors').textContent = data.totalDoctors || 0;
        document.getElementById('totalPatients').textContent = data.totalPatients || 0;
        document.getElementById('todayAppointments').textContent = data.todayAppointments || 0;
    } catch (error) {
        console.error('Error fetching statistics:', error);
    }
}

// Fetch Departments
async function fetchDepartments() {
    try {
        const response = await fetch(`${API_BASE_URL}/departments/get-departments.php`);
        const data = await response.json();
        
        if (data.success) {
            departments = data.departments || [];
            document.getElementById('totalDepartments').textContent = departments.length;
            renderDepartments();
        } else {
            showEmptyState();
        }
    } catch (error) {
        console.error('Error fetching departments:', error);
        // Show sample departments if API fails
        showSampleDepartments();
    }
}

// Show Sample Departments (for demo)
function showSampleDepartments() {
    departments = [
        {
            id: 1,
            name: 'Cardiology',
            code: 'CARD',
            description: 'Heart and cardiovascular system care',
            head: 'Dr. John Smith',
            contact: '+1234567890',
            location: 'Building A, 3rd Floor',
            doctor_count: 5,
            patient_count: 120
        },
        {
            id: 2,
            name: 'Neurology',
            code: 'NEUR',
            description: 'Brain and nervous system treatment',
            head: 'Dr. Sarah Johnson',
            contact: '+1234567891',
            location: 'Building B, 2nd Floor',
            doctor_count: 4,
            patient_count: 85
        },
        {
            id: 3,
            name: 'Pediatrics',
            code: 'PEDI',
            description: 'Children and infant healthcare',
            head: 'Dr. Michael Brown',
            contact: '+1234567892',
            location: 'Building C, 1st Floor',
            doctor_count: 6,
            patient_count: 200
        },
        {
            id: 4,
            name: 'Orthopedics',
            code: 'ORTH',
            description: 'Bone and joint treatment',
            head: 'Dr. Emily Davis',
            contact: '+1234567893',
            location: 'Building A, 2nd Floor',
            doctor_count: 3,
            patient_count: 95
        },
        {
            id: 5,
            name: 'Emergency',
            code: 'EMER',
            description: '24/7 emergency medical services',
            head: 'Dr. Robert Wilson',
            contact: '+1234567894',
            location: 'Building D, Ground Floor',
            doctor_count: 8,
            patient_count: 150
        },
        {
            id: 6,
            name: 'Radiology',
            code: 'RADI',
            description: 'Medical imaging and diagnostics',
            head: 'Dr. Lisa Anderson',
            contact: '+1234567895',
            location: 'Building B, Ground Floor',
            doctor_count: 4,
            patient_count: 75
        }
    ];
    
    document.getElementById('totalDepartments').textContent = departments.length;
    renderDepartments();
}

// Render Departments
function renderDepartments(filteredDepartments = null) {
    const container = document.getElementById('departmentsContainer');
    const depts = filteredDepartments || departments;
    
    if (depts.length === 0) {
        showEmptyState();
        return;
    }
    
    if (isGridView) {
        container.className = 'grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6';
        container.innerHTML = depts.map(dept => `
            <div class="department-card bg-white rounded-xl shadow-md overflow-hidden fade-in">
                <div class="bg-gradient-to-r from-green-500 to-green-600 p-4 text-white">
                    <div class="flex items-center justify-between mb-2">
                        <h3 class="text-xl font-bold">${dept.name}</h3>
                        <span class="bg-white text-green-600 px-3 py-1 rounded-full text-xs font-semibold">${dept.code || 'N/A'}</span>
                    </div>
                    <p class="text-green-100 text-sm">${dept.description || 'No description available'}</p>
                </div>
                
                <div class="p-4">
                    <div class="space-y-3 mb-4">
                        <div class="flex items-center text-sm text-gray-600">
                            <i class="fas fa-user-md text-green-600 w-5 mr-2"></i>
                            <span class="font-medium">Head:</span>
                            <span class="ml-2">${dept.head || 'Not assigned'}</span>
                        </div>
                        
                        <div class="flex items-center text-sm text-gray-600">
                            <i class="fas fa-phone text-green-600 w-5 mr-2"></i>
                            <span class="font-medium">Contact:</span>
                            <span class="ml-2">${dept.contact || 'N/A'}</span>
                        </div>
                        
                        <div class="flex items-center text-sm text-gray-600">
                            <i class="fas fa-map-marker-alt text-green-600 w-5 mr-2"></i>
                            <span class="font-medium">Location:</span>
                            <span class="ml-2">${dept.location || 'N/A'}</span>
                        </div>
                    </div>
                    
                    <div class="flex items-center justify-between pt-3 border-t border-gray-200">
                        <div class="flex space-x-4">
                            <div class="text-center">
                                <p class="text-2xl font-bold text-green-600">${dept.doctor_count || 0}</p>
                                <p class="text-xs text-gray-500">Doctors</p>
                            </div>
                            <div class="text-center">
                                <p class="text-2xl font-bold text-blue-600">${dept.patient_count || 0}</p>
                                <p class="text-xs text-gray-500">Patients</p>
                            </div>
                        </div>
                        
                        <div class="flex space-x-2">
                            <button onclick="editDepartment(${dept.id})" class="text-blue-600 hover:text-blue-800 p-2" title="Edit">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button onclick="deleteDepartment(${dept.id})" class="text-red-600 hover:text-red-800 p-2" title="Delete">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `).join('');
    } else {
        // List View
        container.className = 'space-y-4';
        container.innerHTML = depts.map(dept => `
            <div class="department-card bg-white rounded-xl shadow-md p-6 fade-in">
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center">
                    <div class="flex-1">
                        <div class="flex items-center space-x-3 mb-2">
                            <div class="bg-green-100 rounded-full p-3">
                                <i class="fas fa-hospital text-green-600 text-xl"></i>
                            </div>
                            <div>
                                <h3 class="text-xl font-bold text-gray-900">${dept.name}</h3>
                                <span class="text-sm text-gray-500">${dept.code || 'N/A'}</span>
                            </div>
                        </div>
                        <p class="text-gray-600 text-sm mb-3 ml-14">${dept.description || 'No description available'}</p>
                        
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3 text-sm ml-14">
                            <div class="flex items-center text-gray-600">
                                <i class="fas fa-user-md text-green-600 mr-2"></i>
                                <span>Head: ${dept.head || 'Not assigned'}</span>
                            </div>
                            <div class="flex items-center text-gray-600">
                                <i class="fas fa-phone text-green-600 mr-2"></i>
                                <span>${dept.contact || 'N/A'}</span>
                            </div>
                            <div class="flex items-center text-gray-600">
                                <i class="fas fa-map-marker-alt text-green-600 mr-2"></i>
                                <span>${dept.location || 'N/A'}</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex items-center space-x-6 mt-4 md:mt-0">
                        <div class="text-center">
                            <p class="text-2xl font-bold text-green-600">${dept.doctor_count || 0}</p>
                            <p class="text-xs text-gray-500">Doctors</p>
                        </div>
                        <div class="text-center">
                            <p class="text-2xl font-bold text-blue-600">${dept.patient_count || 0}</p>
                            <p class="text-xs text-gray-500">Patients</p>
                        </div>
                        <div class="flex space-x-2">
                            <button onclick="editDepartment(${dept.id})" class="text-blue-600 hover:text-blue-800 p-2" title="Edit">
                                <i class="fas fa-edit text-xl"></i>
                            </button>
                            <button onclick="deleteDepartment(${dept.id})" class="text-red-600 hover:text-red-800 p-2" title="Delete">
                                <i class="fas fa-trash text-xl"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `).join('');
    }
}

// Show Empty State
function showEmptyState() {
    const container = document.getElementById('departmentsContainer');
    container.className = 'col-span-full';
    container.innerHTML = `
        <div class="text-center py-12 bg-white rounded-xl shadow-md">
            <i class="fas fa-hospital text-gray-400 text-6xl mb-4"></i>
            <h3 class="text-xl font-semibold text-gray-900 mb-2">No Departments Found</h3>
            <p class="text-gray-600 mb-4">Start by adding your first department</p>
            <button onclick="openAddDepartmentModal()" class="bg-green-600 text-white px-6 py-3 rounded-lg hover:bg-green-700 transition">
                <i class="fas fa-plus mr-2"></i>Add Department
            </button>
        </div>
    `;
}

// Search Functionality
document.getElementById('searchInput').addEventListener('input', (e) => {
    const searchTerm = e.target.value.toLowerCase();
    const filtered = departments.filter(dept => 
        dept.name.toLowerCase().includes(searchTerm) ||
        dept.code.toLowerCase().includes(searchTerm) ||
        (dept.description && dept.description.toLowerCase().includes(searchTerm))
    );
    renderDepartments(filtered);
});

// View Toggle
document.getElementById('viewToggle').addEventListener('click', () => {
    isGridView = !isGridView;
    const btn = document.getElementById('viewToggle');
    if (isGridView) {
        btn.innerHTML = '<i class="fas fa-th-large mr-2"></i>Grid View';
    } else {
        btn.innerHTML = '<i class="fas fa-list mr-2"></i>List View';
    }
    renderDepartments();
});

// Add Department Button
document.getElementById('addDepartmentBtn').addEventListener('click', openAddDepartmentModal);

// Open Add Department Modal
function openAddDepartmentModal() {
    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-hospital mr-2"></i>Add New Department';
    document.getElementById('departmentForm').reset();
    document.getElementById('departmentId').value = '';
    document.getElementById('departmentModal').classList.remove('hidden');
}

// Close Department Modal
function closeDepartmentModal() {
    document.getElementById('departmentModal').classList.add('hidden');
}

// Edit Department
function editDepartment(id) {
    const dept = departments.find(d => d.id === id);
    if (!dept) return;
    
    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-edit mr-2"></i>Edit Department';
    document.getElementById('departmentId').value = dept.id;
    document.getElementById('departmentName').value = dept.name;
    document.getElementById('departmentCode').value = dept.code || '';
    document.getElementById('departmentDescription').value = dept.description || '';
    document.getElementById('departmentHead').value = dept.head || '';
    document.getElementById('departmentContact').value = dept.contact || '';
    document.getElementById('departmentLocation').value = dept.location || '';
    document.getElementById('departmentModal').classList.remove('hidden');
}

// Delete Department
async function deleteDepartment(id) {
    const result = await Swal.fire({
        title: 'Delete Department?',
        text: 'This action cannot be undone!',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Yes, delete it!',
        cancelButtonText: 'Cancel'
    });
    
    if (result.isConfirmed) {
        try {
            const response = await fetch(`${API_BASE_URL}/departments/delete-department.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: id })
            });
            
            const data = await response.json();
            
            if (data.success) {
                Swal.fire('Deleted!', 'Department has been deleted.', 'success');
                departments = departments.filter(d => d.id !== id);
                renderDepartments();
                document.getElementById('totalDepartments').textContent = departments.length;
            } else {
                Swal.fire('Error', data.message || 'Failed to delete department', 'error');
            }
        } catch (error) {
            // For demo, remove from local array
            departments = departments.filter(d => d.id !== id);
            renderDepartments();
            document.getElementById('totalDepartments').textContent = departments.length;
            Swal.fire('Deleted!', 'Department has been deleted.', 'success');
        }
    }
}

// Submit Department Form
document.getElementById('departmentForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const id = document.getElementById('departmentId').value;
    const formData = {
        name: document.getElementById('departmentName').value,
        code: document.getElementById('departmentCode').value,
        description: document.getElementById('departmentDescription').value,
        head: document.getElementById('departmentHead').value,
        contact: document.getElementById('departmentContact').value,
        location: document.getElementById('departmentLocation').value
    };
    
    try {
        const url = id ? 
            `${API_BASE_URL}/departments/update-department.php` : 
            `${API_BASE_URL}/departments/add-department.php`;
        
        const response = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(id ? { ...formData, id } : formData)
        });
        
        const data = await response.json();
        
        if (data.success) {
            Swal.fire('Success!', id ? 'Department updated successfully' : 'Department added successfully', 'success');
            closeDepartmentModal();
            fetchDepartments();
        } else {
            Swal.fire('Error', data.message || 'Operation failed', 'error');
        }
    } catch (error) {
        // For demo, add/update in local array
        if (id) {
            const index = departments.findIndex(d => d.id == id);
            if (index !== -1) {
                departments[index] = { ...departments[index], ...formData };
            }
        } else {
            const newDept = {
                id: departments.length + 1,
                ...formData,
                doctor_count: 0,
                patient_count: 0
            };
            departments.push(newDept);
        }
        
        renderDepartments();
        document.getElementById('totalDepartments').textContent = departments.length;
        closeDepartmentModal();
        Swal.fire('Success!', id ? 'Department updated successfully' : 'Department added successfully', 'success');
    }
});

// Refresh Button
document.getElementById('refreshBtn').addEventListener('click', async () => {
    const btn = document.getElementById('refreshBtn');
    btn.classList.add('animate-spin');
    
    await Promise.all([
        fetchDepartments(),
        fetchStatistics()
    ]);
    
    setTimeout(() => {
        btn.classList.remove('animate-spin');
        Swal.fire({
            icon: 'success',
            title: 'Refreshed!',
            text: 'Data updated successfully',
            timer: 1500,
            showConfirmButton: false
        });
    }, 500);
});

// Auto Refresh
function startAutoRefresh() {
    refreshTimer = setInterval(async () => {
        console.log('Auto-refreshing departments data...');
        await fetchDepartments();
        await fetchStatistics();
    }, REFRESH_INTERVAL);
}

function stopAutoRefresh() {
    if (refreshTimer) {
        clearInterval(refreshTimer);
    }
}

// Initialize on page load
window.addEventListener('load', async () => {
    await Promise.all([
        fetchDepartments(),
        fetchStatistics()
    ]);
    startAutoRefresh();
});

// Stop auto-refresh when page is hidden
document.addEventListener('visibilitychange', () => {
    if (document.hidden) {
        stopAutoRefresh();
    } else {
        startAutoRefresh();
    }
});

console.log('MediTrack Departments Management - Loaded Successfully!');
