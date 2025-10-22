<?php
  session_start();
  if (!isset($_SESSION['user_id'])) {
    header('Location: ../Auth/login.php');
    exit;
  }

  require_once '../../database/database.php';
  $currentUserId = (int)$_SESSION['user_id'];
  $currentStaffId = null;
  try {
    $stf = $pdo->prepare('SELECT id FROM staff WHERE user_id = ? LIMIT 1');
    $stf->execute([$currentUserId]);
    $row = $stf->fetch(PDO::FETCH_ASSOC);
    if ($row && !empty($row['id'])) { $currentStaffId = (int)$row['id']; }
  } catch (Throwable $e) { /* ignore; fallback below */ }
  $reportedByIdForInsert = $currentStaffId ?: $currentUserId;
  function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
  $flash = [ 'type' => null, 'message' => null ];
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_report') {
    try {
      $pdo->beginTransaction();

      $student_id = (int)($_POST['student_id'] ?? 0);
      $violation_type_id = (int)($_POST['violation_type_id'] ?? 0);
      $title = trim($_POST['title'] ?? '');
      $description = trim($_POST['description'] ?? '');
      $location = trim($_POST['location'] ?? '');
      $incident_date = trim($_POST['incident_date'] ?? '');

      if (!$student_id || !$violation_type_id || !$incident_date || !$title) {
        throw new Exception('Please fill out all required fields.');
      }
      $case_number = 'CASE-' . date('Ymd-His') . '-' . substr(bin2hex(random_bytes(4)), 0, 8);
      $stmt = $pdo->prepare('INSERT INTO cases (case_number, student_id, reported_by_staff_id, violation_type_id, title, description, location, incident_date, status_id) VALUES (?,?,?,?,?,?,?,?,1)');
      $stmt->execute([$case_number, $student_id, $reportedByIdForInsert, $violation_type_id, $title, $description, $location, $incident_date]);
      $case_id = (int)$pdo->lastInsertId();
      if (!empty($_FILES['evidence']['name'])) {
        $uploadDir = '../../uploads/case_evidence/';
        if (!is_dir($uploadDir)) {
          @mkdir($uploadDir, 0775, true);
        }

        $originalName = basename($_FILES['evidence']['name']);
        $ext = pathinfo($originalName, PATHINFO_EXTENSION);
        $safeName = 'case' . $case_id . '_' . time() . '_' . mt_rand(1000,9999) . ($ext ? ('.' . $ext) : '');
        $targetPath = $uploadDir . $safeName;

        if (!move_uploaded_file($_FILES['evidence']['tmp_name'], $targetPath)) {
          throw new Exception('Failed to upload evidence file.');
        }

        $mime = mime_content_type($targetPath);
        $size = filesize($targetPath);

        $stmtEv = $pdo->prepare('INSERT INTO case_evidence (case_id, uploaded_by_user_id, filename, file_path, file_type, file_size) VALUES (?,?,?,?,?,?)');
        $stmtEv->execute([$case_id, $currentUserId, $originalName, $targetPath, $mime, $size]);
      }

      $pdo->commit();
      $flash = [ 'type' => 'success', 'message' => 'Report filed successfully. Case Number: ' . e($case_number) ];
    } catch (Throwable $ex) {
      if ($pdo->inTransaction()) { $pdo->rollBack(); }
      $flash = [ 'type' => 'error', 'message' => 'Failed to submit report: ' . e($ex->getMessage()) ];
    }
  }

  // Handle quick message
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_message') {
    try {
      $recipient_user_id = (int)($_POST['recipient_user_id'] ?? 0);
      $subject = trim($_POST['subject'] ?? '');
      $body = trim($_POST['body'] ?? '');
      $case_id_for_msg = !empty($_POST['case_id_for_msg']) ? (int)$_POST['case_id_for_msg'] : null;

      if (!$recipient_user_id || !$body) {
        throw new Exception('Please select a recipient and enter a message.');
      }

      $stmt = $pdo->prepare('INSERT INTO messages (case_id, sender_user_id, recipient_user_id, subject, body) VALUES (?,?,?,?,?)');
      $stmt->execute([$case_id_for_msg, $currentUserId, $recipient_user_id, $subject, $body]);
      $flash = [ 'type' => 'success', 'message' => 'Message sent successfully.' ];
    } catch (Throwable $ex) {
      $flash = [ 'type' => 'error', 'message' => 'Failed to send message: ' . e($ex->getMessage()) ];
    }
  }
  $students = $pdo->query('SELECT id, student_number, first_name, last_name FROM students ORDER BY last_name, first_name')->fetchAll(PDO::FETCH_ASSOC);
  $violations = $pdo->query('SELECT id, code, name FROM violation_types ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
  $metrics = [ 'total' => 0, 'under_review' => 0, 'resolved' => 0, 'rejected' => 0 ];
  $stmt = $pdo->prepare('SELECT status_id, COUNT(*) as c FROM cases WHERE (reported_by_staff_id = ? OR reported_by_staff_id = ?) GROUP BY status_id');
  $stmt->execute([$currentStaffId, $currentUserId]);
  while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $metrics['total'] += (int)$row['c'];
    if ((int)$row['status_id'] === 2 || (int)$row['status_id'] === 3) { $metrics['under_review'] += (int)$row['c']; }
    if ((int)$row['status_id'] === 4) { $metrics['resolved'] += (int)$row['c']; }
    if ((int)$row['status_id'] === 6) { $metrics['rejected'] += (int)$row['c']; }
  }
  // Get notification count for the current user
  $notificationCount = 0;
  try {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
    $stmt->execute([$currentUserId]);
    $notificationCount = (int)$stmt->fetchColumn();
  } catch (PDOException $e) {
    error_log('Error fetching notification count: ' . $e->getMessage());
  }

  $cases = $pdo->prepare('SELECT c.id, c.case_number, c.title, c.incident_date, c.status_id, ct.name as status_name, s.first_name, s.last_name, vt.name as violation_name
                          FROM cases c
                          LEFT JOIN case_status ct ON ct.id = c.status_id
                          LEFT JOIN students s ON s.id = c.student_id
                          LEFT JOIN violation_types vt ON vt.id = c.violation_type_id
                          WHERE (c.reported_by_staff_id = ? OR c.reported_by_staff_id = ?)
                          ORDER BY c.created_at DESC
                          LIMIT 10');
  $cases->execute([$currentStaffId, $currentUserId]);
  $cases = $cases->fetchAll(PDO::FETCH_ASSOC);
  $recipients = $pdo->query("SELECT id, username, role_id FROM users WHERE role_id IN (1,4) ORDER BY role_id, username")->fetchAll(PDO::FETCH_ASSOC);
  $statusAgg = $pdo->prepare('SELECT 
      SUM(CASE WHEN status_id = 1 THEN 1 ELSE 0 END) AS filed,
      SUM(CASE WHEN status_id IN (2,3) THEN 1 ELSE 0 END) AS under_review,
      SUM(CASE WHEN status_id = 5 THEN 1 ELSE 0 END) AS appealed,
      SUM(CASE WHEN status_id = 4 THEN 1 ELSE 0 END) AS resolved,
      SUM(CASE WHEN status_id = 6 THEN 1 ELSE 0 END) AS rejected
    FROM cases WHERE (reported_by_staff_id = ? OR reported_by_staff_id = ?)');
  $statusAgg->execute([$currentStaffId, $currentUserId]);
  $statusRow = $statusAgg->fetch(PDO::FETCH_ASSOC) ?: ['filed'=>0,'under_review'=>0,'appealed'=>0,'resolved'=>0,'rejected'=>0];

  $statusChart = [
    'labels' => ['Filed', 'Under Review/Investigation', 'Appealed', 'Resolved', 'Rejected'],
    'data'   => [ (int)$statusRow['filed'], (int)$statusRow['under_review'], (int)$statusRow['appealed'], (int)$statusRow['resolved'], (int)$statusRow['rejected'] ]
  ];
  // Expose appealed count to top metrics
  $metrics['appealed'] = (int)$statusRow['appealed'];

  // 14-day trend by incident date for current staff
  $trendStmt = $pdo->prepare('SELECT DATE(incident_date) as d, COUNT(*) as c
                              FROM cases
                              WHERE (reported_by_staff_id = ? OR reported_by_staff_id = ?)
                                AND incident_date >= DATE_SUB(CURDATE(), INTERVAL 13 DAY)
                              GROUP BY DATE(incident_date)
                              ORDER BY d ASC');
  $trendStmt->execute([$currentStaffId, $currentUserId]);
  $trendRows = $trendStmt->fetchAll(PDO::FETCH_KEY_PAIR); 

  $trendLabels = [];
  $trendData = [];
  for ($i = 13; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-{$i} day"));
    $trendLabels[] = date('M d', strtotime($date));
    $trendData[] = isset($trendRows[$date]) ? (int)$trendRows[$date] : 0;
  }

  $pageTitle = 'Staff Dashboard - SDMS';
  include '../../components/staff-head.php';
?>

<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo $pageTitle; ?></title>
</head>
<body class="h-full bg-gray-50">
<div class="min-h-full flex flex-col md:flex-row">
  <?php include '../../components/staff-sidebar.php'; ?>

  <main class="flex-1 md:ml-64">
    <!-- Mobile Header -->
    <div class="md:hidden sticky top-0 z-40 bg-white border-b border-gray-200">
      <div class="h-16 flex items-center px-4">
        <button id="staffSidebarToggle" class="text-primary text-2xl mr-3">
          <i class="fa-solid fa-bars"></i>
        </button>
        <div class="text-primary font-bold flex-grow">Staff Dashboard</div>
        <a href="notifications.php" class="relative text-primary text-2xl p-2 hover:bg-gray-100 rounded-full transition-colors duration-200">
          <i class="fa-solid fa-bell"></i>
          <?php if ($notificationCount > 0): ?>
            <span class="absolute -top-0.5 -right-0.5 bg-red-500 text-white text-xs font-bold rounded-full h-5 w-5 flex items-center justify-center shadow-sm">
              <?php echo $notificationCount > 9 ? '9+' : $notificationCount; ?>
            </span>
          <?php endif; ?>
        </a>
      </div>
    </div>

    <!-- Desktop Header -->
    <div class="hidden md:flex sticky top-0 z-40 bg-white border-b border-gray-200 h-16 items-center px-6 justify-between">
      <h1 class="text-xl font-bold text-primary">Dashboard</h1>
      <div class="flex items-center space-x-4">
        <a href="notifications.php" class="relative text-primary text-2xl p-2 hover:bg-gray-100 rounded-full transition-colors duration-200">
          <i class="fa-solid fa-bell"></i>
          <?php if ($notificationCount > 0): ?>
            <span class="absolute -top-0.5 -right-0.5 bg-red-500 text-white text-xs font-bold rounded-full h-5 w-5 flex items-center justify-center shadow-sm">
              <?php echo $notificationCount > 9 ? '9+' : $notificationCount; ?>
            </span>
          <?php endif; ?>
        </a>
      </div>
    </div>
    <!-- End Top bar -->
    <div class="h-16 flex items-center justify-between px-4 md:px-6 lg:px-8 border-b border-gray-200 bg-white sticky top-0 z-40">
      <div class="flex items-center gap-3">
        <button id="staffSidebarToggle" class="md:hidden text-primary text-xl focus:outline-none" aria-label="Toggle Sidebar">
          <i class="fa-solid fa-bars"></i>
        </button>
        <h1 class="text-lg sm:text-xl md:text-2xl font-semibold text-gray-800">Staff Dashboard</h1>
      </div>
    </div>

    <div class="p-3 sm:p-4 md:p-6 space-y-4 md:space-y-6">
      <?php if ($flash['type']): ?>
        <div class="p-4 rounded border <?php echo $flash['type']==='success' ? 'bg-green-50 border-green-200 text-green-700' : 'bg-red-50 border-red-200 text-red-700'; ?>">
          <?php echo $flash['message']; ?>
        </div>
      <?php endif; ?>

      <!-- Metrics -->
      <section>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 sm:gap-4">
          <div class="p-3 sm:p-4 rounded-lg border bg-white shadow-sm hover:shadow transition-shadow">
            <div class="text-xs sm:text-sm text-gray-500 truncate">Total Cases Reported</div>
            <div class="text-2xl sm:text-3xl font-bold mt-1 text-gray-800"><?php echo (int)$metrics['total']; ?></div>
          </div>
          <div class="p-3 sm:p-4 rounded-lg border bg-white shadow-sm hover:shadow transition-shadow">
            <div class="text-xs sm:text-sm text-gray-500 truncate">Under Review</div>
            <div class="text-2xl sm:text-3xl font-bold mt-1 text-blue-600"><?php echo (int)$metrics['under_review']; ?></div>
          </div>
          <div class="p-3 sm:p-4 rounded-lg border bg-white shadow-sm hover:shadow transition-shadow">
            <div class="text-xs sm:text-sm text-gray-500 truncate">Appealed</div>
            <div class="text-2xl sm:text-3xl font-bold mt-1 text-purple-600"><?php echo (int)($metrics['appealed'] ?? 0); ?></div>
          </div>
          <div class="p-3 sm:p-4 rounded-lg border bg-white shadow-sm hover:shadow transition-shadow">
            <div class="text-xs sm:text-sm text-gray-500 truncate">Resolved</div>
            <div class="text-2xl sm:text-3xl font-bold mt-1 text-green-600"><?php echo (int)$metrics['resolved']; ?></div>
          </div>
          <div class="p-3 sm:p-4 rounded-lg border bg-white shadow-sm hover:shadow transition-shadow">
            <div class="text-xs sm:text-sm text-gray-500 truncate">Rejected</div>
            <div class="text-2xl sm:text-3xl font-bold mt-1 text-red-600"><?php echo (int)$metrics['rejected']; ?></div>
          </div>
        </div>
      </section>

      <!-- Cases by Status (Chart) -->
      <section class="bg-white border rounded-lg shadow-sm overflow-hidden">
        <div class="flex items-center justify-between p-3 sm:p-4 border-b">
          <h2 class="text-base sm:text-lg font-semibold text-gray-800">Cases by Status</h2>
        </div>
        <div class="p-2 sm:p-4">
          <div class="w-full" style="height:220px;">
            <canvas id="statusChart"></canvas>
          </div>
        </div>
      </section>

      <!-- 14-Day Case Trend (Chart) -->
      <section class="bg-white border rounded-lg shadow-sm overflow-hidden">
        <div class="flex items-center justify-between p-3 sm:p-4 border-b">
          <h2 class="text-base sm:text-lg font-semibold text-gray-800">14-Day Case Trend</h2>
        </div>
        <div class="p-2 sm:p-4">
          <div class="w-full" style="height:260px;">
            <canvas id="trendChart"></canvas>
          </div>
        </div>
      </section>

      
    </div>
  </main>
</div>


<script>
  // Charts
  function initCharts() {
    // Status Chart (Doughnut)
    const statusCtx = document.getElementById('statusChart')?.getContext('2d');
    if (statusCtx) {
      new Chart(statusCtx, {
        type: 'doughnut',
        data: {
          labels: <?php echo json_encode($statusChart['labels']); ?>,
          datasets: [{
            data: <?php echo json_encode($statusChart['data']); ?>,
            backgroundColor: ['#3B82F6', '#F59E0B', '#8B5CF6', '#10B981', '#EF4444'],
            borderWidth: 0
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: { position: 'bottom' }
          }
        }
      });
    }

    // Trend Chart (Line)
    const trendCtx = document.getElementById('trendChart')?.getContext('2d');
    if (trendCtx) {
      new Chart(trendCtx, {
        type: 'line',
        data: {
          labels: <?php echo json_encode($trendLabels); ?>,
          datasets: [{
            label: 'Cases',
            data: <?php echo json_encode($trendData); ?>,
            borderColor: '#3B82F6',
            backgroundColor: 'rgba(59, 130, 246, 0.2)',
            fill: true,
            tension: 0.35,
            pointRadius: 3
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          scales: {
            y: { beginAtZero: true, precision: 0 }
          },
          plugins: {
            legend: { display: false }
          }
        }
      });
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initCharts);
  } else {
    initCharts();
  }
</script>

  </main>
</div>

<script>
  // Chart instances
  let statusChart = null;
  let trendChart = null;

  // Charts
  function initCharts() {
    // Destroy existing charts if they exist
    if (statusChart) {
      statusChart.destroy();
    }
    if (trendChart) {
      trendChart.destroy();
    }

    // Status Chart (Doughnut)
    const statusCtx = document.getElementById('statusChart');
    if (statusCtx) {
      statusChart = new Chart(statusCtx.getContext('2d'), {
        type: 'doughnut',
        data: {
          labels: <?php echo json_encode($statusChart['labels']); ?>,
          datasets: [{
            data: <?php echo json_encode($statusChart['data']); ?>,
            backgroundColor: ['#3B82F6', '#F59E0B', '#8B5CF6', '#10B981', '#EF4444'],
            borderWidth: 0,
            borderColor: '#fff',
            borderWidth: 2,
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          cutout: '65%',
          plugins: {
            legend: { 
              position: 'bottom',
              labels: {
                padding: 20,
                usePointStyle: true,
                pointStyle: 'circle',
                font: {
                  size: window.innerWidth < 640 ? 10 : 12
                }
              }
            }
          },
          layout: {
            padding: 10
          }
        }
      });
    }

    // Trend Chart (Line)
    const trendCtx = document.getElementById('trendChart');
    if (trendCtx) {
      trendChart = new Chart(trendCtx.getContext('2d'), {
        type: 'line',
        data: {
          labels: <?php echo json_encode($trendLabels); ?>,
          datasets: [{
            label: 'Cases',
            data: <?php echo json_encode($trendData); ?>,
            borderColor: '#3B82F6',
            backgroundColor: 'rgba(59, 130, 246, 0.1)',
            borderWidth: 2,
            fill: true,
            tension: 0.3,
            pointRadius: 3,
            pointHoverRadius: 5,
            pointBackgroundColor: '#fff',
            pointBorderColor: '#3B82F6',
            pointBorderWidth: 2
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          scales: {
            y: { 
              beginAtZero: true, 
              precision: 0,
              grid: {
                drawBorder: false
              },
              ticks: {
                maxTicksLimit: 5
              }
            },
            x: {
              grid: {
                display: false
              },
              ticks: {
                maxRotation: 45,
                minRotation: 45,
                autoSkip: true,
                maxTicksLimit: 7
              }
            }
          },
          plugins: {
            legend: { 
              display: false 
            },
            tooltip: {
              backgroundColor: 'rgba(0, 0, 0, 0.8)',
              titleFont: {
                size: 12
              },
              bodyFont: {
                size: 13,
                weight: 'bold'
              },
              padding: 10,
              displayColors: false
            }
          }
        }
      });
    }
  }

  // Initialize charts when the DOM is fully loaded
  document.addEventListener('DOMContentLoaded', function() {
    // Initialize charts
    initCharts();
    
    // Handle window resize with debounce for charts
    let resizeTimer;
    window.addEventListener('resize', function() {
      clearTimeout(resizeTimer);
      resizeTimer = setTimeout(initCharts, 250);
    });
    
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
      return new bootstrap.Tooltip(tooltipTriggerEl);
    });
  });
</script>

<?php include '../../components/staff-footer.php'; ?>
</html>