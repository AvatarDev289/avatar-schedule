<?php
/**
 * index.php — Executive Dashboard
 * Avatar Electric — Schedule of Project
 */
require_once __DIR__ . '/functions.php';

$projects = get_projects();
$stats    = dashboard_stats($projects);
$perMonth = projects_per_month($projects);
$labels   = status_labels();
$colors   = status_colors();

// Panel tracking summary (all cabinets across all projects)
$allPanels   = get_panels();
$panelStats  = panel_stats($allPanels);
$panelLabels = panel_status_labels();
$panelColors = panel_status_colors();

// Overdue projects (sorted by most late)
$overdue = array_filter($projects, fn($p) => $p['effective_status'] === 'overdue');
usort($overdue, fn($a, $b) => overdue_days($b) <=> overdue_days($a));

// Upcoming deliveries grouped by month (exclude completed/delivered, must have due date)
$deliveries = array_filter($projects, fn($p) =>
    !in_array($p['effective_status'], ['completed', 'delivered'], true) && !empty($p['due_date']));
usort($deliveries, fn($a, $b) => strtotime($a['due_date']) <=> strtotime($b['due_date']));

// Top cards definition (colors come from the brand token / status palette)
$cards = [
    ['ทั้งหมด',        $stats['total'],                         'bi-folder',              'var(--primary-color)',              'projects.php'],
    ['ส่งมอบแล้ว',     $stats['count']['delivered'],            'bi-box-seam-fill',       status_color('delivered'),          'projects.php?status=delivered'],
    ['ส่งมอบบางส่วน',  $stats['count']['partial_delivery'],     'bi-box-arrow-right',     status_color('partial_delivery'),   'projects.php?status=partial_delivery'],
    ['เสร็จแล้ว',      $stats['count']['completed'],            'bi-check-circle',        status_color('completed'),          'projects.php?status=completed'],
    ['กำลังดำเนินการ', $stats['count']['in_progress'],          'bi-gear',                status_color('in_progress'),        'projects.php?status=in_progress'],
    ['ล่าช้า',         $stats['count']['overdue'],              'bi-exclamation-octagon', status_color('overdue'),            'projects.php?status=overdue'],
    ['ใกล้ครบกำหนด',   $stats['count']['near_due'],             'bi-alarm',               status_color('near_due'),           'projects.php?status=near_due'],
    ['รอเริ่มงาน',     $stats['count']['pending'],              'bi-hourglass-split',     status_color('pending'),            'projects.php?status=pending'],
];

render_header('ภาพรวม Dashboard', 'index.php');
?>

<!-- Summary cards -->
<div class="cards-row">
  <?php foreach ($cards as [$label, $value, $icon, $color, $href]): ?>
    <a class="stat-card" href="<?= e($href) ?>" style="--c:<?= $color ?>">
      <div class="stat-icon"><i class="bi <?= e($icon) ?>"></i></div>
      <div class="stat-body">
        <div class="stat-value"><?= (int)$value ?></div>
        <div class="stat-label"><?= e($label) ?></div>
      </div>
    </a>
  <?php endforeach; ?>
</div>

<div class="grid-3">
  <!-- LEFT: doughnut chart -->
  <div class="panel">
    <div class="panel-head"><i class="bi bi-pie-chart-fill"></i> สัดส่วนสถานะโครงการ</div>
    <div class="panel-body chart-wrap">
      <canvas id="statusChart" height="240"></canvas>
      <div class="chart-center">
        <div class="cc-num"><?= (int)$stats['total'] ?></div>
        <div class="cc-label">โครงการ</div>
      </div>
    </div>
    <ul class="legend">
      <?php foreach ($labels as $k => $lb): ?>
        <li>
          <span class="dot" style="background:<?= $colors[$k] ?>"></span>
          <span class="lg-label"><?= e($lb) ?></span>
          <span class="lg-num"><?= (int)$stats['count'][$k] ?></span>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>

  <!-- CENTER: main schedule table -->
  <div class="panel span-2">
    <div class="panel-head">
      <i class="bi bi-table"></i> ตารางแผนงานโครงการ (Project Schedule)
      <a href="projects.php" class="ms-auto btn btn-sm btn-light">ดูทั้งหมด</a>
    </div>
    <div class="panel-body p-0">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 data-table">
          <thead>
            <tr>
              <th>Project No.</th>
              <th>ชื่อโครงการ / ลูกค้า</th>
              <th>กำหนดส่ง</th>
              <th style="width:160px">ความคืบหน้า</th>
              <th>สถานะ</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach (array_slice($projects, 0, 12) as $p):
                $st = $p['effective_status']; ?>
            <tr onclick="location.href='project_view.php?id=<?= (int)$p['id'] ?>'" style="cursor:pointer">
              <td class="fw-600"><?= e($p['project_no']) ?></td>
              <td>
                <div class="fw-600"><?= e($p['project_name']) ?></div>
                <div class="text-muted small"><?= e($p['customer']) ?></div>
              </td>
              <td><?= e(format_date_dmy($p['due_date'])) ?></td>
              <td>
                <div class="progress-mini">
                  <div class="bar" style="width:<?= (int)$p['progress'] ?>%;background:<?= status_color($st) ?>"></div>
                </div>
                <span class="small text-muted"><?= (int)$p['progress'] ?>%</span>
              </td>
              <td><span class="<?= status_badge_class($st) ?>"><?= e(status_label($st)) ?></span></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<div class="grid-3">
  <!-- Bar chart: projects per month -->
  <div class="panel span-2">
    <div class="panel-head"><i class="bi bi-bar-chart-fill"></i> จำนวนโครงการตามกำหนดส่งมอบรายเดือน</div>
    <div class="panel-body">
      <canvas id="monthChart" height="120"></canvas>
    </div>
  </div>

  <!-- RIGHT: amount by status panel -->
  <div class="panel">
    <div class="panel-head"><i class="bi bi-cash-stack"></i> มูลค่าตามสถานะ (บาท)</div>
    <div class="panel-body">
      <ul class="amount-list">
        <?php foreach ($labels as $k => $lb): ?>
          <li>
            <span class="dot" style="background:<?= $colors[$k] ?>"></span>
            <span class="al-label"><?= e($lb) ?></span>
            <span class="al-count"><?= (int)$stats['count'][$k] ?> งาน</span>
            <span class="al-amount"><?= money($stats['amount'][$k]) ?></span>
          </li>
        <?php endforeach; ?>
        <li class="total">
          <span class="al-label">รวมทั้งสิ้น</span>
          <span class="al-count"><?= (int)$stats['total'] ?> งาน</span>
          <span class="al-amount"><?= money($stats['total_amount']) ?></span>
        </li>
      </ul>
    </div>
  </div>
</div>

<div class="grid-2">
  <!-- Overdue projects -->
  <div class="panel">
    <div class="panel-head text-danger"><i class="bi bi-exclamation-octagon-fill"></i> โครงการที่ล่าช้า (Overdue)</div>
    <div class="panel-body p-0">
      <div class="table-responsive">
        <table class="table table-sm align-middle mb-0 data-table">
          <thead><tr><th>Project</th><th>ลูกค้า</th><th>กำหนดส่ง</th><th class="text-end">ล่าช้า</th></tr></thead>
          <tbody>
          <?php if (!$overdue): ?>
            <tr><td colspan="4" class="text-center text-muted py-4">ไม่มีโครงการล่าช้า 🎉</td></tr>
          <?php else: foreach (array_slice($overdue, 0, 8) as $p): ?>
            <tr onclick="location.href='project_view.php?id=<?= (int)$p['id'] ?>'" style="cursor:pointer">
              <td>
                <div class="fw-600"><?= e($p['project_no']) ?></div>
                <div class="text-muted small text-truncate" style="max-width:220px"><?= e($p['project_name']) ?></div>
              </td>
              <td class="small"><?= e($p['customer']) ?></td>
              <td class="small"><?= e(format_date_dmy($p['due_date'])) ?></td>
              <td class="text-end"><span class="badge bg-danger"><?= overdue_days($p) ?> วัน</span></td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Upcoming deliveries -->
  <div class="panel">
    <div class="panel-head"><i class="bi bi-truck"></i> กำหนดส่งมอบที่กำลังจะถึง</div>
    <div class="panel-body p-0">
      <div class="table-responsive">
        <table class="table table-sm align-middle mb-0 data-table">
          <thead><tr><th>Project</th><th>กำหนดส่ง</th><th class="text-end">คงเหลือ</th><th>สถานะ</th></tr></thead>
          <tbody>
          <?php foreach (array_slice($deliveries, 0, 8) as $p):
              $dr = days_remaining($p); $st = $p['effective_status']; ?>
            <tr onclick="location.href='project_view.php?id=<?= (int)$p['id'] ?>'" style="cursor:pointer">
              <td>
                <div class="fw-600"><?= e($p['project_no']) ?></div>
                <div class="text-muted small text-truncate" style="max-width:200px"><?= e($p['customer']) ?></div>
              </td>
              <td class="small"><?= e(format_date_dmy($p['due_date'])) ?></td>
              <td class="text-end">
                <?php if ($dr < 0): ?>
                  <span class="badge bg-danger">เลย <?= abs($dr) ?> วัน</span>
                <?php else: ?>
                  <span class="badge bg-light text-dark"><?= $dr ?> วัน</span>
                <?php endif; ?>
              </td>
              <td><span class="<?= status_badge_class($st) ?>"><?= e(status_label($st)) ?></span></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- ===== Panel tracking summary ===== -->
<div class="grid-3">
  <!-- panel status pie -->
  <div class="panel">
    <div class="panel-head"><i class="bi bi-hdd-stack"></i> สถานะรายตู้ (Panels)
      <a href="panels.php" class="ms-auto btn btn-sm btn-light">ดูทั้งหมด</a>
    </div>
    <div class="panel-body chart-wrap">
      <canvas id="panelChart" height="240"></canvas>
      <div class="chart-center"><div class="cc-num"><?= (int)$panelStats['total'] ?></div><div class="cc-label">ตู้</div></div>
    </div>
  </div>

  <!-- panel KPI + legend -->
  <div class="panel span-2">
    <div class="panel-head"><i class="bi bi-clipboard-check"></i> ภาพรวมการผลิต/ส่งมอบรายตู้</div>
    <div class="panel-body">
      <div class="panel-minicards" style="grid-template-columns:repeat(5,1fr)">
        <div class="pmc"><span class="n"><?= (int)$panelStats['total'] ?></span><span class="l">ตู้ทั้งหมด</span></div>
        <div class="pmc ok"><span class="n"><?= (int)$panelStats['delivered'] ?></span><span class="l">ส่งมอบแล้ว</span></div>
        <div class="pmc blue"><span class="n"><?= (int)$panelStats['producing'] ?></span><span class="l">กำลังดำเนินการ</span></div>
        <div class="pmc danger"><span class="n"><?= (int)$panelStats['overdue'] ?></span><span class="l">ล่าช้า</span></div>
        <div class="pmc slate"><span class="n"><?= (int)$panelStats['overall'] ?>%</span><span class="l">Progress เฉลี่ย</span></div>
      </div>
      <hr>
      <ul class="pstatus-legend">
        <?php foreach ($panelLabels as $k => $lb): ?>
          <li><span class="dot" style="width:11px;height:11px;border-radius:3px;background:<?= $panelColors[$k] ?>"></span>
            <?= e($lb) ?> <strong class="ms-1"><?= (int)$panelStats['count'][$k] ?></strong></li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>
</div>

<!-- Bottom summary bar -->
<div class="summary-bar">
  <div class="sb-item"><span class="sb-num"><?= (int)$stats['total'] ?></span><span class="sb-label">โครงการทั้งหมด</span></div>
  <div class="sb-item ok"><span class="sb-num"><?= (int)$stats['count']['completed'] ?></span><span class="sb-label">เสร็จสมบูรณ์</span></div>
  <div class="sb-item warn"><span class="sb-num"><?= (int)($stats['count']['in_progress'] + $stats['count']['near_due']) ?></span><span class="sb-label">กำลังดำเนินการ</span></div>
  <div class="sb-item danger"><span class="sb-num"><?= (int)$stats['count']['overdue'] ?></span><span class="sb-label">ล่าช้า</span></div>
  <div class="sb-item money"><span class="sb-num"><?= money($stats['total_amount']) ?></span><span class="sb-label">มูลค่ารวม (บาท)</span></div>
</div>

<script>
window.DASHBOARD = {
  status: {
    labels: <?= json_encode(array_values($labels), JSON_UNESCAPED_UNICODE) ?>,
    data:   <?= json_encode(array_values($stats['count'])) ?>,
    colors: <?= json_encode(array_values($colors)) ?>
  },
  months: {
    labels: ["ม.ค.","ก.พ.","มี.ค.","เม.ย.","พ.ค.","มิ.ย.","ก.ค.","ส.ค.","ก.ย.","ต.ค.","พ.ย.","ธ.ค."],
    data:   <?= json_encode(array_values($perMonth)) ?>
  },
  panels: {
    labels: <?= json_encode(array_values($panelLabels), JSON_UNESCAPED_UNICODE) ?>,
    data:   <?= json_encode(array_values($panelStats['count'])) ?>,
    colors: <?= json_encode(array_values($panelColors)) ?>
  }
};
</script>

<?php render_footer(); ?>
