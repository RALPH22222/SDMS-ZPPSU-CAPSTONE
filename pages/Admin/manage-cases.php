<?php
  // Manage Cases Page
  // Features:
  // - View and filter all cases (search, status, violation type, date range, confidentiality)
  // - Resolve/close cases (updates cases.status_id to 4 [Resolved], sets resolution text & date)

  session_start();
  if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
  }
  $csrf = $_SESSION['csrf_token'];

  require_once __DIR__ . '/../../database/database.php';

  $flash = ['type' => null, 'msg' => null];
  if (!empty($_SESSION['flash']) && is_array($_SESSION['flash'])) {
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
  }

  function flash($type, $msg) { return ['type' => $type, 'msg' => $msg]; }
  function ensure_csrf() {
    if (!isset($_POST['csrf']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf'])) {
      throw new Exception('Invalid CSRF token.');
    }
  }

  // Handle POST actions
  try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      ensure_csrf();
      $action = $_POST['action'] ?? '';

      if ($action === 'resolve_case') {
        $case_id = (int)($_POST['case_id'] ?? 0);
        $resolution = trim($_POST['resolution'] ?? '') ?: null;
        if ($case_id <= 0) throw new Exception('Invalid case.');

        // Fetch current status for logging
        $cur = $pdo->prepare('SELECT status_id FROM cases WHERE id = ?');
        $cur->execute([$case_id]);
        $fromStatus = $cur->fetchColumn();
        if ($fromStatus === false) throw new Exception('Case not found.');
        if ((int)$fromStatus === 4) throw new Exception('Case is already resolved.');

        $pdo->beginTransaction();
        // Update case to Resolved (id=4)
        $upd = $pdo->prepare('UPDATE cases SET status_id = 4, resolution = ?, resolution_date = NOW() WHERE id = ?');
        $upd->execute([$resolution, $case_id]);

        // Insert case log
        $log = $pdo->prepare('INSERT INTO case_logs (id, case_id, performed_by_user_id, action, from_value, to_value, note, created_at) VALUES (NULL, ?, NULL, ?, ?, ?, ?, CURRENT_TIMESTAMP())');
        $log->execute([$case_id, 'Status Change', (string)$fromStatus, '4', $resolution]);

        $pdo->commit();
        $_SESSION['flash'] = flash('success', 'Case resolved successfully.');
        header('Location: ' . basename($_SERVER['PHP_SELF']) . '?' . http_build_query($_GET));
        exit;
      }
    }
  } catch (Exception $e) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      $_SESSION['flash'] = flash('error', $e->getMessage());
      header('Location: ' . basename($_SERVER['PHP_SELF']) . '?' . http_build_query($_GET));
      exit;
    } else {
      $flash = flash('error', $e->getMessage());
    }
  }

  // Filters (GET)
  $q = trim($_GET['q'] ?? '');
  $status_id = isset($_GET['status_id']) && $_GET['status_id'] !== '' ? (int)$_GET['status_id'] : null;
  $violation_type_id = isset($_GET['violation_type_id']) && $_GET['violation_type_id'] !== '' ? (int)$_GET['violation_type_id'] : null;
  $date_from = trim($_GET['date_from'] ?? '');
  $date_to = trim($_GET['date_to'] ?? '');
  $conf = isset($_GET['conf']) ? (int)$_GET['conf'] : -1; // -1=All, 0=No, 1=Yes

  // Pagination
  $page = max(1, (int)($_GET['page'] ?? 1));
  $pageSize = 10;
  $offset = ($page - 1) * $pageSize;

  // Reference data
  $statuses = $pdo->query('SELECT id, name FROM case_status ORDER BY id ASC')->fetchAll(PDO::FETCH_ASSOC);
  $violationTypes = $pdo->query('SELECT id, CONCAT(code, " — ", name) AS label FROM violation_types ORDER BY code ASC')->fetchAll(PDO::FETCH_ASSOC);

  // Build query
  $where = [];
  $params = [];

  if ($q !== '') {
    $where[] = '(c.case_number LIKE ? OR c.title LIKE ? OR s.first_name LIKE ? OR s.last_name LIKE ?)';
    $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%";
  }
  if ($status_id !== null) { $where[] = 'c.status_id = ?'; $params[] = $status_id; }
  if ($violation_type_id !== null) { $where[] = 'c.violation_type_id = ?'; $params[] = $violation_type_id; }
  if ($date_from !== '') { $where[] = 'DATE(c.incident_date) >= ?'; $params[] = $date_from; }
  if ($date_to !== '') { $where[] = 'DATE(c.incident_date) <= ?'; $params[] = $date_to; }
  if ($conf === 0) { $where[] = 'c.is_confidential = 0'; }
  if ($conf === 1) { $where[] = 'c.is_confidential = 1'; }

  $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

  // Count total
  $countSql = "SELECT COUNT(*)
               FROM cases c
               JOIN students s ON s.id = c.student_id
               JOIN violation_types vt ON vt.id = c.violation_type_id
               JOIN case_status st ON st.id = c.status_id
               LEFT JOIN staff rf ON rf.id = c.reported_by_staff_id
               $whereSql";
  $stmt = $pdo->prepare($countSql);
  $stmt->execute($params);
  $total = (int)$stmt->fetchColumn();
  $totalPages = max(1, (int)ceil($total / $pageSize));
  if ($page > $totalPages) { $page = $totalPages; $offset = ($page - 1) * $pageSize; }

  // Fetch page
  $sql = "SELECT
            c.id, c.case_number, c.title, c.description, c.location, c.incident_date,
            c.status_id, c.resolution, c.resolution_date, c.is_confidential,
            s.first_name AS s_fn, s.last_name AS s_ln, s.student_number,
            vt.code AS v_code, vt.name AS v_name,
            st.name AS status_name,
            rf.first_name AS r_fn, rf.last_name AS r_ln
          FROM cases c
          JOIN students s ON s.id = c.student_id
          JOIN violation_types vt ON vt.id = c.violation_type_id
          JOIN case_status st ON st.id = c.status_id
          LEFT JOIN staff rf ON rf.id = c.reported_by_staff_id
          $whereSql
          ORDER BY c.created_at DESC
          LIMIT $pageSize OFFSET $offset";
  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);
  $cases = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<?php $pageTitle = 'Manage Cases - SDMS'; include_once __DIR__ . '/../../components/admin-head.php'; ?>

<div class="md:ml-64">
  <?php include_once __DIR__ . '/../../components/admin-sidebar.php'; ?>

  <main class="p-6">
    <div class="max-w-7xl mx-auto">
      <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-semibold text-dark">Manage Cases</h1>
      </div>

      <?php if ($flash['type']): ?>
        <div class="hidden" id="flashData"
             data-type="<?php echo $flash['type']==='success'?'success':'error'; ?>"
             data-msg='<?php echo json_encode($flash["msg"]); ?>'></div>
      <?php endif; ?>

      <!-- Filters -->
      <form method="get" class="bg-white border border-gray-200 rounded-lg p-4 mb-4">
        <div class="grid grid-cols-1 md:grid-cols-6 gap-3">
          <div class="md:col-span-2">
            <label class="block text-sm font-medium mb-1">Search</label>
            <input type="text" name="q" value="<?php echo htmlspecialchars($q); ?>" placeholder="Case #, title, student" class="w-full border rounded px-3 py-2" />
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Status</label>
            <select name="status_id" class="w-full border rounded px-3 py-2">
              <option value="">All</option>
              <?php foreach ($statuses as $st): $sid=(int)$st['id']; ?>
                <option value="<?php echo $sid; ?>" <?php echo $status_id===$sid?'selected':''; ?>><?php echo htmlspecialchars($st['name']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class>
            <label class="block text-sm font-medium mb-1">Violation</label>
            <select name="violation_type_id" class="w-full border rounded px-3 py-2">
              <option value="">All</option>
              <?php foreach ($violationTypes as $vt): $vid=(int)$vt['id']; ?>
                <option value="<?php echo $vid; ?>" <?php echo $violation_type_id===$vid?'selected':''; ?>><?php echo htmlspecialchars($vt['label']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">From</label>
            <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>" class="w-full border rounded px-3 py-2" />
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">To</label>
            <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>" class="w-full border rounded px-3 py-2" />
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Confidential</label>
            <select name="conf" class="w-full border rounded px-3 py-2">
              <option value="-1" <?php echo $conf===-1?'selected':''; ?>>All</option>
              <option value="0" <?php echo $conf===0?'selected':''; ?>>No</option>
              <option value="1" <?php echo $conf===1?'selected':''; ?>>Yes</option>
            </select>
          </div>
        </div>
        <div class="mt-4 flex gap-2">
          <button class="px-4 py-2 bg-primary text-white rounded">Apply Filters</button>
          <a href="manage-cases.php" class="px-4 py-2 border rounded">Reset</a>
        </div>
      </form>

      <!-- Cases Table -->
      <div class="overflow-x-auto bg-white border border-gray-200 rounded-lg">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Case</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Student</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Violation</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Incident Date</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
              <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200">
            <?php if (!$cases): ?>
              <tr><td colspan="6" class="px-4 py-6 text-center text-gray-500">No cases found.</td></tr>
            <?php else: ?>
              <?php foreach ($cases as $c): $isResolved = ((int)$c['status_id'] === 4); ?>
              <tr class="hover:bg-gray-50">
                <td class="px-4 py-3 text-sm">
                  <div class="font-medium text-dark flex items-center gap-2">
                    <span><?php echo htmlspecialchars($c['case_number']); ?></span>
                    <?php if ((int)$c['is_confidential'] === 1): ?>
                      <span class="text-xs px-2 py-0.5 bg-red-50 text-red-600 rounded">Confidential</span>
                    <?php endif; ?>
                  </div>
                  <div class="text-xs text-gray-600"><?php echo htmlspecialchars($c['title']); ?></div>
                </td>
                <td class="px-4 py-3 text-sm">
                  <div class="text-dark font-medium"><?php echo htmlspecialchars($c['s_ln'] . ', ' . $c['s_fn']); ?></div>
                  <div class="text-xs text-gray-600">ID: <?php echo htmlspecialchars($c['student_number']); ?></div>
                </td>
                <td class="px-4 py-3 text-sm">
                  <div class="text-dark font-medium"><?php echo htmlspecialchars($c['v_name']); ?></div>
                  <div class="text-xs text-gray-600"><?php echo htmlspecialchars($c['v_code']); ?></div>
                </td>
                <td class="px-4 py-3 text-sm">
                  <?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($c['incident_date']))); ?><br>
                  <span class="text-xs text-gray-600">Reported by: <?php echo htmlspecialchars(trim(($c['r_ln'] ?? '') . ', ' . ($c['r_fn'] ?? '')) ?: '—'); ?></span>
                </td>
                <td class="px-4 py-3 text-sm">
                  <span class="inline-flex items-center px-2 py-0.5 rounded text-xs <?php echo $isResolved ? 'bg-green-50 text-green-700' : 'bg-amber-50 text-amber-700'; ?>">
                    <?php echo htmlspecialchars($c['status_name']); ?>
                  </span>
                </td>
                <td class="px-4 py-3 text-sm text-right">
                  <div class="inline-flex gap-2">
                    <!-- View details could navigate to a details page (future) -->
                    <button class="text-blue-600 hover:underline" onclick='openCaseDetails(<?php echo json_encode(["id"=>(int)$c['id']]); ?>)'>View</button>
                    <?php if (!$isResolved): ?>
                      <button class="text-green-700 hover:underline" onclick='openResolveModal(<?php echo json_encode([
                        'id' => (int)$c['id'],
                        'case_number' => $c['case_number'],
                        'title' => $c['title']
                      ], JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT); ?>)'>Resolve</button>
                    <?php else: ?>
                      <span class="text-gray-400">—</span>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div class="mt-4 flex items-center justify-between">
        <div class="text-sm text-gray-600">Showing
          <?php
            $from = $total ? ($offset + 1) : 0;
            $to = min($offset + $pageSize, $total);
            echo $from . '–' . $to . ' of ' . $total;
          ?>
        </div>
        <div class="flex gap-2">
          <?php
            $baseParams = $_GET; unset($baseParams['page']);
            $baseUrl = basename($_SERVER['PHP_SELF']) . '?' . http_build_query($baseParams);
          ?>
          <a class="px-3 py-1 border rounded <?php echo $page<=1?'pointer-events-none opacity-50':''; ?>" href="<?php echo $baseUrl . ($page>1 ? ('&page=' . ($page-1)) : ''); ?>">Prev</a>
          <span class="px-3 py-1">Page <?php echo $page; ?> / <?php echo $totalPages; ?></span>
          <a class="px-3 py-1 border rounded <?php echo $page>=$totalPages?'pointer-events-none opacity-50':''; ?>" href="<?php echo $baseUrl . ($page<$totalPages ? ('&page=' . ($page+1)) : ''); ?>">Next</a>
        </div>
      </div>
    </div>
  </main>
</div>

<!-- Resolve Modal -->
<div id="resolveModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
  <div class="bg-white w-full max-w-xl rounded-lg shadow-lg p-6">
    <div class="flex items-center justify-between mb-4">
      <h2 class="text-lg font-semibold">Resolve Case</h2>
      <button onclick="closeResolveModal()" class="text-gray-500 hover:text-gray-700"><i class="fa fa-times"></i></button>
    </div>
    <form method="post" id="resolveForm">
      <input type="hidden" name="csrf" value="<?php echo $csrf; ?>" />
      <input type="hidden" name="action" value="resolve_case" />
      <input type="hidden" name="case_id" id="resolve_case_id" value="" />

      <div class="space-y-3">
        <div>
          <div class="text-sm text-gray-600">Case: <span id="resolve_case_label" class="font-medium"></span></div>
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Resolution Notes</label>
          <textarea name="resolution" id="resolve_resolution" class="w-full border rounded px-3 py-2" rows="4" placeholder="Enter resolution details (optional)"></textarea>
        </div>
      </div>

      <div class="mt-6 flex justify-end gap-2">
        <button type="button" onclick="closeResolveModal()" class="px-4 py-2 border rounded">Cancel</button>
        <button class="px-4 py-2 bg-green-600 text-white rounded">Resolve</button>
      </div>
    </form>
  </div>
</div>

<script>
  // Flash via SweetAlert2
  document.addEventListener('DOMContentLoaded', () => {
    const flash = document.getElementById('flashData');
    if (flash && window.Swal) {
      const type = flash.getAttribute('data-type') || 'info';
      const msg = JSON.parse(flash.getAttribute('data-msg') || '""');
      Swal.fire({ icon: type, title: type === 'success' ? 'Success' : 'Error', text: msg });
    }
  });

  // Resolve Modal helpers
  const resolveModal = document.getElementById('resolveModal');
  function openResolveModal(data) {
    document.getElementById('resolve_case_id').value = data.id;
    document.getElementById('resolve_case_label').textContent = `${data.case_number} — ${data.title}`;
    document.getElementById('resolve_resolution').value = '';
    show(resolveModal);
  }
  function closeResolveModal() { hide(resolveModal); }
  function show(el) { el.classList.remove('hidden'); el.classList.add('flex'); }
  function hide(el) { el.classList.add('hidden'); el.classList.remove('flex'); }

  // Placeholder for view action (future extension)
  function openCaseDetails(data) {
    if (window.Swal) {
      Swal.fire({ title: 'Case Details', text: 'Detailed view is under construction.', icon: 'info' });
    } else {
      alert('Detailed view is under construction.');
    }
  }
</script>

<?php include_once __DIR__ . '/../../components/admin-footer.php'; ?>