<?php
   if (session_status() === PHP_SESSION_NONE) {
     session_start();
   }
 
   if (!isset($_SESSION['user_id'])) {
     header('Location: ../Auth/login.php');
     exit;
   }
 
   require_once __DIR__ . '/../../database/database.php';
 
   $currentUserId = (int)$_SESSION['user_id'];
 
   $currentStaffId = null;
   try {
     $stf = $pdo->prepare('SELECT id FROM staff WHERE user_id = ? LIMIT 1');
     $stf->execute([$currentUserId]);
     $row = $stf->fetch(PDO::FETCH_ASSOC);
     if ($row && !empty($row['id'])) { $currentStaffId = (int)$row['id']; }
   } catch (Throwable $e) { /* ignore */ }
 
   function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
 
   $q = trim($_GET['q'] ?? '');
   $statusFilter = trim($_GET['status'] ?? ''); 
 
   $params = [];
   $where = [];
   $where[] = '(c.reported_by_staff_id = ? OR c.reported_by_staff_id = ?)';
   $params[] = $currentStaffId;
   $params[] = $currentUserId;
 
   if ($q !== '') {
     $where[] = '(c.case_number LIKE ? OR s.first_name LIKE ? OR s.last_name LIKE ? OR a.appeal_text LIKE ?)';
     $like = "%" . $q . "%";
     array_push($params, $like, $like, $like, $like);
   }
   if ($statusFilter !== '' && ctype_digit($statusFilter)) {
     $where[] = 'a.status_id = ?';
     $params[] = (int)$statusFilter;
   }
 
   $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
 
   // Fetch appeals with joins for context
   $sql = "SELECT 
             a.id AS appeal_id,
             a.case_id,
             a.student_id,
             a.appeal_text,
             a.attachment_evidence_id,
             a.status_id,
             a.submitted_at,
             a.reviewed_by_user_id,
             a.decision_text,
             a.decision_at,
             c.case_number,
             c.title AS case_title,
             vt.name AS violation_name,
             s.first_name, s.last_name, s.student_number,
             ce.file_path AS attachment_path,
             ce.filename AS attachment_filename
           FROM appeals a
           JOIN cases c ON c.id = a.case_id
           LEFT JOIN violation_types vt ON vt.id = c.violation_type_id
           JOIN students s ON s.id = a.student_id
           LEFT JOIN case_evidence ce ON ce.id = a.attachment_evidence_id
           $whereSql
           ORDER BY a.submitted_at DESC, a.id DESC";
 
   $appeals = [];
   try {
     $stmt = $pdo->prepare($sql);
     $stmt->execute($params);
     $appeals = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
   } catch (Throwable $e) {
     $appeals = [];
   }
   $appealStatuses = [
     1 => 'Submitted',
     2 => 'Under Review',
     3 => 'Approved',
     4 => 'Rejected',
   ];
 
   $pageTitle = 'Manage Appeals - Staff - SDMS';
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
         <h1 class="text-xl md:text-2xl font-semibold">Manage Appeals</h1>
       </div>
       <div class="text-sm text-gray-500">
         Logged in as: <span class="font-medium text-dark"><?php echo e($_SESSION['email'] ?? $_SESSION['username'] ?? 'Staff'); ?></span>
       </div>
     </div>
 
     <div class="p-4 md:p-6 space-y-6 overflow-x-hidden">
       <!-- Filters -->
       <form method="get" class="bg-white border rounded-lg p-4 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
         <div>
           <label class="block text-sm text-gray-600 mb-1">Search</label>
           <input type="text" name="q" value="<?php echo e($q); ?>" placeholder="Case #, student, or appeal text" class="w-full border-gray-300 rounded-lg focus:ring-primary focus:border-primary" />
         </div>
         <div>
           <label class="block text-sm text-gray-600 mb-1">Status</label>
           <select name="status" class="w-full border-gray-300 rounded-lg focus:ring-primary focus:border-primary">
             <option value="">All</option>
             <?php foreach ($appealStatuses as $sid => $sname): ?>
               <option value="<?php echo (int)$sid; ?>" <?php echo ($statusFilter !== '' && (int)$statusFilter === (int)$sid) ? 'selected' : ''; ?>><?php echo e($sname); ?></option>
             <?php endforeach; ?>
           </select>
         </div>
         <div class="flex items-end">
           <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90">
             <i class="fa-solid fa-magnifying-glass"></i>
             <span>Apply</span>
           </button>
           <a href="manage-appeal.php" class="ml-2 inline-flex items-center gap-2 px-4 py-2 border rounded-lg hover:bg-gray-50">
             <i class="fa-solid fa-rotate-left"></i>
             <span>Reset</span>
           </a>
         </div>
       </form>
 
       <!-- Appeals List -->
       <section class="bg-white border rounded-lg overflow-hidden">
         <div class="flex items-center justify-between p-4 border-b">
           <h2 class="text-lg font-semibold">Appeals (<?php echo count($appeals); ?>)</h2>
         </div>

         <?php if (empty($appeals)): ?>
           <div class="p-6 text-gray-500">No appeals found for your reported cases.</div>
         <?php else: ?>
           <div class="overflow-x-auto">
             <div class="min-w-full inline-block align-middle">
               <div class="overflow-hidden">
                 <table class="min-w-full divide-y divide-gray-200">
                   <thead class="bg-gray-50">
                     <tr>
                       <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Submitted</th>
                       <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Case</th>
                       <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student</th>
                       <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider hidden md:table-cell">Violation</th>
                       <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Appeal</th>
                       <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Attachment</th>
                       <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                     </tr>
                   </thead>
                   <tbody class="bg-white divide-y divide-gray-200">
                     <?php foreach ($appeals as $a): ?>
                       <?php
                         $submitted = $a['submitted_at'] ? date('M d, Y H:i', strtotime($a['submitted_at'])) : '-';
                         $caseLabel = '#' . ($a['case_number'] ?? $a['case_id']) . ' - ' . ($a['case_title'] ?? '');
                         $studentName = trim(($a['first_name'] ?? '') . ' ' . ($a['last_name'] ?? ''));
                         $violation = $a['violation_name'] ?? '-';
                         $appealExcerpt = trim((string)($a['appeal_text'] ?? ''));
                         if (mb_strlen($appealExcerpt) > 120) { $appealExcerpt = mb_substr($appealExcerpt, 0, 120) . '…'; }
                         $stId = (int)($a['status_id'] ?? 1);
                         $statusName = $appealStatuses[$stId] ?? ('Status #' . $stId);
                         $statusClass = match ($stId) {
                           1 => 'bg-yellow-50 text-yellow-700 border-yellow-200',
                           2 => 'bg-blue-50 text-blue-700 border-blue-200',
                           3 => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                           4 => 'bg-rose-50 text-rose-700 border-rose-200',
                           default => 'bg-gray-50 text-gray-700 border-gray-200',
                         };
                         $attPath = $a['attachment_path'] ?? '';
                         $attName = $a['attachment_filename'] ?? '';
                       ?>
                       <tr class="hover:bg-gray-50">
                         <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                           <div class="font-medium"><?php echo e($submitted); ?></div>
                         </td>
                         <td class="px-4 py-3 text-sm text-gray-900">
                           <div class="font-medium"><?php echo e($caseLabel); ?></div>
                         </td>
                         <td class="px-4 py-3 text-sm text-gray-900">
                           <div class="font-medium"><?php echo e($studentName); ?></div>
                           <div class="text-xs text-gray-500">ID: <?php echo e($a['student_number'] ?? ''); ?></div>
                         </td>
                         <td class="px-4 py-3 text-sm text-gray-900 hidden md:table-cell">
                           <div class="max-w-xs truncate"><?php echo e($violation); ?></div>
                         </td>
                         <td class="px-4 py-3 text-sm text-gray-900">
                           <div class="max-w-xs md:max-w-md lg:max-w-lg xl:max-w-xl"><?php echo nl2br(e($appealExcerpt)); ?></div>
                         </td>
                         <td class="px-4 py-3 text-sm text-gray-900">
                           <?php if ($attPath): ?>
                             <a href="<?php echo e($attPath); ?>" target="_blank" class="inline-flex items-center gap-1 text-primary hover:underline text-sm">
                               <i class="fa-solid fa-paperclip"></i>
                               <span class="truncate max-w-[100px] md:max-w-[150px] inline-block"><?php echo e($attName ?: 'View'); ?></span>
                             </a>
                           <?php else: ?>
                             <span class="text-gray-400">—</span>
                           <?php endif; ?>
                         </td>
                         <td class="px-4 py-3 whitespace-nowrap text-sm">
                           <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $statusClass; ?>">
                             <?php echo e($statusName); ?>
                           </span>
                         </td>
                       </tr>
                     <?php endforeach; ?>
                   </tbody>
                 </table>
               </div>
             </div>
           </div>
         <?php endif; ?>
       </section>
     </div>
   </main>
 </div>
 
 <?php include __DIR__ . '/../../components/staff-footer.php'; ?>