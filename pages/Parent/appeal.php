<?php
   // Parent Appeal/Clarification Submission Page
   if (session_status() === PHP_SESSION_NONE) {
     session_start();
   }

   require_once __DIR__ . '/../../database/database.php';

   // Auth guard: must be logged in and role = Parent (4)
   // Uncomment when auth is wired
   // if (!isset($_SESSION['user_id']) || !isset($_SESSION['role_id']) || (int)$_SESSION['role_id'] !== 4) {
   //   header('Location: /SDMS/pages/Auth/login.php');
   //   exit;
   // }

   $parentUserId = (int)($_SESSION['user_id'] ?? 0);

   // Fetch children linked to this parent
   $children = [];
   try {
     $stmt = $pdo->prepare(
       'SELECT s.id, s.student_number, s.first_name, s.middle_name, s.last_name, s.suffix
        FROM parent_student ps
        JOIN students s ON s.id = ps.student_id
        WHERE ps.parent_user_id = :pid'
     );
     $stmt->execute([':pid' => $parentUserId]);
     $children = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
   } catch (Throwable $e) {
     $children = [];
   }

   // Preload cases for all linked children (open/recent first)
   $cases = [];
   if (!empty($children)) {
     try {
       $ids = array_map(fn($r) => (int)$r['id'], $children);
       $in = implode(',', array_fill(0, count($ids), '?'));
       $sql = "SELECT c.id, c.case_number, c.title, c.student_id, c.status_id, c.incident_date, vt.name AS violation_name,
                      cs.name AS status_name, c.reported_by_staff_id
               FROM cases c
               JOIN violation_types vt ON vt.id = c.violation_type_id
               JOIN case_status cs ON cs.id = c.status_id
               WHERE c.student_id IN ($in)
               ORDER BY c.created_at DESC";
       $st = $pdo->prepare($sql);
       $st->execute($ids);
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
   if ($_SERVER['REQUEST_METHOD'] === 'POST') {
     $caseId = (int)($_POST['case_id'] ?? 0);
     $appealText = trim($_POST['appeal_text'] ?? '');

     // Basic validations
     if ($caseId <= 0) {
       $errors[] = 'Please select a case.';
     }
     if ($appealText === '') {
       $errors[] = 'Please provide details for your appeal/clarification.';
     }

     // Verify that the selected case belongs to one of the parent's linked students
     $caseRow = null;
     if (empty($errors)) {
       try {
         $ids = array_map(fn($r) => (int)$r['id'], $children);
         if (!empty($ids)) {
           $in = implode(',', array_fill(0, count($ids), '?'));
           $q = $pdo->prepare("SELECT c.*, vt.name AS violation_name FROM cases c JOIN violation_types vt ON vt.id = c.violation_type_id WHERE c.id = ? AND c.student_id IN ($in) LIMIT 1");
           $params = array_merge([$caseId], $ids);
           $q->execute($params);
           $caseRow = $q->fetch(PDO::FETCH_ASSOC) ?: null;
         }
       } catch (Throwable $e) {
         $errors[] = 'Unable to validate selected case at this time.';
       }
       if (!$caseRow) {
         $errors[] = 'Invalid case selection.';
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
         // rudimentary file validation
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
               $relPath = '/SDMS/uploads/appeals/' . $newName; // web path assumption
               $ins = $pdo->prepare('INSERT INTO case_evidence (case_id, uploaded_by_user_id, filename, file_path, file_type, file_size) VALUES (:cid, :uid, :fn, :fp, :ft, :fs)');
               $ins->execute([
                 ':cid' => $caseId,
                 ':uid' => $parentUserId ?: null,
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

         // Insert into appeals
         $insA = $pdo->prepare('INSERT INTO appeals (case_id, student_id, appeal_text, attachment_evidence_id, status_id) VALUES (:cid, :sid, :txt, :evid, :status)');
         $insA->execute([
           ':cid' => $caseId,
           ':sid' => (int)$caseRow['student_id'],
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
           ':uid' => $parentUserId ?: null,
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
        // Set flash and redirect (PRG) to avoid resubmission on reload
        $_SESSION['flash_success'] = 'Your appeal/clarification has been submitted successfully.';
        header('Location: appeal.php');
        exit;
       } catch (Throwable $e) {
         if ($pdo->inTransaction()) {
           $pdo->rollBack();
         }
         $errors[] = 'Failed to submit your appeal. Please try again later.';
       }
     }
   }

   $pageTitle = 'Submit Appeal - Parent - SDMS';
   include __DIR__ . '/../../components/parent-head.php';
 ?>

 <div class="min-h-screen flex">
   <?php include __DIR__ . '/../../components/parent-sidebar.php'; ?>

   <main class="flex-1 md:ml-64">
     <!-- Top bar -->
     <div class="h-16 flex items-center px-4 border-b border-gray-200 bg-white sticky top-0 z-40">
       <button id="parentSidebarToggle" class="md:hidden text-primary text-xl mr-3" aria-label="Toggle Sidebar">
         <i class="fa-solid fa-bars"></i>
       </button>
       <h1 class="text-xl font-bold text-primary">Submit Appeal / Clarification</h1>
       <div class="ml-auto text-sm text-gray">
         Logged in as: <span class="font-medium text-dark"><?php echo htmlspecialchars($_SESSION['email'] ?? $_SESSION['username'] ?? 'Parent'); ?></span>
       </div>
     </div>

     <div class="p-6 max-w-3xl">
       <?php if ($success): ?>
         <div class="mb-4 p-4 rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-800">
           <i class="fa-solid fa-check-circle mr-2"></i>
           <?php echo htmlspecialchars($success); ?>
         </div>
       <?php endif; ?>

       <?php if (!empty($errors)): ?>
         <div class="mb-4 p-4 rounded-lg border border-rose-200 bg-rose-50 text-rose-800">
           <div class="font-semibold mb-1">Please fix the following:</div>
           <ul class="list-disc pl-5 space-y-1">
             <?php foreach ($errors as $err): ?>
               <li><?php echo htmlspecialchars($err); ?></li>
             <?php endforeach; ?>
           </ul>
         </div>
       <?php endif; ?>

       <?php if (empty($children)): ?>
         <div class="bg-yellow-50 border border-yellow-200 text-yellow-800 p-4 rounded-lg">
           <div class="flex items-start gap-3">
             <i class="fa-solid fa-circle-info mt-1"></i>
             <div>
               <h2 class="font-semibold">No linked student found</h2>
               <p class="text-sm mt-1">Your account is not linked to any student yet. Please contact the school administration for assistance.</p>
             </div>
           </div>
         </div>
       <?php else: ?>
         <form action="" method="post" enctype="multipart/form-data" class="bg-white border border-gray-200 rounded-xl shadow-sm p-5 space-y-4">
           <div>
             <label for="case_id" class="block text-sm font-medium text-gray-700 mb-1">Select Case</label>
             <select name="case_id" id="case_id" class="w-full border-gray-300 rounded-lg focus:ring-primary focus:border-primary">
               <option value="">-- Choose a case --</option>
               <?php
                 // Group cases by child for better readability
                 $byStudent = [];
                 foreach ($children as $ch) { $byStudent[(int)$ch['id']] = $ch; }
                 $grouped = [];
                 foreach ($cases as $c) { $grouped[(int)$c['student_id']][] = $c; }
                 foreach ($grouped as $sid => $rows):
                   $ch = $byStudent[$sid] ?? null;
                   $studentName = $ch ? ($ch['first_name'] . ' ' . ($ch['middle_name'] ? $ch['middle_name'][0] . '. ' : '') . $ch['last_name'] . ($ch['suffix'] ? ', ' . $ch['suffix'] : '')) : ('Student #' . $sid);
               ?>
                   <optgroup label="<?php echo htmlspecialchars($studentName); ?>">
                     <?php foreach ($rows as $row): ?>
                       <?php
                         $label = '#' . $row['case_number'] . ' - ' . $row['violation_name'] . ' - ' . $row['title'] . ' (' . date('M d, Y', strtotime($row['incident_date'])) . ')';
                       ?>
                       <option value="<?php echo (int)$row['id']; ?>" <?php echo ((int)($_POST['case_id'] ?? 0) === (int)$row['id']) ? 'selected' : ''; ?>>
                         <?php echo htmlspecialchars($label); ?>
                       </option>
                     <?php endforeach; ?>
                   </optgroup>
               <?php endforeach; ?>
             </select>
           </div>

           <div>
             <label for="appeal_text" class="block text-sm font-medium text-gray-700 mb-1">Appeal / Clarification Details</label>
             <textarea name="appeal_text" id="appeal_text" rows="6" class="w-full border-gray-300 rounded-lg focus:ring-primary focus:border-primary" placeholder="Explain your appeal or clarification. Provide any necessary details."><?php echo htmlspecialchars($_POST['appeal_text'] ?? ''); ?></textarea>
           </div>

           <div>
             <label for="attachment" class="block text-sm font-medium text-gray-700 mb-1">Attachment (optional)</label>
             <input type="file" name="attachment" id="attachment" accept=".jpg,.jpeg,.png,.pdf" class="block w-full text-sm text-gray-700 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-primary/10 file:text-primary hover:file:bg-primary/20" />
             <p class="text-xs text-gray mt-1">Allowed: JPG, PNG, PDF. Max 10 MB.</p>
           </div>

           <div class="pt-2">
             <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90">
               <i class="fa-solid fa-paper-plane"></i>
               <span>Submit Appeal</span>
             </button>
           </div>
         </form>
       <?php endif; ?>
     </div>
   </main>
 </div>

 <?php include __DIR__ . '/../../components/parent-footer.php'; ?>