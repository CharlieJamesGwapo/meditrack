/**
 * doctor-dashboard.js
 * Consultation OPD Management System
 * Doctor Portal — fully functional dashboard with QR check-in and medical records
 */

'use strict';

// ─────────────────────────────────────────────────────────────────────────────
// State
// ─────────────────────────────────────────────────────────────────────────────
let currentTab         = 'today';
let autoRefreshTimer   = null;
let html5QrCode        = null;
let qrScannerRunning   = false;
let currentAppointment = null;   // appointment object being acted on

// ─────────────────────────────────────────────────────────────────────────────
// Bootstrap
// ─────────────────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    // Auth guard — role must be 'doctor'
    const user = checkAuth();
    if (!user) return;
    if (user.role !== 'doctor') {
        window.location.href = '/meditrack/pages/login.html';
        return;
    }

    // Show today's date in header
    const now = new Date();
    const headerDate = document.getElementById('header-date');
    if (headerDate) {
        headerDate.textContent = now.toLocaleDateString('en-US', {
            weekday: 'long', year: 'numeric', month: 'long', day: 'numeric'
        });
    }

    // Set doctor name placeholders from session while API loads
    const sessionName = user.name || user.username || 'Doctor';
    document.getElementById('header-name').textContent = sessionName;
    document.getElementById('sidebar-name').textContent = sessionName;

    // Initial loads
    loadStats();
    loadProfile(true);   // silent: fills sidebar info without showing profile tab
    loadTodayAppointments();

    // Auto-refresh every 30 s (today tab)
    startAutoRefresh();
});

// ─────────────────────────────────────────────────────────────────────────────
// Auto-refresh
// ─────────────────────────────────────────────────────────────────────────────
function startAutoRefresh() {
    clearInterval(autoRefreshTimer);
    autoRefreshTimer = setInterval(() => {
        if (currentTab === 'today') {
            loadStats();
            loadTodayAppointments(true); // silent refresh
        }
    }, 30000);
}

// ─────────────────────────────────────────────────────────────────────────────
// Tab Navigation
// ─────────────────────────────────────────────────────────────────────────────
function switchTab(tab) {
    currentTab = tab;

    // Hide all tab content
    document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
    document.getElementById(`tab-${tab}`).classList.add('active');

    // Update desktop sidebar nav
    document.querySelectorAll('[id^="nav-"]').forEach(btn => {
        btn.classList.remove('active');
        btn.classList.add('text-teal-100');
        btn.classList.remove('text-white');
    });
    const activeNav = document.getElementById(`nav-${tab}`);
    if (activeNav) {
        activeNav.classList.add('active', 'text-white');
        activeNav.classList.remove('text-teal-100');
    }

    // Update mobile bottom nav
    document.querySelectorAll('.mobile-nav-btn').forEach(btn => {
        btn.classList.remove('active', 'text-teal-600');
        btn.classList.add('text-gray-400');
    });
    const activeMobile = document.getElementById(`mobile-nav-${tab}`);
    if (activeMobile) {
        activeMobile.classList.add('active', 'text-teal-600');
        activeMobile.classList.remove('text-gray-400');
    }

    // Update page title
    const titles = {
        today:   "Today's Appointments",
        all:     'All Appointments',
        profile: 'My Profile'
    };
    document.getElementById('page-title').textContent = titles[tab] || '';

    // Lazy-load tabs
    if (tab === 'all') loadAllAppointments();
    if (tab === 'profile') loadProfile();
}

// ─────────────────────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────────────────────
function formatDate(dateStr) {
    if (!dateStr) return '—';
    const d = new Date(dateStr + 'T00:00:00');
    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

function formatTime(timeStr) {
    if (!timeStr) return '—';
    // Accept "HH:MM:SS" or "HH:MM"
    const [h, m] = timeStr.split(':').map(Number);
    const ampm = h >= 12 ? 'PM' : 'AM';
    const hour = h % 12 || 12;
    return `${hour}:${String(m).padStart(2, '0')} ${ampm}`;
}

function formatDateTimeFull(dateStr, timeStr) {
    return `${formatDate(dateStr)} at ${formatTime(timeStr)}`;
}

function todayISO() {
    const d = new Date();
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

function getStatusBadge(status) {
    const labels = {
        scheduled:  'Scheduled',
        confirmed:  'Confirmed',
        checked_in: 'Checked In',
        in_progress:'In Progress',
        completed:  'Completed',
        cancelled:  'Cancelled'
    };
    const label = labels[status] || status;
    return `<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold badge-${status || 'scheduled'}">${label}</span>`;
}

function spinRefreshIcon(id, spinning) {
    const icon = document.getElementById(id);
    if (!icon) return;
    spinning ? icon.classList.add('fa-spin') : icon.classList.remove('fa-spin');
}

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

function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

// ─────────────────────────────────────────────────────────────────────────────
// Stats
// ─────────────────────────────────────────────────────────────────────────────
async function loadStats() {
    try {
        const res = await apiRequest('/doctor/stats.php');
        if (res && res.success && res.stats) {
            const s = res.stats;
            document.getElementById('stat-today').textContent     = s.today_appointments ?? '0';
            document.getElementById('stat-checkin').textContent   = s.today_checked_in   ?? '0';
            document.getElementById('stat-completed').textContent = s.today_completed    ?? '0';
        }
    } catch (e) {
        console.error('loadStats error', e);
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Today's Appointments
// ─────────────────────────────────────────────────────────────────────────────
async function loadTodayAppointments(silent = false) {
    const skeleton = document.getElementById('today-skeleton');
    const list     = document.getElementById('today-appointments-list');

    if (!silent) {
        skeleton.classList.remove('hidden');
        list.classList.add('hidden');
        spinRefreshIcon('refresh-icon-today', true);
    }

    try {
        const today = todayISO();
        const res   = await apiRequest(`/doctor/get-appointments.php?date=${today}`);

        skeleton.classList.add('hidden');
        list.classList.remove('hidden');
        spinRefreshIcon('refresh-icon-today', false);

        if (!res || !res.success) {
            list.innerHTML = renderEmptyState('Could not load appointments. Please try again.');
            return;
        }

        const appts = res.appointments || [];
        if (appts.length === 0) {
            list.innerHTML = renderEmptyState("No appointments scheduled for today.", 'fa-calendar-day');
            return;
        }

        list.innerHTML = appts.map(a => renderAppointmentCard(a)).join('');

    } catch (e) {
        console.error('loadTodayAppointments error', e);
        skeleton.classList.add('hidden');
        list.classList.remove('hidden');
        spinRefreshIcon('refresh-icon-today', false);
        list.innerHTML = renderEmptyState('Network error. Please refresh.');
    }
}

function renderAppointmentCard(a) {
    const statusBadge = getStatusBadge(a.status);
    const timeStr     = a.formatted_time || formatTime(a.appointment_time);
    const name        = escapeHtml(a.patient_name || 'Unknown Patient');
    const age         = a.patient_dob ? `, ${Math.floor((Date.now() - new Date(a.patient_dob).getTime()) / 31557600000)} yrs` : '';
    const gender      = a.patient_gender ? ` • ${a.patient_gender}` : '';
    const reason      = escapeHtml(a.reason_for_visit || 'General Consultation');
    const priority    = a.priority === 'urgent' ? `<span class="ml-2 text-xs bg-red-100 text-red-600 px-2 py-0.5 rounded-full font-semibold">URGENT</span>` : '';

    // Action button based on status
    // Encode appointment JSON safely for use inside a single-quote onclick attribute
    const safeJson = JSON.stringify(a).replace(/'/g, '&#39;');
    let actionBtn = '';
    if (a.status === 'scheduled' || a.status === 'confirmed') {
        actionBtn = `
            <button onclick='openQRScanner(${safeJson})'
                class="flex items-center space-x-1 bg-amber-500 hover:bg-amber-600 text-white px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors">
                <i class="fas fa-qrcode"></i>
                <span>Scan QR</span>
            </button>`;
    } else if (a.status === 'checked_in' || a.status === 'in_progress') {
        actionBtn = `
            <button onclick='openRecordModal(${safeJson})'
                class="flex items-center space-x-1 bg-teal-600 hover:bg-teal-700 text-white px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors">
                <i class="fas fa-notes-medical"></i>
                <span>Write Record</span>
            </button>`;
    } else if (a.status === 'completed') {
        actionBtn = `
            <button onclick='viewRecord(${safeJson})'
                class="flex items-center space-x-1 bg-gray-100 hover:bg-gray-200 text-gray-600 px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors">
                <i class="fas fa-eye"></i>
                <span>View Record</span>
            </button>`;
    }

    return `
    <div class="appointment-card bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-3">
        <div class="flex items-start justify-between gap-3">
            <div class="flex items-start space-x-3 flex-1 min-w-0">
                <!-- Time bubble -->
                <div class="flex-shrink-0 text-center bg-teal-50 border border-teal-100 rounded-xl px-3 py-2 min-w-16">
                    <p class="text-teal-700 font-bold text-sm leading-tight">${timeStr}</p>
                    <p class="text-teal-500 text-xs mt-0.5">${a.appointment_number || '#' + a.id}</p>
                </div>
                <!-- Info -->
                <div class="flex-1 min-w-0">
                    <div class="flex items-center flex-wrap gap-1 mb-1">
                        <p class="font-semibold text-gray-800 text-sm">${name}${priority}</p>
                    </div>
                    <p class="text-xs text-gray-500 mb-1">${age ? age.replace(', ', '') : ''}${gender}</p>
                    <p class="text-xs text-gray-500 line-clamp-1 mb-2">
                        <i class="fas fa-comment-medical text-teal-400 mr-1"></i>${reason}
                    </p>
                    <div class="flex items-center flex-wrap gap-2">
                        ${statusBadge}
                        ${a.blood_group ? `<span class="text-xs bg-red-50 text-red-600 px-2 py-0.5 rounded-full border border-red-100">${escapeHtml(a.blood_group)}</span>` : ''}
                        ${a.allergies ? `<span class="text-xs bg-orange-50 text-orange-600 px-2 py-0.5 rounded-full border border-orange-100 truncate max-w-32" title="${escapeHtml(a.allergies)}"><i class="fas fa-exclamation-triangle mr-0.5"></i>Allergies</span>` : ''}
                    </div>
                </div>
            </div>
            <!-- Action -->
            <div class="flex-shrink-0">
                ${actionBtn}
            </div>
        </div>
    </div>`;
}

function renderEmptyState(msg, icon = 'fa-inbox') {
    return `
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 py-12 text-center">
        <i class="fas ${icon} text-4xl text-gray-300 mb-3"></i>
        <p class="text-gray-400 text-sm">${escapeHtml(msg)}</p>
    </div>`;
}

// ─────────────────────────────────────────────────────────────────────────────
// All Appointments
// ─────────────────────────────────────────────────────────────────────────────
async function loadAllAppointments() {
    const tbody = document.getElementById('all-appointments-tbody');
    tbody.innerHTML = `<tr><td colspan="5" class="px-4 py-8 text-center text-gray-400"><i class="fas fa-spinner fa-spin text-teal-400 text-xl mr-2"></i>Loading...</td></tr>`;

    const dateFilter   = document.getElementById('filter-date')?.value || '';
    const statusFilter = document.getElementById('filter-status')?.value || '';

    let url = '/doctor/get-appointments.php';
    const params = [];
    if (dateFilter)   params.push(`date=${encodeURIComponent(dateFilter)}`);
    if (statusFilter) params.push(`status=${encodeURIComponent(statusFilter)}`);
    if (params.length) url += '?' + params.join('&');

    try {
        const res = await apiRequest(url);

        if (!res || !res.success) {
            tbody.innerHTML = `<tr><td colspan="5" class="px-4 py-6 text-center text-red-400 text-sm">Failed to load appointments.</td></tr>`;
            return;
        }

        const appts = res.appointments || [];
        if (appts.length === 0) {
            tbody.innerHTML = `<tr><td colspan="5" class="px-4 py-8 text-center text-gray-400 text-sm"><i class="fas fa-calendar-times text-3xl block mb-2 text-gray-300"></i>No appointments found.</td></tr>`;
            return;
        }

        tbody.innerHTML = appts.map(a => `
            <tr class="border-b border-gray-50 hover:bg-gray-50 transition-colors">
                <td class="px-4 py-3 text-sm text-gray-700 whitespace-nowrap">${formatDate(a.appointment_date)}</td>
                <td class="px-4 py-3 text-sm text-gray-700 whitespace-nowrap">${formatTime(a.appointment_time)}</td>
                <td class="px-4 py-3">
                    <p class="text-sm font-medium text-gray-800">${escapeHtml(a.patient_name || '—')}</p>
                    ${a.patient_dob ? `<p class="text-xs text-gray-400">${Math.floor((Date.now() - new Date(a.patient_dob).getTime()) / 31557600000)} yrs${a.patient_gender ? ' • ' + a.patient_gender : ''}</p>` : ''}
                </td>
                <td class="px-4 py-3 text-sm text-gray-500 hidden sm:table-cell max-w-xs">
                    <span class="line-clamp-1">${escapeHtml(a.reason_for_visit || '—')}</span>
                </td>
                <td class="px-4 py-3">${getStatusBadge(a.status)}</td>
            </tr>
        `).join('');

    } catch (e) {
        console.error('loadAllAppointments error', e);
        tbody.innerHTML = `<tr><td colspan="5" class="px-4 py-6 text-center text-red-400 text-sm">Network error. Please try again.</td></tr>`;
    }
}

function applyFilters() {
    loadAllAppointments();
}

// ─────────────────────────────────────────────────────────────────────────────
// QR Scanner Modal
// ─────────────────────────────────────────────────────────────────────────────
function openQRScanner(appointment) {
    currentAppointment = appointment;

    const modal = document.getElementById('qr-modal');
    modal.classList.remove('hidden');
    document.getElementById('manual-token').value = '';
    document.getElementById('qr-status-text').textContent = 'Allow camera access to scan';

    // Initialize scanner after a short delay to let the modal render
    setTimeout(() => initQRScanner(), 300);
}

function initQRScanner() {
    // Clean up any previous instance
    stopQRScanner();

    const qrReaderId = 'qr-reader';
    const placeholder = document.getElementById('qr-reader-placeholder');

    try {
        html5QrCode = new Html5Qrcode(qrReaderId);

        Html5Qrcode.getCameras()
            .then(cameras => {
                if (!cameras || cameras.length === 0) {
                    if (placeholder) placeholder.innerHTML = '<i class="fas fa-video-slash text-3xl mb-2 block text-gray-400"></i><p class="text-sm text-gray-400">No camera found</p>';
                    document.getElementById('qr-status-text').textContent = 'No camera detected — use manual input below';
                    return;
                }

                // Prefer back/environment camera
                const camera = cameras.find(c => /back|rear|environment/i.test(c.label)) || cameras[0];

                if (placeholder) placeholder.style.display = 'none';

                const config = {
                    fps: 10,
                    qrbox: { width: 220, height: 220 },
                    aspectRatio: 1.0,
                    showTorchButtonIfSupported: true
                };

                html5QrCode.start(
                    camera.id,
                    config,
                    (decodedText) => onQRScanSuccess(decodedText),
                    (errorMsg) => { /* silent — fires on every non-QR frame */ }
                ).then(() => {
                    qrScannerRunning = true;
                    document.getElementById('qr-status-text').textContent = 'Camera active — point at patient QR code';
                }).catch(err => {
                    console.error('QR start error', err);
                    document.getElementById('qr-status-text').textContent = 'Camera access denied — use manual input';
                });
            })
            .catch(err => {
                console.error('getCameras error', err);
                document.getElementById('qr-status-text').textContent = 'Camera unavailable — use manual input below';
            });

    } catch (err) {
        console.error('QR init error', err);
        document.getElementById('qr-status-text').textContent = 'QR scanner unavailable — use manual input';
    }
}

function onQRScanSuccess(decodedText) {
    // Extract token_hash from QR URL: e.g. https://example.com/checkin?token=XXXX
    let tokenHash = decodedText.trim();

    try {
        const url = new URL(tokenHash);
        const tokenParam = url.searchParams.get('token');
        if (tokenParam) tokenHash = tokenParam;
    } catch (e) {
        // Not a URL — use as-is (raw token)
    }

    if (!tokenHash) {
        Swal.fire({ icon: 'warning', title: 'Invalid QR', text: 'Could not extract a token from the QR code.', confirmButtonColor: '#0891B2' });
        return;
    }

    stopQRScanner();
    performCheckIn(tokenHash);
}

function checkInManual() {
    const tokenHash = document.getElementById('manual-token').value.trim();
    if (!tokenHash) {
        Swal.fire({ icon: 'warning', title: 'Token Required', text: 'Please enter a QR token.', confirmButtonColor: '#0891B2' });
        return;
    }
    stopQRScanner();
    performCheckIn(tokenHash);
}

async function performCheckIn(tokenHash) {
    const loadingEl = document.getElementById('qr-status-text');
    if (loadingEl) loadingEl.textContent = 'Checking in patient...';

    Swal.fire({
        title: 'Checking in...',
        text: 'Please wait.',
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading()
    });

    try {
        const res = await apiRequest('/appointments/checkin.php', {
            method: 'POST',
            body: JSON.stringify({ token_hash: tokenHash })
        });

        if (!res || !res.success) {
            Swal.fire({
                icon: 'error',
                title: 'Check-in Failed',
                text: res?.message || 'Could not check in patient.',
                confirmButtonColor: '#0891B2'
            });
            return;
        }

        const appt = res.appointment || {};
        const dob  = appt.patient_dob ? new Date(appt.patient_dob).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }) : '—';

        closeQRScanner();

        Swal.fire({
            icon: 'success',
            title: 'Patient Checked In!',
            html: `
                <div class="text-left space-y-2 mt-2">
                    <div class="bg-teal-50 rounded-lg p-3">
                        <p class="text-lg font-bold text-teal-800">${escapeHtml(appt.patient_name || '—')}</p>
                        <p class="text-sm text-teal-600">${appt.specialization || ''}</p>
                    </div>
                    <div class="grid grid-cols-2 gap-2 text-sm">
                        <div><span class="text-gray-500">DOB:</span> <span class="font-medium">${dob}</span></div>
                        <div><span class="text-gray-500">Blood Group:</span> <span class="font-medium text-red-600">${escapeHtml(appt.blood_group || '—')}</span></div>
                        <div class="col-span-2"><span class="text-gray-500">Reason:</span> <span class="font-medium">${escapeHtml(appt.reason_for_visit || '—')}</span></div>
                        ${appt.allergies ? `<div class="col-span-2"><span class="text-gray-500">Allergies:</span> <span class="font-medium text-orange-600">${escapeHtml(appt.allergies)}</span></div>` : ''}
                    </div>
                </div>`,
            confirmButtonColor: '#0891B2',
            confirmButtonText: 'Got it'
        });

        loadTodayAppointments(true);
        loadStats();

    } catch (e) {
        console.error('performCheckIn error', e);
        Swal.fire({
            icon: 'error',
            title: 'Network Error',
            text: 'Could not connect to the server. Please try again.',
            confirmButtonColor: '#0891B2'
        });
    }
}

function stopQRScanner() {
    if (html5QrCode && qrScannerRunning) {
        html5QrCode.stop()
            .then(() => {
                qrScannerRunning = false;
                html5QrCode.clear();
            })
            .catch(err => {
                qrScannerRunning = false;
                console.warn('QR stop warning', err);
            });
    }
}

function closeQRScanner() {
    stopQRScanner();
    document.getElementById('qr-modal').classList.add('hidden');
    // Reset the reader div for next use
    const reader = document.getElementById('qr-reader');
    if (reader) {
        reader.innerHTML = `
            <div id="qr-reader-placeholder" class="text-center text-gray-400 py-8">
                <i class="fas fa-camera text-3xl mb-2 block"></i>
                <p class="text-sm">Camera initializing...</p>
            </div>`;
    }
    document.getElementById('manual-token').value = '';
}

// ─────────────────────────────────────────────────────────────────────────────
// Medical Record Modal
// ─────────────────────────────────────────────────────────────────────────────
function openRecordModal(appointment) {
    currentAppointment = appointment;

    const modal    = document.getElementById('record-modal');
    const form     = document.getElementById('medical-record-form');
    const readonly = document.getElementById('record-readonly-banner');
    const submitBtn = document.getElementById('record-submit-btn');

    // Reset form
    form.reset();
    readonly.classList.add('hidden');
    submitBtn.classList.remove('hidden');
    enableFormFields(form, true);

    // Pre-fill hidden fields
    document.getElementById('record-appointment-id').value = appointment.id || '';
    document.getElementById('record-patient-id').value     = appointment.patient_id || '';

    // Header info
    document.getElementById('record-modal-title').textContent    = 'Medical Record';
    document.getElementById('record-modal-subtitle').textContent = `Appointment #${appointment.appointment_number || appointment.id}`;

    // Patient banner
    const name    = appointment.patient_name || 'Unknown Patient';
    const age     = appointment.patient_age ? `${appointment.patient_age} yrs` : '';
    const gender  = appointment.patient_gender || '';
    const meta    = [age, gender, appointment.blood_group].filter(Boolean).join(' • ');

    document.getElementById('record-patient-name').textContent  = name;
    document.getElementById('record-patient-meta').textContent  = meta || '—';
    document.getElementById('record-appt-time').textContent     = formatTime(appointment.appointment_time);
    document.getElementById('record-appt-reason').textContent   = appointment.reason_for_visit || 'General Consultation';

    // Pre-fill chief complaint with reason_for_visit as a hint
    if (appointment.reason_for_visit) {
        document.getElementById('rec-chief-complaint').value = appointment.reason_for_visit;
    }

    modal.classList.remove('hidden');
    document.getElementById('rec-chief-complaint').focus();
}

function viewRecord(appointment) {
    // Open modal in read-only mode
    openRecordModal(appointment);

    const readonly  = document.getElementById('record-readonly-banner');
    const submitBtn = document.getElementById('record-submit-btn');
    const form      = document.getElementById('medical-record-form');

    readonly.classList.remove('hidden');
    submitBtn.classList.add('hidden');
    enableFormFields(form, false);

    document.getElementById('record-modal-title').textContent    = 'View Record';
    document.getElementById('record-modal-subtitle').textContent = 'Read-only';
}

function enableFormFields(form, enabled) {
    const fields = form.querySelectorAll('input, textarea, select');
    fields.forEach(f => {
        if (f.type === 'hidden') return;
        f.disabled = !enabled;
        if (!enabled) f.classList.add('bg-gray-50', 'cursor-not-allowed');
        else f.classList.remove('bg-gray-50', 'cursor-not-allowed');
    });
}

async function submitMedicalRecord(event) {
    event.preventDefault();

    const submitBtn = document.getElementById('record-submit-btn');
    const origHTML  = submitBtn.innerHTML;

    // Collect form data
    const appointmentId = document.getElementById('record-appointment-id').value;
    const patientId     = document.getElementById('record-patient-id').value;
    const chiefComplaint = document.getElementById('rec-chief-complaint').value.trim();
    const symptoms       = document.getElementById('rec-symptoms').value.trim();
    const bp             = document.getElementById('rec-bp').value.trim();
    const temp           = document.getElementById('rec-temp').value.trim();
    const hr             = document.getElementById('rec-hr').value.trim();
    const weight         = document.getElementById('rec-weight').value.trim();
    const height         = document.getElementById('rec-height').value.trim();
    const diagnosis      = document.getElementById('rec-diagnosis').value.trim();
    const prescription   = document.getElementById('rec-prescription').value.trim();
    const labTests       = document.getElementById('rec-lab-tests').value.trim();
    const notes          = document.getElementById('rec-notes').value.trim();
    const followUp       = document.getElementById('rec-followup').value;

    if (!chiefComplaint && !diagnosis) {
        Swal.fire({
            icon: 'warning',
            title: 'Required Fields',
            text: 'Please enter at least a chief complaint or diagnosis.',
            confirmButtonColor: '#0891B2'
        });
        return;
    }

    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <span>Saving...</span>';

    try {
        const res = await apiRequest('/doctor/save-medical-record.php', {
            method: 'POST',
            body: JSON.stringify({
                appointment_id: appointmentId,
                patient_id:     patientId,
                chief_complaint: chiefComplaint,
                symptoms,
                vital_signs: { bp, temperature: temp, heart_rate: hr, weight, height },
                diagnosis,
                prescription,
                lab_tests_ordered: labTests,
                notes,
                follow_up_date: followUp || null
            })
        });

        submitBtn.disabled = false;
        submitBtn.innerHTML = origHTML;

        if (!res || !res.success) {
            Swal.fire({
                icon: 'error',
                title: 'Save Failed',
                text: res?.message || 'Could not save medical record.',
                confirmButtonColor: '#0891B2'
            });
            return;
        }

        closeRecordModal();

        showToast('success', 'Record Saved!', 'Medical record saved and appointment marked as completed.');

        // Refresh data
        loadTodayAppointments(true);
        loadStats();

    } catch (e) {
        console.error('submitMedicalRecord error', e);
        submitBtn.disabled = false;
        submitBtn.innerHTML = origHTML;
        Swal.fire({
            icon: 'error',
            title: 'Network Error',
            text: 'Could not connect. Please try again.',
            confirmButtonColor: '#0891B2'
        });
    }
}

function closeRecordModal() {
    document.getElementById('record-modal').classList.add('hidden');
    document.getElementById('medical-record-form').reset();
    currentAppointment = null;
}

// ─────────────────────────────────────────────────────────────────────────────
// Profile
// ─────────────────────────────────────────────────────────────────────────────
let profileLoaded = false;

async function loadProfile(sidebarOnly = false) {
    if (sidebarOnly && profileLoaded) return;

    const skeleton = document.getElementById('profile-skeleton');
    const content  = document.getElementById('profile-content');

    if (!sidebarOnly) {
        if (skeleton) skeleton.classList.remove('hidden');
        if (content)  content.classList.add('hidden');
    }

    try {
        const res = await apiRequest('/doctor/get-profile.php');

        if (!res || !res.success) {
            if (!sidebarOnly) {
                if (skeleton) skeleton.classList.add('hidden');
                if (content) {
                    content.classList.remove('hidden');
                    content.innerHTML = `<div class="bg-white rounded-xl p-8 text-center text-red-400"><i class="fas fa-exclamation-circle text-3xl mb-2 block"></i>Could not load profile.</div>`;
                }
            }
            return;
        }

        const p = res.profile || res.doctor || {};

        // Populate sidebar
        const sName = p.full_name || `Dr. ${p.first_name || ''} ${p.last_name || ''}`.trim() || 'Doctor';
        document.getElementById('sidebar-name').textContent = sName;
        document.getElementById('sidebar-spec').textContent  = p.specialization || 'Internal Medicine';
        document.getElementById('header-name').textContent   = sName;
        document.getElementById('header-role').textContent   = p.specialization || 'Doctor';

        // Avatar initials
        const initials = (p.first_name?.[0] || 'D') + (p.last_name?.[0] || '');
        const avatarHtml = p.profile_image_url
            ? `<img src="${escapeHtml(p.profile_image_url)}" alt="${escapeHtml(sName)}" class="w-full h-full object-cover">`
            : `<span class="text-sm font-bold">${escapeHtml(initials)}</span>`;

        document.getElementById('sidebar-avatar').innerHTML = avatarHtml;
        document.getElementById('header-avatar').innerHTML  = avatarHtml;

        profileLoaded = true;
        if (sidebarOnly) return;

        // Populate profile tab
        const profileAvatar = document.getElementById('profile-avatar');
        if (profileAvatar) {
            profileAvatar.innerHTML = p.profile_image_url
                ? `<img src="${escapeHtml(p.profile_image_url)}" alt="${escapeHtml(sName)}" class="w-full h-full object-cover">`
                : `<i class="fas fa-user-md text-3xl"></i>`;
        }

        setProfileText('profile-name',       sName);
        setProfileText('profile-spec',        p.specialization || '—');
        setProfileText('profile-dept',        p.department_name || p.department || '—');
        setProfileText('profile-license',     p.license_number || '—');
        setProfileText('profile-email',       p.email || p.user_email || '—');
        setProfileText('profile-contact',     p.contact_number || p.phone || '—');
        setProfileText('profile-experience',  p.experience_years ? `${p.experience_years} years` : '—');
        setProfileText('profile-fee',         p.consultation_fee ? `₱${Number(p.consultation_fee).toLocaleString()}` : '—');
        setProfileText('profile-qual',        p.qualification || '—');
        setProfileText('profile-bio',         p.bio || 'No bio available.');

        // Schedule
        renderSchedule(p.schedule || []);

        if (skeleton) skeleton.classList.add('hidden');
        if (content)  content.classList.remove('hidden');

    } catch (e) {
        console.error('loadProfile error', e);
        if (!sidebarOnly) {
            if (skeleton) skeleton.classList.add('hidden');
            if (content) {
                content.classList.remove('hidden');
                content.innerHTML = `<div class="bg-white rounded-xl p-8 text-center text-red-400"><i class="fas fa-exclamation-circle text-3xl mb-2 block"></i>Network error loading profile.</div>`;
            }
        }
    }
}

function setProfileText(id, text) {
    const el = document.getElementById(id);
    if (el) el.textContent = text;
}

function renderSchedule(schedule) {
    const tbody = document.getElementById('schedule-tbody');
    if (!tbody) return;

    if (!schedule || schedule.length === 0) {
        tbody.innerHTML = `<tr><td colspan="5" class="px-3 py-4 text-center text-gray-400 text-sm">No schedule configured.</td></tr>`;
        return;
    }

    const dayOrder = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
    const sorted   = [...schedule].sort((a, b) => dayOrder.indexOf(a.day_of_week) - dayOrder.indexOf(b.day_of_week));

    tbody.innerHTML = sorted.map(s => `
        <tr class="border-b border-gray-50 hover:bg-gray-50 transition-colors">
            <td class="px-3 py-2.5 font-medium text-gray-800 text-sm">${escapeHtml(s.day_of_week || '—')}</td>
            <td class="px-3 py-2.5 text-sm text-gray-700">${formatTime(s.start_time)}</td>
            <td class="px-3 py-2.5 text-sm text-gray-700">${formatTime(s.end_time)}</td>
            <td class="px-3 py-2.5 text-sm text-gray-500 hidden sm:table-cell">${s.slot_duration ? s.slot_duration + ' min' : '—'}</td>
            <td class="px-3 py-2.5 text-sm text-gray-500 hidden sm:table-cell">${s.max_patients || '—'}</td>
        </tr>
    `).join('');
}

// ─────────────────────────────────────────────────────────────────────────────
// Change Password
// ─────────────────────────────────────────────────────────────────────────────
async function submitChangePassword(event) {
    event.preventDefault();

    const current  = document.getElementById('cp-current').value;
    const newPass  = document.getElementById('cp-new').value;
    const confirm  = document.getElementById('cp-confirm').value;
    const btn      = event.submitter || document.querySelector('#change-password-form button[type="submit"]');

    if (newPass !== confirm) {
        Swal.fire({
            icon: 'warning',
            title: 'Password Mismatch',
            text: 'New password and confirmation do not match.',
            confirmButtonColor: '#0891B2'
        });
        return;
    }

    if (newPass.length < 8) {
        Swal.fire({
            icon: 'warning',
            title: 'Too Short',
            text: 'New password must be at least 8 characters.',
            confirmButtonColor: '#0891B2'
        });
        return;
    }

    const origHTML = btn ? btn.innerHTML : '';
    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Updating...'; }

    try {
        const res = await apiRequest('/doctor/change-password.php', {
            method: 'POST',
            body: JSON.stringify({ current_password: current, new_password: newPass })
        });

        if (btn) { btn.disabled = false; btn.innerHTML = origHTML; }

        if (!res || !res.success) {
            Swal.fire({
                icon: 'error',
                title: 'Failed',
                text: res?.message || 'Could not change password.',
                confirmButtonColor: '#0891B2'
            });
            return;
        }

        document.getElementById('change-password-form').reset();

        showToast('success', 'Password Updated', 'Your password has been changed successfully.');

    } catch (e) {
        if (btn) { btn.disabled = false; btn.innerHTML = origHTML; }
        Swal.fire({
            icon: 'error',
            title: 'Network Error',
            text: 'Could not connect to the server.',
            confirmButtonColor: '#0891B2'
        });
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Keyboard shortcuts
// ─────────────────────────────────────────────────────────────────────────────
document.addEventListener('keydown', (e) => {
    // Escape closes modals
    if (e.key === 'Escape') {
        closeQRScanner();
        closeRecordModal();
    }
});
