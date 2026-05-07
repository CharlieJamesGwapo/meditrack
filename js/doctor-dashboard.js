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

    // Reset manual-entry fallback to hidden so each open shows camera as primary path
    const manualBlock  = document.getElementById('manual-block');
    const manualToggle = document.getElementById('manual-toggle');
    if (manualBlock)  manualBlock.classList.add('hidden');
    if (manualToggle) manualToggle.classList.remove('hidden');

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

    // Chief complaint is read-only (sourced from triage; falls back to reason_for_visit)
    const ccDisplay = document.getElementById('rec-chief-complaint-display');
    const triageCC = appointment.triage_chief_complaint || (appointment.vitals && appointment.vitals.chief_complaint) || '';
    if (ccDisplay) ccDisplay.textContent = triageCC || appointment.reason_for_visit || '—';

    // Render vitals readout (read-only; staff records vitals at triage)
    renderVitalsReadout(appointment);

    // Load existing referral and follow-up for this appointment (Batch C3)
    if (appointment.id && typeof loadReferralForAppointment === 'function') loadReferralForAppointment(appointment.id);
    if (appointment.id && typeof loadFollowupForAppointment === 'function') loadFollowupForAppointment(appointment.id);

    modal.classList.remove('hidden');
    document.getElementById('rec-symptoms').focus();
}

function renderVitalsReadout(appointment) {
    const readout = document.getElementById('vitals-readout');
    const meta    = document.getElementById('vitals-recorded-meta');
    if (!readout) return;
    const v = appointment.vitals;
    if (v) {
        readout.innerHTML = `
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <div><div class="text-[10px] text-slate-400 uppercase tracking-wide">BP</div><div class="font-semibold text-[#083344]">${escapeHtml(v.blood_pressure || '—')}</div></div>
                <div><div class="text-[10px] text-slate-400 uppercase tracking-wide">Temp</div><div class="font-semibold text-[#083344]">${v.temperature ?? '—'} °C</div></div>
                <div><div class="text-[10px] text-slate-400 uppercase tracking-wide">HR</div><div class="font-semibold text-[#083344]">${v.heart_rate ?? '—'} bpm</div></div>
                <div><div class="text-[10px] text-slate-400 uppercase tracking-wide">SpO₂</div><div class="font-semibold text-[#083344]">${v.oxygen_saturation ?? '—'} %</div></div>
                <div><div class="text-[10px] text-slate-400 uppercase tracking-wide">Weight</div><div class="font-semibold text-[#083344]">${v.weight ?? '—'} kg</div></div>
                <div><div class="text-[10px] text-slate-400 uppercase tracking-wide">Height</div><div class="font-semibold text-[#083344]">${v.height_cm ?? '—'} cm</div></div>
                ${v.notes ? `<div class="col-span-2 sm:col-span-2"><div class="text-[10px] text-slate-400 uppercase tracking-wide">Notes</div><div class="text-[#0f172a]">${escapeHtml(v.notes)}</div></div>` : ''}
            </div>
        `;
        if (meta && v.recorded_at) {
            meta.classList.remove('hidden');
            meta.textContent = 'Recorded ' + new Date(v.recorded_at).toLocaleString();
        }
    } else {
        readout.innerHTML = `
            <div class="flex items-center justify-between">
                <p class="text-slate-500">Vitals not recorded yet.</p>
                <button type="button" id="btn-doctor-record-vitals" class="px-3 py-1.5 rounded-lg bg-cyan-600 text-white text-xs font-semibold hover:bg-cyan-700">
                    <i class="fa-solid fa-heart-pulse mr-1"></i> Record vitals
                </button>
            </div>
        `;
        const btn = document.getElementById('btn-doctor-record-vitals');
        if (btn) btn.addEventListener('click', () => openDoctorVitalsModal(appointment.id, appointment.patient_name));
        if (meta) meta.classList.add('hidden');
    }
}

async function openDoctorVitalsModal(appointment_id, patient_name) {
    const form = document.getElementById('form-doctor-vitals');
    form.reset();
    document.getElementById('dv-appt-id').value = appointment_id;
    document.getElementById('dv-patient-name').textContent = patient_name || '';
    try {
        const data = await apiRequest('/staff/get-vitals.php?appointment_id=' + appointment_id);
        if (data && data.success && data.vitals) {
            const v = data.vitals;
            form.chief_complaint.value   = v.chief_complaint   ?? '';
            form.blood_pressure.value    = v.blood_pressure    ?? '';
            form.temperature.value       = v.temperature       ?? '';
            form.heart_rate.value        = v.heart_rate        ?? '';
            form.oxygen_saturation.value = v.oxygen_saturation ?? '';
            form.weight.value            = v.weight            ?? '';
            form.height_cm.value         = v.height_cm         ?? '';
            form.notes.value             = v.notes             ?? '';
        }
    } catch (_) {}
    document.getElementById('modal-doctor-vitals').classList.remove('hidden');
}

document.querySelectorAll('[data-close-doctor-vitals]').forEach(b =>
    b.addEventListener('click', () => document.getElementById('modal-doctor-vitals').classList.add('hidden'))
);

document.getElementById('form-doctor-vitals').addEventListener('submit', async (ev) => {
    ev.preventDefault();
    const fd = new FormData(ev.target);
    const body = Object.fromEntries(fd.entries());
    ['temperature', 'heart_rate', 'oxygen_saturation', 'weight', 'height_cm'].forEach(k => {
        if (body[k] === '') delete body[k];
    });
    body.appointment_id = +body.appointment_id;
    const data = await apiRequest('/staff/save-vitals.php', {
        method: 'POST',
        body: JSON.stringify(body)
    });
    if (!data || !data.success) {
        Swal.fire('Error', data?.message || 'Failed to save', 'error');
        return;
    }
    document.getElementById('modal-doctor-vitals').classList.add('hidden');
    Swal.fire({ icon: 'success', title: 'Saved', text: 'Vitals recorded.', confirmButtonColor: '#0891B2' });
    if (typeof loadTodayAppointments === 'function') loadTodayAppointments(true);
    if (currentAppointment && currentAppointment.id === body.appointment_id) {
        // Refetch to update the readout
        try {
            const fresh = await apiRequest('/doctor/get-appointments.php');
            const refreshed = (fresh?.appointments || []).find(a => a.id === body.appointment_id);
            if (refreshed) {
                currentAppointment = refreshed;
                renderVitalsReadout(refreshed);
            }
        } catch (_) {}
    }
});

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

    // Collect form data (chief_complaint + vitals are sourced from triage and not sent here)
    const appointmentId = document.getElementById('record-appointment-id').value;
    const patientId     = document.getElementById('record-patient-id').value;
    const symptoms       = document.getElementById('rec-symptoms').value.trim();
    const diagnosis      = document.getElementById('rec-diagnosis').value.trim();
    const prescription   = document.getElementById('rec-prescription').value.trim();
    const labTests       = document.getElementById('rec-lab-tests').value.trim();
    const notes          = document.getElementById('rec-notes').value.trim();
    const followUp       = document.getElementById('rec-followup').value;

    if (!symptoms && !diagnosis) {
        Swal.fire({
            icon: 'warning',
            title: 'Required Fields',
            text: 'Please enter at least symptoms or a diagnosis.',
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
                symptoms,
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

// ─── Referrals (Batch C3) ────────────────────────────────────
function bindReferralCard() {
    const enable = document.getElementById('ref-enable');
    const body   = document.getElementById('ref-body');
    const spec   = document.getElementById('ref-specialty');
    const otherWrap = document.getElementById('ref-specialty-other-wrap');
    if (!enable || !body) return;
    enable.addEventListener('change', () => body.classList.toggle('hidden', !enable.checked));
    spec.addEventListener('change', () => { otherWrap.style.display = spec.value === 'Other' ? '' : 'none'; });
    document.getElementById('ref-save-btn').addEventListener('click', saveReferral);
    document.getElementById('ref-print-btn').addEventListener('click', () => {
        if (!currentAppointment) return;
        window.open('print-referral.html?appointment_id=' + currentAppointment.id, '_blank');
    });
}

async function loadReferralForAppointment(appointment_id) {
    const status = document.getElementById('ref-status');
    const printBtn = document.getElementById('ref-print-btn');
    const enable = document.getElementById('ref-enable');
    const body   = document.getElementById('ref-body');
    const spec   = document.getElementById('ref-specialty');
    const otherWrap = document.getElementById('ref-specialty-other-wrap');
    if (!enable) return;
    enable.checked = false;
    body.classList.add('hidden');
    spec.value = '';
    otherWrap.style.display = 'none';
    document.getElementById('ref-specialty-other').value = '';
    document.getElementById('ref-suggested').value = '';
    document.getElementById('ref-urgency').value = 'routine';
    document.getElementById('ref-reason').value = '';
    status.textContent = '';
    printBtn.classList.add('hidden');
    try {
        const data = await apiRequest('/doctor/get-referral.php?appointment_id=' + appointment_id);
        if (data && data.success && data.referral) {
            const r = data.referral;
            enable.checked = true;
            body.classList.remove('hidden');
            spec.value = r.specialty || '';
            if (spec.value === 'Other') {
                otherWrap.style.display = '';
                document.getElementById('ref-specialty-other').value = r.specialty_other || '';
            }
            document.getElementById('ref-suggested').value = r.suggested_specialist || '';
            document.getElementById('ref-urgency').value   = r.urgency || 'routine';
            document.getElementById('ref-reason').value    = r.reason || '';
            printBtn.classList.remove('hidden');
            status.textContent = 'Referral on file — last updated ' + new Date(r.updated_at || r.issued_at).toLocaleString();
        }
    } catch (_) {}
}

async function saveReferral() {
    if (!currentAppointment) return;
    const status = document.getElementById('ref-status');
    const body = {
        appointment_id:       currentAppointment.id,
        specialty:            document.getElementById('ref-specialty').value,
        specialty_other:      document.getElementById('ref-specialty-other').value.trim(),
        suggested_specialist: document.getElementById('ref-suggested').value.trim(),
        urgency:              document.getElementById('ref-urgency').value,
        reason:               document.getElementById('ref-reason').value.trim(),
    };
    if (!body.specialty || !body.reason) {
        Swal.fire('Required', 'Specialty and reason are required.', 'warning');
        return;
    }
    if (body.specialty === 'Other' && !body.specialty_other) {
        Swal.fire('Required', 'Please describe the specialty.', 'warning');
        return;
    }
    status.textContent = 'Saving…';
    const data = await apiRequest('/doctor/save-referral.php', { method: 'POST', body: JSON.stringify(body) });
    if (!data || !data.success) {
        status.textContent = '';
        Swal.fire('Error', data?.message || 'Failed to save referral', 'error');
        return;
    }
    status.textContent = 'Saved ' + new Date().toLocaleString();
    document.getElementById('ref-print-btn').classList.remove('hidden');
}

// ─── Follow-up scheduling (Batch C3) ─────────────────────────
function bindFollowupCard() {
    const enable = document.getElementById('fu-enable');
    const body   = document.getElementById('fu-body');
    if (!enable || !body) return;
    enable.addEventListener('change', () => body.classList.toggle('hidden', !enable.checked));
    document.getElementById('fu-save-btn').addEventListener('click', saveFollowup);
}

async function loadFollowupForAppointment(appointment_id) {
    const enable = document.getElementById('fu-enable');
    const body   = document.getElementById('fu-body');
    const dateEl = document.getElementById('fu-date');
    const timeEl = document.getElementById('fu-time');
    const reasonEl = document.getElementById('fu-reason');
    const statusEl = document.getElementById('fu-status');
    const hidden = document.getElementById('rec-followup');
    if (!enable || !body) return;
    enable.checked = false;
    body.classList.add('hidden');
    dateEl.value = ''; timeEl.value = ''; reasonEl.value = ''; statusEl.textContent = '';
    if (hidden) hidden.value = '';
    try {
        const data = await apiRequest('/doctor/get-followup.php?parent_appointment_id=' + appointment_id);
        if (data && data.success && data.followup) {
            const f = data.followup;
            enable.checked = true;
            body.classList.remove('hidden');
            dateEl.value = f.appointment_date || '';
            timeEl.value = (f.appointment_time || '').substring(0, 5);
            reasonEl.value = f.reason_for_visit || '';
            statusEl.textContent = `Existing follow-up #${f.appointment_number} (${f.status})`;
            if (hidden) hidden.value = f.appointment_date || '';
        }
    } catch (_) {}
}

async function saveFollowup() {
    if (!currentAppointment) return;
    const date = document.getElementById('fu-date').value;
    const time = document.getElementById('fu-time').value;
    const reason = document.getElementById('fu-reason').value.trim();
    const statusEl = document.getElementById('fu-status');
    if (!date || !time) { Swal.fire('Required', 'Pick both a date and a time.', 'warning'); return; }
    statusEl.textContent = 'Saving…';
    const data = await apiRequest('/doctor/schedule-followup.php', {
        method: 'POST',
        body: JSON.stringify({
            parent_appointment_id: currentAppointment.id,
            appointment_date: date,
            appointment_time: time,
            reason_for_visit: reason
        })
    });
    if (!data || !data.success) {
        statusEl.textContent = '';
        Swal.fire('Error', data?.message || 'Failed to save follow-up', 'error');
        return;
    }
    statusEl.textContent = `Follow-up #${data.appointment_number} ${data.mode} for ${date} ${time}`;
    const hidden = document.getElementById('rec-followup');
    if (hidden) hidden.value = date;
    if (typeof loadTodayAppointments === 'function') loadTodayAppointments(true);
}

window.addEventListener('DOMContentLoaded', () => { bindReferralCard(); bindFollowupCard(); });

// ─── Patient History (Batch C addition) ────────────────────────────────
async function openPatientHistoryFromDrawer() {
    if (!currentAppointment || !currentAppointment.patient_id) {
        Swal.fire('No patient', 'Open a patient appointment first.', 'info');
        return;
    }
    return viewPatientHistory(currentAppointment.patient_id);
}

async function viewPatientHistory(patientId) {
    const modal   = document.getElementById('modal-patient-history');
    const content = document.getElementById('ph-content');
    const meta    = document.getElementById('ph-patient-meta');
    if (!modal || !content) return;

    modal.classList.remove('hidden');
    content.innerHTML = `
        <div class="text-center py-10 text-slate-400">
            <i class="fas fa-spinner fa-spin text-2xl"></i>
            <p class="text-sm mt-2">Loading history…</p>
        </div>`;
    meta.textContent = 'Loading…';

    try {
        const data = await apiRequest('/admin/get-patient-history.php?patient_id=' + patientId);
        if (!data || !data.success) throw new Error(data?.message || 'Failed to load history');
        renderPatientHistory(data.patient, data.history);
    } catch (e) {
        content.innerHTML = `<div class="text-center py-10 text-red-600">
            <i class="fas fa-circle-exclamation text-2xl"></i>
            <p class="text-sm mt-2">${escapeHtml(e.message)}</p>
        </div>`;
    }
}

function renderPatientHistory(patient, history) {
    const meta = document.getElementById('ph-patient-meta');
    const content = document.getElementById('ph-content');
    const ageStr = patient.age != null ? `${patient.age}y` : '';
    const parts = [
        patient.gender, ageStr, patient.blood_group ? 'Blood ' + patient.blood_group : '',
        patient.contact_number, patient.email
    ].filter(Boolean);
    meta.innerHTML = `<strong class="text-[#083344]">${escapeHtml(patient.full_name)}</strong> &middot; ${parts.map(escapeHtml).join(' &middot; ')}`;

    if (!history.length) {
        content.innerHTML = `
            <div class="text-center py-10 text-slate-400">
                <i class="fas fa-folder-open text-3xl mb-3"></i>
                <p class="text-sm">No appointment history yet.</p>
            </div>`;
        return;
    }

    const stats = history.reduce((s, h) => {
        s.total++;
        if (h.appointment.status === 'completed') s.completed++;
        if (h.appointment.status === 'cancelled') s.cancelled++;
        if (h.medical_record) s.records++;
        if (h.certificate) s.certs++;
        if (h.referral) s.referrals++;
        if (h.appointment.is_followup) s.followups++;
        return s;
    }, { total: 0, completed: 0, cancelled: 0, records: 0, certs: 0, referrals: 0, followups: 0 });

    const summaryHtml = `
        <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7 gap-2 mb-5">
            ${[
                ['Visits', stats.total, 'cyan'],
                ['Completed', stats.completed, 'emerald'],
                ['Cancelled', stats.cancelled, 'red'],
                ['Records', stats.records, 'blue'],
                ['Certs', stats.certs, 'green'],
                ['Referrals', stats.referrals, 'amber'],
                ['Follow-ups', stats.followups, 'purple'],
            ].map(([label, n, color]) => `
                <div class="rounded-lg bg-${color}-50 border border-${color}-100 px-3 py-2 text-center">
                    <div class="text-lg font-bold text-${color}-700">${n}</div>
                    <div class="text-[10px] uppercase tracking-wide text-${color}-600 font-semibold">${label}</div>
                </div>
            `).join('')}
        </div>`;

    const timelineHtml = history.map(h => renderHistoryEntry(h)).join('');
    content.innerHTML = summaryHtml + '<div id="ph-timeline" class="space-y-3">' + timelineHtml + '</div>';
    const search = document.getElementById('ph-search');
    if (search) search.value = '';
}

function filterPatientHistoryTimeline(q) {
    const tl = document.getElementById('ph-timeline');
    if (!tl) return;
    const norm = (q || '').toLowerCase().trim();
    tl.querySelectorAll('[data-appt-search]').forEach(el => {
        el.style.display = (!norm || el.dataset.apptSearch.includes(norm)) ? '' : 'none';
    });
}
window.filterPatientHistoryTimeline = filterPatientHistoryTimeline;

function renderHistoryEntry(h) {
    const a = h.appointment;
    const statusBadgeClass = {
        scheduled:   'bg-blue-50 text-blue-700 border-blue-200',
        checked_in:  'bg-amber-50 text-amber-700 border-amber-200',
        in_progress: 'bg-purple-50 text-purple-700 border-purple-200',
        completed:   'bg-emerald-50 text-emerald-700 border-emerald-200',
        cancelled:   'bg-red-50 text-red-700 border-red-200',
        no_show:     'bg-gray-50 text-gray-700 border-gray-200',
    }[a.status] || 'bg-gray-50 text-gray-700 border-gray-200';

    const dateStr = a.appointment_date ? new Date(a.appointment_date).toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' }) : '';
    const timeStr = (a.appointment_time || '').substring(0, 5);

    const v = h.vitals;
    const vitalsHtml = v ? `
        <div class="bg-rose-50 border border-rose-100 rounded-lg p-3">
            <div class="text-[10px] uppercase tracking-wide font-bold text-rose-700 mb-1.5"><i class="fas fa-heartbeat mr-1"></i>Vitals</div>
            <div class="grid grid-cols-3 sm:grid-cols-6 gap-2 text-xs">
                ${[
                    ['BP',   v.blood_pressure],
                    ['Temp', v.temperature ? v.temperature + '°C' : null],
                    ['HR',   v.heart_rate ? v.heart_rate + 'bpm' : null],
                    ['SpO₂', v.oxygen_saturation ? v.oxygen_saturation + '%' : null],
                    ['Wt',   v.weight ? v.weight + 'kg' : null],
                    ['Ht',   v.height_cm ? v.height_cm + 'cm' : null],
                ].map(([k, val]) => val ? `<div><span class="text-rose-500">${k}:</span> <span class="font-semibold">${escapeHtml(val)}</span></div>` : '').join('')}
            </div>
            ${v.chief_complaint ? `<div class="text-xs text-slate-600 mt-2"><strong>Chief complaint:</strong> ${escapeHtml(v.chief_complaint)}</div>` : ''}
        </div>` : '';

    const r = h.medical_record;
    const recordHtml = r ? `
        <div class="bg-cyan-50 border border-cyan-100 rounded-lg p-3">
            <div class="text-[10px] uppercase tracking-wide font-bold text-cyan-700 mb-1.5"><i class="fas fa-stethoscope mr-1"></i>Medical Record</div>
            <div class="space-y-1.5 text-xs">
                ${r.symptoms     ? `<div><strong>Symptoms:</strong> ${escapeHtml(r.symptoms)}</div>` : ''}
                ${r.diagnosis    ? `<div><strong>Diagnosis:</strong> ${escapeHtml(r.diagnosis)}</div>` : ''}
                ${r.prescription ? `<div><strong>Prescription:</strong> ${escapeHtml(r.prescription)}</div>` : ''}
                ${r.lab_tests_ordered ? `<div><strong>Labs:</strong> ${escapeHtml(r.lab_tests_ordered)}</div>` : ''}
                ${r.notes        ? `<div class="text-slate-500 italic">${escapeHtml(r.notes)}</div>` : ''}
            </div>
        </div>` : '';

    const c = h.certificate;
    const certHtml = c ? `
        <div class="bg-emerald-50 border border-emerald-100 rounded-lg p-3 flex items-start gap-2">
            <div class="flex-1">
                <div class="text-[10px] uppercase tracking-wide font-bold text-emerald-700 mb-1.5"><i class="fas fa-file-medical mr-1"></i>Medical Certificate</div>
                <div class="text-xs text-slate-700">
                    <strong>${escapeHtml(c.diagnosis)}</strong> &middot; Rest from ${escapeHtml(c.rest_period_start)} to ${escapeHtml(c.rest_period_end)} (${c.rest_days} day${c.rest_days == 1 ? '' : 's'})
                    ${c.requested_by ? ` &middot; <span class="text-slate-500">requested by ${escapeHtml(c.requested_by)}</span>` : ''}
                </div>
            </div>
            <a href="print-certificate.html?appointment_id=${a.id}" target="_blank"
                class="px-2 py-1 rounded-md bg-white border border-emerald-300 text-emerald-700 text-[11px] font-semibold hover:bg-emerald-100">
                <i class="fas fa-print"></i> Print
            </a>
        </div>` : '';

    const ref = h.referral;
    const refHtml = ref ? `
        <div class="bg-blue-50 border border-blue-100 rounded-lg p-3 flex items-start gap-2">
            <div class="flex-1">
                <div class="text-[10px] uppercase tracking-wide font-bold text-blue-700 mb-1.5"><i class="fas fa-share-from-square mr-1"></i>Referral</div>
                <div class="text-xs text-slate-700">
                    <strong>${escapeHtml(ref.specialty === 'Other' && ref.specialty_other ? ref.specialty_other : ref.specialty)}</strong>
                    &middot; <span class="uppercase font-semibold text-blue-700">${escapeHtml(ref.urgency)}</span>
                    <div class="text-slate-600 mt-1">${escapeHtml(ref.reason)}</div>
                    ${ref.suggested_specialist ? `<div class="text-slate-500 mt-1">Suggested: ${escapeHtml(ref.suggested_specialist)}</div>` : ''}
                </div>
            </div>
            <a href="print-referral.html?appointment_id=${a.id}" target="_blank"
                class="px-2 py-1 rounded-md bg-white border border-blue-300 text-blue-700 text-[11px] font-semibold hover:bg-blue-100">
                <i class="fas fa-print"></i> Print
            </a>
        </div>` : '';

    const cancelHtml = a.status === 'cancelled' ? `
        <div class="bg-red-50 border border-red-100 rounded-lg p-3">
            <div class="text-[10px] uppercase tracking-wide font-bold text-red-700 mb-1.5"><i class="fas fa-times-circle mr-1"></i>Cancelled</div>
            <div class="text-xs text-slate-700">
                By <strong>${escapeHtml(a.cancelled_by || '—')}</strong>${a.cancel_reason ? ` &middot; ${escapeHtml(a.cancel_reason)}` : ''}
                ${a.cancelled_at ? `<div class="text-slate-500 mt-1">${new Date(a.cancelled_at).toLocaleString()}</div>` : ''}
            </div>
        </div>` : '';

    const searchBlob = ((a.appointment_number || '') + ' ' + (a.reason_for_visit || '') + ' ' + (h.doctor.full_name || '') + ' ' + (a.status || '') + ' ' + (r ? (r.diagnosis || '') + ' ' + (r.symptoms || '') + ' ' + (r.prescription || '') : '') + ' ' + (c ? (c.diagnosis || '') : '') + ' ' + (ref ? (ref.specialty || '') + ' ' + (ref.reason || '') : '')).toLowerCase();
    return `
        <div class="border border-slate-200 rounded-xl overflow-hidden" data-appt-search="${escapeHtml(searchBlob)}">
            <div class="bg-slate-50 px-4 py-2.5 flex items-center justify-between gap-3 flex-wrap">
                <div class="flex items-center gap-2 min-w-0">
                    <span class="text-xs font-mono text-slate-500">#${escapeHtml(a.appointment_number || a.id)}</span>
                    <span class="font-semibold text-[#083344] text-sm">${dateStr}${timeStr ? ' · ' + timeStr : ''}</span>
                    ${a.is_followup ? '<span class="inline-flex items-center px-1.5 py-0.5 rounded-full bg-purple-100 text-purple-700 text-[10px] font-semibold uppercase">Follow-up</span>' : ''}
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-xs text-slate-500">Dr. ${escapeHtml(h.doctor.full_name)}</span>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold uppercase border ${statusBadgeClass}">${escapeHtml(a.status)}</span>
                </div>
            </div>
            <div class="p-3 space-y-2.5">
                ${a.reason_for_visit ? `<div class="text-xs"><strong class="text-slate-700">Reason:</strong> <span class="text-slate-600">${escapeHtml(a.reason_for_visit)}</span></div>` : ''}
                ${vitalsHtml}
                ${recordHtml}
                ${certHtml}
                ${refHtml}
                ${cancelHtml}
            </div>
        </div>`;
}

window.viewPatientHistory = viewPatientHistory;
window.openPatientHistoryFromDrawer = openPatientHistoryFromDrawer;
