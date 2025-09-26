<?php
  // Manage Violations Page
  // Uses tables: violation_categories, violation_types, violation_penalties
  // Shows penalties per offense (1st-4th) and supports CRUD + PDF export (client-side)

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

  // Handle POST actions: create_violation, update_violation, delete_violation
  try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      ensure_csrf();
      $action = $_POST['action'] ?? '';

      if ($action === 'create_violation') {
        $category_id = (int)($_POST['category_id'] ?? 0);
        $code = trim($_POST['code'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '') ?: null;
        $p1 = trim($_POST['penalty_1'] ?? '');
        $p2 = trim($_POST['penalty_2'] ?? '');
        $p3 = trim($_POST['penalty_3'] ?? '');
        $p4 = trim($_POST['penalty_4'] ?? '');
        if ($category_id <= 0 || $code === '' || $name === '') throw new Exception('Category, Code and Name are required.');

        $pdo->beginTransaction();
        $ins = $pdo->prepare('INSERT INTO violation_types (id, category_id, code, name, description, points, created_at) VALUES (NULL, ?, ?, ?, ?, 0, CURRENT_TIMESTAMP())');
        $ins->execute([$category_id, $code, $name, $description]);
        $vtid = (int)$pdo->lastInsertId();

        $pin = $pdo->prepare('INSERT INTO violation_penalties (id, violation_type_id, offense_number, penalty_description, suspension_days, community_service_days, is_expulsion, created_at) VALUES (NULL, ?, ?, ?, NULL, NULL, 0, CURRENT_TIMESTAMP())');
        $penalties = [1 => $p1, 2 => $p2, 3 => $p3, 4 => $p4];
        foreach ($penalties as $off => $desc) {
          $pin->execute([$vtid, $off, $desc !== '' ? $desc : '—']);
        }
        $pdo->commit();

        $_SESSION['flash'] = flash('success', 'Violation created successfully.');
        header('Location: ' . basename($_SERVER['PHP_SELF']) . '?tab=' . $category_id);
        exit;
      }

      if ($action === 'update_violation') {
        $id = (int)($_POST['id'] ?? 0);
        $category_id = (int)($_POST['category_id'] ?? 0);
        $code = trim($_POST['code'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '') ?: null;
        $p1 = trim($_POST['penalty_1'] ?? '');
        $p2 = trim($_POST['penalty_2'] ?? '');
        $p3 = trim($_POST['penalty_3'] ?? '');
        $p4 = trim($_POST['penalty_4'] ?? '');
        if ($id <= 0 || $category_id <= 0 || $code === '' || $name === '') throw new Exception('Violation ID, Category, Code and Name are required.');

        $pdo->beginTransaction();
        $upd = $pdo->prepare('UPDATE violation_types SET category_id = ?, code = ?, name = ?, description = ? WHERE id = ?');
        $upd->execute([$category_id, $code, $name, $description, $id]);

        // Upsert penalties 1..4
        for ($off = 1; $off <= 4; $off++) {
          $desc = ${"p$off"} !== '' ? ${"p$off"} : '—';
          // Check existing
          $chk = $pdo->prepare('SELECT id FROM violation_penalties WHERE violation_type_id = ? AND offense_number = ?');
          $chk->execute([$id, $off]);
          $pid = $chk->fetchColumn();
          if ($pid) {
            $u = $pdo->prepare('UPDATE violation_penalties SET penalty_description = ? WHERE id = ?');
            $u->execute([$desc, $pid]);
          } else {
            $i = $pdo->prepare('INSERT INTO violation_penalties (id, violation_type_id, offense_number, penalty_description, suspension_days, community_service_days, is_expulsion, created_at) VALUES (NULL, ?, ?, ?, NULL, NULL, 0, CURRENT_TIMESTAMP())');
            $i->execute([$id, $off, $desc]);
          }
        }
        $pdo->commit();

        $_SESSION['flash'] = flash('success', 'Violation updated successfully.');
        header('Location: ' . basename($_SERVER['PHP_SELF']) . '?tab=' . $category_id);
        exit;
      }

      if ($action === 'delete_violation') {
        $id = (int)($_POST['id'] ?? 0);
        $tab = (int)($_POST['tab'] ?? 0);
        if ($id <= 0) throw new Exception('Invalid violation.');
        // Check references in cases
        $cnt = $pdo->prepare('SELECT COUNT(*) FROM cases WHERE violation_type_id = ?');
        $cnt->execute([$id]);
        if ((int)$cnt->fetchColumn() > 0) {
          throw new Exception('Cannot delete: Violation is referenced by existing cases.');
        }
        $pdo->beginTransaction();
        $pdo->prepare('DELETE FROM violation_penalties WHERE violation_type_id = ?')->execute([$id]);
        $pdo->prepare('DELETE FROM violation_types WHERE id = ?')->execute([$id]);
        $pdo->commit();
        $_SESSION['flash'] = flash('success', 'Violation deleted.');
        header('Location: ' . basename($_SERVER['PHP_SELF']) . ($tab ? ('?tab=' . $tab) : ''));
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

  // Current tab (category)
  $currentTab = (int)($_GET['tab'] ?? 0);

  // Fetch categories
  $categories = $pdo->query('SELECT id, name, description FROM violation_categories ORDER BY id ASC')->fetchAll(PDO::FETCH_ASSOC);
  if ($currentTab === 0 && $categories) { $currentTab = (int)$categories[0]['id']; }

  // Fetch violations grouped by category with penalties
  $violationsByCat = [];
  $stmt = $pdo->query('SELECT vt.id, vt.category_id, vt.code, vt.name, vt.description,
                              MAX(CASE WHEN vp.offense_number = 1 THEN vp.penalty_description END) AS p1,
                              MAX(CASE WHEN vp.offense_number = 2 THEN vp.penalty_description END) AS p2,
                              MAX(CASE WHEN vp.offense_number = 3 THEN vp.penalty_description END) AS p3,
                              MAX(CASE WHEN vp.offense_number = 4 THEN vp.penalty_description END) AS p4
                       FROM violation_types vt
                       LEFT JOIN violation_penalties vp ON vp.violation_type_id = vt.id
                       GROUP BY vt.id
                       ORDER BY vt.category_id ASC, vt.name ASC');
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
  foreach ($rows as $r) { $violationsByCat[(int)$r['category_id']][] = $r; }
?>

<?php $pageTitle = 'Manage Violations - SDMS'; include_once __DIR__ . '/../../components/admin-head.php'; ?>

<div class="md:ml-64">
  <?php include_once __DIR__ . '/../../components/admin-sidebar.php'; ?>

  <main class="p-6">
    <div class="max-w-7xl mx-auto">
      <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-semibold text-dark">Manage Violations</h1>
        <div class="flex gap-2">
          <button id="btnOpenCreateViolation" class="px-4 py-2 bg-primary text-white rounded hover:opacity-90"><i class="fa fa-plus mr-2"></i>Add Violation</button>
          <button id="btnExportPdf" class="px-4 py-2 bg-secondary text-black rounded hover:opacity-90"><i class="fa fa-file-pdf mr-2"></i>Export PDF (Current Tab)</button>
        </div>
      </div>

      <?php if ($flash['type']): ?>
        <div class="hidden" id="flashData"
             data-type="<?php echo $flash['type']==='success'?'success':'error'; ?>"
             data-msg='<?php echo json_encode($flash['msg']); ?>'></div>
      <?php endif; ?>

      <!-- Tabs: Categories -->
      <div class="border-b border-gray-200 mb-4">
        <nav class="-mb-px flex flex-wrap gap-2" aria-label="Tabs">
          <?php foreach ($categories as $cat): $cid=(int)$cat['id']; $active=$cid===$currentTab; ?>
            <a href="?tab=<?php echo $cid; ?>" class="px-4 py-2 border-b-2 <?php echo $active?'border-primary text-primary':'border-transparent text-gray-600 hover:text-primary hover:border-primary'; ?>">
              <?php echo htmlspecialchars($cat['name']); ?>
            </a>
          <?php endforeach; ?>
        </nav>
      </div>

      <!-- Tables per category (only show active for simplicity) -->
      <?php foreach ($categories as $cat): $cid=(int)$cat['id']; if ($cid !== $currentTab) continue; ?>
        <div id="categoryPanel_<?php echo $cid; ?>" class="space-y-3">
          <div class="text-sm text-gray-600">Category: <span class="font-medium"><?php echo htmlspecialchars($cat['name']); ?></span> — <?php echo htmlspecialchars($cat['description'] ?? ''); ?></div>

          <div id="tableWrap_<?php echo $cid; ?>" class="overflow-x-auto bg-white border border-gray-200 rounded-lg">
            <table class="min-w-full divide-y divide-gray-200" id="violationsTable_<?php echo $cid; ?>">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Violation / Offense</th>
                  <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">First Offense</th>
                  <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Second Offense</th>
                  <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Third Offense</th>
                  <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fourth Offense</th>
                  <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-200">
                <?php $items = $violationsByCat[$cid] ?? []; ?>
                <?php if (!$items): ?>
                  <tr><td colspan="6" class="px-4 py-6 text-center text-gray-500">No violations found in this category.</td></tr>
                <?php else: ?>
                  <?php foreach ($items as $v): ?>
                    <tr class="hover:bg-gray-50">
                      <td class="px-4 py-3 text-sm font-medium text-dark">
                        <div class="flex flex-col">
                          <span><?php echo htmlspecialchars($v['name']); ?></span>
                          <span class="text-xs text-gray-500"><?php echo htmlspecialchars($v['code']); ?><?php echo $v['description']?(' — '.htmlspecialchars($v['description'])):''; ?></span>
                        </div>
                      </td>
                      <td class="px-4 py-3 text-sm align-top"><?php echo nl2br(htmlspecialchars($v['p1'] ?? '—')); ?></td>
                      <td class="px-4 py-3 text-sm align-top"><?php echo nl2br(htmlspecialchars($v['p2'] ?? '—')); ?></td>
                      <td class="px-4 py-3 text-sm align-top"><?php echo nl2br(htmlspecialchars($v['p3'] ?? '—')); ?></td>
                      <td class="px-4 py-3 text-sm align-top"><?php echo nl2br(htmlspecialchars($v['p4'] ?? '—')); ?></td>
                      <td class="px-4 py-3 text-sm text-right">
                        <div class="inline-flex gap-2">
                          <button class="text-blue-600 hover:underline" onclick='openEditViolation(<?php echo json_encode([
                            'id' => (int)$v['id'],
                            'category_id' => $cid,
                            'code' => $v['code'],
                            'name' => $v['name'],
                            'description' => $v['description'],
                            'p1' => $v['p1'] ?? '',
                            'p2' => $v['p2'] ?? '',
                            'p3' => $v['p3'] ?? '',
                            'p4' => $v['p4'] ?? ''
                          ], JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT); ?>)'>Edit</button>
                          <form method="post" class="inline confirm-delete">
                            <input type="hidden" name="csrf" value="<?php echo $csrf; ?>" />
                            <input type="hidden" name="action" value="delete_violation" />
                            <input type="hidden" name="id" value="<?php echo (int)$v['id']; ?>" />
                            <input type="hidden" name="tab" value="<?php echo $cid; ?>" />
                            <button class="text-red-600 hover:underline">Delete</button>
                          </form>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </main>
</div>

<!-- Create/Edit Violation Modal -->
<div id="violationModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
  <div class="bg-white w-full max-w-3xl rounded-lg shadow-lg p-6">
    <div class="flex items-center justify-between mb-4">
      <h2 id="violationModalTitle" class="text-lg font-semibold">Add Violation</h2>
      <button onclick="closeViolationModal()" class="text-gray-500 hover:text-gray-700"><i class="fa fa-times"></i></button>
    </div>
    <form method="post" id="violationForm">
      <input type="hidden" name="csrf" value="<?php echo $csrf; ?>" />
      <input type="hidden" name="action" value="create_violation" />
      <input type="hidden" name="id" id="violationId" value="" />

      <div class="space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
          <div>
            <label class="block text-sm font-medium mb-1">Category <span class="text-red-500">*</span></label>
            <select name="category_id" id="category_id" class="w-full border rounded px-3 py-2" required>
              <option value="">Select category</option>
              <?php foreach ($categories as $c): ?>
                <option value="<?php echo (int)$c['id']; ?>" <?php echo $currentTab===(int)$c['id']?'selected':''; ?>><?php echo htmlspecialchars($c['name']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Code <span class="text-red-500">*</span></label>
            <input type="text" name="code" id="code" class="w-full border rounded px-3 py-2" required />
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Name <span class="text-red-500">*</span></label>
            <input type="text" name="name" id="name" class="w-full border rounded px-3 py-2" required />
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium mb-1">Description</label>
          <textarea name="description" id="description" class="w-full border rounded px-3 py-2" rows="2"></textarea>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium mb-1">First Offense</label>
            <textarea name="penalty_1" id="penalty_1" class="w-full border rounded px-3 py-2" rows="3"></textarea>
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Second Offense</label>
            <textarea name="penalty_2" id="penalty_2" class="w-full border rounded px-3 py-2" rows="3"></textarea>
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Third Offense</label>
            <textarea name="penalty_3" id="penalty_3" class="w-full border rounded px-3 py-2" rows="3"></textarea>
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Fourth Offense</label>
            <textarea name="penalty_4" id="penalty_4" class="w-full border rounded px-3 py-2" rows="3"></textarea>
          </div>
        </div>
      </div>

      <div class="mt-6 flex justify-end gap-2">
        <button type="button" onclick="closeViolationModal()" class="px-4 py-2 border rounded">Cancel</button>
        <button class="px-4 py-2 bg-primary text-white rounded">Save</button>
      </div>
    </form>
  </div>
</div>

<script>
  // Buttons and modal refs
  const violationModal = document.getElementById('violationModal');
  const violationForm = document.getElementById('violationForm');
  const violationModalTitle = document.getElementById('violationModalTitle');

  document.getElementById('btnOpenCreateViolation').addEventListener('click', () => openCreateViolation());
  document.getElementById('btnExportPdf').addEventListener('click', () => exportCurrentTabToPdf());

  function openCreateViolation() {
    violationModalTitle.textContent = 'Add Violation';
    violationForm.action.value = 'create_violation';
    violationForm.id.value = '';
    violationForm.code.value = '';
    violationForm.name.value = '';
    violationForm.description.value = '';
    violationForm.penalty_1.value = '';
    violationForm.penalty_2.value = '';
    violationForm.penalty_3.value = '';
    violationForm.penalty_4.value = '';
    show(violationModal);
  }

  function openEditViolation(v) {
    violationModalTitle.textContent = 'Edit Violation';
    violationForm.action.value = 'update_violation';
    violationForm.id.value = v.id;
    violationForm.category_id.value = v.category_id;
    violationForm.code.value = v.code || '';
    violationForm.name.value = v.name || '';
    violationForm.description.value = v.description || '';
    violationForm.penalty_1.value = v.p1 || '';
    violationForm.penalty_2.value = v.p2 || '';
    violationForm.penalty_3.value = v.p3 || '';
    violationForm.penalty_4.value = v.p4 || '';
    show(violationModal);
  }

  function closeViolationModal() { hide(violationModal); }
  function show(el) { el.classList.remove('hidden'); el.classList.add('flex'); }
  function hide(el) { el.classList.add('hidden'); el.classList.remove('flex'); }

  // SweetAlert integrations
  document.addEventListener('DOMContentLoaded', () => {
    // Flash
    const flash = document.getElementById('flashData');
    if (flash && window.Swal) {
      const type = flash.getAttribute('data-type') || 'info';
      const msg = JSON.parse(flash.getAttribute('data-msg') || '""');
      Swal.fire({ icon: type, title: type === 'success' ? 'Success' : 'Error', text: msg });
    }

    // Confirm delete violation
    document.querySelectorAll('form.confirm-delete').forEach((f) => {
      f.addEventListener('submit', (e) => {
        e.preventDefault();
        Swal.fire({
          title: 'Delete violation?',
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

  // Client-side PDF export using jsPDF + html2canvas
  async function exportCurrentTabToPdf() {
    const params = new URLSearchParams(window.location.search);
    const tab = params.get('tab');
    const id = tab ? tab : document.querySelector('[id^="categoryPanel_"]')?.id.split('_')[1];
    if (!id) return;
    const tableWrap = document.getElementById('tableWrap_' + id);
    if (!tableWrap) return;

    // Hide action columns before export
    const actionColumns = tableWrap.querySelectorAll('th:last-child, td:last-child');
    const originalDisplay = [];
    actionColumns.forEach(col => {
      originalDisplay.push(col.style.display);
      col.style.display = 'none';
    });

    // Load libraries if not present
    await ensureScript('https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js', 'html2canvas');
    await ensureScript('https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js', 'jspdf');

    try {
      const { jsPDF } = window.jspdf;
      const canvas = await html2canvas(tableWrap, { 
        scale: 2,
        logging: false,
        useCORS: true
      });

      const pdf = new jsPDF('l', 'pt', 'a4'); // landscape for wide table
      const pageWidth = pdf.internal.pageSize.getWidth();
      const pageHeight = pdf.internal.pageSize.getHeight();

      // Fit image width to page with small margin
      const margin = 20;
      const imgWidth = pageWidth - margin * 2;
      const imgHeight = canvas.height * (imgWidth / canvas.width);

      let y = margin;
      // Title
      pdf.setFontSize(14);
      const title = document.querySelector('nav [href$="?tab=' + id + '"]')?.textContent?.trim() || 'Violations';
      pdf.text('Violations — ' + title, margin, y);
      y += 10;

      // Add image (table) possibly across multiple pages
      let remainingHeight = imgHeight;
      let position = y + 10;
      let srcY = 0;
      const pageContentHeight = pageHeight - position - margin;
      
      while (remainingHeight > 0) {
        const sliceHeight = Math.min(remainingHeight, pageContentHeight);
        const pageCanvas = document.createElement('canvas');
        pageCanvas.width = canvas.width;
        pageCanvas.height = Math.floor(sliceHeight * (canvas.width / imgWidth));
        const ctx = pageCanvas.getContext('2d');
        ctx.drawImage(canvas, 0, srcY, canvas.width, pageCanvas.height, 0, 0, pageCanvas.width, pageCanvas.height);
        const pageImgData = pageCanvas.toDataURL('image/png');
        const pageImgHeight = sliceHeight;
        pdf.addImage(pageImgData, 'PNG', margin, position, imgWidth, pageImgHeight, undefined, 'FAST');
        remainingHeight -= sliceHeight;
        srcY += pageCanvas.height;
        if (remainingHeight > 0) { pdf.addPage('a4', 'l'); position = margin; }
      }

      pdf.save('violations_tab_' + id + '.pdf');
    } catch (error) {
      console.error('Error generating PDF:', error);
      alert('Failed to generate PDF. Please try again.');
    } finally {
      // Restore action columns visibility
      actionColumns.forEach((col, index) => {
        col.style.display = originalDisplay[index];
      });
    }
  }

  function ensureScript(src, key) {
    return new Promise((resolve, reject) => {
      if (key === 'html2canvas' && window.html2canvas) return resolve();
      if (key === 'jspdf' && window.jspdf && window.jspdf.jsPDF) return resolve();
      const s = document.createElement('script'); s.src = src; s.onload = resolve; s.onerror = reject; document.head.appendChild(s);
    });
  }
</script>

<?php include_once __DIR__ . '/../../components/admin-footer.php'; ?>