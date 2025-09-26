<?php
  // Staff: Submit Disciplinary Report
  if (session_status() === PHP_SESSION_NONE) {
    session_start();
  }

  // CSRF token
  if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
  }
  $csrf = $_SESSION['csrf_token'];

  // Auth guard
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
  try {
    $students = $pdo->query("SELECT id, student_number, first_name, last_name FROM students ORDER BY last_name ASC, first_name ASC")
                   ->fetchAll(PDO::FETCH_ASSOC) ?: [];
  } catch (Throwable $e) { $students = []; }
  try {
    $violationTypes = $pdo->query("SELECT id, CONCAT(code, '  ', name) AS label FROM violation_types ORDER BY code ASC")
                          ->fetchAll(PDO::FETCH_ASSOC) ?: [];
  } catch (Throwable $e) { $violationTypes = []; }

  // Helper: generate unique case number
  function generate_case_number(PDO $pdo) {
    for ($i = 0; $i < 5; $i++) {
      $candidate = 'CASE-' . date('Ymd-His') . '-' . random_int(100, 999);
      $chk = $pdo->prepare('SELECT 1 FROM cases WHERE case_number = ? LIMIT 1');
      $chk->execute([$candidate]);
      if (!$chk->fetchColumn()) { return $candidate; }
      usleep(100000);
    }
    return 'CASE-' . date('Ymd-His') . '-' . bin2hex(random_bytes(2));
  }

  // Handle submission
  try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      ensure_csrf();

      $student_id = (int)($_POST['student_id'] ?? 0);
      $violation_type_id = (int)($_POST['violation_type_id'] ?? 0);
      $title = trim($_POST['title'] ?? '');
      $description = trim($_POST['description'] ?? '') ?: null;
      $location = trim($_POST['location'] ?? '') ?: null;
      $incident_date = trim($_POST['incident_date'] ?? ''); // datetime-local
      $is_confidential = isset($_POST['is_confidential']) ? 1 : 0;

      if ($student_id <= 0 || $violation_type_id <= 0 || $title === '' || $incident_date === '') {
        throw new Exception('Please complete all required fields.');
      }
      $incident_dt = str_replace('T', ' ', $incident_date);

      $case_number = generate_case_number($pdo);

      $pdo->beginTransaction();

      // Insert case
      $stmt = $pdo->prepare('INSERT INTO cases (case_number, student_id, reported_by_staff_id, violation_type_id, title, description, location, incident_date, status_id, is_confidential) VALUES (?,?,?,?,?,?,?,?,1,?)');
      $stmt->execute([$case_number, $student_id, ($currentStaffId ?? $currentUserId), $violation_type_id, $title, $description, $location, $incident_dt, $is_confidential]);
      $case_id = (int)$pdo->lastInsertId();

      // Evidence upload (optional, single file)
      if (!empty($_FILES['evidence']['name'])) {
        // store a relative web path used by staff pages
        $uploadDir = __DIR__ . '/../../uploads/case_evidence/';
        if (!is_dir($uploadDir)) { @mkdir($uploadDir, 0775, true); }

        $originalName = basename($_FILES['evidence']['name']);
        $ext = pathinfo($originalName, PATHINFO_EXTENSION);
        $safeName = 'case' . $case_id . '_' . time() . '_' . mt_rand(1000,9999) . ($ext ? ('.' . $ext) : '');
        $targetPath = rtrim($uploadDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $safeName;

        if (!move_uploaded_file($_FILES['evidence']['tmp_name'], $targetPath)) {
          throw new Exception('Failed to upload evidence file.');
        }

        $relativePath = '../../uploads/case_evidence/' . $safeName;

        $mime = @mime_content_type($targetPath) ?: null;
        $size = @filesize($targetPath) ?: null;

        $stmtEv = $pdo->prepare('INSERT INTO case_evidence (case_id, uploaded_by_user_id, filename, file_path, file_type, file_size) VALUES (?,?,?,?,?,?)');
        $stmtEv->execute([$case_id, $currentUserId, $originalName, $relativePath, $mime, $size]);
      }

      $pdo->commit();
      $_SESSION['flash'] = flash('success', 'Report submitted successfully. Case Number: ' . e($case_number));
      header('Location: ' . basename($_SERVER['PHP_SELF']));
      exit;
    }
  } catch (Exception $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    $_SESSION['flash'] = flash('error', $e->getMessage());
    header('Location: ' . basename($_SERVER['PHP_SELF']));
    exit;
  }

  $pageTitle = 'Submit Report - Staff - SDMS';
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
        <h1 class="text-xl md:text-2xl font-semibold">Submit Disciplinary Report</h1>
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

      <!-- Form Card -->
      <section class="bg-white border rounded-lg overflow-hidden">
        <div class="p-4 border-b">
          <h2 class="text-lg font-semibold">Report Details</h2>
          <p class="text-sm text-gray-500 mt-1">Fill in the incident details and attach evidence if available.</p>
        </div>
        <div class="p-4">
          <form method="post" enctype="multipart/form-data" class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <input type="hidden" name="csrf" value="<?php echo $csrf; ?>" />

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
              <input type="text" name="title" class="w-full border rounded px-3 py-2" placeholder="Brief title for the incident" required />
            </div>

            <div class="md:col-span-2">
              <label class="block text-sm font-medium mb-1">Description</label>
              <textarea name="description" class="w-full border rounded px-3 py-2" rows="4" placeholder="Describe the incident (optional)"></textarea>
            </div>

            <div>
              <label class="block text-sm font-medium mb-1">Location</label>
              <input type="text" name="location" class="w-full border rounded px-3 py-2" placeholder="e.g., Building A, Room 201" />
            </div>

            <div>
              <label class="block text-sm font-medium mb-1">Incident Date & Time<span class="text-rose-600">*</span></label>
              <input type="datetime-local" name="incident_date" class="w-full border rounded px-3 py-2" required />
            </div>

            <div class="md:col-span-2">
              <label class="block text-sm font-medium mb-1">Evidence (optional)</label>
              <input type="file" name="evidence" accept="image/*,application/pdf" class="w-full border rounded px-3 py-2" />
              <p class="text-xs text-gray-500 mt-1">Accepted: images or PDF. Max size depends on server configuration.</p>
            </div>

            <div class="md:col-span-2 flex items-center gap-2">
              <input type="checkbox" id="is_confidential" name="is_confidential" class="h-4 w-4" />
              <label for="is_confidential" class="text-sm">Mark as confidential</label>
            </div>

            <div class="md:col-span-2 flex justify-end gap-2 mt-2">
              <a href="manage-report.php" class="px-4 py-2 border rounded">Cancel</a>
              <button class="px-4 py-2 bg-primary text-white rounded hover:bg-primary/90">Submit Report</button>
            </div>
          </form>
        </div>
      </section>
    </div>
  </main>
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
</script>

<?php include __DIR__ . '/../../components/staff-footer.php'; ?>
