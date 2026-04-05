/**
 * Complete Doctor Dashboard - Fully Functional
 * Features: Appointments management, Patient list, Profile display
 */

let currentUser = null;
let currentDoctor = null;
let appointments = [];
let patients = [];
let refreshInterval = null;

// Initialize dashboard
document.addEventListener('DOMContentLoaded', async () => {
    console.log('Doctor Dashboard Loading...');
    
    // Check authentication
    currentUser = await checkAuth();
    if (!currentUser) {
        window.location.href = 'login.html';
        return;
    }
    
    if (currentUser.role !== 'doctor') {
        Swal.fire({
            icon: 'error',
            title: 'Access Denied',
            text: 'This page is for doctors only',
            confirmButtonColor: '#10b981'
        }).then(() => {
            window.location.href = 'login.html';
        });
        return;
    }
    
    // Load doctor profile
    await loadDoctorProfile();
    
    // Setup UI
    setupEventListeners();
    updateClock();
    setInterval(updateClock, 1000);
    
    // Load initial data
    await Promise.all([
        loadDashboardStats(),
        loadAppointments(),
        loadPatients()
    ]);
    
    // Start real-time updates
    startRealTimeUpdates();
    
    console.log('Doctor Dashboard Ready!');
});

/**
 * Load doctor profile
 */
async function loadDoctorProfile() {
    try {
        const response = await fetch('../api/doctor/get-profile.php');
        const data = await response.json();
        
        console.log('Profile API Response:', data);
        
        if (data.success && data.profile) {
            currentDoctor = data.profile;
            
            // Update name displays
            const fullName = currentDoctor.full_name || 'Doctor';
            const firstName = currentDoctor.first_name || '';
            const lastName = currentDoctor.last_name || '';
            
            // Update all name elements
            const userNameEl = document.getElementById('userName');
            const welcomeNameEl = document.getElementById('welcomeName');
            const dropdownNameEl = document.getElementById('dropdownName');
            
            if (userNameEl) userNameEl.textContent = `Dr. ${firstName} ${lastName}`.trim();
            if (welcomeNameEl) welcomeNameEl.textContent = fullName;
            if (dropdownNameEl) dropdownNameEl.textContent = `Dr. ${fullName}`;
            
            // Update specialization and department
            const specializationEl = document.getElementById('specialization');
            const departmentEl = document.getElementById('department');
            
            if (specializationEl) {
                specializationEl.textContent = currentDoctor.specialization || 'Specialist';
            }
            if (departmentEl) {
                departmentEl.textContent = currentDoctor.department || 'Department';
            }
            
            // Update email
            const email = currentDoctor.email || '';
            const dropdownEmailEl = document.getElementById('dropdownEmail');
            const doctorEmailEl = document.getElementById('doctorEmail');
            
            if (dropdownEmailEl) dropdownEmailEl.textContent = email;
            if (doctorEmailEl) doctorEmailEl.textContent = email;
            
            // Update phone
            const phone = currentDoctor.contact_number || currentDoctor.phone || '';
            const doctorPhoneEl = document.getElementById('doctorPhone');
            if (doctorPhoneEl) doctorPhoneEl.textContent = phone;
            
            // Update profile pictures
            const profilePicElements = [
                'profilePicture',
                'welcomeProfilePic',
                'dropdownProfilePic',
                'profilePicPreview'
            ];
            
            profilePicElements.forEach(id => {
                const el = document.getElementById(id);
                if (el) {
                    if (currentDoctor.profile_image_url && currentDoctor.profile_image) {
                        el.innerHTML = `<img src="${currentDoctor.profile_image_url}" alt="${fullName}" class="w-full h-full object-cover rounded-full">`;
                    } else {
                        const initials = fullName
                            ? fullName.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase()
                            : 'DR';
                        el.innerHTML = `<span class="text-white font-bold text-2xl">${initials}</span>`;
                    }
                }
            });
            
            // Update profile form if exists
            const profileFirstNameEl = document.getElementById('profileFirstName');
            const profileMiddleNameEl = document.getElementById('profileMiddleName');
            const profileLastNameEl = document.getElementById('profileLastName');
            const profileEmailEl = document.getElementById('profileEmail');
            const profilePhoneEl = document.getElementById('profilePhone');
            const profileSpecializationEl = document.getElementById('profileSpecialization');
            const profileDepartmentEl = document.getElementById('profileDepartment');
            const profileBioEl = document.getElementById('profileBio');
            
            if (profileFirstNameEl) profileFirstNameEl.value = currentDoctor.first_name || '';
            if (profileMiddleNameEl) profileMiddleNameEl.value = currentDoctor.middle_name || '';
            if (profileLastNameEl) profileLastNameEl.value = currentDoctor.last_name || '';
            if (profileEmailEl) profileEmailEl.value = currentDoctor.email || '';
            if (profilePhoneEl) profilePhoneEl.value = currentDoctor.contact_number || '';
            if (profileSpecializationEl) profileSpecializationEl.value = currentDoctor.specialization || '';
            if (profileDepartmentEl) profileDepartmentEl.value = currentDoctor.department || '';
            if (profileBioEl) profileBioEl.value = currentDoctor.bio || '';
            
            console.log('✅ Doctor profile loaded successfully:', {
                name: fullName,
                specialization: currentDoctor.specialization,
                department: currentDoctor.department,
                email: email,
                phone: phone
            });
        } else {
            console.error('❌ Failed to load doctor profile:', data.message);
            
            // Show error notification
            Swal.fire({
                icon: 'error',
                title: 'Profile Load Error',
                text: data.message || 'Could not load doctor profile',
                confirmButtonColor: '#10b981'
            });
        }
    } catch (error) {
        console.error('❌ Error loading doctor profile:', error);
        
        Swal.fire({
            icon: 'error',
            title: 'Connection Error',
            text: 'Could not connect to server. Please check your connection.',
            confirmButtonColor: '#10b981'
        });
    }
}

/**
 * Update real-time clock
 */
function updateClock() {
    const now = new Date();
    
    // Update time
    const timeEl = document.getElementById('realTimeClock');
    if (timeEl) {
        timeEl.textContent = now.toLocaleString('en-US', {
            weekday: 'short',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit'
        });
    }
    
    // Update date
    const dateEl = document.getElementById('currentDate');
    if (dateEl) {
        dateEl.textContent = now.toLocaleDateString('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric'
        });
    }
}

/**
 * Setup event listeners
 */
function setupEventListeners() {
    // Profile menu toggle
    const profileMenuBtn = document.getElementById('profileMenuBtn');
    const profileDropdown = document.getElementById('profileDropdown');
    
    if (profileMenuBtn && profileDropdown) {
        profileMenuBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            profileDropdown.classList.toggle('hidden');
        });
        
        // Close dropdown when clicking outside
        document.addEventListener('click', () => {
            profileDropdown.classList.add('hidden');
        });
    }
    
    // Tab switching
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const tab = btn.dataset.tab;
            switchTab(tab);
        });
    });
    
    // Refresh button
    const refreshBtn = document.getElementById('refreshBtn');
    if (refreshBtn) {
        refreshBtn.addEventListener('click', async () => {
            refreshBtn.classList.add('fa-spin');
            await loadAppointments();
            refreshBtn.classList.remove('fa-spin');
            
            Swal.fire({
                icon: 'success',
                title: 'Refreshed!',
                text: 'Appointments updated',
                timer: 1500,
                showConfirmButton: false
            });
        });
    }
    
    // Date filter
    const dateFilter = document.getElementById('dateFilter');
    if (dateFilter) {
        dateFilter.addEventListener('change', () => {
            loadAppointments();
        });
    }
}

/**
 * Switch between tabs
 */
function switchTab(tabName) {
    // Update tab buttons
    document.querySelectorAll('.tab-btn').forEach(btn => {
        if (btn.dataset.tab === tabName) {
            btn.classList.add('active', 'border-green-600', 'text-green-600');
            btn.classList.remove('border-transparent', 'text-gray-500');
        } else {
            btn.classList.remove('active', 'border-green-600', 'text-green-600');
            btn.classList.add('border-transparent', 'text-gray-500');
        }
    });
    
    // Show/hide tab content
    document.querySelectorAll('.tab-content').forEach(content => {
        content.classList.add('hidden');
    });
    
    const activeTab = document.getElementById(`${tabName}Tab`);
    if (activeTab) {
        activeTab.classList.remove('hidden');
    }
    
    // Load tab-specific data
    if (tabName === 'appointments') {
        loadAppointments();
    } else if (tabName === 'patients') {
        loadPatients();
    }
}

/**
 * Load dashboard statistics
 */
async function loadDashboardStats() {
    try {
        const response = await fetch('../api/doctor/stats.php');
        const data = await response.json();
        
        if (data.success) {
            document.getElementById('todayCount').textContent = data.stats.today || 0;
            document.getElementById('checkedInCount').textContent = data.stats.checked_in || 0;
            document.getElementById('pendingCount').textContent = data.stats.pending || 0;
            document.getElementById('completedCount').textContent = data.stats.completed || 0;
        }
    } catch (error) {
        console.error('Error loading stats:', error);
    }
}

/**
 * Load appointments
 */
async function loadAppointments() {
    try {
        const dateFilter = document.getElementById('dateFilter');
        const filterDate = dateFilter ? dateFilter.value : '';
        
        let url = '../api/doctor/get-appointments.php';
        if (filterDate) {
            url += `?date=${filterDate}`;
        }
        
        const response = await fetch(url);
        const data = await response.json();
        
        if (data.success) {
            appointments = data.appointments;
            displayAppointments(appointments);
        } else {
            console.error('Failed to load appointments:', data.message);
            displayAppointments([]);
        }
    } catch (error) {
        console.error('Error loading appointments:', error);
        displayAppointments([]);
    }
}

/**
 * Display appointments list
 */
function displayAppointments(appointmentsList) {
    const container = document.getElementById('appointmentsList');
    if (!container) return;
    
    if (!appointmentsList || appointmentsList.length === 0) {
        container.innerHTML = `
            <div class="text-center py-12">
                <i class="fas fa-calendar-times text-6xl text-gray-300 mb-4"></i>
                <p class="text-gray-500">No appointments found</p>
            </div>
        `;
        return;
    }
    
    container.innerHTML = appointmentsList.map(apt => {
        const statusColors = {
            'pending': 'bg-yellow-100 text-yellow-800',
            'confirmed': 'bg-blue-100 text-blue-800',
            'scheduled': 'bg-blue-100 text-blue-800',
            'checked-in': 'bg-purple-100 text-purple-800',
            'completed': 'bg-green-100 text-green-800',
            'cancelled': 'bg-red-100 text-red-800'
        };
        
        const statusIcons = {
            'pending': 'fa-clock',
            'confirmed': 'fa-check-circle',
            'scheduled': 'fa-calendar-check',
            'checked-in': 'fa-user-check',
            'completed': 'fa-check-double',
            'cancelled': 'fa-times-circle'
        };
        
        return `
            <div class="bg-white rounded-lg shadow-md p-6 hover:shadow-xl transition appointment-card">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div class="flex-1">
                        <div class="flex items-center gap-3 mb-2">
                            <h3 class="text-xl font-bold text-gray-900">${apt.patient_name}</h3>
                            <span class="px-3 py-1 rounded-full text-xs font-semibold ${statusColors[apt.status] || 'bg-gray-100 text-gray-800'}">
                                <i class="fas ${statusIcons[apt.status] || 'fa-circle'} mr-1"></i>${apt.status_display || apt.status.toUpperCase()}
                            </span>
                        </div>
                        <div class="space-y-2 text-sm text-gray-600">
                            <p><i class="fas fa-calendar text-green-600 w-5"></i><strong>Date:</strong> ${apt.formatted_date}</p>
                            <p><i class="fas fa-clock text-green-600 w-5"></i><strong>Time:</strong> ${apt.formatted_time}</p>
                            ${apt.reason_for_visit ? `<p><i class="fas fa-notes-medical text-green-600 w-5"></i><strong>Reason:</strong> ${apt.reason_for_visit}</p>` : ''}
                            ${apt.patient_contact ? `<p><i class="fas fa-phone text-green-600 w-5"></i><strong>Contact:</strong> ${apt.patient_contact}</p>` : ''}
                        </div>
                    </div>
                    <div class="flex flex-col gap-2">
                        ${apt.status === 'pending' ? `
                            <button onclick="confirmAppointment('${apt.id}')" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition text-sm">
                                <i class="fas fa-check mr-2"></i>Confirm
                            </button>
                            <button onclick="cancelAppointment('${apt.id}')" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition text-sm">
                                <i class="fas fa-times mr-2"></i>Decline
                            </button>
                        ` : ''}
                        ${apt.can_add_record ? `
                            <button onclick="openMedicalRecordModal('${apt.id}', '${apt.patient_id}', '${apt.patient_name}', '${apt.patient_age}', '${apt.patient_gender}', '${apt.patient_contact}', '${apt.allergies || ''}')" 
                                class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition text-sm">
                                <i class="fas fa-file-medical mr-2"></i>Add Record
                            </button>
                        ` : ''}
                        <button onclick="viewPatientDetails('${apt.patient_id}')" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition text-sm">
                            <i class="fas fa-user mr-2"></i>View Patient
                        </button>
                    </div>
                </div>
            </div>
        `;
    }).join('');
}

/**
 * Confirm appointment
 */
async function confirmAppointment(appointmentId) {
    const result = await Swal.fire({
        title: 'Confirm Appointment?',
        text: 'This will notify the patient that the appointment is confirmed.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#10b981',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Yes, Confirm',
        cancelButtonText: 'Cancel'
    });
    
    if (result.isConfirmed) {
        try {
            const response = await fetch('../api/appointments/confirm.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ appointment_id: appointmentId }),
                credentials: 'include'
            });
            
            const data = await response.json();
            
            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Confirmed!',
                    text: 'Appointment has been confirmed',
                    confirmButtonColor: '#10b981'
                });
                
                loadAppointments();
                loadDashboardStats();
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: data.message || 'Failed to confirm appointment',
                    confirmButtonColor: '#10b981'
                });
            }
        } catch (error) {
            console.error('Error confirming appointment:', error);
        }
    }
}

/**
 * Complete appointment
 */
async function completeAppointment(appointmentId) {
    const result = await Swal.fire({
        title: 'Complete Appointment?',
        text: 'Mark this appointment as completed?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#10b981',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Yes, Complete',
        cancelButtonText: 'Cancel'
    });
    
    if (result.isConfirmed) {
        try {
            const response = await fetch('../api/appointments/complete.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ appointment_id: appointmentId }),
                credentials: 'include'
            });
            
            const data = await response.json();
            
            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Completed!',
                    text: 'Appointment marked as completed',
                    confirmButtonColor: '#10b981'
                });
                
                loadAppointments();
                loadDashboardStats();
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: data.message || 'Failed to complete appointment',
                    confirmButtonColor: '#10b981'
                });
            }
        } catch (error) {
            console.error('Error completing appointment:', error);
        }
    }
}

/**
 * Cancel appointment
 */
async function cancelAppointment(appointmentId) {
    const result = await Swal.fire({
        title: 'Decline Appointment?',
        text: 'This will cancel the appointment and notify the patient.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Yes, Decline',
        cancelButtonText: 'Cancel'
    });
    
    if (result.isConfirmed) {
        try {
            const response = await fetch('../api/appointments/cancel.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ appointment_id: appointmentId }),
                credentials: 'include'
            });
            
            const data = await response.json();
            
            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Declined',
                    text: 'Appointment has been cancelled',
                    confirmButtonColor: '#10b981'
                });
                
                loadAppointments();
                loadDashboardStats();
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: data.message || 'Failed to cancel appointment',
                    confirmButtonColor: '#10b981'
                });
            }
        } catch (error) {
            console.error('Error cancelling appointment:', error);
        }
    }
}

/**
 * View patient details
 */
async function viewPatientDetails(patientId) {
    try {
        const response = await fetch(`../api/patients/get-details.php?id=${patientId}`);
        const data = await response.json();
        
        if (data.success && data.patient) {
            const patient = data.patient;
            
            Swal.fire({
                title: 'Patient Details',
                html: `
                    <div class="text-left space-y-3">
                        <div class="flex items-center gap-3 mb-4">
                            ${patient.profile_image_url 
                                ? `<img src="${patient.profile_image_url}" class="w-16 h-16 rounded-full object-cover">`
                                : `<div class="w-16 h-16 rounded-full bg-green-100 flex items-center justify-center text-green-600 font-bold text-xl">${patient.full_name.split(' ').map(n => n[0]).join('')}</div>`
                            }
                            <div>
                                <h3 class="text-xl font-bold">${patient.full_name}</h3>
                                <p class="text-sm text-gray-600">${patient.gender} • ${patient.age || 'N/A'} years old</p>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3 text-sm">
                            <div><strong>Email:</strong> ${patient.email || 'N/A'}</div>
                            <div><strong>Phone:</strong> ${patient.contact_number || 'N/A'}</div>
                            <div><strong>Blood Group:</strong> ${patient.blood_group || 'N/A'}</div>
                            <div><strong>DOB:</strong> ${patient.date_of_birth || 'N/A'}</div>
                        </div>
                        ${patient.allergies ? `<div class="bg-red-50 border border-red-200 rounded p-2"><strong>Allergies:</strong> ${patient.allergies}</div>` : ''}
                        ${patient.medical_history ? `<div class="bg-blue-50 border border-blue-200 rounded p-2"><strong>Medical History:</strong> ${patient.medical_history}</div>` : ''}
                    </div>
                `,
                confirmButtonColor: '#10b981',
                confirmButtonText: 'Close',
                width: '600px'
            });
        }
    } catch (error) {
        console.error('Error loading patient details:', error);
    }
}

/**
 * Open medical record modal
 */
function openMedicalRecordModal(appointmentId, patientId, patientName, patientAge, patientGender, patientContact, allergies) {
    // Set hidden fields
    document.getElementById('recordAppointmentId').value = appointmentId;
    document.getElementById('recordPatientId').value = patientId;
    
    // Display patient info
    const patientInfo = document.getElementById('patientInfo');
    patientInfo.innerHTML = `
        <div><strong>Name:</strong> ${patientName}</div>
        <div><strong>Age:</strong> ${patientAge || 'N/A'} years</div>
        <div><strong>Gender:</strong> ${patientGender}</div>
        <div><strong>Contact:</strong> ${patientContact || 'N/A'}</div>
        ${allergies ? `<div class="md:col-span-2 text-red-600"><strong>⚠️ Allergies:</strong> ${allergies}</div>` : ''}
    `;
    
    // Clear form
    document.getElementById('recordForm').reset();
    document.getElementById('recordAppointmentId').value = appointmentId;
    document.getElementById('recordPatientId').value = patientId;
    
    // Show modal
    document.getElementById('recordModal').classList.remove('hidden');
}

/**
 * Close medical record modal
 */
function closeRecordModal() {
    document.getElementById('recordModal').classList.add('hidden');
    document.getElementById('recordForm').reset();
}

/**
 * Save medical record
 */
document.addEventListener('DOMContentLoaded', () => {
    const recordForm = document.getElementById('recordForm');
    if (recordForm) {
        recordForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            // Show loading
            const saveText = document.getElementById('saveText');
            const saveSpinner = document.getElementById('saveSpinner');
            saveText.classList.add('hidden');
            saveSpinner.classList.remove('hidden');
            
            try {
                const formData = {
                    appointment_id: document.getElementById('recordAppointmentId').value,
                    patient_id: document.getElementById('recordPatientId').value,
                    chief_complaint: document.getElementById('chiefComplaint').value,
                    symptoms: document.getElementById('symptoms').value,
                    vital_bp: document.getElementById('vitalBP').value,
                    vital_temp: document.getElementById('vitalTemp').value,
                    vital_pulse: document.getElementById('vitalPulse').value,
                    vital_weight: document.getElementById('vitalWeight').value,
                    diagnosis: document.getElementById('diagnosis').value,
                    prescription: document.getElementById('prescription').value,
                    lab_tests: document.getElementById('labTests').value,
                    follow_up_date: document.getElementById('followUpDate').value,
                    notes: document.getElementById('notes').value
                };
                
                const response = await fetch('../api/doctor/save-medical-record.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(formData)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Record Saved!',
                        html: `
                            <div class="text-left">
                                <p class="mb-2">✅ Medical record has been saved successfully</p>
                                <p class="text-sm text-gray-600">✓ Diagnosis recorded</p>
                                <p class="text-sm text-gray-600">✓ Prescription saved</p>
                                <p class="text-sm text-gray-600">✓ Appointment marked as completed</p>
                            </div>
                        `,
                        confirmButtonColor: '#10b981'
                    });
                    
                    closeRecordModal();
                    loadAppointments();
                    loadDashboardStats();
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: result.message || 'Failed to save medical record',
                        confirmButtonColor: '#10b981'
                    });
                }
            } catch (error) {
                console.error('Error saving medical record:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Failed to save medical record. Please try again.',
                    confirmButtonColor: '#10b981'
                });
            } finally {
                saveText.classList.remove('hidden');
                saveSpinner.classList.add('hidden');
            }
        });
    }
});

/**
 * Load patients list
 */
async function loadPatients() {
    try {
        const response = await fetch('../api/doctor/get-patients.php');
        const data = await response.json();
        
        if (data.success) {
            patients = data.patients;
            displayPatients(patients);
        }
    } catch (error) {
        console.error('Error loading patients:', error);
    }
}

/**
 * Display patients list
 */
function displayPatients(patientsList) {
    const container = document.getElementById('patientsList');
    if (!container) return;
    
    if (!patientsList || patientsList.length === 0) {
        container.innerHTML = `
            <div class="text-center py-12">
                <i class="fas fa-users text-6xl text-gray-300 mb-4"></i>
                <p class="text-gray-500">No patients found</p>
            </div>
        `;
        return;
    }
    
    container.innerHTML = patientsList.map(patient => `
        <div class="bg-white rounded-lg shadow-md p-6 hover:shadow-xl transition">
            <div class="flex items-center gap-4">
                ${patient.profile_image_url 
                    ? `<img src="${patient.profile_image_url}" class="w-16 h-16 rounded-full object-cover">`
                    : `<div class="w-16 h-16 rounded-full bg-green-100 flex items-center justify-center text-green-600 font-bold text-xl">${patient.full_name.split(' ').map(n => n[0]).join('')}</div>`
                }
                <div class="flex-1">
                    <h3 class="text-lg font-bold text-gray-900">${patient.full_name}</h3>
                    <p class="text-sm text-gray-600">${patient.gender} • ${patient.age || 'N/A'} years</p>
                    <p class="text-sm text-gray-600">${patient.contact_number || 'N/A'}</p>
                </div>
                <button onclick="viewPatientDetails('${patient.id}')" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition">
                    <i class="fas fa-eye mr-2"></i>View
                </button>
            </div>
        </div>
    `).join('');
}

/**
 * Start real-time updates
 */
function startRealTimeUpdates() {
    // Refresh appointments every 30 seconds
    refreshInterval = setInterval(() => {
        loadAppointments();
        loadDashboardStats();
    }, 30000);
}

/**
 * Logout confirmation
 */
function confirmLogout() {
    Swal.fire({
        title: 'Logout?',
        text: 'Are you sure you want to logout?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#10b981',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Yes, Logout',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            performLogout();
        }
    });
}

function performLogout() {
    // Clear session
    localStorage.clear();
    sessionStorage.clear();
    
    // Show loading
    Swal.fire({
        title: 'Logging out...',
        text: 'Please wait',
        icon: 'info',
        showConfirmButton: false,
        timer: 1000,
        timerProgressBar: true
    }).then(() => {
        // Redirect to logout API
        window.location.href = '../api/auth/logout.php';
    });
}
