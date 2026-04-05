/**
 * MediTrack Reports - Real-time Analytics Dashboard
 * Professional reporting system for doctors and patients
 */

// Configuration
const API_BASE_URL = '../api';
const REFRESH_INTERVAL = 30000; // 30 seconds

// Chart instances
let doctorsSpecializationChart = null;
let doctorsDepartmentChart = null;
let patientsGenderChart = null;
let patientsAgeChart = null;

// Data storage
let doctorsData = [];
let patientsData = [];

// Initialize reports on page load
document.addEventListener('DOMContentLoaded', function() {
    console.log('MediTrack Reports System Initializing...');
    
    // Load initial data
    loadAllData();
    
    // Set up auto-refresh
    setInterval(loadAllData, REFRESH_INTERVAL);
    
    // Update last updated time
    updateLastUpdatedTime();
    setInterval(updateLastUpdatedTime, 1000);
    
    console.log('Reports system ready!');
});

// Load all data
async function loadAllData() {
    try {
        await Promise.all([
            loadSummaryStats(),
            loadDoctorsData(),
            loadPatientsData()
        ]);
        
        console.log('All data loaded successfully');
    } catch (error) {
        console.error('Error loading data:', error);
        showError('Failed to load reports data');
    }
}

// Load summary statistics
async function loadSummaryStats() {
    try {
        const response = await fetch(`${API_BASE_URL}/admin/stats.php`);
        const data = await response.json();
        
        if (data.success) {
            document.getElementById('totalPatients').textContent = data.stats.total_patients || 0;
            document.getElementById('totalDoctors').textContent = data.stats.total_doctors || 0;
            document.getElementById('totalAppointments').textContent = data.stats.total_appointments || 0;
            document.getElementById('activeRecords').textContent = 
                (data.stats.total_patients || 0) + (data.stats.total_doctors || 0);
        }
    } catch (error) {
        console.error('Error loading summary stats:', error);
    }
}

// Load doctors data
async function loadDoctorsData() {
    try {
        const response = await fetch(`${API_BASE_URL}/admin/get-all-doctors.php`);
        const data = await response.json();
        
        if (data.success) {
            doctorsData = data.doctors || [];
            updateDoctorsTable();
            updateDoctorsCharts();
        }
    } catch (error) {
        console.error('Error loading doctors data:', error);
        document.getElementById('doctorsTableBody').innerHTML = 
            '<tr><td colspan="5" class="px-6 py-4 text-center text-red-500">Error loading doctors data</td></tr>';
    }
}

// Load patients data
async function loadPatientsData() {
    try {
        const response = await fetch(`${API_BASE_URL}/admin/get-all-patients.php`);
        const data = await response.json();
        
        if (data.success) {
            patientsData = data.patients || [];
            updatePatientsTable();
            updatePatientsCharts();
        }
    } catch (error) {
        console.error('Error loading patients data:', error);
        document.getElementById('patientsTableBody').innerHTML = 
            '<tr><td colspan="5" class="px-6 py-4 text-center text-red-500">Error loading patients data</td></tr>';
    }
}

// Update doctors table
function updateDoctorsTable() {
    const tbody = document.getElementById('doctorsTableBody');
    
    if (doctorsData.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="px-6 py-4 text-center text-gray-500">No doctors found</td></tr>';
        return;
    }
    
    tbody.innerHTML = doctorsData.map(doctor => `
        <tr class="hover:bg-gray-50">
            <td class="px-6 py-4 whitespace-nowrap">
                <div class="flex items-center">
                    <div class="flex-shrink-0 h-10 w-10">
                        <div class="h-10 w-10 rounded-full bg-green-100 flex items-center justify-center">
                            <i class="fas fa-user-md text-green-600"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <div class="text-sm font-medium text-gray-900">${doctor.full_name || 'N/A'}</div>
                        <div class="text-sm text-gray-500">${doctor.email || 'N/A'}</div>
                    </div>
                </div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${doctor.specialization || 'N/A'}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${doctor.department_name || 'N/A'}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${doctor.license_number || 'N/A'}</td>
            <td class="px-6 py-4 whitespace-nowrap">
                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                    Active
                </span>
            </td>
        </tr>
    `).join('');
}

// Update patients table
function updatePatientsTable() {
    const tbody = document.getElementById('patientsTableBody');
    
    if (patientsData.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="px-6 py-4 text-center text-gray-500">No patients found</td></tr>';
        return;
    }
    
    tbody.innerHTML = patientsData.map(patient => {
        const age = patient.age || calculateAge(patient.date_of_birth) || 'N/A';
        return `
            <tr class="hover:bg-gray-50">
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 h-10 w-10">
                            <div class="h-10 w-10 rounded-full bg-blue-100 flex items-center justify-center">
                                <i class="fas fa-user text-blue-600"></i>
                            </div>
                        </div>
                        <div class="ml-4">
                            <div class="text-sm font-medium text-gray-900">${patient.full_name || 'N/A'}</div>
                            <div class="text-sm text-gray-500">${patient.email || 'N/A'}</div>
                        </div>
                    </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${age}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${patient.gender || 'N/A'}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${patient.blood_group || 'N/A'}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${patient.city || 'N/A'}</td>
            </tr>
        `;
    }).join('');
}

// Update doctors charts
function updateDoctorsCharts() {
    updateDoctorsSpecializationChart();
    updateDoctorsDepartmentChart();
}

// Update patients charts
function updatePatientsCharts() {
    updatePatientsGenderChart();
    updatePatientsAgeChart();
}

// Doctors specialization chart
function updateDoctorsSpecializationChart() {
    const ctx = document.getElementById('doctorsSpecializationChart').getContext('2d');
    
    // Count specializations
    const specializationCounts = {};
    doctorsData.forEach(doctor => {
        const spec = doctor.specialization || 'Unknown';
        specializationCounts[spec] = (specializationCounts[spec] || 0) + 1;
    });
    
    const labels = Object.keys(specializationCounts);
    const data = Object.values(specializationCounts);
    
    if (doctorsSpecializationChart) {
        doctorsSpecializationChart.destroy();
    }
    
    doctorsSpecializationChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{
                data: data,
                backgroundColor: [
                    '#10b981', '#3b82f6', '#f59e0b', '#ef4444', '#8b5cf6',
                    '#06b6d4', '#84cc16', '#f97316', '#ec4899', '#6b7280'
                ]
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
}

// Doctors department chart
function updateDoctorsDepartmentChart() {
    const ctx = document.getElementById('doctorsDepartmentChart').getContext('2d');
    
    // Count departments
    const departmentCounts = {};
    doctorsData.forEach(doctor => {
        const dept = doctor.department_name || 'Unknown';
        departmentCounts[dept] = (departmentCounts[dept] || 0) + 1;
    });
    
    const labels = Object.keys(departmentCounts);
    const data = Object.values(departmentCounts);
    
    if (doctorsDepartmentChart) {
        doctorsDepartmentChart.destroy();
    }
    
    doctorsDepartmentChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Number of Doctors',
                data: data,
                backgroundColor: '#10b981',
                borderColor: '#059669',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            }
        }
    });
}

// Patients gender chart
function updatePatientsGenderChart() {
    const ctx = document.getElementById('patientsGenderChart').getContext('2d');
    
    // Count genders
    const genderCounts = { male: 0, female: 0, other: 0 };
    patientsData.forEach(patient => {
        const gender = (patient.gender || 'other').toLowerCase();
        if (genderCounts.hasOwnProperty(gender)) {
            genderCounts[gender]++;
        } else {
            genderCounts.other++;
        }
    });
    
    if (patientsGenderChart) {
        patientsGenderChart.destroy();
    }
    
    patientsGenderChart = new Chart(ctx, {
        type: 'pie',
        data: {
            labels: ['Male', 'Female', 'Other'],
            datasets: [{
                data: [genderCounts.male, genderCounts.female, genderCounts.other],
                backgroundColor: ['#3b82f6', '#ec4899', '#6b7280']
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
}

// Patients age chart
function updatePatientsAgeChart() {
    const ctx = document.getElementById('patientsAgeChart').getContext('2d');
    
    // Count age groups
    const ageGroups = {
        '0-18': 0,
        '19-30': 0,
        '31-50': 0,
        '51-70': 0,
        '70+': 0
    };
    
    patientsData.forEach(patient => {
        const age = patient.age || calculateAge(patient.date_of_birth);
        if (age !== null) {
            if (age <= 18) ageGroups['0-18']++;
            else if (age <= 30) ageGroups['19-30']++;
            else if (age <= 50) ageGroups['31-50']++;
            else if (age <= 70) ageGroups['51-70']++;
            else ageGroups['70+']++;
        }
    });
    
    if (patientsAgeChart) {
        patientsAgeChart.destroy();
    }
    
    patientsAgeChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: Object.keys(ageGroups),
            datasets: [{
                label: 'Number of Patients',
                data: Object.values(ageGroups),
                backgroundColor: '#3b82f6',
                borderColor: '#2563eb',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            }
        }
    });
}

// Tab switching
function switchTab(tabName) {
    // Update tab buttons
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('active', 'border-green-500', 'text-green-600');
        btn.classList.add('border-transparent', 'text-gray-500');
    });
    
    // Update tab content
    document.querySelectorAll('.tab-content').forEach(content => {
        content.classList.add('hidden');
    });
    
    // Activate selected tab
    const activeBtn = document.getElementById(tabName + 'TabBtn');
    const activeContent = document.getElementById(tabName + 'Tab');
    
    if (activeBtn && activeContent) {
        activeBtn.classList.add('active', 'border-green-500', 'text-green-600');
        activeBtn.classList.remove('border-transparent', 'text-gray-500');
        activeContent.classList.remove('hidden');
    }
}

// Refresh all data
async function refreshAllData() {
    const refreshBtn = document.querySelector('button[onclick="refreshAllData()"]');
    const originalText = refreshBtn.innerHTML;
    
    refreshBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Refreshing...';
    refreshBtn.disabled = true;
    
    try {
        await loadAllData();
        
        Swal.fire({
            icon: 'success',
            title: 'Data Refreshed!',
            text: 'All reports have been updated',
            timer: 2000,
            showConfirmButton: false,
            toast: true,
            position: 'top-end'
        });
    } catch (error) {
        showError('Failed to refresh data');
    } finally {
        refreshBtn.innerHTML = originalText;
        refreshBtn.disabled = false;
    }
}

// Export functions
function exportDoctorsReport(format) {
    if (format === 'pdf') {
        exportToPDF('doctors');
    } else if (format === 'excel') {
        exportToExcel('doctors');
    }
}

function exportPatientsReport(format) {
    if (format === 'pdf') {
        exportToPDF('patients');
    } else if (format === 'excel') {
        exportToExcel('patients');
    }
}

function exportToPDF(type) {
    Swal.fire({
        icon: 'info',
        title: 'PDF Export',
        text: `Generating ${type} PDF report...`,
        timer: 2000,
        showConfirmButton: false
    });
    
    // Here you would implement actual PDF generation
    // For now, we'll simulate it
    setTimeout(() => {
        const link = document.createElement('a');
        link.href = `${API_BASE_URL}/admin/export-${type}-pdf.php`;
        link.download = `${type}-report-${new Date().toISOString().split('T')[0]}.pdf`;
        link.click();
    }, 2000);
}

function exportToExcel(type) {
    Swal.fire({
        icon: 'info',
        title: 'Excel Export',
        text: `Generating ${type} Excel report...`,
        timer: 2000,
        showConfirmButton: false
    });
    
    // Here you would implement actual Excel generation
    // For now, we'll simulate it
    setTimeout(() => {
        const link = document.createElement('a');
        link.href = `${API_BASE_URL}/admin/export-${type}-excel.php`;
        link.download = `${type}-report-${new Date().toISOString().split('T')[0]}.xlsx`;
        link.click();
    }, 2000);
}

// Utility functions
function calculateAge(dateOfBirth) {
    if (!dateOfBirth) return null;
    
    const today = new Date();
    const birthDate = new Date(dateOfBirth);
    let age = today.getFullYear() - birthDate.getFullYear();
    const monthDiff = today.getMonth() - birthDate.getMonth();
    
    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
        age--;
    }
    
    return age;
}

function updateLastUpdatedTime() {
    const now = new Date();
    const timeString = now.toLocaleTimeString();
    document.getElementById('lastUpdated').textContent = timeString;
}

function showError(message) {
    Swal.fire({
        icon: 'error',
        title: 'Error',
        text: message,
        confirmButtonColor: '#ef4444'
    });
}

// Initialize on load
console.log('MediTrack Reports System Loaded');
