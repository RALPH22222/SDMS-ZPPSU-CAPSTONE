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
  $department_id = isset($_GET['department_id']) && $_GET['department_id'] !== '' ? (int)$_GET['department_id'] : null;
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

  // Get departments for filter dropdown
  $deptStmt = $pdo->query('SELECT id, department_name, abbreviation FROM departments ORDER BY department_name');
  $departments = $deptStmt->fetchAll(PDO::FETCH_ASSOC);

  // Build query
  $where = [];
  $params = [];

  if ($q !== '') {
    $where[] = '(c.case_number LIKE ? OR c.title LIKE ? OR s.first_name LIKE ? OR s.last_name LIKE ?)';
    $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%";
  }
  if ($status_id !== null) { $where[] = 'c.status_id = ?'; $params[] = $status_id; }
  if ($violation_type_id !== null) { $where[] = 'c.violation_type_id = ?'; $params[] = $violation_type_id; }
  if ($department_id !== null) { $where[] = 'd.id = ?'; $params[] = $department_id; }
  if ($date_from !== '') { $where[] = 'DATE(c.incident_date) >= ?'; $params[] = $date_from; }
  if ($date_to !== '') { $where[] = 'DATE(c.incident_date) <= ?'; $params[] = $date_to; }
  if ($conf === 0) { $where[] = 'c.is_confidential = 0'; }
  if ($conf === 1) { $where[] = 'c.is_confidential = 1'; }

  $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

  // Count total
  $countSql = "SELECT COUNT(*)
               FROM cases c
               JOIN students s ON s.id = c.student_id
               JOIN courses co ON co.id = s.course_id
               JOIN departments d ON d.id = co.department_id
               JOIN violation_types vt ON vt.id = c.violation_type_id
               JOIN case_status st ON st.id = c.status_id
               LEFT JOIN users rf ON rf.id = c.reported_by_marshal_id
               $whereSql";
  $stmt = $pdo->prepare($countSql);
  $stmt->execute($params);
  $total = (int)$stmt->fetchColumn();
  $totalPages = max(1, (int)ceil($total / $pageSize));
  if ($page > $totalPages) { $page = $totalPages; $offset = ($page - 1) * $pageSize; }

  // Fetch page
  $sql = "SELECT
            c.id, c.case_number, c.title, c.incident_date, c.is_confidential, c.status_id,
            CONCAT(s.last_name, ', ', s.first_name, IFNULL(CONCAT(' ', s.middle_name), ''), IF(s.suffix IS NOT NULL, CONCAT(' ', s.suffix), '')) AS student_name,
            s.student_number,
            vt.name AS violation_type,
            st.name AS status_name,
            rf.username AS r_username,
            d.department_name,
            d.abbreviation AS dept_abbr
          FROM cases c
          JOIN students s ON s.id = c.student_id
          JOIN courses co ON co.id = s.course_id
          JOIN departments d ON d.id = co.department_id
          JOIN violation_types vt ON vt.id = c.violation_type_id
          JOIN case_status st ON st.id = c.status_id
          LEFT JOIN users rf ON rf.id = c.reported_by_marshal_id
          $whereSql
          ORDER BY c.incident_date DESC
          LIMIT $pageSize OFFSET $offset";
  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);
  $cases = $stmt->fetchAll(PDO::FETCH_ASSOC);
  
  // Debug information
  error_log("SQL Query: " . $sql);
  error_log("Query Parameters: " . print_r($params, true));
  error_log("Number of cases found: " . count($cases));
  if (count($cases) === 0) {
    error_log("No cases found. Checking if tables exist...");
    try {
      $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
      error_log("Available tables: " . print_r($tables, true));
      
      // Check if cases table exists
      if (in_array('cases', $tables)) {
        $caseCount = $pdo->query("SELECT COUNT(*) FROM cases")->fetchColumn();
        error_log("Total cases in database: " . $caseCount);
      }
    } catch (PDOException $e) {
      error_log("Error checking database: " . $e->getMessage());
    }
  }
?>

<?php $pageTitle = 'Manage Cases - SDMS'; include_once __DIR__ . '/../../components/admin-head.php'; ?>

<div class="min-h-screen md:pl-64">
  <!-- Mobile header -->
  <div class="md:hidden sticky top-0 z-40 bg-white border-b border-gray-200">
    <div class="h-16 flex items-center px-4">
      <button id="adminSidebarToggle" class="text-primary text-2xl mr-3">
        <i class="fa-solid fa-bars"></i>
      </button>
      <div class="text-primary font-bold">Manage Cases</div>
    </div>
  </div>

  <?php include_once __DIR__ . '/../../components/admin-sidebar.php'; ?>

  <main class="px-4 md:px-8 py-6">
    <div class="max-w-7xl mx-auto">
      <div class="flex items-center gap-3 mb-6">
        <i class="fa-solid fa-folder-open text-primary text-2xl"></i>
        <h1 class="text-2xl md:text-3xl font-bold text-primary">Manage Cases</h1>
      </div>

      <?php if ($flash['type']): ?>
        <div class="hidden" id="flashData"
             data-type="<?php echo $flash['type']==='success'?'success':'error'; ?>"
             data-msg='<?php echo json_encode($flash["msg"]); ?>'></div>
      <?php endif; ?>
      
      <?php 
      // Debug information
      if (empty($cases)): 
        $error_info = [];
        try {
          $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
          $error_info[] = "<strong>Available tables:</strong> " . (empty($tables) ? 'None' : implode(', ', $tables));
          
          if (in_array('cases', $tables)) {
            $caseCount = $pdo->query("SELECT COUNT(*) FROM cases")->fetchColumn();
            $error_info[] = "<strong>Total cases in database:</strong> $caseCount";
          } else {
            $error_info[] = "<strong>Error:</strong> 'cases' table does not exist in the database.";
          }
          
          // Check required tables
          $required_tables = ['students', 'violation_types', 'case_status', 'staff'];
          $missing_tables = array_diff($required_tables, $tables);
          if (!empty($missing_tables)) {
            $error_info[] = "<strong>Missing required tables:</strong> " . implode(', ', $missing_tables);
          }
          
        } catch (PDOException $e) {
          $error_info[] = "<strong>Database Error:</strong> " . $e->getMessage();
        }
      ?>
      <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-4">
        <div class="flex">
          <div class="flex-shrink-0">
            <i class="fas fa-exclamation-triangle text-yellow-400"></i>
          </div>
          <div class="ml-3">
            <h3 class="text-sm font-medium text-yellow-800">No cases found. Here's what we found:</h3>
            <div class="mt-2 text-sm text-yellow-700">
              <ul class="list-disc pl-5 space-y-1">
                <?php foreach ($error_info as $info): ?>
                  <li><?php echo $info; ?></li>
                <?php endforeach; ?>
              </ul>
            </div>
          </div>
        </div>
      </div>
      <?php endif; ?>

      <!-- Filters -->
      <form method="get" class="bg-white border border-gray-200 rounded-lg p-4 mb-4">
        <div class="grid grid-cols-1 md:grid-cols-6 gap-3">
          <div class="md:col-span-2">
            <label class="block text-sm font-medium mb-1">Search</label>
            <input type="text" name="q" value="<?php echo htmlspecialchars($q); ?>" placeholder="Case #, title, student" class="w-full border rounded px-3 py-2" />
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Department</label>
            <select name="department_id" class="w-full border rounded px-3 py-2">
              <option value="">All Departments</option>
              <?php foreach ($departments as $dept): ?>
              <option value="<?php echo $dept['id']; ?>" <?php echo (isset($department_id) && $department_id == $dept['id']) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($dept['department_name']); ?>
              </option>
              <?php endforeach; ?>
            </select>
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
      <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
        <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Case</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Student</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Department</th>
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
                  <div class="text-dark font-medium"><?php echo htmlspecialchars($c['student_name']); ?></div>
                  <div class="text-xs text-gray-600">ID: <?php echo htmlspecialchars($c['student_number']); ?></div>
                </td>
                <td class="px-4 py-3 text-sm">
                  <span class="text-sm text-gray-700"><?php echo htmlspecialchars($c['dept_abbr'] ?? 'N/A'); ?></span>
                  <div class="text-xs text-gray-500"><?php echo htmlspecialchars($c['department_name'] ?? ''); ?></div>
                </td>
                <td class="px-4 py-3 text-sm">
                  <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                    <?php echo htmlspecialchars($c['violation_type']); ?>
                  </span>
                </td>
                <td class="px-4 py-3 text-sm">
                  <?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($c['incident_date']))); ?><br>
                  <span class="text-xs text-gray-600">Reported by: <?php echo htmlspecialchars($c['r_username'] ?? '—'); ?></span>
                </td>
                <td class="px-4 py-3 text-sm">
                  <span class="inline-flex items-center px-2 py-0.5 rounded text-xs <?php echo $isResolved ? 'bg-green-50 text-green-700' : 'bg-amber-50 text-amber-700'; ?>">
                    <?php echo htmlspecialchars($c['status_name']); ?>
                  </span>
                </td>
                <td class="px-4 py-3 text-sm text-right">
                  <div class="inline-flex gap-2">
                    <!-- View Case Details Button -->
                    <button class="text-blue-600 hover:underline" onclick='openCaseDetails(<?php echo json_encode([
                      'id' => (int)$c['id'],
                      'case_number' => $c['case_number'],
                      'title' => $c['title'],
                      'student' => $c['s_ln'] . ', ' . $c['s_fn'],
                      'student_number' => $c['student_number'],
                      'violation' => $c['v_name'] . ' (' . $c['v_code'] . ')',
                      'incident_date' => date('Y-m-d H:i', strtotime($c['incident_date'])),
                      'location' => $c['location'],
                      'description' => $c['description'],
                      'status' => $c['status_name'],
                      'is_confidential' => (bool)$c['is_confidential'],
                      'reported_by' => trim(($c['r_ln'] ?? '') . ', ' . ($c['r_fn'] ?? '')) ?: '—',
                      'resolution' => $c['resolution'],
                      'resolution_date' => $c['resolution_date'] ? date('Y-m-d H:i', strtotime($c['resolution_date'])) : null
                    ], JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT); ?>)'>View</button>
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
  
  <!-- Case Details Modal -->
  <div id="caseDetailsModal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-hidden="true">
    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
      <!-- Background overlay -->
      <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" onclick="closeCaseDetails()"></div>
      
      <!-- Modal panel -->
      <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full">
        <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
          <div class="sm:flex sm:items-start">
            <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
              <div class="flex justify-between items-start">
                <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">
                  Case Details: <span id="caseNumber"></span>
                  <span id="caseConfidential" class="ml-2 text-xs px-2 py-0.5 bg-red-50 text-red-600 rounded hidden">Confidential</span>
                </h3>
                <button type="button" class="text-gray-400 hover:text-gray-500" onclick="closeCaseDetails()">
                  <span class="sr-only">Close</span>
                  <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                  </svg>
                </button>
              </div>
              <div class="mt-4">
                <h4 class="text-lg font-medium text-gray-900 mb-2" id="caseTitle"></h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                  <div>
                    <p class="text-gray-500">Student</p>
                    <p class="font-medium" id="caseStudent"></p>
                  </div>
                  <div>
                    <p class="text-gray-500">Student ID</p>
                    <p class="font-medium" id="caseStudentNumber"></p>
                  </div>
                  <div>
                    <p class="text-gray-500">Violation</p>
                    <p class="font-medium" id="caseViolation"></p>
                  </div>
                  <div>
                    <p class="text-gray-500">Incident Date</p>
                    <p class="font-medium" id="caseIncidentDate"></p>
                  </div>
                  <div>
                    <p class="text-gray-500">Location</p>
                    <p class="font-medium" id="caseLocation"></p>
                  </div>
                  <div>
                    <p class="text-gray-500">Status</p>
                    <p class="font-medium"><span id="caseStatus"></span></p>
                  </div>
                  <div>
                    <p class="text-gray-500">Reported By</p>
                    <p class="font-medium" id="caseReportedBy"></p>
                  </div>
                </div>
                <div class="mt-4">
                  <p class="text-gray-500 mb-1">Description</p>
                  <div class="bg-gray-50 p-3 rounded" id="caseDescription"></div>
                </div>
                <div class="mt-4 hidden" id="resolutionSection">
                  <p class="text-gray-500 mb-1">Resolution</p>
                  <div class="bg-green-50 p-3 rounded" id="caseResolution"></div>
                  <p class="text-sm text-gray-500 mt-1">Resolved on: <span id="caseResolutionDate"></span></p>
                </div>
              </div>
              
              <!-- Case Details Content -->
              <div class="mt-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                  <div>
                    <p class="text-gray-500">Case Title</p>
                    <p class="font-medium" id="caseTitle"></p>
                  </div>
                  <div>
                    <p class="text-gray-500">Student</p>
                    <p class="font-medium" id="caseStudent"></p>
                  </div>
                  <div>
                    <p class="text-gray-500">Student ID</p>
                    <p class="font-medium" id="caseStudentNumber"></p>
                  </div>
                  <div>
                    <p class="text-gray-500">Violation</p>
                    <p class="font-medium" id="caseViolation"></p>
                  </div>
                  <div>
                    <p class="text-gray-500">Incident Date</p>
                    <p class="font-medium" id="caseIncidentDate"></p>
                  </div>
                  <div>
                    <p class="text-gray-500">Location</p>
                    <p class="font-medium" id="caseLocation"></p>
                  </div>
                  <div>
                    <p class="text-gray-500">Status</p>
                    <p class="font-medium"><span id="caseStatus"></span></p>
                  </div>
                  <div>
                    <p class="text-gray-500">Reported By</p>
                    <p class="font-medium" id="caseReportedBy"></p>
                  </div>
                </div>
                <div class="mt-4">
                  <p class="text-gray-500 mb-1">Description</p>
                  <div class="bg-gray-50 p-3 rounded" id="caseDescription"></div>
                </div>
                <div class="mt-4 hidden" id="resolutionSection">
                  <p class="text-gray-500 mb-1">Resolution</p>
                  <div class="bg-green-50 p-3 rounded" id="caseResolution"></div>
                  <p class="text-sm text-gray-500 mt-1">Resolved on: <span id="caseResolutionDate"></span></p>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
          <button type="button" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-primary text-base font-medium text-white hover:bg-primary-dark focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary sm:ml-3 sm:w-auto sm:text-sm" onclick="closeCaseDetails()">
            Close
          </button>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
// Case Details Modal Functions
function openCaseDetails(caseData) {
  console.log('Opening case details:', caseData);
  const modal = document.getElementById('caseDetailsModal');
  
  if (!modal) {
    console.error('Error: Could not find caseDetailsModal element');
    alert('Error: Could not load case details. Please try again.');
    return;
  }
  
  try {
    // Debug: Log all elements we're trying to set
    console.log('Setting modal content for case:', caseData.case_number);
    
    // Helper function to safely set text content
    const setTextContent = (id, text) => {
      const element = document.getElementById(id);
      if (element) {
        element.textContent = text || '—';
      } else {
        console.warn(`Element with ID '${id}' not found`);
      }
    };
    
    // Set all text content
    setTextContent('caseNumber', caseData.case_number);
    setTextContent('caseTitle', caseData.title);
    setTextContent('caseStudent', caseData.student);
    setTextContent('caseStudentNumber', caseData.student_number);
    setTextContent('caseViolation', caseData.violation);
    setTextContent('caseIncidentDate', caseData.incident_date);
    setTextContent('caseLocation', caseData.location || '—');
    
    // Set status with styling
    const statusElement = document.getElementById('caseStatus');
    if (statusElement) {
      statusElement.textContent = caseData.status;
      statusElement.className = 'inline-flex items-center px-2.5 py-0.5 rounded text-xs font-medium ' + 
        (caseData.status && caseData.status.toLowerCase().includes('resolved') 
          ? 'bg-green-100 text-green-800' 
          : 'bg-yellow-100 text-yellow-800');
    }
    
    // Set reported by and description
    setTextContent('caseReportedBy', caseData.reported_by);
    setTextContent('caseDescription', caseData.description || 'No description provided.');
    
    // Handle confidential flag
    const confidentialElement = document.getElementById('caseConfidential');
    if (confidentialElement) {
      confidentialElement.style.display = caseData.is_confidential ? 'inline-block' : 'none';
    }
    
    // Handle resolution
    const resolutionSection = document.getElementById('resolutionSection');
    if (resolutionSection) {
      if (caseData.resolution) {
        resolutionSection.classList.remove('hidden');
        setTextContent('caseResolution', caseData.resolution);
        setTextContent('caseResolutionDate', caseData.resolution_date || '—');
      } else {
        resolutionSection.classList.add('hidden');
      }
    }
    
    // Show modal
    modal.classList.remove('hidden');
    modal.classList.add('block');
    document.body.classList.add('overflow-hidden');
    
    // Force a reflow to ensure animations work
    void modal.offsetWidth;
    
    // Add a small delay to ensure the transition works
    setTimeout(() => {
      modal.classList.add('opacity-100');
    }, 10);
    
  } catch (error) {
    console.error('Error in openCaseDetails:', error);
    alert('An error occurred while loading case details. Please check the console for details.');
  }
}

function closeCaseDetails() {
  const modal = document.getElementById('caseDetailsModal');
  if (modal) {
    // Trigger the closing animation
    modal.classList.remove('opacity-100');
    
    // Wait for the animation to complete before hiding
    setTimeout(() => {
      modal.classList.add('hidden');
      modal.classList.remove('block');
      document.body.classList.remove('overflow-hidden');
    }, 200); // Match this with your CSS transition duration
  }
}

// Initialize modal event listeners when the DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
  const modal = document.getElementById('caseDetailsModal');
  if (modal) {
    // Close when clicking outside the modal content
    modal.addEventListener('click', function(e) {
      if (e.target === this) {
        closeCaseDetails();
      }
    });
    
    // Close button
    const closeBtn = modal.querySelector('button[onclick="closeCaseDetails()"]');
    if (closeBtn) {
      closeBtn.addEventListener('click', closeCaseDetails);
    }
  }
});

document.addEventListener('DOMContentLoaded', function() {
  const sidebar = document.getElementById('adminSidebar');
  const sidebarToggle = document.getElementById('adminSidebarToggle');
  const sidebarOverlay = document.getElementById('sidebarOverlay');
  
  function toggleSidebar() {
    sidebar.classList.toggle('-translate-x-full');
    if (sidebarOverlay) {
      sidebarOverlay.classList.toggle('hidden');
      document.body.classList.toggle('overflow-hidden');
    }
  }

  if (sidebarToggle) {
    sidebarToggle.addEventListener('click', toggleSidebar);
  }
  
  // Close sidebar when clicking on a nav link on mobile
  const navLinks = document.querySelectorAll('#adminSidebar a');
  navLinks.forEach(link => {
    link.addEventListener('click', () => {
      if (window.innerWidth < 768) { // Only on mobile
        toggleSidebar();
      }
    });
  });
});
</script>

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

  // The openCaseDetails function is already defined above with full modal functionality
</script>

<?php include_once __DIR__ . '/../../components/admin-footer.php'; ?>