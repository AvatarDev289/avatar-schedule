<?php
/**
 * _panel_form.php — shared add/edit form for a panel/cabinet.
 * Expects: $pn (values), $project (parent project), $isEdit (bool).
 */
$pmap = panel_status_progress_map();
?>
<form method="post" class="needs-validation" novalidate>
  <div class="panel">
    <div class="panel-head"><i class="bi bi-hdd"></i> ข้อมูลตู้ / Panel</div>
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
          <input name="delivery_group" class="form-control" placeholder="A / B / C / D" value="<?= e($pn['delivery_group'] ?? '') ?>">
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
          <select name="status" id="panelStatus" class="form-select">
            <?php foreach (panel_workflow_statuses() as $s): ?>
              <option value="<?= e($s) ?>" data-progress="<?= $pmap[$s] ?>"
                <?= ($pn['status'] ?? 'pending') === $s ? 'selected' : '' ?>>
                <?= e(panel_status_label($s)) ?> (<?= $pmap[$s] ?>%)
              </option>
            <?php endforeach; ?>
          </select>
          <small class="text-muted">Progress จะตั้งตามสถานะอัตโนมัติ</small>
        </div>
        <div class="col-md-4">
          <label class="form-label">กำหนดส่ง (Target)</label>
          <input type="date" name="target_delivery_date" class="form-control" value="<?= e($pn['target_delivery_date'] ?? '') ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label">วันส่งจริง (Actual)</label>
          <input type="date" name="actual_delivery_date" class="form-control" value="<?= e($pn['actual_delivery_date'] ?? '') ?>">
          <small class="text-muted">หากระบุ จะถือว่าส่งมอบแล้ว</small>
        </div>

        <div class="col-12">
          <label class="form-label">หมายเหตุ</label>
          <textarea name="remark" class="form-control" rows="2"><?= e($pn['remark'] ?? '') ?></textarea>
        </div>
      </div>
    </div>
  </div>

  <div class="d-flex gap-2 mt-3">
    <button class="btn btn-success btn-lg"><i class="bi bi-save"></i> บันทึก</button>
    <a href="project_view.php?id=<?= (int)$project['id'] ?>#panels" class="btn btn-light btn-lg">ยกเลิก</a>
  </div>
</form>

<script>
// keep progress in sync visually (server still enforces the rule)
document.getElementById('panelStatus')?.addEventListener('change', function () {
  var p = this.options[this.selectedIndex].dataset.progress;
  this.nextElementSibling.textContent = 'Progress จะถูกตั้งเป็น ' + p + '% ตามสถานะนี้';
});
</script>
