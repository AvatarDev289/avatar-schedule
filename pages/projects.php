<?php
/**
 * pages/projects.php — Projects list fragment
 */
require_once __DIR__ . '/../functions.php';

$filters = [
    'search'        => trim($_GET['search'] ?? ''),
    'status'        => $_GET['status'] ?? '',
    'department_id' => $_GET['department_id'] ?? '',
];
$projects    = get_projects($filters);
$departments = get_departments();
$labels      = status_labels();
$qs          = http_build_query(array_filter($filters));
?>

<!-- Toolbar -->
<div class="panel mb-3">
  <div class="panel-body">
    <form class="filter-bar" data-spa-filter="/projects">
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
      <select name="department_id" class="form-select">
        <option value="">ทุกแผนก</option>
        <?php foreach ($departments as $d): ?>
          <option value="<?= (int)$d['id'] ?>" <?= (string)$filters['department_id']===(string)$d['id']?'selected':'' ?>>
            <?= e($d['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <button class="btn btn-primary"><i class="bi bi-funnel"></i> กรอง</button>
      <a href="#/projects" class="btn btn-outline-secondary">ล้าง</a>
      <div class="ms-auto d-flex gap-2">
        <a href="#/projects/add" class="btn btn-success"><i class="bi bi-plus-lg"></i> เพิ่มโครงการ</a>
        <a href="export_excel.php?<?= e($qs) ?>" class="btn btn-outline-success" title="ดาวน์โหลด Excel">
          <i class="bi bi-file-earmark-excel"></i> Excel
        </a>
        <a href="#" class="btn btn-outline-dark"
           data-spa-fullscreen="print_report.php?<?= e($qs) ?>&fragment=1"
           data-spa-title="รายงานสรุปโครงการ">
          <i class="bi bi-printer"></i> Print
        </a>
      </div>
    </form>
  </div>
</div>

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
            <th>แผนก</th>
            <th>ผู้รับผิดชอบ</th>
            <th>เริ่ม</th>
            <th>กำหนดส่ง</th>
            <th style="width:140px">คืบหน้า</th>
            <th class="text-end">มูลค่า</th>
            <th>สถานะ</th>
            <th class="text-center">จัดการ</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$projects): ?>
            <tr><td colspan="11" class="text-center text-muted py-5">ไม่พบข้อมูลโครงการ</td></tr>
          <?php else: $i = 1; foreach ($projects as $p): $st = $p['effective_status']; ?>
            <tr>
              <td class="text-muted"><?= $i++ ?></td>
              <td class="fw-600"><?= e($p['project_no']) ?></td>
              <td>
                <a href="#/projects/view/<?= (int)$p['id'] ?>" class="link-title"><?= e($p['project_name']) ?></a>
                <div class="text-muted small"><?= e($p['customer']) ?></div>
              </td>
              <td class="small"><?= e($p['department'] ?? '-') ?></td>
              <td class="small"><?= e($p['responsible'] ?? '-') ?></td>
              <td class="small"><?= e(format_date($p['start_date'])) ?></td>
              <td class="small"><?= e(format_date($p['due_date'])) ?></td>
              <td>
                <div class="progress-mini">
                  <div class="bar" style="width:<?= (int)$p['progress'] ?>%;background:<?= status_color($st) ?>"></div>
                </div>
                <span class="small text-muted"><?= (int)$p['progress'] ?>%</span>
              </td>
              <td class="text-end fw-600"><?= money($p['amount']) ?></td>
              <td><span class="<?= status_badge_class($st) ?>"><?= e(status_label($st)) ?></span></td>
              <td class="text-center text-nowrap">
                <a href="#/projects/view/<?= (int)$p['id'] ?>" class="btn btn-sm btn-light" title="ดู">
                  <i class="bi bi-eye"></i>
                </a>
                <a href="#/projects/edit/<?= (int)$p['id'] ?>" class="btn btn-sm btn-light" title="แก้ไข">
                  <i class="bi bi-pencil"></i>
                </a>
                <button class="btn btn-sm btn-light text-danger" title="ลบ"
                        onclick="SPA.confirmDelete('ยืนยันการลบโครงการ <?= e($p['project_no']) ?>?','api/project_delete.php?id=<?= (int)$p['id'] ?>','/projects')">
                  <i class="bi bi-trash"></i>
                </button>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
