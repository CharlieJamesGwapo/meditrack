/**
 * patient-dashboard.js — Internal Medicine OPD Management System
 * Patient Portal
 */

'use strict';

// ─── Auth Guard ───────────────────────────────────────────────────────────────
(function authGuard() {
    const user = JSON.parse(sessionStorage.getItem('user') || 'null');
    if (!user || user.role !== 'patient') {
        window.location.href = '/meditrack/pages/login.html';
    }
})();

// ─── Constants ────────────────────────────────────────────────────────────────
// API_BASE is defined in auth.js (loaded first)

// ─── State ────────────────────────────────────────────────────────────────────
let currentUser       = null;
let allAppointments   = [];
let currentDoctor     = null;
let selectedSlot      = null;
let currentQRApptId   = null;
let currentAppointments = [];

// ─── DOM Ready ────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    currentUser = JSON.parse(sessionStorage.getItem('user') || 'null');
    if (!currentUser) return;

    initUI();
    startClock();
    loadBookingTab();

    // Set min date for date picker
    const dateInput = document.getElementById('appointmentDate');
    if (dateInput) dateInput.min = todayISO();
});

// ─── UI Init ──────────────────────────────────────────────────────────────────
function initUI() {
    const name = currentUser.name || currentUser.full_name || currentUser.username || 'Patient';

    setTextContent('sidebarName',   name);
    setTextContent('headerName',    name);
    setTextContent('bannerName',    name);
    setTextContent('headerGreeting', `Welcome, ${name}`);
    setTextContent('headerDate',    formatDateLong(new Date()));

    // Avatars: initials
    const initials = getInitials(name);
    setAvatarInitials('sidebarAvatar',  initials, 'teal');
    setAvatarInitials('headerAvatar',   initials, 'teal');
    setAvatarInitials('bannerAvatar',   initials, 'teal');
}

// ─── Clock ────────────────────────────────────────────────────────────────────
function startClock() {
    function tick() {
        const el = document.getElementById('headerClock');
        if (el) el.textContent = new Date().toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
    }
    tick();
    setInterval(tick, 1000);
}

// ─── Tab Navigation ───────────────────────────────────────────────────────────
function switchTab(tabName) {
    // Hide all sections
    document.querySelectorAll('.tab-content').forEach(s => s.classList.add('hidden'));

    // Show selected
    const target = document.getElementById(`tab-${tabName}`);
    if (target) target.classList.remove('hidden');

    // Update sidebar nav
    document.querySelectorAll('#sidebar .nav-item[data-tab]').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.tab === tabName);
    });

    // Update mobile nav
    document.querySelectorAll('#mobileBottomNav .mobile-nav-btn').forEach(btn => {
        const isActive = btn.dataset.tab === tabName;
        btn.classList.toggle('text-teal-600', isActive);
        btn.classList.toggle('text-gray-400', !isActive);
    });

    // Lazy-load tab content
    switch (tabName) {
        case 'booking':      loadBookingTab();      break;
        case 'appointments': loadAppointments();    break;
        case 'records':      loadMedicalRecords();  break;
        case 'profile':      loadProfile();         break;
    }
}

// ═══════════════════════════════════════════════════════════════════════════════
// TAB: BOOK APPOINTMENT
// ═══════════════════════════════════════════════════════════════════════════════

async function loadBookingTab() {
    const dateInput = document.getElementById('appointmentDate');
    if (dateInput) {
        dateInput.min = todayISO();
        if (!dateInput.value) hideBookingExtras();
    }

    // Check if any active doctor exists
    hide('noDoctorAlert');
    try {
        const data = await apiRequest(`/patient/get-available-slots.php?date=${todayISO()}`);
        if (!data || !data.success) {
            showNoDoctorAlert(data?.message || 'No doctor is currently available.');
        }
    } catch (err) {
        // Silently fail — user will see error when selecting a date
    }
}

function showNoDoctorAlert(msg) {
    const container = document.getElementById('noDoctorAlert');
    if (!container) return;
    container.innerHTML = `
        <div class="rounded-xl p-4 mb-5 border border-red-200" style="background:linear-gradient(135deg,#FEF2F2,#FFF1F2);">
            <div class="flex items-start gap-3">
                <div class="w-10 h-10 rounded-xl bg-red-100 flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-user-doctor text-red-500"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="font-bold text-red-700 text-sm" style="font-family:'Outfit',sans-serif;">No Doctor Available</p>
                    <p class="text-xs text-red-500 mt-0.5">Appointment booking is temporarily unavailable. Please contact the clinic or try again later.</p>
                </div>
            </div>
        </div>`;
    container.classList.remove('hidden');
    // Disable the date picker
    const dateInput = document.getElementById('appointmentDate');
    if (dateInput) { dateInput.disabled = true; dateInput.title = 'No doctor available'; }
}

function hideBookingExtras() {
    hide('slotsLoading');
    hide('noSlotsMsg');
    hide('slotsContainer');
    hide('bookingForm');
}

async function onDateChange(dateValue) {
    if (!dateValue) { hideBookingExtras(); return; }

    // Reset
    selectedSlot = null;
    hide('noSlotsMsg');
    hide('slotsContainer');
    hide('bookingForm');
    hide('doctorCard');
    show('slotsLoading');

    try {
        const data = await apiRequest(`/patient/get-available-slots.php?date=${encodeURIComponent(dateValue)}`);

        hide('slotsLoading');

        if (!data || !data.success) {
            showError(data?.message || 'Failed to load slots');
            return;
        }

        // Show doctor card
        if (data.doctor) {
            currentDoctor = data.doctor;
            renderDoctorCard(data.doctor);
        }

        if (!data.slots || data.slots.length === 0) {
            show('noSlotsMsg');
            return;
        }

        renderSlots(data.slots, dateValue);
        show('slotsContainer');

    } catch (err) {
        hide('slotsLoading');
        showError('Could not fetch available slots.');
        console.error(err);
    }
}

function renderDoctorCard(doctor) {
    show('doctorCard');
    setTextContent('doctorName', 'Dr. ' + (doctor.full_name || doctor.name || ''));
    setTextContent('doctorSpecialization', doctor.specialization || 'Internal Medicine');
    setTextContent('doctorDepartment', doctor.department || '');
    const feeEl = document.getElementById('doctorFee');
    if (feeEl) feeEl.textContent = doctor.consultation_fee ? `₱${parseFloat(doctor.consultation_fee).toFixed(2)}` : '';

    // Avatar initials
    const initials = getInitials(doctor.full_name || doctor.name || 'DR');
    setAvatarInitials('doctorAvatar', initials, 'teal');
    if (doctor.profile_image_url || doctor.profile_image) {
        const img = new Image();
        const src = doctor.profile_image_url || ('/meditrack/uploads/' + doctor.profile_image);
        img.onload = () => {
            const el = document.getElementById('doctorAvatar');
            if (el) { el.innerHTML = ''; el.style.backgroundImage = `url('${src}')`; el.style.backgroundSize = 'cover'; }
        };
        img.src = src;
    }
}

function renderSlots(slots, dateValue) {
    const grid = document.getElementById('slotsGrid');
    if (!grid) return;
    grid.innerHTML = '';

    slots.forEach(slot => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.textContent = slot.display;
        btn.className = 'slot-btn px-2 py-2 rounded-lg text-sm font-medium text-center';

        if (slot.booked) {
            btn.classList.add('booked');
            btn.disabled = true;
            btn.title = 'Already booked';
        } else if (slot.past) {
            btn.classList.add('past');
            btn.disabled = true;
            btn.title = 'Time has passed';
        } else if (slot.available) {
            btn.classList.add('available');
            btn.onclick = () => selectSlot(slot, btn);
        } else {
            btn.classList.add('booked');
            btn.disabled = true;
        }

        grid.appendChild(btn);
    });
}

function selectSlot(slot, btnEl) {
    // Deselect previous
    document.querySelectorAll('#slotsGrid .slot-btn.selected').forEach(b => {
        b.classList.remove('selected');
        b.classList.add('available');
    });

    // Select new
    btnEl.classList.remove('available');
    btnEl.classList.add('selected');
    selectedSlot = slot;

    // Show booking form
    setTextContent('selectedSlotDisplay', slot.display);
    show('bookingForm');
    document.getElementById('reasonForVisit').value = '';
    document.getElementById('reasonForVisit').focus();
}

async function bookAppointment() {
    if (!selectedSlot) { showError('Please select a time slot.'); return; }
    if (!currentDoctor) { showError('Doctor information not available.'); return; }

    const reason = (document.getElementById('reasonForVisit')?.value || '').trim();
    if (!reason) { showError('Please enter a reason for your visit.'); return; }

    const dateValue = document.getElementById('appointmentDate')?.value;
    if (!dateValue) { showError('Please select a date.'); return; }

    const bookBtn = document.getElementById('bookBtn');
    setLoading(bookBtn, true, 'Booking...');

    try {
        const data = await apiRequest('/patient/book-appointment.php', {
            method: 'POST',
            body: JSON.stringify({
                doctor_id:        currentDoctor.id,
                appointment_date: dateValue,
                appointment_time: selectedSlot.time,
                reason_for_visit: reason
            })
        });

        setLoading(bookBtn, false, '<i class="fas fa-calendar-check"></i> Confirm Appointment');

        if (data && data.success) {
            const appt = data.appointment || {};
            let htmlContent = `<p class="text-gray-600 mb-3">Your appointment has been confirmed!</p>
                <div class="text-left bg-gray-50 rounded-lg p-3 text-sm space-y-1">
                    <p><span class="font-semibold">Number:</span> ${escHtml(appt.appointment_number || '')}</p>
                    <p><span class="font-semibold">Doctor:</span> ${escHtml(appt.doctor_name ? 'Dr. ' + appt.doctor_name : 'Doctor')}</p>
                    <p><span class="font-semibold">Date:</span> ${appt.appointment_date ? formatDate(appt.appointment_date) : ''}</p>
                    <p><span class="font-semibold">Time:</span> ${appt.appointment_time ? formatTime(appt.appointment_time) : ''}</p>
                </div>
                <div class="mt-4 text-center">
                    <p class="text-xs text-gray-500 mb-2">Your QR Code for Check-in:</p>
                    <canvas id="swalQrCanvas" style="margin:0 auto;"></canvas>
                    <p class="text-xs text-gray-400 mt-1">Show this to the doctor when you arrive</p>
                </div>`;

            await Swal.fire({
                icon:  'success',
                title: 'Appointment Booked!',
                html:  htmlContent,
                confirmButtonText: 'View My Appointments',
                confirmButtonColor: '#0891B2',
                width: '480px',
                didOpen: () => {
                    // Generate QR code in the SweetAlert dialog
                    const qrUrl = appt.qr_url || '';
                    const canvas = document.getElementById('swalQrCanvas');
                    if (canvas && qrUrl && typeof QRCode !== 'undefined') {
                        QRCode.toCanvas(canvas, qrUrl, {
                            width: 180, margin: 2,
                            color: { dark: '#083344', light: '#ffffff' }
                        });
                    } else if (canvas && appt.qr_image) {
                        // Fallback to image
                        const img = document.createElement('img');
                        img.src = appt.qr_image;
                        img.className = 'w-40 h-40 mx-auto border rounded-lg p-1';
                        img.alt = 'QR Code';
                        canvas.replaceWith(img);
                    }
                }
            });

            // Reset form
            document.getElementById('appointmentDate').value = '';
            document.getElementById('reasonForVisit').value  = '';
            selectedSlot   = null;
            currentDoctor  = null;
            hideBookingExtras();

            // Switch to appointments tab
            switchTab('appointments');
        } else {
            showError(data?.message || 'Failed to book appointment.');
        }
    } catch (err) {
        setLoading(bookBtn, false, '<i class="fas fa-calendar-check"></i> Confirm Appointment');
        showError('An error occurred while booking. Please try again.');
        console.error(err);
    }
}

// ═══════════════════════════════════════════════════════════════════════════════
// TAB: MY APPOINTMENTS
// ═══════════════════════════════════════════════════════════════════════════════

async function loadAppointments() {
    show('appointmentsLoading');
    hide('appointmentsList');
    hide('noAppointmentsMsg');

    try {
        const data = await apiRequest('/patient/get-appointments.php');
        hide('appointmentsLoading');

        if (!data || !data.success) {
            showError(data?.message || 'Failed to load appointments');
            return;
        }

        allAppointments = data.appointments || [];
        currentAppointments = [...allAppointments];
        renderAppointments(currentAppointments);

    } catch (err) {
        hide('appointmentsLoading');
        showError('Could not load appointments.');
        console.error(err);
    }
}

function filterAppointments(filter) {
    // Update filter button styles
    document.querySelectorAll('.appt-filter-btn').forEach(btn => {
        const isActive = btn.dataset.filter === filter;
        btn.classList.toggle('active-filter', isActive);
    });

    if (filter === 'all') {
        currentAppointments = [...allAppointments];
    } else {
        currentAppointments = allAppointments.filter(a => a.status === filter);
    }
    renderAppointments(currentAppointments);
}

function renderAppointments(appointments) {
    const list = document.getElementById('appointmentsList');
    if (!list) return;

    if (!appointments || appointments.length === 0) {
        list.innerHTML = '';
        hide('appointmentsList');
        show('noAppointmentsMsg');
        return;
    }

    hide('noAppointmentsMsg');
    show('appointmentsList');

    list.innerHTML = appointments.map(appt => {
        const badge   = getStatusBadge(appt.status);
        const dateStr = appt.appointment_date ? formatDate(appt.appointment_date) : 'N/A';
        const timeStr = appt.appointment_time ? formatTime(appt.appointment_time) : 'N/A';
        const doctor  = escHtml(appt.doctor_name || 'Unknown Doctor');
        const spec    = escHtml(appt.specialization || '');
        const apptNum = escHtml(appt.appointment_number || `#${appt.id}`);
        const reason  = escHtml(appt.reason_for_visit || '');

        const isScheduled = appt.status === 'scheduled' || appt.status === 'confirmed' || appt.status === 'pending';
        const isCompleted = appt.status === 'completed';
        const isCancelled = appt.status === 'cancelled';

        let actionBtns = '';
        if (isScheduled) {
            actionBtns = `
                <button onclick="showQRModal(${appt.id})"
                    class="flex items-center gap-1.5 px-3 py-1.5 bg-teal-50 hover:bg-teal-100 text-teal-700 border border-teal-200 rounded-lg text-xs font-semibold transition">
                    <i class="fas fa-qrcode"></i> View QR
                </button>
                <button onclick="cancelAppointment(${appt.id}, '${apptNum}')"
                    class="flex items-center gap-1.5 px-3 py-1.5 bg-red-50 hover:bg-red-100 text-red-600 border border-red-200 rounded-lg text-xs font-semibold transition">
                    <i class="fas fa-times-circle"></i> Cancel
                </button>`;
        } else if (isCompleted) {
            actionBtns = `
                <button onclick="switchTab('records')"
                    class="flex items-center gap-1.5 px-3 py-1.5 bg-green-50 hover:bg-green-100 text-green-700 border border-green-200 rounded-lg text-xs font-semibold transition">
                    <i class="fas fa-file-medical"></i> View Record
                </button>`;
        }

        return `
        <div class="appointment-card bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <div class="flex items-start justify-between gap-3 mb-3">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="w-10 h-10 rounded-full bg-teal-100 flex items-center justify-center flex-shrink-0">
                        <i class="fas fa-user-md text-teal-600"></i>
                    </div>
                    <div class="min-w-0">
                        <p class="font-semibold text-gray-800 truncate">Dr. ${doctor}</p>
                        ${spec ? `<p class="text-xs text-teal-600">${spec}</p>` : ''}
                    </div>
                </div>
                <span class="flex-shrink-0 ${badge.bg} ${badge.text} text-xs font-semibold px-2.5 py-1 rounded-full">${badge.label}</span>
            </div>

            <div class="grid grid-cols-2 gap-3 mb-3 text-sm">
                <div class="flex items-center gap-2 text-gray-600">
                    <i class="fas fa-hashtag text-gray-400 w-4 text-center"></i>
                    <span>${apptNum}</span>
                </div>
                <div class="flex items-center gap-2 text-gray-600">
                    <i class="fas fa-calendar text-gray-400 w-4 text-center"></i>
                    <span>${dateStr}</span>
                </div>
                <div class="flex items-center gap-2 text-gray-600">
                    <i class="fas fa-clock text-gray-400 w-4 text-center"></i>
                    <span>${timeStr}</span>
                </div>
                ${reason ? `<div class="flex items-center gap-2 text-gray-600 col-span-2">
                    <i class="fas fa-notes-medical text-gray-400 w-4 text-center"></i>
                    <span class="truncate">${reason}</span>
                </div>` : ''}
            </div>

            ${actionBtns ? `<div class="flex flex-wrap gap-2 pt-3 border-t border-gray-100">${actionBtns}</div>` : ''}
        </div>`;
    }).join('');
}

async function cancelAppointment(appointmentId, apptNumber) {
    const confirm = await Swal.fire({
        icon:  'warning',
        title: 'Cancel Appointment?',
        html:  `<p class="text-gray-600">Are you sure you want to cancel appointment <strong>${apptNumber}</strong>?</p><p class="text-sm text-gray-500 mt-2">This action cannot be undone.</p>`,
        showCancelButton:    true,
        confirmButtonText:   'Yes, Cancel It',
        cancelButtonText:    'Keep Appointment',
        confirmButtonColor:  '#ef4444',
        cancelButtonColor:   '#6b7280',
        reverseButtons:      true
    });

    if (!confirm.isConfirmed) return;

    try {
        const data = await apiRequest('/patient/cancel-appointment.php', {
            method: 'POST',
            body: JSON.stringify({ appointment_id: appointmentId })
        });

        if (data && data.success) {
            showToast('success', 'Cancelled', 'Appointment has been cancelled.');
            loadAppointments();
        } else {
            showError(data?.message || 'Failed to cancel appointment.');
        }
    } catch (err) {
        showError('An error occurred. Please try again.');
        console.error(err);
    }
}

// ═══════════════════════════════════════════════════════════════════════════════
// TAB: MEDICAL RECORDS
// ═══════════════════════════════════════════════════════════════════════════════

async function loadMedicalRecords() {
    show('recordsLoading');
    hide('recordsList');
    hide('noRecordsMsg');

    try {
        const data = await apiRequest('/patient/get-medical-records.php');
        hide('recordsLoading');

        if (!data || !data.success) {
            showError(data?.message || 'Failed to load records');
            return;
        }

        const records = data.records || [];
        renderRecords(records);

    } catch (err) {
        hide('recordsLoading');
        showError('Could not load medical records.');
        console.error(err);
    }
}

function renderRecords(records) {
    const list = document.getElementById('recordsList');
    if (!list) return;

    if (!records || records.length === 0) {
        list.innerHTML = '';
        show('noRecordsMsg');
        return;
    }

    hide('noRecordsMsg');
    show('recordsList');

    list.innerHTML = records.map((rec, idx) => {
        const dateStr    = rec.appointment_date ? formatDate(rec.appointment_date) : (rec.created_at ? formatDate(rec.created_at) : 'N/A');
        const doctor     = escHtml(rec.doctor_name || 'Unknown Doctor');
        const spec       = escHtml(rec.specialization || '');
        const complaint  = escHtml(rec.chief_complaint || '');
        const diagnosis  = escHtml(rec.diagnosis || '');
        const preview    = diagnosis.length > 100 ? diagnosis.substring(0, 100) + '...' : diagnosis;
        const prescription = escHtml(rec.prescription || '');
        const labTests   = escHtml(rec.lab_tests_ordered || '');
        const notes      = escHtml(rec.notes || '');
        const followUp   = rec.follow_up_date ? formatDate(rec.follow_up_date) : '';
        const vs         = rec.vital_signs || {};
        const bp         = escHtml(vs.bp || '');
        const temp       = escHtml(vs.temperature || vs.temp || '');
        const pulse      = escHtml(vs.heart_rate || '');
        const weight     = escHtml(vs.weight || '');

        const vitalsHtml = (bp || temp || pulse || weight) ? `
            <div class="mt-3">
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Vital Signs</p>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                    ${bp     ? `<div class="bg-red-50 rounded-lg p-2 text-center"><p class="text-xs text-gray-500">Blood Pressure</p><p class="font-semibold text-red-700 text-sm">${bp}</p></div>` : ''}
                    ${temp   ? `<div class="bg-orange-50 rounded-lg p-2 text-center"><p class="text-xs text-gray-500">Temperature</p><p class="font-semibold text-orange-700 text-sm">${temp}</p></div>` : ''}
                    ${pulse  ? `<div class="bg-pink-50 rounded-lg p-2 text-center"><p class="text-xs text-gray-500">Heart Rate</p><p class="font-semibold text-pink-700 text-sm">${pulse}</p></div>` : ''}
                    ${weight ? `<div class="bg-blue-50 rounded-lg p-2 text-center"><p class="text-xs text-gray-500">Weight</p><p class="font-semibold text-blue-700 text-sm">${weight}</p></div>` : ''}
                </div>
            </div>` : '';

        return `
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <!-- Card Header (always visible, clickable) -->
            <div class="p-5 cursor-pointer select-none hover:bg-gray-50 transition" onclick="toggleRecord('detail-${idx}', 'chevron-${idx}')">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="w-10 h-10 rounded-full bg-teal-100 flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-file-medical text-teal-600"></i>
                        </div>
                        <div class="min-w-0">
                            <p class="font-semibold text-gray-800">Dr. ${doctor}</p>
                            ${spec ? `<p class="text-xs text-teal-600">${spec}</p>` : ''}
                            <p class="text-xs text-gray-500 mt-0.5">${dateStr}</p>
                        </div>
                    </div>
                    <i id="chevron-${idx}" class="fas fa-chevron-down text-gray-400 mt-1 flex-shrink-0 transition-transform duration-300"></i>
                </div>
                ${diagnosis ? `<p class="text-sm text-gray-600 mt-3 pl-13 line-clamp-2">${preview}</p>` : ''}
            </div>

            <!-- Expanded Detail -->
            <div id="detail-${idx}" class="record-detail border-t border-gray-100">
                <div class="p-5 space-y-4">
                    ${complaint ? `
                    <div>
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Chief Complaint</p>
                        <p class="text-sm text-gray-800">${complaint}</p>
                    </div>` : ''}

                    ${vitalsHtml}

                    ${diagnosis ? `
                    <div>
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Diagnosis</p>
                        <p class="text-sm text-gray-800">${diagnosis}</p>
                    </div>` : ''}

                    ${prescription ? `
                    <div>
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Prescription</p>
                        <p class="text-sm text-gray-800 whitespace-pre-line">${prescription}</p>
                    </div>` : ''}

                    ${labTests ? `
                    <div>
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Lab Tests</p>
                        <p class="text-sm text-gray-800 whitespace-pre-line">${labTests}</p>
                    </div>` : ''}

                    ${notes ? `
                    <div>
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Notes</p>
                        <p class="text-sm text-gray-800 whitespace-pre-line">${notes}</p>
                    </div>` : ''}

                    ${followUp ? `
                    <div class="flex items-center gap-2 bg-teal-50 rounded-lg p-3">
                        <i class="fas fa-calendar-alt text-teal-600"></i>
                        <div>
                            <p class="text-xs text-teal-600 font-semibold">Follow-up Date</p>
                            <p class="text-sm font-medium text-teal-800">${followUp}</p>
                        </div>
                    </div>` : ''}

                    <div class="pt-2">
                        <button onclick="window.open('/meditrack/pages/print-record.html?id=${rec.id}', '_blank')"
                            class="flex items-center gap-2 px-4 py-2 bg-gray-700 hover:bg-gray-800 text-white text-sm font-semibold rounded-lg transition">
                            <i class="fas fa-print"></i> Print Record
                        </button>
                    </div>
                </div>
            </div>
        </div>`;
    }).join('');
}

function toggleRecord(detailId, chevronId) {
    const detail  = document.getElementById(detailId);
    const chevron = document.getElementById(chevronId);
    if (!detail) return;
    detail.classList.toggle('expanded');
    if (chevron) chevron.style.transform = detail.classList.contains('expanded') ? 'rotate(180deg)' : '';
}

// ═══════════════════════════════════════════════════════════════════════════════
// TAB: PROFILE
// ═══════════════════════════════════════════════════════════════════════════════

async function loadProfile() {
    show('profileLoading');
    hide('profileForm');

    try {
        const data = await apiRequest('/patient/get-profile.php');
        hide('profileLoading');

        if (!data || !data.success) {
            showError(data?.message || 'Failed to load profile');
            return;
        }

        const p = data.profile || data.patient || {};
        populateProfileForm(p);
        show('profileForm');

    } catch (err) {
        hide('profileLoading');
        showError('Could not load profile.');
        console.error(err);
    }
}

function populateProfileForm(p) {
    setValue('pFullName',      p.full_name          || '');
    setValue('pEmail',         p.email              || '');
    setValue('pContact',       p.contact_number     || '');
    setValue('pDob',           p.date_of_birth      || '');
    setValue('pGender',        p.gender             || '');
    setValue('pBloodGroup',    p.blood_group        || '');
    setValue('pAddress',       p.address            || '');
    setValue('pBarangay',      p.barangay           || p.address?.split(',')[0] || '');
    setValue('pCity',          p.city               || '');
    setValue('pRegion',        p.region             || '');
    setValue('pAllergies',     p.allergies          || '');
    setValue('pEmergencyName', p.emergency_contact_name   || '');
    setValue('pEmergencyNumber', p.emergency_contact_number || '');
}

async function saveProfile(event) {
    event.preventDefault();
    const form = document.getElementById('profileForm');
    const submitBtn = form.querySelector('[type="submit"]');
    setLoading(submitBtn, true, 'Saving...');

    const payload = {
        full_name:               getValue('pFullName'),
        contact_number:          getValue('pContact'),
        date_of_birth:           getValue('pDob'),
        gender:                  getValue('pGender'),
        blood_group:             getValue('pBloodGroup'),
        address:                 getValue('pAddress'),
        barangay:                getValue('pBarangay'),
        city:                    getValue('pCity'),
        region:                  getValue('pRegion'),
        allergies:               getValue('pAllergies'),
        emergency_contact_name:  getValue('pEmergencyName'),
        emergency_contact_number: getValue('pEmergencyNumber')
    };

    try {
        const data = await apiRequest('/patient/update-profile.php', {
            method: 'POST',
            body: JSON.stringify(payload)
        });

        setLoading(submitBtn, false, '<i class="fas fa-save"></i> Save Changes');

        if (data && data.success) {
            // Update name in header/sidebar if changed
            if (payload.full_name) {
                const initials = getInitials(payload.full_name);
                setTextContent('sidebarName',    payload.full_name);
                setTextContent('headerName',     payload.full_name);
                setTextContent('bannerName',     payload.full_name);
                setTextContent('headerGreeting', `Welcome, ${payload.full_name}`);
                setAvatarInitials('sidebarAvatar',  initials, 'teal');
                setAvatarInitials('headerAvatar',   initials, 'teal');
                setAvatarInitials('bannerAvatar',   initials, 'teal');
            }
            showToast('success', 'Profile Updated', 'Your profile has been saved successfully.');
        } else {
            showError(data?.message || 'Failed to save profile.');
        }
    } catch (err) {
        setLoading(submitBtn, false, '<i class="fas fa-save"></i> Save Changes');
        showError('An error occurred. Please try again.');
        console.error(err);
    }
}

async function changePassword(event) {
    event.preventDefault();

    const current  = getValue('currentPassword');
    const newPwd   = getValue('newPassword');
    const confirm  = getValue('confirmPassword');

    if (!current || !newPwd || !confirm) { showError('All fields are required.'); return; }
    if (newPwd !== confirm) { showError('New passwords do not match.'); return; }
    if (newPwd.length < 6)  { showError('New password must be at least 6 characters.'); return; }

    const form = document.getElementById('passwordForm');
    const submitBtn = form.querySelector('[type="submit"]');
    setLoading(submitBtn, true, 'Updating...');

    try {
        const data = await apiRequest('/patient/change-password.php', {
            method: 'POST',
            body: JSON.stringify({ current_password: current, new_password: newPwd, confirm_password: confirm })
        });

        setLoading(submitBtn, false, '<i class="fas fa-key"></i> Update Password');

        if (data && data.success) {
            document.getElementById('passwordForm').reset();
            showToast('success', 'Password Updated', 'Your password has been changed successfully.');
        } else {
            showError(data?.message || 'Failed to change password.');
        }
    } catch (err) {
        setLoading(submitBtn, false, '<i class="fas fa-key"></i> Update Password');
        showError('An error occurred. Please try again.');
        console.error(err);
    }
}

function togglePwd(inputId, btnEl) {
    const input = document.getElementById(inputId);
    if (!input) return;
    const isPassword = input.type === 'password';
    input.type = isPassword ? 'text' : 'password';
    const icon = btnEl.querySelector('i');
    if (icon) icon.className = isPassword ? 'fas fa-eye-slash' : 'fas fa-eye';
}

// ═══════════════════════════════════════════════════════════════════════════════
// QR MODAL — Client-side QR generation (no external API needed)
// ═══════════════════════════════════════════════════════════════════════════════

async function showQRModal(appointmentId) {
    currentQRApptId = appointmentId;

    // Find appointment in local cache
    const appt = allAppointments.find(a => a.id == appointmentId);

    // Populate modal header info
    setTextContent('qrApptNumber', appt ? `Appointment: ${appt.appointment_number || '#' + appointmentId}` : '');
    setTextContent('qrApptInfo', appt
        ? `Dr. ${appt.doctor_name || ''} \u2022 ${appt.appointment_date ? formatDate(appt.appointment_date) : ''} ${appt.appointment_time ? formatTime(appt.appointment_time) : ''}`
        : '');

    // Open modal
    const modal = document.getElementById('qrModal');
    if (modal) modal.classList.add('active');
    document.body.style.overflow = 'hidden';

    // Generate or fetch QR
    await fetchQRCode(appointmentId);
}

async function fetchQRCode(appointmentId) {
    show('qrLoading');
    hide('qrImageWrapper');

    try {
        const data = await apiRequest('/appointments/generate-qr.php', {
            method: 'POST',
            body: JSON.stringify({ appointment_id: appointmentId })
        });

        hide('qrLoading');

        if (data && data.success) {
            const qrUrl = data.qr_url || '';
            const qrImage = data.qr_image || '';

            // Try client-side QR generation first (most reliable)
            if (qrUrl && typeof QRCode !== 'undefined') {
                renderQRCanvas(qrUrl);
            } else if (qrImage) {
                renderQRImage(qrImage);
            } else {
                showError('Failed to generate QR code.');
                closeQRModal();
            }

            // Cache the URL for regeneration
            const cached = allAppointments.find(a => a.id == appointmentId);
            if (cached) {
                cached.qr_url = qrUrl;
                cached.qr_image = qrImage;
            }
        } else {
            showError(data?.message || 'Failed to generate QR code.');
            closeQRModal();
        }
    } catch (err) {
        hide('qrLoading');
        showError('Could not generate QR code.');
        closeQRModal();
        console.error(err);
    }
}

function renderQRCanvas(url) {
    hide('qrLoading');
    show('qrImageWrapper');
    const canvas = document.getElementById('qrCanvas');
    const img = document.getElementById('qrImage');
    if (canvas) canvas.classList.remove('hidden');
    if (img) img.classList.add('hidden');

    if (canvas && typeof QRCode !== 'undefined') {
        QRCode.toCanvas(canvas, url, {
            width: 260,
            margin: 2,
            color: { dark: '#083344', light: '#ffffff' }
        }, function(err) {
            if (err) {
                console.error('QR canvas error:', err);
                // Fallback to image if canvas fails
                if (img) { img.classList.remove('hidden'); canvas.classList.add('hidden'); }
            }
        });
    }
}

function renderQRImage(src) {
    hide('qrLoading');
    show('qrImageWrapper');
    const canvas = document.getElementById('qrCanvas');
    const img = document.getElementById('qrImage');
    if (canvas) canvas.classList.add('hidden');
    if (img) { img.classList.remove('hidden'); img.src = src; }
}

async function regenerateQR() {
    if (!currentQRApptId) return;
    const cached = allAppointments.find(a => a.id == currentQRApptId);
    if (cached) { cached.qr_image = null; cached.qr_url = null; }
    await fetchQRCode(currentQRApptId);
}

function downloadQR() {
    // Try canvas first (client-side generated)
    const canvas = document.getElementById('qrCanvas');
    if (canvas && !canvas.classList.contains('hidden')) {
        try {
            const link = document.createElement('a');
            link.download = `QR-Appointment-${currentQRApptId}.png`;
            link.href = canvas.toDataURL('image/png');
            link.click();
            return;
        } catch (e) { console.error('Canvas download error:', e); }
    }

    // Fallback to img element
    const img = document.getElementById('qrImage');
    if (!img || !img.src) { showError('No QR code to download.'); return; }
    const link = document.createElement('a');
    link.download = `QR-Appointment-${currentQRApptId}.png`;
    link.href = img.src;
    link.click();
}

function closeQRModal() {
    const modal = document.getElementById('qrModal');
    if (modal) modal.classList.remove('active');
    document.body.style.overflow = '';
    currentQRApptId = null;
}

// Close QR modal on backdrop click
document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('qrModal');
    if (modal) {
        modal.addEventListener('click', e => {
            if (e.target === modal) closeQRModal();
        });
    }
});

// ─── Logout ───────────────────────────────────────────────────────────────────
async function confirmLogout() {
    const result = await Swal.fire({
        icon:  'question',
        title: 'Sign Out?',
        text:  'Are you sure you want to log out?',
        showCancelButton:   true,
        confirmButtonText:  'Yes, Sign Out',
        cancelButtonText:   'Cancel',
        confirmButtonColor: '#0891B2',
        cancelButtonColor:  '#6b7280',
        reverseButtons: true
    });
    if (result.isConfirmed) logout();
}

// ═══════════════════════════════════════════════════════════════════════════════
// HELPERS
// ═══════════════════════════════════════════════════════════════════════════════

function formatDate(dateStr) {
    if (!dateStr) return 'N/A';
    try {
        const d = new Date(dateStr + (dateStr.includes('T') ? '' : 'T00:00:00'));
        return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
    } catch { return dateStr; }
}

function formatDateLong(date) {
    return date.toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' });
}

function formatTime(timeStr) {
    if (!timeStr) return 'N/A';
    try {
        const [h, m] = timeStr.split(':');
        const hour = parseInt(h, 10);
        const ampm = hour >= 12 ? 'PM' : 'AM';
        const hour12 = hour % 12 || 12;
        return `${hour12}:${m} ${ampm}`;
    } catch { return timeStr; }
}

function getStatusBadge(status) {
    const map = {
        scheduled:  { bg: 'bg-blue-100',   text: 'text-blue-700',   label: 'Scheduled'  },
        confirmed:  { bg: 'bg-blue-100',   text: 'text-blue-700',   label: 'Confirmed'  },
        pending:    { bg: 'bg-yellow-100', text: 'text-yellow-700', label: 'Pending'    },
        checked_in: { bg: 'bg-amber-100',  text: 'text-amber-700',  label: 'Checked In' },
        completed:  { bg: 'bg-green-100',  text: 'text-green-700',  label: 'Completed'  },
        cancelled:  { bg: 'bg-red-100',    text: 'text-red-700',    label: 'Cancelled'  },
        no_show:    { bg: 'bg-gray-100',   text: 'text-gray-600',   label: 'No Show'    }
    };
    return map[status] || { bg: 'bg-gray-100', text: 'text-gray-600', label: status || 'Unknown' };
}

function todayISO() {
    return new Date().toISOString().split('T')[0];
}

function getInitials(name) {
    if (!name) return 'P';
    const parts = name.trim().split(/\s+/);
    if (parts.length >= 2) return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
    return name.substring(0, 2).toUpperCase();
}

function escHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function show(id) {
    const el = document.getElementById(id);
    if (el) el.classList.remove('hidden');
}

function hide(id) {
    const el = document.getElementById(id);
    if (el) el.classList.add('hidden');
}

function setTextContent(id, text) {
    const el = document.getElementById(id);
    if (el) el.textContent = text;
}

function setValue(id, value) {
    const el = document.getElementById(id);
    if (el) el.value = value || '';
}

function getValue(id) {
    const el = document.getElementById(id);
    return el ? el.value.trim() : '';
}

function setAvatarInitials(id, initials, color) {
    const el = document.getElementById(id);
    if (!el) return;
    el.innerHTML = initials;
    el.style.backgroundImage = '';
    el.className = el.className.replace(/bg-\w+-\d+/g, '');
    el.classList.add(`bg-${color}-500`);
}

function setLoading(btn, isLoading, originalHtml) {
    if (!btn) return;
    if (isLoading) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>' + originalHtml;
    } else {
        btn.disabled = false;
        btn.innerHTML = originalHtml;
    }
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
