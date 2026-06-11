<?php
/**
 * _panel_form.php — shared add/edit form for a panel/cabinet.
 * Expects: $pn (values), $project (parent project), $isEdit (bool).
 */
$effS      = compute_panel_status($pn);
$effLabel  = panel_effective_label($pn);
$effColor  = panel_status_color($effS);
$taskCount = !empty($pn['id']) ? count_panel_tasks((int)$pn['id']) : 0;
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
          <label class="form-label">วันเริ่มงาน (Planned Start)</label>
          <input type="date" name="planned_start_date" class="form-control" id="plannedStartDate"
                 value="<?= e($pn['planned_start_date'] ?? '') ?>">
          <small class="text-muted">ใช้คำนวณวันแผนของแต่ละ Task</small>
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
        <!-- ===== Task-derived Status Display ===== -->
        <div class="col-12">
          <div class="card border-0" style="background:#f8fafc;border-radius:10px;padding:14px 20px">
            <div class="d-flex align-items-center gap-3 flex-wrap">
              <div>
                <div class="text-muted small mb-1">สถานะปัจจุบัน (จาก Tasks)</div>
                <span class="status-pill" style="background:<?= $effColor ?>;font-size:13px;padding:5px 14px">
                  <?= e($effLabel) ?>
                </span>
              </div>
              <div>
                <div class="text-muted small mb-1">Progress</div>
                <span class="fw-600"><?= (int)($pn['progress_percent'] ?? 0) ?>%</span>
                <span class="text-muted small ms-1">(AVG จาก <?= $taskCount ?> tasks)</span>
              </div>
              <?php if ($taskCount === 0): ?>
              <div class="ms-auto">
                <span class="badge bg-secondary" style="font-size:10px">ยังไม่มี Tasks — สร้างด้านล่าง</span>
              </div>
              <?php endif; ?>
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

  <!-- ===== Auto Workflow Section ===== -->
  <?php
    $dbTemplates      = get_task_templates(true);   // DB-backed templates (active only)
    $existTaskCount   = !empty($pn['id']) ? count_panel_tasks((int)$pn['id']) : 0;
    $otherPanels      = array_filter(
        get_project_panels((int)$project['id']),
        fn($op) => (int)$op['id'] !== (int)($pn['id'] ?? 0)
    );
    // Build JS step preview data from DB templates (name→items array)
    $dbTplStepsJs = [];
    foreach ($dbTemplates as $dt) {
        $itms = get_task_template_items((int)$dt['id']);
        $dbTplStepsJs[(string)$dt['id']] = [
            'label'      => $dt['template_name'],
            'cabinet'    => $dt['cabinet_type'] ?? '',
            'steps'      => array_column($itms, 'task_name'),
            'durations'  => array_column($itms, 'duration_days'),
            'totalDays'  => array_sum(array_column($itms, 'duration_days')),
        ];
    }
  ?>
  <div class="panel mt-3" id="autoWorkflowPanel">
    <div class="panel-head" style="cursor:pointer" onclick="document.getElementById('autoWorkflowBody').classList.toggle('d-none')">
      <i class="bi bi-diagram-3"></i> สร้างขั้นตอนงานอัตโนมัติ
      <span class="ms-auto text-muted small" id="autoWorkflowHint">
        <?php if ($existTaskCount > 0): ?>
          <span class="badge bg-warning text-dark"><?= $existTaskCount ?> ขั้นตอนมีอยู่แล้ว</span>
        <?php endif; ?>
        <i class="bi bi-chevron-down"></i>
      </span>
    </div>
    <div class="panel-body <?= $existTaskCount > 0 ? '' : 'd-none' ?>" id="autoWorkflowBody">

      <?php if ($existTaskCount > 0 && !empty($isEdit)): ?>
        <!-- Conflict notice on Edit -->
        <div class="alert alert-warning mb-3">
          <i class="bi bi-exclamation-triangle me-1"></i>
          ตู้นี้มีขั้นตอนงานอยู่แล้ว <strong><?= $existTaskCount ?> รายการ</strong>
          หากสร้างใหม่ ให้เลือกวิธีดำเนินการ
        </div>
        <div class="mb-3">
          <label class="form-label fw-600">วิธีดำเนินการ</label>
          <div class="d-flex flex-column gap-2">
            <label class="d-flex align-items-center gap-2">
              <input type="radio" name="conflict_action" value="add_missing" class="form-check-input mt-0" checked>
              <span>เพิ่มเฉพาะขั้นตอนที่ยังไม่มี (ปลอดภัย)</span>
            </label>
            <label class="d-flex align-items-center gap-2">
              <input type="radio" name="conflict_action" value="replace" class="form-check-input mt-0">
              <span class="text-danger">ลบของเดิมแล้วสร้างใหม่ทั้งหมด</span>
            </label>
            <label class="d-flex align-items-center gap-2">
              <input type="radio" name="conflict_action" value="skip" class="form-check-input mt-0">
              <span class="text-muted">ไม่สร้าง (ข้ามขั้นตอนนี้)</span>
            </label>
          </div>
        </div>
      <?php endif; ?>

      <div class="row g-3">
        <div class="col-md-6">
          <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" name="auto_tasks" id="chkAutoTasks" value="1"
                   <?= $existTaskCount === 0 ? 'checked' : '' ?>>
            <label class="form-check-label fw-600" for="chkAutoTasks">
              สร้างขั้นตอนงานอัตโนมัติ
            </label>
          </div>
          <div class="text-muted small mt-1">ระบบจะสร้าง Tasks มาตรฐานให้ทันทีหลัง Save</div>
        </div>

        <div class="col-md-6" id="wfTemplateWrap">
          <label class="form-label">Template
            <a href="task_templates.php" target="_blank" class="ms-1 text-muted small" title="จัดการ Templates">
              <i class="bi bi-box-arrow-up-right"></i>
            </a>
          </label>
          <select name="task_template" id="wfTemplateSel" class="form-select">
            <option value="auto">ตามประเภทตู้ (อัตโนมัติ)</option>
            <?php foreach ($dbTemplates as $dt): ?>
              <option value="db:<?= (int)$dt['id'] ?>">
                <?= e($dt['template_name']) ?>
                <?= $dt['cabinet_type'] ? '(' . e($dt['cabinet_type']) . ')' : '' ?>
              </option>
            <?php endforeach; ?>
            <?php if ($otherPanels): ?>
              <option disabled>──── Copy จากตู้อื่น ────</option>
              <?php foreach ($otherPanels as $op): ?>
                <option value="copy:<?= (int)$op['id'] ?>">
                  Copy จาก <?= e($op['panel_no']) ?> — <?= e(mb_substr($op['panel_name'], 0, 30)) ?>
                  (<?= count_panel_tasks((int)$op['id']) ?> tasks)
                </option>
              <?php endforeach; ?>
            <?php endif; ?>
          </select>
        </div>

        <!-- Preview steps -->
        <div class="col-12" id="wfStepsPreview" style="display:none">
          <label class="form-label small text-muted">ขั้นตอนที่จะสร้าง</label>
          <div id="wfStepsList" class="d-flex flex-wrap gap-1"></div>
        </div>
      </div>

    </div><!-- /panel-body -->
  </div><!-- /autoWorkflowPanel -->

  <div class="d-flex gap-2 mt-3">
    <button class="btn btn-success btn-lg"><i class="bi bi-save"></i> บันทึก</button>
    <a href="project_view.php?id=<?= (int)$project['id'] ?>#panels" class="btn btn-light btn-lg">ยกเลิก</a>
  </div>
</form>

<script>
(function () {
  var DB_TPLS = <?= json_encode($dbTplStepsJs, JSON_UNESCAPED_UNICODE) ?>;
  var chk      = document.getElementById('chkAutoTasks');
  var wrap     = document.getElementById('wfTemplateWrap');
  var sel      = document.getElementById('wfTemplateSel');
  var preview  = document.getElementById('wfStepsPreview');
  var stepList = document.getElementById('wfStepsList');
  var psdInput = document.getElementById('plannedStartDate');

  function panelTypeVal() {
    var el = document.querySelector('input[name="panel_type"]');
    return el ? el.value.toUpperCase().trim() : '';
  }

  function resolveAutoId() {
    var pt = panelTypeVal();
    var keys = Object.keys(DB_TPLS);
    for (var i = 0; i < keys.length; i++) {
      var cab = (DB_TPLS[keys[i]].cabinet || '').toUpperCase();
      if (cab && pt.indexOf(cab) === 0) return keys[i];
    }
    return keys.length ? keys[0] : null;
  }

  function showSteps() {
    if (!chk || !chk.checked) { preview.style.display='none'; return; }
    var v = sel ? sel.value : 'auto';
    var tpl = null;
    if (v === 'auto') {
      var aid = resolveAutoId();
      if (aid) tpl = DB_TPLS[aid];
    } else if (v.indexOf('db:') === 0) {
      var tid = v.replace('db:', '');
      tpl = DB_TPLS[tid] || null;
    }
    if (!tpl || !tpl.steps || !tpl.steps.length) { preview.style.display='none'; return; }

    var startDate = psdInput ? psdInput.value : '';
    var badges = tpl.steps.map(function(s, i) {
      var dur = tpl.durations ? (tpl.durations[i] || 1) : 1;
      var dateHint = '';
      if (startDate) {
        var d = new Date(startDate);
        // advance by sum of previous durations
        var offset = 0;
        for (var j = 0; j < i; j++) offset += (tpl.durations ? (tpl.durations[j] || 1) : 1);
        d.setDate(d.getDate() + offset);
        var dd = String(d.getDate()).padStart(2,'0');
        var mm = String(d.getMonth()+1).padStart(2,'0');
        dateHint = '<span class="text-muted" style="font-size:9px"> (' + dd + '/' + mm + '/' + d.getFullYear() + ' ' + dur + 'วัน)</span>';
      } else {
        dateHint = '<span class="text-muted" style="font-size:9px"> ' + dur + 'วัน</span>';
      }
      return '<span class="badge bg-light text-dark border mb-1" style="font-size:11px">'
           + (i+1) + '. ' + s + dateHint + '</span>';
    });
    var totalHint = '<div class="text-muted small mt-1">รวม ' + (tpl.totalDays || 0) + ' วัน</div>';
    stepList.innerHTML = badges.join('') + totalHint;
    preview.style.display = '';
  }

  function toggleWrap() {
    if (wrap) wrap.style.display = (chk && chk.checked) ? '' : 'none';
    showSteps();
  }

  if (chk) chk.addEventListener('change', toggleWrap);
  if (sel) sel.addEventListener('change', showSteps);
  if (psdInput) psdInput.addEventListener('change', showSteps);
  var ptInput = document.querySelector('input[name="panel_type"]');
  if (ptInput) ptInput.addEventListener('input', showSteps);

  toggleWrap();
  showSteps();
})();
</script>
