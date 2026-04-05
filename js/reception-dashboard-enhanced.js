// Enhanced Reception Dashboard with Real Data Implementation
let currentUser = null;
let html5QrCode = null;
let currentPatientId = null;
let currentSection = 'dashboard';

// Initialize dashboard
document.addEventListener('DOMContentLoaded', async () => {
    currentUser = await checkAuth();
    if (!currentUser) return;

    if (currentUser.role !== 'reception' && currentUser.role !== 'admin') {
        Swal.fire('Access Denied', 'Reception access only.', 'error');
        window.location.href = 'login.html';
        return;
    }

    document.getElementById('userName').textContent = currentUser.full_name || currentUser.username;
    
    setupEventListeners();
    loadTodayAppointments();
    updateStats();
    updateCurrentTime();
    loadContacts();
    
    // Update time every minute
    setInterval(updateCurrentTime, 60000);
});

// Setup event listeners
function setupEventListeners() {
    // Navigation
    document.querySelectorAll('.sidebar-item').forEach(item => {
        item.addEventListener('click', (e) => {
            e.preventDefault();
            const section = item.dataset.section;
            switchSection(section);
        });
    });

    // Mobile menu
    document.getElementById('mobileMenuBtn').addEventListener('click', toggleMobileMenu);
    document.getElementById('mobileOverlay').addEventListener('click', closeMobileMenu);

    // Dashboard functions
    document.getElementById('logoutBtn').addEventListener('click', logout);
    document.getElementById('startScanBtn').addEventListener('click', startScanner);
    document.getElementById('stopScanBtn').addEventListener('click', stopScanner);
    document.getElementById('manualCheckInBtn').addEventListener('click', manualCheckIn);
    document.getElementById('refreshBtn').addEventListener('click', loadTodayAppointments);
    document.getElementById('statusFilter').addEventListener('change', loadTodayAppointments);
    
    // Patient management
    document.getElementById('addPatientBtn').addEventListener('click', showAddPatientModal);
    document.getElementById('searchPatientsBtn').addEventListener('click', searchPatients);
    document.getElementById('patientSearch').addEventListener('keypress', (e) => {
        if (e.key === 'Enter') searchPatients();
    });

    // Communication
    document.getElementById('sendMessageBtn').addEventListener('click', sendMessage);
    document.getElementById('messageInput').addEventListener('keypress', (e) => {
        if (e.key === 'Enter') sendMessage();
    });

    // Account settings
    document.getElementById('profileForm').addEventListener('submit', updateProfile);
    document.getElementById('passwordForm').addEventListener('submit', changePassword);
}

// Section switching
function switchSection(section) {
    // Update active sidebar item
    document.querySelectorAll('.sidebar-item').forEach(item => {
        item.classList.remove('active');
    });
    document.querySelector(`[data-section="${section}"]`).classList.add('active');

    // Hide all sections
    document.querySelectorAll('.content-section').forEach(sec => {
        sec.classList.remove('active');
    });

    // Show selected section
    document.getElementById(`${section}Section`).classList.add('active');

    // Update page title
    const titles = {
        dashboard: 'Dashboard',
        checkin: 'QR Check-in',
        patients: 'Patient Management',
        appointments: 'Appointments',
        communication: 'Communication',
        account: 'Account Settings'
    };
    document.getElementById('pageTitle').textContent = titles[section];

    currentSection = section;

    // Load section-specific data
    switch(section) {
        case 'dashboard':
            loadTodayAppointments();
            break;
        case 'patients':
            loadPatients();
            break;
        case 'appointments':
            loadAllAppointments();
            break;
        case 'communication':
            loadMessages();
            break;
        case 'account':
            loadAccountSettings();
            break;
    }
}

// Mobile menu functions
function toggleMobileMenu() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('mobileOverlay');
    
    sidebar.classList.toggle('open');
    overlay.classList.toggle('hidden');
}

function closeMobileMenu() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('mobileOverlay');
    
    sidebar.classList.remove('open');
    overlay.classList.add('hidden');
}

// Load today's appointments with real data
async function loadTodayAppointments() {
    const today = new Date().toISOString().split('T')[0];
    const status = document.getElementById('statusFilter')?.value || '';
    
    let url = `../api/appointments/list.php?date=${today}`;
    if (status) url += `&status=${status}`;

    try {
        const appointmentsList = document.getElementById('appointmentsList');
        if (appointmentsList) {
            appointmentsList.innerHTML = `
                <div class="text-center py-8">
                    <i class="fas fa-spinner fa-spin text-2xl text-green-600 mb-2"></i>
                    <p class="text-gray-600">Loading appointments...</p>
                </div>
            `;
        }

        const response = await fetch(url);
        const data = await response.json();

        if (data.success) {
            displayAppointments(data.appointments);
            updateStats(data.appointments);
            updateRecentActivity(data.appointments);
        } else {
            if (appointmentsList) {
                appointmentsList.innerHTML = 
                    `<div class="text-center py-8 text-red-600">
                        <i class="fas fa-exclamation-triangle mb-2"></i>
                        <p>Error loading appointments: ${data.message}</p>
                    </div>`;
            }
        }
    } catch (error) {
        console.error('Error loading appointments:', error);
        const appointmentsList = document.getElementById('appointmentsList');
        if (appointmentsList) {
            appointmentsList.innerHTML = 
                `<div class="text-center py-8 text-red-600">
                    <i class="fas fa-exclamation-triangle mb-2"></i>
                    <p>Network error occurred</p>
                </div>`;
        }
    }
}

// Display appointments
function displayAppointments(appointments) {
    const container = document.getElementById('appointmentsList');
    if (!container) return;
    
    if (appointments.length === 0) {
        container.innerHTML = `
            <div class="text-center py-8 text-gray-500">
                <i class="fas fa-calendar-times text-4xl mb-2"></i>
                <p>No appointments found</p>
            </div>
        `;
        return;
    }

    container.innerHTML = appointments.map(apt => `
        <div class="card-professional p-4 mb-4">
            <div class="flex justify-between items-start">
                <div class="flex-1">
                    <div class="flex items-center mb-2">
                        <h4 class="font-semibold text-gray-900">${apt.patient_name}</h4>
                        <span class="ml-2 status-badge status-${apt.status}">${apt.status.replace('_', ' ')}</span>
                    </div>
                    <p class="text-sm text-gray-600">
                        <i class="fas fa-user-md mr-2"></i>Dr. ${apt.doctor_name}
                    </p>
                    <p class="text-sm text-gray-600">
                        <i class="fas fa-clock mr-2"></i>${formatTime(apt.appointment_time)}
                    </p>
                    ${apt.reason_for_visit ? `<p class="text-sm text-gray-600 mt-1"><i class="fas fa-notes-medical mr-2"></i>${apt.reason_for_visit}</p>` : ''}
                </div>
                <div class="ml-4 space-y-2">
                    ${apt.status === 'scheduled' ? `
                        <button onclick="quickCheckIn(${apt.id})" class="btn-primary text-sm">
                            <i class="fas fa-check mr-1"></i>Check In
                        </button>
                    ` : ''}
                    <button onclick="viewPatientDetails(${apt.patient_id})" class="btn-secondary text-sm block">
                        <i class="fas fa-eye mr-1"></i>View Details
                    </button>
                </div>
            </div>
        </div>
    `).join('');
}

// Update statistics with real data
function updateStats(appointments) {
    const stats = {
        total: appointments.length,
        checked_in: appointments.filter(apt => apt.status === 'checked_in').length,
        pending: appointments.filter(apt => apt.status === 'scheduled').length,
        completed: appointments.filter(apt => apt.status === 'completed').length
    };

    const elements = {
        todayCount: stats.total,
        checkedInCount: stats.checked_in,
        pendingCount: stats.pending,
        completedCount: stats.completed
    };

    Object.entries(elements).forEach(([id, value]) => {
        const element = document.getElementById(id);
        if (element) element.textContent = value;
    });
}

// Update recent activity
function updateRecentActivity(appointments) {
    const recentActivity = document.getElementById('recentActivity');
    if (!recentActivity) return;

    const recentCheckins = appointments
        .filter(apt => apt.status === 'checked_in')
        .slice(0, 5);

    if (recentCheckins.length === 0) {
        recentActivity.innerHTML = `
            <div class="text-center py-8 text-gray-500">
                <i class="fas fa-clipboard-list text-2xl mb-2"></i>
                <p>No recent activity</p>
            </div>
        `;
        return;
    }

    recentActivity.innerHTML = recentCheckins.map(apt => `
        <div class="flex items-center p-3 bg-gray-50 rounded-lg">
            <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center mr-3">
                <i class="fas fa-check text-green-600 text-sm"></i>
            </div>
            <div class="flex-1">
                <p class="text-sm font-medium">${apt.patient_name} checked in</p>
                <p class="text-xs text-gray-500">Dr. ${apt.doctor_name} • ${formatTime(apt.appointment_time)}</p>
            </div>
        </div>
    `).join('');
}

// QR Scanner functions (same as before)
async function startScanner() {
    try {
        const qrReaderElement = document.getElementById('qr-reader');
        qrReaderElement.classList.remove('hidden');
        
        html5QrCode = new Html5Qrcode("qr-reader");
        
        await html5QrCode.start(
            { facingMode: "environment" },
            {
                fps: 10,
                qrbox: { width: 250, height: 250 }
            },
            (decodedText) => {
                processQRCode(decodedText);
                stopScanner();
            },
            (errorMessage) => {
                // Ignore scanning errors
            }
        );

        document.getElementById('startScanBtn').classList.add('hidden');
        document.getElementById('stopScanBtn').classList.remove('hidden');
        
        showScannerAlert('Camera started. Point at QR code to scan.', 'info');
    } catch (error) {
        console.error('Error starting scanner:', error);
        showScannerAlert('Failed to start camera. Please check permissions.', 'error');
    }
}

async function stopScanner() {
    if (html5QrCode) {
        try {
            await html5QrCode.stop();
            html5QrCode.clear();
            html5QrCode = null;
        } catch (error) {
            console.error('Error stopping scanner:', error);
        }
    }
    
    document.getElementById('qr-reader').classList.add('hidden');
    document.getElementById('startScanBtn').classList.remove('hidden');
    document.getElementById('stopScanBtn').classList.add('hidden');
    hideScannerAlert();
}

// Process QR Code with real data validation
async function processQRCode(tokenHash) {
    try {
        let decodedToken = tokenHash;
        try {
            decodedToken = atob(tokenHash);
        } catch (e) {
            // Not base64, use as is
        }

        showScannerAlert('Validating QR code...', 'info');

        const response = await fetch('../api/appointments/checkin.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ token_hash: decodedToken })
        });

        const data = await response.json();

        if (data.success) {
            Swal.fire({
                title: 'Check-in Successful!',
                text: `${data.appointment.patient_name} has been checked in for their appointment with Dr. ${data.appointment.doctor_name}`,
                icon: 'success',
                timer: 3000,
                showConfirmButton: false
            });
            
            loadTodayAppointments();
            
            setTimeout(() => {
                currentPatientId = data.appointment.patient_id;
                viewPatientDetails(data.appointment.patient_id);
            }, 2000);
            
            hideScannerAlert();
        } else {
            showScannerAlert(data.message || 'Check-in failed', 'error');
        }
    } catch (error) {
        console.error('Error processing QR code:', error);
        showScannerAlert('Error processing QR code', 'error');
    }
}

// Manual check-in
async function manualCheckIn() {
    const tokenHash = document.getElementById('manualToken').value.trim();
    if (!tokenHash) {
        Swal.fire('Error', 'Please enter a QR token', 'error');
        return;
    }
    
    await processQRCode(tokenHash);
    document.getElementById('manualToken').value = '';
}

// Quick check-in
async function quickCheckIn(appointmentId) {
    try {
        const result = await Swal.fire({
            title: 'Confirm Check-in',
            text: 'Are you sure you want to check in this patient?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes, Check In',
            cancelButtonText: 'Cancel'
        });

        if (result.isConfirmed) {
            const response = await fetch('../api/appointments/quick-checkin.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ appointment_id: appointmentId })
            });

            const data = await response.json();

            if (data.success) {
                Swal.fire({
                    title: 'Success!',
                    text: 'Patient checked in successfully',
                    icon: 'success',
                    timer: 2000,
                    showConfirmButton: false
                });
                
                loadTodayAppointments();
                
                setTimeout(() => {
                    currentPatientId = data.appointment.patient_id;
                    viewPatientDetails(data.appointment.patient_id);
                }, 1500);
            } else {
                Swal.fire('Error', data.message || 'Check-in failed', 'error');
            }
        }
    } catch (error) {
        console.error('Error with quick check-in:', error);
        Swal.fire('Error', 'Network error occurred', 'error');
    }
}

// Patient management functions
async function loadPatients() {
    // Implementation for loading patients
    console.log('Loading patients...');
}

async function searchPatients() {
    // Implementation for searching patients
    console.log('Searching patients...');
}

function showAddPatientModal() {
    // Implementation for showing add patient modal
    console.log('Show add patient modal...');
}

// Communication functions
async function loadContacts() {
    // Implementation for loading contacts
    console.log('Loading contacts...');
}

async function loadMessages() {
    // Implementation for loading messages
    console.log('Loading messages...');
}

function sendMessage() {
    // Implementation for sending messages
    console.log('Sending message...');
}

// Account settings functions
async function loadAccountSettings() {
    // Implementation for loading account settings
    console.log('Loading account settings...');
}

async function updateProfile(e) {
    e.preventDefault();
    // Implementation for updating profile
    console.log('Updating profile...');
}

async function changePassword(e) {
    e.preventDefault();
    // Implementation for changing password
    console.log('Changing password...');
}

// Utility functions
function showScannerAlert(message, type) {
    const alertDiv = document.getElementById('scannerAlert');
    if (alertDiv) {
        alertDiv.className = `mb-4 p-4 rounded-lg ${type === 'error' ? 'bg-red-100 text-red-700' : 'bg-blue-100 text-blue-700'}`;
        alertDiv.textContent = message;
        alertDiv.classList.remove('hidden');
    }
}

function hideScannerAlert() {
    const alertDiv = document.getElementById('scannerAlert');
    if (alertDiv) {
        alertDiv.classList.add('hidden');
    }
}

function updateCurrentTime() {
    const now = new Date();
    const timeString = now.toLocaleTimeString('en-US', { 
        hour: '2-digit', 
        minute: '2-digit',
        hour12: true 
    });
    const timeElement = document.getElementById('currentTime');
    if (timeElement) {
        timeElement.textContent = timeString;
    }
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

function calculateAge(birthDate) {
    const today = new Date();
    const birth = new Date(birthDate);
    let age = today.getFullYear() - birth.getFullYear();
    const monthDiff = today.getMonth() - birth.getMonth();
    
    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birth.getDate())) {
        age--;
    }
    
    return age;
}

async function logout() {
    try {
        const response = await fetch('../api/auth/logout.php', { method: 'POST' });
        const data = await response.json();
        
        if (data.success) {
            window.location.href = 'login.html';
        }
    } catch (error) {
        console.error('Logout error:', error);
        window.location.href = 'login.html';
    }
}

// Placeholder functions for features not yet implemented
function viewPatientDetails(patientId) {
    console.log('View patient details:', patientId);
    Swal.fire('Info', 'Patient details feature will be implemented soon', 'info');
}

function loadAllAppointments() {
    console.log('Loading all appointments...');
}
