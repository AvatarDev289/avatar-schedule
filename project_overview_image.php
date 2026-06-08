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
 * Priority order: project_tasks (DB) → panels → project date span
 * scope=all  tries DB tasks first; all scopes fall through to panel/date fallbacks.
 */
$tasks = [];

if ($scope === 'all') {
    $tasks = get_project_tasks($id);
    // Normalize DB task fields → end_date + progress so build_timeline() can use them
    foreach ($tasks as &$t) {
        if (!isset($t['end_date']))  $t['end_date']  = $t['due_date'] ?? null;
        if (!isset($t['progress']))  $t['progress']  = (int)($t['progress_percent'] ?? 0);
        if (!isset($t['color'])) {
            $sc = ['completed' => '#16A34A', 'in_progress' => '#3B82F6',
                   'overdue' => '#EF4444', 'cancelled' => '#6B7280'];
            $t['color'] = $sc[$t['status'] ?? ''] ?? '#F59E0B';
        }
    }
    unset($t);
}

/* Fallback 1: no DB tasks (or scoped mode) → build rows from panels */
if (!$tasks && $panels) {
    $startBase = !empty($p['start_date']) ? $p['start_date'] : date('Y-m-d');
    foreach ($panels as $pn) {
        $end = $pn['actual_delivery_date'] ?: ($pn['target_delivery_date'] ?: ($p['due_date'] ?: date('Y-m-d')));
        $start = $startBase;
        if (strtotime($start) > strtotime($end)) {
            $start = $end;
        }
        $tasks[] = [
            'task_name'      => $pn['panel_no'] . ' · ' . $pn['panel_name'],
            'start_date'     => $start,
            'end_date'       => $end,
            'progress'       => (int)$pn['progress_percent'],
            'status'         => $pn['eff_status'],
            'color'          => panel_status_color($pn['eff_status']),
            'delivery_group' => (string)($pn['delivery_group'] ?? ''),
        ];
    }
}

/* Fallback 2: no panels either → single bar spanning the project dates */
if (!$tasks) {
    $pStart = !empty($p['start_date']) ? $p['start_date'] : date('Y-m-d');
    $pEnd   = !empty($p['due_date'])   ? $p['due_date']   : date('Y-m-d', strtotime('+30 days', strtotime($pStart)));
    if (strtotime($pEnd) <= strtotime($pStart)) {
        $pEnd = date('Y-m-d', strtotime('+30 days', strtotime($pStart)));
    }
    $tasks[] = [
        'task_name'  => mb_substr($p['project_name'], 0, 40),
        'start_date' => $pStart,
        'end_date'   => $pEnd,
        'progress'   => (int)$p['progress'],
        'status'     => $p['effective_status'],
        'color'      => status_color($p['effective_status']),
    ];
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

// Build stable group → color map (used throughout page)
$groupColorMap = build_group_color_map($panels);
$hasGroups     = !empty($groupColorMap);

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
uksort($byGroup, fn($a, $b) => panel_group_sort_key($a) <=> panel_group_sort_key($b));
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

$scolor   = fn($s) => getStatusColor($s);
$slabel   = fn($s) => panel_status_label($s);

/* ---------- Timeline Summary ---------- */
// Unified label: panel workflow steps → m_ manual → project statuses
$ulabel = function(string $s): string {
    return panel_status_labels()[$s] ?? manual_status_options()[$s] ?? status_labels()[$s] ?? $s;
};

// Classify a bar into category color + Thai label (override original workflow colors)
$bar_cat = function(array $t) use ($ulabel): array {
    $prog = (int)($t['progress'] ?? $t['progress_percent'] ?? 0);
    $stat = $t['status'] ?? '';
    if ($prog >= 100 || in_array($stat, ['delivered', 'completed'], true)) {
        return ['color' => '#16A34A', 'label' => 'เสร็จแล้ว'];
    }
    if (in_array($stat, ['overdue', 'm_overdue'], true)) {
        return ['color' => '#EF4444', 'label' => 'ล่าช้า'];
    }
    if (in_array($stat, ['m_on_hold', 'm_cancelled', 'cancelled'], true)) {
        return ['color' => '#6B7280', 'label' => 'พักงาน'];
    }
    if ($prog > 0) {
        return ['color' => '#3B82F6', 'label' => 'กำลังดำเนินการ'];
    }
    return ['color' => '#F59E0B', 'label' => 'รอเริ่ม'];
};

// Find first incomplete task → "current stage"
$tls_cur  = null;
$tls_nxt  = null;
$tls_done = false;
foreach ($tasks as $i => $t) {
    $prog = (int)($t['progress'] ?? $t['progress_percent'] ?? 0);
    $stat = $t['status'] ?? '';
    if ($prog < 100 && !in_array($stat, ['delivered', 'completed'], true) && $tls_cur === null) {
        $tls_cur = $t;
        $tls_nxt = $tasks[$i + 1] ?? null;
    }
}
if ($tasks && $tls_cur === null) {
    $tls_done = true;
}

// Summary display values
if ($tls_done) {
    $sumStatus   = 'เสร็จสมบูรณ์';
    $sumColor    = '#16A34A';
    $sumStage    = 'ส่งมอบแล้ว';
    $sumProgress = 100;
    $sumTarget   = $targetCompletion;
} elseif ($tls_cur) {
    $sumStatus   = $ulabel($tls_cur['status'] ?? 'pending');
    $sumColor    = panel_status_color($tls_cur['status'] ?? 'pending');
    $sumProgress = (int)($tls_cur['progress'] ?? $tls_cur['progress_percent'] ?? 0);
    $sumStage    = mb_strimwidth($tls_cur['task_name'] ?? '', 0, 44, '…');
    $sumTarget   = $tls_cur['end_date'] ?? ($tls_cur['due_date'] ?? $targetCompletion);
} else {
    $sumStatus   = $ulabel($p['effective_status']);
    $sumColor    = status_color($p['effective_status']);
    $sumStage    = '-';
    $sumProgress = $overall;
    $sumTarget   = $targetCompletion;
}
$sumNext = $tls_nxt
    ? mb_strimwidth($tls_nxt['task_name'] ?? '', 0, 44, '…')
    : ($tls_done ? '—' : 'ไม่มีขั้นตอนถัดไป');

/* ---------- Debug mode: ?debug_timeline=1 ---------- */
if (!empty($_GET['debug_timeline'])) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="th"><head><meta charset="utf-8">';
    echo '<style>body{font-family:monospace;padding:20px;font-size:13px;}';
    echo 'h2{color:#FF7A00;} h3{margin-top:18px;} ';
    echo 'table{border-collapse:collapse;margin-top:8px;} ';
    echo 'td,th{border:1px solid #ccc;padding:5px 10px;} th{background:#f3f4f6;}</style>';
    echo '</head><body>';
    echo '<h2>Timeline Debug — Project #' . (int)$id . ' &nbsp;·&nbsp; scope=' . e($scope) . '</h2>';
    echo '<table>';
    echo '<tr><th>project_id</th><td>' . (int)$id . '</td></tr>';
    echo '<tr><th>project_start (window)</th><td>' . date('Y-m-d', $timeline['start']) . '</td></tr>';
    echo '<tr><th>project_end (window)</th><td>' . date('Y-m-d', $timeline['end']) . '</td></tr>';
    echo '<tr><th>total_days</th><td>' . $timeline['total_days'] . '</td></tr>';
    echo '<tr><th>timeline_items (bars)</th><td>' . count($timeline['bars']) . '</td></tr>';
    echo '<tr><th>tasks fed to build_timeline</th><td>' . count($tasks) . '</td></tr>';
    echo '<tr><th>panels loaded</th><td>' . count($panels) . '</td></tr>';
    echo '</table>';
    echo '<h3>Bars</h3>';
    if (!$timeline['bars']) {
        echo '<p style="color:#EF4444">⚠️ No bars computed — check task start_date / end_date</p>';
    } else {
        echo '<table><tr><th>#</th><th>task_name</th><th>start_date</th><th>end_date</th>'
           . '<th>left%</th><th>width%</th><th>progress</th><th>status</th></tr>';
        foreach ($timeline['bars'] as $i => $b) {
            $lOk = $b['left'] >= 0 && $b['left'] <= 100;
            $wOk = $b['width'] > 0 && $b['width'] <= 100;
            echo '<tr'
               . (!$lOk ? ' style="background:#fef2f2"' : '')
               . '><td>' . ($i + 1) . '</td>'
               . '<td>' . e($b['task_name']) . '</td>'
               . '<td>' . e($b['start_date'] ?? '—') . '</td>'
               . '<td>' . e($b['end_date'] ?? '—') . '</td>'
               . '<td>' . ($lOk ? '' : '⚠️ ') . round($b['left'], 2) . '%</td>'
               . '<td>' . ($wOk ? '' : '⚠️ ') . round($b['width'], 2) . '%</td>'
               . '<td>' . (int)$b['progress'] . '%</td>'
               . '<td>' . e($b['status']) . '</td></tr>';
        }
        echo '</table>';
    }
    echo '</body></html>';
    exit;
}
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
    <?php if ($totalPages > 1): ?>
    <div class="tb-page-nav">
      <button class="btn btn-outline-secondary btn-sm" id="pgPrev" onclick="prevPage()" disabled><i class="bi bi-chevron-left"></i></button>
      <span class="tb-page-info" id="pgInfo">หน้า 1/<?= $totalPages ?></span>
      <button class="btn btn-outline-secondary btn-sm" id="pgNext" onclick="nextPage()"><i class="bi bi-chevron-right"></i></button>
    </div>
    <?php endif; ?>
    <?php if ($hasGroups): ?>
    <div class="color-mode-wrap" title="เลือกโหมดสี Timeline">
      <button class="color-mode-btn active" data-mode="group" onclick="setGanttColorMode('group')">
        <i class="bi bi-palette2"></i> สีตาม Group
      </button>
      <button class="color-mode-btn" data-mode="status" onclick="setGanttColorMode('status')">
        <i class="bi bi-circle-half"></i> สีตามสถานะ
      </button>
    </div>
    <?php endif; ?>
    <button class="btn btn-success"   onclick="downloadPNG()"><i class="bi bi-filetype-png"></i> Download PNG</button>
    <button class="btn btn-primary"   onclick="downloadJPG()"><i class="bi bi-filetype-jpg"></i> Download JPG</button>
    <button class="btn btn-dark"      onclick="printPDF()"><i class="bi bi-printer"></i> Print / PDF</button>
    <button class="btn btn-outline-secondary" onclick="toggleFullscreen()"><i class="bi bi-arrows-fullscreen"></i> Fullscreen</button>
  </div>
</div>

<div class="export-status" id="exportStatus" hidden>
  <div class="spinner-border spinner-border-sm"></div> กำลังสร้างรูปภาพ...
</div>

<style>
/* --- Timeline Summary strip --- */
.tls-box{display:flex;background:#F8FAFC;border:1px solid #E2E8F0;border-radius:8px;margin-bottom:10px;overflow:hidden;font-family:inherit}
.tls-item{flex:1;padding:7px 10px;border-right:1px solid #E2E8F0;text-align:center}
.tls-item:last-child{border-right:none}
.tls-item.tls-current{background:#fff8f0}
.tls-lbl{font-size:9px;font-weight:700;color:#94A3B8;text-transform:uppercase;letter-spacing:.4px;margin-bottom:2px}
.tls-val{font-size:11.5px;font-weight:700;line-height:1.25;word-break:break-word}
/* --- Gantt status column --- */
.g-status-col{flex:0 0 80px;display:flex;align-items:center;justify-content:flex-end;padding-left:5px}
.g-st-pill{font-size:9px;font-weight:700;color:#fff;padding:2px 7px;border-radius:10px;white-space:nowrap;line-height:1.4}
/* --- Page navigation in toolbar --- */
.tb-page-nav{display:flex;align-items:center;gap:6px}
.tb-page-info{font-size:13px;font-weight:600;color:#374151;padding:0 6px;white-space:nowrap;min-width:72px;text-align:center}
/* Hide non-active report pages in preview — export JS removes this class before capturing */
.page-preview-hidden{display:none!important}
</style>

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
        <div class="rh-date">ข้อมูล ณ <?= e(format_date_dmy(date('Y-m-d'))) ?></div>
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
          <span class="rt-target-v"><?= e(format_date_dmy($targetCompletion)) ?></span></div>
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
          <!-- Timeline Summary -->
          <div class="tls-box">
            <div class="tls-item tls-current">
              <div class="tls-lbl">สถานะปัจจุบัน</div>
              <div class="tls-val" style="color:<?= $sumColor ?>"><?= e($sumStatus) ?></div>
            </div>
            <div class="tls-item">
              <div class="tls-lbl">ขั้นตอนงาน</div>
              <div class="tls-val"><?= e($sumStage) ?></div>
            </div>
            <div class="tls-item">
              <div class="tls-lbl">ความคืบหน้า</div>
              <div class="tls-val" style="color:<?= $sumColor ?>"><?= $sumProgress ?>%</div>
            </div>
            <div class="tls-item">
              <div class="tls-lbl">ขั้นตอนถัดไป</div>
              <div class="tls-val"><?= e($sumNext) ?></div>
            </div>
            <div class="tls-item">
              <div class="tls-lbl">กำหนดเสร็จ</div>
              <div class="tls-val"><?= e(format_date_dmy($sumTarget)) ?></div>
            </div>
          </div>
          <div class="gantt">
            <div class="gantt-axis">
              <div class="ga-label"></div>
              <div class="ga-track">
                <?php foreach ($timeline['months'] as $m): ?>
                  <div class="ga-month" style="left:<?= round($m['pos'],2) ?>%"><?= e(format_date_dmy(date('Y-m-d', $m['ts']))) ?></div>
                <?php endforeach; ?>
              </div>
              <div style="flex:0 0 80px"></div><!-- spacer matches .g-status-col -->
            </div>
            <div class="gantt-rows">
              <?php
                $todayPos = null;
                if ($timeline['today'] >= $timeline['start'] && $timeline['today'] <= $timeline['end']) {
                    $todayPos = ($timeline['pos'])($timeline['today']);
                }
                foreach ($timeline['bars'] as $b):
                    $cat         = $bar_cat($b);
                    $grp         = isset($b['delivery_group']) ? (string)$b['delivery_group'] : '';
                    $groupColor  = ($grp !== '' && isset($groupColorMap[$grp])) ? $groupColorMap[$grp] : null;
                    $statusColor = getStatusColor($b['status'] ?? '');
                    $bc          = $groupColor ?? $statusColor; // default: color by group
                ?>
                <div class="g-row">
                  <div class="g-label"><?= e($b['task_name']) ?></div>
                  <div class="g-track">
                    <?php foreach ($timeline['months'] as $m): ?><span class="g-grid" style="left:<?= round($m['pos'],2) ?>%"></span><?php endforeach; ?>
                    <?php if ($todayPos !== null): ?><span class="g-today" style="left:<?= round($todayPos,2) ?>%"></span><?php endif; ?>
                    <div class="g-bar"
                         style="left:<?= round($b['left'],2) ?>%;width:<?= round($b['width'],2) ?>%;--bc:<?= $bc ?>;--bc-soft:<?= hex_tint($bc,0.22) ?>"
                         data-group-color="<?= e($groupColor ?? $statusColor) ?>"
                         data-status-color="<?= e($statusColor) ?>">
                      <div class="g-fill" style="width:<?= (int)$b['progress'] ?>%"></div>
                      <span class="g-pct"><?= (int)$b['progress'] ?>%</span>
                    </div>
                  </div>
                  <div class="g-status-col"><span class="g-st-pill" style="background:<?= e($statusColor) ?>"><?= e($cat['label']) ?></span></div>
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
              <tr><td class="il">วันเริ่มงาน</td><td class="iv"><?= e(format_date_dmy($p['start_date'])) ?></td></tr>
              <tr><td class="il">กำหนดส่ง</td><td class="iv"><?= e(format_date_dmy($p['due_date'])) ?></td></tr>
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
                <?php $dg = (string)($pn['delivery_group'] ?? ''); $dgc = $dg !== '' ? ($groupColorMap[$dg] ?? '#6B7280') : null; ?>
                <tr>
                  <td class="mono"><?= e($pn['panel_no']) ?></td>
                  <td class="ellip"><?= e($pn['panel_name']) ?></td>
                  <td><?= $dgc ? getGroupBadge($dg, $dgc) : '-' ?></td>
                  <td><?= getStatusBadge($es) ?></td>
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
                <?php $gc = $groupColorMap[$g['group']] ?? '#6B7280'; ?>
                <tr>
                  <td><span class="grp-chip" style="background:<?= e($gc) ?>"><?= e($g['group']) ?></span></td>
                  <td class="text-center"><?= $g['done'] ?>/<?= $g['count'] ?></td>
                  <td><?= e(format_date_dmy($g['target'])) ?></td>
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
                <span class="ms-date"><?= e(format_date_dmy($m['milestone_date'])) ?></span>
              </li>
            <?php endforeach; ?>
          </ul>
          <?php endif; ?>
        </div>
      </section>
    </div>

    <!-- Legend: Group + Status (only items present in this project) -->
    <?php
      $usedStatuses = $panels ? array_values(array_unique(array_map(fn($p) => $p['eff_status'], $panels))) : [];
      sort($usedStatuses);
    ?>
    <?php if ($hasGroups || $usedStatuses): ?>
    <div class="rpt-legend">
      <?php if ($hasGroups): ?>
      <div class="legend-section">
        <div class="legend-title">Group Legend</div>
        <div class="legend-items">
          <?php foreach ($groupColorMap as $gName => $gColor): ?>
            <div class="legend-item">
              <span class="legend-dot" style="background:<?= e($gColor) ?>"></span>
              <span class="legend-label"><?= e($gName) ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>
      <?php if ($usedStatuses): ?>
      <div class="legend-section">
        <div class="legend-title">Status Legend</div>
        <div class="legend-items">
          <?php foreach ($usedStatuses as $stk): ?>
            <div class="legend-item">
              <span class="legend-dot" style="background:<?= getStatusColor($stk) ?>"></span>
              <span class="legend-label"><?= e(getStatusLabel($stk)) ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>

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
              <?php $dg2 = (string)($pn['delivery_group'] ?? ''); $dgc2 = $dg2 !== '' ? ($groupColorMap[$dg2] ?? '#6B7280') : null; ?>
              <tr>
                <td class="mono"><?= e($pn['panel_no']) ?></td>
                <td class="ellip"><?= e($pn['panel_name']) ?></td>
                <td><?= e($pn['panel_type'] ?: '-') ?></td>
                <td><?= $dgc2 ? getGroupBadge($dg2, $dgc2) : '-' ?></td>
                <td><?= e(format_date_dmy($pn['target_delivery_date'])) ?></td>
                <td><?= getStatusBadge($es) ?></td>
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

// Mirror of PHP hex_tint(): mix hex color with white by colorPct (0..1)
function tintHex(hex, colorPct) {
    hex = hex.replace('#', '');
    if (hex.length === 3) hex = hex[0]+hex[0]+hex[1]+hex[1]+hex[2]+hex[2];
    var r = parseInt(hex.substr(0,2),16),
        g = parseInt(hex.substr(2,2),16),
        b = parseInt(hex.substr(4,2),16);
    var mix = function(c) { return Math.round(colorPct * c + (1 - colorPct) * 255); };
    return '#' + [mix(r), mix(g), mix(b)].map(function(v){
        return v.toString(16).padStart(2,'0');
    }).join('');
}

function setGanttColorMode(mode) {
    document.querySelectorAll('.g-bar[data-group-color]').forEach(function(bar) {
        var color = mode === 'status' ? bar.dataset.statusColor : bar.dataset.groupColor;
        bar.style.setProperty('--bc', color);
        bar.style.setProperty('--bc-soft', tintHex(color, 0.22));
    });
    document.querySelectorAll('.color-mode-btn').forEach(function(btn) {
        btn.classList.toggle('active', btn.dataset.mode === mode);
    });
    window._ganttColorMode = mode;
}

<?php if ($totalPages > 1): ?>
// Page navigator: show one report-page at a time in preview
(function () {
  var TOTAL = <?= $totalPages ?>;
  var cur = 1;
  function goToPage(n) {
    if (n < 1 || n > TOTAL) return;
    cur = n;
    document.querySelectorAll('.report-page').forEach(function (pg) {
      var pn = parseInt(pg.getAttribute('data-page'), 10) || 1;
      pg.classList.toggle('page-preview-hidden', pn !== cur);
    });
    var info = document.getElementById('pgInfo');
    if (info) info.textContent = 'หน้า ' + cur + '/' + TOTAL;
    var btnPrev = document.getElementById('pgPrev');
    var btnNext = document.getElementById('pgNext');
    if (btnPrev) btnPrev.disabled = cur <= 1;
    if (btnNext) btnNext.disabled = cur >= TOTAL;
    if (typeof window.fitPreview === 'function') window.fitPreview();
  }
  window.prevPage = function () { goToPage(cur - 1); };
  window.nextPage = function () { goToPage(cur + 1); };
  goToPage(1); // show only page 1 on load; export JS reveals all before capturing
})();
<?php endif; ?>
</script>
<?php if (!$fragment): ?>
<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
<script src="assets/js/project_overview_export.js"></script>
</body>
</html>
<?php endif; ?>
