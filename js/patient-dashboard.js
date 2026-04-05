let currentUser = null;
let departments = [];
let doctors = [];

// Initialize dashboard
document.addEventListener('DOMContentLoaded', async () => {
    currentUser = await checkAuth();
    if (!currentUser) return;

    // Set user name
    document.getElementById('userName').textContent = currentUser.full_name;
    document.getElementById('welcomeName').textContent = currentUser.full_name;

    // Setup event listeners
    setupEventListeners();

    // Load initial data
    loadAppointments();
    loadNotifications();
    loadDepartments();

    // Set minimum date for appointment booking
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('appointmentDate').setAttribute('min', today);
});

function setupEventListeners() {
    // Tab switching
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', () => switchTab(btn.dataset.tab));
    });

    // Logout
    document.getElementById('logoutBtn').addEventListener('click', logout);

    // Booking form
    document.getElementById('bookingForm').addEventListener('submit', bookAppointment);
    document.getElementById('department').addEventListener('change', loadDoctorsByDepartment);
    document.getElementById('doctor').addEventListener('change', () => {
        if (document.getElementById('appointmentDate').value) {
            loadTimeSlots();
        }
    });
    document.getElementById('appointmentDate').addEventListener('change', loadTimeSlots);

    // QR Modal
    document.getElementById('closeQrModal').addEventListener('click', () => {
        document.getElementById('qrModal').classList.add('hidden');
    });
}

function switchTab(tabName) {
    // Update tab buttons
    document.querySelectorAll('.tab-btn').forEach(btn => {
        if (btn.dataset.tab === tabName) {
            btn.classList.add('active', 'border-purple-600', 'text-purple-600');
            btn.classList.remove('border-transparent', 'text-gray-500');
        } else {
            btn.classList.remove('active', 'border-purple-600', 'text-purple-600');
            btn.classList.add('border-transparent', 'text-gray-500');
        }
    });

    // Update tab content
    document.querySelectorAll('.tab-content').forEach(content => {
        content.classList.add('hidden');
    });
    document.getElementById(tabName + 'Tab').classList.remove('hidden');

    // Load data for specific tabs
    if (tabName === 'appointments') {
        loadAppointments();
    } else if (tabName === 'history') {
        loadMedicalHistory();
    }
}

async function loadAppointments() {
    try {
        const response = await fetch('../api/appointments/list.php');
        const data = await response.json();

        const container = document.getElementById('appointmentsList');

        if (data.success && data.appointments.length > 0) {
            container.innerHTML = data.appointments.map(apt => `
                <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition">
                    <div class="flex justify-between items-start">
                        <div class="flex-1">
                            <div class="flex items-center mb-2">
                                <span class="text-lg font-semibold text-gray-900">${apt.doctor_name}</span>
                                <span class="ml-3 px-2 py-1 text-xs font-semibold rounded-full ${getStatusColor(apt.status)}">${apt.status.toUpperCase()}</span>
                            </div>
                            <p class="text-sm text-gray-600"><i class="fas fa-stethoscope mr-2"></i>${apt.specialization}</p>
                            <p class="text-sm text-gray-600"><i class="fas fa-building mr-2"></i>${apt.department}</p>
                            <p class="text-sm text-gray-600 mt-2"><i class="fas fa-calendar mr-2"></i>${formatDate(apt.appointment_date)} at ${formatTime(apt.appointment_time)}</p>
                            ${apt.reason_for_visit ? `<p class="text-sm text-gray-600 mt-1"><i class="fas fa-notes-medical mr-2"></i>${apt.reason_for_visit}</p>` : ''}
                        </div>
                        <div class="ml-4">
                            ${apt.status === 'scheduled' && apt.token_hash ? `
                                <button onclick="showQRCode('${apt.token_hash}', '${apt.qr_expires_at}')" class="bg-purple-600 text-white px-4 py-2 rounded-md hover:bg-purple-700 text-sm">
                                    <i class="fas fa-qrcode mr-2"></i>Show QR
                                </button>
                            ` : ''}
                        </div>
                    </div>
                </div>
            `).join('');
        } else {
            container.innerHTML = `
                <div class="text-center py-8 text-gray-500">
                    <i class="fas fa-calendar-times text-4xl mb-2"></i>
                    <p>No appointments found</p>
                </div>
            `;
        }
    } catch (error) {
        console.error('Error loading appointments:', error);
    }
}

async function loadDepartments() {
    try {
        const response = await fetch('../api/doctors/list.php');
        const data = await response.json();

        if (data.success) {
            doctors = data.doctors;
            departments = [...new Set(doctors.map(d => d.department))];

            const select = document.getElementById('department');
            select.innerHTML = '<option value="">All Departments</option>' +
                departments.map(dept => `<option value="${dept}">${dept}</option>`).join('');

            // Also populate all doctors initially
            populateDoctors(doctors);
        }
    } catch (error) {
        console.error('Error loading departments:', error);
    }
}

function loadDoctorsByDepartment() {
    const selectedDept = document.getElementById('department').value;
    const filteredDoctors = selectedDept ? doctors.filter(d => d.department === selectedDept) : doctors;
    populateDoctors(filteredDoctors);
}

function populateDoctors(doctorList) {
    const select = document.getElementById('doctor');
    select.innerHTML = '<option value="">Select Doctor</option>' +
        doctorList.map(doc => `
            <option value="${doc.id}">
                Dr. ${doc.full_name} - ${doc.specialization} (Fee: $${doc.consultation_fee})
            </option>
        `).join('');
}

async function loadTimeSlots() {
    const doctorId = document.getElementById('doctor').value;
    const date = document.getElementById('appointmentDate').value;

    if (!doctorId || !date) return;

    try {
        const response = await fetch(`../api/doctors/available-slots.php?doctor_id=${doctorId}&date=${date}`);
        const data = await response.json();

        const select = document.getElementById('timeSlot');

        if (data.success && data.slots.length > 0) {
            select.innerHTML = '<option value="">Select time slot</option>' +
                data.slots.filter(slot => slot.available).map(slot => `
                    <option value="${slot.time}">${slot.display}</option>
                `).join('');
        } else {
            select.innerHTML = '<option value="">No slots available</option>';
        }
    } catch (error) {
        console.error('Error loading time slots:', error);
    }
}

async function bookAppointment(e) {
    e.preventDefault();

    const doctorId = document.getElementById('doctor').value;
    const appointmentDate = document.getElementById('appointmentDate').value;
    const appointmentTime = document.getElementById('timeSlot').value;
    const reasonForVisit = document.getElementById('reasonForVisit').value;

    const bookingText = document.getElementById('bookingText');
    const bookingSpinner = document.getElementById('bookingSpinner');

    bookingText.classList.add('hidden');
    bookingSpinner.classList.remove('hidden');

    try {
        const response = await fetch('../api/appointments/create.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                doctor_id: doctorId,
                appointment_date: appointmentDate,
                appointment_time: appointmentTime,
                reason_for_visit: reasonForVisit
            })
        });

        const data = await response.json();

        if (data.success) {
            showBookingAlert('Appointment booked successfully!', 'success');
            document.getElementById('bookingForm').reset();
            
            // Show QR code
            if (data.qr_code) {
                showQRCode(data.qr_code.token_hash, data.qr_code.expires_at, data.qr_code.qr_image);
            }

            // Switch to appointments tab
            setTimeout(() => {
                switchTab('appointments');
            }, 2000);
        } else {
            showBookingAlert(data.message || 'Booking failed', 'error');
        }
    } catch (error) {
        showBookingAlert('An error occurred. Please try again.', 'error');
    } finally {
        bookingText.classList.remove('hidden');
        bookingSpinner.classList.add('hidden');
    }
}

function showBookingAlert(message, type) {
    const alert = document.getElementById('bookingAlert');
    alert.className = `mb-4 p-4 rounded-lg ${type === 'error' ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700'}`;
    alert.textContent = message;
    alert.classList.remove('hidden');
    setTimeout(() => alert.classList.add('hidden'), 5000);
}

function showQRCode(tokenHash, expiresAt, qrImage = null) {
    const modal = document.getElementById('qrModal');
    const container = document.getElementById('qrCodeContainer');
    const expirySpan = document.getElementById('qrExpiry');

    if (qrImage) {
        container.innerHTML = `<img src="${qrImage}" alt="QR Code" class="mx-auto">`;
    } else {
        // Fallback: show token hash as text
        container.innerHTML = `
            <div class="bg-gray-100 p-4 rounded">
                <p class="text-xs text-gray-600 mb-2">Token Hash:</p>
                <p class="text-sm font-mono break-all">${tokenHash}</p>
            </div>
        `;
    }

    expirySpan.textContent = formatDateTime(expiresAt);
    modal.classList.remove('hidden');
}

async function loadMedicalHistory() {
    try {
        const response = await fetch('../api/visits/patient-history.php');
        const data = await response.json();

        const container = document.getElementById('historyList');

        if (data.success && data.visits.length > 0) {
            container.innerHTML = data.visits.map(visit => `
                <div class="border border-gray-200 rounded-lg p-4">
                    <div class="flex justify-between items-start mb-3">
                        <div>
                            <h3 class="font-semibold text-lg">${visit.doctor_name}</h3>
                            <p class="text-sm text-gray-600">${visit.specialization}</p>
                        </div>
                        <span class="text-sm text-gray-500">${formatDate(visit.visit_date)}</span>
                    </div>
                    ${visit.diagnosis ? `<p class="text-sm mb-2"><strong>Diagnosis:</strong> ${visit.diagnosis}</p>` : ''}
                    ${visit.prescription ? `<p class="text-sm mb-2"><strong>Prescription:</strong> ${visit.prescription}</p>` : ''}
                    ${visit.notes ? `<p class="text-sm mb-2"><strong>Notes:</strong> ${visit.notes}</p>` : ''}
                    ${visit.follow_up_date ? `<p class="text-sm text-purple-600"><strong>Follow-up:</strong> ${formatDate(visit.follow_up_date)}</p>` : ''}
                </div>
            `).join('');
        } else {
            container.innerHTML = `
                <div class="text-center py-8 text-gray-500">
                    <i class="fas fa-file-medical text-4xl mb-2"></i>
                    <p>No medical history found</p>
                </div>
            `;
        }
    } catch (error) {
        console.error('Error loading medical history:', error);
    }
}

async function loadNotifications() {
    try {
        const response = await fetch('../api/notifications/list.php?unread_only=true');
        const data = await response.json();

        if (data.success && data.unread_count > 0) {
            const badge = document.getElementById('notificationBadge');
            badge.textContent = data.unread_count;
            badge.classList.remove('hidden');
        }
    } catch (error) {
        console.error('Error loading notifications:', error);
    }
}

async function logout() {
    try {
        await fetch('../api/auth/logout.php', { method: 'POST' });
        window.location.href = 'login.html';
    } catch (error) {
        console.error('Logout error:', error);
        window.location.href = 'login.html';
    }
}

// Utility functions
function getStatusColor(status) {
    const colors = {
        'scheduled': 'bg-blue-100 text-blue-800',
        'checked_in': 'bg-green-100 text-green-800',
        'in_progress': 'bg-yellow-100 text-yellow-800',
        'completed': 'bg-gray-100 text-gray-800',
        'cancelled': 'bg-red-100 text-red-800',
        'no_show': 'bg-red-100 text-red-800'
    };
    return colors[status] || 'bg-gray-100 text-gray-800';
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
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
