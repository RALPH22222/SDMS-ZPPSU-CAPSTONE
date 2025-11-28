<?php
  if (session_status() === PHP_SESSION_NONE) {
    session_start();
  }
  if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
  }
  $csrf = $_SESSION['csrf_token'];
  if (!isset($_SESSION['user_id'])) {
    header('Location: ../Auth/login.php');
    exit;
  }

  require_once __DIR__ . '/../../database/database.php';

  $currentUserId = (int)($_SESSION['user_id'] ?? 0);

  $currentStaffId = null;
  try {
    $stf = $pdo->prepare('SELECT id FROM staff WHERE user_id = ? LIMIT 1');
    $stf->execute([$currentUserId]);
    $row = $stf->fetch(PDO::FETCH_ASSOC);
    if ($row && !empty($row['id'])) { $currentStaffId = (int)$row['id']; }
  } catch (Throwable $e) { /* ignore */ }

  function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

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
  // Check if it's an AJAX request
  $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
  
  // Set JSON content type for AJAX responses
  if ($isAjax) {
    header('Content-Type: application/json');
  }

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
        $incident_date = trim($_POST['incident_date'] ?? ''); 
        $is_confidential = isset($_POST['is_confidential']) ? 1 : 0;
        if ($student_id <= 0 || $violation_type_id <= 0 || $title === '' || $incident_date === '') {
          throw new Exception('Please complete all required fields.');
        }
        $incident_dt = str_replace('T', ' ', $incident_date);

        $case_number = generate_case_number($pdo);

        $stmt = $pdo->prepare('INSERT INTO cases (id, case_number, student_id, reported_by_marshal_id, violation_type_id, title, description, location, incident_date, status_id, resolution, resolution_date, is_confidential, created_at, updated_at) VALUES (NULL, ?, ?, ?, ?, ?, ?, ?, ?, 1, NULL, NULL, ?, CURRENT_TIMESTAMP(), NULL)');
        $stmt->execute([$case_number, $student_id, ($currentStaffId ?? $currentUserId), $violation_type_id, $title, $description, $location, $incident_dt, $is_confidential]);
        $caseId = $pdo->lastInsertId();
        try {
            $stmt = $pdo->prepare('SELECT user_id FROM students WHERE id = ?');
            $stmt->execute([$student_id]);
            $studentUserId = $stmt->fetchColumn();
            
            if ($studentUserId) {
                require_once __DIR__ . '/../../includes/notification_functions.php';
                $message = "A new report has been filed against you: {$title}";
                sendNotification($studentUserId, $message, 1, $caseId);
            }
        } catch (Exception $e) {
            error_log("Failed to send notification to student: " . $e->getMessage());
        }
        require_once __DIR__ . '/../../includes/notification_functions.php';
        $adminIds = getAdminRecipients();
        if (!empty($adminIds)) {
            $message = "New case #$case_number reported: $title";
            sendNotification($adminIds, $message, 1, $caseId);
        }
        $_SESSION['flash'] = flash('success', 'Report created successfully.');
        header('Location: ' . basename($_SERVER['PHP_SELF']) . '?' . http_build_query($_GET));
        exit;
      }
      if ($action === 'update_report') {
        $case_id = (int)($_POST['case_id'] ?? 0);
        $student_id = (int)($_POST['student_id'] ?? 0);
        $violation_type_id = (int)($_POST['violation_type_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        
        // Set response headers for AJAX
        if ($isAjax) {
          header('Content-Type: application/json');
        }
        $description = trim($_POST['description'] ?? '') ?: null;
        $location = trim($_POST['location'] ?? '') ?: null;
        $incident_date = trim($_POST['incident_date'] ?? '');
        $status_id = isset($_POST['status_id']) && ctype_digit((string)$_POST['status_id']) ? (int)$_POST['status_id'] : null;
        $is_confidential = isset($_POST['is_confidential']) ? 1 : 0;
        if ($case_id <= 0) { throw new Exception('Invalid case.'); }
        if ($student_id <= 0 || $violation_type_id <= 0 || $title === '' || $incident_date === '') {
          throw new Exception('Please complete all required fields.');
        }
        $own = $pdo->prepare('SELECT reported_by_marshal_id FROM cases WHERE id = ?');
        $own->execute([$case_id]);
        $rep = $own->fetchColumn();
        if ($rep === false) { throw new Exception('Case not found.'); }
        
        // Get the marshal's user ID to check permissions
        $marshalUserStmt = $pdo->prepare('SELECT user_id FROM marshal WHERE id = ?');
        $marshalUserStmt->execute([$rep]);
        $marshalUserId = $marshalUserStmt->fetchColumn();
        
        // Check if current user is the marshal who created the case
        if ($marshalUserId != $currentUserId) {
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
        try {
            $stmt = $pdo->prepare('SELECT user_id FROM students WHERE id = ?');
            $stmt->execute([$student_id]);
            $studentUserId = $stmt->fetchColumn();
            if ($studentUserId) {
                require_once __DIR__ . '/../../includes/notification_functions.php';
                $statusText = '';
                if ($status_id !== null) {
                    $stmt = $pdo->prepare('SELECT name FROM case_status WHERE id = ?');
                    $stmt->execute([$status_id]);
                    $statusName = $stmt->fetchColumn();
                    $statusText = ", Status: " . ($statusName ?: 'Updated');
                }
                $message = "Your report has been updated: {$title}{$statusText}";
                sendNotification($studentUserId, $message, 1, $case_id);
            }
        } catch (Exception $e) {
            error_log("Failed to send update notification to student: " . $e->getMessage());
        }
        if ($isAjax) {
          echo json_encode([
            'success' => true,
            'message' => 'Report updated successfully',
            'redirect' => basename($_SERVER['PHP_SELF']) . '?' . http_build_query($_GET)
          ]);
          exit;
        } else {
          $_SESSION['flash'] = flash('success', 'Report updated successfully.');
          header('Location: ' . basename($_SERVER['PHP_SELF']) . '?' . http_build_query($_GET));
          exit;
        }
      }
      if ($action === 'delete_report') {
        $case_id = (int)($_POST['case_id'] ?? 0);
        if ($case_id <= 0) { throw new Exception('Invalid case.'); }
        $own = $pdo->prepare('SELECT reported_by_marshal_id FROM cases WHERE id = ?');
        $own->execute([$case_id]);
        $rep = $own->fetchColumn();
        if ($rep === false) { throw new Exception('Case not found.'); }
        
        // Get the marshal's user ID to check permissions
        $marshalUserStmt = $pdo->prepare('SELECT user_id FROM marshal WHERE id = ?');
        $marshalUserStmt->execute([$rep]);
        $marshalUserId = $marshalUserStmt->fetchColumn();
        
        // Check if current user is the marshal who created the case
        if ($marshalUserId != $currentUserId) {
          throw new Exception('You are not allowed to delete this case.');
        }
        $del = $pdo->prepare('DELETE FROM cases WHERE id = ?');
        $del->execute([$case_id]);
        if ($isAjax) {
          echo json_encode([
            'success' => true,
            'message' => 'Report deleted successfully',
            'redirect' => basename($_SERVER['PHP_SELF']) . '?' . http_build_query($_GET)
          ]);
          exit;
        } else {
          $_SESSION['flash'] = flash('success', 'Report deleted successfully.');
          header('Location: ' . basename($_SERVER['PHP_SELF']) . '?' . http_build_query($_GET));
          exit;
        }
      }
    }
  } catch (Exception $e) {
    if ($isAjax) {
      header('Content-Type: application/json');
      http_response_code(400);
      echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
      ]);
      exit;
    } else {
      $_SESSION['flash'] = flash('error', $e->getMessage());
      header('Location: ' . basename($_SERVER['PHP_SELF']));
      exit;
    }
  }
  // Get filter values from request
  $q = trim($_GET['q'] ?? '');
  $statusFilter = $_GET['status'] ?? '';
  $date_from = $_GET['date_from'] ?? '';
  $date_to = $_GET['date_to'] ?? '';
  $courseFilter = $_GET['course'] ?? '';
  $params = [];
  $where = [];
  
  // Get marshal data for the current user, including department_id
  $currentMarshalId = null;
  $marshalStmt = $pdo->prepare('SELECT id, department_id FROM marshal WHERE user_id = ?');
  $marshalStmt->execute([$currentUserId]);
  $marshalData = $marshalStmt->fetch(PDO::FETCH_ASSOC);
  
  // Debug: Log marshal data
  error_log("Marshal data: " . print_r($marshalData, true));
  
  if ($marshalData) {
    $currentMarshalId = $marshalData['id'];
    $staffDeptId = $marshalData['department_id'] ?? null;
  }
  
  // Search filter
  if ($q !== '') {
    $where[] = '(ca.case_number LIKE ? OR ca.title LIKE ? OR s.first_name LIKE ? OR s.last_name LIKE ? OR vt.name LIKE ?)';
    $like = "%" . $q . "%";
    array_push($params, $like, $like, $like, $like, $like);
  }
  
  // Status filter
  if ($statusFilter !== '' && ctype_digit($statusFilter)) {
    $where[] = 'ca.status_id = ?';
    $params[] = (int)$statusFilter;
  }
  
  // Date range filter
  if ($date_from !== '') { 
    $where[] = 'DATE(ca.incident_date) >= ?'; 
    $params[] = $date_from; 
  }
  if ($date_to !== '') { 
    $where[] = 'DATE(ca.incident_date) <= ?'; 
    $params[] = $date_to; 
  }
  
  // Course filter
  if ($courseFilter !== '' && ctype_digit($courseFilter)) {
    $where[] = 's.course_id = ?';
    $params[] = (int)$courseFilter;
  }
  // Initialize variables
  $staffDeptId = null;
  $courses = [];
  
  // Check if we have marshal data from earlier
  if (isset($marshalData) && is_array($marshalData) && !empty($marshalData)) {
    // Get department_id directly from marshal record if it exists
    $staffDeptId = isset($marshalData['department_id']) ? (int)$marshalData['department_id'] : null;
    
    if (!empty($staffDeptId)) {
      try {
        // Get courses for the marshal's department
        $courseQuery = "
          SELECT id, course_name 
          FROM courses 
          WHERE department_id = ?
          ORDER BY course_name
        ";
        
        $courseStmt = $pdo->prepare($courseQuery);
        $courseStmt->execute([$staffDeptId]);
        $courses = $courseStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        
        error_log("Found " . count($courses) . " courses for department " . $staffDeptId);
        
        // Debug output
        error_log("Marshal User ID: " . $currentUserId);
        error_log("Marshal ID: " . $marshalData['id']);
        error_log("Department ID: " . $staffDeptId);
        
      } catch (Exception $e) {
        error_log("Error fetching courses: " . $e->getMessage());
      }
    } else {
      error_log("No department assigned to marshal ID: " . $marshalData['id']);
    }
  }
  // Build params starting with the current user's id for m.user_id = ?
  // Note: we'll prepend this when executing the final query
  
  $cases = [];
  try {
    // Enable error reporting for debugging
    error_reporting(E_ALL);
    ini_set('display_errors', $isAjax ? 0 : 1); // Don't output errors for AJAX requests
    
    // Debug: Log the current user ID and staff ID
    error_log("Current User ID: " . $currentUserId);
    error_log("Current Staff ID: " . ($currentStaffId ?? 'null'));
    
    // First, let's try to fetch all cases without any filters to verify the connection
    $testQuery = "SELECT ca.id, ca.case_number, ca.title, ca.created_at, 
                         s.first_name, s.last_name, d.department_name
                  FROM cases ca
                  JOIN students s ON ca.student_id = s.id
                  JOIN courses co ON s.course_id = co.id
                  JOIN departments d ON co.department_id = d.id
                  ORDER BY ca.created_at DESC";
    
    $stmt = $pdo->prepare($testQuery);
    $stmt->execute();
    $testCases = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Log the results
    error_log("Test Query Results: " . print_r($testCases, true));
    
  } catch (PDOException $e) {
    error_log("Database error in manage-report.php: " . $e->getMessage());
    $error = $e->getMessage();
    if ($isAjax) {
      header('Content-Type: application/json');
      http_response_code(500);
      echo json_encode(['success' => false, 'error' => 'A database error occurred']);
      exit;
    }
  } catch (Exception $e) {
    error_log("Error in manage-report.php: " . $e->getMessage());
    $error = $e->getMessage();
    if ($isAjax) {
      header('Content-Type: application/json');
      http_response_code(500);
      echo json_encode(['success' => false, 'error' => 'An error occurred']);
      exit;
    }
  }
  
  // Build the base query per user's requested SQL, plus extra fields needed by Edit modal
  $sql = "SELECT 
    ca.id AS case_id,
    ca.case_number,
    ca.title AS report_title,
    ca.description AS report_description,
    cs.name AS case_status,
    s.first_name,
    s.last_name,
    s.student_number,
    co.course_code,
    co.course_name,
    d.department_name,
    vt.name AS violation_name,
    vt.code AS violation_code,
    ca.is_confidential,
    ca.created_at AS report_date,
    ca.student_id,
    ca.violation_type_id,
    ca.location,
    ca.incident_date,
    ca.status_id
  FROM cases ca
  JOIN students s ON ca.student_id = s.id
  JOIN courses co ON s.course_id = co.id
  JOIN departments d ON co.department_id = d.id
  JOIN marshal m ON d.id = m.department_id
  LEFT JOIN case_status cs ON ca.status_id = cs.id
  LEFT JOIN violation_types vt ON vt.id = ca.violation_type_id
  WHERE m.user_id = ?";

  // Add filter conditions if any
  if (!empty($where)) {
    $sql .= " AND " . implode(" AND ", $where);
  }

  // Add department filter if staff has an assigned department
  if (!empty($staffDeptId)) {
    $sql .= " AND d.id = ?";
    $params[] = $staffDeptId;
  }

  // Add ORDER BY
  $sql .= " ORDER BY ca.created_at DESC";
  
  // Debug: Log the final query and parameters
  error_log("Final Query: " . $sql);
  error_log("Query Parameters (excluding initial user_id): " . print_r($params, true));
  
  try {
    // Prepend current user id for m.user_id = ?
    $execParams = array_merge([$currentUserId], $params);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($execParams);
    $cases = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    
    // Log the number of cases found
    error_log("Number of cases found: " . count($cases));
    // Debug output
  if (isset($_GET['debug'])) {
    echo "<pre>";
    echo "Number of cases: " . count($cases) . "\n";
    print_r($cases);
    echo "</pre>";
  }
    // If no cases found, log available tables for debugging
    if (empty($cases)) {
      error_log("No cases found with the main query");
      $testTables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
      error_log("Available tables: " . print_r($testTables, true));
    }
  } catch (PDOException $e) {
    error_log("Database error in manage-report.php: " . $e->getMessage());
    $cases = [];
    if ($isAjax) {
      header('Content-Type: application/json');
      http_response_code(500);
      echo json_encode(['success' => false, 'error' => 'A database error occurred']);
      exit;
    }
  } catch (Exception $e) {
    error_log("Error in manage-report.php: " . $e->getMessage());
    $cases = [];
    if ($isAjax) {
      header('Content-Type: application/json');
      http_response_code(500);
      echo json_encode(['success' => false, 'error' => 'An error occurred']);
      exit;
    }
  }
  $pageTitle = 'Manage Reports - Staff - SDMS';
  include __DIR__ . '/../../components/staff-head.php';
?>
<div class="min-h-screen flex">
  <?php include __DIR__ . '/../../components/staff-sidebar.php'; ?>
  <main class="flex-1 ml-0 md:ml-64">
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
      
      <!-- Debug Output -->
      <?php if (isset($_GET['debug'])): ?>
        <div class="mb-4 p-4 bg-yellow-50 border-l-4 border-yellow-400">
          <h3 class="font-bold text-yellow-800">Debug Information</h3>
          <pre class="text-xs mt-2 p-2 bg-white border rounded overflow-auto">
User ID: <?php echo $currentUserId; ?>
Staff ID: <?php echo $currentStaffId; ?>
Department ID: <?php echo $staffDeptId ?? 'Not set'; ?>

Courses (<?php echo count($courses); ?>):
<?php print_r($courses); ?>

SQL Query:
<?php echo isset($courseQuery) ? htmlspecialchars($courseQuery) : 'Not set'; ?>

Params: user_id=<?php echo $currentUserId; ?>
          </pre>
        </div>
      <?php endif; ?>
      
      <div class="bg-white border rounded-lg overflow-hidden">
        <div class="p-4 border-b bg-gray-50">
          <div class="flex items-center justify-between">
            <h3 class="text-lg font-medium text-gray-800">Filter Reports</h3>
            <button type="button" onclick="toggleFilters()" class="md:hidden text-gray-500 hover:text-gray-700">
              <i class="fa-solid fa-filter"></i>
            </button>
          </div>
        </div>
        
        <form method="get" class="p-4 space-y-4" id="filterForm">
          <!-- Search and Basic Filters -->
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <!-- Search -->
            <div class="md:col-span-2">
              <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
              <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                  <i class="fa-solid fa-search text-gray-400"></i>
                </div>
                <input type="text" name="q" value="<?php echo e($q); ?>" 
                       placeholder="Case #, title, student, violation" 
                       class="pl-10 w-full border-gray-300 rounded-lg focus:ring-primary focus:border-primary" />
              </div>
            </div>
            
            <!-- Status -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
              <select name="status" class="w-full border-gray-300 rounded-lg focus:ring-primary focus:border-primary">
                <option value="">All Status</option>
                <?php foreach ($caseStatuses as $st): $sid=(int)$st['id']; ?>
                  <option value="<?php echo $sid; ?>" <?php echo ($statusFilter !== '' && (int)$statusFilter===$sid)?'selected':''; ?>>
                    <?php echo e($st['name']); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          
          <!-- Advanced Filters (initially hidden on mobile) -->
          <div id="advancedFilters" class="space-y-4 hidden md:block">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 pt-2 border-t border-gray-200">
              <!-- Date Range -->
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Date Range</label>
                <div class="grid grid-cols-2 gap-2">
                  <div>
                    <input type="date" name="date_from" value="<?php echo e($date_from); ?>" 
                           class="w-full border-gray-300 rounded-lg focus:ring-primary focus:border-primary text-sm" 
                           placeholder="From" />
                  </div>
                  <div>
                    <input type="date" name="date_to" value="<?php echo e($date_to); ?>" 
                           class="w-full border-gray-300 rounded-lg focus:ring-primary focus:border-primary text-sm" 
                           placeholder="To" />
                  </div>
                </div>
              </div>
              
              <!-- Course Filter -->
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Course</label>
                <?php if (!empty($courses)): ?>
                  <select name="course" class="w-full border-gray-300 rounded-lg focus:ring-primary focus:border-primary">
                    <option value="">All Courses</option>
                    <?php foreach ($courses as $course): ?>
                      <option value="<?php echo $course['id']; ?>" <?php echo ($courseFilter == $course['id']) ? 'selected' : ''; ?>>
                        <?php echo e($course['course_name']); ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                <?php else: ?>
                  <select disabled class="w-full border-gray-300 rounded-lg bg-gray-100 text-gray-500">
                    <option>No courses available</option>
                  </select>
                <?php endif; ?>
              </div>
              
              <!-- Action Buttons -->
              <div class="flex items-end">
                <div class="flex space-x-2 w-full">
                  <button type="submit" class="flex-1 inline-flex justify-center items-center gap-2 px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 transition-colors">
                    <i class="fa-solid fa-filter"></i>
                    <span>Apply Filters</span>
                  </button>
                  <a href="manage-report.php" class="inline-flex items-center gap-2 px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                    <i class="fa-solid fa-rotate-left"></i>
                    <span class="hidden sm:inline">Reset</span>
                  </a>
                </div>
              </div>
            </div>
          </div>
          
          <!-- Mobile Toggle Button -->
          <div class="pt-2 border-t border-gray-200 md:hidden">
            <button type="button" onclick="toggleFilters()" class="w-full flex items-center justify-between text-sm font-medium text-primary hover:text-primary/80">
              <span>Advanced Filters</span>
              <i class="fa-solid fa-chevron-down text-xs transition-transform" id="filterToggleIcon"></i>
            </button>
          </div>
        </form>
        
        <script>
        // Toggle advanced filters on mobile
        function toggleFilters() {
          const filters = document.getElementById('advancedFilters');
          const icon = document.getElementById('filterToggleIcon');
          
          if (filters.classList.contains('hidden')) {
            filters.classList.remove('hidden');
            icon.classList.add('rotate-180');
          } else {
            filters.classList.add('hidden');
            icon.classList.remove('rotate-180');
          }
        }
        
        // Initialize filters based on screen size
        function initFilters() {
          const filters = document.getElementById('advancedFilters');
          if (window.innerWidth >= 768) { // md breakpoint
            filters.classList.remove('hidden');
          } else {
            filters.classList.add('hidden');
          }
        }
        
        // Run on load and on resize
        document.addEventListener('DOMContentLoaded', initFilters);
        window.addEventListener('resize', initFilters);
        </script>
      </div>
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
                    $incident = $c['report_date'] ? date('Y-m-d H:i', strtotime($c['report_date'])) : '-';
                    $statusName = $c['case_status'] ?? 'Pending';
                  ?>
                  <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 align-top">
                      <div class="font-medium"><?php echo e($caseLabel); ?></div>
                      <div class="text-xs text-gray-500 truncate max-w-xs"><?php echo e($c['report_title'] ?? ''); ?></div>
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
                        <?php 
                          $caseData = [
                            'id' => (int)($c['case_id'] ?? 0),
                            'student_id' => (int)($c['student_id'] ?? 0),
                            'student_name' => trim(($c['last_name'] ?? '') . ', ' . ($c['first_name'] ?? '') . ' (' . ($c['student_number'] ?? '') . ')'),
                            'violation_type_id' => (int)($c['violation_type_id'] ?? 0),
                            'title' => (string)($c['report_title'] ?? ''),
                            'description' => (string)($c['report_description'] ?? ''),
                            'location' => (string)($c['location'] ?? ''),
                            'incident_date' => $c['incident_date'] ? date('Y-m-d\TH:i', strtotime($c['incident_date'])) : '',
                            'status_id' => (int)($c['status_id'] ?? 0),
                            'is_confidential' => (int)($c['is_confidential'] ?? 0)
                          ];
                          $jsonData = json_encode($caseData, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT);
                        ?>
                        <button type="button" class="text-blue-600 hover:underline" 
                          onclick='openEditModal(<?php echo htmlspecialchars($jsonData, ENT_QUOTES, 'UTF-8'); ?>)'>Edit</button>
                        <form method="post" action="" class="inline" onsubmit="return confirmDelete(event);">
                          <input type="hidden" name="csrf" value="<?php echo $csrf; ?>" />
                          <input type="hidden" name="action" value="delete_report" />
                          <input type="hidden" name="case_id" value="<?php echo (int)$c['case_id']; ?>" />
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
<div id="editModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
  <div class="bg-white w-full max-w-2xl rounded-lg shadow-lg p-6">
    <div class="flex items-center justify-between mb-4">
      <h2 class="text-lg font-semibold">Edit Report</h2>
      <button onclick="closeEditModal()" class="text-gray-500 hover:text-gray-700"><i class="fa fa-times"></i></button>
    </div>
    <form id="editReportForm" method="post" onsubmit="return submitEditForm(event)">
      <input type="hidden" name="csrf" value="<?php echo $csrf; ?>" />
      <input type="hidden" name="action" value="update_report" />
      <input type="hidden" name="case_id" id="edit_case_id" value="" />
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium mb-1">Student</label>
          <input type="hidden" name="student_id" id="edit_student_id" value="" />
          <div class="w-full border rounded px-3 py-2 bg-gray-100 text-gray-700" id="edit_student_display">
            <!-- Student name will be populated by JavaScript -->
          </div>
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Violation<span class="text-rose-600">*</span></label>
          <select name="violation_type_id" id="edit_violation_type_id" class="w-full border rounded px-3 py-2 required-field" required>
            <option value="">Select a violation</option>
            <?php foreach ($violationTypes as $vt): ?>
              <option value="<?php echo (int)$vt['id']; ?>"><?php echo e($vt['label']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="md:col-span-2">
          <label class="block text-sm font-medium mb-1">Title<span class="text-rose-600">*</span></label>
          <input type="text" name="title" id="edit_title" class="w-full border rounded px-3 py-2 required-field" required />
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
          <input type="datetime-local" name="incident_date" id="edit_incident_date" class="w-full border rounded px-3 py-2 required-field" required />
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
        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded">Save Changes</button>
      </div>
    </form>
  </div>
</div>
<script>
  document.addEventListener('DOMContentLoaded', () => {
    const flash = document.getElementById('flashData');
    if (flash && window.Swal) {
      const type = flash.getAttribute('data-type') || 'info';
      const msg = JSON.parse(flash.getAttribute('data-msg') || '""');
      Swal.fire({ icon: type, title: type === 'success' ? 'Success' : 'Error', text: msg });
    }
  });

  // Function to open edit modal with case data
  function openEditModal(data) {
    try {
      // Ensure data is an object (it might be a JSON string or already parsed)
      let caseData;
      if (typeof data === 'string') {
        try {
          caseData = JSON.parse(data);
        } catch (e) {
          console.error('Failed to parse JSON data:', data);
          throw new Error('Invalid case data format');
        }
      } else {
        caseData = data;
      }
      
      // Debug log
      console.log('Opening edit modal with data:', caseData);
      
      document.getElementById('edit_case_id').value = caseData.id || '';
      // Set student ID and display name
      const studentId = caseData.student_id || '';
      document.getElementById('edit_student_id').value = studentId;
      
      // Display the student's name from the case data
      if (caseData.student_name) {
        document.getElementById('edit_student_display').textContent = caseData.student_name;
      } else {
        // Fallback to finding the name in the dropdown if available
        const studentSelect = document.querySelector('select[name="student_id"]');
        if (studentSelect) {
          const studentOption = studentSelect.querySelector(`option[value="${studentId}"]`);
          if (studentOption) {
            document.getElementById('edit_student_display').textContent = studentOption.textContent.trim();
          } else {
            document.getElementById('edit_student_display').textContent = `Student ID: ${studentId}`;
          }
        } else {
          document.getElementById('edit_student_display').textContent = `Student ID: ${studentId}`;
        }
      }
      document.getElementById('edit_violation_type_id').value = caseData.violation_type_id || '';
      document.getElementById('edit_title').value = caseData.title || '';
      document.getElementById('edit_description').value = caseData.description || '';
      document.getElementById('edit_location').value = caseData.location || '';
      document.getElementById('edit_incident_date').value = caseData.incident_date || '';
      document.getElementById('edit_status_id').value = caseData.status_id || '';
      document.getElementById('edit_is_confidential').checked = !!caseData.is_confidential;
      const editModal = document.getElementById('editModal');
      if (editModal) {
        editModal.classList.remove('hidden');
        editModal.classList.add('flex');
      }
    } catch (error) {
      console.error('Error opening edit modal:', error, 'Data:', data);
      if (window.Swal) {
        Swal.fire({
          icon: 'error',
          title: 'Error',
          text: 'Failed to load case data. Please try again.'
        });
      } else {
        alert('Failed to load case data. Please try again.');
      }
    }
  }
  
  function closeEditModal() {
    const editModal = document.getElementById('editModal');
    if (editModal) {
      editModal.classList.add('hidden');
      editModal.classList.remove('flex');
    }
  }

  // Make sure this is at the top level
  async function submitEditForm(event) {
    event.preventDefault();
    const form = event.target;
    
    // Debug: Log form data
    console.log('Form submission started');
    
    // Clear previous error states
    form.querySelectorAll('.border-red-500').forEach(el => {
      el.classList.remove('border-red-500');
      console.log('Cleared error state for:', el.name);
    });
    
    // Get all form controls that are required
    const requiredFields = [
      { element: document.getElementById('edit_violation_type_id'), name: 'violation_type_id' },
      { element: document.getElementById('edit_title'), name: 'title' },
      { element: document.getElementById('edit_incident_date'), name: 'incident_date' }
      // student_id is not required as it's automatically populated
    ];
    
    let isValid = true;
    const formData = new FormData(form);
    
    // Ensure student_id is included in form data
    const studentId = document.getElementById('edit_student_id').value;
    if (studentId && !formData.has('student_id')) {
      formData.set('student_id', studentId);
    }
    
    // Debug: Log current form values
    console.log('Current form values:');
    for (let [key, value] of formData.entries()) {
      console.log(key, '=', value);
    }
    
    // Validate each required field
    const missingFields = [];
    
    for (const field of requiredFields) {
      const value = formData.get(field.name)?.toString().trim() || '';
      const fieldLabel = field.element.labels?.[0]?.textContent.trim() || field.name;
      console.log(`Validating ${field.name} (${fieldLabel}):`, value);
      
      if (!value) {
        console.error(`❌ Missing required field: ${field.name} (${fieldLabel})`);
        field.element.classList.add('border-red-500');
        missingFields.push(fieldLabel.replace('*', '').trim());
        isValid = false;
      } else {
        console.log(`✅ ${field.name} is valid`);
      }
    }
    
    if (missingFields.length > 0) {
      console.group('Missing Required Fields');
      console.log('%cThe following fields are required but empty:', 'color: #ef4444; font-weight: bold');
      missingFields.forEach(field => console.log(`• ${field}`));
      console.groupEnd();
    }
    
    if (!isValid) {
      const errorMessage = 'Please fill in all required fields marked with *';
      console.log('Validation failed:', errorMessage);
      
      if (window.Swal) {
Swal.fire({
          icon: 'error',
          title: 'Validation Error',
          text: errorMessage,
          timer: 5000,
          showConfirmButton: true
        }).then(() => {
          // After alert is closed, focus the first error field
          const firstError = form.querySelector('.border-red-500');
          if (firstError) {
            firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
            firstError.focus();
          }
        });
      } else {
        alert(errorMessage);
        const firstError = form.querySelector('.border-red-500');
        if (firstError) firstError.focus();
      }
      
      return false;
    }
    
    // Ensure all required fields are included in FormData
    requiredFields.forEach(field => {
      if (!formData.has(field.name)) {
        formData.append(field.name, field.value);
      }
    });
    
    // Add a timestamp to prevent caching
    formData.append('_', Date.now());
    
    try {
      const response = await fetch('', {
        method: 'POST',
        body: formData,
        headers: {
          'X-Requested-With': 'XMLHttpRequest'
        }
      });
      
      const result = await response.json();
      
      if (result.success) {
        if (window.Swal) {
  Swal.fire({
            icon: 'success',
            title: 'Success',
            text: result.message || 'Report updated successfully',
            timer: 2000,
            showConfirmButton: false
          });
        } else {
          alert(result.message || 'Report updated successfully');
        }
        closeEditModal();
        window.location.reload();
      } else {
        throw new Error(result.message || 'Failed to update report');
      }
    } catch (error) {
      console.error('Error:', error);
      if (window.Swal) {
        Swal.fire({
          icon: 'error',
          title: 'Error',
          text: error.message || 'An error occurred while updating the report'
        });
      } else {
        alert(error.message || 'An error occurred while updating the report');
      }
    }
    
    return false;
  }

  async function confirmDelete(event) {
    event.preventDefault();
    const form = event.target.closest('form');
    const caseId = form.querySelector('input[name="case_id"]')?.value;
    
    if (!caseId) {
      console.error('No case ID found for deletion');
      if (window.Swal) {
        Swal.fire('Error', 'No case ID found for deletion', 'error');
      } else {
        alert('No case ID found for deletion');
      }
      return false;
    }
    
    try {
      const confirmed = await (window.Swal ? 
        Swal.fire({
          title: 'Delete Report?',
          text: 'This action cannot be undone.',
          icon: 'warning',
          showCancelButton: true,
          confirmButtonColor: '#dc2626',
          confirmButtonText: 'Delete',
          cancelButtonText: 'Cancel',
          reverseButtons: true
        }) : 
        confirm('Delete this report? This action cannot be undone.')
      );

      if (!(window.Swal ? confirmed.isConfirmed : confirmed)) {
        return false;
      }

      const formData = new FormData();
      formData.append('csrf', document.querySelector('input[name="csrf"]').value);
      formData.append('action', 'delete_report');
      formData.append('case_id', caseId);

      const response = await fetch('', {
        method: 'POST',
        body: formData,
        headers: {
          'X-Requested-With': 'XMLHttpRequest'
        }
      });

      // Check if response is JSON
      const contentType = response.headers.get('content-type');
      if (contentType && contentType.includes('application/json')) {
        const result = await response.json();
        if (result.success) {
          if (window.Swal) {
            Swal.fire({
              icon: 'success',
              title: 'Success',
              text: result.message || 'Report deleted successfully',
              timer: 2000,
              showConfirmButton: false
            });
          }
          window.location.reload();
          return false;
        } else {
          throw new Error(result.message || 'Failed to delete report');
        }
      } else {
        // If not JSON, it's probably an HTML error page
        const text = await response.text();
        console.error('Unexpected response:', text);
        throw new Error('Server returned an invalid response');
      }
    } catch (error) {
      console.error('Error deleting report:', error);
      if (window.Swal) {
        Swal.fire({
          icon: 'error',
          title: 'Error',
          text: error.message || 'An error occurred while deleting the report'
        });
      } else {
        alert(error.message || 'An error occurred while deleting the report');
      }
    }
    return false;
  }
</script>

<?php include __DIR__ . '/../../components/staff-footer.php'; ?>