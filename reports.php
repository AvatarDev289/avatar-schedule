<?php
/**
 * reports.php — Reports hub (Classic multi-page)
 */
require_once __DIR__ . '/functions.php';

$projects = get_projects();
$filters  = status_labels();

render_header('รายงาน', 'reports.php');
?>

<div class="panel mb-4">
  <div class="panel-head"><i class="bi bi-file-earmark-bar-graph"></i> ศูนย์รายงาน (Reports Hub)</div>
  <div class="panel-body">
    <p class="text-muted mb-0">เลือกประเภทรายงานที่ต้องการสร้างหรือพิมพ์</p>
  </div>
</div>

<div class="grid-3 mb-4">

  <!-- Overview Image Report -->
  <div class="panel" style="border-top:3px solid var(--primary-color)">
    <div class="panel-body">
      <div class="d-flex align-items-center gap-3 mb-3">
        <div style="width:44px;height:44px;background:var(--primary-soft);border-radius:10px;display:flex;align-items:center;justify-content:center">
          <i class="bi bi-image" style="font-size:20px;color:var(--primary-color)"></i>
        </div>
        <div>
          <div class="fw-700" style="font-size:15px">Project Overview Report</div>
          <div class="text-muted small">รายงานภาพรวมโครงการ (PNG/PDF)</div>
        </div>
      </div>
      <p class="small text-muted mb-3">สร้างรายงานโครงการแบบ visual overview พร้อม Gantt chart, ตารางตู้, KPI</p>
      <label class="form-label small fw-600">เลือกโครงการ</label>
      <select id="reportProjectSel" class="form-select form-select-sm mb-2">
        <option value="">— เลือกโครงการ —</option>
        <?php foreach ($projects as $p): ?>
          <option value="<?= (int)$p['id'] ?>"><?= e($p['project_no']) ?> — <?= e(mb_substr($p['project_name'], 0, 30)) ?></option>
        <?php endforeach; ?>
      </select>
      <button class="btn btn-primary w-100" onclick="
        var v = document.getElementById('reportProjectSel').value;
        if (!v) { alert('กรุณาเลือกโครงการ'); return; }
        location.href = 'project_report_select.php?project_id=' + v;
      ">
        <i class="bi bi-sliders me-1"></i> เลือกขอบเขตรายงาน
      </button>
    </div>
  </div>

  <!-- Print Report -->
  <div class="panel" style="border-top:3px solid var(--info-color)">
    <div class="panel-body">
      <div class="d-flex align-items-center gap-3 mb-3">
        <div style="width:44px;height:44px;background:#EFF6FF;border-radius:10px;display:flex;align-items:center;justify-content:center">
          <i class="bi bi-printer" style="font-size:20px;color:var(--info-color)"></i>
        </div>
        <div>
          <div class="fw-700" style="font-size:15px">รายงานผู้บริหาร</div>
          <div class="text-muted small">Executive Summary Report</div>
        </div>
      </div>
      <p class="small text-muted mb-3">รายงานสรุปโครงการทั้งหมด หรือรายละเอียดโครงการเดี่ยว พร้อมลายเซ็น</p>
      <label class="form-label small fw-600">เลือกโครงการ (ไม่เลือก = ทุกโครงการ)</label>
      <select id="printProjectSel" class="form-select form-select-sm mb-2">
        <option value="">ทุกโครงการ</option>
        <?php foreach ($projects as $p): ?>
          <option value="<?= (int)$p['id'] ?>"><?= e($p['project_no']) ?> — <?= e(mb_substr($p['project_name'], 0, 30)) ?></option>
        <?php endforeach; ?>
      </select>
      <button class="btn w-100" style="background:var(--info-color);color:#fff" onclick="
        var v = document.getElementById('printProjectSel').value;
        window.open('print_report.php' + (v ? '?id=' + v : ''), '_blank');
      ">
        <i class="bi bi-printer me-1"></i> ดูรายงาน
      </button>
    </div>
  </div>

  <!-- Excel Export -->
  <div class="panel" style="border-top:3px solid var(--success-color)">
    <div class="panel-body">
      <div class="d-flex align-items-center gap-3 mb-3">
        <div style="width:44px;height:44px;background:#ECFDF5;border-radius:10px;display:flex;align-items:center;justify-content:center">
          <i class="bi bi-file-earmark-excel" style="font-size:20px;color:var(--success-color)"></i>
        </div>
        <div>
          <div class="fw-700" style="font-size:15px">Export Excel</div>
          <div class="text-muted small">ดาวน์โหลดข้อมูล .xls</div>
        </div>
      </div>
      <p class="small text-muted mb-3">ส่งออกรายการโครงการทั้งหมดเป็นไฟล์ Excel พร้อมข้อมูลครบถ้วน</p>
      <div class="mb-3">
        <label class="form-label small fw-600">กรองตามสถานะ (ไม่เลือก = ทั้งหมด)</label>
        <select id="excelStatusSel" class="form-select form-select-sm">
          <option value="">ทุกสถานะ</option>
          <?php foreach ($filters as $k => $lb): ?>
            <option value="<?= e($k) ?>"><?= e($lb) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <a id="excelBtn" href="export_excel.php" class="btn btn-success w-100"
         onclick="var s=document.getElementById('excelStatusSel').value; this.href='export_excel.php'+(s?'?status='+s:'')">
        <i class="bi bi-download me-1"></i> ดาวน์โหลด Excel
      </a>
    </div>
  </div>

</div>

<!-- Quick project links -->
<div class="panel">
  <div class="panel-head"><i class="bi bi-list-ul"></i> เข้าถึงรายงานรายโครงการ</div>
  <div class="panel-body p-0">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead>
          <tr>
            <th>Project No.</th><th>ชื่อโครงการ</th>
            <th class="text-center" style="width:140px">คืบหน้า</th>
            <th>สถานะ</th><th class="text-center">รายงาน</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($projects as $p): $st = $p['effective_status']; ?>
            <tr>
              <td class="fw-600"><?= e($p['project_no']) ?></td>
              <td>
                <a href="project_view.php?id=<?= (int)$p['id'] ?>" class="link-title"><?= e($p['project_name']) ?></a>
                <div class="text-muted small"><?= e($p['customer']) ?></div>
              </td>
              <td class="text-center">
                <div class="progress-mini">
                  <div class="bar" style="width:<?= (int)$p['progress'] ?>%;background:<?= status_color($st) ?>"></div>
                </div>
                <span class="small text-muted"><?= (int)$p['progress'] ?>%</span>
              </td>
              <td><span class="<?= status_badge_class($st) ?>"><?= e(status_label($st)) ?></span></td>
              <td class="text-center text-nowrap">
                <a href="project_report_select.php?project_id=<?= (int)$p['id'] ?>" class="btn btn-sm btn-primary" title="Overview Report">
                  <i class="bi bi-image"></i>
                </a>
                <a href="print_report.php?id=<?= (int)$p['id'] ?>" target="_blank"
                   class="btn btn-sm btn-outline-secondary ms-1" title="พิมพ์รายงาน">
                  <i class="bi bi-printer"></i>
                </a>
                <a href="export_excel.php" class="btn btn-sm btn-outline-success ms-1" title="Excel">
                  <i class="bi bi-file-earmark-excel"></i>
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php render_footer(); ?>
