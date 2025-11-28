<?php
  session_start();
  if (!isset($_SESSION['user_id'])) {
    header('Location: ../Auth/login.php');
    exit;
  }

  require_once '../../database/database.php';
  $currentUserId = (int)$_SESSION['user_id'];
  $currentStaffId = null;
  $departmentId = null;
  $departmentAbbr = 'Marshal';
  
  // Get department information
  try {
    // Get department from marshal table
    $deptStmt = $pdo->prepare('SELECT d.id as department_id, d.abbreviation 
                              FROM marshal m 
                              LEFT JOIN departments d ON m.department_id = d.id 
                              WHERE m.user_id = ? 
                              LIMIT 1');
    $deptStmt->execute([$currentUserId]);
    $deptRow = $deptStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($deptRow) {
      if (!empty($deptRow['department_id'])) {
        $departmentId = (int)$deptRow['department_id'];
        $departmentAbbr = !empty($deptRow['abbreviation']) 
          ? htmlspecialchars($deptRow['abbreviation'], ENT_QUOTES, 'UTF-8') 
          : 'Marshal';
        
        error_log("Found department - ID: $departmentId, Abbreviation: $departmentAbbr for user: $currentUserId");
      } else {
        error_log("No department ID found for user: $currentUserId");
      }
    } else {
      error_log("No department record found for user: $currentUserId");
    }
  } catch (Throwable $e) {
    error_log('Error fetching department info: ' . $e->getMessage());
  }
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
  try {
    $sql = 'SELECT status_id, COUNT(*) as c FROM cases c';
    $params = [];
    
    if ($departmentId) {
      $sql .= ' JOIN students s ON c.student_id = s.id 
               JOIN courses co ON s.course_id = co.id 
               WHERE co.department_id = ?';
      $params[] = $departmentId;
    } else {
      $sql .= ' WHERE (reported_by_marshal_id = ? OR reported_by_marshal_id = ?)';
      $params = array_merge($params, [$currentStaffId, $currentUserId]);
    }
    
    $sql .= ' GROUP BY status_id';
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
  } catch (PDOException $e) {
    error_log('Error fetching case metrics: ' . $e->getMessage());
  }
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

  try {
    $sql = 'SELECT c.id, c.case_number, c.title, c.incident_date, c.status_id, ct.name as status_name, s.first_name, s.last_name, vt.name as violation_name
            FROM cases c
            LEFT JOIN case_status ct ON ct.id = c.status_id
            LEFT JOIN students s ON s.id = c.student_id
            LEFT JOIN violation_types vt ON vt.id = c.violation_type_id';
    
    $params = [];
    
    if ($departmentId) {
      $sql .= ' JOIN courses co ON s.course_id = co.id 
                WHERE co.department_id = ?';
      $params[] = $departmentId;
    } else {
      $sql .= ' WHERE (c.reported_by_marshal_id = ? OR c.reported_by_marshal_id = ?)';
      $params = array_merge($params, [$currentStaffId, $currentUserId]);
    }
    
    $sql .= ' ORDER BY c.created_at DESC LIMIT 10';
    
    $cases = $pdo->prepare($sql);
    $cases->execute($params);
  } catch (PDOException $e) {
    error_log('Error fetching recent cases: ' . $e->getMessage());
    $cases = [];
  }
  $cases = $cases->fetchAll(PDO::FETCH_ASSOC);
  $recipients = $pdo->query("SELECT id, username, role_id FROM users WHERE role_id IN (1,4) ORDER BY role_id, username")->fetchAll(PDO::FETCH_ASSOC);
  try {
    $sql = 'SELECT 
      SUM(CASE WHEN c.status_id = 1 THEN 1 ELSE 0 END) AS filed,
      SUM(CASE WHEN c.status_id IN (2,3) THEN 1 ELSE 0 END) AS under_review,
      SUM(CASE WHEN c.status_id = 5 THEN 1 ELSE 0 END) AS appealed,
      SUM(CASE WHEN c.status_id = 4 THEN 1 ELSE 0 END) AS resolved,
      SUM(CASE WHEN c.status_id = 6 THEN 1 ELSE 0 END) AS rejected
    FROM cases c';
    
    $params = [];
    
    if ($departmentId) {
      $sql .= ' JOIN students s ON c.student_id = s.id 
                JOIN courses co ON s.course_id = co.id 
                WHERE co.department_id = ?';
      $params[] = $departmentId;
    } else {
      $sql .= ' WHERE (c.reported_by_marshal_id = ? OR c.reported_by_marshal_id = ?)';
      $params = array_merge($params, [$currentStaffId, $currentUserId]);
    }
    
    $statusAgg = $pdo->prepare($sql);
    $statusAgg->execute($params);
  } catch (PDOException $e) {
    error_log('Error fetching status aggregation: ' . $e->getMessage());
    $statusAgg = [];
  }
  $statusRow = $statusAgg->fetch(PDO::FETCH_ASSOC) ?: ['filed'=>0,'under_review'=>0,'appealed'=>0,'resolved'=>0,'rejected'=>0];

  $statusChart = [
    'labels' => ['Filed', 'Under Review/Investigation', 'Appealed', 'Resolved', 'Rejected'],
    'data'   => [ (int)$statusRow['filed'], (int)$statusRow['under_review'], (int)$statusRow['appealed'], (int)$statusRow['resolved'], (int)$statusRow['rejected'] ]
  ];
  // Expose appealed count to top metrics
  $metrics['appealed'] = (int)$statusRow['appealed'];

  // 14-day trend by incident date for current staff
  $trendStmt = null;
  $trendRows = [];
  try {
    $sql = 'SELECT DATE(c.incident_date) as d, COUNT(*) as c
            FROM cases c';
    
    $params = [];
    
    if ($departmentId) {
      $sql .= ' JOIN students s ON c.student_id = s.id 
                JOIN courses co ON s.course_id = co.id 
                WHERE co.department_id = ?';
      $params[] = $departmentId;
    } else {
      $sql .= ' WHERE (c.reported_by_marshal_id = ? OR c.reported_by_marshal_id = ?)';
      $params = array_merge($params, [$currentStaffId, $currentUserId]);
    }
    
    $sql .= ' AND c.incident_date >= DATE_SUB(CURDATE(), INTERVAL 13 DAY)
             GROUP BY DATE(c.incident_date)
             ORDER BY d ASC';
    
    $trendStmt = $pdo->prepare($sql);
    $trendStmt->execute($params);
    $trendRows = $trendStmt->fetchAll(PDO::FETCH_KEY_PAIR);
  } catch (PDOException $e) {
    error_log('Error fetching trend data: ' . $e->getMessage());
  }
  $trendRows = $trendStmt->fetchAll(PDO::FETCH_KEY_PAIR); 

  $trendLabels = [];
  $trendData = [];
  for ($i = 13; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-{$i} day"));
    $trendLabels[] = date('M d', strtotime($date));
    $trendData[] = isset($trendRows[$date]) ? (int)$trendRows[$date] : 0;
  }

  // Department variables are now initialized at the top of the file

  // Get dashboard metrics
  $widgetData = [
    'total_students' => 0,
    'total_courses' => 0,
    'active_cases' => 0,
    'resolved_cases' => 0,
    'messages_this_month' => 0
  ];

  if ($departmentId) {
    try {
      // Total Students in Department
      $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM students WHERE course_id IN (SELECT id FROM courses WHERE department_id = ?)');
      $stmt->execute([$departmentId]);
      $result = $stmt->fetch(PDO::FETCH_ASSOC);
      $widgetData['total_students'] = $result ? (int)$result['count'] : 0;

      // Total Courses in Department
      $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM courses WHERE department_id = ?');
      $stmt->execute([$departmentId]);
      $result = $stmt->fetch(PDO::FETCH_ASSOC);
      $widgetData['total_courses'] = $result ? (int)$result['count'] : 0;

      // Active Cases (not resolved or rejected)
      $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM cases c 
                            JOIN students s ON c.student_id = s.id 
                            WHERE s.course_id IN (SELECT id FROM courses WHERE department_id = ?) 
                            AND c.status_id NOT IN (4, 6)');
      $stmt->execute([$departmentId]);
      $result = $stmt->fetch(PDO::FETCH_ASSOC);
      $widgetData['active_cases'] = $result ? (int)$result['count'] : 0;

      // Resolved Cases
      $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM cases c 
                            JOIN students s ON c.student_id = s.id 
                            WHERE s.course_id IN (SELECT id FROM courses WHERE department_id = ?) 
                            AND c.status_id = 4');
      $stmt->execute([$departmentId]);
      $result = $stmt->fetch(PDO::FETCH_ASSOC);
      $widgetData['resolved_cases'] = $result ? (int)$result['count'] : 0;

      // Messages this month (as sender or recipient)
      $currentMonth = date('Y-m-01');
      $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM messages 
                            WHERE (sender_user_id = ? OR recipient_user_id = ?) 
                            AND created_at >= ?');
      $stmt->execute([$currentUserId, $currentUserId, $currentMonth]);
      $result = $stmt->fetch(PDO::FETCH_ASSOC);
      $widgetData['messages_this_month'] = $result ? (int)$result['count'] : 0;

    } catch (Throwable $e) {
      // Log error but don't break the page
      error_log('Dashboard widget error: ' . $e->getMessage());
    }
  }

  $pageTitle = $departmentAbbr . ' Dashboard - SDMS';
  include '../../components/staff-head.php';
  include '../../components/staff-sidebar.php';
?>

<div class="min-h-full flex flex-col md:flex-row">
  <main class="flex-1 md:ml-64">
    <!-- Mobile Header -->
    <div class="md:hidden sticky top-0 z-40 bg-white border-b border-gray-200">
      <div class="h-16 flex items-center px-4">
        <button id="staffSidebarToggle" class="text-primary text-2xl mr-3">
          <i class="fa-solid fa-bars"></i>
        </button>
        <div class="text-primary font-bold flex-grow">Welcome Marshal</div>
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
      <h1 class="text-xl font-bold text-primary">Welcome Marshal<?php echo $departmentAbbr !== 'Marshal' ? ' - ' . $departmentAbbr : ''; ?></h1>
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
        <h1 class="text-lg sm:text-xl md:text-2xl font-semibold text-gray-800"><?php echo $departmentAbbr; ?> Dashboard</h1>
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

      <!-- Metrics -->
      <section>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <!-- Stats Cards -->
        <div class="bg-white rounded-lg shadow p-6">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-gray-500">Total Students</p>
              <p class="text-2xl font-bold text-primary"><?php echo $widgetData['total_students']; ?></p>
            </div>
            <div class="p-3 rounded-full bg-blue-50 text-blue-500">
              <i class="fa-solid fa-users text-xl"></i>
            </div>
          </div>
          <div class="mt-2">
            <p class="text-xs text-gray-500">Students in your department</p>
          </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-gray-500">Total Courses</p>
              <p class="text-2xl font-bold text-yellow-500"><?php echo $widgetData['total_courses']; ?></p>
            </div>
            <div class="p-3 rounded-full bg-yellow-50 text-yellow-500">
              <i class="fa-solid fa-book text-xl"></i>
            </div>
          </div>
          <div class="mt-2">
            <p class="text-xs text-gray-500">Courses in your department</p>
          </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-gray-500">Active Cases</p>
              <p class="text-2xl font-bold text-red-500"><?php echo $widgetData['active_cases']; ?></p>
            </div>
            <div class="p-3 rounded-full bg-red-50 text-red-500">
              <i class="fa-solid fa-clipboard-list text-xl"></i>
            </div>
          </div>
          <div class="mt-2">
            <p class="text-xs text-gray-500">Open cases in your department</p>
          </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-gray-500">Resolved Cases</p>
              <p class="text-2xl font-bold text-green-500"><?php echo $widgetData['resolved_cases']; ?></p>
            </div>
            <div class="p-3 rounded-full bg-green-50 text-green-500">
              <i class="fa-solid fa-check-circle text-xl"></i>
            </div>
          </div>
          <div class="mt-2">
            <p class="text-xs text-gray-500">Closed cases in your department</p>
          </div>
        </div>
      </div>

      <!-- Second Row of Widgets -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-lg shadow p-6">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-gray-500">Messages</p>
              <p class="text-2xl font-bold text-indigo-500"><?php echo $widgetData['messages_this_month']; ?></p>
            </div>
            <div class="p-3 rounded-full bg-indigo-50 text-indigo-500">
              <i class="fa-solid fa-envelope text-xl"></i>
            </div>
          </div>
          <div class="mt-2">
            <p class="text-xs text-gray-500">Sent/received this month</p>
          </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-gray-500">Total Cases</p>
              <p class="text-2xl font-bold text-primary"><?php echo $metrics['total']; ?></p>
            </div>
            <div class="p-3 rounded-full bg-blue-50 text-blue-500">
              <i class="fa-solid fa-folder-open text-xl"></i>
            </div>
          </div>
          <div class="mt-2">
            <p class="text-xs text-gray-500">Your reported cases</p>
          </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-gray-500">Under Review</p>
              <p class="text-2xl font-bold text-yellow-500"><?php echo $metrics['under_review']; ?></p>
            </div>
            <div class="p-3 rounded-full bg-yellow-50 text-yellow-500">
              <i class="fa-solid fa-clock text-xl"></i>
            </div>
          </div>
          <div class="mt-2">
            <p class="text-xs text-gray-500">Your cases in review</p>
          </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-gray-500">Appealed</p>
              <p class="text-2xl font-bold text-purple-500"><?php echo $metrics['appealed']; ?></p>
            </div>
            <div class="p-3 rounded-full bg-purple-50 text-purple-500">
              <i class="fa-solid fa-gavel text-xl"></i>
            </div>
          </div>
          <div class="mt-2">
            <p class="text-xs text-gray-500">Cases under appeal</p>
          </div>
        </div>
      </div>
    </div>
  </main>
</div>


  </main>
</div>

<script>
  // Chart instances
  let statusChart = null;
  let trendChart = null;

  // Charts
  function initCharts() {
    // Destroy existing charts if they exist
    if (statusChart && typeof statusChart.destroy === 'function') {
      statusChart.destroy();
    }
    if (trendChart && typeof trendChart.destroy === 'function') {
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