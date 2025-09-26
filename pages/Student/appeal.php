<?php
// Student Appeal/Clarification Submission Page
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

require_once __DIR__ . '/../../database/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role_id']) || (int)$_SESSION['role_id'] !== 3) {
  header('Location: /SDMS/pages/Auth/login.php');
  exit;
}
$studentUserId = (int)($_SESSION['user_id'] ?? 0);
$student = null;

// Initialize flash messages
$errors = [];
$success = null;
if (!empty($_SESSION['flash_success'])) {
  $success = $_SESSION['flash_success'];
  unset($_SESSION['flash_success']);
}

try {
  $stmt = $pdo->prepare('SELECT id, student_number, first_name, middle_name, last_name, suffix FROM students WHERE user_id = ? LIMIT 1');
  $stmt->execute([$studentUserId]);
  $student = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
} catch (Throwable $e) {
  $student = null;
}
$cases = [];
if ($student) {
  try {
    $sql = "SELECT c.id, c.case_number, c.title, c.status_id, c.incident_date, 
                   vt.name AS violation_name, cs.name AS status_name, c.reported_by_staff_id
            FROM cases c
            JOIN violation_types vt ON vt.id = c.violation_type_id
            JOIN case_status cs ON cs.id = c.status_id
            WHERE c.student_id = :sid
            AND (c.status_id IS NULL OR c.status_id <> 6)
            AND NOT EXISTS (
              SELECT 1 FROM appeals a 
              WHERE a.case_id = c.id AND a.student_id = :sid
            )
            ORDER BY c.created_at DESC";
    $st = $pdo->prepare($sql);
    $st->execute([':sid' => (int)$student['id']]);
    $cases = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
  } catch (Throwable $e) {
    $cases = [];
  }
}

$errors = [];
// Flash success support (PRG)
$success = null;
if (!empty($_SESSION['flash_success'])) {
  $success = $_SESSION['flash_success'];
  unset($_SESSION['flash_success']);
}

// Handle submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $student) {
  $caseId = (int)($_POST['case_id'] ?? 0);
  $appealText = trim($_POST['reason'] ?? '');

  // Basic validations
  if ($caseId <= 0) {
    $errors[] = 'Please select a case.';
  }
  if ($appealText === '') {
    $errors[] = 'Please provide details for your appeal/clarification.';
  }

  // Verify that the selected case belongs to the student
  $caseRow = null;
  if (empty($errors)) {
    try {
      $q = $pdo->prepare("
        SELECT c.*, vt.name AS violation_name 
        FROM cases c 
        JOIN violation_types vt ON vt.id = c.violation_type_id 
        WHERE c.id = ? AND c.student_id = ? 
        LIMIT 1
      ");
      $q->execute([$caseId, (int)$student['id']]);
      $caseRow = $q->fetch(PDO::FETCH_ASSOC) ?: null;
      
      if (!$caseRow) {
        $errors[] = 'Invalid case selection.';
      }
    } catch (Throwable $e) {
      $errors[] = 'Unable to validate selected case at this time.';
    }
  }

  // Process optional file upload
  $evidenceId = null;
  $uploadedFileMeta = null;
  if (empty($errors) && isset($_FILES['attachment']) && $_FILES['attachment']['error'] !== UPLOAD_ERR_NO_FILE) {
    $f = $_FILES['attachment'];
    if ($f['error'] !== UPLOAD_ERR_OK) {
      $errors[] = 'File upload failed. Please try again.';
    } else {
      // File validation
      $allowed = ['image/jpeg','image/png','application/pdf','image/jpg'];
      $mime = mime_content_type($f['tmp_name']);
      if (!in_array($mime, $allowed, true)) {
        $errors[] = 'Only JPG, PNG, or PDF files are allowed.';
      }
      if ($f['size'] > 10 * 1024 * 1024) { // 10MB
        $errors[] = 'File is too large. Max size is 10 MB.';
      }

      if (empty($errors)) {
        $uploadsDir = __DIR__ . '/../../uploads/appeals';
        if (!is_dir($uploadsDir)) {
          @mkdir($uploadsDir, 0775, true);
        }
        $ext = pathinfo($f['name'], PATHINFO_EXTENSION);
        $safeBase = preg_replace('/[^A-Za-z0-9-_]/', '_', pathinfo($f['name'], PATHINFO_FILENAME));
        $newName = 'case' . $caseId . '_' . time() . '_' . $safeBase . '.' . $ext;
        $destPath = $uploadsDir . '/' . $newName;
        
        if (!@move_uploaded_file($f['tmp_name'], $destPath)) {
          $errors[] = 'Unable to save uploaded file.';
        } else {
          // insert into case_evidence
          try {
            $relPath = '/SDMS/uploads/appeals/' . $newName;
            $ins = $pdo->prepare('INSERT INTO case_evidence (case_id, uploaded_by_user_id, filename, file_path, file_type, file_size) VALUES (:cid, :uid, :fn, :fp, :ft, :fs)');
            $ins->execute([
              ':cid' => $caseId,
              ':uid' => $studentUserId ?: null,
              ':fn'  => $newName,
              ':fp'  => $relPath,
              ':ft'  => $mime,
              ':fs'  => (int)$f['size'],
            ]);
            $evidenceId = (int)$pdo->lastInsertId();
            $uploadedFileMeta = ['filename' => $newName, 'path' => $relPath];
          } catch (Throwable $e) {
            $errors[] = 'Failed to record the uploaded file.';
          }
        }
      }
    }
  }

  // Insert appeal, update case status to Appealed (5), log, and notify
  if (empty($errors) && $caseRow) {
    try {
      $pdo->beginTransaction();

      // Check if appeal already exists
      $checkStmt = $pdo->prepare('SELECT id FROM appeals WHERE case_id = ? AND student_id = ?');
      $checkStmt->execute([$caseId, (int)$student['id']]);
      if ($checkStmt->fetch()) {
        throw new Exception('Appeal already submitted for this case.');
      }

      // Insert into appeals
      $insA = $pdo->prepare('INSERT INTO appeals (case_id, student_id, appeal_text, attachment_evidence_id, status_id, submitted_at) VALUES (:cid, :sid, :txt, :evid, :status, NOW())');
      $insA->execute([
        ':cid' => $caseId,
        ':sid' => (int)$student['id'],
        ':txt' => $appealText,
        ':evid'=> $evidenceId ?: null,
        ':status' => 1, // Submitted/Pending
      ]);
      $appealId = (int)$pdo->lastInsertId();

      // Update case status -> Appealed (5) if not already
      if ((int)$caseRow['status_id'] !== 5) {
        $upd = $pdo->prepare('UPDATE cases SET status_id = 5 WHERE id = :cid');
        $upd->execute([':cid' => $caseId]);
      }

      // Add case log
      $log = $pdo->prepare('INSERT INTO case_logs (case_id, performed_by_user_id, action, from_value, to_value, note) VALUES (:cid, :uid, :action, :fromv, :tov, :note)');
      $log->execute([
        ':cid' => $caseId,
        ':uid' => $studentUserId ?: null,
        ':action' => 'APPEAL_SUBMITTED',
        ':fromv' => (string)$caseRow['status_id'],
        ':tov' => '5',
        ':note' => 'Appeal ID #' . $appealId,
      ]);

      // Try to notify responsible staff (reported_by_staff_id -> staff.user_id)
      try {
        if (!empty($caseRow['reported_by_staff_id'])) {
          $stf = $pdo->prepare('SELECT user_id FROM staff WHERE id = :sid LIMIT 1');
          $stf->execute([':sid' => (int)$caseRow['reported_by_staff_id']]);
          $staffUser = $stf->fetch(PDO::FETCH_ASSOC);
          if ($staffUser && !empty($staffUser['user_id'])) {
            $notif = $pdo->prepare('INSERT INTO notifications (user_id, case_id, message, method_id, is_read) VALUES (:uid, :cid, :msg, 1, 0)');
            $notif->execute([
              ':uid' => (int)$staffUser['user_id'],
              ':cid' => $caseId,
              ':msg' => 'New appeal submitted for case #' . $caseRow['case_number'],
            ]);
          }
        }
      } catch (Throwable $e) {
        // non-fatal
      }

      $pdo->commit();
      
      // Set success message and redirect (PRG) to avoid resubmission on reload
      $success = 'Your appeal has been submitted successfully. Reference ID: #' . $appealId;
      $_SESSION['flash_success'] = $success;
      header('Location: ' . $_SERVER['REQUEST_URI']);
      exit;

    } catch (Throwable $e) {
      $pdo->rollBack();
      $errors[] = 'Failed to submit appeal: ' . $e->getMessage();
    }
  }
}

// Fetch student's cases for the select list
$cases = [];
if ($student && !empty($student['id'])) {
  try {
    // Only show cases that:
    //  - belong to this student
    //  - are not in Rejected status (status_id = 6)
    //  - and do not already have an appeal submitted by this student
    $caseStmt = $pdo->prepare(
      'SELECT c.id, c.case_number, c.title 
       FROM cases c
       WHERE c.student_id = :sid
         AND (c.status_id IS NULL OR c.status_id <> 6)
         AND NOT EXISTS (
           SELECT 1 FROM appeals a 
           WHERE a.case_id = c.id AND a.student_id = :sid
         )
       ORDER BY c.created_at DESC'
    );
    $caseStmt->execute([':sid' => (int)$student['id']]);
    $cases = $caseStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
  } catch (Throwable $e) {
    error_log($e->getMessage());
    $cases = [];
  }
}

// Fetch appeals for this student
$appeals = [];
if ($student && !empty($student['id'])) {
  try {
    $aStmt = $pdo->prepare(
      'SELECT a.id, a.case_id, a.appeal_text as reason, a.status_id, 
              a.submitted_at as created_at,
              c.case_number, c.title AS case_title,
              cs.name AS status_name
       FROM appeals a
       LEFT JOIN cases c ON c.id = a.case_id 
       LEFT JOIN case_status cs ON cs.id = c.status_id
       WHERE a.student_id = :sid
       ORDER BY a.submitted_at DESC'
    );
    $aStmt->execute([':sid' => (int)$student['id']]);
    $appeals = $aStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
  } catch (Throwable $e) {
    error_log($e->getMessage());
    $appeals = [];
  }
}

$pageTitle = 'Submit Appeal - SDMS';
include __DIR__ . '/../../components/student-head.php';
?>
<div class="min-h-screen flex">
  <?php include __DIR__ . '/../../components/student-sidebar.php'; ?>

  <main class="flex-1 md:ml-64">
    <!-- Top bar -->
    <div class="h-16 flex items-center px-4 border-b border-gray-200 bg-white sticky top-0 z-40">
      <button id="studentSidebarToggle" class="md:hidden text-primary text-xl mr-3" aria-label="Toggle Sidebar">
        <i class="fa-solid fa-bars"></i>
      </button>
      <h1 class="text-xl font-bold text-primary">Appeals</h1>
      <div class="ml-auto text-sm text-gray">
        Logged in as: <span class="font-medium text-dark"><?php echo e($_SESSION['email'] ?? $_SESSION['username'] ?? 'Student'); ?></span>
      </div>
    </div>

    <div class="p-6 space-y-6">
      <?php if (!$student): ?>
        <div class="bg-yellow-50 border border-yellow-200 text-yellow-800 p-4 rounded-lg">
          <div class="flex items-start gap-3">
            <i class="fa-solid fa-circle-info mt-1"></i>
            <div>
              <h2 class="font-semibold">No student profile linked</h2>
              <p class="text-sm mt-1">Your account is not linked to a student record. Please contact the registrar or system administrator.</p>
            </div>
          </div>
        </div>
      <?php else: ?>
        <!-- Submission Card -->
        <section class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
          <div class="px-5 py-4 border-b border-gray-100 bg-gray-50">
            <h2 class="text-lg font-bold text-dark">Submit New Appeal</h2>
            <p class="text-sm text-gray-600">File an appeal or request clarification for a disciplinary case</p>
          </div>

          <div class="p-5">
            <?php if ($success): ?>
              <div class="mb-4 p-4 rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-800">
                <i class="fa-solid fa-check-circle mr-2"></i>
                <?php echo e($success); ?>
              </div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
              <div class="mb-4 p-4 rounded-lg border border-red-200 bg-red-50 text-red-800">
                <div class="font-medium">Please fix the following errors:</div>
                <ul class="mt-1 ml-4 list-disc">
                  <?php foreach ($errors as $error): ?>
                    <li><?php echo e($error); ?></li>
                  <?php endforeach; ?>
                </ul>
              </div>
            <?php endif; ?>

            <?php if (empty($cases)): ?>
              <div class="text-gray text-sm">You have no cases to appeal.</div>
            <?php else: ?>
              <!-- changed: add id to form for JS hook; keep enctype -->
              <form id="appealForm" method="post" enctype="multipart/form-data" class="space-y-4">
                <div>
                  <label class="block text-sm font-medium text-gray-700">Case</label>
                  <select name="case_id" required class="mt-1 block w-full border-gray-200 rounded">
                    <option value="">-- Select case --</option>
                    <?php foreach ($cases as $c): ?>
                      <option value="<?php echo (int)$c['id']; ?>"><?php echo e($c['case_number'] . ' — ' . $c['title']); ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <div>
                  <label class="block text-sm font-medium text-gray-700">Reason</label>
                  <textarea name="reason" rows="4" required class="mt-1 block w-full border-gray-200 rounded" placeholder="Explain why you are appealing..."></textarea>
                </div>

                <div>
                  <label class="block text-sm font-medium text-gray-700">Attach image (optional)</label>
                  <input id="attachmentInput" type="file" name="attachment" accept="image/*" class="mt-1 block w-full text-sm text-gray-700"/>
                  <p class="text-xs text-gray-500 mt-1">Allowed: JPG, PNG, GIF, WEBP. Max 5MB. (Only used as supporting evidence.)</p>
                </div>

                <div class="flex items-center gap-3">
                  <button id="submitAppealBtn" type="submit" name="submit_appeal" class="px-4 py-2 bg-primary text-white rounded">Submit Appeal</button>
                </div>
              </form>
            <?php endif; ?>
          </div>
        </section>

        <!-- Appeals List -->
        <section class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
          <div class="px-5 py-4 border-b border-gray-100 bg-gray-50">
            <h2 class="text-lg font-bold text-dark">Your Appeals</h2>
            <p class="text-sm text-gray mt-0.5">Track the status of appeals you have submitted.</p>
          </div>

          <div class="p-5">
            <?php if (empty($appeals)): ?>
              <div class="text-gray text-sm">You have not submitted any appeals.</div>
            <?php else: ?>
              <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                  <thead class="bg-gray-50">
                    <tr>
                      <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600">#</th>
                      <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600">Case #</th>
                      <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600">Case Title</th>
                      <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600">Reason</th>
                      <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600">Status</th>
                      <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600">Submitted</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-gray-100">
                    <?php foreach ($appeals as $row): ?>
                      <?php
                        $badge = 'bg-gray-100 text-gray-800';
                        $st = (int)($row['status_id'] ?? 0);
                        if ($st === 1) $badge = 'bg-sky-100 text-sky-800';
                        elseif ($st === 2) $badge = 'bg-amber-100 text-amber-800';
                        elseif ($st === 3) $badge = 'bg-purple-100 text-purple-800';
                        elseif ($st === 4) $badge = 'bg-emerald-100 text-emerald-800';
                        elseif ($st === 5) $badge = 'bg-rose-100 text-rose-800';
                      ?>
                      <tr class="hover:bg-gray-50 align-top">
                        <td class="px-4 py-2 text-sm text-gray"><?php echo e($row['id']); ?></td>
                        <td class="px-4 py-2 text-sm font-medium text-dark"><?php echo e($row['case_number']); ?></td>
                        <td class="px-4 py-2 text-sm text-dark"><?php echo e($row['case_title']); ?></td>
                        <td class="px-4 py-2 text-sm text-gray"><?php echo e(strlen($row['reason']) > 120 ? substr($row['reason'],0,117) . '...' : $row['reason']); ?></td>
                        <td class="px-4 py-2 text-sm">
                          <span class="px-2 py-1 rounded-full text-xs font-semibold <?php echo $badge; ?>"><?php echo e($row['status_name'] ?? 'Unknown'); ?></span>
                        </td>
                        <td class="px-4 py-2 text-sm text-gray"><?php echo e(date('M d, Y g:i a', strtotime($row['created_at']))); ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>
        </section>
      <?php endif; ?>
    </div>
  </main>
</div>

<!-- changed: include SweetAlert2 and attach handlers for flash + confirm -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
  (function () {
    // pass PHP flashes to JS safely
    const flash = <?php echo json_encode(['success' => $flash['success'] ?? null, 'error' => $flash['error'] ?? null], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;

    document.addEventListener('DOMContentLoaded', function () {
      // show success / error via SweetAlert when present
      if (flash.success) {
        Swal.fire({
          icon: 'success',
          title: 'Success',
          text: String(flash.success),
          confirmButtonText: 'OK'
        });
      } else if (flash.error) {
        Swal.fire({
          icon: 'error',
          title: 'Error',
          text: String(flash.error),
          confirmButtonText: 'OK'
        });
      }

      // confirm before submitting appeal
      const form = document.getElementById('appealForm');
      if (form) {
        form.addEventListener('submit', function (ev) {
          ev.preventDefault();
          const attachment = document.getElementById('attachmentInput');
          const hasFile = attachment && attachment.files && attachment.files.length > 0;
          const confirmText = hasFile
            ? 'You are about to submit this appeal with an attached image. Proceed?'
            : 'You are about to submit this appeal. Proceed?';

          Swal.fire({
            title: 'Submit appeal?',
            text: confirmText,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes, submit',
            cancelButtonText: 'Cancel',
            reverseButtons: true
          }).then(function (result) {
            if (result.isConfirmed) {
              // basic client file check: size and type (helps UX)
              if (hasFile) {
                const file = attachment.files[0];
                const maxBytes = 5 * 1024 * 1024;
                if (file.size > maxBytes) {
                  Swal.fire({ icon: 'error', title: 'File too large', text: 'Attached image exceeds 5MB.' });
                  return;
                }
                const allowed = ['image/jpeg','image/png','image/gif','image/webp'];
                if (!allowed.includes(file.type)) {
                  Swal.fire({ icon: 'error', title: 'Invalid file type', text: 'Allowed: JPG, PNG, GIF, WEBP.' });
                  return;
                }
              }
              // proceed to submit
              form.submit();
            }
          });
        });
      }
    });
  })();
</script>

<?php include __DIR__ . '/../../components/student-footer.php'; ?>