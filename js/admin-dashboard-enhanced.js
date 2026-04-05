// Enhanced Admin Dashboard with Real-Time Data
const API_BASE_URL = '../api';
const REFRESH_INTERVAL = 30000; // 30 seconds
let refreshTimer = null;

// Initialize Dashboard
document.addEventListener('DOMContentLoaded', async () => {
    console.log('🚀 Admin Dashboard Loading...');
    
    // Check authentication
    const currentUser = await checkAuth();
    if (!currentUser || currentUser.role !== 'admin') {
        Swal.fire('Access Denied', 'Admin access only', 'error');
        window.location.href = 'login.html';
        return;
    }
    
    document.getElementById('userName').textContent = currentUser.full_name || currentUser.username;
    
    setupEventListeners();
    await initDashboard();
    startAutoRefresh();
    
    console.log('✅ Admin Dashboard Ready!');
});

// Setup Event Listeners
function setupEventListeners() {
    // Sidebar navigation
    document.querySelectorAll('.nav-item').forEach(item => {
        item.addEventListener('click', (e) => {
            e.preventDefault();
            document.querySelectorAll('.nav-item').forEach(nav => nav.classList.remove('active'));
            item.classList.add('active');
        });
    });
    
    // Mobile menu
    const menuToggle = document.getElementById('menuToggle');
    const sidebar = document.getElementById('sidebar');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    
    if (menuToggle) {
        menuToggle.addEventListener('click', () => {
            sidebar.classList.toggle('active');
            sidebarOverlay.classList.toggle('hidden');
        });
    }
    
    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', () => {
            sidebar.classList.remove('active');
            sidebarOverlay.classList.add('hidden');
        });
    }
    
    // Tab switching
    document.querySelectorAll('.tab-btn').forEach(button => {
        button.addEventListener('click', () => {
            const tabName = button.dataset.tab;
            switchTab(tabName);
        });
    });
    
    // Logout
    document.getElementById('logoutBtn').addEventListener('click', logout);
    
    // Refresh button
    const refreshBtn = document.getElementById('refreshBtn');
    if (refreshBtn) {
        refreshBtn.addEventListener('click', async () => {
            refreshBtn.classList.add('animate-spin');
            await initDashboard();
            setTimeout(() => refreshBtn.classList.remove('animate-spin'), 1000);
        });
    }
}

// Initialize Dashboard Data
async function initDashboard() {
    console.log('📊 Loading dashboard data...');
    
    try {
        await Promise.all([
            fetchDashboardStats(),
            fetchRecentAppointments(),
            fetchSystemActivity(),
            loadAllUsers(),
            loadAllAppointments()
        ]);
        
        showNotification('Dashboard Updated', 'All data refreshed successfully', 'success');
    } catch (error) {
        console.error('Error initializing dashboard:', error);
        showNotification('Error', 'Failed to load some data', 'error');
    }
}

// Fetch Real Dashboard Statistics
async function fetchDashboardStats() {
    try {
        console.log('📈 Fetching real-time stats...');
        const response = await fetch(`${API_BASE_URL}/admin/stats.php`);
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        console.log('Stats received:', data);
        
        // Animate counters with real data
        animateCounter(document.getElementById('totalPatients'), data.totalPatients || 0);
        animateCounter(document.getElementById('totalDoctors'), data.totalDoctors || 0);
        animateCounter(document.getElementById('todayAppointments'), data.todayAppointments || 0);
        animateCounter(document.getElementById('totalVisits'), data.totalVisits || 0);
        
        // Update status indicators
        updateStatusIndicators(data);
        
        console.log('✅ Stats updated with real data');
    } catch (error) {
        console.error('❌ Error fetching stats:', error);
        // Set to 0 if error
        animateCounter(document.getElementById('totalPatients'), 0);
        animateCounter(document.getElementById('totalDoctors'), 0);
        animateCounter(document.getElementById('todayAppointments'), 0);
        animateCounter(document.getElementById('totalVisits'), 0);
    }
}

// Animate Counter with Effects
function animateCounter(element, target) {
    if (!element) return;
    
    const current = parseInt(element.textContent) || 0;
    const duration = 1000; // 1 second
    const steps = 50;
    const increment = (target - current) / steps;
    let step = 0;
    
    const timer = setInterval(() => {
        step++;
        if (step >= steps) {
            element.textContent = target;
            clearInterval(timer);
            // Add pulse effect on complete
            element.parentElement.classList.add('scale-110');
            setTimeout(() => element.parentElement.classList.remove('scale-110'), 200);
        } else {
            element.textContent = Math.floor(current + (increment * step));
        }
    }, duration / steps);
}

// Update Status Indicators
function updateStatusIndicators(data) {
    // Add visual indicators for changes
    const indicators = {
        patients: document.getElementById('totalPatients'),
        doctors: document.getElementById('totalDoctors'),
        appointments: document.getElementById('todayAppointments'),
        visits: document.getElementById('totalVisits')
    };
    
    Object.values(indicators).forEach(el => {
        if (el) {
            el.parentElement.parentElement.classList.add('transition-all', 'duration-300');
        }
    });
}

// Fetch Recent Appointments
async function fetchRecentAppointments() {
    try {
        const response = await fetch(`${API_BASE_URL}/admin/recent-appointments.php`);
        const appointments = await response.json();
        
        const container = document.getElementById('recentAppointments');
        if (!container) return;
        
        if (!appointments || appointments.length === 0) {
            container.innerHTML = `
                <div class="text-center py-8 text-gray-500">
                    <i class="fas fa-calendar-times text-4xl mb-2"></i>
                    <p>No recent appointments</p>
                </div>
            `;
            return;
        }
        
        container.innerHTML = appointments.map(apt => `
            <div class="flex items-center space-x-3 p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-all duration-200 hover:scale-102">
                <div class="w-10 h-10 rounded-full bg-gradient-to-br from-blue-400 to-blue-600 flex items-center justify-center flex-shrink-0 shadow-md">
                    <i class="fas fa-user text-white"></i>
                </div>
                <div class="flex-1">
                    <p class="font-semibold text-sm">${apt.patientName}</p>
                    <p class="text-xs text-gray-500">Dr. ${apt.doctorName} • ${apt.time}</p>
                </div>
                <span class="text-xs px-3 py-1 rounded-full font-medium ${
                    apt.status === 'confirmed' ? 'bg-green-100 text-green-700' :
                    apt.status === 'pending' ? 'bg-yellow-100 text-yellow-700' :
                    'bg-gray-100 text-gray-700'
                }">${apt.status}</span>
            </div>
        `).join('');
        
    } catch (error) {
        console.error('Error fetching appointments:', error);
    }
}

// Fetch System Activity
async function fetchSystemActivity() {
    try {
        const response = await fetch(`${API_BASE_URL}/admin/activity.php`);
        const activities = await response.json();
        
        const container = document.getElementById('systemActivity');
        if (!container) return;
        
        if (!activities || activities.length === 0) {
            const now = new Date().toLocaleString('en-US', {
                month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit'
            });
            
            container.innerHTML = `
                <div class="flex items-start space-x-3 p-3 bg-gradient-to-r from-green-50 to-green-100 rounded-lg border border-green-200">
                    <div class="w-8 h-8 rounded-full bg-green-500 flex items-center justify-center flex-shrink-0 shadow-md">
                        <i class="fas fa-check text-white text-sm"></i>
                    </div>
                    <div>
                        <p class="font-semibold text-sm text-green-900">System Active</p>
                        <p class="text-xs text-green-700">Dashboard loaded successfully</p>
                        <p class="text-xs text-green-600 mt-1">${now}</p>
                    </div>
                </div>
            `;
            return;
        }
        
        container.innerHTML = activities.map(activity => `
            <div class="flex items-start space-x-3 p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-all duration-200">
                <div class="w-8 h-8 rounded-full ${
                    activity.type === 'success' ? 'bg-green-500' :
                    activity.type === 'warning' ? 'bg-yellow-500' :
                    activity.type === 'error' ? 'bg-red-500' :
                    'bg-blue-500'
                } flex items-center justify-center flex-shrink-0 shadow-md">
                    <i class="fas ${
                        activity.type === 'success' ? 'fa-check' :
                        activity.type === 'warning' ? 'fa-exclamation' :
                        activity.type === 'error' ? 'fa-times' :
                        'fa-info'
                    } text-white text-sm"></i>
                </div>
                <div class="flex-1">
                    <p class="font-semibold text-sm">${activity.title}</p>
                    <p class="text-xs text-gray-500">${activity.description}</p>
                    <p class="text-xs text-gray-400 mt-1">${activity.time}</p>
                </div>
            </div>
        `).join('');
        
    } catch (error) {
        console.error('Error fetching activity:', error);
    }
}

// Load All Users with Real Data
async function loadAllUsers() {
    try {
        console.log('👥 Loading users...');
        const response = await fetch(`${API_BASE_URL}/admin/users-simple.php`);
        const data = await response.json();
        
        const users = Array.isArray(data) ? data : (data.users || []);
        console.log(`Found ${users.length} users`);
        
        const tbody = document.getElementById('usersTableBody');
        if (!tbody) return;
        
        if (users.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="5" class="text-center py-8 text-gray-500">
                        <i class="fas fa-users text-4xl mb-2"></i>
                        <p>No users found</p>
                    </td>
                </tr>
            `;
            return;
        }
        
        tbody.innerHTML = users.map((user, index) => `
            <tr class="hover:bg-gray-50 transition-colors duration-150" style="animation: fadeInUp 0.3s ease-out ${index * 0.05}s both">
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="flex items-center">
                        <div class="w-10 h-10 rounded-full bg-gradient-to-br from-purple-400 to-purple-600 flex items-center justify-center text-white font-bold shadow-md">
                            ${user.full_name ? user.full_name.charAt(0).toUpperCase() : 'U'}
                        </div>
                        <div class="ml-4">
                            <div class="text-sm font-medium text-gray-900">${user.full_name || user.username}</div>
                            <div class="text-sm text-gray-500">${user.username}</div>
                        </div>
                    </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${user.email}</td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <span class="px-3 py-1 text-xs font-semibold rounded-full ${
                        user.role === 'admin' ? 'bg-purple-100 text-purple-700' :
                        user.role === 'doctor' ? 'bg-green-100 text-green-700' :
                        user.role === 'reception' ? 'bg-blue-100 text-blue-700' :
                        'bg-gray-100 text-gray-700'
                    }">${user.role}</span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <span class="px-3 py-1 text-xs font-semibold rounded-full ${
                        user.status === 'active' ? 'bg-green-100 text-green-700' :
                        'bg-red-100 text-red-700'
                    }">
                        <i class="fas fa-circle text-xs mr-1"></i>${user.status}
                    </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${user.lastLogin || 'Never'}</td>
            </tr>
        `).join('');
        
    } catch (error) {
        console.error('Error loading users:', error);
    }
}

// Load All Appointments
async function loadAllAppointments() {
    try {
        const response = await fetch(`${API_BASE_URL}/admin/appointments.php`);
        const appointments = await response.json();
        
        const container = document.getElementById('allAppointments');
        if (!container) return;
        
        if (!appointments || appointments.length === 0) {
            container.innerHTML = `
                <div class="text-center py-8 text-gray-500">
                    <i class="fas fa-calendar-times text-4xl mb-2"></i>
                    <p>No appointments found</p>
                </div>
            `;
            return;
        }
        
        container.innerHTML = appointments.map((apt, index) => `
            <div class="border border-gray-200 rounded-lg p-4 hover:shadow-lg transition-all duration-300 hover:scale-102" style="animation: fadeInUp 0.3s ease-out ${index * 0.05}s both">
                <div class="flex justify-between items-start mb-2">
                    <div>
                        <h4 class="font-semibold text-gray-900">${apt.patientName}</h4>
                        <p class="text-sm text-gray-600"><i class="fas fa-user-md mr-1"></i>Dr. ${apt.doctorName}</p>
                    </div>
                    <span class="px-3 py-1 text-xs font-semibold rounded-full ${
                        apt.status === 'scheduled' ? 'bg-blue-100 text-blue-700' :
                        apt.status === 'checked_in' ? 'bg-yellow-100 text-yellow-700' :
                        apt.status === 'completed' ? 'bg-green-100 text-green-700' :
                        'bg-red-100 text-red-700'
                    }">${apt.status}</span>
                </div>
                <div class="flex items-center text-sm text-gray-500 space-x-4">
                    <span><i class="fas fa-calendar mr-1"></i>${apt.date}</span>
                    <span><i class="fas fa-clock mr-1"></i>${apt.time}</span>
                </div>
            </div>
        `).join('');
        
    } catch (error) {
        console.error('Error loading appointments:', error);
    }
}

// Tab Switching
function switchTab(tabName) {
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('active', 'border-purple-600', 'text-purple-600');
        btn.classList.add('border-transparent', 'text-gray-500');
    });
    
    const activeBtn = document.querySelector(`[data-tab="${tabName}"]`);
    if (activeBtn) {
        activeBtn.classList.add('active', 'border-purple-600', 'text-purple-600');
        activeBtn.classList.remove('border-transparent', 'text-gray-500');
    }
    
    document.querySelectorAll('.tab-content').forEach(content => {
        content.classList.add('hidden');
    });
    
    const activeContent = document.getElementById(`${tabName}Tab`);
    if (activeContent) {
        activeContent.classList.remove('hidden');
    }
}

// Auto-refresh Data
function startAutoRefresh() {
    if (refreshTimer) clearInterval(refreshTimer);
    
    refreshTimer = setInterval(async () => {
        console.log('🔄 Auto-refreshing data...');
        await fetchDashboardStats();
        await fetchRecentAppointments();
        await fetchSystemActivity();
    }, REFRESH_INTERVAL);
}

// Show Notification
function showNotification(title, message, type = 'info') {
    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
        didOpen: (toast) => {
            toast.addEventListener('mouseenter', Swal.stopTimer);
            toast.addEventListener('mouseleave', Swal.resumeTimer);
        }
    });
    
    Toast.fire({
        icon: type,
        title: title,
        text: message
    });
}

// Logout
async function logout() {
    const result = await Swal.fire({
        title: 'Logout?',
        text: 'Are you sure you want to logout?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Yes, Logout',
        cancelButtonText: 'Cancel'
    });
    
    if (result.isConfirmed) {
        try {
            await fetch(`${API_BASE_URL}/auth/logout.php`, { method: 'POST' });
        } catch (error) {
            console.error('Logout error:', error);
        }
        window.location.href = 'login.html';
    }
}

// Add CSS animations
const style = document.createElement('style');
style.textContent = `
    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    .hover\\:scale-102:hover {
        transform: scale(1.02);
    }
    
    .scale-110 {
        transform: scale(1.1);
    }
    
    @keyframes spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }
    
    .animate-spin {
        animation: spin 1s linear infinite;
    }
`;
document.head.appendChild(style);
