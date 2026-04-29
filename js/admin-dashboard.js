/**
 * admin-dashboard.js
 * Consultation OPD Management System
 * Complete admin dashboard logic
 */

'use strict';

/* ══════════════════════════════════════════════
   AUTH GUARD
══════════════════════════════════════════════ */
const _user = (() => {
    const u = JSON.parse(sessionStorage.getItem('user') || 'null');
    if (!u) { window.location.href = '/meditrack/pages/login.html'; return null; }
    if (u.role !== 'admin') { window.location.href = '/meditrack/pages/login.html'; return null; }
    return u;
})();

/* ══════════════════════════════════════════════
   GLOBAL STATE
══════════════════════════════════════════════ */
let currentTab = 'overview';

// Appointments tab state
let apptPage       = 1;
let apptTotalPages = 1;
let apptFilters    = { date: '', status: '' };

// Activity logs state
let actPage       = 1;
let actTotalPages = 1;
let actFilters    = { action_type: '', module: '' };

// Patients (client-side data store for filtering)
let allPatients = [];

// Reports period
let reportPeriod = 'week';

/* ══════════════════════════════════════════════
   INIT
══════════════════════════════════════════════ */
document.addEventListener('DOMContentLoaded', () => {
    if (!_user) return;

    // Populate admin name in header + sidebar
    const name = _user.name || _user.username || 'Admin';
    const headerName   = document.getElementById('headerAdminName');
    const sidebarName  = document.getElementById('sidebarAdminName');
    if (headerName)  headerName.textContent  = name;
    if (sidebarName) sidebarName.textContent = name;

    // Default tab
    switchTab('overview');
});

/* ══════════════════════════════════════════════
   NAVIGATION
══════════════════════════════════════════════ */
function switchTab(tab) {
    currentTab = tab;

    // Hide all panels
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
    const panel = document.getElementById('tab-' + tab);
    if (panel) panel.classList.add('active');

    // Update sidebar nav items
    document.querySelectorAll('.sidebar [data-tab]').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.tab === tab);
    });

    // Update mobile bottom nav
    document.querySelectorAll('.mobile-bottom-nav .nav-btn').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.tab === tab);
    });
    // Also update "More" dropdown active state
    document.querySelectorAll('#moreDropdown button[data-tab]').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.tab === tab);
    });

    // Update header title
    const titles = {
        overview:     'Admin Dashboard',
        appointments: 'Appointments',
        patients:     'Patients',
        doctors:      'Manage Doctors',
        schedule:     'OPD Schedule',
        reports:      'Reports',
        activity:     'Activity Logs',
    };
    const el = document.getElementById('headerTitle');
    if (el) el.textContent = titles[tab] || 'Admin Dashboard';

    // Close mobile sidebar
    closeSidebar();

    // Load data for the tab
    switch (tab) {
        case 'overview':     loadOverview();     break;
        case 'appointments': loadAppointments(); break;
        case 'patients':     loadPatients();     break;
        case 'doctors':      loadDoctors();      break;
        case 'schedule':     loadSchedule();     break;
        case 'reports':      loadReports();      break;
        case 'activity':     loadActivityLogs(); break;
    }
}

/* ══════════════════════════════════════════════
   SIDEBAR (MOBILE)
══════════════════════════════════════════════ */
function openSidebar() {
    const sidebar  = document.getElementById('sidebar');
    const overlay  = document.getElementById('sidebarOverlay');
    if (sidebar)  sidebar.classList.add('open');
    if (overlay)  overlay.classList.remove('hidden');
}

function closeSidebar() {
    const sidebar  = document.getElementById('sidebar');
    const overlay  = document.getElementById('sidebarOverlay');
    if (sidebar)  sidebar.classList.remove('open');
    if (overlay)  overlay.classList.add('hidden');
}

/* ══════════════════════════════════════════════
   MORE MENU (MOBILE)
══════════════════════════════════════════════ */
function toggleMoreMenu() {
    const dd = document.getElementById('moreDropdown');
    if (dd) dd.classList.toggle('open');
}

function closeMoreMenu() {
    const dd = document.getElementById('moreDropdown');
    if (dd) dd.classList.remove('open');
}

// Close more menu when tapping outside
document.addEventListener('click', e => {
    const dd     = document.getElementById('moreDropdown');
    const moreBtn = document.getElementById('moreBtn');
    if (dd && dd.classList.contains('open') && !dd.contains(e.target) && e.target !== moreBtn && !moreBtn?.contains(e.target)) {
        dd.classList.remove('open');
    }
});

/* ══════════════════════════════════════════════
   OVERVIEW TAB
══════════════════════════════════════════════ */
async function loadOverview() {
    const grid = document.getElementById('statsGrid');
    if (!grid) return;

    grid.innerHTML = `<div class="col-span-2 md:col-span-3 flex items-center justify-center py-12 text-gray-400">
        <i class="fas fa-spinner fa-spin text-2xl mr-3"></i><span>Loading statistics…</span></div>`;

    try {
        const data = await apiRequest('/admin/stats.php');
        if (!data || !data.success) throw new Error(data?.message || 'Failed to load stats');

        const s = data.stats;

        const cards = [
            {
                id: 'stat-patients', label: 'Total Patients', value: s.total_patients ?? 0,
                icon: 'fas fa-users', color: 'blue', iconBg: 'bg-blue-100', iconColor: 'text-blue-600',
                border: 'border-blue-500', sub: 'Registered patients'
            },
            {
                id: 'stat-today', label: "Today's Appointments", value: s.today_appointments ?? 0,
                icon: 'fas fa-calendar-day', color: 'teal', iconBg: 'bg-teal-100', iconColor: 'text-teal-600',
                border: 'border-teal-500', sub: formatDate(new Date())
            },
            {
                id: 'stat-week', label: 'This Week', value: s.week_appointments ?? 0,
                icon: 'fas fa-calendar-week', color: 'purple', iconBg: 'bg-purple-100', iconColor: 'text-purple-600',
                border: 'border-purple-500', sub: 'Current week'
            },
            {
                id: 'stat-month', label: 'This Month', value: s.month_appointments ?? 0,
                icon: 'fas fa-calendar-alt', color: 'yellow', iconBg: 'bg-yellow-100', iconColor: 'text-yellow-600',
                border: 'border-yellow-500', sub: new Date().toLocaleString('default', { month: 'long', year: 'numeric' })
            },
            {
                id: 'stat-completed', label: 'Completed', value: s.total_completed ?? 0,
                icon: 'fas fa-check-circle', color: 'green', iconBg: 'bg-green-100', iconColor: 'text-green-600',
                border: 'border-green-500', sub: 'All time'
            },
            {
                id: 'stat-cancelled', label: 'Cancelled', value: s.total_cancelled ?? 0,
                icon: 'fas fa-times-circle', color: 'red', iconBg: 'bg-red-100', iconColor: 'text-red-500',
                border: 'border-red-400', sub: 'All time'
            },
        ];

        grid.innerHTML = cards.map(c => `
            <div class="stat-card bg-white rounded-xl shadow-sm p-5 border-l-4 ${c.border}">
                <div class="flex items-center justify-between">
                    <div class="min-w-0">
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">${c.label}</p>
                        <p class="counter text-3xl font-bold text-gray-900">${c.value.toLocaleString()}</p>
                        <p class="text-xs text-gray-400 mt-1">${c.sub}</p>
                    </div>
                    <div class="${c.iconBg} rounded-full p-3 flex-shrink-0 ml-3">
                        <i class="${c.icon} ${c.iconColor} text-xl"></i>
                    </div>
                </div>
            </div>`).join('');

        // Check for active doctors
        checkDoctorAvailability();

    } catch (err) {
        grid.innerHTML = `<div class="col-span-2 md:col-span-3 text-center py-10 text-red-500">
            <i class="fas fa-exclamation-circle mr-2"></i>${escHtml(err.message)}</div>`;
    }
}

async function checkDoctorAvailability() {
    const alertBox = document.getElementById('adminDoctorAlert');
    if (!alertBox) return;

    try {
        const data = await apiRequest('/admin/get-doctors.php');
        if (!data || !data.success) return;

        const doctors = data.doctors || [];
        const activeDoctors = doctors.filter(d => d.status === 'active');
        const noDoctors = doctors.length === 0;
        const noActive = activeDoctors.length === 0 && doctors.length > 0;
        const noSchedule = activeDoctors.length > 0 && activeDoctors.every(d => parseInt(d.active_schedule_days) === 0);

        if (noDoctors) {
            alertBox.innerHTML = `
                <div class="rounded-xl p-4 mb-5 border border-red-200" style="background:linear-gradient(135deg,#FEF2F2,#FFF1F2);">
                    <div class="flex items-start gap-3">
                        <div class="w-10 h-10 rounded-xl bg-red-100 flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-exclamation-triangle text-red-500"></i>
                        </div>
                        <div class="flex-1">
                            <p class="font-bold text-red-700 text-sm" style="font-family:'Outfit',sans-serif;">No Doctors in System</p>
                            <p class="text-xs text-red-500 mt-0.5">Patients cannot book appointments. Add a doctor to get started.</p>
                            <button onclick="switchTab('doctors')" class="mt-2 inline-flex items-center gap-1.5 px-4 py-1.5 bg-red-600 hover:bg-red-700 text-white text-xs font-semibold rounded-lg transition">
                                <i class="fas fa-plus"></i> Add Doctor Now
                            </button>
                        </div>
                    </div>
                </div>`;
            alertBox.classList.remove('hidden');
        } else if (noActive) {
            alertBox.innerHTML = `
                <div class="rounded-xl p-4 mb-5 border border-amber-200" style="background:linear-gradient(135deg,#FFFBEB,#FEF3C7);">
                    <div class="flex items-start gap-3">
                        <div class="w-10 h-10 rounded-xl bg-amber-100 flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-user-doctor text-amber-600"></i>
                        </div>
                        <div class="flex-1">
                            <p class="font-bold text-amber-700 text-sm" style="font-family:'Outfit',sans-serif;">All Doctors Inactive</p>
                            <p class="text-xs text-amber-600 mt-0.5">All ${doctors.length} doctor(s) are deactivated. Patients cannot book appointments.</p>
                            <button onclick="switchTab('doctors')" class="mt-2 inline-flex items-center gap-1.5 px-4 py-1.5 bg-amber-600 hover:bg-amber-700 text-white text-xs font-semibold rounded-lg transition">
                                <i class="fas fa-user-md"></i> Manage Doctors
                            </button>
                        </div>
                    </div>
                </div>`;
            alertBox.classList.remove('hidden');
        } else if (noSchedule) {
            alertBox.innerHTML = `
                <div class="rounded-xl p-4 mb-5 border border-amber-200" style="background:linear-gradient(135deg,#FFFBEB,#FEF3C7);">
                    <div class="flex items-start gap-3">
                        <div class="w-10 h-10 rounded-xl bg-amber-100 flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-calendar-xmark text-amber-600"></i>
                        </div>
                        <div class="flex-1">
                            <p class="font-bold text-amber-700 text-sm" style="font-family:'Outfit',sans-serif;">No Active Schedule</p>
                            <p class="text-xs text-amber-600 mt-0.5">Doctor(s) exist but have no active schedule days. Patients won't see any available time slots.</p>
                            <button onclick="switchTab('schedule')" class="mt-2 inline-flex items-center gap-1.5 px-4 py-1.5 bg-amber-600 hover:bg-amber-700 text-white text-xs font-semibold rounded-lg transition">
                                <i class="fas fa-clock"></i> Set Schedule
                            </button>
                        </div>
                    </div>
                </div>`;
            alertBox.classList.remove('hidden');
        } else {
            alertBox.innerHTML = '';
            alertBox.classList.add('hidden');
        }
    } catch (err) {
        console.error('Doctor check error:', err);
    }
}

/* ══════════════════════════════════════════════
   APPOINTMENTS TAB
══════════════════════════════════════════════ */
async function loadAppointments() {
    const tbody = document.getElementById('apptTableBody');
    if (!tbody) return;

    tbody.innerHTML = `<tr><td colspan="7" class="text-center py-10 text-gray-400">
        <i class="fas fa-spinner fa-spin mr-2"></i>Loading…</td></tr>`;

    const params = new URLSearchParams();
    if (apptFilters.date)   params.set('date',   apptFilters.date);
    if (apptFilters.status) params.set('status', apptFilters.status);
    params.set('page', apptPage);

    try {
        const data = await apiRequest('/admin/appointments.php?' + params.toString());
        if (!data || !data.success) throw new Error(data?.message || 'Failed to load appointments');

        const appts = data.appointments || [];

        // Use server-side pagination data from API response
        const pagination = data.pagination || {};
        apptTotalPages = pagination.total_pages || Math.max(1, Math.ceil((pagination.total || appts.length) / 20));
        const pageSize = pagination.per_page || 20;
        const start    = (apptPage - 1) * pageSize;

        updateApptPagination();

        if (!appts.length) {
            tbody.innerHTML = `<tr><td colspan="7" class="text-center py-10 text-gray-400">
                <i class="fas fa-calendar-times mr-2"></i>No appointments found</td></tr>`;
            return;
        }

        tbody.innerHTML = appts.map((a, i) => {
            const rowNum   = start + i + 1;
            const apptNo   = escHtml(a.appointment_number || `#${a.id}`);
            const patient  = escHtml(a.patient_name || '—');
            const date     = formatDateStr(a.appointment_date);
            const time     = formatTime(a.appointment_time);
            const badge    = statusBadge(a.status);
            const canCancel = ['scheduled', 'confirmed', 'checked_in'].includes(a.status);

            return `<tr class="hover:bg-gray-50 transition">
                <td class="px-4 py-3 text-gray-500">${rowNum}</td>
                <td class="px-4 py-3 font-mono text-xs font-semibold text-teal-700">${apptNo}</td>
                <td class="px-4 py-3 font-medium text-gray-800">${patient}</td>
                <td class="px-4 py-3 text-gray-600">${date}</td>
                <td class="px-4 py-3 text-gray-600">${time}</td>
                <td class="px-4 py-3">${badge}</td>
                <td class="px-4 py-3">
                    ${canCancel
                        ? `<button onclick="cancelAppointment(${a.id}, '${apptNo}')"
                                   class="bg-red-50 hover:bg-red-100 text-red-600 border border-red-200 px-3 py-1 rounded-lg text-xs font-semibold transition">
                               <i class="fas fa-ban mr-1"></i>Cancel
                           </button>`
                        : `<span class="text-gray-300 text-xs">—</span>`}
                </td>
            </tr>`;
        }).join('');

    } catch (err) {
        tbody.innerHTML = `<tr><td colspan="7" class="text-center py-8 text-red-500">
            <i class="fas fa-exclamation-circle mr-2"></i>${escHtml(err.message)}</td></tr>`;
    }
}

function applyAppointmentFilters() {
    apptFilters.date   = document.getElementById('apptFilterDate')?.value   || '';
    apptFilters.status = document.getElementById('apptFilterStatus')?.value || '';
    apptPage = 1;
    loadAppointments();
}

function clearAppointmentFilters() {
    apptFilters = { date: '', status: '' };
    const dateEl   = document.getElementById('apptFilterDate');
    const statusEl = document.getElementById('apptFilterStatus');
    if (dateEl)   dateEl.value   = '';
    if (statusEl) statusEl.value = '';
    apptPage = 1;
    loadAppointments();
}

function updateApptPagination() {
    const info    = document.getElementById('apptPageInfo');
    const prevBtn = document.getElementById('apptPrevBtn');
    const nextBtn = document.getElementById('apptNextBtn');
    if (info)    info.textContent = `Page ${apptPage} of ${apptTotalPages}`;
    if (prevBtn) prevBtn.disabled = apptPage <= 1;
    if (nextBtn) nextBtn.disabled = apptPage >= apptTotalPages;
}

function appointmentPrevPage() {
    if (apptPage > 1) { apptPage--; loadAppointments(); }
}
function appointmentNextPage() {
    if (apptPage < apptTotalPages) { apptPage++; loadAppointments(); }
}

async function cancelAppointment(id, apptNo) {
    const result = await Swal.fire({
        title: 'Cancel Appointment?',
        html: `Are you sure you want to cancel appointment <strong>${apptNo}</strong>?<br><span class="text-sm text-gray-500">This action cannot be undone.</span>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, Cancel It',
        cancelButtonText: 'Keep Appointment',
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6b7280',
        reverseButtons: true,
    });

    if (!result.isConfirmed) return;

    try {
        const data = await apiRequest('/admin/cancel-appointment.php', {
            method: 'POST',
            body: JSON.stringify({ appointment_id: id }),
        });

        if (!data || !data.success) throw new Error(data?.message || 'Failed to cancel appointment');

        showToast('success', 'Cancelled', 'The appointment has been cancelled.');
        loadAppointments();

    } catch (err) {
        showError(err.message);
    }
}

/* ══════════════════════════════════════════════
   PATIENTS TAB
══════════════════════════════════════════════ */
async function loadPatients() {
    const tbody = document.getElementById('patientsTableBody');
    if (!tbody) return;

    tbody.innerHTML = `<tr><td colspan="7" class="text-center py-10 text-gray-400">
        <i class="fas fa-spinner fa-spin mr-2"></i>Loading…</td></tr>`;

    try {
        const data = await apiRequest('/admin/get-all-patients.php');
        if (!data || !data.success) throw new Error(data?.message || 'Failed to load patients');

        allPatients = data.patients || [];
        renderPatientsTable(allPatients);

    } catch (err) {
        tbody.innerHTML = `<tr><td colspan="7" class="text-center py-8 text-red-500">
            <i class="fas fa-exclamation-circle mr-2"></i>${escHtml(err.message)}</td></tr>`;
    }
}

function renderPatientsTable(patients) {
    const tbody = document.getElementById('patientsTableBody');
    if (!tbody) return;

    if (!patients.length) {
        tbody.innerHTML = `<tr><td colspan="7" class="text-center py-10 text-gray-400">
            <i class="fas fa-user-slash mr-2"></i>No patients found</td></tr>`;
        return;
    }

    tbody.innerHTML = patients.map((p, i) => {
        const status  = p.status || 'active';
        const isActive = status === 'active';
        const badgeCls = isActive
            ? 'bg-green-100 text-green-700'
            : 'bg-red-100 text-red-600';
        const toggleLabel = isActive ? 'Deactivate' : 'Activate';
        const toggleCls   = isActive
            ? 'bg-red-50 hover:bg-red-100 text-red-600 border border-red-200'
            : 'bg-green-50 hover:bg-green-100 text-green-700 border border-green-200';
        const newStatus   = isActive ? 'inactive' : 'active';

        return `<tr class="hover:bg-gray-50 transition">
            <td class="px-4 py-3 text-gray-500">${i + 1}</td>
            <td class="px-4 py-3">
                <span class="font-semibold text-gray-800">${escHtml(p.full_name || '—')}</span>
                ${p.gender ? `<span class="ml-1 text-xs text-gray-400">(${escHtml(p.gender)})</span>` : ''}
            </td>
            <td class="px-4 py-3 text-gray-600">${escHtml(p.email || '—')}</td>
            <td class="px-4 py-3 text-gray-600">${escHtml(p.contact_number || '—')}</td>
            <td class="px-4 py-3 text-center">
                <span class="inline-block bg-gray-100 text-gray-700 rounded px-2 py-0.5 text-xs font-semibold">
                    ${escHtml(p.blood_group || '—')}
                </span>
            </td>
            <td class="px-4 py-3">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold ${badgeCls}">
                    <span class="w-1.5 h-1.5 rounded-full mr-1 ${isActive ? 'bg-green-500' : 'bg-red-500'}"></span>
                    ${status.charAt(0).toUpperCase() + status.slice(1)}
                </span>
            </td>
            <td class="px-4 py-3">
                <button onclick="togglePatientStatus(${p.patient_id}, '${escHtml(p.full_name || '')}', '${newStatus}')"
                        class="${toggleCls} px-3 py-1 rounded-lg text-xs font-semibold transition">
                    ${toggleLabel}
                </button>
            </td>
        </tr>`;
    }).join('');
}

function filterPatientsTable() {
    const q = (document.getElementById('patientSearch')?.value || '').toLowerCase().trim();
    if (!q) {
        renderPatientsTable(allPatients);
        return;
    }
    const filtered = allPatients.filter(p =>
        (p.full_name || '').toLowerCase().includes(q) ||
        (p.email     || '').toLowerCase().includes(q)
    );
    renderPatientsTable(filtered);
}

async function togglePatientStatus(id, name, newStatus) {
    const action = newStatus === 'inactive' ? 'Deactivate' : 'Activate';
    const iconType = newStatus === 'inactive' ? 'warning' : 'question';

    const result = await Swal.fire({
        title: `${action} Patient?`,
        html: `<strong>${escHtml(name)}</strong> will be set to <strong>${newStatus}</strong>.`,
        icon: iconType,
        showCancelButton: true,
        confirmButtonText: `Yes, ${action}`,
        cancelButtonText: 'Cancel',
        confirmButtonColor: newStatus === 'inactive' ? '#ef4444' : '#0891B2',
        cancelButtonColor: '#6b7280',
        reverseButtons: true,
    });

    if (!result.isConfirmed) return;

    try {
        const data = await apiRequest('/admin/update-patient-status.php', {
            method: 'POST',
            body: JSON.stringify({ patient_id: id, status: newStatus }),
        });

        if (!data || !data.success) throw new Error(data?.message || 'Update failed');

        showToast('success', 'Updated', `Patient status set to ${newStatus}.`);
        loadPatients();

    } catch (err) {
        showError(err.message);
    }
}

/* ══════════════════════════════════════════════
   OPD SCHEDULE TAB
══════════════════════════════════════════════ */
const DAYS = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

async function loadSchedule() {
    const form = document.getElementById('scheduleForm');
    if (!form) return;

    form.innerHTML = `<div class="p-8 text-center text-gray-400">
        <i class="fas fa-spinner fa-spin text-2xl mr-2"></i> Loading schedule…</div>`;

    // Reset doctor info card
    const nameEl  = document.getElementById('schedDocName');
    const specEl  = document.getElementById('schedDocSpec');
    const emailEl = document.getElementById('schedDocEmail');

    try {
        const data = await apiRequest('/admin/get-doctor-schedule.php');
        if (!data || !data.success) {
            form.innerHTML = `
                <div class="p-8 text-center">
                    <div class="w-16 h-16 rounded-2xl bg-red-50 flex items-center justify-center mx-auto mb-4 border border-red-100">
                        <i class="fas fa-user-doctor text-2xl text-red-400"></i>
                    </div>
                    <p class="font-bold text-gray-700 text-lg" style="font-family:'Outfit',sans-serif;">No Active Doctor Found</p>
                    <p class="text-sm text-gray-400 mt-1 mb-4">Add a doctor first before setting up a schedule.</p>
                    <button onclick="switchTab('doctors')" class="inline-flex items-center gap-2 px-5 py-2.5 text-white text-sm font-semibold rounded-xl transition" style="background:#0891B2;">
                        <i class="fas fa-plus"></i> Add Doctor
                    </button>
                </div>`;
            if (nameEl) nameEl.textContent = 'No Doctor';
            if (specEl) specEl.textContent = '';
            if (emailEl) emailEl.textContent = '';
            return;
        }

        const doc = data.doctor || {};
        if (nameEl)  nameEl.textContent  = doc.full_name || 'Doctor';
        if (specEl)  specEl.textContent  = doc.specialization || '';
        if (emailEl) emailEl.textContent = doc.email || '';

        const schedMap = data.schedule || {};

        // Render one row per day (0=Sun … 6=Sat)
        form.innerHTML = DAYS.map((day, dow) => {
            const row       = schedMap[dow] || {};
            const checked   = row.is_active ? 'checked' : '';
            const start     = row.start_time   ? row.start_time.slice(0, 5)   : '08:00';
            const end       = row.end_time     ? row.end_time.slice(0, 5)     : '17:00';
            const slot      = row.slot_duration || 30;
            const maxPat    = row.max_patients  || 20;
            const disabled  = row.is_active ? '' : 'disabled';

            return `
            <div class="schedule-row p-4 flex flex-wrap items-center gap-3" data-dow="${dow}">
                <!-- Checkbox + Day -->
                <label class="flex items-center space-x-2 cursor-pointer min-w-max">
                    <input type="checkbox" data-field="is_active" ${checked}
                           onchange="toggleScheduleRow(${dow}, this.checked)"
                           class="w-4 h-4 text-teal-600 rounded border-gray-300 focus:ring-teal-500">
                    <span class="font-semibold text-gray-800 w-24">${day}</span>
                </label>

                <!-- Start time -->
                <div class="flex flex-col gap-0.5">
                    <label class="text-xs text-gray-400 font-medium">Start</label>
                    <input type="time" data-field="start_time" value="${start}" ${disabled}
                           class="w-32">
                </div>

                <!-- End time -->
                <div class="flex flex-col gap-0.5">
                    <label class="text-xs text-gray-400 font-medium">End</label>
                    <input type="time" data-field="end_time" value="${end}" ${disabled}
                           class="w-32">
                </div>

                <!-- Slot duration -->
                <div class="flex flex-col gap-0.5">
                    <label class="text-xs text-gray-400 font-medium">Slot (min)</label>
                    <select data-field="slot_duration" ${disabled} class="w-24">
                        ${[15,30,45,60].map(v => `<option value="${v}" ${v===slot?'selected':''}>${v} min</option>`).join('')}
                    </select>
                </div>

                <!-- Max patients -->
                <div class="flex flex-col gap-0.5">
                    <label class="text-xs text-gray-400 font-medium">Max Patients</label>
                    <input type="number" data-field="max_patients" value="${maxPat}" min="1" max="100" ${disabled}
                           class="w-24">
                </div>
            </div>`;
        }).join('');

    } catch (err) {
        form.innerHTML = `<div class="p-8 text-center text-red-500">
            <i class="fas fa-exclamation-circle mr-2"></i>${escHtml(err.message)}</div>`;
        if (nameEl)  nameEl.textContent  = 'Error loading doctor';
        if (specEl)  specEl.textContent  = '';
        if (emailEl) emailEl.textContent = '';
    }
}

function toggleScheduleRow(dow, isChecked) {
    const row    = document.querySelector(`.schedule-row[data-dow="${dow}"]`);
    if (!row) return;
    const inputs = row.querySelectorAll('input[type="time"], input[type="number"], select');
    inputs.forEach(el => { el.disabled = !isChecked; });
}

async function saveSchedule(e) {
    if (e) e.preventDefault();

    const rows = document.querySelectorAll('.schedule-row[data-dow]');
    const schedules = [];

    rows.forEach(row => {
        const dow      = parseInt(row.dataset.dow, 10);
        const checkbox = row.querySelector('input[data-field="is_active"]');
        const isActive = checkbox ? checkbox.checked : false;

        const startEl  = row.querySelector('[data-field="start_time"]');
        const endEl    = row.querySelector('[data-field="end_time"]');
        const slotEl   = row.querySelector('[data-field="slot_duration"]');
        const maxEl    = row.querySelector('[data-field="max_patients"]');

        schedules.push({
            day_of_week:   dow,
            is_active:     isActive,
            start_time:    startEl ? startEl.value + ':00' : '08:00:00',
            end_time:      endEl   ? endEl.value   + ':00' : '17:00:00',
            slot_duration: slotEl  ? parseInt(slotEl.value, 10)   : 30,
            max_patients:  maxEl   ? parseInt(maxEl.value,  10)   : 20,
        });
    });

    try {
        const data = await apiRequest('/admin/update-doctor-schedule.php', {
            method: 'POST',
            body: JSON.stringify({ schedules }),
        });

        if (!data || !data.success) throw new Error(data?.message || 'Failed to save schedule');

        showToast('success', 'Schedule Saved', 'OPD schedule has been updated successfully.');
        loadSchedule();

    } catch (err) {
        showError(err.message);
    }
}

/* ══════════════════════════════════════════════
   REPORTS TAB
══════════════════════════════════════════════ */
async function loadReports() {
    const container = document.getElementById('reportsContent');
    if (!container) return;

    container.innerHTML = `<div class="flex items-center justify-center py-16 text-gray-400">
        <i class="fas fa-spinner fa-spin text-2xl mr-3"></i><span>Loading reports…</span></div>`;

    // Sync period buttons UI
    document.querySelectorAll('.period-btn').forEach(btn => {
        const isActive = btn.dataset.period === reportPeriod;
        btn.className = `period-btn px-4 py-2 rounded-lg text-sm font-semibold transition ${
            isActive
                ? 'bg-teal-600 text-white shadow'
                : 'border border-teal-600 text-teal-600 hover:bg-teal-50'
        }`;
    });

    try {
        const data = await apiRequest(`/admin/reports-data.php?range=${reportPeriod}`);
        if (!data || !data.success) throw new Error(data?.message || 'Failed to load reports');

        const ov    = data.overview           || {};
        const trend = data.appointmentsTrend  || { labels: [], data: [] };
        const status_dist = data.statusDistribution || { labels: [], data: [] };
        const demo  = data.demographics       || { labels: [], data: [] };

        // Completion rate
        const total     = ov.totalAppointments || 0;
        const completed = status_dist.data[status_dist.labels.findIndex(l => l.toLowerCase() === 'completed')] || 0;
        const cancelled = status_dist.data[status_dist.labels.findIndex(l => l.toLowerCase() === 'cancelled')] || 0;
        const compRate  = total > 0 ? Math.round((completed / total) * 100) : 0;
        const cancRate  = total > 0 ? Math.round((cancelled / total) * 100) : 0;

        // Gender data (from doctor performance — we derive from demographics hint if available)
        // For now build from what the API returns. The API doesn't return gender directly,
        // so we show age distribution as the demographics section.
        const ageLabels = demo.labels || [];
        const ageData   = demo.data   || [];
        const ageMax    = Math.max(...ageData, 1);

        // Build status rows for the table
        const statusRows = status_dist.labels.map((label, i) => ({
            label,
            count: status_dist.data[i] || 0,
        }));

        // Trend summary: last 7 days
        const trendRows = trend.labels.map((label, i) => ({
            label,
            count: trend.data[i] || 0,
        }));

        const periodLabel = { today: 'Today', week: 'This Week', month: 'This Month', year: 'This Year' }[reportPeriod] || reportPeriod;

        container.innerHTML = `
        <!-- Summary header cards -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-teal-500">
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Total Appointments</p>
                <p class="text-2xl font-bold text-gray-800 mt-1">${ov.totalAppointments ?? 0}</p>
                <p class="text-xs text-gray-400">${periodLabel}</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-blue-500">
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Total Patients</p>
                <p class="text-2xl font-bold text-gray-800 mt-1">${ov.totalPatients ?? 0}</p>
                <p class="text-xs text-gray-400">Registered</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-green-500">
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Completion Rate</p>
                <p class="text-2xl font-bold text-gray-800 mt-1">${compRate}%</p>
                <p class="text-xs text-gray-400">${periodLabel}</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-red-400">
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Cancellation Rate</p>
                <p class="text-2xl font-bold text-gray-800 mt-1">${cancRate}%</p>
                <p class="text-xs text-gray-400">${periodLabel}</p>
            </div>
        </div>

        <!-- Completion rate progress -->
        <div class="bg-white rounded-xl shadow-sm p-5 mb-6">
            <h4 class="font-semibold text-gray-700 mb-3 font-poppins">Completion Rate — ${periodLabel}</h4>
            <div class="flex items-center space-x-4">
                <div class="flex-1 bg-gray-100 rounded-full h-5 overflow-hidden">
                    <div class="demo-bar h-5 bg-teal-500 rounded-full flex items-center justify-end pr-2"
                         style="width:${compRate}%;">
                        <span class="text-xs text-white font-semibold">${compRate > 10 ? compRate + '%' : ''}</span>
                    </div>
                </div>
                <span class="text-lg font-bold text-teal-700 w-12 text-right">${compRate}%</span>
            </div>
            ${cancelled > 0 ? `
            <div class="flex items-center space-x-4 mt-3">
                <span class="text-xs text-gray-500 w-28 flex-shrink-0">Cancellations:</span>
                <div class="flex-1 bg-gray-100 rounded-full h-3 overflow-hidden">
                    <div class="demo-bar h-3 bg-red-400 rounded-full" style="width:${cancRate}%;"></div>
                </div>
                <span class="text-sm font-semibold text-red-500 w-12 text-right">${cancRate}%</span>
            </div>` : ''}
        </div>

        <!-- Two-column layout: Status table + Trend -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">

            <!-- Appointment Status Breakdown -->
            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                <div class="p-4 border-b border-gray-100 bg-gray-50">
                    <h4 class="font-semibold text-gray-700 font-poppins">Appointment Status — ${periodLabel}</h4>
                </div>
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="text-left px-4 py-2 font-semibold text-gray-600">Status</th>
                            <th class="text-right px-4 py-2 font-semibold text-gray-600">Count</th>
                            <th class="text-right px-4 py-2 font-semibold text-gray-600">%</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        ${statusRows.length ? statusRows.map(r => {
                            const pct = total > 0 ? Math.round((r.count / total) * 100) : 0;
                            return `<tr class="hover:bg-gray-50">
                                <td class="px-4 py-2">${statusBadge(r.label.toLowerCase())}</td>
                                <td class="px-4 py-2 text-right font-semibold text-gray-800">${r.count}</td>
                                <td class="px-4 py-2 text-right text-gray-500">${pct}%</td>
                            </tr>`;
                        }).join('') : `<tr><td colspan="3" class="px-4 py-6 text-center text-gray-400">No data</td></tr>`}
                        ${total > 0 ? `<tr class="bg-gray-50 font-semibold">
                            <td class="px-4 py-2 text-gray-700">Total</td>
                            <td class="px-4 py-2 text-right text-gray-800">${total}</td>
                            <td class="px-4 py-2 text-right text-gray-500">100%</td>
                        </tr>` : ''}
                    </tbody>
                </table>
            </div>

            <!-- 7-Day Trend -->
            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                <div class="p-4 border-b border-gray-100 bg-gray-50">
                    <h4 class="font-semibold text-gray-700 font-poppins">Last 7 Days</h4>
                </div>
                <div class="p-4 space-y-3">
                    ${trendRows.length ? (() => {
                        const tMax = Math.max(...trendRows.map(r => r.count), 1);
                        return trendRows.map(r => {
                            const w = Math.round((r.count / tMax) * 100);
                            return `<div class="flex items-center gap-3">
                                <span class="text-xs text-gray-500 w-8 text-right flex-shrink-0">${escHtml(r.label)}</span>
                                <div class="flex-1 bg-gray-100 rounded-full h-5 overflow-hidden">
                                    <div class="demo-bar h-5 bg-teal-400 rounded-full" style="width:${w}%;"></div>
                                </div>
                                <span class="text-xs font-semibold text-gray-700 w-5 text-right">${r.count}</span>
                            </div>`;
                        }).join('');
                    })() : '<p class="text-center text-gray-400 text-sm py-4">No data</p>'}
                </div>
            </div>
        </div>

        <!-- Demographics: Age Distribution -->
        <div class="bg-white rounded-xl shadow-sm p-5 mb-6">
            <h4 class="font-semibold text-gray-700 mb-4 font-poppins">Age Distribution</h4>
            <div class="space-y-3">
                ${ageLabels.length ? ageLabels.map((label, i) => {
                    const val = ageData[i] || 0;
                    const w   = Math.round((val / ageMax) * 100);
                    const colors = ['bg-blue-400', 'bg-teal-400', 'bg-purple-400', 'bg-yellow-400', 'bg-orange-400'];
                    return `<div class="flex items-center gap-3">
                        <span class="text-xs text-gray-600 w-14 flex-shrink-0">${escHtml(label)}</span>
                        <div class="flex-1 bg-gray-100 rounded-full h-6 overflow-hidden">
                            <div class="demo-bar h-6 ${colors[i % colors.length]} rounded-full flex items-center pl-2"
                                 style="width:${w}%; min-width: ${val > 0 ? '24px' : '0'};">
                                ${w > 10 ? `<span class="text-xs text-white font-semibold">${val}</span>` : ''}
                            </div>
                        </div>
                        <span class="text-xs font-semibold text-gray-700 w-6 text-right">${val}</span>
                    </div>`;
                }).join('') : '<p class="text-gray-400 text-sm">No demographic data available</p>'}
            </div>
        </div>`;

    } catch (err) {
        container.innerHTML = `<div class="text-center py-10 text-red-500">
            <i class="fas fa-exclamation-circle mr-2"></i>${escHtml(err.message)}</div>`;
    }
}

function selectReportPeriod(period) {
    reportPeriod = period;
    loadReports();
}

/* ══════════════════════════════════════════════
   ACTIVITY LOGS TAB
══════════════════════════════════════════════ */
async function loadActivityLogs() {
    const tbody = document.getElementById('activityTableBody');
    if (!tbody) return;

    tbody.innerHTML = `<tr><td colspan="6" class="text-center py-10 text-gray-400">
        <i class="fas fa-spinner fa-spin mr-2"></i>Loading…</td></tr>`;

    const params = new URLSearchParams();
    if (actFilters.action_type && actFilters.action_type !== 'all') params.set('action_type', actFilters.action_type);
    if (actFilters.module       && actFilters.module       !== 'all') params.set('module',      actFilters.module);
    params.set('page', actPage);

    try {
        const data = await apiRequest('/admin/activity-logs.php?' + params.toString());
        if (!data || !data.success) throw new Error(data?.message || 'Failed to load activity logs');

        const logs = data.logs || [];
        actTotalPages = data.total_pages || 1;
        updateActPagination();

        if (!logs.length) {
            tbody.innerHTML = `<tr><td colspan="6" class="text-center py-10 text-gray-400">
                <i class="fas fa-clipboard mr-2"></i>No activity logs found</td></tr>`;
            return;
        }

        tbody.innerHTML = logs.map(log => {
            const ts     = formatDateTime(log.created_at);
            const user   = escHtml(log.username  || '—');
            const role   = escHtml(log.user_role || '—');
            const action = actionBadge(log.action_type);
            const module = `<span class="text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded font-mono">${escHtml(log.module || '—')}</span>`;
            const desc   = escHtml(log.description || '—');

            return `<tr class="hover:bg-gray-50 transition">
                <td class="px-4 py-3 text-xs text-gray-500 whitespace-nowrap">${ts}</td>
                <td class="px-4 py-3 font-medium text-gray-800">${user}</td>
                <td class="px-4 py-3 text-xs text-gray-500 capitalize">${role}</td>
                <td class="px-4 py-3">${action}</td>
                <td class="px-4 py-3">${module}</td>
                <td class="px-4 py-3 text-gray-600 max-w-xs truncate" title="${desc}">${desc}</td>
            </tr>`;
        }).join('');

    } catch (err) {
        tbody.innerHTML = `<tr><td colspan="6" class="text-center py-8 text-red-500">
            <i class="fas fa-exclamation-circle mr-2"></i>${escHtml(err.message)}</td></tr>`;
    }
}

function applyActivityFilters() {
    actFilters.action_type = document.getElementById('logFilterAction')?.value || '';
    actFilters.module      = document.getElementById('logFilterModule')?.value || '';
    actPage = 1;
    loadActivityLogs();
}

function clearActivityFilters() {
    actFilters = { action_type: '', module: '' };
    const actionEl  = document.getElementById('logFilterAction');
    const moduleEl  = document.getElementById('logFilterModule');
    if (actionEl)  actionEl.value  = '';
    if (moduleEl)  moduleEl.value  = '';
    actPage = 1;
    loadActivityLogs();
}

function updateActPagination() {
    const info    = document.getElementById('actPageInfo');
    const prevBtn = document.getElementById('actPrevBtn');
    const nextBtn = document.getElementById('actNextBtn');
    if (info)    info.textContent = `Page ${actPage} of ${actTotalPages}`;
    if (prevBtn) prevBtn.disabled = actPage <= 1;
    if (nextBtn) nextBtn.disabled = actPage >= actTotalPages;
}

function activityPrevPage() {
    if (actPage > 1) { actPage--; loadActivityLogs(); }
}
function activityNextPage() {
    if (actPage < actTotalPages) { actPage++; loadActivityLogs(); }
}

/* ══════════════════════════════════════════════
   HELPERS — BADGES
══════════════════════════════════════════════ */
function statusBadge(status) {
    const map = {
        scheduled:  'bg-blue-100 text-blue-700',
        checked_in: 'bg-yellow-100 text-yellow-700',
        in_progress:'bg-purple-100 text-purple-700',
        completed:  'bg-green-100 text-green-700',
        cancelled:  'bg-red-100 text-red-500',
        no_show:    'bg-gray-100 text-gray-500',
    };
    const cls   = map[status] || 'bg-gray-100 text-gray-500';
    const label = status ? status.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase()) : '—';
    return `<span class="inline-block px-2.5 py-0.5 rounded-full text-xs font-semibold ${cls}">${label}</span>`;
}

function actionBadge(action) {
    const map = {
        LOGIN:   'bg-blue-100 text-blue-700',
        LOGOUT:  'bg-gray-100 text-gray-600',
        CREATE:  'bg-green-100 text-green-700',
        UPDATE:  'bg-yellow-100 text-yellow-700',
        DELETE:  'bg-red-100 text-red-600',
        CHECKIN: 'bg-purple-100 text-purple-700',
        READ:    'bg-teal-100 text-teal-700',
    };
    const cls   = map[action] || 'bg-gray-100 text-gray-600';
    return `<span class="inline-block px-2.5 py-0.5 rounded-full text-xs font-semibold ${cls}">${escHtml(action || '—')}</span>`;
}

/* ══════════════════════════════════════════════
   HELPERS — DATE / TIME
══════════════════════════════════════════════ */
function formatDate(d) {
    return d.toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' });
}

function formatDateStr(str) {
    if (!str) return '—';
    const d = new Date(str + 'T00:00:00');
    return isNaN(d) ? str : formatDate(d);
}

function formatTime(str) {
    if (!str) return '—';
    const [h, m] = str.split(':');
    const hour = parseInt(h, 10);
    const ampm = hour >= 12 ? 'PM' : 'AM';
    const hr12 = hour % 12 || 12;
    return `${hr12}:${m} ${ampm}`;
}

function formatDateTime(str) {
    if (!str) return '—';
    const d = new Date(str);
    if (isNaN(d)) return str;
    return d.toLocaleDateString('en-PH', {
        month: 'short', day: 'numeric', year: 'numeric',
        hour: '2-digit', minute: '2-digit'
    });
}

/* ══════════════════════════════════════════════
   TAB: MANAGE DOCTORS — Full CRUD
══════════════════════════════════════════════ */
let allDoctors = [];

async function loadDoctors() {
    const container = document.getElementById('doctorsContainer');
    if (!container) return;
    container.innerHTML = '<div class="text-center py-16"><i class="fas fa-spinner fa-spin text-2xl" style="color:#0891B2;"></i><p class="text-sm text-slate-400 mt-3">Loading doctors...</p></div>';

    try {
        const data = await apiRequest('/admin/get-doctors.php');
        if (!data || !data.success) throw new Error(data?.message || 'Failed');

        allDoctors = data.doctors || [];
        renderDoctorsList(allDoctors);
    } catch (err) {
        container.innerHTML = '<div class="text-center py-16"><i class="fas fa-exclamation-triangle text-2xl text-red-400"></i><p class="text-sm text-slate-500 mt-3">Failed to load doctors</p></div>';
        console.error(err);
    }
}

function renderDoctorsList(doctors) {
    const container = document.getElementById('doctorsContainer');
    if (!container) return;

    if (!doctors || doctors.length === 0) {
        container.innerHTML = `
            <div class="text-center py-16">
                <div class="w-20 h-20 rounded-3xl mx-auto mb-4 flex items-center justify-center" style="background:linear-gradient(135deg,#ECFEFF,#CFFAFE);">
                    <i class="fas fa-user-md text-3xl" style="color:#0891B2;"></i>
                </div>
                <p class="font-bold text-gray-700 text-lg font-outfit">No Doctors Yet</p>
                <p class="text-sm text-gray-400 mt-1 mb-5">Add your first doctor to get started.</p>
                <button onclick="openDoctorModal()" class="btn-primary px-6 py-2.5 rounded-xl text-sm inline-flex items-center gap-2">
                    <i class="fas fa-plus"></i> Add Doctor
                </button>
            </div>`;
        return;
    }

    container.innerHTML = '<div class="grid grid-cols-1 md:grid-cols-2 gap-4">' + doctors.map(doc => {
        const isActive = doc.status === 'active';
        const statusBadge = isActive
            ? '<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-700"><i class="fas fa-circle text-[0.4rem]"></i> Active</span>'
            : '<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-600"><i class="fas fa-circle text-[0.4rem]"></i> Inactive</span>';

        const photoHtml = doc.profile_picture
            ? `<img src="/meditrack/uploads/${escHtml(doc.profile_picture)}" alt="${escHtml(doc.full_name)}" style="width:100%;height:100%;object-fit:cover;" onerror="this.parentElement.innerHTML='${getInitials(doc.full_name)}';this.parentElement.style.color='#fff';">`
            : getInitials(doc.full_name);

        return `
        <div class="card p-5 hover:shadow-lg transition-shadow" style="border-left:4px solid ${isActive ? '#0891B2' : '#cbd5e1'};">
            <div class="flex items-start justify-between gap-3 mb-3">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="w-12 h-12 rounded-xl flex items-center justify-center flex-shrink-0 text-white font-bold text-sm overflow-hidden" style="background:linear-gradient(135deg,${isActive ? '#0891B2,#0E7490' : '#94A3B8,#64748B'});">
                        ${photoHtml}
                    </div>
                    <div class="min-w-0">
                        <p class="font-bold text-gray-800 truncate font-outfit">${escHtml(doc.full_name)}</p>
                        <p class="text-xs text-cyan-600 font-semibold">${escHtml(doc.specialization || 'Internal Medicine')}</p>
                        <p class="text-xs text-gray-400 mt-0.5">${escHtml(doc.email)}</p>
                    </div>
                </div>
                ${statusBadge}
            </div>

            <div class="grid grid-cols-3 gap-2 mb-3">
                <div class="bg-gray-50 rounded-lg p-2 text-center">
                    <p class="text-xs text-gray-400">Fee</p>
                    <p class="font-bold text-gray-700 text-sm">&#8369;${parseFloat(doc.consultation_fee || 0).toFixed(0)}</p>
                </div>
                <div class="bg-gray-50 rounded-lg p-2 text-center">
                    <p class="text-xs text-gray-400">Appointments</p>
                    <p class="font-bold text-gray-700 text-sm">${doc.total_appointments || 0}</p>
                </div>
                <div class="bg-gray-50 rounded-lg p-2 text-center">
                    <p class="text-xs text-gray-400">Schedule</p>
                    <p class="font-bold text-gray-700 text-sm">${doc.active_schedule_days || 0} days</p>
                </div>
            </div>

            <div class="flex gap-2 pt-3 border-t border-gray-100">
                <button onclick="openDoctorModal(${doc.id})" class="flex-1 flex items-center justify-center gap-1.5 px-3 py-2 bg-cyan-50 hover:bg-cyan-100 text-cyan-700 border border-cyan-200 rounded-lg text-xs font-semibold transition">
                    <i class="fas fa-edit"></i> Edit
                </button>
                <button onclick="toggleDoctorStatus(${doc.id}, '${doc.full_name}', '${doc.status}')" class="flex-1 flex items-center justify-center gap-1.5 px-3 py-2 ${isActive ? 'bg-amber-50 hover:bg-amber-100 text-amber-700 border-amber-200' : 'bg-emerald-50 hover:bg-emerald-100 text-emerald-700 border-emerald-200'} border rounded-lg text-xs font-semibold transition">
                    <i class="fas fa-${isActive ? 'ban' : 'check-circle'}"></i> ${isActive ? 'Deactivate' : 'Activate'}
                </button>
                <button onclick="deleteDoctor(${doc.id}, '${escHtml(doc.full_name)}')" class="flex items-center justify-center gap-1.5 px-3 py-2 bg-red-50 hover:bg-red-100 text-red-600 border border-red-200 rounded-lg text-xs font-semibold transition">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        </div>`;
    }).join('') + '</div>';
}

function getInitials(name) {
    if (!name) return 'DR';
    const parts = name.replace(/^Dr\.?\s*/i, '').trim().split(/\s+/);
    if (parts.length >= 2) return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
    return name.substring(0, 2).toUpperCase();
}

// ─── Doctor Modal ────────────────────────────────────────────────────────────
function openDoctorModal(doctorId = null) {
    const modal = document.getElementById('doctorModal');
    const title = document.getElementById('doctorModalTitle');
    const form  = document.getElementById('doctorForm');
    const pwdInput = document.getElementById('docPassword');
    const pwdReq = document.getElementById('pwdRequired');

    form.reset();
    document.getElementById('docEditId').value = '';
    document.getElementById('docProfilePicture').value = '';
    document.getElementById('docSpecialization').value = 'Internal Medicine';
    document.getElementById('docFee').value = '500';
    resetDocPhotoPreview();

    if (doctorId) {
        // Edit mode
        title.textContent = 'Edit Doctor';
        document.getElementById('docEditId').value = doctorId;
        pwdInput.removeAttribute('required');
        pwdInput.placeholder = 'Leave blank to keep current';
        pwdReq.textContent = '';

        const doc = allDoctors.find(d => d.id == doctorId);
        if (doc) {
            document.getElementById('docFullName').value = doc.full_name || '';
            document.getElementById('docEmail').value = doc.email || '';
            document.getElementById('docSpecialization').value = doc.specialization || 'Internal Medicine';
            document.getElementById('docLicense').value = doc.license_number || '';
            document.getElementById('docFee').value = doc.consultation_fee || '500';
            document.getElementById('docExperience').value = doc.experience_years || '0';
            document.getElementById('docBio').value = doc.bio || '';
            if (doc.profile_picture) {
                document.getElementById('docProfilePicture').value = doc.profile_picture;
                showDocPhotoPreview('/meditrack/uploads/' + doc.profile_picture);
            }
        }
    } else {
        // Add mode
        title.textContent = 'Add New Doctor';
        pwdInput.setAttribute('required', 'required');
        pwdInput.placeholder = 'Min 6 characters';
        pwdReq.textContent = '*';
    }

    modal.classList.remove('hidden');
    modal.classList.add('flex');
    document.body.style.overflow = 'hidden';
}

// ─── Doctor photo helpers ────────────────────────────────────────────────────
function resetDocPhotoPreview() {
    const preview = document.getElementById('docPhotoPreview');
    if (preview) {
        preview.innerHTML = '<i class="fas fa-user-md text-2xl"></i>';
        preview.style.background = '#ECFEFF';
    }
    const fileInput = document.getElementById('docPhotoInput');
    if (fileInput) fileInput.value = '';
}

function showDocPhotoPreview(src) {
    const preview = document.getElementById('docPhotoPreview');
    if (!preview) return;
    preview.innerHTML = `<img src="${src}" alt="Doctor photo" style="width:100%;height:100%;object-fit:cover;">`;
    preview.style.background = '#fff';
}

// Wire up the file input once on DOM ready (preview locally before upload)
document.addEventListener('DOMContentLoaded', () => {
    const fileInput = document.getElementById('docPhotoInput');
    if (!fileInput) return;
    fileInput.addEventListener('change', (e) => {
        const f = e.target.files && e.target.files[0];
        if (!f) return;
        if (f.size > 5 * 1024 * 1024) {
            showError('Photo too large (max 5 MB).');
            fileInput.value = '';
            return;
        }
        const reader = new FileReader();
        reader.onload = ev => showDocPhotoPreview(ev.target.result);
        reader.readAsDataURL(f);
    });
});

async function uploadDoctorPhoto(doctorId) {
    const fileInput = document.getElementById('docPhotoInput');
    const file = fileInput && fileInput.files && fileInput.files[0];
    if (!file) return null;
    const fd = new FormData();
    fd.append('photo', file);
    if (doctorId) fd.append('doctor_id', String(doctorId));
    // apiRequest sets JSON Content-Type — for multipart, fetch directly.
    try {
        const res = await fetch(API_BASE + '/admin/upload-doctor-photo.php', {
            method: 'POST',
            credentials: 'include',
            body: fd
        });
        const data = await res.json().catch(() => null);
        if (data && data.success) return data.filename;
        showError(data?.message || 'Photo upload failed.');
        return null;
    } catch (err) {
        console.error('upload photo error', err);
        showError('Photo upload failed (network error).');
        return null;
    }
}

function closeDoctorModal() {
    const modal = document.getElementById('doctorModal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    document.body.style.overflow = '';
}

async function saveDoctorForm(event) {
    event.preventDefault();
    const editId = document.getElementById('docEditId').value;
    const saveBtn = document.getElementById('docSaveBtn');
    saveBtn.disabled = true;
    saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

    const payload = {
        full_name:        document.getElementById('docFullName').value.trim(),
        email:            document.getElementById('docEmail').value.trim(),
        specialization:   document.getElementById('docSpecialization').value.trim(),
        license_number:   document.getElementById('docLicense').value.trim(),
        consultation_fee: parseFloat(document.getElementById('docFee').value) || 0,
        experience_years: parseInt(document.getElementById('docExperience').value) || 0,
        bio:              document.getElementById('docBio').value.trim(),
        // Existing filename from edit mode (preserved unless a new photo is uploaded below)
        profile_picture:  document.getElementById('docProfilePicture').value || null
    };

    const pwd = document.getElementById('docPassword').value;
    if (pwd) payload.password = pwd;

    let url, successMsg;
    if (editId) {
        payload.doctor_id = parseInt(editId);
        url = '/admin/update-doctor.php';
        successMsg = 'Doctor updated successfully';
    } else {
        if (!pwd || pwd.length < 6) {
            showError('Password is required (min 6 characters).');
            saveBtn.disabled = false;
            saveBtn.innerHTML = '<i class="fas fa-save"></i> <span>Save Doctor</span>';
            return;
        }
        url = '/admin/add-doctor.php';
        successMsg = 'Doctor added successfully';
    }

    try {
        // If editing and a new photo was picked, upload first so we can pass the new filename in the payload.
        if (editId) {
            const newPic = await uploadDoctorPhoto(parseInt(editId));
            if (newPic) payload.profile_picture = newPic;
        }
        // Otherwise (add mode) we upload after creating the doctor so we have a doctor_id to associate.

        const data = await apiRequest(url, {
            method: 'POST',
            body: JSON.stringify(payload)
        });

        if (data && data.success) {
            // Add mode: now that we have a doctor_id, upload the photo if one was selected.
            if (!editId && data.doctor_id) {
                const newPic = await uploadDoctorPhoto(parseInt(data.doctor_id));
                if (newPic) {
                    // Re-call update-doctor so the doctors row carries the filename even if upload-doctor-photo
                    // already wrote it directly to the DB. Ensures consistency on retry/idempotency.
                    await apiRequest('/admin/update-doctor.php', {
                        method: 'POST',
                        body: JSON.stringify({ doctor_id: data.doctor_id, profile_picture: newPic })
                    });
                }
            }
            closeDoctorModal();
            showToast('success', 'Success', successMsg);
            loadDoctors();
            loadOverview();
        } else {
            showError(data?.message || 'Failed to save doctor.');
        }
    } catch (err) {
        showError('An error occurred. Please try again.');
        console.error(err);
    }

    saveBtn.disabled = false;
    saveBtn.innerHTML = '<i class="fas fa-save"></i> <span>Save Doctor</span>';
}

async function toggleDoctorStatus(doctorId, name, currentStatus) {
    const newStatus = currentStatus === 'active' ? 'inactive' : 'active';
    const actionText = newStatus === 'active' ? 'activate' : 'deactivate';

    const result = await Swal.fire({
        icon: 'question',
        title: `${actionText.charAt(0).toUpperCase() + actionText.slice(1)} Doctor?`,
        html: `<p class="text-gray-600">Are you sure you want to ${actionText} <strong>${escHtml(name)}</strong>?</p>`,
        showCancelButton: true,
        confirmButtonText: `Yes, ${actionText}`,
        cancelButtonText: 'Cancel',
        confirmButtonColor: newStatus === 'active' ? '#16a34a' : '#f59e0b',
        cancelButtonColor: '#6b7280',
        reverseButtons: true
    });

    if (!result.isConfirmed) return;

    try {
        const data = await apiRequest('/admin/update-doctor.php', {
            method: 'POST',
            body: JSON.stringify({ doctor_id: doctorId, status: newStatus })
        });

        if (data && data.success) {
            showToast('success', 'Done', `Doctor ${actionText}d successfully.`);
            loadDoctors();
        } else {
            showError(data?.message || 'Failed to update doctor status.');
        }
    } catch (err) {
        showError('An error occurred. Please try again.');
    }
}

async function deleteDoctor(doctorId, name) {
    const result = await Swal.fire({
        icon: 'warning',
        title: 'Delete Doctor?',
        html: `<p class="text-gray-600">Permanently delete <strong>${escHtml(name)}</strong>?</p><p class="text-sm text-red-500 mt-2">This cannot be undone. Doctors with active appointments will be deactivated instead.</p>`,
        showCancelButton: true,
        confirmButtonText: 'Delete Permanently',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#6b7280',
        reverseButtons: true
    });

    if (!result.isConfirmed) return;

    try {
        const data = await apiRequest('/admin/delete-doctor.php', {
            method: 'POST',
            body: JSON.stringify({ doctor_id: doctorId, action: 'delete' })
        });

        if (data && data.success) {
            showToast('success', 'Deleted', data.message || 'Doctor has been removed.');
            loadDoctors();
            loadOverview();
        } else {
            // If can't delete, offer deactivation
            if (data?.message?.includes('active appointment')) {
                const deactivate = await Swal.fire({
                    icon: 'info',
                    title: 'Cannot Delete',
                    text: data.message,
                    showCancelButton: true,
                    confirmButtonText: 'Deactivate Instead',
                    cancelButtonText: 'Cancel',
                    confirmButtonColor: '#f59e0b',
                    reverseButtons: true
                });
                if (deactivate.isConfirmed) {
                    toggleDoctorStatus(doctorId, name, 'active');
                }
            } else {
                showError(data?.message || 'Failed to delete doctor.');
            }
        }
    } catch (err) {
        showError('An error occurred. Please try again.');
    }
}

// Close doctor modal on backdrop click
document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('doctorModal');
    if (modal) {
        modal.addEventListener('click', e => {
            if (e.target === modal) closeDoctorModal();
        });
    }
});

/* ══════════════════════════════════════════════
   HELPERS — MISC
══════════════════════════════════════════════ */
function showToast(icon, title, text = '') {
    Swal.fire({
        icon, title, text,
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 2500,
        timerProgressBar: true,
        showClass: { popup: 'animate__animated animate__slideInRight' },
        hideClass: { popup: 'animate__animated animate__slideOutRight' }
    });
}

function showError(message) {
    Swal.fire({
        icon: 'error',
        title: 'Oops!',
        text: message,
        confirmButtonColor: '#0891B2'
    });
}

function escHtml(str) {
    if (str === null || str === undefined) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}
