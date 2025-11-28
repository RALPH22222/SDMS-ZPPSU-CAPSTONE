<?php
  $pageTitle = 'Activity Log - SDMS';
  require_once '../../components/admin-head.php';
  require_once '../../database/database.php';
  $q = isset($_GET['q']) ? trim($_GET['q']) : '';
  $fltTable = isset($_GET['table_name']) ? trim($_GET['table_name']) : '';
  $fltAction = isset($_GET['action']) ? trim($_GET['action']) : '';
  $fltUser = isset($_GET['user_id']) ? trim($_GET['user_id']) : '';
  $dateFrom = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
  $dateTo = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';
  $page = isset($_GET['page']) && ctype_digit($_GET['page']) ? (int)$_GET['page'] : 1;
  if ($page < 1) $page = 1;
  $pageSize = 20;
  $offset = ($page - 1) * $pageSize;
  $where = [];
  $params = [];
  if ($q !== '') {
    $where[] = "(at.table_name LIKE :q OR at.action LIKE :q OR at.record_id LIKE :q OR at.old_values LIKE :q OR at.new_values LIKE :q)";
    $params[':q'] = "%$q%";
  }
  if ($fltTable !== '') { $where[] = 'at.table_name = :table_name'; $params[':table_name'] = $fltTable; }
  if ($fltAction !== '') { $where[] = 'at.action = :action'; $params[':action'] = $fltAction; }
  if ($fltUser !== '' && ctype_digit($fltUser)) { $where[] = 'at.performed_by_user_id = :user_id'; $params[':user_id'] = (int)$fltUser; }
  if ($dateFrom !== '') { $where[] = 'DATE(at.created_at) >= :date_from'; $params[':date_from'] = $dateFrom; }
  if ($dateTo !== '') { $where[] = 'DATE(at.created_at) <= :date_to'; $params[':date_to'] = $dateTo; }
  $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
  $sqlCount = "SELECT COUNT(*) FROM audit_trail at $whereSql";
  $stmt = $pdo->prepare($sqlCount);
  foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v);
  }
  $stmt->execute();
  $totalRows = (int)$stmt->fetchColumn();
  $totalPages = max(1, (int)ceil($totalRows / $pageSize));
  if ($page > $totalPages) { $page = $totalPages; $offset = ($page - 1) * $pageSize; }
  $sql = "
    SELECT at.*, u.username, u.email,
           CONCAT(COALESCE(m.first_name,''),' ',COALESCE(m.last_name,'')) AS staff_name
    FROM audit_trail at
    LEFT JOIN users u ON u.id = at.performed_by_user_id
    LEFT JOIN marshal m ON m.user_id = u.id
    $whereSql
    ORDER BY at.created_at DESC
    LIMIT :limit OFFSET :offset
  ";
  $stmt = $pdo->prepare($sql);
  foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v);
  }
  $stmt->bindValue(':limit', $pageSize, PDO::PARAM_INT);
  $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
  $stmt->execute();
  $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
  $tables = $pdo->query("SELECT DISTINCT table_name FROM audit_trail ORDER BY table_name")->fetchAll(PDO::FETCH_COLUMN);
  $actions = $pdo->query("SELECT DISTINCT action FROM audit_trail ORDER BY action")->fetchAll(PDO::FETCH_COLUMN);
  $users = $pdo->query("SELECT DISTINCT u.id AS id, COALESCE(CONCAT(m.first_name,' ',m.last_name), u.username, u.email) AS name
                        FROM audit_trail at
                        LEFT JOIN users u ON u.id = at.performed_by_user_id
                        LEFT JOIN marshal m ON m.user_id = u.id
                        WHERE at.performed_by_user_id IS NOT NULL
                        ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="min-h-screen md:pl-64">
  <div class="md:hidden sticky top-0 z-40 bg-white border-b border-gray-200">
    <div class="h-16 flex items-center px-4">
      <button id="adminSidebarToggle" class="text-primary text-2xl mr-3">
        <i class="fa-solid fa-bars"></i>
      </button>
      <div class="text-primary font-bold">Activity Log</div>
    </div>
  </div>

  <?php include '../../components/admin-sidebar.php'; ?>

  <main class="px-4 md:px-8 py-6">
    <div class="flex items-center gap-3 mb-6">
      <i class="fa-solid fa-list-check text-primary text-2xl"></i>
      <h1 class="text-2xl md:text-3xl font-bold text-primary">Audit Trail</h1>
    </div>
    <form method="get" class="bg-white border border-gray-200 rounded-lg p-4 mb-6 grid grid-cols-1 md:grid-cols-6 gap-3">
      <div class="md:col-span-2">
        <label class="text-sm text-gray">Search</label>
        <input type="text" name="q" value="<?php echo htmlspecialchars($q); ?>" placeholder="Search action, table, record id, values..."
               class="w-full border border-gray-300 rounded px-3 py-2 focus:outline-none focus:ring-1 focus:ring-primary" />
      </div>
      <div>
        <label class="text-sm text-gray">Table</label>
        <select name="table_name" class="w-full border border-gray-300 rounded px-3 py-2">
          <option value="">All</option>
          <?php foreach ($tables as $t): ?>
            <option value="<?php echo htmlspecialchars($t); ?>" <?php echo $fltTable===$t?'selected':''; ?>><?php echo htmlspecialchars($t); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="text-sm text-gray">Action</label>
        <select name="action" class="w-full border border-gray-300 rounded px-3 py-2">
          <option value="">All</option>
          <?php foreach ($actions as $a): ?>
            <option value="<?php echo htmlspecialchars($a); ?>" <?php echo $fltAction===$a?'selected':''; ?>><?php echo htmlspecialchars($a); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="text-sm text-gray">User</label>
        <select name="user_id" class="w-full border border-gray-300 rounded px-3 py-2">
          <option value="">All</option>
          <?php foreach ($users as $u): ?>
            <option value="<?php echo (int)$u['id']; ?>" <?php echo ($fltUser!=='' && (int)$fltUser===(int)$u['id'])?'selected':''; ?>>
              <?php echo htmlspecialchars($u['name']); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="text-sm text-gray">From</label>
        <input type="date" name="date_from" value="<?php echo htmlspecialchars($dateFrom); ?>" class="w-full border border-gray-300 rounded px-3 py-2" />
      </div>
      <div>
        <label class="text-sm text-gray">To</label>
        <input type="date" name="date_to" value="<?php echo htmlspecialchars($dateTo); ?>" class="w-full border border-gray-300 rounded px-3 py-2" />
      </div>
      <div class="md:col-span-6 flex items-center gap-3">
        <button type="submit" class="bg-primary text-white px-4 py-2 rounded hover:opacity-90">
          <i class="fa-solid fa-filter mr-2"></i>Apply Filters
        </button>
        <a href="activity-log.php" class="text-primary px-3 py-2 hover:underline">Reset</a>
        <div class="text-sm text-gray ml-auto">Showing <?php echo $totalRows ? ($offset+1) : 0; ?>–<?php echo min($offset+$pageSize, $totalRows); ?> of <?php echo $totalRows; ?></div>
      </div>
    </form>

    <!-- Results table -->
    <div class="bg-white border border-gray-200 rounded-lg overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead class="bg-gray-50 text-gray-700">
          <tr>
            <th class="px-4 py-3 text-left">Date/Time</th>
            <th class="px-4 py-3 text-left">User</th>
            <th class="px-4 py-3 text-left">Action</th>
            <th class="px-4 py-3 text-left">Table</th>
            <th class="px-4 py-3 text-left">Record ID</th>
            <th class="px-4 py-3 text-left">Changes</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$logs): ?>
            <tr>
              <td colspan="6" class="px-4 py-6 text-center text-gray">No audit events found for the selected filters.</td>
            </tr>
          <?php else: ?>
            <?php foreach ($logs as $i => $row): ?>
              <?php
                $displayUser = $row['staff_name'] ? trim($row['staff_name']) : ($row['username'] ?: ($row['email'] ?: 'System'));
                $old = null; $new = null;
                if (!empty($row['old_values'])) { $old = json_decode($row['old_values'], true); }
                if (!empty($row['new_values'])) { $new = json_decode($row['new_values'], true); }
                $preview = '';
                if (is_array($old) || is_array($new)) {
                  $keys = array_unique(array_merge(array_keys((array)$old), array_keys((array)$new)));
                  $slice = array_slice($keys, 0, 3);
                  $parts = [];
                  foreach ($slice as $k) {
                    $ov = isset($old[$k]) ? $old[$k] : null;
                    $nv = isset($new[$k]) ? $new[$k] : null;
                    if ($ov === $nv) continue;
                    $parts[] = htmlspecialchars($k) . ': ' . htmlspecialchars(var_export($ov, true)) . ' → ' . htmlspecialchars(var_export($nv, true));
                  }
                  $preview = implode('; ', $parts);
                }
                $rowId = 'chg_' . $row['id'];
              ?>
              <tr class="border-t border-gray-100 align-top">
                <td class="px-4 py-3 whitespace-nowrap text-gray-700"><?php echo htmlspecialchars(date('Y-m-d H:i:s', strtotime($row['created_at']))); ?></td>
                <td class="px-4 py-3"><?php echo htmlspecialchars($displayUser); ?></td>
                <td class="px-4 py-3"><span class="inline-flex items-center gap-2"><i class="fa-solid fa-circle-dot text-primary text-xs"></i> <?php echo htmlspecialchars($row['action']); ?></span></td>
                <td class="px-4 py-3"><?php echo htmlspecialchars($row['table_name']); ?></td>
                <td class="px-4 py-3"><?php echo htmlspecialchars($row['record_id']); ?></td>
                <td class="px-4 py-3 w-[480px]">
                  <?php if ($preview): ?>
                    <div class="text-gray-700 mb-2"><?php echo $preview; ?><?php if (strlen($preview) > 0) echo ' ...'; ?></div>
                  <?php else: ?>
                    <div class="text-gray">—</div>
                  <?php endif; ?>
                  <button type="button" class="text-primary text-sm hover:underline" onclick="toggleDetails('<?php echo $rowId; ?>')">
                    View full diff
                  </button>
                  <div id="<?php echo $rowId; ?>" class="hidden mt-2 grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                      <div class="text-xs text-gray mb-1">Old Values</div>
                      <pre class="bg-gray-50 border border-gray-200 rounded p-2 overflow-auto max-h-64 text-xs"><?php echo htmlspecialchars(json_encode($old, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)); ?></pre>
                    </div>
                    <div>
                      <div class="text-xs text-gray mb-1">New Values</div>
                      <pre class="bg-gray-50 border border-gray-200 rounded p-2 overflow-auto max-h-64 text-xs"><?php echo htmlspecialchars(json_encode($new, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)); ?></pre>
                    </div>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
    <?php if ($totalPages > 1): ?>
      <div class="flex items-center justify-between mt-4">
        <div class="text-sm text-gray">Page <?php echo $page; ?> of <?php echo $totalPages; ?></div>
        <div class="flex gap-2">
          <?php
            $baseParams = $_GET; unset($baseParams['page']);
            $buildLink = function($p) use ($baseParams) {
              $params = $baseParams; $params['page'] = $p; return 'activity-log.php?' . http_build_query($params);
            };
          ?>
          <a class="px-3 py-2 border border-gray-300 rounded <?php echo $page<=1?'pointer-events-none opacity-50':''; ?>" href="<?php echo $page>1 ? $buildLink($page-1) : '#'; ?>">Prev</a>
          <a class="px-3 py-2 border border-gray-300 rounded <?php echo $page>=$totalPages?'pointer-events-none opacity-50':''; ?>" href="<?php echo $page<$totalPages ? $buildLink($page+1) : '#'; ?>">Next</a>
        </div>
      </div>
    <?php endif; ?>
  </main>
</div>

<script>
  function toggleDetails(id) {
    const el = document.getElementById(id);
    if (!el) return;
    el.classList.toggle('hidden');
  }
</script>

<?php require_once '../../components/admin-footer.php'; ?>