<?php
// Student Dashboard: View personal disciplinary history
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

require_once __DIR__ . '/../../database/database.php';

// Optional auth guard: uncomment when login flow is ready
// if (!isset($_SESSION['user_id']) || !isset($_SESSION['role_id']) || (int)$_SESSION['role_id'] !== 3) {
//   header('Location: /SDMS/pages/Auth/login.php');
//   exit;
// }

// Helper: sanitize output
function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;

// Map current user to student record
$student = null;
try {
  if ($userId) {
    $stmt = $pdo->prepare('SELECT id, student_number, first_name, middle_name, last_name, suffix FROM students WHERE user_id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
  }
} catch (Throwable $e) {
  $student = null;
}

// Fetch cases for this student
$cases = [];
if ($student && !empty($student['id'])) {
  try {
    $caseStmt = $pdo->prepare(
      'SELECT c.id, c.case_number, c.title, c.description, c.location, c.incident_date, c.status_id, c.updated_at, c.created_at,
              cs.name AS status_name, vt.name AS violation_name
       FROM cases c
       JOIN case_status cs ON cs.id = c.status_id
       JOIN violation_types vt ON vt.id = c.violation_type_id
       WHERE c.student_id = :sid
       ORDER BY c.created_at DESC'
    );
    $caseStmt->execute([':sid' => (int)$student['id']]);
    $cases = $caseStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
  } catch (Throwable $e) {
    $cases = [];
  }
}

// Compute metrics
$metrics = [
  'total' => count($cases),
  'open' => 0,
  'resolved' => 0, // status_id = 4
  'appealed' => 0, // status_id = 5
  'rejected' => 0, // status_id = 6
];
foreach ($cases as $c) {
  $st = (int)($c['status_id'] ?? 0);
  if ($st === 4) $metrics['resolved']++;
  elseif ($st === 5) $metrics['appealed']++;
  elseif ($st === 6) $metrics['rejected']++;
}
foreach ($cases as $c) {
  $st = (int)($c['status_id'] ?? 0);
  if (!in_array($st, [4, 6], true)) { $metrics['open']++; }
}

$pageTitle = 'Student Dashboard - SDMS';
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
      <h1 class="text-xl font-bold text-primary">Student Dashboard</h1>
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
        <!-- Student Header Card -->
        <section class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
          <div class="px-5 py-4 border-b border-gray-100 bg-gray-50 flex items-center justify-between">
            <div>
              <h2 class="text-lg font-bold text-dark">
                <?php
                  echo e(
                    ($student['first_name'] ?? '') . ' ' .
                    (!empty($student['middle_name']) ? strtoupper($student['middle_name'][0]) . '. ' : '') .
                    ($student['last_name'] ?? '') .
                    (!empty($student['suffix']) ? ', ' . $student['suffix'] : '')
                  );
                ?>
              </h2>
              <p class="text-sm text-gray mt-0.5">Student No.: <span class="font-medium text-dark"><?php echo e($student['student_number'] ?? ''); ?></span></p>
            </div>
            <div class="grid grid-cols-4 gap-3">
              <div class="text-center">
                <div class="text-xs text-gray">Total Cases</div>
                <div class="text-xl font-bold text-dark"><?php echo (int)$metrics['total']; ?></div>
              </div>
              <div class="text-center">
                <div class="text-xs text-gray">Open</div>
                <div class="text-xl font-bold text-amber-600"><?php echo (int)$metrics['open']; ?></div>
              </div>
              <div class="text-center">
                <div class="text-xs text-gray">Resolved</div>
                <div class="text-xl font-bold text-emerald-700"><?php echo (int)$metrics['resolved']; ?></div>
              </div>
              <div class="text-center">
                <div class="text-xs text-gray">Appealed</div>
                <div class="text-xl font-bold text-indigo-700"><?php echo (int)$metrics['appealed']; ?></div>
              </div>
            </div>
          </div>

          <div class="p-5">
            <?php if (empty($cases)): ?>
              <div class="text-gray text-sm">You have no disciplinary cases.</div>
            <?php else: ?>
              <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                  <thead class="bg-gray-50">
                    <tr>
                      <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600">Case #</th>
                      <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600">Violation</th>
                      <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600">Title</th>
                      <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600">Incident Date</th>
                      <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600">Status</th>
                      <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600">Last Update</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-gray-100">
                    <?php foreach ($cases as $row): ?>
                      <?php
                        $badge = 'bg-gray-100 text-gray-800';
                        $st = (int)($row['status_id'] ?? 0);
                        if ($st === 1) $badge = 'bg-sky-100 text-sky-800';          // Filed
                        elseif ($st === 2) $badge = 'bg-amber-100 text-amber-800';  // Under Review
                        elseif ($st === 3) $badge = 'bg-purple-100 text-purple-800';// Investigation
                        elseif ($st === 4) $badge = 'bg-emerald-100 text-emerald-800'; // Resolved
                        elseif ($st === 5) $badge = 'bg-indigo-100 text-indigo-800';// Appealed
                        elseif ($st === 6) $badge = 'bg-rose-100 text-rose-800';    // Rejected
                      ?>
                      <tr class="hover:bg-gray-50">
                        <td class="px-4 py-2 text-sm font-medium text-dark"><?php echo e($row['case_number']); ?></td>
                        <td class="px-4 py-2 text-sm text-dark"><?php echo e($row['violation_name']); ?></td>
                        <td class="px-4 py-2 text-sm text-dark"><?php echo e($row['title']); ?></td>
                        <td class="px-4 py-2 text-sm text-dark"><?php echo e(date('M d, Y', strtotime($row['incident_date']))); ?></td>
                        <td class="px-4 py-2 text-sm">
                          <span class="px-2 py-1 rounded-full text-xs font-semibold <?php echo $badge; ?>"><?php echo e($row['status_name']); ?></span>
                        </td>
                        <td class="px-4 py-2 text-sm text-gray"><?php echo e($row['updated_at'] ? date('M d, Y g:i a', strtotime($row['updated_at'])) : date('M d, Y g:i a', strtotime($row['created_at']))); ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>
        </section>
      <?php endif; ?>
  </main>
</div>

<?php include __DIR__ . '/../../components/student-footer.php'; ?>