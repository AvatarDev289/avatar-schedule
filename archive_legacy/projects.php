<?php
/**
 * projects.php — All projects list with filters
 */
require_once __DIR__ . '/functions.php';

$filters = [
    'search' => trim($_GET['search'] ?? ''),
    'status' => $_GET['status'] ?? '',
];

$projects = get_projects($filters);
$labels   = status_labels();

$qs = http_build_query(array_filter($filters));

render_header('รายการ Project ทั้งหมด', 'projects.php');
?>

<!-- Toolbar -->
<div class="panel mb-3">
  <div class="panel-body">
    <form class="filter-bar" method="get">
      <div class="fb-search">
        <i class="bi bi-search"></i>
        <input type="text" name="search" class="form-control" placeholder="ค้นหา เลขที่ / ชื่อโครงการ / ลูกค้า"
               value="<?= e($filters['search']) ?>">
      </div>
      <select name="status" class="form-select">
        <option value="">ทุกสถานะ</option>
        <?php foreach ($labels as $k => $lb): ?>
          <option value="<?= e($k) ?>" <?= $filters['status']===$k?'selected':'' ?>><?= e($lb) ?></option>
        <?php endforeach; ?>
      </select>
      <button class="btn btn-primary"><i class="bi bi-funnel"></i> กรอง</button>
      <a href="projects.php" class="btn btn-outline-secondary">ล้าง</a>
      <div class="ms-auto d-flex gap-2">
        <a href="project_add.php" class="btn btn-success"><i class="bi bi-plus-lg"></i> เพิ่มโครงการ</a>
        <a href="export_excel.php?<?= e($qs) ?>" class="btn btn-outline-success"><i class="bi bi-file-earmark-excel"></i> Excel</a>
        <a href="print_report.php?<?= e($qs) ?>" class="btn btn-outline-dark" target="_blank"><i class="bi bi-printer"></i> Print</a>
      </div>
    </form>
  </div>
</div>

<?php if (isset($_GET['msg'])): ?>
  <div class="alert alert-success alert-dismissible fade show">
    <?= e($_GET['msg']) ?>
    <button class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endif; ?>

<div class="panel">
  <div class="panel-head">
    <i class="bi bi-folder2-open"></i> โครงการทั้งหมด
    <span class="badge bg-primary ms-2"><?= count($projects) ?> รายการ</span>
  </div>
  <div class="panel-body p-0">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0 data-table">
        <thead>
          <tr>
            <th>#</th>
            <th>Project No.</th>
            <th>ชื่อโครงการ / ลูกค้า</th>
            <th>วันเริ่ม</th>
            <th>กำหนดส่ง</th>
            <th style="width:140px">คืบหน้า</th>
            <th>สถานะ</th>
            <th class="text-center">จัดการ</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$projects): ?>
            <tr><td colspan="8" class="text-center text-muted py-5">ไม่พบข้อมูลโครงการ</td></tr>
          <?php else: $i = 1; foreach ($projects as $p): $st = $p['effective_status']; ?>
            <tr>
              <td class="text-muted"><?= $i++ ?></td>
              <td class="fw-600"><?= e($p['project_no']) ?></td>
              <td>
                <a href="project_view.php?id=<?= (int)$p['id'] ?>" class="link-title"><?= e($p['project_name']) ?></a>
                <div class="text-muted small"><?= e($p['customer'] ?: '-') ?></div>
              </td>
              <td class="small"><?= e(format_date_dmy($p['start_date'])) ?></td>
              <td class="small"><?= e(format_date_dmy($p['due_date'])) ?></td>
              <td>
                <div class="progress-mini">
                  <div class="bar" style="width:<?= (int)$p['progress'] ?>%;background:<?= status_color($st) ?>"></div>
                </div>
                <span class="small text-muted"><?= (int)$p['progress'] ?>%</span>
              </td>
              <td><span class="<?= status_badge_class($st) ?>"><?= e(status_label($st)) ?></span></td>
              <td class="text-center text-nowrap">
                <a href="project_view.php?id=<?= (int)$p['id'] ?>" class="btn btn-sm btn-light" title="ดู"><i class="bi bi-eye"></i></a>
                <a href="project_edit.php?id=<?= (int)$p['id'] ?>" class="btn btn-sm btn-light" title="แก้ไข"><i class="bi bi-pencil"></i></a>
                <a href="project_delete.php?id=<?= (int)$p['id'] ?>" class="btn btn-sm btn-light text-danger"
                   title="ลบ" onclick="return confirm('ยืนยันการลบโครงการ <?= e($p['project_no']) ?> ?')"><i class="bi bi-trash"></i></a>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php render_footer(); ?>
