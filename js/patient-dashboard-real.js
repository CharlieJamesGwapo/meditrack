// Patient Dashboard with Real API Integration
let currentUser = null;
let allDoctors = [];
let allAppointments = [];
let currentProfile = null;
let refreshInterval = null;

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    checkAuthentication();
    initializeDashboard();
    setupEventListeners();
    updateClock();
    setInterval(updateClock, 1000);
    
    // Auto-refresh appointments every 30 seconds
    refreshInterval = setInterval(() => {
        loadAppointments();
    }, 30000);
});

// Check Authentication
function checkAuthentication() {
    // This should be handled by auth-check.js
    // If not authenticated, redirect to login
}

// Initialize Dashboard
async function initializeDashboard() {
    try {
        await Promise.all([
            loadProfile(),
            loadDoctors(),
            loadAppointments(),
            loadDepartments(),
            loadMedicalHistory(),
            loadNotifications()
        ]);
        
        Swal.fire({
            title: 'Welcome to MediTrack!',
            text: `Hello ${currentUser?.name || 'Patient'}, manage your health appointments easily`,
            icon: 'success',
            confirmButtonColor: '#10b981',
            timer: 2000,
            showConfirmButton: false
        });
    } catch (error) {
        console.error('Initialization error:', error);
    }
}

// Setup Event Listeners
function setupEventListeners() {
    // Tab switching
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', () => switchTab(btn.dataset.tab));
    });
    
    // Logout
    document.getElementById('logoutBtn').addEventListener('click', handleLogout);
    
    // Notification button
    const notificationBtn = document.getElementById('notificationBtn');
    if (notificationBtn) {
        notificationBtn.addEventListener('click', toggleNotificationDropdown);
    }
    
    // Close notification dropdown when clicking outside
    document.addEventListener('click', (e) => {
        const dropdown = document.getElementById('notificationDropdown');
        const btn = document.getElementById('notificationBtn');
        if (dropdown && !dropdown.contains(e.target) && !btn.contains(e.target)) {
            dropdown.classList.add('hidden');
        }
    });
    
    // Doctor search and filter
    const searchInput = document.getElementById('doctorSearch');
    const filterSelect = document.getElementById('departmentFilter');
    
    if (searchInput) {
        searchInput.addEventListener('input', filterDoctors);
    }
    
    if (filterSelect) {
        filterSelect.addEventListener('change', filterDoctors);
    }
    
    // Booking form
    const bookingForm = document.getElementById('bookingForm');
    if (bookingForm) {
        bookingForm.addEventListener('submit', handleBooking);
    }
    
    // Department change in booking form
    const bookDepartment = document.getElementById('bookDepartment');
    if (bookDepartment) {
        bookDepartment.addEventListener('change', loadDoctorsByDepartment);
    }
    
    // Doctor change in booking form
    const bookDoctor = document.getElementById('bookDoctor');
    if (bookDoctor) {
        bookDoctor.addEventListener('change', handleDoctorChange);
    }
    
    // Date change in booking form
    const bookDate = document.getElementById('bookDate');
    if (bookDate) {
        bookDate.addEventListener('change', loadAvailableSlots);
        // Set minimum date to today
        bookDate.min = new Date().toISOString().split('T')[0];
    }
    
    // Profile edit button
    const editProfileBtn = document.getElementById('editProfileBtn');
    if (editProfileBtn) {
        editProfileBtn.addEventListener('click', openEditProfileModal);
    }
    
    // Change password button
    const changePasswordBtn = document.getElementById('changePasswordBtn');
    if (changePasswordBtn) {
        changePasswordBtn.addEventListener('click', openChangePasswordModal);
    }
}

// Update Clock
function updateClock() {
    const now = new Date();
    const options = { 
        weekday: 'long', 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    };
    const clockElement = document.getElementById('currentTime');
    if (clockElement) {
        clockElement.textContent = now.toLocaleDateString('en-US', options);
    }
}

// Tab Switching
function switchTab(tabName) {
    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.classList.add('hidden');
    });
    
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('active', 'bg-green-600', 'text-white');
        btn.classList.add('bg-gray-100', 'text-gray-600');
    });
    
    const tabElement = document.getElementById(tabName + 'Tab');
    if (tabElement) {
        tabElement.classList.remove('hidden');
    }
    
    const activeBtn = document.querySelector(`[data-tab="${tabName}"]`);
    if (activeBtn) {
        activeBtn.classList.add('active', 'bg-green-600', 'text-white');
        activeBtn.classList.remove('bg-gray-100', 'text-gray-600');
    }
}

// Load Profile
async function loadProfile() {
    try {
        const response = await fetch('../api/patient/get-profile.php');
        const result = await response.json();
        
        if (result.success) {
            currentProfile = result.profile;
            currentUser = {
                id: result.profile.user_id,
                name: result.profile.full_name,
                email: result.profile.email
            };
            
            displayProfile(result.profile);
            
            // Update header
            const userName = document.getElementById('userName');
            if (userName) {
                userName.textContent = result.profile.full_name;
            }
            
            const welcomeName = document.getElementById('welcomeName');
            if (welcomeName) {
                welcomeName.textContent = result.profile.full_name.split(' ')[0];
            }
            
            // Update avatar with profile image or default icon
            const avatar = document.getElementById('userAvatar');
            if (avatar) {
                if (result.profile.profile_image_url) {
                    avatar.innerHTML = `<img src="${result.profile.profile_image_url}" class="w-full h-full object-cover rounded-full" alt="Profile">`;
                } else {
                    avatar.innerHTML = `<i class="fas fa-user text-green-600"></i>`;
                }
            }
        } else {
            throw new Error(result.message);
        }
    } catch (error) {
        console.error('Error loading profile:', error);
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Failed to load profile',
            confirmButtonColor: '#10b981'
        });
    }
}

// Display Profile
function displayProfile(profile) {
    document.getElementById('profileName').textContent = profile.full_name || '-';
    document.getElementById('profileEmail').textContent = profile.email || '-';
    document.getElementById('profileDOB').textContent = profile.date_of_birth || '-';
    document.getElementById('profileGender').textContent = profile.gender ? profile.gender.charAt(0).toUpperCase() + profile.gender.slice(1) : '-';
    document.getElementById('profileContact').textContent = profile.contact_number || '-';
    document.getElementById('profileBlood').textContent = profile.blood_group || '-';
    
    const address = [profile.address, profile.city, profile.province, profile.region].filter(Boolean).join(', ');
    document.getElementById('profileAddress').textContent = address || '-';
    
    const emergency = profile.emergency_contact_name && profile.emergency_contact_number 
        ? `${profile.emergency_contact_name} - ${profile.emergency_contact_number}` 
        : '-';
    document.getElementById('profileEmergency').textContent = emergency;
    
    // Profile picture - show image or default icon
    const profilePicture = document.getElementById('profilePicture');
    const profilePlaceholder = document.getElementById('profilePlaceholder');
    
    if (profile.profile_image_url) {
        profilePicture.src = profile.profile_image_url;
        profilePicture.classList.remove('hidden');
        profilePlaceholder.classList.add('hidden');
    } else {
        profilePicture.classList.add('hidden');
        profilePlaceholder.classList.remove('hidden');
    }
}

// Load Doctors
async function loadDoctors() {
    const doctorsList = document.getElementById('doctorsList');
    const doctorCount = document.getElementById('doctorCount');
    
    try {
        // Show loading state
        if (doctorsList) {
            doctorsList.innerHTML = `
                <div class="col-span-full text-center py-8">
                    <i class="fas fa-spinner fa-spin text-4xl text-green-600 mb-4"></i>
                    <p class="text-gray-600">Loading doctors...</p>
                </div>
            `;
        }
        
        const response = await fetch('../api/patient/get-doctors.php');
        
        // Check if response is ok
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const result = await response.json();
        console.log('Doctors API Response:', result); // Debug log
        
        if (result.success && result.doctors) {
            allDoctors = result.doctors;
            console.log('Loaded doctors:', allDoctors.length); // Debug log
            
            displayDoctors(allDoctors);
            
            if (doctorCount) {
                doctorCount.textContent = result.count || allDoctors.length;
            }
            
            // Populate booking form doctors
            populateBookingDoctors(allDoctors);
        } else {
            throw new Error(result.message || 'No doctors found');
        }
    } catch (error) {
        console.error('Error loading doctors:', error);
        
        if (doctorsList) {
            doctorsList.innerHTML = `
                <div class="col-span-full text-center py-12">
                    <i class="fas fa-exclamation-triangle text-6xl text-red-400 mb-4"></i>
                    <p class="text-gray-800 font-semibold mb-2">Failed to load doctors</p>
                    <p class="text-gray-600 text-sm mb-4">${error.message}</p>
                    <button onclick="loadDoctors()" class="bg-green-600 text-white px-6 py-2 rounded-lg hover:bg-green-700 transition">
                        <i class="fas fa-redo mr-2"></i>Try Again
                    </button>
                </div>
            `;
        }
        
        if (doctorCount) {
            doctorCount.textContent = '0';
        }
    }
}

// Display Doctors
function displayDoctors(doctors) {
    const container = document.getElementById('doctorsList');
    
    if (!container) {
        console.error('doctorsList container not found!');
        return;
    }
    
    if (!doctors || doctors.length === 0) {
        container.innerHTML = `
            <div class="col-span-full text-center py-12">
                <i class="fas fa-user-md text-6xl md:text-7xl text-gray-300 mb-4"></i>
                <p class="text-gray-700 font-semibold text-lg mb-2">No doctors found</p>
                <p class="text-gray-500 text-sm mb-4">Try adjusting your search or filter</p>
                <button onclick="loadDoctors()" class="bg-green-600 text-white px-6 py-2 rounded-lg hover:bg-green-700 transition">
                    <i class="fas fa-redo mr-2"></i>Refresh
                </button>
            </div>
        `;
        return;
    }
    
    console.log('Displaying', doctors.length, 'doctors'); // Debug
    
    container.innerHTML = doctors.map(doctor => {
        const doctorName = (doctor.full_name || 'Unknown Doctor').replace(/'/g, "\\'");
        const specialization = (doctor.specialization || 'General').replace(/'/g, "\\'");
        const department = doctor.department || 'General';
        const rating = doctor.rating || 4.5;
        const experience = doctor.experience_years || 0;
        const fee = parseFloat(doctor.consultation_fee || 0);
        const qualification = doctor.qualification || '';
        
        return `
        <div class="doctor-card bg-white border-2 border-gray-200 rounded-xl p-4 md:p-6 shadow-sm hover:shadow-xl transition-all duration-300">
            <div class="flex flex-col items-center text-center">
                <!-- Doctor Image -->
                <div class="w-20 h-20 md:w-24 md:h-24 rounded-full overflow-hidden bg-gradient-to-br from-green-100 to-green-200 mb-3 md:mb-4 border-4 border-green-500 shadow-lg">
                    ${doctor.profile_image_url 
                        ? `<img src="${doctor.profile_image_url}" class="w-full h-full object-cover" alt="${doctorName}" onerror="this.parentElement.innerHTML='<div class=\\'w-full h-full flex items-center justify-center\\'><i class=\\'fas fa-user-md text-3xl md:text-4xl text-green-600\\'></i></div>'">` 
                        : `<div class="w-full h-full flex items-center justify-center"><i class="fas fa-user-md text-3xl md:text-4xl text-green-600"></i></div>`
                    }
                </div>
                
                <!-- Doctor Info -->
                <h3 class="text-base md:text-lg font-bold text-gray-800 mb-1 line-clamp-2">${doctorName}</h3>
                <p class="text-green-600 font-medium text-sm md:text-base mb-2">${specialization}</p>
                
                <!-- Department -->
                <div class="flex items-center text-xs md:text-sm text-gray-600 mb-3 bg-gray-50 px-3 py-1 rounded-full">
                    <i class="fas fa-hospital mr-1"></i>
                    <span class="truncate max-w-[150px]">${department}</span>
                </div>
                
                <!-- Rating and Experience -->
                <div class="flex items-center justify-center gap-3 md:gap-4 text-xs md:text-sm mb-3 w-full">
                    <div class="flex items-center bg-yellow-50 px-2 py-1 rounded-lg">
                        <i class="fas fa-star text-yellow-400 mr-1"></i>
                        <span class="font-bold text-yellow-700">${rating}</span>
                    </div>
                    <div class="flex items-center bg-blue-50 px-2 py-1 rounded-lg text-gray-700">
                        <i class="fas fa-briefcase text-blue-600 mr-1"></i>
                        <span class="font-medium">${experience}+ yrs</span>
                    </div>
                </div>
                
                <!-- Qualification -->
                ${qualification ? `<p class="text-xs text-gray-500 mb-3 line-clamp-1">${qualification}</p>` : ''}
                
                <!-- Consultation Fee -->
                <div class="text-lg md:text-xl font-bold text-green-600 mb-4 bg-green-50 px-4 py-2 rounded-lg">
                    ₱${fee.toFixed(2)}
                </div>
                
                <!-- Book Button -->
                <button onclick="bookAppointmentWithDoctor(${doctor.id}, '${doctorName}', '${specialization}', ${fee})" 
                        class="w-full bg-gradient-to-r from-green-600 to-green-700 text-white py-2 md:py-3 px-4 rounded-lg hover:from-green-700 hover:to-green-800 transition-all duration-300 shadow-md hover:shadow-lg text-sm md:text-base font-medium">
                    <i class="fas fa-calendar-plus mr-2"></i>Book Appointment
                </button>
            </div>
        </div>
    `;
    }).join('');
    
    console.log('Doctors displayed successfully!'); // Debug
}

// Filter Doctors
function filterDoctors() {
    const searchTerm = document.getElementById('doctorSearch').value.toLowerCase();
    const department = document.getElementById('departmentFilter').value;
    
    const filtered = allDoctors.filter(doctor => {
        const matchesSearch = doctor.full_name.toLowerCase().includes(searchTerm) || 
                            doctor.specialization.toLowerCase().includes(searchTerm);
        const matchesDepartment = !department || doctor.department === department;
        return matchesSearch && matchesDepartment;
    });
    
    displayDoctors(filtered);
    document.getElementById('doctorCount').textContent = filtered.length;
}

// Load Departments
async function loadDepartments() {
    try {
        const response = await fetch('../api/patient/get-departments.php');
        const result = await response.json();
        
        if (result.success) {
            // Populate filter dropdown
            const filterSelect = document.getElementById('departmentFilter');
            if (filterSelect) {
                result.departments.forEach(dept => {
                    const option = document.createElement('option');
                    option.value = dept;
                    option.textContent = dept;
                    filterSelect.appendChild(option);
                });
            }
            
            // Populate booking form dropdown
            const bookSelect = document.getElementById('bookDepartment');
            if (bookSelect) {
                result.departments.forEach(dept => {
                    const option = document.createElement('option');
                    option.value = dept;
                    option.textContent = dept;
                    bookSelect.appendChild(option);
                });
            }
        }
    } catch (error) {
        console.error('Error loading departments:', error);
    }
}

// Populate Booking Doctors
function populateBookingDoctors(doctors) {
    const select = document.getElementById('bookDoctor');
    if (!select) return;
    
    // Clear existing options except the first one
    select.innerHTML = '<option value="">Select Doctor</option>';
    
    doctors.forEach(doctor => {
        const option = document.createElement('option');
        option.value = doctor.id;
        option.textContent = `${doctor.full_name} - ${doctor.specialization}`;
        option.dataset.fee = doctor.consultation_fee;
        option.dataset.department = doctor.department;
        select.appendChild(option);
    });
}

// Load Doctors by Department
function loadDoctorsByDepartment() {
    const department = document.getElementById('bookDepartment').value;
    const doctorSelect = document.getElementById('bookDoctor');
    
    if (!doctorSelect) return;
    
    // Clear and reset
    doctorSelect.innerHTML = '<option value="">Select Doctor</option>';
    
    const filtered = department 
        ? allDoctors.filter(d => d.department === department)
        : allDoctors;
    
    filtered.forEach(doctor => {
        const option = document.createElement('option');
        option.value = doctor.id;
        option.textContent = `${doctor.full_name} - ${doctor.specialization}`;
        option.dataset.fee = doctor.consultation_fee;
        doctorSelect.appendChild(option);
    });
    
    // Reset time slots
    const timeSelect = document.getElementById('bookTime');
    if (timeSelect) {
        timeSelect.innerHTML = '<option value="">Select date and doctor first</option>';
    }
}

// Handle Doctor Change
function handleDoctorChange() {
    loadAvailableSlots();
}

// Load Available Slots
async function loadAvailableSlots() {
    const doctorId = document.getElementById('bookDoctor').value;
    const date = document.getElementById('bookDate').value;
    const timeSelect = document.getElementById('bookTime');
    
    if (!timeSelect) return;
    
    if (!doctorId || !date) {
        timeSelect.innerHTML = '<option value="">Select date and doctor first</option>';
        return;
    }
    
    try {
        timeSelect.innerHTML = '<option value="">Loading slots...</option>';
        
        const response = await fetch(`../api/patient/get-available-slots.php?doctor_id=${doctorId}&date=${date}`);
        const result = await response.json();
        
        if (result.success) {
            timeSelect.innerHTML = '<option value="">Select time slot</option>';
            
            result.slots.forEach(slot => {
                if (slot.available) {
                    const option = document.createElement('option');
                    option.value = slot.time;
                    option.textContent = slot.display;
                    timeSelect.appendChild(option);
                }
            });
            
            if (timeSelect.options.length === 1) {
                timeSelect.innerHTML = '<option value="">No available slots for this date</option>';
            }
        } else {
            throw new Error(result.message);
        }
    } catch (error) {
        console.error('Error loading slots:', error);
        timeSelect.innerHTML = '<option value="">Error loading slots</option>';
    }
}

// Book Appointment with Doctor
function bookAppointmentWithDoctor(doctorId, doctorName, specialization, fee) {
    Swal.fire({
        title: `Book Appointment`,
        html: `
            <div class="text-left space-y-2">
                <p><strong>Doctor:</strong> ${doctorName}</p>
                <p><strong>Specialization:</strong> ${specialization}</p>
                <p><strong>Consultation Fee:</strong> ₱${parseFloat(fee).toFixed(2)}</p>
            </div>
        `,
        icon: 'info',
        showCancelButton: true,
        confirmButtonColor: '#10b981',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Continue to Booking',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            switchTab('book');
            
            // Pre-select doctor
            setTimeout(() => {
                const doctorSelect = document.getElementById('bookDoctor');
                if (doctorSelect) {
                    doctorSelect.value = doctorId;
                    
                    // Trigger change to load slots if date is selected
                    const dateInput = document.getElementById('bookDate');
                    if (dateInput && dateInput.value) {
                        loadAvailableSlots();
                    }
                }
            }, 100);
        }
    });
}

// Handle Booking Form Submit
async function handleBooking(e) {
    e.preventDefault();
    
    const doctorId = document.getElementById('bookDoctor').value;
    const date = document.getElementById('bookDate').value;
    const time = document.getElementById('bookTime').value;
    const reason = document.getElementById('bookReason').value;
    
    if (!doctorId || !date || !time || !reason) {
        Swal.fire({
            icon: 'error',
            title: 'Missing Information',
            text: 'Please fill in all required fields',
            confirmButtonColor: '#10b981'
        });
        return;
    }
    
    // Show loading
    const submitBtn = document.querySelector('#bookingForm button[type="submit"]');
    const bookingText = document.getElementById('bookingText');
    const bookingSpinner = document.getElementById('bookingSpinner');
    
    submitBtn.disabled = true;
    bookingText.textContent = 'Booking...';
    bookingSpinner.classList.remove('hidden');
    
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
            // Reset form
            document.getElementById('bookingForm').reset();
            
            // Reload appointments
            await loadAppointments();
            
            // Show success message with email notification
            Swal.fire({
                icon: 'success',
                title: '🎉 Appointment Booked!',
                html: `
                    <div class="text-left">
                        <p class="mb-3"><strong>Appointment Number:</strong> ${result.appointment.appointment_number}</p>
                        <p><strong>Doctor:</strong> ${result.appointment.doctor_name}</p>
                        <p><strong>Specialization:</strong> ${result.appointment.specialization}</p>
                        <p><strong>Date:</strong> ${new Date(result.appointment.appointment_date).toLocaleDateString()}</p>
                        <p><strong>Time:</strong> ${result.appointment.appointment_time}</p>
                        <div class="mt-4 p-3 bg-green-50 border-l-4 border-green-500 rounded">
                            <p class="text-sm text-green-800">
                                <i class="fas fa-envelope mr-2"></i>
                                <strong>Email Sent!</strong> A confirmation email has been sent to your inbox with all appointment details.
                            </p>
                        </div>
                        <p class="mt-3 text-sm text-gray-600">
                            <i class="fas fa-info-circle text-green-600 mr-1"></i>
                            Please arrive 15 minutes before your appointment time.
                        </p>
                    </div>
                `,
                confirmButtonColor: '#10b981',
                confirmButtonText: 'View My Appointments',
                width: '500px'
            }).then(() => {
                switchTab('appointments');
                loadNotifications(); // Reload notifications
            });
        } else {
            throw new Error(result.message);
        }
    } catch (error) {
        Swal.fire({
            icon: 'error',
            title: 'Booking Failed',
            text: error.message || 'Failed to book appointment. Please try again.',
            confirmButtonColor: '#10b981'
        });
    } finally {
        submitBtn.disabled = false;
        bookingText.textContent = 'Book Appointment';
        bookingSpinner.classList.add('hidden');
    }
}

// Load Appointments
async function loadAppointments() {
    try {
        const response = await fetch('../api/patient/get-appointments.php');
        const result = await response.json();
        
        if (result.success) {
            allAppointments = result.appointments;
            displayAppointments();
            
            const upcomingCount = document.getElementById('upcomingCount');
            if (upcomingCount) {
                upcomingCount.textContent = result.upcoming_count;
            }
        } else {
            throw new Error(result.message);
        }
    } catch (error) {
        console.error('Error loading appointments:', error);
        const appointmentsList = document.getElementById('appointmentsList');
        if (appointmentsList) {
            appointmentsList.innerHTML = `
                <div class="text-center py-8">
                    <i class="fas fa-exclamation-circle text-6xl text-red-300 mb-4"></i>
                    <p class="text-gray-600">Failed to load appointments</p>
                </div>
            `;
        }
    }
}

// Display Appointments
function displayAppointments() {
    const container = document.getElementById('appointmentsList');
    if (!container) return;
    
    if (allAppointments.length === 0) {
        container.innerHTML = `
            <div class="text-center py-8">
                <i class="fas fa-calendar-times text-6xl text-gray-300 mb-4"></i>
                <p class="text-gray-600 mb-4">No appointments scheduled</p>
                <button onclick="switchTab('book')" class="bg-green-600 text-white py-2 px-6 rounded-lg hover:bg-green-700 transition">
                    <i class="fas fa-plus-circle mr-2"></i>Book Your First Appointment
                </button>
            </div>
        `;
        return;
    }
    
    container.innerHTML = allAppointments.map(apt => `
        <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition">
            <div class="flex items-start justify-between">
                <div class="flex-1">
                    <div class="flex items-center mb-2">
                        <div class="w-12 h-12 rounded-full overflow-hidden bg-green-100 mr-3 border-2 border-green-500">
                            ${apt.doctor_image_url 
                                ? `<img src="${apt.doctor_image_url}" class="w-full h-full object-cover" alt="Doctor">` 
                                : `<div class="w-full h-full flex items-center justify-center"><i class="fas fa-user-md text-green-600"></i></div>`
                            }
                        </div>
                        <div>
                            <h3 class="font-bold text-lg text-gray-800">${apt.doctor_name}</h3>
                            <p class="text-green-600 text-sm">${apt.specialization}</p>
                        </div>
                    </div>
                    <div class="ml-15 space-y-1">
                        <div class="flex items-center text-sm text-gray-600">
                            <i class="fas fa-calendar mr-2 w-4"></i>
                            <span>${apt.formatted_date}</span>
                        </div>
                        <div class="flex items-center text-sm text-gray-600">
                            <i class="fas fa-clock mr-2 w-4"></i>
                            <span>${apt.formatted_time}</span>
                        </div>
                        <div class="flex items-center text-sm text-gray-600">
                            <i class="fas fa-hospital mr-2 w-4"></i>
                            <span>${apt.department}</span>
                        </div>
                        ${apt.reason_for_visit ? `
                        <div class="flex items-start text-sm text-gray-600 mt-2">
                            <i class="fas fa-notes-medical mr-2 w-4 mt-1"></i>
                            <span>${apt.reason_for_visit}</span>
                        </div>
                        ` : ''}
                    </div>
                </div>
                <div class="flex flex-col items-end space-y-2">
                    <span class="px-3 py-1 rounded-full text-xs font-medium ${getStatusClass(apt.status)}">
                        ${apt.status.toUpperCase()}
                    </span>
                    ${apt.status === 'scheduled' ? `
                    <button onclick="cancelAppointment(${apt.id})" class="text-red-600 hover:text-red-800 text-sm transition">
                        <i class="fas fa-times-circle mr-1"></i>Cancel
                    </button>
                    ` : ''}
                </div>
            </div>
        </div>
    `).join('');
}

// Get Status Class
function getStatusClass(status) {
    const classes = {
        'scheduled': 'bg-green-100 text-green-800',
        'completed': 'bg-blue-100 text-blue-800',
        'cancelled': 'bg-red-100 text-red-800',
        'in_progress': 'bg-yellow-100 text-yellow-800'
    };
    return classes[status] || 'bg-gray-100 text-gray-800';
}

// Cancel Appointment
function cancelAppointment(id) {
    Swal.fire({
        title: 'Cancel Appointment?',
        text: 'Are you sure you want to cancel this appointment?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Yes, cancel it',
        cancelButtonText: 'No, keep it'
    }).then(async (result) => {
        if (result.isConfirmed) {
            try {
                // Call API to cancel appointment
                const response = await fetch('../api/patient/cancel-appointment.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ appointment_id: id })
                });
                
                const apiResult = await response.json();
                
                if (apiResult.success) {
                    await loadAppointments();
                    
                    Swal.fire({
                        icon: 'success',
                        title: 'Cancelled!',
                        text: 'Your appointment has been cancelled',
                        confirmButtonColor: '#10b981',
                        timer: 2000
                    });
                } else {
                    throw new Error(apiResult.message);
                }
            } catch (error) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Failed to cancel appointment',
                    confirmButtonColor: '#10b981'
                });
            }
        }
    });
}

// Load Medical History
async function loadMedicalHistory() {
    try {
        const response = await fetch('../api/visits/patient-history.php');
        const result = await response.json();
        
        const historyList = document.getElementById('historyList');
        if (!historyList) return;
        
        if (result.success && result.visits && result.visits.length > 0) {
            historyList.innerHTML = result.visits.map(visit => `
                <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition">
                    <div class="flex items-start justify-between mb-2">
                        <div>
                            <h3 class="font-bold text-lg text-gray-800">${visit.doctor_name}</h3>
                            <p class="text-sm text-gray-600">${visit.specialization}</p>
                        </div>
                        <span class="text-sm text-gray-500">${visit.formatted_date}</span>
                    </div>
                    <div class="space-y-2 text-sm">
                        ${visit.diagnosis ? `
                        <div>
                            <span class="font-medium text-gray-700">Diagnosis:</span>
                            <p class="text-gray-600">${visit.diagnosis}</p>
                        </div>
                        ` : ''}
                        ${visit.prescription ? `
                        <div>
                            <span class="font-medium text-gray-700">Prescription:</span>
                            <p class="text-gray-600">${visit.prescription}</p>
                        </div>
                        ` : ''}
                        ${visit.notes ? `
                        <div>
                            <span class="font-medium text-gray-700">Notes:</span>
                            <p class="text-gray-600">${visit.notes}</p>
                        </div>
                        ` : ''}
                    </div>
                </div>
            `).join('');
        } else {
            historyList.innerHTML = `
                <div class="text-center py-8">
                    <i class="fas fa-file-medical text-6xl text-gray-300 mb-4"></i>
                    <p class="text-gray-600">No medical history available</p>
                </div>
            `;
        }
    } catch (error) {
        console.error('Error loading medical history:', error);
        const historyList = document.getElementById('historyList');
        if (historyList) {
            historyList.innerHTML = `
                <div class="text-center py-8">
                    <i class="fas fa-exclamation-circle text-6xl text-red-300 mb-4"></i>
                    <p class="text-gray-600">Failed to load medical history</p>
                </div>
            `;
        }
    }
}

// Open Edit Profile Modal
function openEditProfileModal() {
    Swal.fire({
        title: 'Edit Profile',
        html: `
            <div class="text-left space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
                    <input type="text" id="edit_full_name" value="${currentProfile.full_name || ''}" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Contact Number</label>
                    <input type="text" id="edit_contact" value="${currentProfile.contact_number || ''}" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Blood Group</label>
                    <select id="edit_blood" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500">
                        <option value="">Select Blood Group</option>
                        <option value="A+" ${currentProfile.blood_group === 'A+' ? 'selected' : ''}>A+</option>
                        <option value="A-" ${currentProfile.blood_group === 'A-' ? 'selected' : ''}>A-</option>
                        <option value="B+" ${currentProfile.blood_group === 'B+' ? 'selected' : ''}>B+</option>
                        <option value="B-" ${currentProfile.blood_group === 'B-' ? 'selected' : ''}>B-</option>
                        <option value="AB+" ${currentProfile.blood_group === 'AB+' ? 'selected' : ''}>AB+</option>
                        <option value="AB-" ${currentProfile.blood_group === 'AB-' ? 'selected' : ''}>AB-</option>
                        <option value="O+" ${currentProfile.blood_group === 'O+' ? 'selected' : ''}>O+</option>
                        <option value="O-" ${currentProfile.blood_group === 'O-' ? 'selected' : ''}>O-</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Emergency Contact Name</label>
                    <input type="text" id="edit_emergency_name" value="${currentProfile.emergency_contact_name || ''}" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Emergency Contact Number</label>
                    <input type="text" id="edit_emergency_number" value="${currentProfile.emergency_contact_number || ''}" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500">
                </div>
            </div>
        `,
        showCancelButton: true,
        confirmButtonColor: '#10b981',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Save Changes',
        cancelButtonText: 'Cancel',
        preConfirm: () => {
            return {
                full_name: document.getElementById('edit_full_name').value,
                contact_number: document.getElementById('edit_contact').value,
                blood_group: document.getElementById('edit_blood').value,
                emergency_contact_name: document.getElementById('edit_emergency_name').value,
                emergency_contact_number: document.getElementById('edit_emergency_number').value,
                date_of_birth: currentProfile.date_of_birth,
                gender: currentProfile.gender,
                address: currentProfile.address,
                region: currentProfile.region,
                province: currentProfile.province,
                city: currentProfile.city,
                zip_code: currentProfile.zip_code,
                allergies: currentProfile.allergies,
                medical_history: currentProfile.medical_history
            };
        }
    }).then(async (result) => {
        if (result.isConfirmed) {
            await updateProfile(result.value);
        }
    });
}

// Update Profile
async function updateProfile(data) {
    try {
        const formData = new FormData();
        Object.keys(data).forEach(key => {
            formData.append(key, data[key] || '');
        });
        
        const response = await fetch('../api/patient/update-profile.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            await loadProfile();
            
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: 'Profile updated successfully',
                confirmButtonColor: '#10b981',
                timer: 2000
            });
        } else {
            throw new Error(result.message);
        }
    } catch (error) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Failed to update profile',
            confirmButtonColor: '#10b981'
        });
    }
}

// Open Change Password Modal
function openChangePasswordModal() {
    Swal.fire({
        title: 'Change Password',
        html: `
            <div class="text-left space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Current Password</label>
                    <input type="password" id="current_password" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">New Password</label>
                    <input type="password" id="new_password" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Confirm New Password</label>
                    <input type="password" id="confirm_password" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500">
                </div>
            </div>
        `,
        showCancelButton: true,
        confirmButtonColor: '#10b981',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Change Password',
        cancelButtonText: 'Cancel',
        preConfirm: () => {
            const current = document.getElementById('current_password').value;
            const newPass = document.getElementById('new_password').value;
            const confirm = document.getElementById('confirm_password').value;
            
            if (!current || !newPass || !confirm) {
                Swal.showValidationMessage('All fields are required');
                return false;
            }
            
            if (newPass !== confirm) {
                Swal.showValidationMessage('New passwords do not match');
                return false;
            }
            
            if (newPass.length < 6) {
                Swal.showValidationMessage('Password must be at least 6 characters');
                return false;
            }
            
            return {
                current_password: current,
                new_password: newPass,
                confirm_password: confirm
            };
        }
    }).then(async (result) => {
        if (result.isConfirmed) {
            await changePassword(result.value);
        }
    });
}

// Change Password
async function changePassword(data) {
    try {
        const response = await fetch('../api/patient/change-password.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (result.success) {
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: 'Password changed successfully',
                confirmButtonColor: '#10b981',
                timer: 2000
            });
        } else {
            throw new Error(result.message);
        }
    } catch (error) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: error.message || 'Failed to change password',
            confirmButtonColor: '#10b981'
        });
    }
}

// Handle Logout
function handleLogout() {
    Swal.fire({
        title: 'Logout?',
        text: 'Are you sure you want to logout?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#10b981',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Yes, logout',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = '../api/auth/logout.php';
        }
    });
}

// Toggle Notification Dropdown
function toggleNotificationDropdown() {
    const dropdown = document.getElementById('notificationDropdown');
    if (dropdown) {
        dropdown.classList.toggle('hidden');
        if (!dropdown.classList.contains('hidden')) {
            loadNotifications();
        }
    }
}

// Load Notifications
async function loadNotifications() {
    try {
        const response = await fetch('../api/notifications/list.php');
        const result = await response.json();
        
        const notificationList = document.getElementById('notificationList');
        const notificationBadge = document.getElementById('notificationBadge');
        
        if (result.success && result.notifications && result.notifications.length > 0) {
            // Update badge
            const unreadCount = result.notifications.filter(n => !n.is_read).length;
            if (unreadCount > 0) {
                notificationBadge.textContent = unreadCount;
                notificationBadge.classList.remove('hidden');
            } else {
                notificationBadge.classList.add('hidden');
            }
            
            // Display notifications
            notificationList.innerHTML = result.notifications.map(notification => `
                <div class="p-4 border-b border-gray-100 hover:bg-gray-50 cursor-pointer ${!notification.is_read ? 'bg-green-50' : ''}" 
                     onclick="markNotificationRead(${notification.id})">
                    <div class="flex items-start">
                        <div class="flex-shrink-0">
                            <i class="fas ${getNotificationIcon(notification.type)} text-green-600 text-lg"></i>
                        </div>
                        <div class="ml-3 flex-1">
                            <p class="text-sm font-medium text-gray-900">${notification.title}</p>
                            <p class="text-xs text-gray-600 mt-1">${notification.message}</p>
                            <p class="text-xs text-gray-400 mt-1">${formatNotificationTime(notification.created_at)}</p>
                        </div>
                        ${!notification.is_read ? '<div class="flex-shrink-0"><span class="inline-block w-2 h-2 bg-green-500 rounded-full"></span></div>' : ''}
                    </div>
                </div>
            `).join('');
        } else {
            notificationBadge.classList.add('hidden');
            notificationList.innerHTML = `
                <div class="p-4 text-center text-gray-500 text-sm">
                    <i class="fas fa-bell-slash text-3xl text-gray-300 mb-2"></i>
                    <p>No notifications</p>
                </div>
            `;
        }
    } catch (error) {
        console.error('Error loading notifications:', error);
    }
}

// Get Notification Icon
function getNotificationIcon(type) {
    const icons = {
        'appointment': 'fa-calendar-check',
        'reminder': 'fa-clock',
        'cancellation': 'fa-times-circle',
        'update': 'fa-info-circle',
        'system': 'fa-bell'
    };
    return icons[type] || 'fa-bell';
}

// Format Notification Time
function formatNotificationTime(timestamp) {
    const date = new Date(timestamp);
    const now = new Date();
    const diffMs = now - date;
    const diffMins = Math.floor(diffMs / 60000);
    const diffHours = Math.floor(diffMs / 3600000);
    const diffDays = Math.floor(diffMs / 86400000);
    
    if (diffMins < 1) return 'Just now';
    if (diffMins < 60) return `${diffMins} minute${diffMins > 1 ? 's' : ''} ago`;
    if (diffHours < 24) return `${diffHours} hour${diffHours > 1 ? 's' : ''} ago`;
    if (diffDays < 7) return `${diffDays} day${diffDays > 1 ? 's' : ''} ago`;
    return date.toLocaleDateString();
}

// Mark Notification as Read
async function markNotificationRead(notificationId) {
    try {
        await fetch('../api/notifications/mark-read.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ notification_id: notificationId })
        });
        
        // Reload notifications
        loadNotifications();
    } catch (error) {
        console.error('Error marking notification as read:', error);
    }
}
