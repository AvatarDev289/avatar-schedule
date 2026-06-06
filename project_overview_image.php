<?php
/**
 * project_overview_image.php
 * Generate a fixed 1536 x 1024 px "Project Overview" report rendered as HTML,
 * exportable to PNG / JPG / PDF via html2canvas.
 *
 * All data is fetched live from MySQL. If a project has more than 16 panels,
 * the report is split into multiple pages automatically.
 */
require_once __DIR__ . '/functions.php';

$fragment = !empty($_GET['fragment']); // SPA fragment mode — skip HTML wrapper

$id = (int)($_GET['id'] ?? $_GET['project_id'] ?? 0);
$p  = get_project($id);

if (!$p) {
    http_response_code(404);
    if (!$fragment) echo '<!doctype html><meta charset="utf-8">';
    echo '<div class="alert alert-warning m-4">ไม่พบโครงการที่ต้องการ (id=' . e($id) . ')</div>';
    exit;
}

/* ---------- Report scope ---------- */
$scope = $_GET['scope'] ?? 'all';
if (!in_array($scope, ['all', 'single', 'multiple'], true)) {
    $scope = 'all';
}

// panel_ids may arrive as array (panel_ids[]) or comma string (panel_ids=1,2,3)
$rawIds = $_GET['panel_ids'] ?? [];
if (is_string($rawIds)) {
    $rawIds = ($rawIds === '') ? [] : explode(',', $rawIds);
}
$reqIds   = is_array($rawIds) ? $rawIds : [];
$validIds = validate_panel_ids($id, $reqIds);   // prepared statement, project-scoped
if ($scope === 'single' && $validIds) {
    $validIds = [$validIds[0]];                   // single => keep exactly one
}

// total panels in the project (for "selected / all" display)
$cs = db()->prepare("SELECT COUNT(*) FROM project_panels WHERE project_id = ?");
$cs->execute([$id]);
$allPanelsCount = (int)$cs->fetchColumn();

if ($scope === 'all') {
    $panels = get_project_panels($id);
} else {
    if (!$validIds) {
        http_response_code(400);
        if (!$fragment) echo '<!doctype html><meta charset="utf-8">';
        echo '<div class="alert alert-warning m-4">'
           . '<h5>ไม่พบตู้ที่เลือก หรือ ตู้ไม่อยู่ในโครงการนี้</h5>'
           . '<p>กรุณาเลือกตู้อย่างน้อย 1 รายการ</p>'
           . '</div>';
        exit;
    }
    $panels = get_project_panels_by_ids($id, $validIds);
}
$selectedCount = count($panels);

$milestones = get_project_milestones($id);

/* ---------- Timeline ----------
 * all      -> master timeline from project tasks
 * scoped   -> timeline derived from the selected panels' delivery dates
 */
if ($scope === 'all') {
    $tasks = get_project_tasks($id);
} else {
    $startBase = !empty($p['start_date']) ? $p['start_date'] : date('Y-m-d');
    $tasks = [];
    foreach ($panels as $pn) {
        $end = $pn['actual_delivery_date'] ?: ($pn['target_delivery_date'] ?: ($p['due_date'] ?: date('Y-m-d')));
        $start = $startBase;
        if (strtotime($start) > strtotime($end)) {
            $start = $end;
        }
        $tasks[] = [
            'task_name'  => $pn['panel_no'] . ' · ' . $pn['panel_name'],
            'start_date' => $start,
            'end_date'   => $end,
            'progress'   => (int)$pn['progress_percent'],
            'status'     => $pn['eff_status'],
            'color'      => panel_status_color($pn['eff_status']),
        ];
    }
}
$timeline = build_timeline($p, $tasks);

// scoped: keep only milestones inside the selected timeline window
if ($scope !== 'all' && $milestones) {
    $ws = $timeline['start']; $we = $timeline['end'];
    $milestones = array_values(array_filter($milestones, function ($m) use ($ws, $we) {
        $ts = strtotime($m['milestone_date']);
        return $ts >= $ws && $ts <= $we;
    }));
}

$pstats = panel_stats($panels);
$st  = $p['effective_status'];
$dr  = days_remaining($p);

// Overall progress from the (scoped) panels; fallback to project progress
$overall = $panels ? $pstats['overall'] : (int)$p['progress'];

/* ---------- Report meta by scope ---------- */
$reportTitle = [
    'all'      => 'PROJECT OVERVIEW REPORT',
    'single'   => 'PANEL OVERVIEW REPORT',
    'multiple' => 'SELECTED PANELS OVERVIEW REPORT',
][$scope];
$scopeThai = [
    'all'      => 'ทั้งโครงการ',
    'single'   => 'เฉพาะตู้ที่เลือก',
    'multiple' => 'หลายตู้ที่เลือก',
][$scope];

// Target completion: project due date (all) or latest target of selected panels
if ($scope === 'all') {
    $targetCompletion = $p['due_date'];
} else {
    $tg = array_filter(array_map(fn($x) => $x['target_delivery_date'], $panels));
    $targetCompletion = $tg ? max($tg) : $p['due_date'];
}

// Export filename base by scope
if ($scope === 'single' && $selectedCount === 1) {
    $fnRaw = 'panel-overview-' . $panels[0]['panel_no'];
} elseif ($scope === 'multiple') {
    $fnRaw = 'selected-panels-overview-' . $p['project_no'];
} else {
    $fnRaw = 'project-overview-' . $p['project_no'];
}
$fileBase = preg_replace('/[^\w\-]+/u', '_', $fnRaw);

// progress ring geometry (SVG — reliable with html2canvas)
$ringR = 70; $ringC = 2 * M_PI * $ringR;
$ringOffset = $ringC * (1 - ($overall / 100));

// ---- Delivery schedule grouped by panel.delivery_group ----
$byGroup = [];
foreach ($panels as $pn) {
    $g = $pn['delivery_group'] !== null && $pn['delivery_group'] !== '' ? $pn['delivery_group'] : '—';
    $byGroup[$g][] = $pn;
}
ksort($byGroup);
$deliveryGroups = [];
foreach ($byGroup as $g => $rows) {
    $cnt = count($rows);
    $done = count(array_filter($rows, fn($x) => $x['eff_status'] === 'delivered'));
    $over = count(array_filter($rows, fn($x) => $x['eff_status'] === 'overdue'));
    $targets = array_filter(array_map(fn($x) => $x['target_delivery_date'], $rows));
    $prog = $cnt ? (int)round(array_sum(array_map(fn($x) => (int)$x['progress_percent'], $rows)) / $cnt) : 0;
    if ($done === $cnt)      $gstatus = 'delivered';
    elseif ($over > 0)       $gstatus = 'overdue';
    else                     $gstatus = 'production';
    $deliveryGroups[] = [
        'group'   => $g,
        'count'   => $cnt,
        'done'    => $done,
        'target'  => $targets ? max($targets) : null,
        'progress'=> $prog,
        'status'  => $gstatus,
    ];
}

// ---- Pagination: 16 panels per page ----
$PER_PAGE = 16;
$chunks   = $panels ? array_chunk($panels, $PER_PAGE) : [[]];
$firstChunk = $chunks[0];
$extraChunks = array_slice($chunks, 1);
$totalPages = count($chunks);

$scolor   = fn($s) => panel_status_color($s);
$slabel   = fn($s) => panel_status_label($s);
?>
<?php if (!$fragment): ?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Project Overview — <?= e($p['project_no']) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&family=Sarabun:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link href="assets/css/project_overview.css" rel="stylesheet">
</head>
<body>
<?php endif; ?>

<!-- ===== Toolbar (excluded from export) ===== -->
<div class="toolbar">
  <div class="tb-left">
    <?php if (!$fragment): ?>
    <a href="project_view.php?id=<?= (int)$id ?>" class="btn btn-light"><i class="bi bi-arrow-left"></i> กลับ</a>
    <?php else: ?>
    <button class="btn btn-light" onclick="document.getElementById('fullscreenModal')?.querySelector('[data-bs-dismiss]')?.click()">
      <i class="bi bi-x-lg"></i> ปิด
    </button>
    <?php endif; ?>
    <span class="tb-title"><i class="bi bi-image"></i> Project Overview — <?= e($p['project_no']) ?>
      <?php if ($totalPages > 1): ?><span class="badge bg-info text-dark"><?= $totalPages ?> หน้า</span><?php endif; ?>
    </span>
  </div>
  <div class="tb-right">
    <button class="btn btn-success"   onclick="downloadPNG()"><i class="bi bi-filetype-png"></i> Download PNG</button>
    <button class="btn btn-primary"   onclick="downloadJPG()"><i class="bi bi-filetype-jpg"></i> Download JPG</button>
    <button class="btn btn-dark"      onclick="printPDF()"><i class="bi bi-printer"></i> Print / PDF</button>
    <button class="btn btn-outline-secondary" onclick="toggleFullscreen()"><i class="bi bi-arrows-fullscreen"></i> Fullscreen</button>
  </div>
</div>

<div class="export-status" id="exportStatus" hidden>
  <div class="spinner-border spinner-border-sm"></div> กำลังสร้างรูปภาพ...
</div>

<div class="canvas-stage" id="canvasStage">

  <!-- ============ PAGE 1 (full overview) ============ -->
  <div class="report-page" id="reportCanvas" data-page="1">

    <header class="rpt-header">
      <div class="rh-brand">
        <img src="assets/img/logo.png" alt="logo" class="rh-logo">
        <div>
          <div class="rh-name">AVATAR ELECTRIC</div>
          <div class="rh-sub">บริษัท อวตาร อิเล็คทริค จำกัด</div>
        </div>
      </div>
      <div class="rh-center">
        <div class="rh-report"><?= e($reportTitle) ?></div>
        <div class="rh-report-th">ขอบเขต: <?= e($scopeThai) ?>
          · ตู้ <?= (int)$selectedCount ?>/<?= (int)$allPanelsCount ?><?= $totalPages>1 ? ' · หน้า 1/'.$totalPages : '' ?></div>
      </div>
      <div class="rh-meta">
        <div class="rh-no"><?= e($p['project_no']) ?></div>
        <div class="rh-date">ข้อมูล ณ <?= e(format_date(date('Y-m-d'))) ?></div>
      </div>
    </header>

    <div class="rpt-titlebar">
      <div class="rt-main">
        <h1 class="rt-name"><?= e($p['project_name']) ?></h1>
        <div class="rt-customer">
          <i class="bi bi-building"></i> <?= e($p['customer'] ?: '-') ?>
          <?php if ($scope === 'single' && $selectedCount === 1): ?>
            &nbsp;|&nbsp; <i class="bi bi-hdd"></i> <strong><?= e($panels[0]['panel_no']) ?></strong> — <?= e($panels[0]['panel_name']) ?>
          <?php endif; ?>
        </div>
      </div>
      <div class="rt-right">
        <div class="rt-target"><span class="rt-target-l">กำหนดแล้วเสร็จ</span>
          <span class="rt-target-v"><?= e(format_date($targetCompletion)) ?></span></div>
        <div class="rt-status" style="--sc:<?= status_color($st) ?>">
          <span class="rt-badge"><?= e(status_label($st)) ?></span>
        </div>
      </div>
    </div>

    <!-- KPI strip -->
    <?php $kpiStyle = fn($c) => "--c:$c;--c-soft:" . hex_tint($c, 0.14);
          $cOver = $pstats['overdue'] > 0 ? '#EF4444' : '#6B7280'; ?>
    <div class="rpt-kpis">
      <div class="kpi" style="<?= $kpiStyle('#FF7A00') ?>">
        <div class="kpi-ico"><i class="bi bi-graph-up-arrow"></i></div>
        <div><div class="kpi-num"><?= (int)$overall ?>%</div><div class="kpi-lbl">ความคืบหน้ารวม</div></div>
      </div>
      <div class="kpi" style="<?= $kpiStyle('#111111') ?>">
        <div class="kpi-ico"><i class="bi bi-hdd-stack"></i></div>
        <div><div class="kpi-num"><?= (int)$pstats['total'] ?></div><div class="kpi-lbl">จำนวนตู้ทั้งหมด</div></div>
      </div>
      <div class="kpi" style="<?= $kpiStyle('#16A34A') ?>">
        <div class="kpi-ico"><i class="bi bi-box-seam"></i></div>
        <div><div class="kpi-num"><?= (int)$pstats['delivered'] ?>/<?= (int)$pstats['total'] ?></div><div class="kpi-lbl">ส่งมอบแล้ว</div></div>
      </div>
      <div class="kpi" style="<?= $kpiStyle('#3B82F6') ?>">
        <div class="kpi-ico"><i class="bi bi-gear-wide-connected"></i></div>
        <div><div class="kpi-num"><?= (int)$pstats['producing'] ?></div><div class="kpi-lbl">กำลังดำเนินการ</div></div>
      </div>
      <div class="kpi" style="<?= $kpiStyle($cOver) ?>">
        <div class="kpi-ico"><i class="bi bi-exclamation-octagon"></i></div>
        <div><div class="kpi-num"><?= (int)$pstats['overdue'] ?></div><div class="kpi-lbl">ตู้ล่าช้า</div></div>
      </div>
    </div>

    <!-- Body: timeline + ring/info -->
    <div class="rpt-body">
      <section class="rpt-card timeline-card">
        <div class="rc-head"><i class="bi bi-bar-chart-steps"></i> แผนงานตามระยะเวลา (Project Timeline)</div>
        <div class="rc-body">
          <?php if (!$tasks): ?>
            <div class="empty">ยังไม่มีข้อมูลกิจกรรมในแผนงาน</div>
          <?php else: ?>
          <div class="gantt">
            <div class="gantt-axis">
              <div class="ga-label"></div>
              <div class="ga-track">
                <?php foreach ($timeline['months'] as $m): ?>
                  <div class="ga-month" style="left:<?= round($m['pos'],2) ?>%"><?= e(format_date(date('Y-m-d', $m['ts']))) ?></div>
                <?php endforeach; ?>
              </div>
            </div>
            <div class="gantt-rows">
              <?php
                $todayPos = null;
                if ($timeline['today'] >= $timeline['start'] && $timeline['today'] <= $timeline['end']) {
                    $todayPos = ($timeline['pos'])($timeline['today']);
                }
                foreach ($timeline['bars'] as $b): $bc = $b['color'] ?? status_color($b['status']); ?>
                <div class="g-row">
                  <div class="g-label"><?= e($b['task_name']) ?></div>
                  <div class="g-track">
                    <?php foreach ($timeline['months'] as $m): ?><span class="g-grid" style="left:<?= round($m['pos'],2) ?>%"></span><?php endforeach; ?>
                    <?php if ($todayPos !== null): ?><span class="g-today" style="left:<?= round($todayPos,2) ?>%"></span><?php endif; ?>
                    <div class="g-bar" style="left:<?= round($b['left'],2) ?>%;width:<?= round($b['width'],2) ?>%;--bc:<?= $bc ?>;--bc-soft:<?= hex_tint($bc,0.22) ?>">
                      <div class="g-fill" style="width:<?= (int)$b['progress'] ?>%"></div>
                      <span class="g-pct"><?= (int)$b['progress'] ?>%</span>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
            <?php if ($todayPos !== null): ?><div class="gantt-legend"><span class="dot-today"></span> เส้นวันนี้ (Today)</div><?php endif; ?>
          </div>
          <?php endif; ?>
        </div>
      </section>

      <aside class="rpt-side">
        <section class="rpt-card progress-card">
          <div class="rc-head"><i class="bi bi-speedometer2"></i> ความคืบหน้าโดยรวม (จากรายตู้)</div>
          <div class="rc-body ring-wrap">
            <svg width="180" height="180" viewBox="0 0 180 180" class="ring">
              <circle cx="90" cy="90" r="<?= $ringR ?>" class="ring-bg"></circle>
              <circle cx="90" cy="90" r="<?= $ringR ?>" class="ring-fg"
                      stroke="<?= status_color($st) ?>"
                      stroke-dasharray="<?= round($ringC,2) ?>"
                      stroke-dashoffset="<?= round($ringOffset,2) ?>"
                      transform="rotate(-90 90 90)"></circle>
              <text x="90" y="84" text-anchor="middle" class="ring-num"><?= (int)$overall ?>%</text>
              <text x="90" y="108" text-anchor="middle" class="ring-lbl">คืบหน้า</text>
            </svg>
            <div class="ring-stats">
              <div><span class="rs-n"><?= (int)$pstats['delivered'] ?>/<?= (int)$pstats['total'] ?></span><span class="rs-l">ตู้ส่งมอบ</span></div>
              <div><span class="rs-n"><?= (int)$pstats['overdue'] ?></span><span class="rs-l">ตู้ล่าช้า</span></div>
            </div>
          </div>
        </section>

        <section class="rpt-card info-card">
          <div class="rc-head"><i class="bi bi-info-circle"></i> ข้อมูลโครงการ</div>
          <div class="rc-body">
            <table class="info-table">
              <tr><td class="il">ลูกค้า</td><td class="iv"><?= e($p['customer'] ?: '-') ?></td></tr>
              <tr><td class="il">ผู้รับผิดชอบ</td><td class="iv"><?= e($p['responsible'] ?: '-') ?></td></tr>
              <tr><td class="il">วันเริ่มงาน</td><td class="iv"><?= e(format_date($p['start_date'])) ?></td></tr>
              <tr><td class="il">กำหนดส่ง</td><td class="iv"><?= e(format_date($p['due_date'])) ?></td></tr>
              <tr><td class="il">มูลค่างาน</td><td class="iv"><?= money($p['amount']) ?> บ.</td></tr>
            </table>
          </div>
        </section>
      </aside>
    </div>

    <!-- Bottom: Panel List | Delivery by Group | Milestones -->
    <div class="rpt-bottom rpt-bottom-3">

      <!-- bottom-left: Panel List -->
      <section class="rpt-card">
        <div class="rc-head"><i class="bi bi-hdd-stack"></i> รายการตู้ (Panel List)
          <span class="rc-count"><?= count($panels) ?> ตู้</span></div>
        <div class="rc-body">
          <?php if (!$panels): ?><div class="empty">ยังไม่มีข้อมูลตู้</div><?php else: ?>
          <table class="pl-table">
            <thead><tr><th>No.</th><th>ชื่อตู้</th><th>Grp</th><th>สถานะ</th><th class="text-end">%</th></tr></thead>
            <tbody>
              <?php foreach ($firstChunk as $pn): $es=$pn['eff_status']; ?>
                <tr>
                  <td class="mono"><?= e($pn['panel_no']) ?></td>
                  <td class="ellip"><?= e($pn['panel_name']) ?></td>
                  <td><?= e($pn['delivery_group'] ?: '-') ?></td>
                  <td><span class="pl-badge" style="background:<?= $scolor($es) ?>"><?= e($slabel($es)) ?></span></td>
                  <td class="text-end"><?= (int)$pn['progress_percent'] ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
          <?php if ($totalPages > 1): ?>
            <div class="pl-more">+ ดูตู้ที่เหลืออีก <?= count($panels)-count($firstChunk) ?> ตู้ ในหน้าถัดไป</div>
          <?php endif; ?>
          <?php endif; ?>
        </div>
      </section>

      <!-- bottom-center: Delivery Schedule by Group -->
      <section class="rpt-card">
        <div class="rc-head"><i class="bi bi-truck"></i> กำหนดส่งมอบรายกลุ่ม (by Group)</div>
        <div class="rc-body">
          <?php if (!$deliveryGroups): ?><div class="empty">-</div><?php else: ?>
          <table class="dg-table">
            <thead><tr><th>Group</th><th class="text-center">ตู้</th><th>กำหนด</th><th class="text-end">%</th></tr></thead>
            <tbody>
              <?php foreach ($deliveryGroups as $g): ?>
                <tr>
                  <td><span class="grp-chip" style="background:<?= $scolor($g['status']) ?>"><?= e($g['group']) ?></span></td>
                  <td class="text-center"><?= $g['done'] ?>/<?= $g['count'] ?></td>
                  <td><?= e(format_date($g['target'])) ?></td>
                  <td class="text-end"><?= $g['progress'] ?>%</td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
          <?php endif; ?>
        </div>
      </section>

      <!-- bottom-right: Key Milestones -->
      <section class="rpt-card">
        <div class="rc-head"><i class="bi bi-flag"></i> หมุดหมายสำคัญ (Milestones)</div>
        <div class="rc-body">
          <?php if (!$milestones): ?><div class="empty">-</div><?php else: ?>
          <ul class="ms-list">
            <?php foreach ($milestones as $m): ?>
              <li class="<?= $m['is_done'] ? 'done' : '' ?>">
                <span class="ms-ico"><i class="bi <?= $m['is_done'] ? 'bi-check-circle-fill' : 'bi-circle' ?>"></i></span>
                <span class="ms-title"><?= e($m['title']) ?></span>
                <span class="ms-date"><?= e(format_date($m['milestone_date'])) ?></span>
              </li>
            <?php endforeach; ?>
          </ul>
          <?php endif; ?>
        </div>
      </section>
    </div>

    <footer class="rpt-footer">
      <span>© <?= date('Y') ?> Avatar Electric Co., Ltd. — Schedule of Project</span>
      <span>Generated <?= e(date('d/m/Y H:i')) ?> · หน้า 1<?= $totalPages>1 ? '/'.$totalPages : '' ?></span>
    </footer>

  </div><!-- /page 1 -->

  <!-- ============ EXTRA PAGES (panels continued) ============ -->
  <?php foreach ($extraChunks as $ci => $chunk): $pageNo = $ci + 2; ?>
  <div class="report-page" data-page="<?= $pageNo ?>">
    <header class="rpt-header">
      <div class="rh-brand">
        <img src="assets/img/logo.png" alt="logo" class="rh-logo">
        <div><div class="rh-name">AVATAR ELECTRIC</div><div class="rh-sub">บริษัท อวตาร อิเล็คทริค จำกัด</div></div>
      </div>
      <div class="rh-center">
        <div class="rh-report">PANEL LIST (ต่อ)</div>
        <div class="rh-report-th">รายการตู้ (หน้า <?= $pageNo ?>/<?= $totalPages ?>)</div>
      </div>
      <div class="rh-meta"><div class="rh-no"><?= e($p['project_no']) ?></div>
        <div class="rh-date"><?= e($p['project_name']) ?></div></div>
    </header>

    <section class="rpt-card" style="flex:1">
      <div class="rc-head"><i class="bi bi-hdd-stack"></i> รายการตู้ (ต่อ) — ลำดับที่ <?= $ci*$PER_PAGE + $PER_PAGE + 1 ?> เป็นต้นไป</div>
      <div class="rc-body">
        <table class="pl-table pl-table-wide">
          <thead><tr><th>Panel No.</th><th>ชื่อตู้</th><th>ประเภท</th><th>Group</th><th>กำหนดส่ง</th><th>สถานะ</th><th class="text-end">%</th></tr></thead>
          <tbody>
            <?php foreach ($chunk as $pn): $es=$pn['eff_status']; ?>
              <tr>
                <td class="mono"><?= e($pn['panel_no']) ?></td>
                <td class="ellip"><?= e($pn['panel_name']) ?></td>
                <td><?= e($pn['panel_type'] ?: '-') ?></td>
                <td><?= e($pn['delivery_group'] ?: '-') ?></td>
                <td><?= e(format_date($pn['target_delivery_date'])) ?></td>
                <td><span class="pl-badge" style="background:<?= $scolor($es) ?>"><?= e($slabel($es)) ?></span></td>
                <td class="text-end"><?= (int)$pn['progress_percent'] ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>

    <footer class="rpt-footer">
      <span>© <?= date('Y') ?> Avatar Electric Co., Ltd. — Schedule of Project</span>
      <span>Generated <?= e(date('d/m/Y H:i')) ?> · หน้า <?= $pageNo ?>/<?= $totalPages ?></span>
    </footer>
  </div>
  <?php endforeach; ?>

</div><!-- /.canvas-stage -->

<script>
window.REPORT_FILEBASE = <?= json_encode($fileBase, JSON_UNESCAPED_UNICODE) ?>;
// In SPA fragment mode fitPreview() must run after HTML is injected into the modal
if (typeof window.fitPreview === 'function') { setTimeout(window.fitPreview, 120); }
</script>
<?php if (!$fragment): ?>
<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
<script src="assets/js/project_overview_export.js"></script>
</body>
</html>
<?php endif; ?>
