// Reception Dashboard with Real Data Implementation
let currentUser = null;
let html5QrCode = null;
let currentPatientId = null;

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
    
    // Update time every minute
    setInterval(updateCurrentTime, 60000);
});

// Setup event listeners
function setupEventListeners() {
    document.getElementById('logoutBtn').addEventListener('click', logout);
    document.getElementById('startScanBtn').addEventListener('click', startScanner);
    document.getElementById('stopScanBtn').addEventListener('click', stopScanner);
    document.getElementById('manualCheckInBtn').addEventListener('click', manualCheckIn);
    document.getElementById('refreshBtn').addEventListener('click', loadTodayAppointments);
    document.getElementById('statusFilter').addEventListener('change', loadTodayAppointments);
    document.getElementById('closePatientModal').addEventListener('click', () => {
        document.getElementById('patientModal').classList.add('hidden');
    });
    document.getElementById('saveTriageBtn').addEventListener('click', saveTriage);
    document.getElementById('clearTriageBtn').addEventListener('click', clearTriageForm);
    document.getElementById('closeSuccessModal').addEventListener('click', () => {
        document.getElementById('successModal').classList.add('hidden');
    });
}

// Load today's appointments with real data
async function loadTodayAppointments() {
    const today = new Date().toISOString().split('T')[0];
    const status = document.getElementById('statusFilter').value;
    
    let url = `../api/appointments/list.php?date=${today}`;
    if (status) url += `&status=${status}`;

    try {
        // Show loading state
        document.getElementById('appointmentsList').innerHTML = `
            <div class="text-center py-8">
                <i class="fas fa-spinner fa-spin text-2xl text-green-600 mb-2"></i>
                <p class="text-gray-600">Loading appointments...</p>
            </div>
        `;

        const response = await fetch(url);
        const data = await response.json();

        if (data.success) {
            displayAppointments(data.appointments);
            updateStats(data.appointments);
        } else {
            document.getElementById('appointmentsList').innerHTML = 
                `<div class="text-center py-8 text-red-600">
                    <i class="fas fa-exclamation-triangle mb-2"></i>
                    <p>Error loading appointments: ${data.message}</p>
                </div>`;
        }
    } catch (error) {
        console.error('Error loading appointments:', error);
        document.getElementById('appointmentsList').innerHTML = 
            `<div class="text-center py-8 text-red-600">
                <i class="fas fa-exclamation-triangle mb-2"></i>
                <p>Network error occurred</p>
            </div>`;
    }
}

// Display appointments
function displayAppointments(appointments) {
    const container = document.getElementById('appointmentsList');
    
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
        <div class="bg-white border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow">
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
                        <button onclick="quickCheckIn(${apt.id})" class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700 text-sm whitespace-nowrap">
                            <i class="fas fa-check mr-1"></i>Check In
                        </button>
                    ` : ''}
                    <button onclick="viewPatientDetails(${apt.patient_id})" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 text-sm whitespace-nowrap block">
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

    document.getElementById('todayCount').textContent = stats.total;
    document.getElementById('checkedInCount').textContent = stats.checked_in;
    document.getElementById('pendingCount').textContent = stats.pending;
    document.getElementById('completedCount').textContent = stats.completed;
}

// Start QR Scanner
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

// Stop QR Scanner
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
        // Decode token if base64 encoded
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
            // Show success notification
            Swal.fire({
                title: 'Check-in Successful!',
                text: `${data.appointment.patient_name} has been checked in for their appointment with Dr. ${data.appointment.doctor_name}`,
                icon: 'success',
                timer: 3000,
                showConfirmButton: false
            });
            
            // Reload appointments to show updated status
            loadTodayAppointments();
            
            // Auto-open patient details for triage after 2 seconds
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

// Quick check-in for scheduled appointments
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
                
                // Open patient details for triage
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

// View patient details with real data
async function viewPatientDetails(patientId) {
    currentPatientId = patientId;
    
    try {
        const response = await fetch(`../api/visits/patient-history.php?patient_id=${patientId}`);
        const data = await response.json();

        if (data.success) {
            const patient = data.patient;
            const visits = data.visits;

            const detailsHtml = `
                <div class="space-y-4">
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <h4 class="font-semibold text-lg mb-3 text-green-800">
                            <i class="fas fa-user mr-2"></i>Personal Information
                        </h4>
                        <div class="grid grid-cols-2 gap-3 text-sm">
                            <div><strong>Name:</strong> ${patient.full_name}</div>
                            <div><strong>DOB:</strong> ${formatDate(patient.date_of_birth)} (${calculateAge(patient.date_of_birth)} years)</div>
                            <div><strong>Gender:</strong> ${patient.gender}</div>
                            <div><strong>Blood Group:</strong> ${patient.blood_group || 'N/A'}</div>
                            <div><strong>Contact:</strong> ${patient.contact_number}</div>
                            <div><strong>Email:</strong> ${patient.email || 'N/A'}</div>
                        </div>
                        ${patient.allergies ? `<div class="mt-3 p-2 bg-red-50 border border-red-200 rounded text-sm">
                            <strong class="text-red-600"><i class="fas fa-exclamation-triangle mr-1"></i>Allergies:</strong> ${patient.allergies}
                        </div>` : ''}
                        ${patient.emergency_contact ? `<div class="mt-3 text-sm">
                            <strong>Emergency Contact:</strong> ${patient.emergency_contact}
                        </div>` : ''}
                    </div>

                    <div class="bg-gray-50 p-4 rounded-lg">
                        <h4 class="font-semibold text-lg mb-3 text-blue-800">
                            <i class="fas fa-history mr-2"></i>Medical History (${visits.length} visits)
                        </h4>
                        ${visits.length > 0 ? `
                            <div class="space-y-3 max-h-64 overflow-y-auto">
                                ${visits.slice(0, 5).map(visit => `
                                    <div class="bg-white p-3 rounded border border-gray-200">
                                        <div class="flex justify-between mb-2">
                                            <span class="font-semibold text-sm text-green-700">Dr. ${visit.doctor_name}</span>
                                            <span class="text-xs text-gray-500">${formatDate(visit.visit_date)}</span>
                                        </div>
                                        ${visit.diagnosis ? `<p class="text-sm mb-1"><strong>Diagnosis:</strong> ${visit.diagnosis}</p>` : ''}
                                        ${visit.prescription ? `<p class="text-sm mb-1"><strong>Prescription:</strong> ${visit.prescription}</p>` : ''}
                                        ${visit.notes ? `<p class="text-sm text-gray-600"><strong>Notes:</strong> ${visit.notes}</p>` : ''}
                                    </div>
                                `).join('')}
                                ${visits.length > 5 ? `<p class="text-xs text-gray-500 text-center">... and ${visits.length - 5} more visits</p>` : ''}
                            </div>
                        ` : '<p class="text-sm text-gray-500">No previous visits recorded</p>'}
                    </div>
                </div>
            `;

            document.getElementById('patientDetails').innerHTML = detailsHtml;
            document.getElementById('patientModal').classList.remove('hidden');
        }
    } catch (error) {
        console.error('Error loading patient details:', error);
        Swal.fire('Error', 'Failed to load patient details', 'error');
    }
}

// Save triage assessment with real data
async function saveTriage() {
    if (!currentPatientId) {
        Swal.fire('Error', 'No patient selected for triage', 'error');
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

    // Validate required fields
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
            Swal.fire({
                title: 'Success!',
                text: 'Triage assessment saved successfully',
                icon: 'success',
                timer: 2000,
                showConfirmButton: false
            });
            clearTriageForm();
        } else {
            Swal.fire('Error', data.message || 'Failed to save triage', 'error');
        }
    } catch (error) {
        console.error('Error saving triage:', error);
        Swal.fire('Error', 'Network error occurred', 'error');
    }
}

// Clear triage form
function clearTriageForm() {
    document.getElementById('triageForm').reset();
    document.getElementById('priorityLevel').value = 'low';
}

// Show scanner alert
function showScannerAlert(message, type) {
    const alertDiv = document.getElementById('scannerAlert');
    alertDiv.className = `mb-4 p-4 rounded-lg ${type === 'error' ? 'bg-red-100 text-red-700' : 'bg-blue-100 text-blue-700'}`;
    alertDiv.textContent = message;
    alertDiv.classList.remove('hidden');
}

// Hide scanner alert
function hideScannerAlert() {
    document.getElementById('scannerAlert').classList.add('hidden');
}

// Update current time
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

// Utility functions
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

// Logout function
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
