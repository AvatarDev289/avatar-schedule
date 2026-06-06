<?php
/**
 * pages/settings.php — System settings fragment
 */
require_once __DIR__ . '/../functions.php';

$departments = get_departments();
$users       = get_users();

$saved = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action'])) {
    // Settings page handles simple DB lookups; actual mutations handled in api/
    $saved = true;
}
?>

<div class="grid-3">

  <!-- Company Info (display only) -->
  <div class="panel">
    <div class="panel-head"><i class="bi bi-building"></i> ข้อมูลบริษัท</div>
    <div class="panel-body">
      <div class="d-flex align-items-center gap-3 mb-3">
        <img src="assets/img/logo.png" alt="logo" style="height:56px;object-fit:contain"
             onerror="this.style.display='none'">
        <div>
          <div class="fw-700" style="font-size:18px;color:var(--primary-color)">AVATAR ELECTRIC</div>
          <div class="text-muted small">บริษัท อวตาร อิเล็คทริค จำกัด</div>
        </div>
      </div>
      <table class="table table-sm table-borderless mb-0">
        <tr><td class="text-muted small" style="width:110px">ระบบ</td><td class="small">Schedule of Project Dashboard</td></tr>
        <tr><td class="text-muted small">เวอร์ชัน</td><td class="small">v2.0 SPA</td></tr>
        <tr><td class="text-muted small">วันที่</td><td class="small"><?= e(format_date(date('Y-m-d'))) ?></td></tr>
        <tr><td class="text-muted small">Near-due days</td><td class="small"><?= NEAR_DUE_DAYS ?> วัน</td></tr>
      </table>
    </div>
  </div>

  <!-- Departments -->
  <div class="panel">
    <div class="panel-head"><i class="bi bi-diagram-3"></i> แผนก (<?= count($departments) ?> รายการ)</div>
    <div class="panel-body p-0">
      <table class="table table-sm align-middle mb-0">
        <thead><tr><th>#</th><th>ชื่อแผนก</th></tr></thead>
        <tbody>
          <?php if (!$departments): ?>
            <tr><td colspan="2" class="text-center text-muted py-3">ยังไม่มีแผนก</td></tr>
          <?php else: $i=1; foreach ($departments as $d): ?>
            <tr><td class="text-muted small"><?= $i++ ?></td><td><?= e($d['name']) ?></td></tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Users -->
  <div class="panel">
    <div class="panel-head"><i class="bi bi-people"></i> ผู้ใช้งาน (<?= count($users) ?> คน)</div>
    <div class="panel-body p-0">
      <table class="table table-sm align-middle mb-0">
        <thead><tr><th>#</th><th>ชื่อ-นามสกุล</th></tr></thead>
        <tbody>
          <?php if (!$users): ?>
            <tr><td colspan="2" class="text-center text-muted py-3">ยังไม่มีผู้ใช้</td></tr>
          <?php else: $i=1; foreach ($users as $u): ?>
            <tr><td class="text-muted small"><?= $i++ ?></td><td><?= e($u['name']) ?></td></tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

<!-- SPA System Info -->
<div class="panel mt-3">
  <div class="panel-head"><i class="bi bi-info-circle"></i> ข้อมูล SPA System</div>
  <div class="panel-body">
    <div class="row g-3">
      <div class="col-md-4">
        <div class="p-3 rounded" style="background:var(--primary-soft);border:1px solid rgba(255,122,0,.2)">
          <div class="fw-700 mb-1" style="color:var(--primary-color)"><i class="bi bi-lightning-charge me-1"></i>SPA Architecture</div>
          <div class="small text-muted">ระบบ Single Page Application — โหลดเนื้อหาผ่าน AJAX ไม่มี Full Page Reload</div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="p-3 rounded" style="background:#EFF6FF;border:1px solid rgba(59,130,246,.2)">
          <div class="fw-700 mb-1" style="color:var(--info-color)"><i class="bi bi-clock-history me-1"></i>Browser History API</div>
          <div class="small text-muted">รองรับ Back/Forward ของ Browser และ Shareable URL (#/dashboard)</div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="p-3 rounded" style="background:#ECFDF5;border:1px solid rgba(16,185,129,.2)">
          <div class="fw-700 mb-1" style="color:var(--success-color)"><i class="bi bi-shield-check me-1"></i>Security</div>
          <div class="small text-muted">PDO Prepared Statements, CSRF-safe JSON API, Project-scoped Panel validation</div>
        </div>
      </div>
    </div>

    <hr class="my-3">

    <div class="row g-2 small text-muted">
      <div class="col-md-3"><strong>PHP:</strong> <?= PHP_VERSION ?></div>
      <div class="col-md-3"><strong>Bootstrap:</strong> 5.3.3</div>
      <div class="col-md-3"><strong>Chart.js:</strong> 4.4.1</div>
      <div class="col-md-3"><strong>html2canvas:</strong> 1.4.1</div>
    </div>
  </div>
</div>

<!-- Quick actions -->
<div class="panel mt-3">
  <div class="panel-head"><i class="bi bi-gear-wide-connected"></i> การดำเนินการด่วน</div>
  <div class="panel-body">
    <div class="d-flex flex-wrap gap-2">
      <a href="#/projects/add" class="btn btn-success">
        <i class="bi bi-plus-circle me-1"></i> เพิ่มโครงการใหม่
      </a>
      <a href="#/deliveries" class="btn btn-outline-primary">
        <i class="bi bi-truck me-1"></i> ดูการส่งมอบ
      </a>
      <a href="#" class="btn btn-outline-secondary"
         data-spa-fullscreen="print_report.php?fragment=1" data-spa-title="รายงานผู้บริหาร">
        <i class="bi bi-printer me-1"></i> พิมพ์รายงานทั้งหมด
      </a>
      <a href="export_excel.php" class="btn btn-outline-success">
        <i class="bi bi-file-earmark-excel me-1"></i> Export Excel
      </a>
    </div>
  </div>
</div>
