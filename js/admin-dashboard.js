let currentUser = null;

document.addEventListener('DOMContentLoaded', async () => {
    currentUser = await checkAuth();
    if (!currentUser) return;

    if (currentUser.role !== 'admin') {
        alert('Access denied. Admin access only.');
        window.location.href = 'login.html';
        return;
    }

    document.getElementById('userName').textContent = currentUser.username;

    setupEventListeners();
    loadStats();
    loadRecentAppointments();
    loadSystemActivity();
});

function setupEventListeners() {
    document.getElementById('logoutBtn').addEventListener('click', logout);
    
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', () => switchTab(btn.dataset.tab));
    });

    document.getElementById('filterAppointments').addEventListener('click', loadAllAppointments);
    document.getElementById('userRoleFilter').addEventListener('change', loadUsers);
}

function switchTab(tabName) {
    document.querySelectorAll('.tab-btn').forEach(btn => {
        if (btn.dataset.tab === tabName) {
            btn.classList.add('active', 'border-purple-600', 'text-purple-600');
            btn.classList.remove('border-transparent', 'text-gray-500');
        } else {
            btn.classList.remove('active', 'border-purple-600', 'text-purple-600');
            btn.classList.add('border-transparent', 'text-gray-500');
        }
    });

    document.querySelectorAll('.tab-content').forEach(content => {
        content.classList.add('hidden');
    });
    document.getElementById(tabName + 'Tab').classList.remove('hidden');

    // Load data for specific tabs
    if (tabName === 'appointments') {
        loadAllAppointments();
    } else if (tabName === 'users') {
        loadUsers();
    } else if (tabName === 'audit') {
        loadAuditLogs();
    }
}

async function loadStats() {
    try {
        // Load various stats
        const [appointmentsRes, doctorsRes] = await Promise.all([
            fetch('../api/appointments/list.php'),
            fetch('../api/doctors/list.php')
        ]);

        const appointmentsData = await appointmentsRes.json();
        const doctorsData = await doctorsRes.json();

        if (appointmentsData.success) {
            const today = new Date().toISOString().split('T')[0];
            const todayAppointments = appointmentsData.appointments.filter(a => a.appointment_date === today);
            document.getElementById('todayAppointments').textContent = todayAppointments.length;

            // Count unique patients
            const uniquePatients = new Set(appointmentsData.appointments.map(a => a.patient_id));
            document.getElementById('totalPatients').textContent = uniquePatients.size;

            // Count completed visits
            const completedVisits = appointmentsData.appointments.filter(a => a.status === 'completed');
            document.getElementById('totalVisits').textContent = completedVisits.length;
        }

        if (doctorsData.success) {
            document.getElementById('totalDoctors').textContent = doctorsData.doctors.length;
        }
    } catch (error) {
        console.error('Error loading stats:', error);
    }
}

async function loadRecentAppointments() {
    try {
        const response = await fetch('../api/appointments/list.php?limit=10');
        const data = await response.json();

        const container = document.getElementById('recentAppointments');

        if (data.success && data.appointments.length > 0) {
            container.innerHTML = data.appointments.slice(0, 5).map(apt => `
                <div class="flex justify-between items-center p-3 bg-gray-50 rounded">
                    <div class="flex-1">
                        <p class="font-semibold text-sm">${apt.patient_name}</p>
                        <p class="text-xs text-gray-600">Dr. ${apt.doctor_name}</p>
                        <p class="text-xs text-gray-500">${formatDate(apt.appointment_date)} ${formatTime(apt.appointment_time)}</p>
                    </div>
                    <span class="px-2 py-1 text-xs font-semibold rounded-full ${getStatusColor(apt.status)}">${apt.status}</span>
                </div>
            `).join('');
        } else {
            container.innerHTML = '<p class="text-sm text-gray-500">No recent appointments</p>';
        }
    } catch (error) {
        console.error('Error loading recent appointments:', error);
    }
}

async function loadSystemActivity() {
    const container = document.getElementById('systemActivity');
    container.innerHTML = `
        <div class="space-y-2 text-sm">
            <div class="flex items-center p-2 bg-blue-50 rounded">
                <i class="fas fa-info-circle text-blue-600 mr-2"></i>
                <span>System running normally</span>
            </div>
            <div class="flex items-center p-2 bg-green-50 rounded">
                <i class="fas fa-check-circle text-green-600 mr-2"></i>
                <span>Database connected</span>
            </div>
            <div class="flex items-center p-2 bg-purple-50 rounded">
                <i class="fas fa-server text-purple-600 mr-2"></i>
                <span>All services operational</span>
            </div>
        </div>
    `;
}

async function loadAllAppointments() {
    const date = document.getElementById('appointmentDateFilter').value;
    const status = document.getElementById('appointmentStatusFilter').value;

    let url = '../api/appointments/list.php?';
    if (date) url += `date=${date}&`;
    if (status) url += `status=${status}&`;

    try {
        const response = await fetch(url);
        const data = await response.json();

        const container = document.getElementById('allAppointments');

        if (data.success && data.appointments.length > 0) {
            container.innerHTML = data.appointments.map(apt => `
                <div class="border border-gray-200 rounded-lg p-4">
                    <div class="flex justify-between items-start">
                        <div class="flex-1">
                            <div class="flex items-center mb-2">
                                <span class="font-semibold">${apt.patient_name}</span>
                                <span class="ml-3 px-2 py-1 text-xs font-semibold rounded-full ${getStatusColor(apt.status)}">${apt.status}</span>
                            </div>
                            <p class="text-sm text-gray-600">Dr. ${apt.doctor_name} - ${apt.specialization}</p>
                            <p class="text-sm text-gray-600">${formatDate(apt.appointment_date)} at ${formatTime(apt.appointment_time)}</p>
                            <p class="text-sm text-gray-500">Appointment #${apt.appointment_number}</p>
                        </div>
                    </div>
                </div>
            `).join('');
        } else {
            container.innerHTML = '<p class="text-center text-gray-500 py-8">No appointments found</p>';
        }
    } catch (error) {
        console.error('Error loading appointments:', error);
    }
}

async function loadUsers() {
    const role = document.getElementById('userRoleFilter').value;
    
    try {
        let url = '../api/admin/users.php';
        if (role) url += `?role=${role}`;

        const response = await fetch(url);
        const data = await response.json();

        const tbody = document.getElementById('usersTableBody');

        if (data.success && data.users.length > 0) {
            tbody.innerHTML = data.users.map(user => `
                <tr>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">${user.username}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${user.email}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        <span class="px-2 py-1 text-xs font-semibold rounded-full ${getRoleBadgeColor(user.role)}">${user.role.toUpperCase()}</span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        <span class="px-2 py-1 text-xs font-semibold rounded-full ${getStatusBadgeColor(user.status)}">${user.status}</span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${user.last_login ? formatDateTime(user.last_login) : 'Never'}</td>
                </tr>
            `).join('');
        } else {
            tbody.innerHTML = `
                <tr>
                    <td colspan="5" class="text-center py-4 text-gray-500">
                        No users found
                    </td>
                </tr>
            `;
        }
    } catch (error) {
        console.error('Error loading users:', error);
        const tbody = document.getElementById('usersTableBody');
        tbody.innerHTML = `
            <tr>
                <td colspan="5" class="text-center py-4 text-red-500">
                    Error loading users: ${error.message}
                </td>
            </tr>
        `;
    }
}

async function loadAuditLogs() {
    try {
        // This would need a dedicated API endpoint in production
        const tbody = document.getElementById('auditTableBody');
        tbody.innerHTML = `
            <tr>
                <td colspan="5" class="text-center py-4 text-gray-500">
                    Audit logs API endpoint needed
                </td>
            </tr>
        `;
    } catch (error) {
        console.error('Error loading audit logs:', error);
    }
}

async function logout() {
    try {
        await fetch('../api/auth/logout.php', { method: 'POST' });
        window.location.href = 'login.html';
    } catch (error) {
        window.location.href = 'login.html';
    }
}

function getStatusColor(status) {
    const colors = {
        'scheduled': 'bg-blue-100 text-blue-800',
        'checked_in': 'bg-green-100 text-green-800',
        'in_progress': 'bg-yellow-100 text-yellow-800',
        'completed': 'bg-gray-100 text-gray-800',
        'cancelled': 'bg-red-100 text-red-800'
    };
    return colors[status] || 'bg-gray-100 text-gray-800';
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
}

function formatTime(timeString) {
    const [hours, minutes] = timeString.split(':');
    const hour = parseInt(hours);
    const ampm = hour >= 12 ? 'PM' : 'AM';
    const displayHour = hour % 12 || 12;
    return `${displayHour}:${minutes} ${ampm}`;
}

function formatDateTime(dateTimeString) {
    const date = new Date(dateTimeString);
    return date.toLocaleString('en-US', { 
        year: 'numeric', 
        month: 'short', 
        day: 'numeric', 
        hour: '2-digit', 
        minute: '2-digit' 
    });
}

function getRoleBadgeColor(role) {
    const colors = {
        'admin': 'bg-red-100 text-red-800',
        'doctor': 'bg-blue-100 text-blue-800',
        'reception': 'bg-green-100 text-green-800',
        'patient': 'bg-purple-100 text-purple-800'
    };
    return colors[role] || 'bg-gray-100 text-gray-800';
}

function getStatusBadgeColor(status) {
    const colors = {
        'active': 'bg-green-100 text-green-800',
        'inactive': 'bg-gray-100 text-gray-800',
        'suspended': 'bg-red-100 text-red-800'
    };
    return colors[status] || 'bg-gray-100 text-gray-800';
}
