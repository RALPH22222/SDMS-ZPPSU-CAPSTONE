<?php
  // Manage Teachers -> Assign teachers (staff) to classes as advisers
  // Uses tables: classes (id, class_name, adviser_staff_id), staff (id, user_id, names), users (id, role_id), roles (Teacher)

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

  // Use role_id = 6 for teachers
  $teacherRoleId = 5;
  
  // Check if any users have role_id = 6
  $checkRole = $pdo->query("SELECT COUNT(*) as count FROM users WHERE role_id = $teacherRoleId")->fetch(PDO::FETCH_ASSOC);
  if ($checkRole['count'] == 0) {
    error_log("WARNING: No users found with role_id = $teacherRoleId. Teachers will not be available for assignment.");
  }

  // Handle POST actions
  try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      ensure_csrf();
      $action = $_POST['action'] ?? '';

      if ($action === 'create_class') {
        $name = trim($_POST['class_name'] ?? '');
        if ($name === '') throw new Exception('Class name is required.');
        $stmt = $pdo->prepare('INSERT INTO classes (id, class_name, adviser_staff_id, created_at) VALUES (NULL, ?, NULL, CURRENT_TIMESTAMP())');
        $stmt->execute([$name]);
        $_SESSION['flash'] = flash('success', 'Class created.');
        header('Location: ' . basename($_SERVER['PHP_SELF']));
        exit;
      }

      if ($action === 'rename_class') {
        $id = (int)($_POST['class_id'] ?? 0);
        $name = trim($_POST['class_name'] ?? '');
        if ($id <= 0 || $name === '') throw new Exception('Class and name are required.');
        $stmt = $pdo->prepare('UPDATE classes SET class_name = ? WHERE id = ?');
        $stmt->execute([$name, $id]);
        $_SESSION['flash'] = flash('success', 'Class renamed.');
        header('Location: ' . basename($_SERVER['PHP_SELF']));
        exit;
      }

      if ($action === 'delete_class') {
        $id = (int)($_POST['class_id'] ?? 0);
        if ($id <= 0) throw new Exception('Invalid class.');
        // Optional: check dependencies (e.g., students referencing class_id) – not present in schema provided
        $stmt = $pdo->prepare('DELETE FROM classes WHERE id = ?');
        $stmt->execute([$id]);
        $_SESSION['flash'] = flash('success', 'Class deleted.');
        header('Location: ' . basename($_SERVER['PHP_SELF']));
        exit;
      }

      if ($action === 'assign_teacher') {
        $classId = (int)($_POST['class_id'] ?? 0);
        $staffId = (int)($_POST['staff_id'] ?? 0);
        if ($classId <= 0 || $staffId <= 0) throw new Exception('Class and Teacher are required.');
        // Validate staff is a Teacher (by user role)
        $chk = $pdo->prepare('SELECT COUNT(*)
                              FROM staff s
                              JOIN users u ON u.id = s.user_id
                              WHERE s.id = ? AND u.role_id = ?');
        $chk->execute([$staffId, $teacherRoleId]);
        if ((int)$chk->fetchColumn() === 0) {
          throw new Exception('Selected staff is not a Teacher.');
        }
        $stmt = $pdo->prepare('UPDATE classes SET adviser_staff_id = ? WHERE id = ?');
        $stmt->execute([$staffId, $classId]);
        $_SESSION['flash'] = flash('success', 'Teacher assigned to class.');
        header('Location: ' . basename($_SERVER['PHP_SELF']));
        exit;
      }

      if ($action === 'unassign_teacher') {
        $classId = (int)($_POST['class_id'] ?? 0);
        if ($classId <= 0) throw new Exception('Invalid class.');
        $stmt = $pdo->prepare('UPDATE classes SET adviser_staff_id = NULL WHERE id = ?');
        $stmt->execute([$classId]);
        $_SESSION['flash'] = flash('success', 'Teacher unassigned from class.');
        header('Location: ' . basename($_SERVER['PHP_SELF']));
        exit;
      }
    }
  } catch (Exception $e) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      $_SESSION['flash'] = flash('error', $e->getMessage());
      header('Location: ' . basename($_SERVER['PHP_SELF']));
      exit;
    } else {
      $flash = flash('error', $e->getMessage());
    }
  }

  // Filters
  $q = trim($_GET['q'] ?? '');

  // Fetch classes with adviser info
  $where = '';
  $params = [];
  if ($q !== '') { $where = 'WHERE c.class_name LIKE ?'; $params[] = "%$q%"; }
  $sql = "SELECT c.id, c.class_name, c.adviser_staff_id,
                 s.first_name, s.last_name
          FROM classes c
          LEFT JOIN staff s ON s.id = c.adviser_staff_id
          $where
          ORDER BY c.class_name ASC";
  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);
  $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

  // Fetch teachers (staff linked to users with Teacher role)
  $teachers = [];
  if ($teacherRoleId) {
    $tq = $pdo->prepare('SELECT s.id, s.first_name, s.last_name, s.department, u.role_id
                         FROM staff s
                         JOIN users u ON u.id = s.user_id
                         WHERE u.role_id = ?
                         ORDER BY s.last_name ASC, s.first_name ASC');
    $tq->execute([$teacherRoleId]);
    $teachers = $tq->fetchAll(PDO::FETCH_ASSOC);
    
    // Debug output
    error_log("Teachers with role_id=$teacherRoleId: " . print_r($teachers, true));
    
    // Also log all staff with their role_ids for debugging
    $allStaff = $pdo->query('SELECT s.id, s.first_name, s.last_name, u.role_id FROM staff s JOIN users u ON u.id = s.user_id')->fetchAll(PDO::FETCH_ASSOC);
    error_log("All staff with roles: " . print_r($allStaff, true));
  }
?>

<?php $pageTitle = 'Manage Teachers - SDMS'; include_once __DIR__ . '/../../components/admin-head.php'; ?>

<div class="md:ml-64">
  <?php include_once __DIR__ . '/../../components/admin-sidebar.php'; ?>

  <main class="p-6">
    <div class="max-w-7xl mx-auto">
      <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-semibold text-dark">Manage Teachers & Class Advisers</h1>
        <div class="flex gap-2">
          <button id="btnOpenCreateClass" class="px-4 py-2 bg-primary text-white rounded hover:opacity-90"><i class="fa fa-plus mr-2"></i>Create Class</button>
        </div>
      </div>

      <?php if ($flash['type']): ?>
        <div class="hidden" id="flashData"
             data-type="<?php echo $flash['type']==='success'?'success':'error'; ?>"
             data-msg='<?php echo json_encode($flash["msg"]); ?>'></div>
      <?php endif; ?>

      <!-- Filters -->
      <form method="get" class="bg-white border border-gray-200 rounded-lg p-4 mb-4">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
          <div class="md:col-span-2">
            <label class="block text-sm font-medium mb-1">Search class</label>
            <input type="text" name="q" value="<?php echo htmlspecialchars($q); ?>" placeholder="Class name" class="w-full border rounded px-3 py-2" />
          </div>
        </div>
        <div class="mt-4 flex gap-2">
          <button class="px-4 py-2 bg-primary text-white rounded hover:opacity-90" type="submit"><i class="fa fa-search mr-2"></i>Filter</button>
          <a href="<?php echo basename($_SERVER['PHP_SELF']); ?>" class="px-4 py-2 border rounded hover:bg-gray-50"><i class="fa fa-rotate mr-2"></i>Reset</a>
        </div>
      </form>

      <!-- Classes Table -->
      <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Class</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Adviser</th>
                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <?php if (!$classes): ?>
                <tr><td colspan="3" class="px-4 py-6 text-center text-gray-500">No classes found.</td></tr>
              <?php endif; ?>
              <?php foreach ($classes as $c): $cid=(int)$c['id']; $adv = $c['first_name']? ($c['first_name'].' '.$c['last_name']) : '—'; ?>
                <tr>
                  <td class="px-4 py-3 align-top">
                    <div class="font-medium text-dark"><?php echo htmlspecialchars($c['class_name']); ?></div>
                  </td>
                  <td class="px-4 py-3 align-top">
                    <div class="text-gray-800"><?php echo htmlspecialchars($adv); ?></div>
                  </td>
                  <td class="px-4 py-3 align-top">
                    <div class="flex justify-end gap-2">
                      <button data-id="<?php echo $cid; ?>" data-name="<?php echo htmlspecialchars($c['class_name']); ?>" class="btnRenameClass px-3 py-1.5 border rounded hover:bg-gray-50"><i class="fa fa-pen mr-1"></i>Rename</button>
                      <?php if ($c['adviser_staff_id']): ?>
                        <form method="post" class="confirm-unassign">
                          <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>" />
                          <input type="hidden" name="action" value="unassign_teacher" />
                          <input type="hidden" name="class_id" value="<?php echo $cid; ?>" />
                          <button class="px-3 py-1.5 border rounded hover:bg-gray-50" type="submit"><i class="fa fa-user-xmark mr-1"></i>Unassign</button>
                        </form>
                      <?php endif; ?>
                      <button data-id="<?php echo $cid; ?>" data-name="<?php echo htmlspecialchars($c['class_name']); ?>" class="btnAssign px-3 py-1.5 bg-primary text-white rounded hover:opacity-90"><i class="fa fa-user-check mr-1"></i>Assign</button>
                      <form method="post" class="confirm-delete-class">
                        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>" />
                        <input type="hidden" name="action" value="delete_class" />
                        <input type="hidden" name="class_id" value="<?php echo $cid; ?>" />
                        <button class="px-3 py-1.5 bg-red-50 text-red-600 border border-red-200 rounded hover:bg-red-100" type="submit"><i class="fa fa-trash mr-1"></i>Delete</button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </main>

</div>

<!-- Modals -->
<div id="modalCreateClass" class="hidden fixed inset-0 z-50 items-center justify-center bg-black/30 p-4">
  <div class="bg-white rounded-lg shadow max-w-md w-full p-6">
    <h3 class="text-lg font-semibold mb-4">Create Class</h3>
    <form method="post" class="space-y-3">
      <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>" />
      <input type="hidden" name="action" value="create_class" />
      <div>
        <label class="block text-sm font-medium mb-1">Class name</label>
        <input type="text" name="class_name" class="w-full border rounded px-3 py-2" required />
      </div>
      <div class="flex justify-end gap-2 pt-2">
        <button type="button" class="btnCloseModal px-4 py-2 border rounded">Cancel</button>
        <button type="submit" class="px-4 py-2 bg-primary text-white rounded">Create</button>
      </div>
    </form>
  </div>
  </div>

<div id="modalRenameClass" class="hidden fixed inset-0 z-50 items-center justify-center bg-black/30 p-4">
  <div class="bg-white rounded-lg shadow max-w-md w-full p-6">
    <h3 class="text-lg font-semibold mb-4">Rename Class</h3>
    <form method="post" class="space-y-3">
      <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>" />
      <input type="hidden" name="action" value="rename_class" />
      <input type="hidden" name="class_id" id="rename_class_id" />
      <div>
        <label class="block text-sm font-medium mb-1">New name</label>
        <input type="text" name="class_name" id="rename_class_name" class="w-full border rounded px-3 py-2" required />
      </div>
      <div class="flex justify-end gap-2 pt-2">
        <button type="button" class="btnCloseModal px-4 py-2 border rounded">Cancel</button>
        <button type="submit" class="px-4 py-2 bg-primary text-white rounded">Save</button>
      </div>
    </form>
  </div>
  </div>

<div id="modalAssign" class="hidden fixed inset-0 z-50 items-center justify-center bg-black/30 p-4">
  <div class="bg-white rounded-lg shadow max-w-md w-full p-6">
    <h3 class="text-lg font-semibold mb-4">Assign Teacher to <span id="assign_class_label" class="font-semibold"></span></h3>
    <form method="post" class="space-y-3">
      <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>" />
      <input type="hidden" name="action" value="assign_teacher" />
      <input type="hidden" name="class_id" id="assign_class_id" />
      <div>
        <label class="block text-sm font-medium mb-1">Teacher</label>
        <select name="staff_id" class="w-full border rounded px-3 py-2" required>
          <option value="">Select teacher</option>
          <?php foreach ($teachers as $t): ?>
            <option value="<?php echo (int)$t['id']; ?>"><?php echo htmlspecialchars(($t['last_name'] . ', ' . $t['first_name']) . ($t['department']? (' — '.$t['department']) : '')); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="flex justify-end gap-2 pt-2">
        <button type="button" class="btnCloseModal px-4 py-2 border rounded">Cancel</button>
        <button type="submit" class="px-4 py-2 bg-primary text-white rounded">Assign</button>
      </div>
    </form>
  </div>
  </div>

<script>
  document.addEventListener('DOMContentLoaded', function() {
    // Flash
    const fd = document.getElementById('flashData');
    if (fd && window.Swal) {
      const type = fd.dataset.type;
      const msg = JSON.parse(fd.dataset.msg || '""');
      if (msg) {
        Swal.fire({ icon: type, title: type === 'success' ? 'Success' : 'Error', text: msg });
      }
    }

    // Modal helpers
    const openModal = (el) => { el.classList.remove('hidden'); el.classList.add('flex'); };
    const closeModal = (el) => { el.classList.add('hidden'); el.classList.remove('flex'); };

    const modalCreate = document.getElementById('modalCreateClass');
    const modalRename = document.getElementById('modalRenameClass');
    const modalAssign = document.getElementById('modalAssign');

    document.getElementById('btnOpenCreateClass')?.addEventListener('click', () => openModal(modalCreate));

    document.querySelectorAll('.btnRenameClass').forEach(btn => {
      btn.addEventListener('click', () => {
        document.getElementById('rename_class_id').value = btn.dataset.id;
        document.getElementById('rename_class_name').value = btn.dataset.name;
        openModal(modalRename);
      });
    });

    document.querySelectorAll('.btnAssign').forEach(btn => {
      btn.addEventListener('click', () => {
        document.getElementById('assign_class_id').value = btn.dataset.id;
        document.getElementById('assign_class_label').textContent = btn.dataset.name;
        openModal(modalAssign);
      });
    });

    document.querySelectorAll('.btnCloseModal').forEach(btn => {
      btn.addEventListener('click', () => {
        [modalCreate, modalRename, modalAssign].forEach(m => m && closeModal(m));
      });
    });

    // SweetAlert confirmations
    document.querySelectorAll('form.confirm-unassign').forEach((f) => {
      f.addEventListener('submit', (e) => {
        e.preventDefault();
        if (!window.Swal) return f.submit();
        Swal.fire({
          title: 'Unassign adviser?',
          text: 'This will remove the current adviser from the class.',
          icon: 'warning',
          showCancelButton: true,
          confirmButtonColor: '#3085d6',
          cancelButtonColor: '#d33',
          confirmButtonText: 'Yes, unassign'
        }).then((result) => { if (result.isConfirmed) f.submit(); });
      });
    });

    document.querySelectorAll('form.confirm-delete-class').forEach((f) => {
      f.addEventListener('submit', (e) => {
        e.preventDefault();
        if (!window.Swal) return f.submit();
        Swal.fire({
          title: 'Delete this class?',
          text: 'This action cannot be undone.',
          icon: 'warning',
          showCancelButton: true,
          confirmButtonColor: '#3085d6',
          cancelButtonColor: '#d33',
          confirmButtonText: 'Yes, delete it'
        }).then((result) => { if (result.isConfirmed) f.submit(); });
      });
    });
  });
</script>

<?php include_once __DIR__ . '/../../components/admin-footer.php'; ?>