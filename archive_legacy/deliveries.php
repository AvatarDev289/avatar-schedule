<?php
/**
 * deliveries.php — Delivery tracking dashboard (Classic multi-page)
 */
require_once __DIR__ . '/functions.php';

$allPanels = get_panels();
$pcolors   = panel_status_colors();

$today     = strtotime(date('Y-m-d'));
$overdue   = [];
$upcoming  = [];
$delivered = [];

foreach ($allPanels as $pn) {
    $es = $pn['eff_status'];
    if ($es === 'delivered' || !empty($pn['actual_delivery_date'])) {
        $delivered[] = $pn;
    } elseif (!empty($pn['target_delivery_date'])) {
        $ts = strtotime($pn['target_delivery_date']);
        if ($ts < $today) {
            $pn['_days_overdue'] = (int)ceil(($today - $ts) / 86400);
            $overdue[] = $pn;
        } else {
            $pn['_days_left'] = (int)ceil(($ts - $today) / 86400);
            $upcoming[] = $pn;
        }
    }
}

usort($overdue,   fn($a, $b) => $b['_days_overdue'] <=> $a['_days_overdue']);
usort($upcoming,  fn($a, $b) => strtotime($a['target_delivery_date']) <=> strtotime($b['target_delivery_date']));
usort($delivered, fn($a, $b) => strtotime($b['actual_delivery_date'] ?? '1970-01-01') <=> strtotime($a['actual_delivery_date'] ?? '1970-01-01'));

$total = count($allPanels);

render_header('ติดตามการส่งมอบ', 'deliveries.php');
?>

<!-- KPI row -->
<div class="cards-row" style="grid-template-columns:repeat(4,1fr)">
  <div class="stat-card" style="--c:var(--primary-color)">
    <div class="stat-icon"><i class="bi bi-truck"></i></div>
    <div class="stat-body"><div class="stat-value"><?= $total ?></div><div class="stat-label">ตู้ทั้งหมด</div></div>
  </div>
  <div class="stat-card" style="--c:var(--danger-color)">
    <div class="stat-icon"><i class="bi bi-exclamation-octagon"></i></div>
    <div class="stat-body"><div class="stat-value"><?= count($overdue) ?></div><div class="stat-label">ล่าช้า</div></div>
  </div>
  <div class="stat-card" style="--c:var(--warning-color)">
    <div class="stat-icon"><i class="bi bi-clock-history"></i></div>
    <div class="stat-body"><div class="stat-value"><?= count($upcoming) ?></div><div class="stat-label">กำลังจะส่ง</div></div>
  </div>
  <div class="stat-card" style="--c:var(--success-color)">
    <div class="stat-icon"><i class="bi bi-box-seam-fill"></i></div>
    <div class="stat-body"><div class="stat-value"><?= count($delivered) ?></div><div class="stat-label">ส่งมอบแล้ว</div></div>
  </div>
</div>

<!-- Overdue panels -->
<?php if ($overdue): ?>
<div class="panel mb-3">
  <div class="panel-head" style="color:var(--danger-color)">
    <i class="bi bi-exclamation-octagon-fill"></i> ตู้ที่เลยกำหนดส่งมอบ (<?= count($overdue) ?> รายการ)
  </div>
  <div class="panel-body p-0">
    <?= _dlv_table($overdue, $pcolors, 'overdue') ?>
  </div>
</div>
<?php endif; ?>

<!-- Upcoming panels -->
<div class="panel mb-3">
  <div class="panel-head"><i class="bi bi-clock-history"></i> กำหนดส่งมอบที่กำลังจะถึง (<?= count($upcoming) ?> รายการ)</div>
  <div class="panel-body p-0">
    <?php if (!$upcoming): ?>
      <div class="text-center text-muted py-4">ไม่มีตู้ที่รอส่งมอบในขณะนี้</div>
    <?php else: ?>
      <?= _dlv_table($upcoming, $pcolors, 'upcoming') ?>
    <?php endif; ?>
  </div>
</div>

<!-- Delivered panels -->
<div class="panel">
  <div class="panel-head" style="color:var(--success-color)">
    <i class="bi bi-check-circle-fill"></i> ส่งมอบแล้ว — ล่าสุด 30 รายการ
  </div>
  <div class="panel-body p-0">
    <?php if (!$delivered): ?>
      <div class="text-center text-muted py-4">ยังไม่มีการส่งมอบ</div>
    <?php else: ?>
      <?= _dlv_table(array_slice($delivered, 0, 30), $pcolors, 'delivered') ?>
    <?php endif; ?>
  </div>
</div>

<?php
function _dlv_table(array $panels, array $pcolors, string $type): string
{
    ob_start(); ?>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0 data-table">
        <thead>
          <tr>
            <th>Panel No.</th><th>โครงการ</th><th>ชื่อตู้</th><th>Group</th>
            <?php if ($type === 'overdue'): ?>
              <th>กำหนดส่ง</th><th>ล่าช้า</th>
            <?php elseif ($type === 'upcoming'): ?>
              <th>กำหนดส่ง</th><th>คงเหลือ</th>
            <?php else: ?>
              <th>วันส่งจริง</th>
            <?php endif; ?>
            <th style="width:110px">Progress</th><th>สถานะ</th><th>ผู้รับผิดชอบ</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($panels as $pn): $es = $pn['eff_status']; ?>
            <tr onclick="location.href='project_view.php?id=<?= (int)$pn['project_id'] ?>'" style="cursor:pointer">
              <td class="fw-600"><?= e($pn['panel_no']) ?></td>
              <td class="small"><?= e($pn['project_no'] ?? '-') ?></td>
              <td>
                <div class="fw-600"><?= e($pn['panel_name']) ?></div>
                <div class="text-muted small"><?= e($pn['panel_type']) ?></div>
              </td>
              <td><?= $pn['delivery_group'] ? '<span class="grp-badge">' . e($pn['delivery_group']) . '</span>' : '-' ?></td>
              <?php if ($type === 'overdue'): ?>
                <td class="small text-danger"><?= e(format_date_dmy($pn['target_delivery_date'])) ?></td>
                <td><span class="badge bg-danger"><?= (int)$pn['_days_overdue'] ?> วัน</span></td>
              <?php elseif ($type === 'upcoming'): ?>
                <td class="small"><?= e(format_date_dmy($pn['target_delivery_date'])) ?></td>
                <td>
                  <?php $d = (int)$pn['_days_left']; ?>
                  <span class="badge <?= $d <= 7 ? 'bg-warning text-dark' : 'bg-light text-dark' ?>">
                    <?= $d ?> วัน
                  </span>
                </td>
              <?php else: ?>
                <td class="small text-success"><?= e(format_date_dmy($pn['actual_delivery_date'])) ?></td>
              <?php endif; ?>
              <td>
                <div class="progress-mini">
                  <div class="bar" style="width:<?= (int)$pn['progress_percent'] ?>%;background:<?= panel_status_color($es) ?>"></div>
                </div>
                <span class="small text-muted"><?= (int)$pn['progress_percent'] ?>%</span>
              </td>
              <td><span class="status-pill" style="background:<?= panel_status_color($es) ?>"><?= e(panel_status_label($es)) ?></span></td>
              <td class="small"><?= e($pn['responsible'] ?: '-') ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php
    return ob_get_clean();
}

render_footer();
