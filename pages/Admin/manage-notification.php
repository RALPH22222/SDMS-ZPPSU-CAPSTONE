<?php
// Admin Notifications Management Page
session_start();

require_once __DIR__ . '/../../database/database.php';

// Auth guard: only Admin (role_id = 1)
// if (!isset($_SESSION['user_id']) || !isset($_SESSION['role_id']) || (int)$_SESSION['role_id'] !== 1) {
//   header('Location: /SDMS/pages/Auth/login.php');
//   exit;
// }

// Ensure we have an admin user id (fallback to first admin if session empty)
$adminId = (int)($_SESSION['user_id'] ?? 0);
if ($adminId === 0) {
  try {
    $stmtAdmin = $pdo->query('SELECT id FROM users WHERE role_id = 1 AND is_active = 1 ORDER BY id ASC LIMIT 1');
    $fallbackId = (int)($stmtAdmin->fetchColumn() ?: 0);
    if ($fallbackId > 0) {
      $adminId = $fallbackId;
    }
  } catch (Throwable $e) {
    // ignore
  }
}

function jsonResponse($data, $code = 200) {
  http_response_code($code);
  header('Content-Type: application/json');
  echo json_encode($data);
  exit;
}

// API Endpoints
if (isset($_GET['action'])) {
  $action = $_GET['action'];
  try {
    if ($action === 'list') {
      $onlyUnread = isset($_GET['unread']) && (int)$_GET['unread'] === 1;
      $methodId = isset($_GET['method_id']) ? (int)$_GET['method_id'] : 0;
      $params = [':uid' => $adminId];
      $where = 'WHERE n.user_id = :uid';
      if ($onlyUnread) {
        $where .= ' AND n.is_read = 0';
      }
      if ($methodId > 0) {
        $where .= ' AND n.method_id = :mid';
        $params[':mid'] = $methodId;
      }
      $sql = "
        SELECT n.id, n.message, n.is_read, n.created_at, n.method_id,
               m.name AS method_name,
               n.case_id,
               c.case_number, c.title AS case_title
        FROM notifications n
        LEFT JOIN notification_method m ON m.id = n.method_id
        LEFT JOIN cases c ON c.id = n.case_id
        $where
        ORDER BY n.created_at DESC, n.id DESC
        LIMIT 300
      ";
      $stmt = $pdo->prepare($sql);
      $stmt->execute($params);
      $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
      jsonResponse(['ok' => true, 'items' => $rows]);
    }

    if ($action === 'mark_read' && $_SERVER['REQUEST_METHOD'] === 'POST') {
      $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
      if ($id <= 0) jsonResponse(['ok' => false, 'error' => 'Invalid id'], 400);
      $upd = $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE id = :id AND user_id = :uid');
      $upd->execute([':id' => $id, ':uid' => $adminId]);
      if ($upd->rowCount() === 0) jsonResponse(['ok' => false, 'error' => 'Not found or already read'], 404);
      jsonResponse(['ok' => true]);
    }

    if ($action === 'mark_all_read' && $_SERVER['REQUEST_METHOD'] === 'POST') {
      $methodId = isset($_POST['method_id']) ? (int)$_POST['method_id'] : 0;
      $params = [':uid' => $adminId];
      $where = 'user_id = :uid AND is_read = 0';
      if ($methodId > 0) {
        $where .= ' AND method_id = :mid';
        $params[':mid'] = $methodId;
      }
      $upd = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE $where");
      $upd->execute($params);
      jsonResponse(['ok' => true, 'affected' => $upd->rowCount()]);
    }

    if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
      $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
      if ($id <= 0) jsonResponse(['ok' => false, 'error' => 'Invalid id'], 400);
      $del = $pdo->prepare('DELETE FROM notifications WHERE id = :id AND user_id = :uid');
      $del->execute([':id' => $id, ':uid' => $adminId]);
      if ($del->rowCount() === 0) jsonResponse(['ok' => false, 'error' => 'Not found'], 404);
      jsonResponse(['ok' => true]);
    }

    if ($action === 'methods') {
      $stmt = $pdo->query('SELECT id, name FROM notification_method ORDER BY id ASC');
      $methods = $stmt->fetchAll(PDO::FETCH_ASSOC);
      // Also include unread counts per method
      $cntStmt = $pdo->prepare('SELECT method_id, COUNT(*) AS unread FROM notifications WHERE user_id = :uid AND is_read = 0 GROUP BY method_id');
      $cntStmt->execute([':uid' => $adminId]);
      $counts = [];
      foreach ($cntStmt as $r) { $counts[(int)$r['method_id']] = (int)$r['unread']; }
      jsonResponse(['ok' => true, 'methods' => $methods, 'unread' => $counts]);
    }

    if ($action === 'stats') {
      $st = $pdo->prepare('SELECT SUM(CASE WHEN is_read = 0 THEN 1 ELSE 0 END) AS unread, COUNT(*) AS total FROM notifications WHERE user_id = :uid');
      $st->execute([':uid' => $adminId]);
      $row = $st->fetch(PDO::FETCH_ASSOC) ?: ['unread' => 0, 'total' => 0];
      jsonResponse(['ok' => true, 'stats' => ['unread' => (int)$row['unread'], 'total' => (int)$row['total']]]);
    }

    jsonResponse(['ok' => false, 'error' => 'Unknown action'], 400);
  } catch (Throwable $e) {
    jsonResponse(['ok' => false, 'error' => 'Server error'], 500);
  }
}

// Page render
$pageTitle = 'Notifications - Admin - SDMS';
require_once __DIR__ . '/../../components/admin-head.php';
?>

<div class="min-h-screen md:pl-64">
  <!-- Top bar for mobile with toggle -->
  <div class="md:hidden sticky top-0 z-40 bg-white border-b border-gray-200">
    <div class="h-16 flex items-center px-4">
      <button id="adminSidebarToggle" class="text-primary text-2xl mr-3">
        <i class="fa-solid fa-bars"></i>
      </button>
      <div class="text-primary font-bold">Notifications</div>
    </div>
  </div>

  <?php include __DIR__ . '/../../components/admin-sidebar.php'; ?>

  <main class="px-4 md:px-8 py-6">
    <div class="flex items-center gap-3 mb-4">
      <i class="fa-solid fa-bell text-primary text-2xl"></i>
      <h1 class="text-2xl md:text-3xl font-bold text-primary">Notification Center</h1>
      <div id="notifBadge" class="ml-2 text-xs px-2 py-1 rounded-full bg-primary text-white hidden"></div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
      <!-- Filters & Settings -->
      <aside class="md:col-span-4 space-y-4">
        <div class="bg-white border border-gray-200 rounded-lg shadow-sm">
          <div class="px-4 py-3 border-b border-gray-200 flex items-center justify-between">
            <div class="font-semibold">Filters</div>
            <button id="btnMarkAll" class="text-sm text-primary hover:underline">Mark all as read</button>
          </div>
          <div class="p-4 space-y-3">
            <label class="flex items-center gap-2">
              <input type="checkbox" id="filterUnread" class="h-4 w-4" />
              <span>Show only unread</span>
            </label>
            <div>
              <label class="block text-sm font-medium mb-1">Method</label>
              <select id="filterMethod" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary">
                <option value="0">All Methods</option>
              </select>
            </div>
          </div>
        </div>

        <div class="bg-white border border-gray-200 rounded-lg shadow-sm">
          <div class="px-4 py-3 border-b border-gray-200 font-semibold">Settings</div>
          <div class="p-4 space-y-3">
            <label class="flex items-center gap-2">
              <input type="checkbox" id="setSound" class="h-4 w-4" />
              <span>Play sound on new notification</span>
            </label>
            <label class="flex items-center gap-2">
              <input type="checkbox" id="setDesktop" class="h-4 w-4" />
              <span>Enable desktop notifications</span>
            </label>
          </div>
        </div>
      </aside>

      <!-- Notification List -->
      <section class="md:col-span-8">
        <div class="bg-white border border-gray-200 rounded-lg shadow-sm overflow-hidden">
          <div class="px-4 py-3 border-b border-gray-200 flex items-center justify-between">
            <div class="font-semibold">Notifications</div>
            <div class="text-sm text-gray" id="listInfo"></div>
          </div>
          <ul id="notifList" class="divide-y divide-gray-200 max-h-[70vh] overflow-y-auto">
            <!-- Items injected by JS -->
          </ul>
        </div>
      </section>
    </div>
  </main>
</div>

<script>
  const adminId = <?php echo (int)$adminId; ?>;

  const notifListEl = document.getElementById('notifList');
  const filterUnreadEl = document.getElementById('filterUnread');
  const filterMethodEl = document.getElementById('filterMethod');
  const btnMarkAllEl = document.getElementById('btnMarkAll');
  const listInfoEl = document.getElementById('listInfo');
  const notifBadgeEl = document.getElementById('notifBadge');
  const setSoundEl = document.getElementById('setSound');
  const setDesktopEl = document.getElementById('setDesktop');

  let pollTimer = null;
  let lastSeenIds = new Set();

  // Settings via localStorage
  function loadSettings() {
    try {
      setSoundEl.checked = localStorage.getItem('notif_sound') === '1';
      setDesktopEl.checked = localStorage.getItem('notif_desktop') === '1';
    } catch (e) {}
  }
  function saveSettings() {
    try {
      localStorage.setItem('notif_sound', setSoundEl.checked ? '1' : '0');
      localStorage.setItem('notif_desktop', setDesktopEl.checked ? '1' : '0');
    } catch (e) {}
  }
  setSoundEl.addEventListener('change', saveSettings);
  setDesktopEl.addEventListener('change', async () => {
    if (setDesktopEl.checked && Notification && Notification.permission !== 'granted') {
      try { await Notification.requestPermission(); } catch (e) {}
    }
    saveSettings();
  });

  async function api(url, opts = {}) {
    const res = await fetch(url, opts);
    try { return await res.json(); } catch (e) { return { ok: false }; }
  }

  async function loadMethods() {
    const url = new URL(window.location.href); url.search = '';
    const res = await api(url.pathname + '?action=methods');
    if (!res || !res.ok) return;
    filterMethodEl.innerHTML = '<option value="0">All Methods</option>';
    for (const m of res.methods) {
      const unread = res.unread && res.unread[m.id] ? ` (${res.unread[m.id]})` : '';
      const opt = document.createElement('option');
      opt.value = String(m.id);
      opt.textContent = `${m.name}${unread}`;
      filterMethodEl.appendChild(opt);
    }
  }

  function escapeHtml(s) {
    return String(s).replace(/[&<>"]+/g, (c) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
  }

  function playDing() {
    if (!setSoundEl.checked) return;
    try {
      const ctx = new (window.AudioContext || window.webkitAudioContext)();
      const o = ctx.createOscillator();
      const g = ctx.createGain();
      o.type = 'sine';
      o.frequency.value = 880; // A5
      o.connect(g); g.connect(ctx.destination);
      g.gain.setValueAtTime(0.0001, ctx.currentTime);
      g.gain.exponentialRampToValueAtTime(0.2, ctx.currentTime + 0.02);
      g.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + 0.25);
      o.start(); o.stop(ctx.currentTime + 0.28);
    } catch (e) {}
  }

  function desktopNotify(title, body) {
    if (!setDesktopEl.checked || !('Notification' in window)) return;
    if (Notification.permission !== 'granted') return;
    try { new Notification(title, { body }); } catch (e) {}
  }

  function renderList(items) {
    notifListEl.innerHTML = '';
    if (!items || items.length === 0) {
      notifListEl.innerHTML = '<li class="p-6 text-center text-gray">No notifications.</li>';
      listInfoEl.textContent = '';
      return;
    }
    const frag = document.createDocumentFragment();
    for (const n of items) {
      const li = document.createElement('li');
      li.className = 'p-4 flex items-start justify-between gap-3 ' + (String(n.is_read) === '1' ? 'bg-white' : 'bg-primary/5');
      li.dataset.id = n.id;
      const when = new Date((n.created_at || '').replace(' ', 'T'));
      const whenStr = isNaN(when.getTime()) ? (n.created_at || '') : when.toLocaleString();
      const leftHtml = `
        <div class="flex items-start gap-3">
          <div class="h-9 w-9 rounded-full bg-primary/10 text-primary flex items-center justify-center">
            <i class="fa-solid fa-bell"></i>
          </div>
          <div>
            <div class="font-medium text-dark">${escapeHtml(n.message || '')}</div>
            <div class="text-xs text-gray mt-1">
              ${n.method_name ? escapeHtml(n.method_name) + ' • ' : ''}
              ${n.case_number ? ('Case ' + escapeHtml(n.case_number) + (n.case_title ? ': ' + escapeHtml(n.case_title) : '')) : ''}
            </div>
            <div class="text-[11px] text-gray mt-1">${whenStr}</div>
          </div>
        </div>`;
      const btnRead = String(n.is_read) === '1'
        ? ''
        : `<button data-action="read" data-id="${n.id}" class="px-3 py-1 text-xs rounded-lg bg-primary text-white hover:opacity-95">Mark read</button>`;
      const rightHtml = `
        <div class="flex items-center gap-2">
          ${btnRead}
          <button data-action="delete" data-id="${n.id}" class="px-3 py-1 text-xs rounded-lg border border-gray-300 text-dark hover:bg-gray-50">Delete</button>
        </div>`;
      li.innerHTML = leftHtml + rightHtml;
      frag.appendChild(li);
    }
    notifListEl.appendChild(frag);
    listInfoEl.textContent = `${items.length} item(s)`;
  }

  async function refreshList() {
    const url = new URL(window.location.href); url.search = '';
    const unread = filterUnreadEl.checked ? '&unread=1' : '';
    const method = parseInt(filterMethodEl.value || '0');
    const methodPart = method > 0 ? ('&method_id=' + method) : '';
    const res = await api(url.pathname + '?action=list' + unread + methodPart);
    if (res && res.ok) {
      // Detect new items compared to lastSeenIds
      const currentIds = new Set(res.items.map(x => String(x.id)));
      let hasNew = false;
      for (const id of currentIds) { if (!lastSeenIds.has(id)) { hasNew = true; break; } }
      renderList(res.items);
      // Update badge
      await refreshStatsBadge();
      if (hasNew) {
        playDing();
        const firstNew = res.items.find(x => !lastSeenIds.has(String(x.id)));
        if (firstNew) desktopNotify('New notification', firstNew.message || '');
      }
      lastSeenIds = currentIds;
    }
  }

  async function refreshStatsBadge() {
    const url = new URL(window.location.href); url.search = '';
    const res = await api(url.pathname + '?action=stats');
    if (res && res.ok) {
      const unread = res.stats.unread || 0;
      if (unread > 0) { notifBadgeEl.textContent = unread + ' unread'; notifBadgeEl.classList.remove('hidden'); }
      else { notifBadgeEl.classList.add('hidden'); }
    }
  }

  // Events
  filterUnreadEl.addEventListener('change', refreshList);
  filterMethodEl.addEventListener('change', async () => { await loadMethods(); await refreshList(); });
  btnMarkAllEl.addEventListener('click', async () => {
    const url = new URL(window.location.href); url.search = '';
    const methodId = parseInt(filterMethodEl.value || '0');
    const form = new FormData(); if (methodId > 0) form.append('method_id', String(methodId));
    const res = await api(url.pathname + '?action=mark_all_read', { method: 'POST', body: form });
    if (res && res.ok) { await refreshList(); Swal.fire({ icon: 'success', title: 'Marked all as read', timer: 1000, showConfirmButton: false }); }
  });

  notifListEl.addEventListener('click', async (e) => {
    const btn = e.target.closest('button[data-action]');
    if (!btn) return;
    const action = btn.getAttribute('data-action');
    const id = parseInt(btn.getAttribute('data-id'));
    const url = new URL(window.location.href); url.search = '';
    if (action === 'read') {
      const form = new FormData(); form.append('id', String(id));
      const res = await api(url.pathname + '?action=mark_read', { method: 'POST', body: form });
      if (res && res.ok) { await refreshList(); }
    }
    if (action === 'delete') {
      Swal.fire({
        title: 'Delete notification?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#800000',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, delete'
      }).then(async (result) => {
        if (!result.isConfirmed) return;
        const form = new FormData(); form.append('id', String(id));
        const res = await api(url.pathname + '?action=delete', { method: 'POST', body: form });
        if (res && res.ok) { await refreshList(); Swal.fire({ icon: 'success', title: 'Deleted', timer: 900, showConfirmButton: false }); }
        else { Swal.fire({ icon: 'error', title: 'Failed', text: (res && res.error) ? res.error : 'Please try again.' }); }
      });
    }
  });

  function startPolling() {
    if (pollTimer) clearInterval(pollTimer);
    pollTimer = setInterval(async () => { await loadMethods(); await refreshList(); }, 5000);
  }

  // Initial
  loadSettings();
  loadMethods();
  refreshList();
  startPolling();
</script>

<?php require_once __DIR__ . '/../../components/admin-footer.php'; ?>