<?php
/**
 * pages/report_select.php — Report scope selection fragment
 * Adapted from project_report_select.php for SPA use.
 */
require_once __DIR__ . '/../functions.php';

$projectId = (int)($_GET['project_id'] ?? $_GET['id'] ?? 0);
$project   = get_project($projectId);

if (!$project) {
    echo '<div class="alert alert-warning m-3">ไม่พบโครงการที่ต้องการ</div>';
    return;
}

$panels  = get_project_panels($projectId);
$pstats  = panel_stats($panels);
$overall = $panels ? $pstats['overall'] : (int)$project['progress'];
$types   = array_values(array_unique(array_filter(array_map(fn($x) => $x['panel_type'], $panels))));
$groups  = array_values(array_unique(array_filter(array_map(fn($x) => $x['delivery_group'], $panels))));
sort($types); sort($groups);
?>

<div class="mb-3">
  <a href="#/projects/view/<?= (int)$projectId ?>" class="text-decoration-none small text-muted">
    <i class="bi bi-arrow-left me-1"></i>กลับไปที่โครงการ
  </a>
</div>

<!-- Project summary bar -->
<div class="panel mb-3">
  <div class="panel-body d-flex flex-wrap align-items-center gap-4">
    <div>
      <div class="text-muted small"><?= e($project['project_no']) ?></div>
      <h2 class="h4 mb-0" style="color:var(--primary-color)"><?= e($project['project_name']) ?></h2>
      <div class="text-muted"><?= e($project['customer']) ?></div>
    </div>
    <div class="ms-auto d-flex gap-4 text-center">
      <div><div class="h3 mb-0" style="color:var(--primary-color)"><?= count($panels) ?></div><div class="text-muted small">ตู้ทั้งหมด</div></div>
      <div><div class="h3 mb-0 text-success"><?= (int)$pstats['delivered'] ?></div><div class="text-muted small">ส่งมอบแล้ว</div></div>
      <div><div class="h3 mb-0 text-danger"><?= (int)$pstats['overdue'] ?></div><div class="text-muted small">ล่าช้า</div></div>
      <div style="min-width:140px">
        <div class="h3 mb-0" style="color:var(--info-color)"><?= (int)$overall ?>%</div>
        <div class="progress-mini mt-1"><div class="bar" style="width:<?= (int)$overall ?>%;background:var(--info-color)"></div></div>
        <div class="text-muted small mt-1">Progress รวม</div>
      </div>
    </div>
  </div>
</div>

<?php if (!$panels): ?>
  <div class="alert alert-info">
    โครงการนี้ยังไม่มีตู้ — สร้างรายงานทั้งโครงการได้เลย
    <button class="btn btn-sm btn-primary ms-2"
            onclick="SPA.loadFullscreen('project_overview_image.php?project_id=<?= (int)$projectId ?>&scope=all&fragment=1','Project Overview Report')">
      <i class="bi bi-image"></i> Preview Report
    </button>
  </div>
<?php else: ?>

<form id="reportForm" method="GET" action="project_overview_image.php" data-spa-form="fullscreen">
  <input type="hidden" name="project_id" value="<?= (int)$projectId ?>">
  <input type="hidden" name="panel_ids" id="panelIdsField" value="">

  <!-- Scope -->
  <div class="panel mb-3">
    <div class="panel-head"><i class="bi bi-sliders"></i> ขอบเขตรายงาน (Report Scope)</div>
    <div class="panel-body">
      <div class="scope-grid">
        <label class="scope-opt">
          <input type="radio" name="scope" value="all" checked>
          <span class="so-box">
            <i class="bi bi-collection"></i>
            <span class="so-title">ทั้ง Project</span>
            <span class="so-desc">ใช้ตู้ทั้งหมดของโครงการ</span>
          </span>
        </label>
        <label class="scope-opt">
          <input type="radio" name="scope" value="single">
          <span class="so-box">
            <i class="bi bi-1-square"></i>
            <span class="so-title">เลือกตู้เดียว</span>
            <span class="so-desc">เลือกได้เพียง 1 ตู้</span>
          </span>
        </label>
        <label class="scope-opt">
          <input type="radio" name="scope" value="multiple">
          <span class="so-box">
            <i class="bi bi-check2-square"></i>
            <span class="so-title">เลือกหลายตู้</span>
            <span class="so-desc">ติ๊กเลือกได้หลายตู้</span>
          </span>
        </label>
      </div>
    </div>
  </div>

  <!-- Panel table -->
  <div class="panel">
    <div class="panel-head">
      <i class="bi bi-hdd-stack"></i> เลือกตู้สำหรับรายงาน
      <span class="badge bg-primary ms-2" id="selCount">เลือก 0 ตู้</span>
      <div class="ms-auto d-flex gap-2" id="selectTools">
        <button type="button" class="btn btn-sm btn-light" id="btnSelectAll"><i class="bi bi-check-all"></i> เลือกทั้งหมด</button>
        <button type="button" class="btn btn-sm btn-light" id="btnClear"><i class="bi bi-x-lg"></i> ล้าง</button>
      </div>
    </div>

    <div class="panel-body pb-2">
      <div class="filter-bar">
        <div class="fb-search"><i class="bi bi-search"></i>
          <input type="text" id="fSearch" class="form-control" placeholder="ค้นหา Panel No. / ชื่อตู้">
        </div>
        <select id="fType" class="form-select">
          <option value="">ทุกประเภท</option>
          <?php foreach ($types as $t): ?><option value="<?= e($t) ?>"><?= e($t) ?></option><?php endforeach; ?>
        </select>
        <select id="fStatus" class="form-select">
          <option value="">ทุกสถานะ</option>
          <?php foreach (panel_status_labels() as $k => $lb): ?><option value="<?= e($k) ?>"><?= e($lb) ?></option><?php endforeach; ?>
        </select>
        <select id="fGroup" class="form-select">
          <option value="">ทุก Group</option>
          <?php foreach ($groups as $g): ?><option value="<?= e($g) ?>">Group <?= e($g) ?></option><?php endforeach; ?>
        </select>
        <span class="d-flex align-items-center gap-1">
          <small class="text-muted">กำหนดส่ง</small>
          <input type="date" id="fFrom" class="form-control" style="width:auto">
          <span class="text-muted">–</span>
          <input type="date" id="fTo" class="form-control" style="width:auto">
        </span>
        <button type="button" class="btn btn-outline-secondary" id="btnResetFilter">ล้างตัวกรอง</button>
      </div>
    </div>

    <div class="panel-body p-0">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" id="panelTable">
          <thead>
            <tr>
              <th style="width:46px"><input type="checkbox" id="chkHead" class="form-check-input"></th>
              <th>Panel No.</th><th>ชื่อตู้</th><th>ประเภท</th><th>Group</th>
              <th>กำหนดส่ง</th><th style="width:150px">Progress</th><th>สถานะ</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($panels as $pn): $es = $pn['eff_status']; ?>
              <tr class="panel-row"
                  data-no="<?= e(mb_strtolower($pn['panel_no'])) ?>"
                  data-name="<?= e(mb_strtolower($pn['panel_name'])) ?>"
                  data-type="<?= e($pn['panel_type']) ?>"
                  data-status="<?= e($es) ?>"
                  data-group="<?= e($pn['delivery_group']) ?>"
                  data-target="<?= e($pn['target_delivery_date']) ?>">
                <td><input type="checkbox" class="form-check-input panel-check" name="panel_ids[]" value="<?= (int)$pn['id'] ?>"></td>
                <td class="fw-600"><?= e($pn['panel_no']) ?></td>
                <td><?= e($pn['panel_name']) ?></td>
                <td class="small"><?= e($pn['panel_type'] ?: '-') ?></td>
                <td><?= $pn['delivery_group'] ? '<span class="grp-badge">'.e($pn['delivery_group']).'</span>' : '-' ?></td>
                <td class="small"><?= e(format_date($pn['target_delivery_date'])) ?></td>
                <td>
                  <div class="progress-mini"><div class="bar" style="width:<?= (int)$pn['progress_percent'] ?>%;background:<?= panel_status_color($es) ?>"></div></div>
                  <span class="small text-muted"><?= (int)$pn['progress_percent'] ?>%</span>
                </td>
                <td><span class="status-pill" style="background:<?= panel_status_color($es) ?>"><?= e(panel_status_label($es)) ?></span></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="panel-body d-flex justify-content-end gap-2">
      <a href="#/projects/view/<?= (int)$projectId ?>" class="btn btn-light">ยกเลิก</a>
      <button type="submit" class="btn btn-success btn-lg">
        <i class="bi bi-eye"></i> Preview Report (Fullscreen)
      </button>
    </div>
  </div>
</form>

<?php endif; ?>

<style>
.scope-grid{ display:grid; grid-template-columns:repeat(3,1fr); gap:14px; }
.scope-opt{ position:relative; }
.scope-opt input{ position:absolute; opacity:0; }
.scope-opt .so-box{ display:flex; flex-direction:column; align-items:center; text-align:center; gap:4px;
  border:2px solid var(--gray-200); border-radius:12px; padding:18px; cursor:pointer; transition:.15s; }
.scope-opt .so-box i{ font-size:28px; color:var(--gray-500); }
.scope-opt .so-title{ font-weight:600; color:var(--gray-900); }
.scope-opt .so-desc{ font-size:12.5px; color:var(--gray-500); }
.scope-opt input:checked + .so-box{ border-color:var(--primary-color); background:var(--primary-soft); box-shadow:0 4px 12px rgba(255,122,0,.18); }
.scope-opt input:checked + .so-box i{ color:var(--primary-color); }
.panel-row.dim{ opacity:.35; }
</style>

<script>
(function () {
  var form = document.getElementById('reportForm');
  if (!form) return;
  var rows    = Array.from(document.querySelectorAll('.panel-row'));
  var checks  = Array.from(document.querySelectorAll('.panel-check'));
  var chkHead = document.getElementById('chkHead');
  var selCount= document.getElementById('selCount');
  var hidden  = document.getElementById('panelIdsField');

  function scope() { return (form.querySelector('input[name="scope"]:checked') || {}).value || 'all'; }
  function visibleChecks() { return checks.filter(function(c){ return c.closest('tr').style.display !== 'none'; }); }
  function updateCount() {
    var n = checks.filter(function(c){ return c.checked; }).length;
    selCount.textContent = 'เลือก ' + n + ' ตู้';
  }

  function applyScope() {
    var s = scope();
    checks.forEach(function(c){
      c.disabled = (s === 'all');
      c.closest('tr').classList.toggle('dim', s === 'all');
      if (s === 'all') c.checked = false;
    });
    chkHead.disabled = (s !== 'multiple');
    document.getElementById('btnSelectAll').disabled = (s !== 'multiple');
    if (s === 'all') chkHead.checked = false;
    updateCount();
  }
  form.querySelectorAll('input[name="scope"]').forEach(function(r){ r.addEventListener('change', applyScope); });

  checks.forEach(function(c){
    c.addEventListener('change', function(){
      if (scope() === 'single' && c.checked) { checks.forEach(function(o){ if (o !== c) o.checked = false; }); }
      updateCount();
    });
  });

  document.getElementById('btnSelectAll').addEventListener('click', function(){
    if (scope() !== 'multiple') return;
    visibleChecks().forEach(function(c){ c.checked = true; }); updateCount();
  });
  document.getElementById('btnClear').addEventListener('click', function(){
    checks.forEach(function(c){ c.checked = false; }); chkHead.checked = false; updateCount();
  });
  chkHead.addEventListener('change', function(){
    if (scope() !== 'multiple') return;
    var v = this.checked; visibleChecks().forEach(function(c){ c.checked = v; }); updateCount();
  });

  /* ── Filters ── */
  function filterRows() {
    var q    = (document.getElementById('fSearch').value || '').toLowerCase();
    var typ  = (document.getElementById('fType').value || '').toLowerCase();
    var sta  = (document.getElementById('fStatus').value || '');
    var grp  = (document.getElementById('fGroup').value || '');
    var from = document.getElementById('fFrom').value;
    var to   = document.getElementById('fTo').value;
    rows.forEach(function(r){
      var ok = (!q || r.dataset.no.includes(q) || r.dataset.name.includes(q))
            && (!typ || r.dataset.type.toLowerCase() === typ)
            && (!sta || r.dataset.status === sta)
            && (!grp || r.dataset.group === grp)
            && (!from || r.dataset.target >= from)
            && (!to   || r.dataset.target <= to);
      r.style.display = ok ? '' : 'none';
    });
  }
  ['fSearch','fType','fStatus','fGroup','fFrom','fTo'].forEach(function(id){
    var el = document.getElementById(id);
    if (el) el.addEventListener('input', filterRows);
  });
  document.getElementById('btnResetFilter').addEventListener('click', function(){
    ['fSearch','fType','fStatus','fGroup','fFrom','fTo'].forEach(function(id){
      var el = document.getElementById(id);
      if (el) el.value = '';
    });
    filterRows();
  });

  /* ── Build panel_ids before submit ── */
  form.addEventListener('submit', function(e){
    var ids = checks.filter(function(c){ return c.checked && !c.disabled; }).map(function(c){ return c.value; });
    hidden.value = ids.join(',');
    // Validate non-all scopes
    if (scope() !== 'all' && ids.length === 0) {
      SPA.toast('กรุณาเลือกตู้อย่างน้อย 1 ตู้', 'warning');
      e.preventDefault();
      e.stopPropagation(); // prevent SPA global handler from firing
      return;
    }
    // Disable individual checkboxes to avoid double-sending
    checks.forEach(function(c){ c.disabled = true; });
  });
})();
</script>
