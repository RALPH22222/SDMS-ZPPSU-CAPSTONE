<?php
  // Staff: Manage Reports (create, view, edit, delete cases reported by this staff)
  if (session_status() === PHP_SESSION_NONE) {
    session_start();
  }
  if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
  }
  $csrf = $_SESSION['csrf_token'];

  // Basic auth guard (adjust based on your auth implementation)
  if (!isset($_SESSION['user_id'])) {
    header('Location: ../Auth/login.php');
    exit;
  }

  require_once __DIR__ . '/../../database/database.php';

  $currentUserId = (int)($_SESSION['user_id'] ?? 0);

  // Map to staff.id if available (so we can match cases.reported_by_staff_id)
  $currentStaffId = null;
  try {
    $stf = $pdo->prepare('SELECT id FROM staff WHERE user_id = ? LIMIT 1');
    $stf->execute([$currentUserId]);
    $row = $stf->fetch(PDO::FETCH_ASSOC);
    if ($row && !empty($row['id'])) { $currentStaffId = (int)$row['id']; }
  } catch (Throwable $e) { /* ignore */ }

  // Helper: sanitize output
  function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

  // Flash helper
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

  // Reference lists
  $students = [];
  $violationTypes = [];
  $caseStatuses = [];
  try {
    $students = $pdo->query("SELECT id, student_number, first_name, last_name FROM students ORDER BY last_name ASC, first_name ASC")->fetchAll(PDO::FETCH_ASSOC) ?: [];
  } catch (Throwable $e) { $students = []; }
  try {
    $violationTypes = $pdo->query("SELECT id, CONCAT(code, '  ', name) AS label FROM violation_types ORDER BY code ASC")->fetchAll(PDO::FETCH_ASSOC) ?: [];
  } catch (Throwable $e) { $violationTypes = []; }
  try {
    $caseStatuses = $pdo->query("SELECT id, name FROM case_status ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC) ?: [];
  } catch (Throwable $e) { $caseStatuses = []; }

  // Helper: generate unique case number
  function generate_case_number(PDO $pdo) {
    for ($i = 0; $i < 5; $i++) {
      $candidate = 'CASE-' . date('Ymd-His') . '-' . random_int(100, 999);
      $chk = $pdo->prepare('SELECT 1 FROM cases WHERE case_number = ? LIMIT 1');
      $chk->execute([$candidate]);
      if (!$chk->fetchColumn()) { return $candidate; }
      usleep(100000); // 100ms to avoid collisions on fast loops
    }
    // Fallback
    return 'CASE-' . date('Ymd-His') . '-' . bin2hex(random_bytes(2));
  }

  // POST actions: create, update, delete
  try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      ensure_csrf();
      $action = $_POST['action'] ?? '';

      if ($action === 'create_report') {
        $student_id = (int)($_POST['student_id'] ?? 0);
        $violation_type_id = (int)($_POST['violation_type_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '') ?: null;
        $location = trim($_POST['location'] ?? '') ?: null;
        $incident_date = trim($_POST['incident_date'] ?? ''); // expects datetime-local (YYYY-MM-DDTHH:MM)
        $is_confidential = isset($_POST['is_confidential']) ? 1 : 0;

        if ($student_id <= 0 || $violation_type_id <= 0 || $title === '' || $incident_date === '') {
          throw new Exception('Please complete all required fields.');
        }
        // Normalize datetime-local to MySQL format
        $incident_dt = str_replace('T', ' ', $incident_date);

        $case_number = generate_case_number($pdo);

        $stmt = $pdo->prepare('INSERT INTO cases (id, case_number, student_id, reported_by_staff_id, violation_type_id, title, description, location, incident_date, status_id, resolution, resolution_date, is_confidential, created_at, updated_at) VALUES (NULL, ?, ?, ?, ?, ?, ?, ?, ?, 1, NULL, NULL, ?, CURRENT_TIMESTAMP(), NULL)');
        $stmt->execute([$case_number, $student_id, ($currentStaffId ?? $currentUserId), $violation_type_id, $title, $description, $location, $incident_dt, $is_confidential]);

        $_SESSION['flash'] = flash('success', 'Report created successfully.');
        header('Location: ' . basename($_SERVER['PHP_SELF']) . '?' . http_build_query($_GET));
        exit;
      }

      if ($action === 'update_report') {
        $case_id = (int)($_POST['case_id'] ?? 0);
        $student_id = (int)($_POST['student_id'] ?? 0);
        $violation_type_id = (int)($_POST['violation_type_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '') ?: null;
        $location = trim($_POST['location'] ?? '') ?: null;
        $incident_date = trim($_POST['incident_date'] ?? '');
        $status_id = isset($_POST['status_id']) && ctype_digit((string)$_POST['status_id']) ? (int)$_POST['status_id'] : null;
        $is_confidential = isset($_POST['is_confidential']) ? 1 : 0;

        if ($case_id <= 0) { throw new Exception('Invalid case.'); }
        if ($student_id <= 0 || $violation_type_id <= 0 || $title === '' || $incident_date === '') {
          throw new Exception('Please complete all required fields.');
        }

        // Verify ownership
        $own = $pdo->prepare('SELECT reported_by_staff_id FROM cases WHERE id = ?');
        $own->execute([$case_id]);
        $rep = $own->fetchColumn();
        if ($rep === false) { throw new Exception('Case not found.'); }
        if (!in_array((int)$rep, [ (int)($currentStaffId ?? 0), $currentUserId ], true)) {
          throw new Exception('You are not allowed to edit this case.');
        }

        $incident_dt = str_replace('T', ' ', $incident_date);

        $sql = 'UPDATE cases SET student_id = ?, violation_type_id = ?, title = ?, description = ?, location = ?, incident_date = ?, is_confidential = ?';
        $params = [$student_id, $violation_type_id, $title, $description, $location, $incident_dt, $is_confidential];
        if ($status_id !== null) { $sql .= ', status_id = ?'; $params[] = $status_id; }
        $sql .= ' WHERE id = ?';
        $params[] = $case_id;

        $upd = $pdo->prepare($sql);
        $upd->execute($params);

        $_SESSION['flash'] = flash('success', 'Report updated successfully.');
        header('Location: ' . basename($_SERVER['PHP_SELF']) . '?' . http_build_query($_GET));
        exit;
      }

      if ($action === 'delete_report') {
        $case_id = (int)($_POST['case_id'] ?? 0);
        if ($case_id <= 0) { throw new Exception('Invalid case.'); }

        // Verify ownership
        $own = $pdo->prepare('SELECT reported_by_staff_id FROM cases WHERE id = ?');
        $own->execute([$case_id]);
        $rep = $own->fetchColumn();
        if ($rep === false) { throw new Exception('Case not found.'); }
        if (!in_array((int)$rep, [ (int)($currentStaffId ?? 0), $currentUserId ], true)) {
          throw new Exception('You are not allowed to delete this case.');
        }

        $del = $pdo->prepare('DELETE FROM cases WHERE id = ?');
        $del->execute([$case_id]);

        $_SESSION['flash'] = flash('success', 'Report deleted successfully.');
        header('Location: ' . basename($_SERVER['PHP_SELF']) . '?' . http_build_query($_GET));
        exit;
      }
    }
  } catch (Exception $e) {
    $_SESSION['flash'] = flash('error', $e->getMessage());
    header('Location: ' . basename($_SERVER['PHP_SELF']) . '?' . http_build_query($_GET));
    exit;
  }

  // Filters (GET)
  $q = trim($_GET['q'] ?? '');
  $statusFilter = trim($_GET['status'] ?? '');
  $date_from = trim($_GET['date_from'] ?? '');
  $date_to = trim($_GET['date_to'] ?? '');

  // Build base query: show cases reported by this staff (by staff.id or by user_id for legacy)
  $params = [];
  $where = [];
  $where[] = '(c.reported_by_staff_id = ? OR c.reported_by_staff_id = ?)';
  $params[] = $currentStaffId;
  $params[] = $currentUserId;

  if ($q !== '') {
    $where[] = '(c.case_number LIKE ? OR c.title LIKE ? OR s.first_name LIKE ? OR s.last_name LIKE ? OR vt.name LIKE ?)';
    $like = "%" . $q . "%";
    array_push($params, $like, $like, $like, $like, $like);
  }
  if ($statusFilter !== '' && ctype_digit($statusFilter)) {
    $where[] = 'c.status_id = ?';
    $params[] = (int)$statusFilter;
  }
  if ($date_from !== '') { $where[] = 'DATE(c.incident_date) >= ?'; $params[] = $date_from; }
  if ($date_to !== '') { $where[] = 'DATE(c.incident_date) <= ?'; $params[] = $date_to; }

  $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

  // Fetch cases
  $cases = [];
  try {
    $sql = "SELECT 
              c.id,
              c.case_number,
              c.title,
              c.description,
              c.location,
              c.incident_date,
              c.status_id,
              c.is_confidential,
              s.id AS student_id,
              s.first_name, s.last_name, s.student_number,
              vt.id AS violation_type_id, vt.name AS violation_name, vt.code AS violation_code
            FROM cases c
            JOIN students s ON s.id = c.student_id
            JOIN violation_types vt ON vt.id = c.violation_type_id
            $whereSql
            ORDER BY c.created_at DESC, c.id DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $cases = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
  } catch (Throwable $e) {
    $cases = [];
  }

  $pageTitle = 'Manage Reports - Staff - SDMS';
  include __DIR__ . '/../../components/staff-head.php';
?>

<div class="min-h-screen flex">
  <?php include __DIR__ . '/../../components/staff-sidebar.php'; ?>

  <main class="flex-1 ml-0 md:ml-64">
    <!-- Top bar -->
    <div class="h-16 flex items-center justify-between px-4 md:px-8 border-b border-gray-200 bg-white sticky top-0 z-40">
      <div class="flex items-center gap-3">
        <button id="staffSidebarToggle" class="md:hidden text-primary text-xl" aria-label="Toggle Sidebar">
          <i class="fa-solid fa-bars"></i>
        </button>
        <h1 class="text-xl md:text-2xl font-semibold">Manage Reports</h1>
      </div>
      <div class="text-sm text-gray-500">
        Logged in as: <span class="font-medium text-dark"><?php echo e($_SESSION['email'] ?? $_SESSION['username'] ?? 'Staff'); ?></span>
      </div>
    </div>

    <div class="p-4 md:p-8 space-y-6">
      <!-- Flash -->
      <?php if ($flash['type']): ?>
        <div class="hidden" id="flashData"
             data-type="<?php echo $flash['type']==='success'?'success':'error'; ?>"
             data-msg='<?php echo json_encode($flash["msg"]); ?>'></div>
      <?php endif; ?>

      <!-- Filters & New -->
      <div class="bg-white border rounded-lg p-4">
        <form method="get" class="grid grid-cols-1 md:grid-cols-5 gap-3">
          <div class="md:col-span-2">
            <label class="block text-sm text-gray-600 mb-1">Search</label>
            <input type="text" name="q" value="<?php echo e($q); ?>" placeholder="Case #, title, student, violation" class="w-full border-gray-300 rounded-lg focus:ring-primary focus:border-primary" />
          </div>
          <div>
            <label class="block text-sm text-gray-600 mb-1">Status</label>
            <select name="status" class="w-full border-gray-300 rounded-lg focus:ring-primary focus:border-primary">
              <option value="">All</option>
              <?php foreach ($caseStatuses as $st): $sid=(int)$st['id']; ?>
                <option value="<?php echo $sid; ?>" <?php echo ($statusFilter !== '' && (int)$statusFilter===$sid)?'selected':''; ?>><?php echo e($st['name']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="block text-sm text-gray-600 mb-1">From</label>
            <input type="date" name="date_from" value="<?php echo e($date_from); ?>" class="w-full border-gray-300 rounded-lg focus:ring-primary focus:border-primary" />
          </div>
          <div>
            <label class="block text-sm text-gray-600 mb-1">To</label>
            <input type="date" name="date_to" value="<?php echo e($date_to); ?>" class="w-full border-gray-300 rounded-lg focus:ring-primary focus:border-primary" />
          </div>
          <div class="md:col-span-5 flex items-end justify-between gap-2">
            <div>
              <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90">
                <i class="fa-solid fa-magnifying-glass"></i>
                <span>Apply</span>
              </button>
              <a href="manage-report.php" class="ml-2 inline-flex items-center gap-2 px-4 py-2 border rounded-lg hover:bg-gray-50">
                <i class="fa-solid fa-rotate-left"></i>
                <span>Reset</span>
              </a>
            </div>
            <div>
              <button type="button" class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700" onclick="openCreateModal()">
                <i class="fa-solid fa-plus"></i>
                <span>New Report</span>
              </button>
            </div>
          </div>
        </form>
      </div>

      <!-- Reports List -->
      <section class="bg-white border rounded-lg overflow-hidden">
        <div class="flex items-center justify-between p-4 border-b">
          <h2 class="text-lg font-semibold">My Reports (<?php echo count($cases); ?>)</h2>
        </div>

        <?php if (empty($cases)): ?>
          <div class="p-6 text-gray-500">No reports found.</div>
        <?php else: ?>
          <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
              <thead class="bg-gray-50 text-gray-600">
                <tr>
                  <th class="text-left font-medium px-4 py-3">Case</th>
                  <th class="text-left font-medium px-4 py-3">Student</th>
                  <th class="text-left font-medium px-4 py-3">Violation</th>
                  <th class="text-left font-medium px-4 py-3">Incident</th>
                  <th class="text-left font-medium px-4 py-3">Status</th>
                  <th class="text-right font-medium px-4 py-3">Actions</th>
                </tr>
              </thead>
              <tbody class="divide-y">
                <?php foreach ($cases as $c): ?>
                  <?php
                    $caseLabel = '#' . ($c['case_number']);
                    $studentName = trim(($c['last_name'] ?? '') . ', ' . ($c['first_name'] ?? ''));
                    $violation = ($c['violation_name'] ?? '-') . ' (' . ($c['violation_code'] ?? '') . ')';
                    $incident = $c['incident_date'] ? date('Y-m-d H:i', strtotime($c['incident_date'])) : '-';
                    $statusId = (int)($c['status_id'] ?? 1);
                    $statusName = '';
                    foreach ($caseStatuses as $st) { if ((int)$st['id'] === $statusId) { $statusName = $st['name']; break; } }
                  ?>
                  <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 align-top">
                      <div class="font-medium"><?php echo e($caseLabel); ?></div>
                      <div class="text-xs text-gray-500 truncate max-w-xs"><?php echo e($c['title'] ?? ''); ?></div>
                      <?php if ((int)($c['is_confidential'] ?? 0) === 1): ?>
                        <div class="text-[10px] inline-block mt-1 px-1.5 py-0.5 bg-rose-50 text-rose-700 rounded">Confidential</div>
                      <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 align-top">
                      <div class="font-medium"><?php echo e($studentName); ?></div>
                      <div class="text-xs text-gray-500">ID: <?php echo e($c['student_number'] ?? ''); ?></div>
                    </td>
                    <td class="px-4 py-3 align-top"><?php echo e($violation); ?></td>
                    <td class="px-4 py-3 align-top whitespace-nowrap"><?php echo e($incident); ?></td>
                    <td class="px-4 py-3 align-top">
                      <span class="inline-block text-xs px-2.5 py-1 bg-gray-50 text-gray-700 border border-gray-200 rounded-full"><?php echo e($statusName ?: ('Status #'.$statusId)); ?></span>
                    </td>
                    <td class="px-4 py-3 align-top text-right">
                      <div class="inline-flex gap-3">
                        <button class="text-blue-600 hover:underline" onclick='openEditModal(<?php echo json_encode([
                          'id' => (int)$c['id'],
                          'student_id' => (int)$c['student_id'],
                          'violation_type_id' => (int)$c['violation_type_id'],
                          'title' => (string)($c['title'] ?? ''),
                          'description' => (string)($c['description'] ?? ''),
                          'location' => (string)($c['location'] ?? ''),
                          'incident_date' => $c['incident_date'] ? date('Y-m-d\TH:i', strtotime($c['incident_date'])) : '',
                          'status_id' => (int)$c['status_id'],
                          'is_confidential' => (int)$c['is_confidential']
                        ], JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT); ?>)'>Edit</button>
                        <form method="post" class="inline" onsubmit="return confirmDelete();">
                          <input type="hidden" name="csrf" value="<?php echo $csrf; ?>" />
                          <input type="hidden" name="action" value="delete_report" />
                          <input type="hidden" name="case_id" value="<?php echo (int)$c['id']; ?>" />
                          <button class="text-rose-600 hover:underline">Delete</button>
                        </form>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </section>
    </div>
  </main>
</div>

<!-- Create Modal -->
<div id="createModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
  <div class="bg-white w-full max-w-2xl rounded-lg shadow-lg p-6">
    <div class="flex items-center justify-between mb-4">
      <h2 class="text-lg font-semibold">New Report</h2>
      <button onclick="closeCreateModal()" class="text-gray-500 hover:text-gray-700"><i class="fa fa-times"></i></button>
    </div>
    <form method="post">
      <input type="hidden" name="csrf" value="<?php echo $csrf; ?>" />
      <input type="hidden" name="action" value="create_report" />

      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium mb-1">Student<span class="text-rose-600">*</span></label>
          <select name="student_id" class="w-full border rounded px-3 py-2" required>
            <option value="">Select a student</option>
            <?php foreach ($students as $s): ?>
              <option value="<?php echo (int)$s['id']; ?>"><?php echo e(trim(($s['last_name'] ?? '') . ', ' . ($s['first_name'] ?? '')) . '  ' . ($s['student_number'] ?? '')); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Violation<span class="text-rose-600">*</span></label>
          <select name="violation_type_id" class="w-full border rounded px-3 py-2" required>
            <option value="">Select a violation</option>
            <?php foreach ($violationTypes as $vt): ?>
              <option value="<?php echo (int)$vt['id']; ?>"><?php echo e($vt['label']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="md:col-span-2">
          <label class="block text-sm font-medium mb-1">Title<span class="text-rose-600">*</span></label>
          <input type="text" name="title" class="w-full border rounded px-3 py-2" required />
        </div>
        <div class="md:col-span-2">
          <label class="block text-sm font-medium mb-1">Description</label>
          <textarea name="description" class="w-full border rounded px-3 py-2" rows="4" placeholder="Describe the incident (optional)"></textarea>
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Location</label>
          <input type="text" name="location" class="w-full border rounded px-3 py-2" />
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Incident Date & Time<span class="text-rose-600">*</span></label>
          <input type="datetime-local" name="incident_date" class="w-full border rounded px-3 py-2" required />
        </div>
        <div class="md:col-span-2 flex items-center gap-2">
          <input type="checkbox" id="create_is_confidential" name="is_confidential" class="h-4 w-4" />
          <label for="create_is_confidential" class="text-sm">Mark as confidential</label>
        </div>
      </div>

      <div class="mt-6 flex justify-end gap-2">
        <button type="button" onclick="closeCreateModal()" class="px-4 py-2 border rounded">Cancel</button>
        <button class="px-4 py-2 bg-emerald-600 text-white rounded">Create</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Modal -->
<div id="editModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
  <div class="bg-white w-full max-w-2xl rounded-lg shadow-lg p-6">
    <div class="flex items-center justify-between mb-4">
      <h2 class="text-lg font-semibold">Edit Report</h2>
      <button onclick="closeEditModal()" class="text-gray-500 hover:text-gray-700"><i class="fa fa-times"></i></button>
    </div>
    <form method="post">
      <input type="hidden" name="csrf" value="<?php echo $csrf; ?>" />
      <input type="hidden" name="action" value="update_report" />
      <input type="hidden" name="case_id" id="edit_case_id" value="" />

      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium mb-1">Student<span class="text-rose-600">*</span></label>
          <select name="student_id" id="edit_student_id" class="w-full border rounded px-3 py-2" required>
            <option value="">Select a student</option>
            <?php foreach ($students as $s): ?>
              <option value="<?php echo (int)$s['id']; ?>"><?php echo e(trim(($s['last_name'] ?? '') . ', ' . ($s['first_name'] ?? '')) . '  ' . ($s['student_number'] ?? '')); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Violation<span class="text-rose-600">*</span></label>
          <select name="violation_type_id" id="edit_violation_type_id" class="w-full border rounded px-3 py-2" required>
            <option value="">Select a violation</option>
            <?php foreach ($violationTypes as $vt): ?>
              <option value="<?php echo (int)$vt['id']; ?>"><?php echo e($vt['label']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="md:col-span-2">
          <label class="block text-sm font-medium mb-1">Title<span class="text-rose-600">*</span></label>
          <input type="text" name="title" id="edit_title" class="w-full border rounded px-3 py-2" required />
        </div>
        <div class="md:col-span-2">
          <label class="block text-sm font-medium mb-1">Description</label>
          <textarea name="description" id="edit_description" class="w-full border rounded px-3 py-2" rows="4"></textarea>
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Location</label>
          <input type="text" name="location" id="edit_location" class="w-full border rounded px-3 py-2" />
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Incident Date & Time<span class="text-rose-600">*</span></label>
          <input type="datetime-local" name="incident_date" id="edit_incident_date" class="w-full border rounded px-3 py-2" required />
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Status</label>
          <select name="status_id" id="edit_status_id" class="w-full border rounded px-3 py-2">
            <option value="">Unchanged</option>
            <?php foreach ($caseStatuses as $st): ?>
              <option value="<?php echo (int)$st['id']; ?>"><?php echo e($st['name']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="md:col-span-2 flex items-center gap-2">
          <input type="checkbox" id="edit_is_confidential" name="is_confidential" class="h-4 w-4" />
          <label for="edit_is_confidential" class="text-sm">Mark as confidential</label>
        </div>
      </div>

      <div class="mt-6 flex justify-end gap-2">
        <button type="button" onclick="closeEditModal()" class="px-4 py-2 border rounded">Cancel</button>
        <button class="px-4 py-2 bg-blue-600 text-white rounded">Save Changes</button>
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

  // Modal helpers
  const createModal = document.getElementById('createModal');
  const editModal = document.getElementById('editModal');
  function show(el){ el.classList.remove('hidden'); el.classList.add('flex'); }
  function hide(el){ el.classList.add('hidden'); el.classList.remove('flex'); }
  function openCreateModal(){ show(createModal); }
  function closeCreateModal(){ hide(createModal); }
  function openEditModal(data){
    document.getElementById('edit_case_id').value = data.id;
    document.getElementById('edit_student_id').value = data.student_id;
    document.getElementById('edit_violation_type_id').value = data.violation_type_id;
    document.getElementById('edit_title').value = data.title || '';
    document.getElementById('edit_description').value = data.description || '';
    document.getElementById('edit_location').value = data.location || '';
    document.getElementById('edit_incident_date').value = data.incident_date || '';
    document.getElementById('edit_status_id').value = data.status_id || '';
    document.getElementById('edit_is_confidential').checked = !!data.is_confidential;
    show(editModal);
  }
  function closeEditModal(){ hide(editModal); }
  function confirmDelete(){
    if (window.Swal) {
      event.preventDefault();
      const form = event.target;
      Swal.fire({
        title: 'Delete report?',
        text: 'This action cannot be undone.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Delete',
        confirmButtonColor: '#dc2626'
      }).then(res => { if (res.isConfirmed) form.submit(); });
      return false;
    }
    return confirm('Delete this report? This action cannot be undone.');
  }
</script>

<?php include __DIR__ . '/../../components/staff-footer.php'; ?>