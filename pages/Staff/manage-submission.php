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
  require_once __DIR__ . '/../../includes/notification_functions.php';
  // Load environment variables from .env via config.php
  $envConfig = __DIR__ . '/../../config.php';
  if (file_exists($envConfig)) {
    require_once $envConfig;
  }
  // PHPMailer (installed via Composer) - optional
  $vendorAutoload = __DIR__ . '/../../vendor/autoload.php';
  if (file_exists($vendorAutoload)) {
    @require_once $vendorAutoload;
  }

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
    $stmt = $pdo->prepare("SELECT s.id, s.student_number, s.first_name, s.last_name, co.course_code 
                          FROM students s 
                          JOIN courses co ON s.course_id = co.id 
                          JOIN departments d ON co.department_id = d.id 
                          JOIN marshal m ON d.id = m.department_id 
                          WHERE m.user_id = ? 
                          ORDER BY s.last_name ASC, s.first_name ASC");
    $stmt->execute([$currentUserId]);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
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

  // Handle email parents modal action (separate from report submission)
  try {
    if (($_SERVER['REQUEST_METHOD'] === 'POST') && (($_POST['action'] ?? '') === 'email_parents')) {
      ensure_csrf();
      $studentIdForEmail = (int)($_POST['student_id'] ?? 0);
      $emailSubject = trim($_POST['email_subject'] ?? '');
      $emailBody = trim($_POST['email_body'] ?? '');
      $emailRecipientsRaw = trim($_POST['email_recipients'] ?? '');

      if ($studentIdForEmail <= 0) { throw new Exception('Please select a student.'); }
      if ($emailSubject === '' || $emailBody === '') { throw new Exception('Email subject and body are required.'); }
      if ($emailRecipientsRaw === '') { throw new Exception('Please provide at least one recipient email.'); }

      // Parse and validate provided recipient emails (comma/semicolon/space separated)
      $parts = preg_split('/[\s,;]+/', $emailRecipientsRaw);
      $emails = [];
      foreach ($parts as $p) {
        $addr = strtolower(trim($p));
        if ($addr !== '' && filter_var($addr, FILTER_VALIDATE_EMAIL)) {
          $emails[$addr] = true; // de-duplicate
        }
      }
      $emails = array_keys($emails);
      if (empty($emails)) { throw new Exception('No valid recipient email addresses found.'); }

      // Also fetch student full name for better context
      $stName = $pdo->prepare("SELECT CONCAT(first_name, ' ', last_name) FROM students WHERE id = ?");
      $stName->execute([$studentIdForEmail]);
      $studentFullName = $stName->fetchColumn() ?: 'the student';

      // Send emails via PHPMailer if available, otherwise fallback to PHP mail()
      $allSent = true;
      $sendErrors = [];
      
      // Check SMTP configuration first
      $smtpHost = getenv('SMTP_HOST') ?: '';
      $smtpPort = (int)(getenv('SMTP_PORT') ?: 0);
      $smtpUser = getenv('SMTP_USER') ?: '';
      $smtpPass = getenv('SMTP_PASS') ?: '';
      $smtpSecure = getenv('SMTP_SECURE') ?: ''; // tls|ssl|''
      
      // Also check $_ENV and $_SERVER as fallbacks (in case getenv doesn't work)
      if (empty($smtpHost)) {
        $smtpHost = $_ENV['SMTP_HOST'] ?? $_SERVER['SMTP_HOST'] ?? '';
      }
      if (empty($smtpPort)) {
        $smtpPort = (int)($_ENV['SMTP_PORT'] ?? $_SERVER['SMTP_PORT'] ?? 0);
      }
      if (empty($smtpUser)) {
        $smtpUser = $_ENV['SMTP_USER'] ?? $_SERVER['SMTP_USER'] ?? '';
      }
      if (empty($smtpPass)) {
        $smtpPass = $_ENV['SMTP_PASS'] ?? $_SERVER['SMTP_PASS'] ?? '';
      }
      if (empty($smtpSecure)) {
        $smtpSecure = $_ENV['SMTP_SECURE'] ?? $_SERVER['SMTP_SECURE'] ?? '';
      }
      
      // Detect if we're on a hosted/production environment (not localhost)
      $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
      $hostNoPort = preg_replace('/:.*/', '', (string)$host);
      $isLocalhost = in_array(strtolower($hostNoPort), ['localhost', '127.0.0.1', '::1']) || 
                     strpos($hostNoPort, 'localhost') !== false ||
                     strpos($hostNoPort, '127.0.0.1') !== false;
      
      // If SMTP is not configured and we're on a hosted environment, show helpful error
      if (empty($smtpHost) && !$isLocalhost && class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
        $envPath = __DIR__ . '/../../.env';
        $envExists = file_exists($envPath) ? 'exists' : 'does not exist';
        throw new Exception('Email service is not configured. Please configure SMTP settings in your .env file. Required: SMTP_HOST, SMTP_PORT, SMTP_USER, SMTP_PASS, and optionally SMTP_SECURE (tls or ssl). (.env file ' . $envExists . ' at ' . $envPath . ')');
      }
      
      if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
        try {
          $mailer = new \PHPMailer\PHPMailer\PHPMailer(true);
          // Determine default FROM email based on host if not provided (reuse $host and $hostNoPort from above)
          $domainFromHost = (strpos((string)$hostNoPort, '.') !== false && strtolower((string)$hostNoPort) !== 'localhost') ? ('no-reply@' . $hostNoPort) : 'no-reply@sdms.local';
          $fromEmail = getenv('SMTP_FROM_EMAIL') ?: $domainFromHost;
          $fromName = getenv('SMTP_FROM_NAME') ?: 'SDMS';
          $replyToEmail = '';
          if (!empty($_SESSION['email']) && filter_var($_SESSION['email'], FILTER_VALIDATE_EMAIL)) {
            $replyToEmail = $_SESSION['email'];
          }

          if ($smtpHost) {
            $mailer->isSMTP();
            $mailer->Host = $smtpHost;
            if ($smtpPort > 0) { 
              $mailer->Port = $smtpPort; 
            } else {
              // Default ports based on security
              if ($smtpSecure === 'ssl') {
                $mailer->Port = 465;
              } elseif ($smtpSecure === 'tls') {
                $mailer->Port = 587;
              } else {
                $mailer->Port = 587; // Default to 587 for TLS
              }
            }
            
            // Set SMTPSecure before authentication
            if ($smtpSecure) {
              $mailer->SMTPSecure = $smtpSecure;
            } elseif (strtolower($smtpHost) === 'smtp.gmail.com') {
              // Gmail defaults to TLS
              $mailer->SMTPSecure = 'tls';
            }
            
            if ($smtpUser !== '') {
              $mailer->SMTPAuth = true;
              $mailer->Username = $smtpUser;
              $mailer->Password = $smtpPass;
            }
            // Set timeout for SMTP connection
            $mailer->Timeout = 30;
            
            // SSL options for all environments (Gmail and other providers may need this)
            $mailer->SMTPOptions = array(
              'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
              )
            );
          } else {
            // If no SMTP host and we're on hosted environment, show error
            if (!$isLocalhost) {
              throw new Exception('SMTP configuration is required on hosted environments. Please set SMTP_HOST in your .env file.');
            }
            // On localhost, try using mail() function (may not work on Windows/XAMPP)
            $mailer->isMail();
          }
          $mailer->CharSet = 'UTF-8';
          $mailer->setFrom($fromEmail, $fromName);
          if ($replyToEmail !== '') { $mailer->addReplyTo($replyToEmail); }
          foreach ($emails as $addr) {
            $mailer->clearAddresses();
            $mailer->addAddress($addr);
            $mailer->Subject = $emailSubject;
            $mailer->isHTML(false);
            $mailer->Body = $emailBody;
            try {
              $mailer->send();
            } catch (Throwable $e) {
              error_log('Email send failed for ' . $addr . ': ' . $e->getMessage());
              $allSent = false;
              // Provide more helpful error messages
              $errorMsg = $e->getMessage();
              if (strpos($errorMsg, 'Could not instantiate mail function') !== false) {
                $errorMsg = 'Email service not configured. Please configure SMTP settings.';
              } elseif (strpos($errorMsg, 'SMTP connect() failed') !== false) {
                $errorMsg = 'Cannot connect to SMTP server. Please check SMTP_HOST and SMTP_PORT settings.';
              } elseif (strpos($errorMsg, 'SMTP Error: Could not authenticate') !== false) {
                $errorMsg = 'SMTP authentication failed. Please check SMTP_USER and SMTP_PASS.';
              }
              $sendErrors[] = $addr . ' → ' . $errorMsg;
            }
          }
        } catch (Throwable $e) {
          error_log('Mailer init/send error: ' . $e->getMessage());
          $allSent = false;
          $errorMsg = $e->getMessage();
          if (strpos($errorMsg, 'Could not instantiate mail function') !== false) {
            $errorMsg = 'Email service not configured. Please configure SMTP settings in .env file.';
          }
          $sendErrors[] = 'Mailer error → ' . $errorMsg;
        }
      } else {
        // PHPMailer not available and no SMTP configured - show error
        throw new Exception('Email service is not available. PHPMailer is not installed and SMTP is not configured. Please install PHPMailer via Composer and configure SMTP settings.');
      }

      if (!$allSent) {
        $detail = '';
        if (!empty($sendErrors)) {
          $detail = ' Details: ' . implode('; ', array_slice($sendErrors, 0, 5));
        }
        $_SESSION['flash'] = flash('error', 'Some emails could not be sent.' . $detail);
      } else {
        $_SESSION['flash'] = flash('success', 'Email sent to parent(s) of ' . e($studentFullName) . '.');
      }

      header('Location: ' . basename($_SERVER['PHP_SELF']));
      exit;
    }
  } catch (Exception $e) {
    $_SESSION['flash'] = flash('error', $e->getMessage());
    header('Location: ' . basename($_SERVER['PHP_SELF']));
    exit;
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

      // Insert case - all cases are reported by marshals
      $stmt = $pdo->prepare('INSERT INTO cases (
          case_number, 
          student_id, 
          violation_type_id, 
          title, 
          description, 
          location, 
          incident_date, 
          status_id, 
          is_confidential, 
          reported_by_marshal_id
      ) VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?, ?)');
      
      // Get the marshal ID for the current user
      $marshalStmt = $pdo->prepare('SELECT id FROM marshal WHERE user_id = ? LIMIT 1');
      $marshalStmt->execute([$currentUserId]);
      $marshal = $marshalStmt->fetch(PDO::FETCH_ASSOC);
      
      if (!$marshal) {
          throw new Exception('You are not authorized to submit cases. Only marshals can submit cases.');
      }
      
      $marshalId = $marshal['id'];
      
      $stmt->execute([
          $case_number, 
          $student_id, 
          $violation_type_id, 
          $title, 
          $description, 
          $location, 
          $incident_dt, 
          $is_confidential, 
          $marshalId
      ]);
      $case_id = (int)$pdo->lastInsertId();

if (!$is_confidential) {
    // Get violation type name for notification
    $vtStmt = $pdo->prepare('SELECT name FROM violation_types WHERE id = ?');
    $vtStmt->execute([$violation_type_id]);
    $violationTypeName = $vtStmt->fetchColumn() ?: 'a disciplinary violation';
    
    // Send notifications to parents
    error_log("Sending notification to parents for case #$case_number");
    $notifyResult = notifyParentsOfReportedStudent($student_id, $case_id, $case_number, $violationTypeName);
    error_log("Parent notification result: " . ($notifyResult ? 'success' : 'failed'));
} else {
    error_log("Case #$case_number is confidential, skipping parent notifications");
}     
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

      // Send notification to admins
      try {
        error_log("Getting admin recipients...");
        $adminIds = getAdminRecipients();
        error_log("Admin recipients: " . print_r($adminIds, true));
        
        if (!empty($adminIds)) {
          // Get violation type name
          $violationStmt = $pdo->prepare("SELECT name FROM violation_types WHERE id = ?");
          $violationStmt->execute([$violation_type_id]);
          $violationType = $violationStmt->fetchColumn();
          
          // Get student name
          $studentStmt = $pdo->prepare("SELECT CONCAT(first_name, ' ', last_name) FROM students WHERE id = ?");
          $studentStmt->execute([$student_id]);
          $studentName = $studentStmt->fetchColumn();
          
          if ($violationType === false || $studentName === false) {
            error_log("Failed to fetch case details for notification. Violation type: " . ($violationType === false ? 'not found' : 'found') . ", Student: " . ($studentName === false ? 'not found' : 'found'));
          } else {
            // Notify admins
            $adminMessage = "New case #$case_number filed for $studentName - $violationType: $title";
            error_log("Sending notification to admins: $adminMessage");
            
            $notificationSent = sendNotification($adminIds, $adminMessage, 1, $case_id);
            if (!$notificationSent) {
              error_log("Failed to send admin notification for case #$case_number");
            } else {
              error_log("Successfully sent admin notification for case #$case_number");
            }

            // Notify student if they have a user account
            $studentStmt = $pdo->prepare("SELECT user_id, CONCAT(first_name, ' ', last_name) as student_name FROM students WHERE id = ?");
            $studentStmt->execute([$student_id]);
            $student = $studentStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($student && !empty($student['user_id'])) {
              $studentMessage = "A new case has been filed against you: $title";
              error_log("Sending notification to student (User ID: {$student['user_id']}): {$studentMessage}");
              
              // Send notification (method_id 1 is for system notifications)
              $notificationSent = sendNotification($student['user_id'], $studentMessage, 1, $case_id);
              
              if (!$notificationSent) {
                error_log("Failed to send notification to student for case #{$case_number}");
              }
            }

            // Notify parents of the student
            $parentStmt = $pdo->prepare("
                SELECT parent_user_id 
                FROM parent_student 
                WHERE student_id = ?
            ");
            $parentStmt->execute([$student_id]);
            $parentIds = $parentStmt->fetchAll(PDO::FETCH_COLUMN);

            if (!empty($parentIds)) {
                $parentMessage = "A new case has been filed for your child $studentName: $title";
                error_log("Sending notification to parents: " . implode(', ', $parentIds) . ": $parentMessage");
                
                foreach ($parentIds as $parentId) {
                    $notificationSent = sendNotification($parentId, $parentMessage, 1, $case_id);
                    if (!$notificationSent) {
                        error_log("Failed to send notification to parent ID $parentId for case #$case_number");
                    }
                }
            }
          }
        } else {
          error_log("No admin users found to send notification to");
        }
      } catch (Exception $e) {
        // Log the error but don't fail the entire operation
        error_log("Error in notification process: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
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
      <?php if (!empty($flash['type'])): ?>
        <div class="hidden" id="flashData"
             data-type="<?php echo htmlspecialchars($flash['type'] === 'success' ? 'success' : 'error', ENT_QUOTES, 'UTF-8'); ?>"
             data-msg="<?php echo htmlspecialchars($flash['msg'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"></div>
      <?php endif; ?>

      <!-- Form Card -->
      <section class="bg-white border rounded-lg overflow-hidden">
        <div class="p-4 border-b">
          <h2 class="text-lg font-semibold">Report Details</h2>
          <p class="text-sm text-gray-500 mt-1">Fill in the incident details and attach evidence if available.</p>
        </div>
        <div class="p-4">
          <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" enctype="multipart/form-data" class="grid grid-cols-1 md:grid-cols-2 gap-4" id="reportForm">
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

            

            <div class="md:col-span-2 flex items-center gap-2">
              <input type="checkbox" id="is_confidential" name="is_confidential" class="h-4 w-4" />
              <label for="is_confidential" class="text-sm">Mark as confidential</label>
            </div>

            <div class="md:col-span-2 flex justify-end gap-2 mt-2">
              <button type="button" id="openEmailParentsModal" class="px-4 py-2 border rounded" title="Send email to the selected student's parents">Email Parents</button>
              <a href="manage-report.php" class="px-4 py-2 border rounded">Cancel</a>
              <button class="px-4 py-2 bg-primary text-white rounded hover:bg-primary/90">Submit Report</button>
            </div>
          </form>
        </div>
      </section>
    </div>
  </main>
</div>

<!-- Email Parents Modal -->
<div id="emailParentsModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
  <div class="bg-white w-11/12 max-w-xl rounded-lg shadow-lg overflow-hidden">
    <div class="p-4 border-b flex items-center justify-between">
      <h3 class="text-lg font-semibold">Email Parents</h3>
      <button id="closeEmailParentsModal" class="text-gray-500 hover:text-gray-700" aria-label="Close">
        <i class="fa-solid fa-xmark"></i>
      </button>
    </div>
    <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="p-4 space-y-3">
      <input type="hidden" name="csrf" value="<?php echo $csrf; ?>" />
      <input type="hidden" name="action" value="email_parents" />
      <input type="hidden" name="student_id" id="emailStudentId" />

      <div>
        <label class="block text-sm font-medium mb-1">Student</label>
        <input type="text" id="emailStudentName" class="w-full border rounded px-3 py-2 bg-gray-50" readonly />
      </div>

      <div>
        <label class="block text-sm font-medium mb-1">Recipient Emails<span class="text-rose-600">*</span></label>
        <input type="text" name="email_recipients" id="emailRecipients" class="w-full border rounded px-3 py-2" required placeholder="parent1@email.com, parent2@email.com" />
        <p class="text-xs text-gray-500 mt-1">Separate multiple emails with commas, semicolons, or spaces.</p>
      </div>

      <div>
        <label class="block text-sm font-medium mb-1">Subject<span class="text-rose-600">*</span></label>
        <input type="text" name="email_subject" id="emailSubject" class="w-full border rounded px-3 py-2" required />
      </div>

      <div>
        <label class="block text-sm font-medium mb-1">Message<span class="text-rose-600">*</span></label>
        <textarea name="email_body" id="emailBody" rows="6" class="w-full border rounded px-3 py-2" required placeholder="Write your message to the parents here..."></textarea>
      </div>

      <div class="flex justify-end gap-2 pt-2">
        <button type="button" id="cancelEmailParents" class="px-4 py-2 border rounded">Cancel</button>
        <button type="submit" class="px-4 py-2 bg-primary text-white rounded hover:bg-primary/90">Send Email</button>
      </div>
    </form>
  </div>
  </div>

<script>
  // Form submission handler
  document.getElementById('reportForm')?.addEventListener('submit', function(e) {
    // Client-side validation
    const requiredFields = this.querySelectorAll('[required]');
    let isValid = true;
    
    requiredFields.forEach(field => {
      if (!field.value.trim()) {
        isValid = false;
        field.classList.add('border-red-500');
      } else {
        field.classList.remove('border-red-500');
      }
    });
    
    if (!isValid) {
      e.preventDefault();
      Swal.fire({
        icon: 'error',
        title: 'Missing Information',
        text: 'Please fill in all required fields marked with *',
        confirmButtonColor: '#3b82f6',
      });
      return false;
    }
    
    // Show loading state
    const submitBtn = this.querySelector('button[type="submit"]');
    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Submitting...';
    }
  });

  // Flash via SweetAlert2
  document.addEventListener('DOMContentLoaded', () => {
    const flash = document.getElementById('flashData');
    if (flash && window.Swal) {
      const type = flash.getAttribute('data-type') || 'info';
      const msg = flash.getAttribute('data-msg') || '';
      
      if (msg) {
        if (type === 'success') {
          Swal.fire({
            icon: 'success',
            title: 'Success!',
            text: msg,
            confirmButtonText: 'OK',
            confirmButtonColor: '#3b82f6',
            timer: 5000,
            timerProgressBar: true
          });
        } else {
          Swal.fire({
            icon: 'error',
            title: 'Error',
            text: msg,
            confirmButtonText: 'OK',
            confirmButtonColor: '#3b82f6'
          });
        }
      }
    }
  });

  // Email Parents Modal Logic
  (function(){
    const openBtn = document.getElementById('openEmailParentsModal');
    const modal = document.getElementById('emailParentsModal');
    const closeBtn = document.getElementById('closeEmailParentsModal');
    const cancelBtn = document.getElementById('cancelEmailParents');
    const studentSelect = document.querySelector('select[name="student_id"]');
    const studentInput = document.getElementById('emailStudentId');
    const studentNameDisplay = document.getElementById('emailStudentName');
    const subjectInput = document.getElementById('emailSubject');
    const bodyInput = document.getElementById('emailBody');

    function openModal(){
      const sid = studentSelect?.value || '';
      const sname = studentSelect?.options[studentSelect.selectedIndex]?.text || '';
      if (!sid) {
        if (window.Swal) {
          Swal.fire({ icon: 'warning', title: 'Select a Student', text: 'Please select a student first.', confirmButtonColor: '#3b82f6' });
        }
        return;
      }
      studentInput.value = sid;
      studentNameDisplay.value = sname.trim();
      if (!subjectInput.value) {
        subjectInput.value = 'Notice regarding your child';
      }
      if (!bodyInput.value) {
        bodyInput.value = 'Good day,\n\nThis is to inform you regarding a disciplinary matter concerning your child. Please log in to the SDMS portal for more details or reply to this email if you have questions.\n\nThank you.';
      }
      modal.classList.remove('hidden');
      modal.classList.add('flex');
    }
    function closeModal(){
      modal.classList.add('hidden');
      modal.classList.remove('flex');
    }
    openBtn?.addEventListener('click', openModal);
    closeBtn?.addEventListener('click', closeModal);
    cancelBtn?.addEventListener('click', closeModal);
    modal?.addEventListener('click', (e) => { if (e.target === modal) closeModal(); });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && !modal.classList.contains('hidden')) closeModal(); });
  })();
</script>

<?php include __DIR__ . '/../../components/staff-footer.php'; ?>
