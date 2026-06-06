<?php
/**
 * project_view.php — Project detail
 */
require_once __DIR__ . '/functions.php';

$id = (int)($_GET['id'] ?? 0);
$p  = get_project($id);

if (!$p) {
    render_header('ไม่พบโครงการ', 'projects.php');
    echo '<div class="alert alert-warning">ไม่พบโครงการที่ต้องการ</div>';
    render_footer();
    exit;
}

$st  = $p['effective_status'];
$od  = overdue_days($p);
$dr  = days_remaining($p);

$panels    = get_project_panels($id);
$pstats    = panel_stats($panels);
$panelProg = project_progress_from_panels($id); // null if no panels
$returnUrl = 'project_view.php?id=' . $id;

render_header('รายละเอียดโครงการ ' . $p['project_no'], 'projects.php');
?>

<?php if (isset($_GET['msg'])): ?>
  <div class="alert alert-success alert-dismissible fade show">
    <?= e($_GET['msg']) ?><button class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endif; ?>

<div class="view-head">
  <div>
    <div class="vh-no"><?= e($p['project_no']) ?></div>
    <h2 class="vh-title"><?= e($p['project_name']) ?></h2>
    <span class="<?= status_badge_class($st) ?>"><?= e(status_label($st)) ?></span>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <a href="project_report_select.php?project_id=<?= (int)$p['id'] ?>" class="btn btn-info text-white"><i class="bi bi-image"></i> Generate Overview Report</a>
    <a href="project_edit.php?id=<?= (int)$p['id'] ?>" class="btn btn-primary"><i class="bi bi-pencil"></i> แก้ไข</a>
    <a href="print_report.php?id=<?= (int)$p['id'] ?>" target="_blank" class="btn btn-outline-dark"><i class="bi bi-printer"></i> พิมพ์</a>
    <a href="project_delete.php?id=<?= (int)$p['id'] ?>" class="btn btn-outline-danger"
       onclick="return confirm('ยืนยันการลบโครงการนี้?')"><i class="bi bi-trash"></i> ลบ</a>
    <a href="projects.php" class="btn btn-light">กลับ</a>
  </div>
</div>

<div class="grid-3">
  <div class="panel span-2">
    <div class="panel-head"><i class="bi bi-clipboard-data"></i> ข้อมูลโครงการ</div>
    <div class="panel-body">
      <div class="detail-grid">
        <div><span class="dl">ลูกค้า</span><span class="dv"><?= e($p['customer'] ?: '-') ?></span></div>
        <div><span class="dl">แผนก</span><span class="dv"><?= e($p['department'] ?: '-') ?></span></div>
        <div><span class="dl">ผู้รับผิดชอบ</span><span class="dv"><?= e($p['responsible'] ?: '-') ?></span></div>
        <div><span class="dl">มูลค่างาน</span><span class="dv fw-600"><?= money2($p['amount']) ?> บาท</span></div>
        <div><span class="dl">วันเริ่มงาน</span><span class="dv"><?= e(format_date($p['start_date'])) ?></span></div>
        <div><span class="dl">กำหนดส่งมอบ</span><span class="dv"><?= e(format_date($p['due_date'])) ?></span></div>
        <div><span class="dl">วันส่งมอบ</span><span class="dv"><?= e(format_date($p['delivery_date'])) ?></span></div>
        <div><span class="dl">วันเสร็จงาน</span><span class="dv"><?= e(format_date($p['completed_date'])) ?></span></div>
      </div>

      <hr>
      <div class="mb-2"><span class="dl">รายละเอียดงาน</span></div>
      <p class="text-body"><?= nl2br(e($p['description'] ?: '-')) ?></p>

      <div class="mt-3 mb-2"><span class="dl">หมายเหตุ</span></div>
      <p class="text-body"><?= nl2br(e($p['remark'] ?: '-')) ?></p>

      <?php if (!empty($p['attachment'])): ?>
        <div class="mt-3">
          <span class="dl">เอกสารแนบ</span><br>
          <a class="btn btn-sm btn-outline-secondary mt-1"
             href="<?= e(UPLOAD_URL . '/' . $p['attachment']) ?>" target="_blank">
            <i class="bi bi-paperclip"></i> <?= e($p['attachment']) ?>
          </a>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="panel">
    <div class="panel-head"><i class="bi bi-graph-up"></i> ความคืบหน้า</div>
    <div class="panel-body text-center">
      <div class="progress-ring" style="--p:<?= (int)$p['progress'] ?>;--col:<?= status_color($st) ?>">
        <span><?= (int)$p['progress'] ?>%</span>
      </div>

      <ul class="mini-stats mt-4">
        <?php if ($st === 'overdue'): ?>
          <li class="danger"><i class="bi bi-exclamation-octagon"></i> ล่าช้า <?= $od ?> วัน</li>
        <?php elseif ($st === 'completed'): ?>
          <li class="ok"><i class="bi bi-check-circle"></i> เสร็จสมบูรณ์</li>
        <?php elseif ($dr !== null): ?>
          <li class="<?= $dr <= NEAR_DUE_DAYS ? 'warn' : '' ?>">
            <i class="bi bi-clock"></i> เหลืออีก <?= $dr ?> วัน
          </li>
        <?php endif; ?>
        <li><i class="bi bi-calendar-plus"></i> สร้างเมื่อ <?= e(format_date($p['created_at'])) ?></li>
        <li><i class="bi bi-calendar-check"></i> แก้ไขล่าสุด <?= e(format_date($p['updated_at'])) ?></li>
      </ul>
    </div>
  </div>
</div>

<!-- ===== Panel / Cabinet tracking ===== -->
<div id="panels" class="panel mt-1">
  <div class="panel-head">
    <i class="bi bi-hdd-stack"></i> ติดตามสถานะรายตู้ (Panel Tracking)
    <span class="badge bg-primary ms-2"><?= count($panels) ?> ตู้</span>
    <?php if ($panelProg !== null): ?>
      <span class="badge bg-success ms-1">Progress รวม <?= $panelProg ?>%</span>
    <?php endif; ?>
    <a href="panel_add.php?project_id=<?= (int)$id ?>" class="ms-auto btn btn-sm btn-success">
      <i class="bi bi-plus-lg"></i> เพิ่มตู้
    </a>
  </div>

  <?php if ($panels): ?>
  <!-- panel mini-summary -->
  <div class="panel-body pb-0">
    <div class="panel-minicards">
      <div class="pmc"><span class="n"><?= $pstats['total'] ?></span><span class="l">ทั้งหมด</span></div>
      <div class="pmc ok"><span class="n"><?= $pstats['delivered'] ?></span><span class="l">ส่งมอบแล้ว</span></div>
      <div class="pmc blue"><span class="n"><?= $pstats['producing'] ?></span><span class="l">กำลังดำเนินการ</span></div>
      <div class="pmc danger"><span class="n"><?= $pstats['overdue'] ?></span><span class="l">ล่าช้า</span></div>
      <div class="pmc slate"><span class="n"><?= $pstats['pending'] ?></span><span class="l">รอเริ่ม</span></div>
    </div>
  </div>
  <?php endif; ?>

  <div class="panel-body p-0">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0 data-table">
        <thead>
          <tr>
            <th>Panel No.</th>
            <th>ชื่อตู้ / ประเภท</th>
            <th>Group</th>
            <th>กำหนดส่ง</th>
            <th>วันส่งจริง</th>
            <th style="width:140px">Progress</th>
            <th style="width:200px">สถานะ (เปลี่ยนได้)</th>
            <th>ผู้รับผิดชอบ</th>
            <th class="text-center">จัดการ</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$panels): ?>
            <tr><td colspan="9" class="text-center text-muted py-4">ยังไม่มีตู้ในโครงการนี้ — กด “เพิ่มตู้” เพื่อเริ่มต้น</td></tr>
          <?php else: foreach ($panels as $pn):
              $es = $pn['eff_status'];
              $ovd = panel_overdue_days($pn); ?>
            <tr>
              <td class="fw-600"><?= e($pn['panel_no']) ?></td>
              <td>
                <div class="fw-600"><?= e($pn['panel_name']) ?></div>
                <div class="text-muted small"><?= e($pn['panel_type']) ?><?= $pn['panel_size'] ? ' · ' . e($pn['panel_size']) : '' ?></div>
              </td>
              <td><?php if ($pn['delivery_group']): ?><span class="grp-badge"><?= e($pn['delivery_group']) ?></span><?php else: ?>-<?php endif; ?></td>
              <td class="small">
                <?= e(format_date($pn['target_delivery_date'])) ?>
                <?php if ($ovd > 0): ?><span class="badge bg-danger ms-1">เลย <?= $ovd ?>ว</span><?php endif; ?>
              </td>
              <td class="small"><?= e(format_date($pn['actual_delivery_date'])) ?></td>
              <td>
                <div class="progress-mini"><div class="bar" style="width:<?= (int)$pn['progress_percent'] ?>%;background:<?= panel_status_color($es) ?>"></div></div>
                <span class="small text-muted"><?= (int)$pn['progress_percent'] ?>%</span>
              </td>
              <td>
                <form method="post" action="panel_update_status.php" class="d-flex gap-1 panel-status-form">
                  <input type="hidden" name="id" value="<?= (int)$pn['id'] ?>">
                  <input type="hidden" name="return" value="<?= e($returnUrl) ?>">
                  <span class="status-pill" style="background:<?= panel_status_color($es) ?>"><?= e(panel_status_label($es)) ?></span>
                  <select name="status" class="form-select form-select-sm status-select" onchange="this.form.submit()">
                    <?php foreach (panel_workflow_statuses() as $ws): ?>
                      <option value="<?= e($ws) ?>" <?= $pn['status']===$ws?'selected':'' ?>><?= e(panel_status_label($ws)) ?></option>
                    <?php endforeach; ?>
                  </select>
                </form>
              </td>
              <td class="small"><?= e($pn['responsible'] ?: '-') ?></td>
              <td class="text-center text-nowrap">
                <a href="panel_edit.php?id=<?= (int)$pn['id'] ?>" class="btn btn-sm btn-light" title="แก้ไข"><i class="bi bi-pencil"></i></a>
                <a href="panel_delete.php?id=<?= (int)$pn['id'] ?>" class="btn btn-sm btn-light text-danger" title="ลบ"
                   onclick="return confirm('ยืนยันการลบตู้ <?= e($pn['panel_no']) ?> ?')"><i class="bi bi-trash"></i></a>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php render_footer(); ?>
