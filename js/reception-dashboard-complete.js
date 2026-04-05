// Complete Reception Dashboard - All 5 QR Check-in Steps
let currentUser = null;
let html5QrCode = null;
let currentPatientId = null;

// Initialize dashboard
document.addEventListener('DOMContentLoaded', async () => {
    console.log('Initializing reception dashboard...');
    
    currentUser = await checkAuth();
    if (!currentUser) return;

    if (currentUser.role !== 'reception' && currentUser.role !== 'admin') {
        Swal.fire('Access Denied', 'Reception access only.', 'error');
        window.location.href = 'login.html';
        return;
    }

    // Update all userName elements
    document.querySelectorAll('#userName').forEach(el => {
        el.textContent = currentUser.full_name || currentUser.username;
    });
    
    setupEventListeners();
    updateCurrentTime(); // Initial time update
    setInterval(updateCurrentTime, 60000);
    switchSection('dashboard');
});

// Setup all event listeners
function setupEventListeners() {
    console.log('Setting up event listeners...');
    
    // Navigation
    document.querySelectorAll('.sidebar-item').forEach(item => {
        item.addEventListener('click', (e) => {
            e.preventDefault();
            const section = item.dataset.section;
            console.log('Sidebar clicked:', section);
            switchSection(section);
        });
    });

    // Mobile menu
    const mobileMenuBtn = document.getElementById('mobileMenuBtn');
    if (mobileMenuBtn) {
        mobileMenuBtn.addEventListener('click', toggleMobileMenu);
        console.log('Mobile menu button attached');
    }
    
    const mobileOverlay = document.getElementById('mobileOverlay');
    if (mobileOverlay) {
        mobileOverlay.addEventListener('click', closeMobileMenu);
    }

    // QR Scanner - with null checks
    const startScanBtn = document.getElementById('startScanBtn');
    if (startScanBtn) {
        startScanBtn.addEventListener('click', () => {
            console.log('Start scan button clicked');
            startScanner();
        });
        console.log('Start scan button attached');
    } else {
        console.error('startScanBtn not found!');
    }
    
    const stopScanBtn = document.getElementById('stopScanBtn');
    if (stopScanBtn) {
        stopScanBtn.addEventListener('click', stopScanner);
    }
    
    const manualCheckInBtn = document.getElementById('manualCheckInBtn');
    if (manualCheckInBtn) {
        manualCheckInBtn.addEventListener('click', manualCheckIn);
        console.log('Manual check-in button attached');
    }
    
    // Appointments
    const refreshBtn = document.getElementById('refreshBtn');
    if (refreshBtn) {
        refreshBtn.addEventListener('click', loadTodayAppointments);
    }
    
    const statusFilter = document.getElementById('statusFilter');
    if (statusFilter) {
        statusFilter.addEventListener('change', loadTodayAppointments);
    }
    
    // Modals
    const closePatientModal = document.getElementById('closePatientModal');
    if (closePatientModal) {
        closePatientModal.addEventListener('click', () => {
            document.getElementById('patientModal').classList.add('hidden');
        });
    }
    
    const saveTriageBtn = document.getElementById('saveTriageBtn');
    if (saveTriageBtn) {
        saveTriageBtn.addEventListener('click', saveTriage);
    }
    
    const clearTriageBtn = document.getElementById('clearTriageBtn');
    if (clearTriageBtn) {
        clearTriageBtn.addEventListener('click', clearTriageForm);
    }
    
    const closeSuccessModal = document.getElementById('closeSuccessModal');
    if (closeSuccessModal) {
        closeSuccessModal.addEventListener('click', () => {
            document.getElementById('successModal').classList.add('hidden');
        });
    }
    
    // Handle all logout buttons
    document.querySelectorAll('#logoutBtn').forEach(btn => {
        btn.addEventListener('click', logout);
    });
    
    console.log('All event listeners set up');
}

// Section switching
function switchSection(section) {
    document.querySelectorAll('.sidebar-item').forEach(item => item.classList.remove('active'));
    document.querySelector(`[data-section="${section}"]`).classList.add('active');
    
    document.querySelectorAll('.content-section').forEach(sec => sec.style.display = 'none');
    
    const targetSection = document.getElementById(`${section}Section`);
    if (targetSection) {
        targetSection.style.display = 'block';
    } else if (section !== 'dashboard') {
        showComingSoon(section);
    }
    
    document.getElementById('pageTitle').textContent = {
        dashboard: 'Dashboard', checkin: 'QR Check-in', patients: 'Patient Management',
        appointments: 'Appointments', communication: 'Communication', account: 'Account Settings'
    }[section] || 'Dashboard';

    if (section === 'dashboard') {
        loadTodayAppointments();
    }
}

// STEP 1: Start QR Scanner
async function startScanner() {
    console.log('Starting QR scanner...');
    
    try {
        const qrReaderElement = document.getElementById('qr-reader');
        if (!qrReaderElement) {
            console.error('qr-reader element not found!');
            Swal.fire('Error', 'QR reader element not found', 'error');
            return;
        }
        
        // Check if Html5Qrcode is available
        if (typeof Html5Qrcode === 'undefined') {
            console.error('Html5Qrcode library not loaded!');
            Swal.fire({
                icon: 'error',
                title: 'Scanner Library Error',
                text: 'QR scanner library not loaded. Please refresh the page.',
                confirmButtonText: 'Refresh Page',
                allowOutsideClick: false
            }).then(() => {
                window.location.reload();
            });
            return;
        }
        
        qrReaderElement.classList.remove('hidden');
        console.log('QR reader element shown');
        
        // Stop existing scanner if any
        if (html5QrCode) {
            try {
                await html5QrCode.stop();
                await html5QrCode.clear();
            } catch (e) {
                console.log('No active scanner to stop');
            }
        }
        
        html5QrCode = new Html5Qrcode("qr-reader");
        console.log('Html5Qrcode instance created');
        
        showScannerAlert('Requesting camera permission...', 'info');
        
        // Request camera permission and start scanning
        const config = { fps: 10, qrbox: { width: 250, height: 250 } };
        
        await html5QrCode.start(
            { facingMode: "environment" },
            config,
            (decodedText) => {
                console.log('QR Code detected:', decodedText);
                processQRCode(decodedText); // STEP 2: Process QR Code
                stopScanner();
            },
            (errorMessage) => {
                // Silently ignore scanning errors (these happen continuously while scanning)
            }
        );

        console.log('Camera started successfully');
        document.getElementById('startScanBtn').classList.add('hidden');
        document.getElementById('stopScanBtn').classList.remove('hidden');
        showScannerAlert('Camera ready! Point at QR code to scan.', 'success');
        
    } catch (error) {
        console.error('Error starting scanner:', error);
        
        let errorMessage = 'Failed to start camera. ';
        let errorTitle = 'Camera Error';
        
        if (error.name === 'NotAllowedError') {
            errorTitle = 'Camera Permission Denied';
            errorMessage = 'Please allow camera access in your browser settings and try again.';
        } else if (error.name === 'NotFoundError') {
            errorTitle = 'No Camera Found';
            errorMessage = 'No camera detected on this device. Please connect a camera or use manual check-in.';
        } else if (error.name === 'NotReadableError') {
            errorTitle = 'Camera In Use';
            errorMessage = 'Camera is already in use by another application. Please close other apps and try again.';
        } else if (error.name === 'OverconstrainedError') {
            errorTitle = 'Camera Not Compatible';
            errorMessage = 'Your camera does not support the required settings. Try using manual check-in.';
        } else {
            errorMessage += error.message || 'Please check camera permissions and try again.';
        }
        
        showScannerAlert(errorMessage, 'error');
        Swal.fire({
            icon: 'error',
            title: errorTitle,
            text: errorMessage,
            confirmButtonText: 'OK'
        });
        
        // Reset button states
        document.getElementById('qr-reader').classList.add('hidden');
        document.getElementById('startScanBtn').classList.remove('hidden');
        document.getElementById('stopScanBtn').classList.add('hidden');
    }
}

async function stopScanner() {
    if (html5QrCode) {
        try {
            await html5QrCode.stop();
            html5QrCode.clear();
            html5QrCode = null;
        } catch (error) {}
    }
    
    document.getElementById('qr-reader').classList.add('hidden');
    document.getElementById('startScanBtn').classList.remove('hidden');
    document.getElementById('stopScanBtn').classList.add('hidden');
    hideScannerAlert();
}

// STEP 2: Process and validate QR code
async function processQRCode(tokenHash) {
    try {
        let decodedToken = tokenHash;
        try {
            decodedToken = atob(tokenHash);
        } catch (e) {}

        showScannerAlert('Validating QR code...', 'info');

        // STEP 3: Validate and retrieve appointment
        const response = await fetch('../api/appointments/checkin.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ token_hash: decodedToken })
        });

        const data = await response.json();

        if (data.success) {
            // STEP 4: Mark as checked-in (done by API)
            Swal.fire({
                title: 'Check-in Successful!',
                text: `${data.appointment.patient_name} checked in for Dr. ${data.appointment.doctor_name}`,
                icon: 'success',
                timer: 3000,
                showConfirmButton: false
            });
            
            loadTodayAppointments();
            
            // STEP 5: Open triage form
            setTimeout(() => {
                currentPatientId = data.appointment.patient_id;
                viewPatientDetails(data.appointment.patient_id);
            }, 2000);
            
            hideScannerAlert();
        } else {
            showScannerAlert(data.message || 'Check-in failed', 'error');
        }
    } catch (error) {
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
    const result = await Swal.fire({
        title: 'Confirm Check-in',
        text: 'Check in this patient?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Yes, Check In'
    });

    if (result.isConfirmed) {
        try {
            const response = await fetch('../api/appointments/quick-checkin.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ appointment_id: appointmentId })
            });

            const data = await response.json();

            if (data.success) {
                Swal.fire('Success!', 'Patient checked in', 'success');
                loadTodayAppointments();
                
                setTimeout(() => {
                    currentPatientId = data.appointment.patient_id;
                    viewPatientDetails(data.appointment.patient_id);
                }, 1500);
            } else {
                Swal.fire('Error', data.message, 'error');
            }
        } catch (error) {
            Swal.fire('Error', 'Network error', 'error');
        }
    }
}

// Load appointments
async function loadTodayAppointments() {
    const today = new Date().toISOString().split('T')[0];
    const status = document.getElementById('statusFilter')?.value || '';
    
    let url = `../api/appointments/list.php?date=${today}`;
    if (status) url += `&status=${status}`;

    try {
        document.getElementById('appointmentsList').innerHTML = `
            <div class="text-center py-8">
                <i class="fas fa-spinner fa-spin text-2xl text-green-600 mb-2"></i>
                <p class="text-gray-600">Loading...</p>
            </div>
        `;

        const response = await fetch(url);
        const data = await response.json();

        if (data.success) {
            displayAppointments(data.appointments);
            updateStats(data.appointments);
        } else {
            document.getElementById('appointmentsList').innerHTML = 
                `<div class="text-center py-8 text-red-600">Error: ${data.message}</div>`;
        }
    } catch (error) {
        document.getElementById('appointmentsList').innerHTML = 
            '<div class="text-center py-8 text-red-600">Network error</div>';
    }
}

// Display appointments
function displayAppointments(appointments) {
    const container = document.getElementById('appointmentsList');
    
    if (appointments.length === 0) {
        container.innerHTML = '<div class="text-center py-8 text-gray-500">No appointments found</div>';
        return;
    }

    container.innerHTML = appointments.map(apt => `
        <div class="bg-white border rounded-lg p-4 hover:shadow-md transition">
            <div class="flex justify-between items-start">
                <div class="flex-1">
                    <div class="flex items-center mb-2">
                        <h4 class="font-semibold text-gray-900">${apt.patient_name}</h4>
                        <span class="ml-2 status-badge status-${apt.status}">${apt.status.replace('_', ' ')}</span>
                    </div>
                    <p class="text-sm text-gray-600"><i class="fas fa-user-md mr-2"></i>Dr. ${apt.doctor_name}</p>
                    <p class="text-sm text-gray-600"><i class="fas fa-clock mr-2"></i>${formatTime(apt.appointment_time)}</p>
                </div>
                <div class="ml-4 space-y-2">
                    ${apt.status === 'scheduled' ? `
                        <button onclick="quickCheckIn(${apt.id})" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 text-sm">
                            <i class="fas fa-check mr-1"></i>Check In
                        </button>
                    ` : ''}
                    <button onclick="viewPatientDetails(${apt.patient_id})" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 text-sm block">
                        <i class="fas fa-eye mr-1"></i>Details
                    </button>
                </div>
            </div>
        </div>
    `).join('');
}

// Update statistics
function updateStats(appointments) {
    const stats = {
        total: appointments.length,
        checked_in: appointments.filter(apt => apt.status === 'checked_in').length,
        pending: appointments.filter(apt => apt.status === 'scheduled').length,
        completed: appointments.filter(apt => apt.status === 'completed').length
    };

    document.getElementById('todayCount').textContent = stats.total;
    document.getElementById('checkedInCount').textContent = stats.checked_in;
    document.getElementById('pendingCount').textContent = stats.pending;
    document.getElementById('completedCount').textContent = stats.completed;
}

// STEP 5: View patient details and triage
async function viewPatientDetails(patientId) {
    currentPatientId = patientId;
    
    try {
        const response = await fetch(`../api/visits/patient-history.php?patient_id=${patientId}`);
        const data = await response.json();

        if (data.success) {
            const patient = data.patient;
            const visits = data.visits;

            document.getElementById('patientDetails').innerHTML = `
                <div class="bg-gray-50 p-4 rounded-lg">
                    <h4 class="font-semibold text-lg mb-3 text-green-800">
                        <i class="fas fa-user mr-2"></i>Patient Information
                    </h4>
                    <div class="grid grid-cols-2 gap-3 text-sm">
                        <div><strong>Name:</strong> ${patient.full_name}</div>
                        <div><strong>Age:</strong> ${calculateAge(patient.date_of_birth)} years</div>
                        <div><strong>Gender:</strong> ${patient.gender}</div>
                        <div><strong>Blood Group:</strong> ${patient.blood_group || 'N/A'}</div>
                        <div><strong>Contact:</strong> ${patient.contact_number}</div>
                        <div><strong>Email:</strong> ${patient.email || 'N/A'}</div>
                    </div>
                    ${patient.allergies ? `<div class="mt-3 p-2 bg-red-50 border border-red-200 rounded text-sm">
                        <strong class="text-red-600">Allergies:</strong> ${patient.allergies}
                    </div>` : ''}
                </div>
            `;

            document.getElementById('patientModal').classList.remove('hidden');
        }
    } catch (error) {
        Swal.fire('Error', 'Failed to load patient details', 'error');
    }
}

// Save triage assessment
async function saveTriage() {
    if (!currentPatientId) {
        Swal.fire('Error', 'No patient selected', 'error');
        return;
    }

    const triageData = {
        patient_id: currentPatientId,
        chief_complaint: document.getElementById('chiefComplaint').value.trim(),
        blood_pressure: document.getElementById('bloodPressure').value.trim(),
        temperature: document.getElementById('temperature').value,
        heart_rate: document.getElementById('heartRate').value,
        weight: document.getElementById('weight').value,
        priority_level: document.getElementById('priorityLevel').value,
        notes: document.getElementById('triageNotes').value.trim(),
        recorded_by: currentUser.id
    };

    if (!triageData.chief_complaint) {
        Swal.fire('Error', 'Chief complaint is required', 'error');
        return;
    }

    try {
        const response = await fetch('../api/triage/save.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(triageData)
        });

        const data = await response.json();

        if (data.success) {
            Swal.fire('Success!', 'Triage saved successfully', 'success');
            clearTriageForm();
        } else {
            Swal.fire('Error', data.message || 'Failed to save triage', 'error');
        }
    } catch (error) {
        Swal.fire('Error', 'Network error', 'error');
    }
}

// Utility functions
function clearTriageForm() {
    document.getElementById('triageForm').reset();
    document.getElementById('priorityLevel').value = 'low';
}

function showScannerAlert(message, type) {
    const alertDiv = document.getElementById('scannerAlert');
    let bgClass = 'bg-blue-100 text-blue-700';
    
    if (type === 'error') {
        bgClass = 'bg-red-100 text-red-700';
    } else if (type === 'success') {
        bgClass = 'bg-green-100 text-green-700';
    } else if (type === 'info') {
        bgClass = 'bg-blue-100 text-blue-700';
    }
    
    alertDiv.className = `mb-4 p-4 rounded-lg ${bgClass}`;
    alertDiv.textContent = message;
    alertDiv.classList.remove('hidden');
}

function hideScannerAlert() {
    document.getElementById('scannerAlert').classList.add('hidden');
}

function updateCurrentTime() {
    const now = new Date();
    const timeString = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true });
    const timeElement = document.getElementById('currentTime');
    if (timeElement) timeElement.textContent = timeString;
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
    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birth.getDate())) age--;
    return age;
}

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

function showComingSoon(feature) {
    Swal.fire('Coming Soon!', `${feature} feature is under development.`, 'info');
}

async function logout() {
    try {
        await fetch('../api/auth/logout.php', { method: 'POST' });
        window.location.href = 'login.html';
    } catch (error) {
        window.location.href = 'login.html';
    }
}
