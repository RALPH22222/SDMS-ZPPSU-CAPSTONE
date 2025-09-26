<?php
  // Manage Users Page
  // Requirements implemented per DB schema:
  // - Single role per user via users.role_id (NOT NULL)
  // - Status via users.is_active (0/1)
  // - Roles management via roles table (unique name)
  // - Passwords stored using password_hash

  session_start();
  // Simple CSRF token
  if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
  }
  $csrf = $_SESSION['csrf_token'];

  require_once __DIR__ . '/../../database/database.php';

  // Seed default roles if none exist
  try {
    $countStmt = $pdo->query('SELECT COUNT(*) FROM roles');
    $roleCount = (int)$countStmt->fetchColumn();
    if ($roleCount === 0) {
      $seed = [
        ['Admin', 'Full access to admin panel'],
        ['Staff', 'Manage cases, violations, and reports'],
        ['Teacher', 'Limited staff operations'],
        ['Parent', 'Parent portal access'],
        ['Student', 'Student portal access'],
      ];
      $ins = $pdo->prepare('INSERT INTO roles (id, name, description, created_at) VALUES (NULL, ?, ?, CURRENT_TIMESTAMP())');
      foreach ($seed as $r) { $ins->execute([$r[0], $r[1]]); }
    }
  } catch (Exception $e) {
    // If seeding fails, continue – UI should still load
  }

  $flash = ['type' => null, 'msg' => null];
  // Retrieve flash from session if present (PRG pattern)
  if (!empty($_SESSION['flash']) && is_array($_SESSION['flash'])) {
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
  }

  function flash($type, $msg) {
    return ['type' => $type, 'msg' => $msg];
  }

  function ensure_csrf() {
    if (!isset($_POST['csrf']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf'])) {
      throw new Exception('Invalid CSRF token.');
    }
  }

  // Handle actions
  try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      ensure_csrf();
      $action = $_POST['action'] ?? '';

      if ($action === 'create_user') {
        try {
            // Enable error reporting for PDO
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Start transaction
            $pdo->beginTransaction();
            
            $username = trim($_POST['username'] ?? '');
            $email = trim($_POST['email'] ?? '') ?: null;
            $contact = trim($_POST['contact_number'] ?? '') ?: null;
            $role_id = (int)($_POST['role_id'] ?? 0);
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            $plain = trim($_POST['password'] ?? '');
            
            error_log("Creating user with data: " . print_r($_POST, true));
            
            // Validate required fields
            if ($username === '' || $role_id <= 0 || $plain === '') {
                throw new Exception('Username, Role and Password are required.');
            }
            
            // Check if role exists
            $roleCheck = $pdo->prepare('SELECT id, name FROM roles WHERE id = ?');
            $roleCheck->execute([$role_id]);
            $role = $roleCheck->fetch(PDO::FETCH_ASSOC);
            
            if (!$role) {
                throw new Exception('Invalid role selected (ID: ' . $role_id . '). Available roles: ' . 
                    implode(', ', array_map(function($r) { return $r['id'] . ':' . $r['name']; }, $pdo->query('SELECT id, name FROM roles')->fetchAll(PDO::FETCH_ASSOC))));
            }
            
            // Check if username already exists
            $userCheck = $pdo->prepare('SELECT id FROM users WHERE username = ?');
            $userCheck->execute([$username]);
            if ($userCheck->fetch()) {
                throw new Exception('Username already exists. Please choose a different username.');
            }
            
            $hash = password_hash($plain, PASSWORD_DEFAULT);
            
            // Get the list of columns in the users table
            $columns = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);
            error_log("Users table columns: " . print_r($columns, true));
            
            // Prepare the SQL with explicit column names
            $sql = 'INSERT INTO users (username, password_hash, email, contact_number, role_id, is_active, last_login, created_at) ' . 
                   'VALUES (?, ?, ?, ?, ?, ?, NULL, CURRENT_TIMESTAMP())';
            
            error_log("Executing SQL: $sql");
            error_log("With values: " . print_r([$username, $hash, $email, $contact, $role_id, $is_active], true));
            
            $stmt = $pdo->prepare($sql);
            $result = $stmt->execute([$username, $hash, $email, $contact, $role_id, $is_active]);
            
            if ($result) {
                $pdo->commit();
                $_SESSION['flash'] = flash('success', 'User created successfully.');
                error_log("User created successfully");
            } else {
                throw new Exception('Failed to create user. Database error: ' . implode(' ', $stmt->errorInfo()));
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Database error: " . $e->getMessage());
            throw new Exception('Database error: ' . $e->getMessage());
        } catch (Exception $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Error creating user: " . $e->getMessage());
            throw $e;
        }
        
        header('Location: ' . basename($_SERVER['PHP_SELF']));
        exit;
      }

      if ($action === 'update_user') {
        $id = (int)($_POST['id'] ?? 0);
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '') ?: null;
        $contact = trim($_POST['contact_number'] ?? '') ?: null;
        $role_id = (int)($_POST['role_id'] ?? 0);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        if ($id <= 0 || $username === '' || $role_id <= 0) throw new Exception('User ID, Username and Role are required.');
        $stmt = $pdo->prepare('UPDATE users SET username = ?, email = ?, contact_number = ?, role_id = ?, is_active = ?, updated_at = CURRENT_TIMESTAMP() WHERE id = ?');
        $stmt->execute([$username, $email, $contact, $role_id, $is_active, $id]);
        $_SESSION['flash'] = flash('success', 'User updated successfully.');
        header('Location: ' . basename($_SERVER['PHP_SELF']));
        exit;
      }

      if ($action === 'toggle_user_status') {
        $id = (int)($_POST['id'] ?? 0);
        $is_active = (int)($_POST['is_active'] ?? 0) ? 1 : 0;
        if ($id <= 0) throw new Exception('Invalid user.');
        $stmt = $pdo->prepare('UPDATE users SET is_active = ?, updated_at = CURRENT_TIMESTAMP() WHERE id = ?');
        $stmt->execute([$is_active, $id]);
        $_SESSION['flash'] = flash('success', 'User status updated.');
        header('Location: ' . basename($_SERVER['PHP_SELF']));
        exit;
      }

      if ($action === 'reset_password') {
        $id = (int)($_POST['id'] ?? 0);
        $new_plain = $_POST['new_password'] ?? '';
        if ($id <= 0 || $new_plain === '') throw new Exception('Invalid password reset request.');
        $hash = password_hash($new_plain, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('UPDATE users SET password_hash = ?, updated_at = CURRENT_TIMESTAMP() WHERE id = ?');
        $stmt->execute([$hash, $id]);
        $_SESSION['flash'] = flash('success', 'Password reset successfully.');
        header('Location: ' . basename($_SERVER['PHP_SELF']));
        exit;
      }

      if ($action === 'create_role') {
        $name = trim($_POST['role_name'] ?? '');
        $desc = trim($_POST['role_desc'] ?? '') ?: null;
        if ($name === '') throw new Exception('Role name is required.');
        $stmt = $pdo->prepare('INSERT INTO roles (id, name, description, created_at) VALUES (NULL, ?, ?, CURRENT_TIMESTAMP())');
        $stmt->execute([$name, $desc]);
        $_SESSION['flash'] = flash('success', 'Role created.');
        header('Location: ' . basename($_SERVER['PHP_SELF']));
        exit;
      }

      if ($action === 'update_role') {
        $id = (int)($_POST['role_id'] ?? 0);
        $name = trim($_POST['role_name'] ?? '');
        $desc = trim($_POST['role_desc'] ?? '') ?: null;
        if ($id <= 0 || $name === '') throw new Exception('Role and name are required.');
        $stmt = $pdo->prepare('UPDATE roles SET name = ?, description = ? WHERE id = ?');
        $stmt->execute([$name, $desc, $id]);
        $_SESSION['flash'] = flash('success', 'Role updated.');
        header('Location: ' . basename($_SERVER['PHP_SELF']));
        exit;
      }

      if ($action === 'delete_role') {
        $id = (int)($_POST['role_id'] ?? 0);
        if ($id <= 0) throw new Exception('Invalid role.');
        // Check if role is in use
        $cnt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE role_id = ?');
        $cnt->execute([$id]);
        if ((int)$cnt->fetchColumn() > 0) {
          throw new Exception('Cannot delete role: it is assigned to existing users.');
        }
        $stmt = $pdo->prepare('DELETE FROM roles WHERE id = ?');
        $stmt->execute([$id]);
        $_SESSION['flash'] = flash('success', 'Role deleted.');
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
  $filter_role = (int)($_GET['role'] ?? 0);
  $filter_status = ($_GET['status'] ?? '') !== '' ? (int)$_GET['status'] : null; // 0 or 1

  // Fetch roles for selects
  $roles = $pdo->query('SELECT id, name, description FROM roles ORDER BY name ASC')->fetchAll(PDO::FETCH_ASSOC);

  // Fetch users list
  $where = [];
  $params = [];
  if ($q !== '') { $where[] = '(u.username LIKE ? OR u.email LIKE ? OR u.contact_number LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%"; }
  if ($filter_role > 0) { $where[] = 'u.role_id = ?'; $params[] = $filter_role; }
  if ($filter_status !== null) { $where[] = 'u.is_active = ?'; $params[] = $filter_status; }
  $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
  $usersStmt = $pdo->prepare("SELECT u.id, u.username, u.email, u.contact_number, u.role_id, u.is_active, u.last_login, r.name AS role_name
                              FROM users u
                              JOIN roles r ON r.id = u.role_id
                              $whereSql
                              ORDER BY u.created_at DESC");
  $usersStmt->execute($params);
  $users = $usersStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<?php $pageTitle = 'Manage Users - SDMS'; include_once __DIR__ . '/../../components/admin-head.php'; ?>

<div class="md:ml-64">
  <?php include_once __DIR__ . '/../../components/admin-sidebar.php'; ?>

  <main class="p-6">
    <div class="max-w-7xl mx-auto">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-semibold text-dark">Manage Users</h1>
        <div class="flex gap-2">
          <button id="btnOpenCreateUser" class="px-4 py-2 bg-primary text-white rounded hover:opacity-90"><i class="fa fa-plus mr-2"></i>Create User</button>
          <!-- <button id="btnOpenRoles" class="px-4 py-2 bg-secondary text-black rounded hover:bg-secondary/90 transition-colors"><i class="fa fa-user-shield mr-2"></i>Manage Roles</button> -->
        </div>
      </div>

      <?php if ($flash['type']): ?>
        <div class="hidden" id="flashData"
             data-type="<?php echo $flash['type']==='success'?'success':'error'; ?>"
             data-msg='<?php echo json_encode($flash['msg']); ?>'></div>
      <?php endif; ?>

      <form method="get" class="mb-4 grid grid-cols-1 md:grid-cols-4 gap-3">
        <input type="text" name="q" value="<?php echo htmlspecialchars($q); ?>" placeholder="Search username, email, contact" class="border rounded px-3 py-2 w-full" />
        <select name="role" class="border rounded px-3 py-2 w-full">
          <option value="0">All Roles</option>
          <?php foreach ($roles as $r): ?>
            <option value="<?php echo (int)$r['id']; ?>" <?php echo $filter_role===(int)$r['id']?'selected':''; ?>><?php echo htmlspecialchars($r['name']); ?></option>
          <?php endforeach; ?>
        </select>
        <select name="status" class="border rounded px-3 py-2 w-full">
          <option value="" <?php echo $filter_status===null?'selected':''; ?>>All Status</option>
          <option value="1" <?php echo $filter_status===1?'selected':''; ?>>Active</option>
          <option value="0" <?php echo $filter_status===0?'selected':''; ?>>Deactivated</option>
        </select>
        <button class="px-4 py-2 bg-gray-800 text-white rounded">Filter</button>
      </form>

      <div class="overflow-x-auto bg-white border border-gray-200 rounded-lg">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Username</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Contact</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Role</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Last Login</th>
              <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200">
            <?php if (!$users): ?>
              <tr><td colspan="7" class="px-4 py-6 text-center text-gray-500">No users found.</td></tr>
            <?php else: ?>
              <?php foreach ($users as $u): ?>
                <tr class="hover:bg-gray-50">
                  <td class="px-4 py-3 text-sm font-medium text-dark"><?php echo htmlspecialchars($u['username']); ?></td>
                  <td class="px-4 py-3 text-sm"><?php echo htmlspecialchars($u['email'] ?? ''); ?></td>
                  <td class="px-4 py-3 text-sm"><?php echo htmlspecialchars($u['contact_number'] ?? ''); ?></td>
                  <td class="px-4 py-3 text-sm"><?php echo htmlspecialchars($u['role_name']); ?></td>
                  <td class="px-4 py-3 text-sm">
                    <?php if ((int)$u['is_active'] === 1): ?>
                      <span class="inline-flex items-center px-2 py-1 text-xs font-medium bg-green-100 text-green-800 rounded">Active</span>
                    <?php else: ?>
                      <span class="inline-flex items-center px-2 py-1 text-xs font-medium bg-red-100 text-red-800 rounded">Deactivated</span>
                    <?php endif; ?>
                  </td>
                  <td class="px-4 py-3 text-sm"><?php echo htmlspecialchars($u['last_login'] ?? ''); ?></td>
                  <td class="px-4 py-3 text-sm text-right">
                    <div class="inline-flex gap-2">
                      <button class="px-2 py-1 text-blue-600 hover:underline" onclick='openEditUser(<?php echo json_encode($u, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT); ?>)'>Edit</button>
                      <form method="post" class="inline confirm-toggle">
                        <input type="hidden" name="csrf" value="<?php echo $csrf; ?>" />
                        <input type="hidden" name="action" value="toggle_user_status" />
                        <input type="hidden" name="id" value="<?php echo (int)$u['id']; ?>" />
                        <input type="hidden" name="is_active" value="<?php echo (int)$u['is_active']===1?0:1; ?>" />
                        <button class="px-2 py-1 text-orange-600 hover:underline"><?php echo (int)$u['is_active']===1?'Deactivate':'Activate'; ?></button>
                      </form>
                      <button class="px-2 py-1 text-purple-700 hover:underline" onclick="openResetPassword(<?php echo (int)$u['id']; ?>, '<?php echo htmlspecialchars($u['username'], ENT_QUOTES); ?>')">Reset Password</button>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <!-- Pagination placeholder -->
      <div class="mt-4 text-sm text-gray-500">Showing <?php echo count($users); ?> result(s).</div>
    </div>
  </main>
</div>

<!-- Create/Edit User Modal -->
<div id="userModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
  <div class="bg-white w-full max-w-lg rounded-lg shadow-lg p-6">
    <div class="flex items-center justify-between mb-4">
      <h2 id="userModalTitle" class="text-lg font-semibold">Create User</h2>
      <button onclick="closeUserModal()" class="text-gray-500 hover:text-gray-700"><i class="fa fa-times"></i></button>
    </div>
    <form method="post" id="userForm">
      <input type="hidden" name="csrf" value="<?php echo $csrf; ?>" />
      <input type="hidden" name="action" value="create_user" />
      <input type="hidden" name="id" id="userId" value="" />

      <div class="space-y-3">
        <div>
          <label class="block text-sm font-medium mb-1">Username <span class="text-red-500">*</span></label>
          <input type="text" name="username" id="username" class="w-full border rounded px-3 py-2" required />
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
          <div>
            <label class="block text-sm font-medium mb-1">Email</label>
            <input type="email" name="email" id="email" class="w-full border rounded px-3 py-2" />
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Contact Number</label>
            <input type="text" name="contact_number" id="contact_number" class="w-full border rounded px-3 py-2" />
          </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
          <div>
            <label class="block text-sm font-medium mb-1">Role <span class="text-red-500">*</span></label>
            <select name="role_id" id="role_id" class="w-full border rounded px-3 py-2" required>
              <option value="">Select role</option>
              <?php foreach ($roles as $r): ?>
                <option value="<?php echo (int)$r['id']; ?>"><?php echo htmlspecialchars($r['name']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="flex items-center gap-2 mt-6 md:mt-0">
            <input type="checkbox" name="is_active" id="is_active" class="h-4 w-4" checked />
            <label for="is_active" class="text-sm">Active</label>
          </div>
        </div>
        <div id="passwordRow">
          <label class="block text-sm font-medium mb-1">Password <span class="text-red-500">*</span></label>
          <div class="flex">
            <input type="text" name="password" id="password" class="flex-1 border rounded-l px-3 py-2" required />
            <button type="button" onclick="genPwd('#password')" class="px-3 py-2 bg-gray-100 border rounded-r hover:bg-gray-200">Generate</button>
          </div>
          <p class="text-xs text-gray-500 mt-1">Auto-generate generates a strong password and fills the field. It will be hashed in the database.</p>
        </div>
      </div>

      <div class="mt-6 flex justify-end gap-2">
        <button type="button" onclick="closeUserModal()" class="px-4 py-2 border rounded">Cancel</button>
        <button class="px-4 py-2 bg-primary text-white rounded">Save</button>
      </div>
    </form>
  </div>
</div>

<!-- Reset Password Modal -->
<div id="resetModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
  <div class="bg-white w-full max-w-md rounded-lg shadow-lg p-6">
    <div class="flex items-center justify-between mb-4">
      <h2 class="text-lg font-semibold">Reset Password</h2>
      <button onclick="closeReset()" class="text-gray-500 hover:text-gray-700"><i class="fa fa-times"></i></button>
    </div>
    <form method="post" id="resetForm">
      <input type="hidden" name="csrf" value="<?php echo $csrf; ?>" />
      <input type="hidden" name="action" value="reset_password" />
      <input type="hidden" name="id" id="resetUserId" value="" />
      <div>
        <label class="block text-sm font-medium mb-1">New Password</label>
        <div class="flex">
          <input type="text" name="new_password" id="new_password" class="flex-1 border rounded-l px-3 py-2" required />
          <button type="button" onclick="genPwd('#new_password')" class="px-3 py-2 bg-gray-100 border rounded-r hover:bg-gray-200">Generate</button>
        </div>
        <p class="text-xs text-gray-500 mt-1">Password will be hashed and stored. Copy it now; it won't be shown again.</p>
      </div>
      <div class="mt-6 flex justify-end gap-2">
        <button type="button" onclick="closeReset()" class="px-4 py-2 border rounded">Cancel</button>
        <button class="px-4 py-2 bg-primary text-white rounded">Update</button>
      </div>
    </form>
  </div>
</div>

<!-- Manage Roles Modal -->
<div id="rolesModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
  <div class="bg-white w-full max-w-2xl rounded-lg shadow-lg p-6">
    <div class="flex items-center justify-between mb-4">
      <h2 class="text-lg font-semibold">Manage Roles</h2>
      <button onclick="closeRoles()" class="text-gray-500 hover:text-gray-700"><i class="fa fa-times"></i></button>
    </div>

    <div class="mb-4">
      <form method="post" class="grid grid-cols-1 md:grid-cols-3 gap-3 items-end">
        <input type="hidden" name="csrf" value="<?php echo $csrf; ?>" />
        <input type="hidden" name="action" value="create_role" />
        <div>
          <label class="block text-sm font-medium mb-1">Role Name</label>
          <input type="text" name="role_name" class="w-full border rounded px-3 py-2" required />
        </div>
        <div class="md:col-span-2">
          <label class="block text-sm font-medium mb-1">Description</label>
          <input type="text" name="role_desc" class="w-full border rounded px-3 py-2" />
        </div>
        <div class="md:col-span-3 text-right">
          <button class="px-4 py-2 bg-primary text-white rounded">Add Role</button>
        </div>
      </form>
    </div>

    <div class="overflow-x-auto border border-gray-200 rounded">
      <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Role</th>
            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Description</th>
            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
          <?php foreach ($roles as $r): ?>
            <tr>
              <td class="px-4 py-2 text-sm font-medium"><?php echo htmlspecialchars($r['name']); ?></td>
              <td class="px-4 py-2 text-sm"><?php echo htmlspecialchars($r['description'] ?? ''); ?></td>
              <td class="px-4 py-2 text-sm text-right">
                <div class="inline-flex gap-2">
                  <button class="text-blue-600 hover:underline" onclick='openEditRole(<?php echo json_encode($r, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT); ?>)'>Edit</button>
                  <form method="post" class="inline confirm-delete-role">
                    <input type="hidden" name="csrf" value="<?php echo $csrf; ?>" />
                    <input type="hidden" name="action" value="delete_role" />
                    <input type="hidden" name="role_id" value="<?php echo (int)$r['id']; ?>" />
                    <button class="text-red-600 hover:underline">Delete</button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- Edit Role Inline Modal -->
    <div id="editRoleWrap" class="mt-4 hidden">
      <form method="post" class="grid grid-cols-1 md:grid-cols-3 gap-3 items-end">
        <input type="hidden" name="csrf" value="<?php echo $csrf; ?>" />
        <input type="hidden" name="action" value="update_role" />
        <input type="hidden" name="role_id" id="editRoleId" value="" />
        <div>
          <label class="block text-sm font-medium mb-1">Role Name</label>
          <input type="text" name="role_name" id="editRoleName" class="w-full border rounded px-3 py-2" required />
        </div>
        <div class="md:col-span-2">
          <label class="block text-sm font-medium mb-1">Description</label>
          <input type="text" name="role_desc" id="editRoleDesc" class="w-full border rounded px-3 py-2" />
        </div>
        <div class="md:col-span-3 text-right">
          <button class="px-4 py-2 bg-primary text-white rounded">Update Role</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
  // Modal controls
  const userModal = document.getElementById('userModal');
  const rolesModal = document.getElementById('rolesModal');
  const resetModal = document.getElementById('resetModal');
  const userForm = document.getElementById('userForm');
  const userModalTitle = document.getElementById('userModalTitle');
  const passwordRow = document.getElementById('passwordRow');

  document.getElementById('btnOpenCreateUser').addEventListener('click', () => openCreateUser());
  document.getElementById('btnOpenRoles').addEventListener('click', () => openRoles());

  function openCreateUser() {
    userModalTitle.textContent = 'Create User';
    userForm.action.value = 'create_user';
    userForm.id.value = '';
    userForm.username.value = '';
    userForm.email.value = '';
    userForm.contact_number.value = '';
    userForm.role_id.value = '';
    userForm.is_active.checked = true;
    userForm.password.required = true;
    passwordRow.classList.remove('hidden');
    show(userModal);
    genPwd('#password');
  }

  function openEditUser(u) {
    userModalTitle.textContent = 'Edit User';
    userForm.action.value = 'update_user';
    userForm.id.value = u.id;
    userForm.username.value = u.username || '';
    userForm.email.value = u.email || '';
    userForm.contact_number.value = u.contact_number || '';
    userForm.role_id.value = u.role_id || '';
    userForm.is_active.checked = parseInt(u.is_active) === 1;
    userForm.password.value = '';
    userForm.password.required = false;
    passwordRow.classList.add('hidden');
    show(userModal);
  }

  function closeUserModal() { hide(userModal); }

  function openRoles() { show(rolesModal); }
  function closeRoles() { hide(rolesModal); document.getElementById('editRoleWrap').classList.add('hidden'); }

  function openEditRole(r) {
    document.getElementById('editRoleWrap').classList.remove('hidden');
    document.getElementById('editRoleId').value = r.id;
    document.getElementById('editRoleName').value = r.name || '';
    document.getElementById('editRoleDesc').value = r.description || '';
  }

  function openResetPassword(userId, username) {
    document.getElementById('resetUserId').value = userId;
    document.getElementById('new_password').value = genPassword();
    show(resetModal);
  }
  function closeReset() { hide(resetModal); }

  function show(el) { el.classList.remove('hidden'); el.classList.add('flex'); }
  function hide(el) { el.classList.add('hidden'); el.classList.remove('flex'); }

  function genPwd(selector) {
    const pwd = genPassword();
    const input = document.querySelector(selector);
    if (input) input.value = pwd;
  }
  function genPassword() {
    const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%^&*()_+-=';
    let pwd = '';
    for (let i = 0; i < 12; i++) {
      pwd += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    return pwd;
  }

  // SweetAlert integrations
  document.addEventListener('DOMContentLoaded', () => {
    // Flash notification
    const flash = document.getElementById('flashData');
    if (flash && window.Swal) {
      const type = flash.getAttribute('data-type') || 'info';
      const msg = JSON.parse(flash.getAttribute('data-msg') || '""');
      Swal.fire({ icon: type, title: type === 'success' ? 'Success' : 'Error', text: msg });
    }

    // Confirm toggle user status
    document.querySelectorAll('form.confirm-toggle').forEach((f) => {
      f.addEventListener('submit', (e) => {
        e.preventDefault();
        Swal.fire({
          title: 'Are you sure?',
          text: 'This will change the user\'s active status.',
          icon: 'warning',
          showCancelButton: true,
          confirmButtonColor: '#3085d6',
          cancelButtonColor: '#d33',
          confirmButtonText: 'Yes, proceed'
        }).then((result) => { if (result.isConfirmed) f.submit(); });
      });
    });

    // Confirm delete role
    document.querySelectorAll('form.confirm-delete-role').forEach((f) => {
      f.addEventListener('submit', (e) => {
        e.preventDefault();
        Swal.fire({
          title: 'Delete role?',
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