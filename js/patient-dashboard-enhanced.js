// Enhanced Patient Dashboard JavaScript
let currentUser = null;
let allDoctors = [];
let allAppointments = [];

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    initializeDashboard();
    setupEventListeners();
    updateClock();
    setInterval(updateClock, 1000);
});

// Initialize Dashboard
async function initializeDashboard() {
    // Load user data
    currentUser = {
        id: 1,
        name: 'John Doe',
        email: 'john@example.com',
        role: 'patient'
    };
    
    document.getElementById('userName').textContent = currentUser.name;
    document.getElementById('welcomeName').textContent = currentUser.name;
    
    // Load initial data
    await Promise.all([
        loadDoctors(),
        loadAppointments(),
        loadProfile()
    ]);
    
    // Show welcome message
    Swal.fire({
        title: 'Welcome to MediTrack!',
        text: `Hello ${currentUser.name}, manage your health appointments easily`,
        icon: 'success',
        confirmButtonColor: '#10b981',
        timer: 2000,
        showConfirmButton: false
    });
}

// Setup Event Listeners
function setupEventListeners() {
    // Tab switching
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', () => switchTab(btn.dataset.tab));
    });
    
    // Logout
    document.getElementById('logoutBtn').addEventListener('click', handleLogout);
    
    // Doctor search and filter
    document.getElementById('doctorSearch')?.addEventListener('input', filterDoctors);
    document.getElementById('departmentFilter')?.addEventListener('change', filterDoctors);
    
    // Mobile menu
    document.getElementById('mobileMenuBtn')?.addEventListener('click', toggleMobileMenu);
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
    document.getElementById('currentTime').textContent = now.toLocaleDateString('en-US', options);
}

// Tab Switching
function switchTab(tabName) {
    // Hide all tabs
    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.classList.add('hidden');
    });
    
    // Remove active class from all buttons
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('active', 'bg-green-600', 'text-white');
        btn.classList.add('bg-gray-100', 'text-gray-600');
    });
    
    // Show selected tab
    document.getElementById(tabName + 'Tab').classList.remove('hidden');
    
    // Add active class to clicked button
    const activeBtn = document.querySelector(`[data-tab="${tabName}"]`);
    activeBtn.classList.add('active', 'bg-green-600', 'text-white');
    activeBtn.classList.remove('bg-gray-100', 'text-gray-600');
}

// Load Doctors
async function loadDoctors() {
    try {
        // Simulated doctor data - replace with API call
        allDoctors = [
            {
                id: 1,
                name: 'Dr. John Smith',
                specialization: 'Cardiologist',
                department: 'Cardiology',
                experience: 15,
                fee: 150,
                rating: 4.8,
                image: '../assets/images/doctor1.jpg',
                available: true
            },
            {
                id: 2,
                name: 'Dr. Sarah Johnson',
                specialization: 'General Physician',
                department: 'General Medicine',
                experience: 10,
                fee: 100,
                rating: 4.9,
                image: '../assets/images/doctor2.jpg',
                available: true
            },
            {
                id: 3,
                name: 'Dr. Michael Williams',
                specialization: 'Orthopedic Surgeon',
                department: 'Orthopedics',
                experience: 12,
                fee: 200,
                rating: 4.7,
                image: '../assets/images/doctor3.jpg',
                available: true
            },
            {
                id: 4,
                name: 'Dr. Emily Brown',
                specialization: 'Pediatrician',
                department: 'Pediatrics',
                experience: 8,
                fee: 120,
                rating: 4.9,
                image: '../assets/images/doctor4.jpg',
                available: true
            },
            {
                id: 5,
                name: 'Dr. David Lee',
                specialization: 'Dermatologist',
                department: 'Dermatology',
                experience: 11,
                fee: 130,
                rating: 4.6,
                image: '../assets/images/doctor5.jpg',
                available: true
            },
            {
                id: 6,
                name: 'Dr. Maria Garcia',
                specialization: 'Gynecologist',
                department: 'Gynecology',
                experience: 14,
                fee: 160,
                rating: 4.8,
                image: '../assets/images/doctor6.jpg',
                available: true
            }
        ];
        
        displayDoctors(allDoctors);
        populateDepartmentFilter();
        document.getElementById('doctorCount').textContent = allDoctors.length;
        
    } catch (error) {
        console.error('Error loading doctors:', error);
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Failed to load doctors',
            confirmButtonColor: '#10b981'
        });
    }
}

// Display Doctors
function displayDoctors(doctors) {
    const container = document.getElementById('doctorsList');
    
    if (doctors.length === 0) {
        container.innerHTML = `
            <div class="col-span-full text-center py-8">
                <i class="fas fa-user-md text-6xl text-gray-300 mb-4"></i>
                <p class="text-gray-600">No doctors found</p>
            </div>
        `;
        return;
    }
    
    container.innerHTML = doctors.map(doctor => `
        <div class="doctor-card bg-white border border-gray-200 rounded-xl p-6 shadow-sm">
            <div class="flex flex-col items-center text-center">
                <div class="w-24 h-24 rounded-full overflow-hidden bg-green-100 mb-4 border-4 border-green-500">
                    <div class="w-full h-full flex items-center justify-center">
                        <i class="fas fa-user-md text-4xl text-green-600"></i>
                    </div>
                </div>
                <h3 class="text-lg font-bold text-gray-800 mb-1">${doctor.name}</h3>
                <p class="text-green-600 font-medium mb-2">${doctor.specialization}</p>
                <div class="flex items-center text-sm text-gray-600 mb-3">
                    <i class="fas fa-hospital mr-1"></i>
                    <span>${doctor.department}</span>
                </div>
                <div class="flex items-center justify-center space-x-4 text-sm mb-4">
                    <div class="flex items-center">
                        <i class="fas fa-star text-yellow-400 mr-1"></i>
                        <span class="font-medium">${doctor.rating}</span>
                    </div>
                    <div class="flex items-center text-gray-600">
                        <i class="fas fa-briefcase mr-1"></i>
                        <span>${doctor.experience}+ years</span>
                    </div>
                </div>
                <div class="text-lg font-bold text-green-600 mb-4">
                    ₱${doctor.fee}
                </div>
                <button onclick="bookAppointmentWithDoctor(${doctor.id})" 
                        class="w-full bg-green-600 text-white py-2 px-4 rounded-lg hover:bg-green-700 transition">
                    <i class="fas fa-calendar-plus mr-2"></i>Book Appointment
                </button>
            </div>
        </div>
    `).join('');
}

// Filter Doctors
function filterDoctors() {
    const searchTerm = document.getElementById('doctorSearch').value.toLowerCase();
    const department = document.getElementById('departmentFilter').value;
    
    const filtered = allDoctors.filter(doctor => {
        const matchesSearch = doctor.name.toLowerCase().includes(searchTerm) || 
                            doctor.specialization.toLowerCase().includes(searchTerm);
        const matchesDepartment = !department || doctor.department === department;
        return matchesSearch && matchesDepartment;
    });
    
    displayDoctors(filtered);
    document.getElementById('doctorCount').textContent = filtered.length;
}

// Populate Department Filter
function populateDepartmentFilter() {
    const departments = [...new Set(allDoctors.map(d => d.department))];
    const select = document.getElementById('departmentFilter');
    
    departments.forEach(dept => {
        const option = document.createElement('option');
        option.value = dept;
        option.textContent = dept;
        select.appendChild(option);
    });
}

// Book Appointment with Doctor
function bookAppointmentWithDoctor(doctorId) {
    const doctor = allDoctors.find(d => d.id === doctorId);
    
    Swal.fire({
        title: `Book Appointment with ${doctor.name}`,
        html: `
            <div class="text-left">
                <p class="mb-2"><strong>Specialization:</strong> ${doctor.specialization}</p>
                <p class="mb-2"><strong>Department:</strong> ${doctor.department}</p>
                <p class="mb-4"><strong>Consultation Fee:</strong> ₱${doctor.fee}</p>
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
            // Switch to booking tab and pre-select doctor
            switchTab('book');
            // Pre-fill doctor selection if needed
        }
    });
}

// Load Appointments
async function loadAppointments() {
    try {
        // Simulated appointments - replace with API call
        allAppointments = [
            {
                id: 1,
                doctor: 'Dr. John Smith',
                specialization: 'Cardiologist',
                date: '2024-11-15',
                time: '10:00 AM',
                status: 'scheduled',
                reason: 'Regular checkup'
            },
            {
                id: 2,
                doctor: 'Dr. Sarah Johnson',
                specialization: 'General Physician',
                date: '2024-11-20',
                time: '2:00 PM',
                status: 'scheduled',
                reason: 'Follow-up consultation'
            }
        ];
        
        displayAppointments();
        updateUpcomingCount();
        
    } catch (error) {
        console.error('Error loading appointments:', error);
    }
}

// Display Appointments
function displayAppointments() {
    const container = document.getElementById('appointmentsList');
    
    if (allAppointments.length === 0) {
        container.innerHTML = `
            <div class="text-center py-8">
                <i class="fas fa-calendar-times text-6xl text-gray-300 mb-4"></i>
                <p class="text-gray-600">No appointments scheduled</p>
                <button onclick="switchTab('book')" class="mt-4 bg-green-600 text-white py-2 px-6 rounded-lg hover:bg-green-700">
                    Book Your First Appointment
                </button>
            </div>
        `;
        return;
    }
    
    container.innerHTML = allAppointments.map(apt => `
        <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition">
            <div class="flex items-center justify-between">
                <div class="flex-1">
                    <h3 class="font-bold text-lg text-gray-800">${apt.doctor}</h3>
                    <p class="text-green-600 text-sm">${apt.specialization}</p>
                    <div class="mt-2 flex items-center space-x-4 text-sm text-gray-600">
                        <span><i class="fas fa-calendar mr-1"></i>${apt.date}</span>
                        <span><i class="fas fa-clock mr-1"></i>${apt.time}</span>
                    </div>
                    <p class="mt-2 text-sm text-gray-600">${apt.reason}</p>
                </div>
                <div class="flex flex-col space-y-2">
                    <span class="px-3 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                        ${apt.status}
                    </span>
                    <button onclick="cancelAppointment(${apt.id})" class="text-red-600 hover:text-red-800 text-sm">
                        <i class="fas fa-times mr-1"></i>Cancel
                    </button>
                </div>
            </div>
        </div>
    `).join('');
}

// Update Upcoming Count
function updateUpcomingCount() {
    const upcoming = allAppointments.filter(apt => apt.status === 'scheduled').length;
    document.getElementById('upcomingCount').textContent = upcoming;
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
    }).then((result) => {
        if (result.isConfirmed) {
            allAppointments = allAppointments.filter(apt => apt.id !== id);
            displayAppointments();
            updateUpcomingCount();
            
            Swal.fire({
                icon: 'success',
                title: 'Cancelled!',
                text: 'Your appointment has been cancelled',
                confirmButtonColor: '#10b981',
                timer: 2000
            });
        }
    });
}

// Load Profile
async function loadProfile() {
    try {
        // Simulated profile data - replace with API call
        const profile = {
            name: 'John Doe',
            email: 'john@example.com',
            dob: '1990-05-15',
            gender: 'Male',
            contact: '+63 912 345 6789',
            blood: 'O+',
            address: 'Makati City, Metro Manila, Philippines',
            emergency: 'Jane Doe - +63 912 345 6780'
        };
        
        document.getElementById('profileName').textContent = profile.name;
        document.getElementById('profileEmail').textContent = profile.email;
        document.getElementById('profileDOB').textContent = profile.dob;
        document.getElementById('profileGender').textContent = profile.gender;
        document.getElementById('profileContact').textContent = profile.contact;
        document.getElementById('profileBlood').textContent = profile.blood;
        document.getElementById('profileAddress').textContent = profile.address;
        document.getElementById('profileEmergency').textContent = profile.emergency;
        
    } catch (error) {
        console.error('Error loading profile:', error);
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
            Swal.fire({
                icon: 'success',
                title: 'Logged out successfully',
                text: 'Redirecting to homepage...',
                confirmButtonColor: '#10b981',
                timer: 1500,
                showConfirmButton: false
            }).then(() => {
                window.location.href = '../index.html';
            });
        }
    });
}

// Toggle Mobile Menu
function toggleMobileMenu() {
    // Add mobile menu functionality if needed
    console.log('Mobile menu toggled');
}
