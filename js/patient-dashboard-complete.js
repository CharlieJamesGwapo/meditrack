// Patient Dashboard Complete JavaScript
// Global variables
let allDoctors = [];
let filteredDoctors = [];
let allAppointments = [];
let patientProfile = null;

// Load Patient Profile
async function loadPatientProfile() {
    try {
        const response = await fetch('../api/patient/get-profile.php');
        const data = await response.json();
        
        if (data.success) {
            patientProfile = data.profile;
            
            const fullName = patientProfile.full_name || 'Patient';
            document.getElementById('userName').textContent = fullName;
            document.getElementById('welcomeName').textContent = fullName;
            document.getElementById('dropdownName').textContent = fullName;
            document.getElementById('dropdownEmail').textContent = patientProfile.email || '';
            document.getElementById('patientEmail').textContent = patientProfile.email || '';
            document.getElementById('patientPhone').textContent = patientProfile.contact_number || patientProfile.phone || '';
            
            if (patientProfile.profile_image_url) {
                const imgHtml = `<img src="${patientProfile.profile_image_url}" class="w-full h-full object-cover">`;
                document.getElementById('profilePicture').innerHTML = imgHtml;
                document.getElementById('dropdownProfilePic').innerHTML = imgHtml;
                document.getElementById('welcomeProfilePic').innerHTML = imgHtml;
            }
        }
    } catch (error) {
        console.error('Error loading profile:', error);
    }
}

// Load Doctors
async function loadDoctors() {
    try {
        console.log('Loading doctors from API...');
        const response = await fetch('../api/patient/get-doctors.php');
        const data = await response.json();
        
        console.log('Doctors API response:', data);
        
        if (data.success) {
            allDoctors = data.doctors || [];
            filteredDoctors = allDoctors;
            console.log(`Loaded ${allDoctors.length} doctors`);
            
            document.getElementById('doctorsCount').textContent = allDoctors.length;
            
            const departments = [...new Set(allDoctors.map(d => d.department))].filter(Boolean);
            const deptSelect = document.getElementById('filterDepartment');
            departments.forEach(dept => {
                const option = document.createElement('option');
                option.value = dept;
                option.textContent = dept;
                deptSelect.appendChild(option);
            });
            
            displayDoctors(allDoctors);
        } else {
            console.error('Failed to load doctors:', data.message);
            document.getElementById('doctorsList').innerHTML = `
                <div class="col-span-full text-center py-12">
                    <i class="fas fa-exclamation-triangle text-6xl text-red-400 mb-4"></i>
                    <p class="text-gray-800 font-semibold text-lg mb-2">Failed to load doctors</p>
                    <p class="text-gray-600 mb-4">${data.message || 'Unknown error'}</p>
                    <button onclick="loadDoctors()" class="bg-green-600 text-white px-6 py-3 rounded-lg hover:bg-green-700 transition">
                        <i class="fas fa-redo mr-2"></i>Try Again
                    </button>
                </div>
            `;
        }
    } catch (error) {
        console.error('Error loading doctors:', error);
        document.getElementById('doctorsList').innerHTML = `
            <div class="col-span-full text-center py-12">
                <i class="fas fa-exclamation-triangle text-6xl text-red-400 mb-4"></i>
                <p class="text-gray-800 font-semibold text-lg mb-2">Failed to load doctors</p>
                <p class="text-gray-600 mb-4">${error.message}</p>
                <button onclick="loadDoctors()" class="bg-green-600 text-white px-6 py-3 rounded-lg hover:bg-green-700 transition">
                    <i class="fas fa-redo mr-2"></i>Try Again
                </button>
            </div>
        `;
    }
}

// Display Doctors
function displayDoctors(doctors) {
    const list = document.getElementById('doctorsList');
    
    if (!doctors || doctors.length === 0) {
        list.innerHTML = `<div class="col-span-full text-center py-12">
            <i class="fas fa-user-md text-6xl text-gray-300 mb-4"></i>
            <p class="text-gray-700 font-semibold text-lg mb-2">No doctors found</p>
        </div>`;
        return;
    }
    
    list.innerHTML = doctors.map(doctor => `
        <div class="doctor-card bg-white border-2 border-gray-200 rounded-xl p-6 shadow-sm hover:border-green-500">
            <div class="flex items-start justify-between mb-4">
                <div class="w-20 h-20 rounded-full overflow-hidden bg-gradient-to-br from-green-100 to-green-200 border-4 border-green-500 shadow-lg flex items-center justify-center">
                    ${doctor.profile_image_url ? 
                        `<img src="${doctor.profile_image_url}" class="w-full h-full object-cover" alt="${doctor.full_name}">` :
                        `<i class="fas fa-user-md text-3xl text-green-600"></i>`
                    }
                </div>
                <span class="px-3 py-1 text-xs rounded-full font-semibold bg-green-100 text-green-700">
                    ${doctor.status ? doctor.status.toUpperCase() : 'ACTIVE'}
                </span>
            </div>
            
            <h3 class="text-lg font-bold text-gray-800 mb-1">${doctor.full_name || 'N/A'}</h3>
            <p class="text-sm text-green-600 font-semibold mb-2">${doctor.specialization || 'General'}</p>
            <p class="text-sm text-gray-600 mb-3">
                <i class="fas fa-building text-green-600 mr-1"></i>
                ${doctor.department || 'N/A'}
            </p>
            
            <div class="grid grid-cols-2 gap-2 mb-3 text-sm">
                <div class="bg-gray-50 p-2 rounded">
                    <p class="text-xs text-gray-500">Experience</p>
                    <p class="font-semibold text-gray-800">${doctor.experience_years || 0} years</p>
                </div>
                <div class="bg-gray-50 p-2 rounded">
                    <p class="text-xs text-gray-500">Fee</p>
                    <p class="font-semibold text-green-600">₱${doctor.consultation_fee || 0}</p>
                </div>
            </div>
            
            <div class="flex gap-2">
                <button onclick="showEnhancedDoctorProfile(${doctor.id})" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-semibold transition shadow-md hover:shadow-lg">
                    <i class="fas fa-user-md mr-1"></i>Profile
                </button>
                <button onclick="bookAppointment(${doctor.id})" class="flex-1 bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm font-semibold transition shadow-md hover:shadow-lg">
                    <i class="fas fa-calendar-plus mr-1"></i>Book
                </button>
            </div>
        </div>
    `).join('');
}

// Filter Doctors
function filterDoctors() {
    const searchTerm = document.getElementById('searchDoctors').value.toLowerCase();
    const deptFilter = document.getElementById('filterDepartment').value;
    
    filteredDoctors = allDoctors.filter(doctor => {
        const matchesSearch = !searchTerm || 
            (doctor.full_name && doctor.full_name.toLowerCase().includes(searchTerm)) ||
            (doctor.specialization && doctor.specialization.toLowerCase().includes(searchTerm));
        
        const matchesDept = !deptFilter || doctor.department === deptFilter;
        
        return matchesSearch && matchesDept;
    });
    
    displayDoctors(filteredDoctors);
}

// View Doctor Details
function viewDoctorDetails(id) {
    const doctor = allDoctors.find(d => d.id == id);
    if (!doctor) return;
    
    Swal.fire({
        title: doctor.full_name,
        html: `
            <div class="text-left space-y-3">
                <div class="flex items-center justify-center mb-4">
                    <div class="w-24 h-24 rounded-full overflow-hidden bg-green-100 border-4 border-green-500">
                        ${doctor.profile_image_url ? 
                            `<img src="${doctor.profile_image_url}" class="w-full h-full object-cover">` :
                            `<div class="w-full h-full flex items-center justify-center"><i class="fas fa-user-md text-4xl text-green-600"></i></div>`
                        }
                    </div>
                </div>
                <p><strong>Specialization:</strong> ${doctor.specialization || 'N/A'}</p>
                <p><strong>Department:</strong> ${doctor.department || 'N/A'}</p>
                <p><strong>Qualification:</strong> ${doctor.qualification || 'N/A'}</p>
                <p><strong>Experience:</strong> ${doctor.experience_years || 0} years</p>
                <p><strong>Consultation Fee:</strong> ₱${doctor.consultation_fee || 0}</p>
                ${doctor.bio ? `<p><strong>About:</strong> ${doctor.bio}</p>` : ''}
            </div>
        `,
        width: '600px',
        confirmButtonColor: '#10b981',
        confirmButtonText: 'Book Appointment',
        showCancelButton: true,
        cancelButtonText: 'Close'
    }).then((result) => {
        if (result.isConfirmed) {
            bookAppointment(id);
        }
    });
}

// Book Appointment
function bookAppointment(doctorId) {
    const doctor = allDoctors.find(d => d.id == doctorId);
    if (!doctor) return;
    
    document.getElementById('bookingDoctorId').value = doctorId;
    document.getElementById('bookingDoctorInfo').innerHTML = `
        <div class="grid grid-cols-2 gap-3">
            <div>
                <p class="text-xs text-gray-500">Doctor</p>
                <p class="font-semibold text-gray-800">${doctor.full_name}</p>
            </div>
            <div>
                <p class="text-xs text-gray-500">Specialization</p>
                <p class="font-semibold text-gray-800">${doctor.specialization}</p>
            </div>
            <div>
                <p class="text-xs text-gray-500">Department</p>
                <p class="font-semibold text-gray-800">${doctor.department}</p>
            </div>
            <div>
                <p class="text-xs text-gray-500">Consultation Fee</p>
                <p class="font-semibold text-green-600">₱${doctor.consultation_fee}</p>
            </div>
        </div>
    `;
    
    // Set date constraints
    const today = new Date();
    const minDate = today.toISOString().split('T')[0];
    const maxDate = new Date(today.setMonth(today.getMonth() + 3)).toISOString().split('T')[0];
    
    const dateInput = document.getElementById('bookingDate');
    dateInput.setAttribute('min', minDate);
    dateInput.setAttribute('max', maxDate);
    dateInput.value = '';
    
    // Add date change listener
    dateInput.addEventListener('change', function() {
        loadAvailableSlots(doctorId, this.value);
    });
    
    loadAvailableSlots(doctorId);
    document.getElementById('bookAppointmentModal').classList.remove('hidden');
}

// Load Available Time Slots
async function loadAvailableSlots(doctorId, selectedDate) {
    const timeSelect = document.getElementById('bookingTime');
    timeSelect.innerHTML = '<option value="">Loading slots...</option>';
    timeSelect.disabled = true;
    
    try {
        // Generate time slots (9 AM to 5 PM, 30-minute intervals)
        const slots = [];
        for (let hour = 9; hour < 17; hour++) {
            slots.push({
                value: `${hour.toString().padStart(2, '0')}:00:00`,
                label: `${hour % 12 || 12}:00 ${hour < 12 ? 'AM' : 'PM'}`
            });
            slots.push({
                value: `${hour.toString().padStart(2, '0')}:30:00`,
                label: `${hour % 12 || 12}:30 ${hour < 12 ? 'AM' : 'PM'}`
            });
        }
        
        // If date is selected, check for booked slots
        let bookedSlots = [];
        if (selectedDate) {
            try {
                const response = await fetch(`../api/patient/get-booked-slots.php?doctor_id=${doctorId}&date=${selectedDate}`);
                const data = await response.json();
                if (data.success) {
                    bookedSlots = data.booked_slots || [];
                }
            } catch (error) {
                console.log('Could not fetch booked slots:', error);
            }
        }
        
        // Populate time select
        timeSelect.innerHTML = '<option value="">Select Time</option>';
        
        slots.forEach(slot => {
            const option = document.createElement('option');
            option.value = slot.value;
            
            // Check if slot is booked
            const isBooked = bookedSlots.includes(slot.value);
            
            if (isBooked) {
                option.textContent = `${slot.label} (Booked)`;
                option.disabled = true;
                option.style.color = '#9ca3af';
            } else {
                option.textContent = slot.label;
            }
            
            timeSelect.appendChild(option);
        });
        
        timeSelect.disabled = false;
        
    } catch (error) {
        console.error('Error loading slots:', error);
        timeSelect.innerHTML = '<option value="">Error loading slots</option>';
        timeSelect.disabled = false;
    }
}

// Close Booking Modal
function closeBookingModal() {
    document.getElementById('bookAppointmentModal').classList.add('hidden');
    document.getElementById('bookingForm').reset();
}

// Load Appointments
async function loadAppointments() {
    try {
        const response = await fetch('../api/patient/get-appointments.php');
        const data = await response.json();
        
        if (data.success) {
            allAppointments = data.appointments || [];
            document.getElementById('upcomingCount').textContent = data.upcoming_count || 0;
            const completed = allAppointments.filter(a => a.status === 'completed').length;
            document.getElementById('completedCount').textContent = completed;
            displayAppointments(allAppointments);
        }
    } catch (error) {
        console.error('Error loading appointments:', error);
    }
}

// Display Appointments
function displayAppointments(appointments) {
    const list = document.getElementById('appointmentsList');
    
    if (!appointments || appointments.length === 0) {
        list.innerHTML = `
            <div class="text-center py-12">
                <i class="fas fa-calendar-times text-6xl text-gray-300 mb-4"></i>
                <p class="text-gray-700 font-semibold text-lg mb-2">No appointments yet</p>
                <p class="text-gray-500 mb-4">Book your first appointment with a doctor</p>
                <button onclick="document.querySelector('[data-tab=doctors]').click()" class="bg-green-600 text-white px-6 py-3 rounded-lg hover:bg-green-700 transition">
                    <i class="fas fa-user-md mr-2"></i>Find Doctors
                </button>
            </div>
        `;
        return;
    }
    
    const statusColors = {
        'scheduled': 'bg-blue-100 text-blue-700',
        'checked_in': 'bg-yellow-100 text-yellow-700',
        'in_progress': 'bg-purple-100 text-purple-700',
        'completed': 'bg-green-100 text-green-700',
        'cancelled': 'bg-red-100 text-red-700'
    };
    
    list.innerHTML = appointments.map(appt => `
        <div class="appointment-card bg-white border-2 border-gray-200 rounded-xl p-6 shadow-sm">
            <div class="flex items-start justify-between mb-4">
                <div class="flex items-center space-x-4">
                    <div class="w-16 h-16 rounded-full overflow-hidden bg-green-100 border-2 border-green-500 flex items-center justify-center">
                        ${appt.doctor_image_url ? 
                            `<img src="${appt.doctor_image_url}" class="w-full h-full object-cover">` :
                            `<i class="fas fa-user-md text-2xl text-green-600"></i>`
                        }
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-gray-800">${appt.doctor_name || 'N/A'}</h3>
                        <p class="text-sm text-green-600 font-semibold">${appt.specialization || 'General'}</p>
                    </div>
                </div>
                <span class="px-3 py-1 text-xs rounded-full font-semibold ${statusColors[appt.status] || 'bg-gray-100 text-gray-700'}">
                    ${appt.status ? appt.status.replace('_', ' ').toUpperCase() : 'SCHEDULED'}
                </span>
            </div>
            
            <div class="grid grid-cols-2 gap-3 mb-3">
                <div class="bg-gray-50 p-3 rounded">
                    <p class="text-xs text-gray-500 mb-1"><i class="fas fa-calendar text-green-600 mr-1"></i>Date</p>
                    <p class="font-semibold text-gray-800">${appt.formatted_date || appt.appointment_date}</p>
                </div>
                <div class="bg-gray-50 p-3 rounded">
                    <p class="text-xs text-gray-500 mb-1"><i class="fas fa-clock text-green-600 mr-1"></i>Time</p>
                    <p class="font-semibold text-gray-800">${appt.formatted_time || appt.appointment_time}</p>
                </div>
            </div>
            
            ${appt.reason_for_visit ? `
            <div class="mb-3">
                <p class="text-xs text-gray-500 mb-1"><i class="fas fa-notes-medical text-green-600 mr-1"></i>Reason</p>
                <p class="text-sm text-gray-700">${appt.reason_for_visit}</p>
            </div>
            ` : ''}
            
            <div class="flex gap-2 mt-4">
                ${appt.status === 'scheduled' || appt.status === 'checked_in' ? `
                    <button onclick="generateQRCode(${appt.id})" class="flex-1 bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm font-semibold transition">
                        <i class="fas fa-qrcode mr-1"></i>QR Code
                    </button>
                ` : ''}
                ${appt.status === 'scheduled' ? `
                    <button onclick="cancelAppointment(${appt.id})" class="flex-1 bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg text-sm font-semibold transition">
                        <i class="fas fa-times mr-1"></i>Cancel
                    </button>
                ` : ''}
                <button onclick="viewAppointmentDetails(${appt.id})" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-semibold transition">
                    <i class="fas fa-eye mr-1"></i>Details
                </button>
            </div>
        </div>
    `).join('');
}

// View Appointment Details
function viewAppointmentDetails(id) {
    const appt = allAppointments.find(a => a.id == id);
    if (!appt) return;
    
    Swal.fire({
        title: 'Appointment Details',
        html: `
            <div class="text-left space-y-3">
                <p><strong>Appointment #:</strong> ${appt.appointment_number || 'N/A'}</p>
                <p><strong>Doctor:</strong> ${appt.doctor_name || 'N/A'}</p>
                <p><strong>Specialization:</strong> ${appt.specialization || 'N/A'}</p>
                <p><strong>Date:</strong> ${appt.formatted_date || appt.appointment_date}</p>
                <p><strong>Time:</strong> ${appt.formatted_time || appt.appointment_time}</p>
                <p><strong>Status:</strong> ${appt.status ? appt.status.replace('_', ' ').toUpperCase() : 'SCHEDULED'}</p>
                ${appt.reason_for_visit ? `<p><strong>Reason:</strong> ${appt.reason_for_visit}</p>` : ''}
            </div>
        `,
        width: '600px',
        confirmButtonColor: '#10b981'
    });
}

// Cancel Appointment
async function cancelAppointment(id) {
    const result = await Swal.fire({
        title: 'Cancel Appointment?',
        text: 'Are you sure you want to cancel this appointment?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Yes, cancel it'
    });
    
    if (result.isConfirmed) {
        try {
            const response = await fetch('../api/patient/cancel-appointment.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ appointment_id: id })
            });
            
            const data = await response.json();
            
            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Cancelled!',
                    text: 'Appointment has been cancelled.',
                    confirmButtonColor: '#10b981'
                });
                loadAppointments();
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: data.message || 'Failed to cancel appointment',
                    confirmButtonColor: '#10b981'
                });
            }
        } catch (error) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Error cancelling appointment: ' + error.message,
                confirmButtonColor: '#10b981'
            });
        }
    }
}

// Initialize on page load
window.addEventListener('load', function() {
    loadPatientProfile();
    loadDoctors();
    loadAppointments();
    
    // Booking form submit
    document.getElementById('bookingForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const doctorId = document.getElementById('bookingDoctorId').value;
        const date = document.getElementById('bookingDate').value;
        const time = document.getElementById('bookingTime').value;
        const reason = document.getElementById('bookingReason').value;
        
        Swal.fire({
            title: 'Booking Appointment...',
            text: 'Please wait',
            icon: 'info',
            showConfirmButton: false,
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); }
        });
        
        try {
            const response = await fetch('../api/patient/book-appointment.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    doctor_id: doctorId,
                    appointment_date: date,
                    appointment_time: time,
                    reason_for_visit: reason
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Success!',
                    text: 'Appointment booked successfully!',
                    confirmButtonColor: '#10b981'
                });
                closeBookingModal();
                loadAppointments();
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: result.message || 'Failed to book appointment',
                    confirmButtonColor: '#10b981'
                });
            }
        } catch (error) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Error booking appointment: ' + error.message,
                confirmButtonColor: '#10b981'
            });
        }
    });
    
    // Tab switching
    document.querySelectorAll('.tab-btn').forEach(button => {
        button.addEventListener('click', function() {
            const tabName = this.getAttribute('data-tab');
            
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.add('hidden');
            });
            
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active', 'border-green-600', 'text-green-600');
                btn.classList.add('border-transparent', 'text-gray-500');
            });
            
            document.getElementById(tabName + 'Tab').classList.remove('hidden');
            
            this.classList.add('active', 'border-green-600', 'text-green-600');
            this.classList.remove('border-transparent', 'text-gray-500');
        });
    });
    
    console.log('Patient Dashboard - Complete Version Ready!');
});

// Generate QR Code for Appointment
async function generateQRCode(appointmentId) {
    Swal.fire({
        title: 'Generating QR Code...',
        text: 'Please wait',
        icon: 'info',
        showConfirmButton: false,
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); }
    });
    
    try {
        const response = await fetch('../api/patient/generate-qr.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ appointment_id: appointmentId })
        });
        
        const result = await response.json();
        
        if (result.success) {
            const qr = result.qr_code;
            const appt = qr.appointment;
            
            Swal.fire({
                title: 'Your QR Code',
                html: `
                    <div class="text-center">
                        <!-- QR Code Image -->
                        <div class="mb-6 flex justify-center">
                            <div class="bg-white p-4 rounded-xl shadow-lg border-4 border-green-500">
                                <img src="${qr.qr_image}" alt="QR Code" class="w-64 h-64">
                            </div>
                        </div>
                        
                        <!-- Appointment Details -->
                        <div class="bg-green-50 border-l-4 border-green-500 p-4 rounded-lg text-left mb-4">
                            <h3 class="font-bold text-green-800 mb-3 text-lg">
                                <i class="fas fa-calendar-check mr-2"></i>Appointment Details
                            </h3>
                            <div class="space-y-2 text-sm">
                                <p><strong>Appointment #:</strong> ${appt.appointment_number}</p>
                                <p><strong>Doctor:</strong> ${appt.doctor_name}</p>
                                <p><strong>Specialization:</strong> ${appt.specialization}</p>
                                <p><strong>Department:</strong> ${appt.department}</p>
                                <p><strong>Date:</strong> ${appt.date}</p>
                                <p><strong>Time:</strong> ${appt.time}</p>
                                <p><strong>Status:</strong> <span class="px-2 py-1 bg-green-600 text-white rounded text-xs">${appt.status.toUpperCase()}</span></p>
                            </div>
                        </div>
                        
                        <!-- Expiry Notice -->
                        <div class="bg-yellow-50 border-l-4 border-yellow-500 p-3 rounded-lg text-left text-sm">
                            <p class="text-yellow-800">
                                <i class="fas fa-clock mr-2"></i>
                                <strong>Valid until:</strong> ${new Date(qr.expires_at).toLocaleString()}
                            </p>
                        </div>
                        
                        <!-- Instructions -->
                        <div class="mt-4 text-left text-sm text-gray-600">
                            <p class="mb-2"><strong>How to use:</strong></p>
                            <ol class="list-decimal list-inside space-y-1">
                                <li>Show this QR code at the reception desk</li>
                                <li>Staff will scan to check you in</li>
                                <li>Wait for your turn in the waiting area</li>
                            </ol>
                        </div>
                    </div>
                `,
                width: '600px',
                confirmButtonColor: '#10b981',
                confirmButtonText: '<i class="fas fa-download mr-2"></i>Download QR Code',
                showCancelButton: true,
                cancelButtonText: 'Close',
                customClass: {
                    popup: 'qr-modal',
                    confirmButton: 'qr-download-btn'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    downloadQRCode(qr.qr_image, appt.appointment_number);
                }
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: result.message || 'Failed to generate QR code',
                confirmButtonColor: '#10b981'
            });
        }
    } catch (error) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Error generating QR code: ' + error.message,
            confirmButtonColor: '#10b981'
        });
    }
}

// Download QR Code as Image
function downloadQRCode(dataUrl, appointmentNumber) {
    const link = document.createElement('a');
    link.href = dataUrl;
    link.download = `MediTrack_QR_${appointmentNumber}.png`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    
    Swal.fire({
        icon: 'success',
        title: 'Downloaded!',
        text: 'QR code has been downloaded successfully',
        confirmButtonColor: '#10b981',
        timer: 2000
    });
}

// ========== ENHANCEMENT FUNCTIONS ==========

// Enhanced Profile Picture Loading with Fallback
function enhanceProfilePictures() {
    if (!patientProfile) return;
    
    const fullName = patientProfile.full_name || 'Patient';
    const fallbackUrl = `https://ui-avatars.com/api/?name=${encodeURIComponent(fullName)}&background=10b981&color=fff&size=200&bold=true`;
    
    // Profile picture elements
    const profileElements = [
        { id: 'profilePicture', size: 'w-10 h-10' },
        { id: 'dropdownProfilePic', size: 'w-12 h-12' },
        { id: 'welcomeProfilePic', size: 'w-24 h-24' }
    ];
    
    profileElements.forEach(({ id }) => {
        const el = document.getElementById(id);
        if (el) {
            let imageUrl = fallbackUrl;
            
            // Check for profile_image_url first
            if (patientProfile.profile_image_url) {
                imageUrl = patientProfile.profile_image_url;
            }
            // Check for profile_image field
            else if (patientProfile.profile_image) {
                imageUrl = `../../uploads/${patientProfile.profile_image}`;
            }
            
            // Create image with error handling
            el.innerHTML = `<img src="${imageUrl}" alt="${fullName}" class="w-full h-full object-cover rounded-full" onerror="this.src='${fallbackUrl}'">`;
        }
    });
}

// Load and Update Dashboard Stats
async function loadDashboardStats() {
    try {
        const response = await fetch('../api/patient/get-appointments.php');
        const data = await response.json();
        
        if (data.success && data.appointments) {
            const appointments = data.appointments;
            
            // Count upcoming (scheduled or checked_in, future dates)
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            
            const upcoming = appointments.filter(apt => {
                const aptDate = new Date(apt.appointment_date);
                return (apt.status === 'scheduled' || apt.status === 'checked_in') && aptDate >= today;
            }).length;
            
            // Count completed
            const completed = appointments.filter(apt => apt.status === 'completed').length;
            
            // Update UI
            const upcomingEl = document.getElementById('upcomingCount');
            if (upcomingEl) upcomingEl.textContent = upcoming;
            
            const completedEl = document.getElementById('completedCount');
            if (completedEl) completedEl.textContent = completed;
        }
        
        // Update doctors count
        const doctorsEl = document.getElementById('doctorsCount');
        if (doctorsEl && allDoctors.length > 0) {
            doctorsEl.textContent = allDoctors.length;
        }
        
    } catch (error) {
        console.log('Stats update error:', error);
    }
}

// Auto-save Profile Data (called after any profile update)
async function autoSaveProfile(fieldName, value) {
    try {
        const response = await fetch('../api/patient/update-profile.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                [fieldName]: value
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            // Show subtle success indicator
            showAutoSaveIndicator('Saved');
            // Reload profile to get updated data
            await loadPatientProfile();
            enhanceProfilePictures();
        }
    } catch (error) {
        console.error('Auto-save error:', error);
        showAutoSaveIndicator('Error', true);
    }
}

// Show Auto-save Indicator
function showAutoSaveIndicator(message, isError = false) {
    const indicator = document.createElement('div');
    indicator.className = `fixed top-20 right-4 px-4 py-2 rounded-lg shadow-lg text-white text-sm font-medium transition-all z-50 ${isError ? 'bg-red-500' : 'bg-green-500'}`;
    indicator.innerHTML = `<i class="fas fa-${isError ? 'exclamation-circle' : 'check-circle'} mr-2"></i>${message}`;
    document.body.appendChild(indicator);
    
    setTimeout(() => {
        indicator.style.opacity = '0';
        setTimeout(() => indicator.remove(), 300);
    }, 2000);
}

// Enhanced Doctor Profile Modal with More Info
function showEnhancedDoctorProfile(doctorId) {
    const doctor = allDoctors.find(d => d.id == doctorId);
    if (!doctor) return;
    
    const profileImageUrl = doctor.profile_image_url || 
                           (doctor.profile_image ? `../../uploads/${doctor.profile_image}` : null);
    const fallbackUrl = `https://ui-avatars.com/api/?name=${encodeURIComponent(doctor.full_name)}&background=10b981&color=fff&size=200&bold=true`;
    const imageUrl = profileImageUrl || fallbackUrl;
    
    Swal.fire({
        title: '',
        html: `
            <div class="text-center">
                <!-- Profile Picture -->
                <div class="mb-6 flex justify-center">
                    <div class="w-32 h-32 rounded-full overflow-hidden border-4 border-green-500 shadow-lg">
                        <img src="${imageUrl}" alt="${doctor.full_name}" class="w-full h-full object-cover" onerror="this.src='${fallbackUrl}'">
                    </div>
                </div>
                
                <!-- Name & Title -->
                <h2 class="text-2xl font-bold text-gray-900 mb-2">${doctor.full_name}</h2>
                <p class="text-green-600 font-semibold text-lg mb-1">${doctor.specialization || 'N/A'}</p>
                <p class="text-gray-600 mb-4"><i class="fas fa-building mr-2"></i>${doctor.department || 'N/A'}</p>
                
                <!-- Rating -->
                <div class="flex justify-center items-center mb-6">
                    <div class="flex text-yellow-400 text-lg">
                        ${generateStars(doctor.rating || 4.5)}
                    </div>
                    <span class="ml-2 text-gray-600 font-medium">${(doctor.rating || 4.5).toFixed(1)} / 5.0</span>
                </div>
                
                <!-- Info Grid -->
                <div class="grid grid-cols-2 gap-4 mb-6 text-left">
                    <div class="bg-green-50 p-4 rounded-lg border border-green-100">
                        <p class="text-xs text-gray-500 mb-1"><i class="fas fa-graduation-cap text-green-600 mr-1"></i>Qualification</p>
                        <p class="font-semibold text-gray-800">${doctor.qualification || 'MD'}</p>
                    </div>
                    <div class="bg-green-50 p-4 rounded-lg border border-green-100">
                        <p class="text-xs text-gray-500 mb-1"><i class="fas fa-briefcase text-green-600 mr-1"></i>Experience</p>
                        <p class="font-semibold text-gray-800">${doctor.experience_years || 0} years</p>
                    </div>
                    <div class="bg-green-50 p-4 rounded-lg border border-green-100">
                        <p class="text-xs text-gray-500 mb-1"><i class="fas fa-money-bill-wave text-green-600 mr-1"></i>Fee</p>
                        <p class="font-semibold text-green-600 text-lg">₱${doctor.consultation_fee || 0}</p>
                    </div>
                    <div class="bg-green-50 p-4 rounded-lg border border-green-100">
                        <p class="text-xs text-gray-500 mb-1"><i class="fas fa-phone text-green-600 mr-1"></i>Contact</p>
                        <p class="font-semibold text-gray-800 text-sm">${doctor.contact_number || 'N/A'}</p>
                    </div>
                </div>
                
                <!-- About Section -->
                ${doctor.bio ? `
                <div class="bg-gray-50 p-4 rounded-lg text-left mb-4 border border-gray-200">
                    <h3 class="font-bold text-gray-800 mb-2 flex items-center">
                        <i class="fas fa-user-md text-green-600 mr-2"></i>About Doctor
                    </h3>
                    <p class="text-gray-700 text-sm leading-relaxed">${doctor.bio}</p>
                </div>
                ` : ''}
                
                <!-- Status Badge -->
                <div class="mb-4">
                    <span class="px-4 py-2 rounded-full text-sm font-semibold ${doctor.status === 'active' ? 'bg-green-100 text-green-700 border border-green-200' : 'bg-gray-100 text-gray-700 border border-gray-200'}">
                        <i class="fas fa-circle text-xs mr-1 ${doctor.status === 'active' ? 'text-green-500' : 'text-gray-500'}"></i>
                        ${doctor.status === 'active' ? 'Available for Consultation' : 'Currently Unavailable'}
                    </span>
                </div>
                
                <!-- Email (if available) -->
                ${doctor.email ? `
                <div class="text-sm text-gray-600 mb-2">
                    <i class="fas fa-envelope mr-2"></i>${doctor.email}
                </div>
                ` : ''}
            </div>
        `,
        width: '650px',
        confirmButtonColor: '#10b981',
        confirmButtonText: '<i class="fas fa-calendar-check mr-2"></i>Book Appointment',
        showCancelButton: true,
        cancelButtonText: '<i class="fas fa-times mr-2"></i>Close',
        customClass: {
            popup: 'rounded-2xl',
            confirmButton: 'px-6 py-3 rounded-lg font-semibold',
            cancelButton: 'px-6 py-3 rounded-lg font-semibold'
        }
    }).then((result) => {
        if (result.isConfirmed) {
            bookAppointment(doctorId);
        }
    });
}

// Generate Star Rating HTML
function generateStars(rating) {
    const fullStars = Math.floor(rating);
    const hasHalfStar = rating % 1 >= 0.5;
    let stars = '';
    
    for (let i = 0; i < 5; i++) {
        if (i < fullStars) {
            stars += '<i class="fas fa-star"></i>';
        } else if (i === fullStars && hasHalfStar) {
            stars += '<i class="fas fa-star-half-alt"></i>';
        } else {
            stars += '<i class="far fa-star"></i>';
        }
    }
    
    return stars;
}

// Call enhancement functions after initial load
setTimeout(() => {
    enhanceProfilePictures();
    loadDashboardStats();
}, 1000);
