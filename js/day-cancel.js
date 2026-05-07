/* Day-cancel modal — "Mark day unavailable".
 * Usage: DayCancel.init({ role: 'doctor' | 'admin' })
 * Injects a button and modal into the page. Button is placed inside #day-cancel-mount if present,
 * otherwise floats top-right of <main>.
 *
 * Admin role includes a doctor selector populated from /api/admin/doctors.php (or similar).
 */
(function () {
  const API_CANCEL_DAY = '../api/doctor/cancel-day.php';
  const API_WEEKLY     = '../api/doctor/get-weekly-appointments.php';
  const API_DOCTORS    = '../api/admin/get-doctors.php';

  function el(html) {
    const d = document.createElement('div');
    d.innerHTML = html.trim();
    return d.firstElementChild;
  }

  function buildModal(role) {
    const doctorSelectHtml = role === 'admin'
      ? `<label class="block text-sm font-medium text-gray-700 mb-1">Doctor</label>
         <select id="dc-doctor" class="w-full border border-gray-300 rounded-lg px-3 py-2 mb-3"><option value="">Loading…</option></select>`
      : '';
    return el(`
      <div id="day-cancel-modal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-xl max-w-md w-full p-6">
          <h3 class="text-lg font-bold text-gray-800 mb-4">Mark day unavailable</h3>
          ${doctorSelectHtml}
          <label class="block text-sm font-medium text-gray-700 mb-1">Date</label>
          <input id="dc-date" type="date" class="w-full border border-gray-300 rounded-lg px-3 py-2 mb-3">
          <label class="block text-sm font-medium text-gray-700 mb-1">Reason</label>
          <textarea id="dc-reason" rows="3" class="w-full border border-gray-300 rounded-lg px-3 py-2 mb-3" placeholder="e.g., Medical emergency, sick leave"></textarea>
          <div id="dc-preview" class="text-sm text-gray-600 mb-4"></div>
          <div class="flex gap-2 justify-end">
            <button type="button" id="dc-close" class="px-3 py-2 text-sm rounded-lg bg-gray-200 hover:bg-gray-300">Close</button>
            <button type="button" id="dc-confirm" class="px-3 py-2 text-sm rounded-lg bg-red-600 text-white hover:bg-red-700">Cancel all on this date</button>
          </div>
        </div>
      </div>
    `);
  }

  function buildTriggerButton() {
    return el(`
      <button type="button" id="btn-mark-day-unavailable"
        class="flex items-center gap-2 px-3 py-2 rounded-lg bg-red-50 hover:bg-red-100 border border-red-200 text-red-700 text-sm font-semibold">
        <i class="fas fa-calendar-times"></i>
        <span>Mark day unavailable</span>
      </button>
    `);
  }

  async function loadDoctors(select) {
    try {
      const r = await fetch(API_DOCTORS, { credentials: 'same-origin' });
      const data = await r.json();
      const list = (data && (data.doctors || data.data)) || [];
      if (!Array.isArray(list) || list.length === 0) {
        select.innerHTML = '<option value="">No doctors</option>';
        return;
      }
      select.innerHTML = list.map(d => `<option value="${d.id}">${d.full_name || d.name || ('Doctor #' + d.id)}</option>`).join('');
    } catch (e) {
      select.innerHTML = '<option value="">Failed to load</option>';
    }
  }

  async function updatePreview(modal, role) {
    const date = modal.querySelector('#dc-date').value;
    const preview = modal.querySelector('#dc-preview');
    if (!date) { preview.textContent = ''; return; }
    const qs = new URLSearchParams({ week_start: date });
    if (role === 'admin') {
      const did = modal.querySelector('#dc-doctor').value;
      if (did) qs.set('doctor_id', did);
    }
    try {
      const r = await fetch(`${API_WEEKLY}?${qs}`, { credentials: 'same-origin' });
      const data = await r.json();
      const count = (data.appointments || []).filter(a => a.appointment_date === date && a.status === 'scheduled').length;
      preview.textContent = `${count} scheduled appointment(s) on ${date} will be cancelled and patients notified.`;
    } catch (e) {
      preview.textContent = '';
    }
  }

  function init(opts = {}) {
    const role = opts.role || 'doctor';

    // Mount button
    let mountPoint = document.getElementById('day-cancel-mount');
    const btn = buildTriggerButton();
    if (mountPoint) {
      mountPoint.appendChild(btn);
    } else {
      btn.classList.add('fixed', 'top-20', 'right-4', 'z-40', 'shadow-lg');
      document.body.appendChild(btn);
    }

    // Mount modal
    const modal = buildModal(role);
    document.body.appendChild(modal);

    const dcDate    = modal.querySelector('#dc-date');
    const dcReason  = modal.querySelector('#dc-reason');
    const dcClose   = modal.querySelector('#dc-close');
    const dcConfirm = modal.querySelector('#dc-confirm');
    const dcDoctor  = modal.querySelector('#dc-doctor');

    btn.addEventListener('click', async () => {
      dcDate.value = new Date().toISOString().slice(0, 10);
      dcReason.value = '';
      modal.querySelector('#dc-preview').textContent = '';
      modal.classList.remove('hidden');
      if (role === 'admin' && dcDoctor && dcDoctor.options.length <= 1) {
        await loadDoctors(dcDoctor);
      }
      updatePreview(modal, role);
    });
    dcClose.addEventListener('click', () => modal.classList.add('hidden'));
    dcDate.addEventListener('change', () => updatePreview(modal, role));
    if (dcDoctor) dcDoctor.addEventListener('change', () => updatePreview(modal, role));

    dcConfirm.addEventListener('click', async () => {
      const date = dcDate.value;
      const reason = dcReason.value.trim();
      if (!date || !reason) { alert('Date and reason are required.'); return; }
      if (!confirm('This will cancel all scheduled appointments on this date. Continue?')) return;

      const body = { date, reason };
      if (role === 'admin' && dcDoctor && dcDoctor.value) body.doctor_id = Number(dcDoctor.value);

      try {
        const r = await fetch(API_CANCEL_DAY, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          credentials: 'same-origin',
          body: JSON.stringify(body),
        });
        const data = await r.json();
        if (data.success) {
          alert(`Cancelled ${data.cancelled_count} appointment(s). Patients notified.`);
          modal.classList.add('hidden');
          if (typeof window.loadAppointments === 'function') window.loadAppointments();
          if (window.WeeklySchedule && window.__weeklyScheduleRef) window.__weeklyScheduleRef.refresh();
        } else {
          alert(data.message || 'Failed to cancel day.');
        }
      } catch (e) {
        alert('Request failed.');
      }
    });
  }

  window.DayCancel = { init };
})();
