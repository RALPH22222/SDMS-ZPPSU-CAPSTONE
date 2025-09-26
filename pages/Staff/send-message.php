<?php
// Staff Messenger Page
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

require_once __DIR__ . '/../../database/database.php';

// Auth guard: only Staff/Teacher (role_id = 5)
// if (!isset($_SESSION['user_id']) || !isset($_SESSION['role_id']) || (int)$_SESSION['role_id'] !== 5) {
//   header('Location: /SDMS/pages/Auth/login.php');
//   exit;
// }

// Ensure we have a valid staffId even if the guard above is disabled for testing
$staffId = (int)($_SESSION['user_id'] ?? 0);
if ($staffId === 0) {
  try {
    $stmtStaff = $pdo->query('SELECT id FROM users WHERE role_id = 5 AND is_active = 1 ORDER BY id ASC LIMIT 1');
    $fallbackId = (int)($stmtStaff->fetchColumn() ?: 0);
    if ($fallbackId > 0) {
      $staffId = $fallbackId;
    }
  } catch (Throwable $e) {
    // ignore; will remain 0
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
    if ($action === 'list_users') {
      // Staff can message Admins (1), Parents (4), Students (3). Exclude self.
      $q = trim($_GET['q'] ?? '');
      $params = [
        ':me' => $staffId,
      ];
      // Allow messaging any active user (exclude self)
      $where = 'WHERE u.is_active = 1 AND u.id != :me';
      if ($q !== '') {
        $where .= ' AND (u.username LIKE :q OR u.email LIKE :q)';
        $params[':q'] = "%$q%";
      }
      $sql = "
        SELECT u.id, u.username, u.email,
               COALESCE(SUM(CASE WHEN m.is_read = 0 THEN 1 ELSE 0 END), 0) AS unread
        FROM users u
        LEFT JOIN messages m
          ON m.sender_user_id = u.id
         AND m.recipient_user_id = :me
        $where
        GROUP BY u.id, u.username, u.email
        ORDER BY unread DESC, u.username ASC
        LIMIT 200
      ";
      $stmt = $pdo->prepare($sql);
      $stmt->execute($params);
      $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
      jsonResponse(['ok' => true, 'users' => $users]);
    }

    if ($action === 'thread') {
      $withId = isset($_GET['with']) ? (int)$_GET['with'] : 0;
      if ($withId <= 0 || $withId === $staffId) {
        jsonResponse(['ok' => false, 'error' => 'Invalid user.'], 400);
      }

      // Ensure the counterpart exists and is active (allow any role)
      try {
        $chk = $pdo->prepare('SELECT id FROM users WHERE id = :id AND is_active = 1 LIMIT 1');
        $chk->execute([':id' => $withId]);
        if (!$chk->fetchColumn()) {
          jsonResponse(['ok' => false, 'error' => 'User not found.'], 404);
        }
      } catch (Throwable $e) {
        // best-effort; continue
      }

      // Mark messages to staff as read
      $upd = $pdo->prepare('UPDATE messages SET is_read = 1 WHERE sender_user_id = :with AND recipient_user_id = :me AND is_read = 0');
      $upd->execute([':with' => $withId, ':me' => $staffId]);

      // Fetch last 200 messages between staff and selected user
      $stmt = $pdo->prepare('
        SELECT m.id, m.sender_user_id, m.recipient_user_id, m.body, m.created_at,
               su.username AS sender_name, ru.username AS recipient_name,
               (m.sender_user_id = :me AND m.created_at >= (NOW() - INTERVAL 15 MINUTE)) AS can_edit
        FROM messages m
        JOIN users su ON su.id = m.sender_user_id
        JOIN users ru ON ru.id = m.recipient_user_id
        WHERE (m.sender_user_id = :me AND m.recipient_user_id = :with)
           OR (m.sender_user_id = :with AND m.recipient_user_id = :me)
        ORDER BY m.created_at ASC, m.id ASC
        LIMIT 200
      ');
      $stmt->execute([':me' => $staffId, ':with' => $withId]);
      $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

      // Also return basic user info
      $uStmt = $pdo->prepare('SELECT id, username, email FROM users WHERE id = :id LIMIT 1');
      $uStmt->execute([':id' => $withId]);
      $withUser = $uStmt->fetch(PDO::FETCH_ASSOC);

      jsonResponse(['ok' => true, 'with' => $withUser, 'messages' => $messages]);
    }

    if ($action === 'send' && $_SERVER['REQUEST_METHOD'] === 'POST') {
      $to = isset($_POST['to']) ? (int)$_POST['to'] : 0;
      $body = trim($_POST['body'] ?? '');
      if ($to <= 0 || $to === $staffId) {
        jsonResponse(['ok' => false, 'error' => 'Invalid recipient.'], 400);
      }
      if ($body === '') {
        jsonResponse(['ok' => false, 'error' => 'Message cannot be empty.'], 400);
      }

      // Ensure recipient exists and is active (allow any role)
      $chk = $pdo->prepare('SELECT id FROM users WHERE id = :id AND is_active = 1 LIMIT 1');
      $chk->execute([':id' => $to]);
      if (!$chk->fetchColumn()) {
        jsonResponse(['ok' => false, 'error' => 'Recipient not found.'], 404);
      }

      $ins = $pdo->prepare('INSERT INTO messages (case_id, sender_user_id, recipient_user_id, subject, body, is_read, created_at) VALUES (NULL, :sender, :recipient, NULL, :body, 0, NOW())');
      $ins->execute([':sender' => $staffId, ':recipient' => $to, ':body' => $body]);

      jsonResponse(['ok' => true, 'id' => (int)$pdo->lastInsertId()]);
    }

    if ($action === 'edit' && $_SERVER['REQUEST_METHOD'] === 'POST') {
      $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
      $body = trim($_POST['body'] ?? '');
      if ($id <= 0) {
        jsonResponse(['ok' => false, 'error' => 'Invalid message.'], 400);
      }
      if ($body === '') {
        jsonResponse(['ok' => false, 'error' => 'Message cannot be empty.'], 400);
      }
      $upd = $pdo->prepare('UPDATE messages SET body = :body WHERE id = :id AND sender_user_id = :me AND created_at >= (NOW() - INTERVAL 15 MINUTE)');
      $upd->execute([':body' => $body, ':id' => $id, ':me' => $staffId]);
      if ($upd->rowCount() === 0) {
        jsonResponse(['ok' => false, 'error' => 'Cannot edit (time window passed or not your message).'], 403);
      }
      jsonResponse(['ok' => true]);
    }

    if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
      $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
      if ($id <= 0) {
        jsonResponse(['ok' => false, 'error' => 'Invalid message.'], 400);
      }
      $del = $pdo->prepare('DELETE FROM messages WHERE id = :id AND sender_user_id = :me');
      $del->execute([':id' => $id, ':me' => $staffId]);
      if ($del->rowCount() === 0) {
        jsonResponse(['ok' => false, 'error' => 'Cannot delete (not your message or already removed).'], 403);
      }
      jsonResponse(['ok' => true]);
    }

    jsonResponse(['ok' => false, 'error' => 'Unknown action.'], 400);
  } catch (Throwable $e) {
    jsonResponse(['ok' => false, 'error' => 'Server error.'], 500);
  }
}

$pageTitle = 'Send Message - Staff - SDMS';
require_once __DIR__ . '/../../components/staff-head.php';
?>

<div class="min-h-screen flex">
  <?php include __DIR__ . '/../../components/staff-sidebar.php'; ?>

  <main class="flex-1 md:ml-64">
    <!-- Top bar -->
    <div class="h-16 flex items-center px-4 border-b border-gray-200 bg-white sticky top-0 z-40">
      <button id="staffSidebarToggle" class="md:hidden text-primary text-xl mr-3" aria-label="Toggle Sidebar">
        <i class="fa-solid fa-bars"></i>
      </button>
      <h1 class="text-xl font-bold text-primary">Messenger</h1>
      <div class="ml-auto text-sm text-gray">
        Logged in as: <span class="font-medium text-dark"><?php echo htmlspecialchars($_SESSION['email'] ?? $_SESSION['username'] ?? 'Staff'); ?></span>
      </div>
    </div>

    <div class="p-4 md:p-6">
      <div class="bg-white border border-gray-200 rounded-lg overflow-hidden shadow-sm grid grid-cols-1 md:grid-cols-12 min-h-[70vh]">
        <!-- Left: user list -->
        <aside class="md:col-span-4 border-b md:border-b-0 md:border-r border-gray-200">
          <div class="p-3 border-b border-gray-200">
            <div class="relative">
              <input id="searchUsers" type="text" class="w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" placeholder="Search users by name or email..." />
              <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray"></i>
            </div>
          </div>
          <ul id="usersList" class="divide-y divide-gray-200 max-h-[70vh] md:max-h-[calc(70vh-60px)] overflow-y-auto">
            <!-- Users loaded via JS -->
          </ul>
        </aside>

        <!-- Right: chat area -->
        <section class="md:col-span-8 flex flex-col">
          <!-- Header -->
          <div id="chatHeader" class="p-4 border-b border-gray-200 flex items-center gap-3">
            <div class="h-10 w-10 rounded-full bg-primary/10 flex items-center justify-center text-primary">
              <i class="fa-solid fa-user"></i>
            </div>
            <div>
              <div id="chatWithName" class="font-semibold">Select a user to start</div>
              <div id="chatWithEmail" class="text-sm text-gray"></div>
            </div>
          </div>

          <!-- Messages -->
          <div class="relative flex-1">
            <div id="chatMessages" class="absolute inset-0 p-4 overflow-y-auto space-y-3 bg-gray-50">
              <div class="text-center text-gray">No conversation selected.</div>
            </div>
            <!-- Scroll to bottom button -->
            <button id="scrollBottomBtn" class="hidden absolute right-4 bottom-4 z-10 px-3 py-2 rounded-full bg-primary text-white shadow hover:opacity-95">
              <i class="fa-solid fa-arrow-down mr-1"></i>
              <span class="text-sm">New messages</span>
            </button>
          </div>

          <!-- Composer -->
          <form id="composer" class="p-3 border-t border-gray-200 flex gap-2">
            <input id="composerInput" type="text" class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" placeholder="Type your message..." disabled />
            <button id="composerSend" type="submit" class="px-4 py-2 bg-primary text-white rounded-lg disabled:opacity-50" disabled>
              <i class="fa-solid fa-paper-plane mr-1"></i>Send
            </button>
          </form>
        </section>
      </div>
    </div>
  </main>
</div>

<!-- Edit Message Modal -->
<div id="editModal" class="fixed inset-0 z-50 hidden">
  <div class="absolute inset-0 bg-black/40"></div>
  <div class="absolute inset-0 flex items-center justify-center p-4">
    <div class="w-full max-w-lg bg-white rounded-lg shadow-xl border border-gray-200">
      <div class="px-4 py-3 border-b border-gray-200 flex items-center justify-between">
        <h3 class="font-semibold text-dark">Edit Message</h3>
        <button id="editModalClose" class="text-gray hover:text-dark"><i class="fa-solid fa-xmark"></i></button>
      </div>
      <div class="p-4">
        <label for="editMessageInput" class="block text-sm font-medium mb-1">Message</label>
        <textarea id="editMessageInput" rows="4" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" placeholder="Update your message..."></textarea>
        <input type="hidden" id="editMessageId" />
      </div>
      <div class="px-4 py-3 border-t border-gray-200 flex items-center justify-end gap-2">
        <button id="editModalCancel" class="px-4 py-2 rounded-lg border border-gray-300 text-dark hover:bg-gray-50">Cancel</button>
        <button id="editModalSave" class="px-4 py-2 rounded-lg bg-primary text-white hover:opacity-95">Save</button>
      </div>
    </div>
  </div>
  <div class="sr-only" aria-live="polite">Edit message modal</div>
  <div class="sr-only" role="dialog" aria-modal="true"></div>
</div>

<script>
  const staffId = <?php echo (int)$staffId; ?>;
  let selectedUserId = null;
  let pollTimer = null;

  const usersListEl = document.getElementById('usersList');
  const searchUsersEl = document.getElementById('searchUsers');
  const chatHeaderNameEl = document.getElementById('chatWithName');
  const chatHeaderEmailEl = document.getElementById('chatWithEmail');
  const chatMessagesEl = document.getElementById('chatMessages');
  const scrollBottomBtn = document.getElementById('scrollBottomBtn');
  const composerEl = document.getElementById('composer');
  const composerInputEl = document.getElementById('composerInput');
  const composerSendEl = document.getElementById('composerSend');
  // Modal elements
  const editModalEl = document.getElementById('editModal');
  const editMessageInputEl = document.getElementById('editMessageInput');
  const editMessageIdEl = document.getElementById('editMessageId');
  const editModalCloseEl = document.getElementById('editModalClose');
  const editModalCancelEl = document.getElementById('editModalCancel');
  const editModalSaveEl = document.getElementById('editModalSave');

  async function api(url, opts = {}) {
    const res = await fetch(url, opts);
    try {
      return await res.json();
    } catch (e) {
      return { ok: false, error: 'Invalid response' };
    }
  }

  function renderUsers(users) {
    usersListEl.innerHTML = '';
    if (!users || users.length === 0) {
      usersListEl.innerHTML = '<li class="p-4 text-gray">No users found.</li>';
      return;
    }
    for (const u of users) {
      const li = document.createElement('li');
      li.className = 'p-3 hover:bg-gray-50 cursor-pointer flex items-center justify-between ' + (selectedUserId === parseInt(u.id) ? 'bg-primary/5' : '');
      li.dataset.id = u.id;

      const hasUnread = u.unread && parseInt(u.unread) > 0;
      li.innerHTML = `
        <div class="flex items-center gap-3">
          <div class="h-9 w-9 rounded-full bg-primary/10 text-primary flex items-center justify-center">
            <i class="fa-solid fa-user"></i>
          </div>
          <div>
            <div class="${hasUnread ? 'font-extrabold text-dark' : 'font-medium'}">${escapeHtml(u.username || 'User ' + u.id)}</div>
            <div class="text-xs text-gray">${escapeHtml(u.email || '')}</div>
          </div>
        </div>
        ${hasUnread ? `<span class="ml-2 inline-flex items-center justify-center min-w-6 h-6 px-2 text-xs bg-primary text-white rounded-full">${u.unread}</span>` : ''}
      `;
      li.addEventListener('click', () => selectUser(parseInt(u.id)));
      usersListEl.appendChild(li);
    }
  }

  function renderMessages(payload) {
    const wasAtBottom = isAtBottom();
    const { with: w, messages } = payload;
    chatHeaderNameEl.textContent = w && w.username ? w.username : 'User ' + (w ? w.id : '');
    chatHeaderEmailEl.textContent = w && w.email ? w.email : '';

    chatMessagesEl.innerHTML = '';
    if (!messages || messages.length === 0) {
      chatMessagesEl.innerHTML = '<div class="text-center text-gray">No messages yet. Start the conversation.</div>';
      requestAnimationFrame(() => scrollToBottom(false));
      return;
    }
    for (const m of messages) {
      const mine = parseInt(m.sender_user_id) === staffId;
      const wrap = document.createElement('div');
      wrap.className = 'flex ' + (mine ? 'justify-end' : 'justify-start');

      const bubble = document.createElement('div');
      bubble.className = 'max-w-[75%] px-3 py-2 rounded-lg shadow text-sm ' + (mine ? 'bg-primary text-white rounded-br-none' : 'bg-white border border-gray-200 text-dark rounded-bl-none');
      const body = escapeHtml(m.body);
      const dt = new Date(m.created_at.replace(' ', 'T'));
      const timeStr = isNaN(dt.getTime()) ? m.created_at : dt.toLocaleString();
      const canEdit = String(m.can_edit) === '1';
      bubble.innerHTML = `
        <div class="message-text">${body}</div>
        <div class="mt-1 flex items-center justify-between gap-3">
          <div class="text-[10px] opacity-75">${timeStr}</div>
          ${mine ? `<div class="flex items-center gap-2 text-[11px]">
            ${canEdit ? `<button class="underline decoration-dotted" data-action="edit" data-id="${m.id}">Edit</button>` : ''}
            <button class="underline decoration-dotted" data-action="delete" data-id="${m.id}">Delete</button>
          </div>` : ''}
        </div>`;

      wrap.appendChild(bubble);
      chatMessagesEl.appendChild(wrap);
    }
    if (wasAtBottom) {
      requestAnimationFrame(() => scrollToBottom(false));
    } else {
      showScrollButton(true);
    }
  }

  function escapeHtml(s) {
    return String(s).replace(/[&<>\"]/g, (c) => ({'&':'&amp;','<':'&lt;','>':'&gt;','\"':'&quot;'}[c] || c));
  }

  async function loadUsers() {
    const q = searchUsersEl.value.trim();
    const url = new URL(window.location.href);
    url.search = '';
    const apiUrl = url.pathname + '?action=list_users' + (q ? '&q=' + encodeURIComponent(q) : '');
    const res = await api(apiUrl);
    if (res.ok) renderUsers(res.users);
  }

  async function loadThread(userId) {
    const url = new URL(window.location.href);
    url.search = '';
    const apiUrl = url.pathname + '?action=thread&with=' + encodeURIComponent(userId);
    const res = await api(apiUrl);
    if (res.ok) renderMessages(res);
  }

  function selectUser(userId) {
    selectedUserId = userId;
    composerInputEl.disabled = false;
    composerSendEl.disabled = false;
    loadThread(userId);
    // Refresh users list immediately so unread styling/badges clear right away
    loadUsers();
  }

  async function sendMessage() {
    const body = composerInputEl.value.trim();
    if (!selectedUserId || body === '') return;
    const form = new FormData();
    form.append('to', String(selectedUserId));
    form.append('body', body);
    const url = new URL(window.location.href);
    url.search = '';
    const res = await api(url.pathname + '?action=send', { method: 'POST', body: form });
    if (res && res.ok) {
      composerInputEl.value = '';
      await loadThread(selectedUserId);
      await loadUsers();
      scrollToBottom(true);
    } else if (res && res.error) {
      alert('Send failed: ' + res.error);
    } else {
      alert('Send failed.');
    }
  }

  // Scroll helpers
  function isAtBottom(threshold = 24) {
    return (chatMessagesEl.scrollHeight - chatMessagesEl.scrollTop - chatMessagesEl.clientHeight) <= threshold;
  }
  function scrollToBottom(smooth = true) {
    chatMessagesEl.scrollTo({ top: chatMessagesEl.scrollHeight, behavior: smooth ? 'smooth' : 'auto' });
    showScrollButton(false);
  }
  function showScrollButton(show) {
    if (!scrollBottomBtn) return;
    if (show) scrollBottomBtn.classList.remove('hidden');
    else scrollBottomBtn.classList.add('hidden');
  }
  chatMessagesEl.addEventListener('scroll', () => {
    showScrollButton(!isAtBottom());
  });
  if (scrollBottomBtn) {
    scrollBottomBtn.addEventListener('click', () => scrollToBottom(true));
  }

  // Delegate edit/delete clicks
  chatMessagesEl.addEventListener('click', async (e) => {
    const btn = e.target.closest('button[data-action]');
    if (!btn) return;
    const id = parseInt(btn.getAttribute('data-id'));
    const action = btn.getAttribute('data-action');
    const url = new URL(window.location.href);
    url.search = '';
    if (action === 'edit') {
      const bodyEl = btn.closest('div').parentElement.querySelector('.message-text');
      const currentText = bodyEl ? bodyEl.textContent : '';
      openEditModal(id, currentText);
    }
    if (action === 'delete') {
      Swal.fire({
        title: 'Delete this message?',
        text: 'This action will permanently remove the message.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#800000',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, delete',
      }).then(async (result) => {
        if (!result.isConfirmed) return;
        const form = new FormData();
        form.append('id', String(id));
        const res = await api(url.pathname + '?action=delete', { method: 'POST', body: form });
        if (res && res.ok) {
          await loadThread(selectedUserId);
          Swal.fire({ icon: 'success', title: 'Deleted', timer: 1200, showConfirmButton: false });
        } else {
          Swal.fire({ icon: 'error', title: 'Delete failed', text: (res && res.error) ? res.error : 'Please try again.' });
        }
      });
    }
  });

  function openEditModal(id, currentText) {
    editMessageIdEl.value = String(id);
    editMessageInputEl.value = currentText;
    editModalEl.classList.remove('hidden');
  }
  function closeEditModal() {
    editModalEl.classList.add('hidden');
    editMessageIdEl.value = '';
    editMessageInputEl.value = '';
  }
  editModalCloseEl.addEventListener('click', closeEditModal);
  editModalCancelEl.addEventListener('click', closeEditModal);
  editModalEl.addEventListener('click', (e) => {
    if (e.target === editModalEl) closeEditModal();
  });
  editModalSaveEl.addEventListener('click', async () => {
    const id = parseInt(editMessageIdEl.value || '0');
    const body = editMessageInputEl.value.trim();
    if (!id || body === '') {
      Swal.fire({ icon: 'warning', title: 'Nothing to save', text: 'Message cannot be empty.' });
      return;
    }
    const url = new URL(window.location.href);
    url.search = '';
    const form = new FormData();
    form.append('id', String(id));
    form.append('body', body);
    const res = await api(url.pathname + '?action=edit', { method: 'POST', body: form });
    if (res && res.ok) {
      closeEditModal();
      await loadThread(selectedUserId);
      Swal.fire({ icon: 'success', title: 'Updated', timer: 1000, showConfirmButton: false });
    } else {
      Swal.fire({ icon: 'error', title: 'Edit failed', text: (res && res.error) ? res.error : 'Please try again.' });
    }
  });

  // Use only the submit handler to avoid double-send
  composerEl.addEventListener('submit', (e) => { e.preventDefault(); sendMessage(); });
  searchUsersEl.addEventListener('input', () => { loadUsers(); });

  function startPolling() {
    if (pollTimer) clearInterval(pollTimer);
    pollTimer = setInterval(async () => {
      await loadUsers();
      if (selectedUserId) await loadThread(selectedUserId);
    }, 5000);
  }

  // Initial load
  loadUsers();
  startPolling();
</script>

<?php require_once __DIR__ . '/../../components/staff-footer.php'; ?>