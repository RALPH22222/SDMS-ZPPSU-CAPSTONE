<?php
  $pageTitle = 'Manage Marshals - SDMS';
  require_once __DIR__ . '/../../components/admin-head.php';
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

  $marshalRoleId = 5;
  try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      ensure_csrf();
      $action = $_POST['action'] ?? '';

      if ($action === 'edit_marshal') {
        $id = (int)($_POST['marshal_id'] ?? 0);
        $first_name = trim($_POST['first_name'] ?? '');
        $middle_name = trim($_POST['middle_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $suffix = trim($_POST['suffix'] ?? '');
        $staff_number = trim($_POST['staff_number'] ?? '');
        $position = trim($_POST['position'] ?? '');
        
        if ($id <= 0) throw new Exception('Invalid marshal.');
        if ($first_name === '' || $last_name === '') throw new Exception('First name and last name are required.');
        if ($staff_number === '') throw new Exception('Staff number is required.');
        
        // Check if staff_number is already taken by another marshal
        $checkStmt = $pdo->prepare('SELECT id FROM marshal WHERE staff_number = ? AND id != ?');
        $checkStmt->execute([$staff_number, $id]);
        if ($checkStmt->fetch()) {
          throw new Exception('Staff number already exists.');
        }
        
        $stmt = $pdo->prepare('UPDATE marshal SET first_name = ?, middle_name = ?, last_name = ?, suffix = ?, staff_number = ?, position = ? WHERE id = ?');
        $stmt->execute([$first_name, $middle_name ?: null, $last_name, $suffix ?: null, $staff_number, $position ?: null, $id]);
        $_SESSION['flash'] = flash('success', 'Marshal updated successfully.');
        header('Location: ' . basename($_SERVER['PHP_SELF']));
        exit;
      }

      if ($action === 'delete_marshal') {
        $id = (int)($_POST['marshal_id'] ?? 0);
        if ($id <= 0) throw new Exception('Invalid marshal.');
        
        // Check if marshal has any cases
        $caseCheck = $pdo->prepare('SELECT COUNT(*) FROM cases WHERE reported_by_marshal_id = ?');
        $caseCheck->execute([$id]);
        $caseCount = (int)$caseCheck->fetchColumn();
        
        if ($caseCount > 0) {
          throw new Exception('Cannot delete marshal. This marshal has ' . $caseCount . ' case(s) associated.');
        }
        
        $stmt = $pdo->prepare('DELETE FROM marshal WHERE id = ?');
        $stmt->execute([$id]);
        $_SESSION['flash'] = flash('success', 'Marshal deleted successfully.');
        header('Location: ' . basename($_SERVER['PHP_SELF']));
        exit;
      }

      if ($action === 'assign_department') {
        $marshalId = (int)($_POST['marshal_id'] ?? 0);
        $departmentId = (int)($_POST['department_id'] ?? 0);
        
        if ($marshalId <= 0) throw new Exception('Invalid marshal.');
        if ($departmentId <= 0) throw new Exception('Department is required.');
        
        // Check if department is already assigned to another marshal
        $checkStmt = $pdo->prepare('SELECT id FROM marshal WHERE department_id = ? AND id != ?');
        $checkStmt->execute([$departmentId, $marshalId]);
        if ($checkStmt->fetch()) {
          throw new Exception('This department is already assigned to another marshal.');
        }
        
        $stmt = $pdo->prepare('UPDATE marshal SET department_id = ? WHERE id = ?');
        $stmt->execute([$departmentId, $marshalId]);
        $_SESSION['flash'] = flash('success', 'Marshal assigned to department successfully.');
        header('Location: ' . basename($_SERVER['PHP_SELF']));
        exit;
      }

      if ($action === 'unassign_department') {
        $marshalId = (int)($_POST['marshal_id'] ?? 0);
        if ($marshalId <= 0) throw new Exception('Invalid marshal.');
        $stmt = $pdo->prepare('UPDATE marshal SET department_id = NULL WHERE id = ?');
        $stmt->execute([$marshalId]);
        $_SESSION['flash'] = flash('success', 'Marshal unassigned from department successfully.');
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

  $q = trim($_GET['q'] ?? '');
  $where = '';
  $params = [];
  if ($q !== '') { 
    $where = 'WHERE (m.first_name LIKE ? OR m.last_name LIKE ? OR m.staff_number LIKE ? OR d.department_name LIKE ?)'; 
    $params[] = "%$q%";
    $params[] = "%$q%";
    $params[] = "%$q%";
    $params[] = "%$q%";
  }
  
  $sql = "SELECT m.id, m.staff_number, m.first_name, m.middle_name, m.last_name, m.suffix, m.position, 
                 m.department_id, m.user_id,
                 d.department_name, d.abbreviation,
                 u.username, u.email, u.contact_number
          FROM marshal m
          LEFT JOIN departments d ON d.id = m.department_id
          LEFT JOIN users u ON u.id = m.user_id
          $where
          ORDER BY m.last_name ASC, m.first_name ASC";
  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);
  $marshals = $stmt->fetchAll(PDO::FETCH_ASSOC);
  
  // Get all departments for assignment (including unassigned ones)
  $deptStmt = $pdo->query('SELECT id, department_name, abbreviation FROM departments ORDER BY department_name ASC');
  $departments = $deptStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<?php $pageTitle = 'Manage Marshals - SDMS'; include_once __DIR__ . '/../../components/admin-head.php'; ?>

<div class="min-h-screen">
  <div class="md:hidden sticky top-0 z-40 bg-white border-b border-gray-200">
    <div class="h-16 flex items-center px-4">
      <button id="adminSidebarToggle" class="text-primary text-2xl mr-3">
        <i class="fa-solid fa-bars"></i>
      </button>
      <h1 class="text-xl font-semibold text-dark">Manage Marshals</h1>
    </div>
  </div>

  <?php include_once __DIR__ . '/../../components/admin-sidebar.php'; ?>

  <main class="md:ml-64 p-4 md:p-6">
    <div class="max-w-7xl mx-auto">
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
        <h1 class="text-xl md:text-2xl font-semibold text-dark">Manage Marshals</h1>
      </div>

      <?php if ($flash['type']): ?>
        <div class="hidden" id="flashData"
             data-type="<?php echo $flash['type']==='success'?'success':'error'; ?>"
             data-msg='<?php echo json_encode($flash["msg"]); ?>'></div>
      <?php endif; ?>
      
      <form method="get" class="bg-white border border-gray-200 rounded-lg p-4 mb-4">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
          <div class="md:col-span-2">
            <label class="block text-sm font-medium mb-1">Search marshal</label>
            <input type="text" name="q" value="<?php echo htmlspecialchars($q); ?>" placeholder="Name, staff number, or department" class="w-full border rounded px-3 py-2" />
          </div>
        </div>
        <div class="mt-4 flex flex-col sm:flex-row gap-2">
          <button class="px-4 py-2 bg-primary text-white rounded hover:opacity-90 w-full sm:w-auto text-center" type="submit">
            <i class="fa fa-search mr-2"></i>Filter
          </button>
          <a href="<?php echo basename($_SERVER['PHP_SELF']); ?>" class="px-4 py-2 border rounded hover:bg-gray-50 text-center">
            <i class="fa fa-rotate mr-2"></i>Reset
          </a>
        </div>
      </form>

      <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Marshal</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Staff Number</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Position</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Department</th>
                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <?php if (empty($marshals)): ?>
                <tr><td colspan="5" class="px-4 py-6 text-center text-gray-500">No marshals found.</td></tr>
              <?php endif; ?>
              <?php foreach ($marshals as $m): 
                $mid = (int)$m['id'];
                $fullName = trim(($m['first_name'] ?? '') . ' ' . ($m['middle_name'] ?? '') . ' ' . ($m['last_name'] ?? ''));
                $fullName = trim($fullName);
                if ($m['suffix']) $fullName .= ' ' . $m['suffix'];
                $deptName = $m['department_name'] ? htmlspecialchars($m['department_name']) : '—';
              ?>
                <tr>
                  <td class="px-4 py-3 align-top">
                    <div class="font-medium text-dark"><?php echo htmlspecialchars($fullName); ?></div>
                    <?php if ($m['username']): ?>
                      <div class="text-sm text-gray-500"><?php echo htmlspecialchars($m['username']); ?></div>
                    <?php endif; ?>
                  </td>
                  <td class="px-4 py-3 align-top">
                    <div class="text-gray-800"><?php echo htmlspecialchars($m['staff_number'] ?? '—'); ?></div>
                  </td>
                  <td class="px-4 py-3 align-top">
                    <div class="text-gray-800"><?php echo htmlspecialchars($m['position'] ?? '—'); ?></div>
                  </td>
                  <td class="px-4 py-3 align-top">
                    <div class="text-gray-800"><?php echo $deptName; ?></div>
                    <?php if ($m['abbreviation']): ?>
                      <div class="text-sm text-gray-500"><?php echo htmlspecialchars($m['abbreviation']); ?></div>
                    <?php endif; ?>
                  </td>
                  <td class="px-4 py-3 align-top">
                    <div class="flex flex-wrap justify-end gap-2">
                      <button 
                        data-id="<?php echo $mid; ?>"
                        data-first-name="<?php echo htmlspecialchars($m['first_name'] ?? ''); ?>"
                        data-middle-name="<?php echo htmlspecialchars($m['middle_name'] ?? ''); ?>"
                        data-last-name="<?php echo htmlspecialchars($m['last_name'] ?? ''); ?>"
                        data-suffix="<?php echo htmlspecialchars($m['suffix'] ?? ''); ?>"
                        data-staff-number="<?php echo htmlspecialchars($m['staff_number'] ?? ''); ?>"
                        data-position="<?php echo htmlspecialchars($m['position'] ?? ''); ?>"
                        class="btnEditMarshal px-3 py-1.5 border rounded hover:bg-gray-50 text-sm whitespace-nowrap">
                        <i class="fa fa-pen mr-1"></i>Edit
                      </button>
                      <?php if ($m['department_id']): ?>
                        <form method="post" class="confirm-unassign inline-block">
                          <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>" />
                          <input type="hidden" name="action" value="unassign_department" />
                          <input type="hidden" name="marshal_id" value="<?php echo $mid; ?>" />
                          <button class="px-3 py-1.5 border rounded hover:bg-gray-50 text-sm whitespace-nowrap" type="submit">
                            <i class="fa fa-unlink mr-1"></i>Unassign
                          </button>
                        </form>
                      <?php endif; ?>
                      <button 
                        data-id="<?php echo $mid; ?>"
                        data-name="<?php echo htmlspecialchars($fullName); ?>"
                        data-department-id="<?php echo (int)($m['department_id'] ?? 0); ?>"
                        class="btnAssignDept px-3 py-1.5 bg-primary text-white rounded hover:opacity-90 text-sm whitespace-nowrap">
                        <i class="fa fa-building mr-1"></i>Assign Dept
                      </button>
                      <form method="post" class="confirm-delete-marshal inline-block">
                        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>" />
                        <input type="hidden" name="action" value="delete_marshal" />
                        <input type="hidden" name="marshal_id" value="<?php echo $mid; ?>" />
                        <button class="px-3 py-1.5 bg-red-50 text-red-600 border border-red-200 rounded hover:bg-red-100 text-sm whitespace-nowrap" type="submit">
                          <i class="fa fa-trash mr-1"></i>Delete
                        </button>
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

<script>
  document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('admin-sidebar');
    const toggleBtn = document.getElementById('adminSidebarToggle');
    
    if (toggleBtn) {
      toggleBtn.addEventListener('click', function() {
        sidebar.classList.toggle('-translate-x-full');
        sidebar.classList.toggle('md:translate-x-0');
      });
    }
  });
</script>

<div id="modalEditMarshal" class="hidden fixed inset-0 z-50 items-center justify-center bg-black/30 p-4">
  <div class="bg-white rounded-lg shadow max-w-md w-full p-6">
    <h3 class="text-lg font-semibold mb-4">Edit Marshal</h3>
    <form method="post" class="space-y-3">
      <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>" />
      <input type="hidden" name="action" value="edit_marshal" />
      <input type="hidden" name="marshal_id" id="edit_marshal_id" />
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block text-sm font-medium mb-1">First Name <span class="text-red-500">*</span></label>
          <input type="text" name="first_name" id="edit_first_name" class="w-full border rounded px-3 py-2" required />
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Middle Name</label>
          <input type="text" name="middle_name" id="edit_middle_name" class="w-full border rounded px-3 py-2" />
        </div>
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block text-sm font-medium mb-1">Last Name <span class="text-red-500">*</span></label>
          <input type="text" name="last_name" id="edit_last_name" class="w-full border rounded px-3 py-2" required />
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Suffix</label>
          <input type="text" name="suffix" id="edit_suffix" class="w-full border rounded px-3 py-2" placeholder="Jr, Sr, III, etc." />
        </div>
      </div>
      <div>
        <label class="block text-sm font-medium mb-1">Staff Number <span class="text-red-500">*</span></label>
        <input type="text" name="staff_number" id="edit_staff_number" class="w-full border rounded px-3 py-2" required />
      </div>
      <div>
        <label class="block text-sm font-medium mb-1">Position</label>
        <input type="text" name="position" id="edit_position" class="w-full border rounded px-3 py-2" />
      </div>
      <div class="flex justify-end gap-2 pt-2">
        <button type="button" class="btnCloseModal px-4 py-2 border rounded">Cancel</button>
        <button type="submit" class="px-4 py-2 bg-primary text-white rounded">Save</button>
      </div>
    </form>
  </div>
</div>

<div id="modalAssignDept" class="hidden fixed inset-0 z-50 items-center justify-center bg-black/30 p-4">
  <div class="bg-white rounded-lg shadow max-w-md w-full p-6">
    <h3 class="text-lg font-semibold mb-4">Assign Department to <span id="assign_marshal_label" class="font-semibold"></span></h3>
    <form method="post" class="space-y-3">
      <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>" />
      <input type="hidden" name="action" value="assign_department" />
      <input type="hidden" name="marshal_id" id="assign_marshal_id" />
      <div>
        <label class="block text-sm font-medium mb-1">Department <span class="text-red-500">*</span></label>
        <select name="department_id" id="assign_department_id" class="w-full border rounded px-3 py-2" required>
          <option value="">Select department</option>
          <?php foreach ($departments as $dept): ?>
            <option value="<?php echo (int)$dept['id']; ?>"><?php echo htmlspecialchars($dept['department_name']); ?> (<?php echo htmlspecialchars($dept['abbreviation']); ?>)</option>
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
    const fd = document.getElementById('flashData');
    if (fd && window.Swal) {
      const type = fd.dataset.type;
      const msg = JSON.parse(fd.dataset.msg || '""');
      if (msg) {
        Swal.fire({ icon: type, title: type === 'success' ? 'Success' : 'Error', text: msg });
      }
    }
    const openModal = (el) => { el.classList.remove('hidden'); el.classList.add('flex'); };
    const closeModal = (el) => { el.classList.add('hidden'); el.classList.remove('flex'); };

    const modalEdit = document.getElementById('modalEditMarshal');
    const modalAssign = document.getElementById('modalAssignDept');

    document.querySelectorAll('.btnEditMarshal').forEach(btn => {
      btn.addEventListener('click', () => {
        document.getElementById('edit_marshal_id').value = btn.dataset.id;
        document.getElementById('edit_first_name').value = btn.dataset.firstName || '';
        document.getElementById('edit_middle_name').value = btn.dataset.middleName || '';
        document.getElementById('edit_last_name').value = btn.dataset.lastName || '';
        document.getElementById('edit_suffix').value = btn.dataset.suffix || '';
        document.getElementById('edit_staff_number').value = btn.dataset.staffNumber || '';
        document.getElementById('edit_position').value = btn.dataset.position || '';
        openModal(modalEdit);
      });
    });

    document.querySelectorAll('.btnAssignDept').forEach(btn => {
      btn.addEventListener('click', () => {
        document.getElementById('assign_marshal_id').value = btn.dataset.id;
        document.getElementById('assign_marshal_label').textContent = btn.dataset.name;
        document.getElementById('assign_department_id').value = btn.dataset.departmentId || '';
        openModal(modalAssign);
      });
    });

    document.querySelectorAll('.btnCloseModal').forEach(btn => {
      btn.addEventListener('click', () => {
        [modalEdit, modalAssign].forEach(m => m && closeModal(m));
      });
    });

    document.querySelectorAll('form.confirm-unassign').forEach((f) => {
      f.addEventListener('submit', (e) => {
        e.preventDefault();
        if (!window.Swal) return f.submit();
        Swal.fire({
          title: 'Unassign department?',
          text: 'This will remove the marshal from their assigned department.',
          icon: 'warning',
          showCancelButton: true,
          confirmButtonColor: '#3085d6',
          cancelButtonColor: '#d33',
          confirmButtonText: 'Yes, unassign'
        }).then((result) => { if (result.isConfirmed) f.submit(); });
      });
    });

    document.querySelectorAll('form.confirm-delete-marshal').forEach((f) => {
      f.addEventListener('submit', (e) => {
        e.preventDefault();
        if (!window.Swal) return f.submit();
        Swal.fire({
          title: 'Delete this marshal?',
          text: 'This action cannot be undone. Make sure the marshal has no associated cases.',
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
