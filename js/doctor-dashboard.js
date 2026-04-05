let currentUser = null;

document.addEventListener('DOMContentLoaded', async () => {
    currentUser = await checkAuth();
    if (!currentUser) return;

    if (currentUser.role !== 'doctor') {
        alert('Access denied. Doctor access only.');
        window.location.href = 'login.html';
        return;
    }

    document.getElementById('userName').textContent = currentUser.full_name;
    document.getElementById('welcomeName').textContent = currentUser.full_name.replace('Dr. ', '');

    setupEventListeners();
    loadAppointments();
    updateStats();

    // Set today's date as default
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('dateFilter').value = today;
});

function setupEventListeners() {
    document.getElementById('logoutBtn').addEventListener('click', logout);
    document.getElementById('refreshBtn').addEventListener('click', loadAppointments);
    document.getElementById('dateFilter').addEventListener('change', loadAppointments);
    document.getElementById('recordForm').addEventListener('submit', saveRecord);
    document.getElementById('closeRecordModal').addEventListener('click', closeRecordModal);

    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', () => switchTab(btn.dataset.tab));
    });
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

    if (tabName === 'patients') {
        loadPatients();
    }
}

async function loadAppointments() {
    const date = document.getElementById('dateFilter').value || new Date().toISOString().split('T')[0];
    
    try {
        const response = await fetch(`../api/appointments/list.php?date=${date}`);
        const data = await response.json();

        const container = document.getElementById('appointmentsList');

        if (data.success && data.appointments.length > 0) {
            container.innerHTML = data.appointments.map(apt => `
                <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition">
                    <div class="flex justify-between items-start">
                        <div class="flex-1">
                            <div class="flex items-center mb-2">
                                <span class="text-lg font-semibold text-gray-900">${apt.patient_name}</span>
                                <span class="ml-3 px-2 py-1 text-xs font-semibold rounded-full ${getStatusColor(apt.status)}">${apt.status.toUpperCase()}</span>
                            </div>
                            <p class="text-sm text-gray-600"><i class="fas fa-clock mr-2"></i>${formatTime(apt.appointment_time)}</p>
                            <p class="text-sm text-gray-600"><i class="fas fa-phone mr-2"></i>${apt.patient_contact}</p>
                            <p class="text-sm text-gray-600"><i class="fas fa-birthday-cake mr-2"></i>DOB: ${formatDate(apt.patient_dob)}</p>
                            ${apt.reason_for_visit ? `<p class="text-sm text-gray-600 mt-2"><i class="fas fa-notes-medical mr-2"></i>${apt.reason_for_visit}</p>` : ''}
                        </div>
                        <div class="ml-4 space-y-2">
                            ${apt.status === 'checked_in' || apt.status === 'in_progress' ? `
                                <button onclick="openRecordModal(${apt.id}, ${apt.patient_id}, '${apt.patient_name}')" class="bg-purple-600 text-white px-4 py-2 rounded-md hover:bg-purple-700 text-sm whitespace-nowrap">
                                    <i class="fas fa-file-medical mr-1"></i>Add Record
                                </button>
                            ` : ''}
                            <button onclick="viewPatientHistory(${apt.patient_id})" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 text-sm whitespace-nowrap block">
                                <i class="fas fa-history mr-1"></i>History
                            </button>
                        </div>
                    </div>
                </div>
            `).join('');
        } else {
            container.innerHTML = `
                <div class="text-center py-8 text-gray-500">
                    <i class="fas fa-calendar-times text-4xl mb-2"></i>
                    <p>No appointments found for this date</p>
                </div>
            `;
        }

        updateStats();
    } catch (error) {
        console.error('Error loading appointments:', error);
    }
}

async function updateStats() {
    const date = document.getElementById('dateFilter').value || new Date().toISOString().split('T')[0];
    
    try {
        const response = await fetch(`../api/appointments/list.php?date=${date}`);
        const data = await response.json();

        if (data.success) {
            const appointments = data.appointments;
            document.getElementById('todayCount').textContent = appointments.length;
            document.getElementById('checkedInCount').textContent = appointments.filter(a => a.status === 'checked_in' || a.status === 'in_progress').length;
            document.getElementById('pendingCount').textContent = appointments.filter(a => a.status === 'scheduled').length;
            document.getElementById('completedCount').textContent = appointments.filter(a => a.status === 'completed').length;
        }
    } catch (error) {
        console.error('Error updating stats:', error);
    }
}

async function loadPatients() {
    try {
        const response = await fetch('../api/appointments/list.php');
        const data = await response.json();

        if (data.success) {
            // Get unique patients
            const patientsMap = new Map();
            data.appointments.forEach(apt => {
                if (!patientsMap.has(apt.patient_id)) {
                    patientsMap.set(apt.patient_id, {
                        id: apt.patient_id,
                        name: apt.patient_name,
                        contact: apt.patient_contact,
                        dob: apt.patient_dob,
                        lastVisit: apt.appointment_date
                    });
                }
            });

            const patients = Array.from(patientsMap.values());
            const container = document.getElementById('patientsList');

            if (patients.length > 0) {
                container.innerHTML = patients.map(patient => `
                    <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition">
                        <div class="flex justify-between items-start">
                            <div class="flex-1">
                                <h3 class="text-lg font-semibold text-gray-900">${patient.name}</h3>
                                <p class="text-sm text-gray-600"><i class="fas fa-phone mr-2"></i>${patient.contact}</p>
                                <p class="text-sm text-gray-600"><i class="fas fa-birthday-cake mr-2"></i>${formatDate(patient.dob)}</p>
                                <p class="text-sm text-gray-600"><i class="fas fa-calendar mr-2"></i>Last Visit: ${formatDate(patient.lastVisit)}</p>
                            </div>
                            <button onclick="viewPatientHistory(${patient.id})" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 text-sm">
                                <i class="fas fa-history mr-1"></i>View History
                            </button>
                        </div>
                    </div>
                `).join('');
            } else {
                container.innerHTML = `
                    <div class="text-center py-8 text-gray-500">
                        <i class="fas fa-users text-4xl mb-2"></i>
                        <p>No patients found</p>
                    </div>
                `;
            }
        }
    } catch (error) {
        console.error('Error loading patients:', error);
    }
}

function openRecordModal(appointmentId, patientId, patientName) {
    document.getElementById('appointmentId').value = appointmentId;
    document.getElementById('patientInfo').innerHTML = `
        <p><strong>Patient:</strong> ${patientName}</p>
        <p><strong>Appointment ID:</strong> ${appointmentId}</p>
    `;
    
    // Clear form
    document.getElementById('recordForm').reset();
    document.getElementById('appointmentId').value = appointmentId;
    
    document.getElementById('recordModal').classList.remove('hidden');
}

function closeRecordModal() {
    document.getElementById('recordModal').classList.add('hidden');
}

async function saveRecord(e) {
    e.preventDefault();

    const appointmentId = document.getElementById('appointmentId').value;
    const vitalSigns = {
        bp: document.getElementById('vitalBP').value,
        temperature: document.getElementById('vitalTemp').value,
        pulse: document.getElementById('vitalPulse').value,
        weight: document.getElementById('vitalWeight').value
    };

    const recordData = {
        appointment_id: appointmentId,
        chief_complaint: document.getElementById('chiefComplaint').value,
        symptoms: document.getElementById('symptoms').value,
        vital_signs: vitalSigns,
        diagnosis: document.getElementById('diagnosis').value,
        prescription: document.getElementById('prescription').value,
        lab_tests_ordered: document.getElementById('labTests').value,
        follow_up_date: document.getElementById('followUpDate').value || null,
        notes: document.getElementById('notes').value
    };

    const saveText = document.getElementById('saveText');
    const saveSpinner = document.getElementById('saveSpinner');

    saveText.classList.add('hidden');
    saveSpinner.classList.remove('hidden');

    try {
        const response = await fetch('../api/visits/create.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(recordData)
        });

        const data = await response.json();

        if (data.success) {
            showRecordAlert('Medical record saved successfully!', 'success');
            setTimeout(() => {
                closeRecordModal();
                loadAppointments();
            }, 1500);
        } else {
            showRecordAlert(data.message || 'Failed to save record', 'error');
        }
    } catch (error) {
        showRecordAlert('An error occurred. Please try again.', 'error');
    } finally {
        saveText.classList.remove('hidden');
        saveSpinner.classList.add('hidden');
    }
}

function showRecordAlert(message, type) {
    const alert = document.getElementById('recordAlert');
    alert.className = `mb-4 p-4 rounded-lg ${type === 'error' ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700'}`;
    alert.textContent = message;
    alert.classList.remove('hidden');
    setTimeout(() => alert.classList.add('hidden'), 5000);
}

async function viewPatientHistory(patientId) {
    try {
        const response = await fetch(`../api/visits/patient-history.php?patient_id=${patientId}`);
        const data = await response.json();

        if (data.success) {
            const patient = data.patient;
            const visits = data.visits;

            let historyHtml = `
                <div class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50" id="historyModal">
                    <div class="relative top-10 mx-auto p-5 border w-11/12 md:w-3/4 shadow-lg rounded-md bg-white">
                        <div class="mt-3">
                            <div class="flex justify-between items-center mb-4">
                                <h3 class="text-lg font-medium text-gray-900">Patient History</h3>
                                <button onclick="closeHistoryModal()" class="text-gray-500 hover:text-gray-700">
                                    <i class="fas fa-times text-xl"></i>
                                </button>
                            </div>
                            
                            <div class="bg-gray-50 p-4 rounded-lg mb-4">
                                <h4 class="font-semibold mb-2">Patient Information</h4>
                                <div class="grid grid-cols-2 gap-3 text-sm">
                                    <div><strong>Name:</strong> ${patient.full_name}</div>
                                    <div><strong>DOB:</strong> ${formatDate(patient.date_of_birth)}</div>
                                    <div><strong>Gender:</strong> ${patient.gender}</div>
                                    <div><strong>Blood Group:</strong> ${patient.blood_group || 'N/A'}</div>
                                    <div><strong>Contact:</strong> ${patient.contact_number}</div>
                                    <div><strong>Email:</strong> ${patient.email || 'N/A'}</div>
                                </div>
                                ${patient.allergies ? `<div class="mt-3 text-sm"><strong class="text-red-600">Allergies:</strong> ${patient.allergies}</div>` : ''}
                                ${patient.medical_history ? `<div class="mt-2 text-sm"><strong>Medical History:</strong> ${patient.medical_history}</div>` : ''}
                            </div>

                            <div class="bg-gray-50 p-4 rounded-lg">
                                <h4 class="font-semibold mb-3">Visit History (${visits.length} visits)</h4>
                                ${visits.length > 0 ? `
                                    <div class="space-y-3 max-h-96 overflow-y-auto">
                                        ${visits.map(visit => `
                                            <div class="bg-white p-4 rounded border border-gray-200">
                                                <div class="flex justify-between mb-2">
                                                    <span class="font-semibold">${visit.doctor_name}</span>
                                                    <span class="text-sm text-gray-500">${formatDate(visit.visit_date)}</span>
                                                </div>
                                                ${visit.chief_complaint ? `<p class="text-sm mb-1"><strong>Chief Complaint:</strong> ${visit.chief_complaint}</p>` : ''}
                                                ${visit.diagnosis ? `<p class="text-sm mb-1"><strong>Diagnosis:</strong> ${visit.diagnosis}</p>` : ''}
                                                ${visit.prescription ? `<p class="text-sm mb-1"><strong>Prescription:</strong> ${visit.prescription}</p>` : ''}
                                                ${visit.notes ? `<p class="text-sm mb-1"><strong>Notes:</strong> ${visit.notes}</p>` : ''}
                                                ${visit.follow_up_date ? `<p class="text-sm text-purple-600"><strong>Follow-up:</strong> ${formatDate(visit.follow_up_date)}</p>` : ''}
                                            </div>
                                        `).join('')}
                                    </div>
                                ` : '<p class="text-sm text-gray-500">No previous visits</p>'}
                            </div>
                        </div>
                    </div>
                </div>
            `;

            document.body.insertAdjacentHTML('beforeend', historyHtml);
        }
    } catch (error) {
        console.error('Error loading patient history:', error);
    }
}

window.closeHistoryModal = function() {
    const modal = document.getElementById('historyModal');
    if (modal) modal.remove();
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
