<?php
/**
 * pages/panel_form.php — Add / Edit panel form (loads in modal)
 * ?id=N → edit, ?project_id=N → add for project
 */
require_once __DIR__ . '/../functions.php';

$id        = (int)($_GET['id'] ?? 0);
$projectId = (int)($_GET['project_id'] ?? 0);
$isEdit    = (bool)$id;

if ($isEdit) {
    $pn = get_panel($id);
    if (!$pn) {
        echo '<div class="alert alert-warning">ไม่พบตู้ที่ต้องการแก้ไข</div>';
        return;
    }
    $projectId = (int)$pn['project_id'];
} else {
    $pn = ['status' => 'pending', 'project_id' => $projectId];
}

$project = get_project($projectId);
if (!$project) {
    echo '<div class="alert alert-warning">ไม่พบโครงการ (project_id=' . (int)$projectId . ')</div>';
    return;
}

$pmap   = panel_status_progress_map();
$navBack = '/projects/view/' . $projectId;
?>

<form data-spa-form="ajax" data-api-action="api/panel_save.php" data-spa-nav="<?= e($navBack) ?>" novalidate>
  <?php if ($isEdit): ?>
    <input type="hidden" name="id" value="<?= (int)$id ?>">
  <?php else: ?>
    <input type="hidden" name="project_id" value="<?= (int)$projectId ?>">
  <?php endif; ?>

  <div class="panel mb-3">
    <div class="panel-head"><i class="bi bi-hdd"></i> ข้อมูลตู้ — <?= e($project['project_no']) ?></div>
    <div class="panel-body">
      <div class="row g-3">
        <div class="col-md-3">
          <label class="form-label">Panel No. <span class="req">*</span></label>
          <input name="panel_no" class="form-control" required value="<?= e($pn['panel_no'] ?? '') ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label">ชื่อตู้ <span class="req">*</span></label>
          <input name="panel_name" class="form-control" required value="<?= e($pn['panel_name'] ?? '') ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">ประเภท (Type)</label>
          <input name="panel_type" class="form-control" placeholder="MDB / DB / PLC ..." value="<?= e($pn['panel_type'] ?? '') ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">ขนาด (Size)</label>
          <input name="panel_size" class="form-control" placeholder="2000x800x600" value="<?= e($pn['panel_size'] ?? '') ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Delivery Group</label>
          <input name="delivery_group" class="form-control" placeholder="A / B / C" value="<?= e($pn['delivery_group'] ?? '') ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">ผู้รับผิดชอบ</label>
          <input name="responsible" class="form-control" value="<?= e($pn['responsible'] ?? '') ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">ลำดับแสดงผล</label>
          <input type="number" name="sort_order" class="form-control" value="<?= e($pn['sort_order'] ?? '0') ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label">สถานะงาน <span class="req">*</span></label>
          <select name="status" id="pfPanelStatus" class="form-select">
            <?php foreach (panel_workflow_statuses() as $s): ?>
              <option value="<?= e($s) ?>" data-progress="<?= $pmap[$s] ?>"
                      <?= ($pn['status'] ?? 'pending') === $s ? 'selected' : '' ?>>
                <?= e(panel_status_label($s)) ?> (<?= $pmap[$s] ?>%)
              </option>
            <?php endforeach; ?>
          </select>
          <small class="text-muted" id="pfProgressHint">Progress จะตั้งตามสถานะอัตโนมัติ</small>
        </div>
        <div class="col-md-4">
          <label class="form-label">กำหนดส่ง (Target)</label>
          <input type="date" name="target_delivery_date" class="form-control"
                 value="<?= e($pn['target_delivery_date'] ?? '') ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label">วันส่งจริง (Actual)</label>
          <input type="date" name="actual_delivery_date" class="form-control"
                 value="<?= e($pn['actual_delivery_date'] ?? '') ?>">
          <small class="text-muted">หากระบุ จะถือว่าส่งมอบแล้ว</small>
        </div>
        <div class="col-12">
          <label class="form-label">หมายเหตุ</label>
          <textarea name="remark" class="form-control" rows="2"><?= e($pn['remark'] ?? '') ?></textarea>
        </div>
      </div>
    </div>
  </div>

  <div class="d-flex gap-2">
    <button type="submit" class="btn btn-success btn-lg">
      <i class="bi bi-save"></i> <?= $isEdit ? 'บันทึกการแก้ไข' : 'เพิ่มตู้' ?>
    </button>
    <button type="button" class="btn btn-light btn-lg" onclick="SPA.closeModal()">ยกเลิก</button>
  </div>
</form>

<script>
document.getElementById('pfPanelStatus')?.addEventListener('change', function () {
  var p = this.options[this.selectedIndex].dataset.progress;
  document.getElementById('pfProgressHint').textContent = 'Progress จะถูกตั้งเป็น ' + p + '% ตามสถานะนี้';
});
</script>
