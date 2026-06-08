<?php
/**
 * settings.php — System settings (Classic multi-page)
 */
require_once __DIR__ . '/functions.php';

$departments = get_departments();
$users       = get_users();

render_header('ตั้งค่าระบบ', 'settings.php');
?>

<div class="grid-3">

  <!-- Company Info -->
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
        <tr><td class="text-muted small">เวอร์ชัน</td><td class="small">v1.0 Classic</td></tr>
        <tr><td class="text-muted small">วันที่</td><td class="small"><?= e(format_date_dmy(date('Y-m-d'))) ?></td></tr>
        <tr><td class="text-muted small">Near-due days</td><td class="small"><?= NEAR_DUE_DAYS ?> วัน</td></tr>
        <tr><td class="text-muted small">PHP</td><td class="small"><?= PHP_VERSION ?></td></tr>
        <tr><td class="text-muted small">Bootstrap</td><td class="small">5.3.3</td></tr>
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
          <?php else: $i = 1; foreach ($departments as $d): ?>
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
          <?php else: $i = 1; foreach ($users as $u): ?>
            <tr><td class="text-muted small"><?= $i++ ?></td><td><?= e($u['name']) ?></td></tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

<!-- Quick actions -->
<div class="panel mt-3">
  <div class="panel-head"><i class="bi bi-gear-wide-connected"></i> การดำเนินการด่วน</div>
  <div class="panel-body">
    <div class="d-flex flex-wrap gap-2">
      <a href="project_add.php" class="btn btn-success">
        <i class="bi bi-plus-circle me-1"></i> เพิ่มโครงการใหม่
      </a>
      <a href="deliveries.php" class="btn btn-outline-primary">
        <i class="bi bi-truck me-1"></i> ดูการส่งมอบ
      </a>
      <a href="print_report.php" target="_blank" class="btn btn-outline-secondary">
        <i class="bi bi-printer me-1"></i> พิมพ์รายงานทั้งหมด
      </a>
      <a href="export_excel.php" class="btn btn-outline-success">
        <i class="bi bi-file-earmark-excel me-1"></i> Export Excel
      </a>
    </div>
  </div>
</div>

<?php render_footer(); ?>
