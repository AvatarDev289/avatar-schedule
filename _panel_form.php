<?php
/**
 * _panel_form.php — shared add/edit form for a panel/cabinet.
 * Expects: $pn (values), $project (parent project), $isEdit (bool).
 */
$pmap    = panel_status_progress_map();
$curMode = $pn['status_mode'] ?? 'AUTO';
$effS    = compute_panel_status($pn);
$autoS   = (function () use ($pn) {
    // compute auto status regardless of current mode (for the info row)
    $tmp = $pn;
    $tmp['status_mode'] = 'AUTO';
    return compute_panel_status($tmp);
})();
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
          <label class="form-label">กำหนดส่ง (Target)</label>
          <input type="date" name="target_delivery_date" class="form-control" value="<?= e($pn['target_delivery_date'] ?? '') ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label">วันส่งจริง (Actual)</label>
          <input type="date" name="actual_delivery_date" class="form-control" value="<?= e($pn['actual_delivery_date'] ?? '') ?>">
          <small class="text-muted">หากระบุเป็นวันที่ผ่านมาหรือวันนี้ จะถือว่าส่งมอบแล้วอัตโนมัติ</small>
        </div>
        <div class="col-md-4">
          <label class="form-label">ความคืบหน้า (Progress %)</label>
          <input type="number" name="progress_percent" class="form-control"
                 min="0" max="100" step="1"
                 value="<?= (int)($pn['progress_percent'] ?? 0) ?>">
          <small class="text-muted">0 – 100 (กรอกด้วยตนเอง)</small>
        </div>

        <!-- ===== Status Mode ===== -->
        <div class="col-12">
          <div class="card border-0" style="background:#f8fafc;border-radius:10px;padding:16px 20px">
            <div class="d-flex align-items-center gap-3 flex-wrap">

              <!-- Current badge -->
              <div>
                <div class="text-muted small mb-1">สถานะปัจจุบัน</div>
                <span class="status-pill" style="background:<?= panel_status_color($effS) ?>;font-size:13px;padding:5px 14px">
                  <?= e(panel_status_label($effS)) ?>
                </span>
              </div>

              <!-- Auto status info -->
              <div>
                <div class="text-muted small mb-1" id="autoStatusLabel">
                  <?= $curMode === 'MANUAL' ? 'Auto Status (ถ้าปิด Override)' : 'Auto Status (ระบบคำนวณ)' ?>
                </div>
                <span class="status-pill" style="background:<?= panel_status_color($autoS) ?>;font-size:13px;padding:5px 14px;opacity:.75">
                  <?= e(panel_status_label($autoS)) ?>
                  &nbsp;<span style="font-size:11px;opacity:.85">(<?= $pmap[$pn['status'] ?? 'pending'] ?? 0 ?>%)</span>
                </span>
              </div>

              <!-- Mode radio -->
              <div class="ms-auto">
                <div class="text-muted small mb-1">โหมดสถานะ</div>
                <div class="d-flex gap-3">
                  <label class="d-flex align-items-center gap-1 cursor-pointer">
                    <input type="radio" name="status_mode" value="AUTO" class="form-check-input mt-0" id="modeAuto"
                      <?= $curMode === 'AUTO' ? 'checked' : '' ?>>
                    <span class="small fw-600">⚙ Auto</span>
                  </label>
                  <label class="d-flex align-items-center gap-1 cursor-pointer">
                    <input type="radio" name="status_mode" value="MANUAL" class="form-check-input mt-0" id="modeManual"
                      <?= $curMode === 'MANUAL' ? 'checked' : '' ?>>
                    <span class="small fw-600">✏ Manual</span>
                  </label>
                </div>
              </div>
            </div>

            <!-- Manual dropdown (shown only when MANUAL is selected) -->
            <div id="manualStatusWrap" class="mt-3" style="display:<?= $curMode === 'MANUAL' ? 'block' : 'none' ?>">
              <label class="form-label small fw-600">เลือกสถานะ Manual Override</label>
              <select name="manual_status" id="manualStatusSel" class="form-select" style="max-width:360px">
                <option value="">— เลือกสถานะ —</option>
                <?php foreach (manual_status_options() as $k => $lb): ?>
                  <option value="<?= e($k) ?>" <?= ($pn['manual_status'] ?? '') === $k ? 'selected' : '' ?>>
                    <?= e($lb) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
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
(function () {
  var modeAuto     = document.getElementById('modeAuto');
  var modeManual   = document.getElementById('modeManual');
  var wrap         = document.getElementById('manualStatusWrap');
  var sel          = document.getElementById('manualStatusSel');
  var progInput    = document.querySelector('input[name="progress_percent"]');

  // Status → progress mapping (mirrors PHP panel_status_progress_map())
  var PROG_MAP = <?= json_encode(panel_status_progress_map()) ?>;

  function toggleWrap() {
    wrap.style.display = modeManual.checked ? 'block' : 'none';
    if (modeAuto.checked) sel.value = '';
    var lbl = document.getElementById('autoStatusLabel');
    if (lbl) lbl.textContent = modeManual.checked ? 'Auto Status (ถ้าปิด Override)' : 'Auto Status (ระบบคำนวณ)';
  }

  // Auto-fill progress when MANUAL status is selected (user can still override)
  function onManualStatusChange() {
    var s = sel.value;
    if (s && PROG_MAP.hasOwnProperty(s) && progInput) {
      progInput.value = PROG_MAP[s];
    }
  }

  modeAuto.addEventListener('change',   toggleWrap);
  modeManual.addEventListener('change', toggleWrap);
  sel.addEventListener('change', onManualStatusChange);

})();
</script>
