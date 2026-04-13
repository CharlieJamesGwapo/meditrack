/* Weekly schedule grid.
 * Usage: WeeklySchedule.mount('#container', { role, doctorIdGetter })
 */
(function () {
  const API_WEEKLY = '../api/doctor/get-weekly-appointments.php';
  const STATUS_COLORS = {
    scheduled:   'bg-blue-100 text-blue-800',
    checked_in:  'bg-amber-100 text-amber-800',
    in_progress: 'bg-purple-100 text-purple-800',
    completed:   'bg-green-100 text-green-800',
    cancelled:   'bg-gray-200 text-gray-500 line-through',
    no_show:     'bg-gray-200 text-gray-500',
  };

  function mondayOf(d) {
    const x = new Date(d);
    const day = x.getDay() || 7;
    if (day !== 1) x.setDate(x.getDate() - (day - 1));
    x.setHours(0, 0, 0, 0);
    return x;
  }
  function addDays(d, n) { const x = new Date(d); x.setDate(x.getDate() + n); return x; }
  function fmt(d) {
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const dd = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${dd}`;
  }
  function esc(s) {
    return String(s ?? '').replace(/[&<>"']/g, c => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c]));
  }

  async function fetchWeek(weekStart, doctorId) {
    const qs = new URLSearchParams({ week_start: weekStart });
    if (doctorId) qs.set('doctor_id', String(doctorId));
    const r = await fetch(`${API_WEEKLY}?${qs}`, { credentials: 'same-origin' });
    return r.json();
  }

  function render(container, weekStart, data) {
    const days = [];
    for (let i = 0; i < 7; i++) days.push(addDays(weekStart, i));
    const byDate = {};
    (data.appointments || []).forEach(a => {
      (byDate[a.appointment_date] ||= []).push(a);
    });

    const label = container.querySelector('[data-label]');
    const grid  = container.querySelector('[data-grid]');
    label.textContent = `${fmt(days[0])} – ${fmt(days[6])}`;

    grid.innerHTML = '';
    const header = document.createElement('div');
    header.className = 'grid grid-cols-7 gap-1 mb-1';
    ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'].forEach((name, i) => {
      const cell = document.createElement('div');
      cell.className = 'text-xs font-semibold text-gray-600 text-center p-1';
      cell.textContent = `${name} ${days[i].getDate()}`;
      header.appendChild(cell);
    });
    grid.appendChild(header);

    const body = document.createElement('div');
    body.className = 'grid grid-cols-7 gap-1';
    days.forEach(d => {
      const col = document.createElement('div');
      col.className = 'min-h-[160px] border border-gray-200 rounded-lg p-1 bg-gray-50';
      const list = (byDate[fmt(d)] || []).slice().sort((a, b) => a.appointment_time.localeCompare(b.appointment_time));
      list.forEach(a => {
        const chip = document.createElement('div');
        chip.className = `text-[11px] rounded px-1.5 py-1 mb-1 ${STATUS_COLORS[a.status] || 'bg-gray-100'}`;
        chip.innerHTML = `<div class="font-semibold">${esc(a.appointment_time).slice(0,5)}</div><div>${esc(a.patient_name)}</div>`;
        col.appendChild(chip);
      });
      if (list.length === 0) {
        const empty = document.createElement('div');
        empty.className = 'text-[10px] text-gray-400 text-center mt-2';
        empty.textContent = '—';
        col.appendChild(empty);
      }
      body.appendChild(col);
    });
    grid.appendChild(body);
  }

  function mount(selector, opts = {}) {
    const container = document.querySelector(selector);
    if (!container) return null;
    const doctorIdGetter = opts.doctorIdGetter || (() => null);
    let weekStart = mondayOf(new Date());

    container.innerHTML = `
      <div class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm">
        <div class="flex items-center justify-between mb-3">
          <h3 class="text-base font-bold text-gray-800"><i class="fas fa-calendar-week text-teal-600 mr-2"></i>Weekly Schedule</h3>
          <div class="flex items-center gap-2">
            <button type="button" data-prev class="px-2 py-1 text-sm rounded bg-gray-100 hover:bg-gray-200">◀</button>
            <span data-label class="text-sm font-medium text-gray-700"></span>
            <button type="button" data-this class="px-2 py-1 text-xs rounded bg-teal-50 text-teal-700 hover:bg-teal-100">This week</button>
            <button type="button" data-next class="px-2 py-1 text-sm rounded bg-gray-100 hover:bg-gray-200">▶</button>
          </div>
        </div>
        <div data-grid></div>
      </div>
    `;

    async function refresh() {
      try {
        const data = await fetchWeek(fmt(weekStart), doctorIdGetter());
        if (data && data.success) render(container, weekStart, data);
      } catch (e) { /* ignore */ }
    }

    container.querySelector('[data-prev]').addEventListener('click', () => { weekStart = addDays(weekStart, -7); refresh(); });
    container.querySelector('[data-next]').addEventListener('click', () => { weekStart = addDays(weekStart, 7); refresh(); });
    container.querySelector('[data-this]').addEventListener('click', () => { weekStart = mondayOf(new Date()); refresh(); });

    refresh();
    const api = { refresh, setWeek(d) { weekStart = mondayOf(d); refresh(); } };
    window.__weeklyScheduleRef = api;
    return api;
  }

  window.WeeklySchedule = { mount };
})();
