<?php
/**
 * task_templates.php — Task Template Config
 * CRUD for task_templates + task_template_items
 */
declare(strict_types=1);
require_once __DIR__ . '/functions.php';

/* -----------------------------------------------------------------------
 *  POST handlers
 * --------------------------------------------------------------------- */
$action = $_POST['_action'] ?? '';

// ── Create / Edit Template ─────────────────────────────────────────────
if ($action === 'save_template') {
    $tid  = (int)($_POST['template_id'] ?? 0);
    $name = trim($_POST['template_name'] ?? '');
    $cab  = trim($_POST['cabinet_type'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    $ord  = (int)($_POST['sort_order'] ?? 0);
    $act  = isset($_POST['is_active']) ? 1 : 0;

    if ($name === '') {
        header('Location: task_templates.php?err=' . rawurlencode('กรุณาระบุชื่อ Template'));
        exit;
    }
    if ($tid > 0) {
        db()->prepare(
            "UPDATE task_templates SET template_name=:n, cabinet_type=:c, description=:d,
             is_active=:a, sort_order=:o, updated_at=NOW() WHERE id=:id"
        )->execute([':n'=>$name,':c'=>$cab?:null,':d'=>$desc?:null,':a'=>$act,':o'=>$ord,':id'=>$tid]);
        $msg = 'แก้ไข Template เรียบร้อย';
    } else {
        db()->prepare(
            "INSERT INTO task_templates (template_name,cabinet_type,description,is_active,sort_order)
             VALUES (:n,:c,:d,:a,:o)"
        )->execute([':n'=>$name,':c'=>$cab?:null,':d'=>$desc?:null,':a'=>$act,':o'=>$ord]);
        $tid = (int)db()->lastInsertId();
        $msg = 'เพิ่ม Template เรียบร้อย';
    }
    header('Location: task_templates.php?t=' . $tid . '&msg=' . rawurlencode($msg));
    exit;
}

// ── Delete Template ────────────────────────────────────────────────────
if ($action === 'delete_template') {
    $tid = (int)($_POST['template_id'] ?? 0);
    if ($tid > 0) {
        db()->prepare("DELETE FROM task_templates WHERE id = :id")->execute([':id' => $tid]);
    }
    header('Location: task_templates.php?msg=' . rawurlencode('ลบ Template เรียบร้อย'));
    exit;
}

// ── Save Item (add / edit) ─────────────────────────────────────────────
if ($action === 'save_item') {
    $tid  = (int)($_POST['template_id'] ?? 0);
    $iid  = (int)($_POST['item_id'] ?? 0);
    $name = trim($_POST['task_name'] ?? '');
    $type = trim($_POST['task_type'] ?? '');
    $dur  = max(1, (int)($_POST['duration_days'] ?? 1));
    $ord  = (int)($_POST['sort_order'] ?? 0);
    $resp = trim($_POST['default_responsible'] ?? '');
    $rem  = trim($_POST['remark'] ?? '');

    if ($name === '' || $tid === 0) {
        header('Location: task_templates.php?t=' . $tid . '&err=' . rawurlencode('กรุณาระบุชื่อ Task'));
        exit;
    }

    if ($iid > 0) {
        db()->prepare(
            "UPDATE task_template_items SET task_name=:n, task_type=:tp, duration_days=:d,
             sort_order=:o, default_responsible=:r, remark=:rm, updated_at=NOW()
             WHERE id=:id AND template_id=:tid"
        )->execute([':n'=>$name,':tp'=>$type?:null,':d'=>$dur,':o'=>$ord,
                    ':r'=>$resp?:null,':rm'=>$rem?:null,':id'=>$iid,':tid'=>$tid]);
        $msg = 'แก้ไข Task เรียบร้อย';
    } else {
        // Auto sort_order = max + 10
        $st = db()->prepare("SELECT COALESCE(MAX(sort_order),0)+10 FROM task_template_items WHERE template_id=:tid");
        $st->execute([':tid'=>$tid]);
        $ord = (int)$st->fetchColumn();
        db()->prepare(
            "INSERT INTO task_template_items (template_id,task_name,task_type,duration_days,sort_order,default_responsible,remark)
             VALUES (:tid,:n,:tp,:d,:o,:r,:rm)"
        )->execute([':tid'=>$tid,':n'=>$name,':tp'=>$type?:null,':d'=>$dur,':o'=>$ord,
                    ':r'=>$resp?:null,':rm'=>$rem?:null]);
        $msg = 'เพิ่ม Task เรียบร้อย';
    }
    header('Location: task_templates.php?t=' . $tid . '&msg=' . rawurlencode($msg));
    exit;
}

// ── Delete Item ────────────────────────────────────────────────────────
if ($action === 'delete_item') {
    $tid = (int)($_POST['template_id'] ?? 0);
    $iid = (int)($_POST['item_id'] ?? 0);
    if ($iid > 0 && $tid > 0) {
        db()->prepare("DELETE FROM task_template_items WHERE id=:id AND template_id=:tid")
             ->execute([':id'=>$iid,':tid'=>$tid]);
    }
    header('Location: task_templates.php?t=' . $tid . '&msg=' . rawurlencode('ลบ Task เรียบร้อย'));
    exit;
}

// ── Reorder Items (drag sort) ──────────────────────────────────────────
if ($action === 'reorder_items') {
    $tid  = (int)($_POST['template_id'] ?? 0);
    $ids  = array_map('intval', explode(',', $_POST['item_ids'] ?? ''));
    $upd  = db()->prepare("UPDATE task_template_items SET sort_order=:o WHERE id=:id AND template_id=:tid");
    foreach ($ids as $i => $iid) {
        $upd->execute([':o' => ($i + 1) * 10, ':id' => $iid, ':tid' => $tid]);
    }
    echo json_encode(['ok' => true]);
    exit;
}

/* -----------------------------------------------------------------------
 *  Data for rendering
 * --------------------------------------------------------------------- */
$templates   = get_task_templates(false);
$activeTid   = (int)($_GET['t'] ?? ($templates[0]['id'] ?? 0));
$activeTpl   = $activeTid ? get_task_template($activeTid) : null;
$items       = $activeTid ? get_task_template_items($activeTid) : [];

$editItemId  = (int)($_GET['edit_item'] ?? 0);
$editItem    = null;
if ($editItemId) {
    foreach ($items as $it) { if ((int)$it['id'] === $editItemId) { $editItem = $it; break; } }
}

// Total duration for the active template
$totalDays = array_sum(array_column($items, 'duration_days'));

render_header('ตั้งค่า Task Template', 'settings.php');
?>

<?php if (isset($_GET['msg'])): ?>
  <div class="alert alert-success alert-dismissible fade show"><?= e($_GET['msg']) ?>
    <button class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>
<?php if (isset($_GET['err'])): ?>
  <div class="alert alert-danger alert-dismissible fade show"><?= e($_GET['err']) ?>
    <button class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<div class="row g-3">

  <!-- ================================================================
       Left column: Template list
  ================================================================= -->
  <div class="col-lg-3">
    <div class="panel h-100">
      <div class="panel-head d-flex align-items-center justify-content-between">
        <span><i class="bi bi-collection"></i> Templates</span>
        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#tplModal" data-mode="add">
          <i class="bi bi-plus-lg"></i>
        </button>
      </div>
      <div class="panel-body p-0">
        <div class="list-group list-group-flush">
          <?php foreach ($templates as $t): ?>
            <a href="task_templates.php?t=<?= (int)$t['id'] ?>"
               class="list-group-item list-group-item-action py-2 px-3 <?= (int)$t['id'] === $activeTid ? 'active' : '' ?>">
              <div class="d-flex justify-content-between align-items-start">
                <div>
                  <div class="fw-600 small"><?= e($t['template_name']) ?></div>
                  <?php if ($t['cabinet_type']): ?>
                    <span class="badge bg-secondary me-1"><?= e($t['cabinet_type']) ?></span>
                  <?php endif; ?>
                </div>
                <?php if (!$t['is_active']): ?>
                  <span class="badge bg-light text-muted ms-1">ซ่อน</span>
                <?php endif; ?>
              </div>
            </a>
          <?php endforeach; ?>
          <?php if (!$templates): ?>
            <div class="text-center text-muted py-4 small">ยังไม่มี Template</div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- ================================================================
       Right column: Template detail + items
  ================================================================= -->
  <div class="col-lg-9">
    <?php if ($activeTpl): ?>

    <!-- Template header card -->
    <div class="panel mb-3">
      <div class="panel-head d-flex align-items-center justify-content-between">
        <span>
          <i class="bi bi-gear-wide-connected"></i>
          <?= e($activeTpl['template_name']) ?>
          <?php if ($activeTpl['cabinet_type']): ?>
            <span class="badge bg-info ms-1"><?= e($activeTpl['cabinet_type']) ?></span>
          <?php endif; ?>
          <?php if (!$activeTpl['is_active']): ?>
            <span class="badge bg-secondary ms-1">ซ่อน</span>
          <?php endif; ?>
        </span>
        <div class="d-flex gap-2">
          <button class="btn btn-sm btn-outline-primary"
                  data-bs-toggle="modal" data-bs-target="#tplModal"
                  data-mode="edit"
                  data-id="<?= (int)$activeTpl['id'] ?>"
                  data-name="<?= e($activeTpl['template_name']) ?>"
                  data-cab="<?= e($activeTpl['cabinet_type'] ?? '') ?>"
                  data-desc="<?= e($activeTpl['description'] ?? '') ?>"
                  data-ord="<?= (int)$activeTpl['sort_order'] ?>"
                  data-active="<?= (int)$activeTpl['is_active'] ?>">
            <i class="bi bi-pencil"></i> แก้ไข
          </button>
          <form method="post" onsubmit="return confirm('ลบ Template นี้? จะลบ Tasks ทั้งหมดใน Template ด้วย')">
            <input type="hidden" name="_action" value="delete_template">
            <input type="hidden" name="template_id" value="<?= (int)$activeTpl['id'] ?>">
            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i> ลบ</button>
          </form>
        </div>
      </div>
      <?php if ($activeTpl['description']): ?>
      <div class="panel-body py-2 small text-muted"><?= e($activeTpl['description']) ?></div>
      <?php endif; ?>
    </div>

    <!-- Items table -->
    <div class="panel">
      <div class="panel-head d-flex align-items-center justify-content-between">
        <span>
          <i class="bi bi-list-ol"></i> ขั้นตอนงาน
          <span class="badge bg-primary ms-1"><?= count($items) ?> ขั้นตอน</span>
          <span class="badge bg-secondary ms-1">รวม <?= $totalDays ?> วัน</span>
        </span>
        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#itemModal"
                data-mode="add" data-tid="<?= (int)$activeTid ?>">
          <i class="bi bi-plus-lg"></i> เพิ่ม Task
        </button>
      </div>
      <div class="panel-body p-0">
        <table class="table table-hover align-middle mb-0" id="itemsTable">
          <thead class="table-light">
            <tr>
              <th style="width:40px">#</th>
              <th>ขั้นตอน</th>
              <th style="width:120px">ประเภท</th>
              <th style="width:90px" class="text-center">ระยะเวลา (วัน)</th>
              <th style="width:140px">ผู้รับผิดชอบ</th>
              <th style="width:140px"></th>
            </tr>
          </thead>
          <tbody id="sortableItems">
            <?php if (!$items): ?>
              <tr><td colspan="6" class="text-center text-muted py-4">ยังไม่มี Task — กด "+ เพิ่ม Task"</td></tr>
            <?php else: ?>
              <?php $cumulativeDays = 0; ?>
              <?php foreach ($items as $i => $it): ?>
                <?php $cumulativeDays += (int)$it['duration_days']; ?>
                <tr data-item-id="<?= (int)$it['id'] ?>" class="sortable-row">
                  <td class="text-muted small drag-handle" title="ลากเพื่อเรียงลำดับ">
                    <i class="bi bi-grip-vertical"></i> <?= $i + 1 ?>
                  </td>
                  <td>
                    <div class="fw-600"><?= e($it['task_name']) ?></div>
                    <?php if ($it['remark']): ?>
                      <div class="text-muted small"><?= e($it['remark']) ?></div>
                    <?php endif; ?>
                  </td>
                  <td class="small text-muted"><?= e($it['task_type'] ?: '-') ?></td>
                  <td class="text-center">
                    <span class="badge bg-primary"><?= (int)$it['duration_days'] ?> วัน</span>
                    <div class="text-muted" style="font-size:10px">สะสม <?= $cumulativeDays ?> วัน</div>
                  </td>
                  <td class="small"><?= e($it['default_responsible'] ?: '-') ?></td>
                  <td class="text-end">
                    <button class="btn btn-xs btn-outline-primary"
                            data-bs-toggle="modal" data-bs-target="#itemModal"
                            data-mode="edit"
                            data-iid="<?= (int)$it['id'] ?>"
                            data-tid="<?= (int)$activeTid ?>"
                            data-name="<?= e($it['task_name']) ?>"
                            data-type="<?= e($it['task_type'] ?? '') ?>"
                            data-dur="<?= (int)$it['duration_days'] ?>"
                            data-ord="<?= (int)$it['sort_order'] ?>"
                            data-resp="<?= e($it['default_responsible'] ?? '') ?>"
                            data-rem="<?= e($it['remark'] ?? '') ?>">
                      <i class="bi bi-pencil"></i>
                    </button>
                    <form method="post" class="d-inline" onsubmit="return confirm('ลบ Task นี้?')">
                      <input type="hidden" name="_action" value="delete_item">
                      <input type="hidden" name="template_id" value="<?= (int)$activeTid ?>">
                      <input type="hidden" name="item_id" value="<?= (int)$it['id'] ?>">
                      <button class="btn btn-xs btn-outline-danger"><i class="bi bi-trash"></i></button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
          <?php if ($items): ?>
          <tfoot class="table-light">
            <tr>
              <td colspan="3" class="text-end fw-600 small text-muted">รวมระยะเวลาทั้งหมด</td>
              <td class="text-center"><span class="badge bg-dark"><?= $totalDays ?> วัน</span></td>
              <td colspan="2"></td>
            </tr>
          </tfoot>
          <?php endif; ?>
        </table>
      </div>
    </div>

    <?php else: ?>
      <div class="panel">
        <div class="panel-body text-center text-muted py-5">
          <i class="bi bi-collection" style="font-size:2rem"></i>
          <div class="mt-2">เลือก Template ทางซ้าย หรือกด "+ Template" เพื่อสร้างใหม่</div>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- ================================================================
     Modal: Template save
================================================================= -->
<div class="modal fade" id="tplModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="post" class="modal-content">
      <input type="hidden" name="_action" value="save_template">
      <input type="hidden" name="template_id" id="tplId" value="0">
      <div class="modal-header">
        <h5 class="modal-title" id="tplModalTitle">เพิ่ม Template</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label fw-600">ชื่อ Template <span class="text-danger">*</span></label>
          <input type="text" name="template_name" id="tplName" class="form-control" required placeholder="เช่น MDB Workflow">
        </div>
        <div class="mb-3">
          <label class="form-label fw-600">ประเภทตู้ (cabinet_type)</label>
          <input type="text" name="cabinet_type" id="tplCab" class="form-control" placeholder="เช่น MDB, DB, MCC">
          <div class="form-text">ใช้จับคู่กับชนิดตู้อัตโนมัติ (prefix match)</div>
        </div>
        <div class="mb-3">
          <label class="form-label fw-600">คำอธิบาย</label>
          <textarea name="description" id="tplDesc" class="form-control" rows="2"></textarea>
        </div>
        <div class="row g-2">
          <div class="col">
            <label class="form-label fw-600">ลำดับ</label>
            <input type="number" name="sort_order" id="tplOrd" class="form-control" value="0" min="0">
          </div>
          <div class="col d-flex align-items-end pb-1">
            <div class="form-check">
              <input type="checkbox" name="is_active" id="tplActive" class="form-check-input" value="1" checked>
              <label class="form-check-label" for="tplActive">เปิดใช้งาน</label>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
        <button type="submit" class="btn btn-primary">บันทึก</button>
      </div>
    </form>
  </div>
</div>

<!-- ================================================================
     Modal: Item save
================================================================= -->
<div class="modal fade" id="itemModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="post" class="modal-content">
      <input type="hidden" name="_action" value="save_item">
      <input type="hidden" name="template_id" id="itemTid" value="<?= (int)$activeTid ?>">
      <input type="hidden" name="item_id" id="itemIid" value="0">
      <div class="modal-header">
        <h5 class="modal-title" id="itemModalTitle">เพิ่ม Task</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label fw-600">ชื่อ Task <span class="text-danger">*</span></label>
          <input type="text" name="task_name" id="itemName" class="form-control" required placeholder="เช่น ตัด/พับ/เชื่อม">
        </div>
        <div class="row g-2 mb-3">
          <div class="col">
            <label class="form-label fw-600">ประเภท (task_type)</label>
            <input type="text" name="task_type" id="itemType" class="form-control" placeholder="เช่น fabrication">
          </div>
          <div class="col">
            <label class="form-label fw-600">ระยะเวลา (วัน) <span class="text-danger">*</span></label>
            <input type="number" name="duration_days" id="itemDur" class="form-control" value="1" min="1" required>
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label fw-600">ผู้รับผิดชอบ (default)</label>
          <input type="text" name="default_responsible" id="itemResp" class="form-control" placeholder="เช่น ฝ่ายผลิต">
        </div>
        <div class="mb-3">
          <label class="form-label fw-600">หมายเหตุ</label>
          <textarea name="remark" id="itemRem" class="form-control" rows="2"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
        <button type="submit" class="btn btn-primary">บันทึก</button>
      </div>
    </form>
  </div>
</div>

<style>
.drag-handle { cursor: grab; color: #aaa; }
.sortable-row.dragging { opacity: .4; }
.btn-xs { padding: .15rem .4rem; font-size: .75rem; }
</style>

<script>
/* ── Modal: Template ──────────────────────────────────────────────── */
document.getElementById('tplModal').addEventListener('show.bs.modal', function(e) {
  var btn = e.relatedTarget;
  var mode = btn.dataset.mode;
  document.getElementById('tplModalTitle').textContent = mode === 'edit' ? 'แก้ไข Template' : 'เพิ่ม Template';
  document.getElementById('tplId').value   = mode === 'edit' ? (btn.dataset.id  || '0') : '0';
  document.getElementById('tplName').value = mode === 'edit' ? (btn.dataset.name || '') : '';
  document.getElementById('tplCab').value  = mode === 'edit' ? (btn.dataset.cab  || '') : '';
  document.getElementById('tplDesc').value = mode === 'edit' ? (btn.dataset.desc || '') : '';
  document.getElementById('tplOrd').value  = mode === 'edit' ? (btn.dataset.ord  || '0') : '0';
  document.getElementById('tplActive').checked = mode !== 'edit' || btn.dataset.active === '1';
});

/* ── Modal: Item ──────────────────────────────────────────────────── */
document.getElementById('itemModal').addEventListener('show.bs.modal', function(e) {
  var btn = e.relatedTarget;
  var mode = btn.dataset.mode;
  document.getElementById('itemModalTitle').textContent = mode === 'edit' ? 'แก้ไข Task' : 'เพิ่ม Task';
  document.getElementById('itemTid').value  = btn.dataset.tid  || '<?= (int)$activeTid ?>';
  document.getElementById('itemIid').value  = mode === 'edit' ? (btn.dataset.iid  || '0') : '0';
  document.getElementById('itemName').value = mode === 'edit' ? (btn.dataset.name || '') : '';
  document.getElementById('itemType').value = mode === 'edit' ? (btn.dataset.type || '') : '';
  document.getElementById('itemDur').value  = mode === 'edit' ? (btn.dataset.dur  || '1') : '1';
  document.getElementById('itemResp').value = mode === 'edit' ? (btn.dataset.resp || '') : '';
  document.getElementById('itemRem').value  = mode === 'edit' ? (btn.dataset.rem  || '') : '';
});

/* ── Drag-to-reorder ──────────────────────────────────────────────── */
(function () {
  var tbody = document.getElementById('sortableItems');
  if (!tbody) return;
  var dragging = null;

  tbody.addEventListener('dragstart', function(e) {
    dragging = e.target.closest('tr');
    if (!dragging) return;
    dragging.classList.add('dragging');
    e.dataTransfer.effectAllowed = 'move';
  });
  tbody.addEventListener('dragover', function(e) {
    e.preventDefault();
    var over = e.target.closest('tr');
    if (!over || over === dragging) return;
    var rect = over.getBoundingClientRect();
    if (e.clientY - rect.top < rect.height / 2) {
      tbody.insertBefore(dragging, over);
    } else {
      tbody.insertBefore(dragging, over.nextSibling);
    }
  });
  tbody.addEventListener('dragend', function() {
    if (!dragging) return;
    dragging.classList.remove('dragging');
    dragging = null;
    saveOrder();
  });

  // Make rows draggable
  tbody.querySelectorAll('.sortable-row').forEach(function(row) { row.draggable = true; });

  function saveOrder() {
    var ids = [];
    tbody.querySelectorAll('.sortable-row').forEach(function(r) {
      ids.push(r.dataset.itemId);
    });
    var form = new FormData();
    form.append('_action', 'reorder_items');
    form.append('template_id', '<?= (int)$activeTid ?>');
    form.append('item_ids', ids.join(','));
    fetch('task_templates.php', { method: 'POST', body: form })
      .then(function(r) { return r.json(); })
      .then(function(d) { if (!d.ok) alert('บันทึกลำดับไม่สำเร็จ'); })
      .catch(function() { alert('เกิดข้อผิดพลาด'); });
  }
})();
</script>

<?php render_footer(); ?>
