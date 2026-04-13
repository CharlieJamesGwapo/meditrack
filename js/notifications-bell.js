/* Notifications bell dropdown.
 * Usage: NotificationsBell.mount('#bell-container')
 * Polls every 30s. Requires user to be logged in (session cookie).
 */
(function () {
  const API_LIST = '../api/notifications/list.php';
  const API_READ = '../api/notifications/mark-read.php';

  function el(html) {
    const d = document.createElement('div');
    d.innerHTML = html.trim();
    return d.firstElementChild;
  }

  function escHtml(s) {
    return String(s ?? '').replace(/[&<>"']/g, c => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c]));
  }

  function fmtTime(s) {
    try { return new Date(String(s).replace(' ', 'T')).toLocaleString(); } catch { return s; }
  }

  async function fetchList() {
    const r = await fetch(API_LIST, { credentials: 'same-origin' });
    return r.json();
  }

  async function markRead(body) {
    const r = await fetch(API_READ, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify(body),
    });
    return r.json();
  }

  function render(root, data) {
    const badge = root.querySelector('[data-badge]');
    const list = root.querySelector('[data-list]');
    const unread = Number(data.unread_count || 0);
    badge.textContent = unread > 99 ? '99+' : String(unread);
    badge.style.display = unread > 0 ? 'inline-flex' : 'none';

    if (!data.notifications || data.notifications.length === 0) {
      list.innerHTML = '<div class="p-4 text-sm text-gray-500 text-center">No notifications yet</div>';
      return;
    }
    list.innerHTML = '';
    data.notifications.forEach(n => {
      const item = el(`
        <button class="w-full text-left p-3 border-b border-gray-100 hover:bg-gray-50 ${n.is_read == 0 ? 'bg-teal-50' : ''}" data-id="${n.id}">
          <div class="font-semibold text-sm text-gray-800">${escHtml(n.title)}</div>
          <div class="text-xs text-gray-600 mt-0.5">${escHtml(n.message)}</div>
          <div class="text-[10px] text-gray-400 mt-1">${escHtml(fmtTime(n.created_at))}</div>
        </button>
      `);
      item.addEventListener('click', async () => {
        await markRead({ id: Number(n.id) });
        if (n.link) { window.location.href = n.link; }
        else { refresh(root); }
      });
      list.appendChild(item);
    });
  }

  async function refresh(root) {
    try {
      const data = await fetchList();
      if (data && data.success) render(root, data);
    } catch (e) { /* ignore */ }
  }

  function mount(selector) {
    const container = document.querySelector(selector);
    if (!container) return;
    container.innerHTML = '';
    const root = el(`
      <div class="relative inline-block">
        <button type="button" data-toggle class="relative p-2 rounded-full hover:bg-gray-100 focus:outline-none" title="Notifications">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-gray-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 10-12 0v3.2a2 2 0 01-.6 1.4L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
          </svg>
          <span data-badge class="absolute -top-0.5 -right-0.5 bg-red-600 text-white text-[10px] font-bold rounded-full min-w-[18px] h-[18px] px-1 items-center justify-center" style="display:none;"></span>
        </button>
        <div data-dropdown class="hidden absolute right-0 mt-2 w-80 bg-white border border-gray-200 rounded-xl shadow-xl z-50">
          <div class="flex items-center justify-between p-3 border-b border-gray-100">
            <div class="font-semibold text-gray-800">Notifications</div>
            <button type="button" data-mark-all class="text-xs text-teal-700 hover:underline">Mark all read</button>
          </div>
          <div data-list class="max-h-96 overflow-y-auto"></div>
        </div>
      </div>
    `);
    container.appendChild(root);

    const toggleBtn = root.querySelector('[data-toggle]');
    const dropdown = root.querySelector('[data-dropdown]');
    toggleBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      dropdown.classList.toggle('hidden');
      if (!dropdown.classList.contains('hidden')) refresh(root);
    });
    document.addEventListener('click', (e) => {
      if (!root.contains(e.target)) dropdown.classList.add('hidden');
    });
    root.querySelector('[data-mark-all]').addEventListener('click', async (e) => {
      e.stopPropagation();
      await markRead({ all: true });
      refresh(root);
    });

    refresh(root);
    setInterval(() => refresh(root), 30000);
  }

  window.NotificationsBell = { mount };
})();
