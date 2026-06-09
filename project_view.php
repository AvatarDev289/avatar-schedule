<?php
/**
 * project_view.php — Project detail (ClickUp-inspired redesign)
 * Tabs: Overview · รายการตู้ · Timeline · ส่งมอบ · รายงาน · Activity Log
 */
declare(strict_types=1);
require_once __DIR__ . '/functions.php';

$id = (int)($_GET['id'] ?? 0);
$p  = get_project($id);

if (!$p) {
    render_header('ไม่พบโครงการ', 'projects.php');
    echo '<div class="alert alert-warning">ไม่พบโครงการที่ต้องการ</div>';
    render_footer();
    exit;
}

// Handle task status update (inline POST action)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'update_task') {
    $taskId    = (int)($_POST['task_id'] ?? 0);
    $newStatus = in_array($_POST['task_status'] ?? '', ['pending','in_progress','completed'], true)
                 ? $_POST['task_status'] : 'pending';
    $actualStart    = !empty($_POST['actual_start_date']) ? $_POST['actual_start_date'] : null;
    $actualComplete = !empty($_POST['actual_completed_date']) ? $_POST['actual_completed_date'] : null;

    $panelIdForTask = 0;
    if ($taskId > 0) {
        $r = db()->prepare("SELECT panel_id FROM project_tasks WHERE id = :tid AND project_id = :pid");
        $r->execute([':tid' => $taskId, ':pid' => $id]);
        $panelIdForTask = (int)$r->fetchColumn();
        update_task_status($taskId, $newStatus, $actualStart, $actualComplete);
    }
    header('Location: project_view.php?id=' . $id . '&open_panel=' . $panelIdForTask . '#panels');
    exit;
}

// Handle task planned-date update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'update_task_dates') {
    $taskId     = (int)($_POST['task_id'] ?? 0);
    $newStart   = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['planned_start'] ?? '') ? $_POST['planned_start'] : null;
    $newFinish  = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['planned_finish'] ?? '') ? $_POST['planned_finish'] : null;
    $doRecalc   = !empty($_POST['recalculate_subsequent']);
    $panelId    = 0;

    if ($taskId > 0 && ($newStart || $newFinish)) {
        $stFetch = db()->prepare("SELECT * FROM project_tasks WHERE id = :id AND project_id = :pid");
        $stFetch->execute([':id' => $taskId, ':pid' => $id]);
        $tRow = $stFetch->fetch();

        if ($tRow) {
            $panelId = (int)$tRow['panel_id'];
            $sets = [];
            $params = [':id' => $taskId];
            if ($newStart)  { $sets[] = 'start_date = :s'; $params[':s'] = $newStart; }
            if ($newFinish) { $sets[] = 'due_date = :f';   $params[':f'] = $newFinish; }
            if ($sets) {
                db()->prepare('UPDATE project_tasks SET ' . implode(', ', $sets) . ' WHERE id = :id')
                    ->execute($params);
            }

            if ($doRecalc && $panelId) {
                // Get this task's sort_order, then recalculate from next task
                $sortOrd = (int)$tRow['sort_order'];
                recalculate_task_dates_from($panelId, $sortOrd + 1);
            }

            recompute_panel_from_tasks($panelId);
        }
    }
    header('Location: project_view.php?id=' . $id . '&open_panel=' . $panelId . '#panels');
    exit;
}

// Bulk action handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['_bulk_action'])) {
    $bulkAction  = $_POST['_bulk_action'];
    $selectedIds = array_map('intval', (array)($_POST['selected_panels'] ?? []));
    $selectedIds = array_filter($selectedIds, fn($x) => $x > 0);
    $bulkMsg     = '';

    if ($selectedIds && in_array($bulkAction, ['bulk_auto_tasks','bulk_update_group','bulk_update_date','bulk_add_task','bulk_delete'], true)) {
        switch ($bulkAction) {
            case 'bulk_auto_tasks':
                $conflictAction = $_POST['bulk_conflict'] ?? 'add_missing';
                $tpl            = trim($_POST['bulk_template'] ?? 'auto');
                $totalCreated   = 0;
                foreach ($selectedIds as $pid) {
                    $existing = count_panel_tasks($pid);
                    if ($existing > 0 && $conflictAction === 'skip') continue;
                    if ($existing > 0 && $conflictAction === 'replace') {
                        delete_panel_tasks($pid);
                        $totalCreated += create_panel_auto_tasks($id, $pid, $tpl === 'auto' ? 'auto' : $tpl);
                    } else {
                        $skip = ($existing > 0 && $conflictAction === 'add_missing');
                        $totalCreated += create_panel_auto_tasks($id, $pid, $tpl === 'auto' ? 'auto' : $tpl, $skip);
                    }
                    recompute_panel_from_tasks($pid);
                }
                $bulkMsg = 'สร้าง Tasks อัตโนมัติ ' . $totalCreated . ' รายการ ใน ' . count($selectedIds) . ' ตู้';
                break;

            case 'bulk_update_group':
                $newGroup = trim($_POST['bulk_group'] ?? '');
                foreach ($selectedIds as $pid) {
                    db()->prepare("UPDATE project_panels SET delivery_group = :g WHERE id = :id AND project_id = :proj")
                        ->execute([':g' => ($newGroup === '' ? null : $newGroup), ':id' => $pid, ':proj' => $id]);
                }
                $bulkMsg = 'เปลี่ยน Group เป็น "' . ($newGroup ?: '—') . '" ให้ ' . count($selectedIds) . ' ตู้';
                break;

            case 'bulk_update_date':
                $newDate = trim($_POST['bulk_date'] ?? '');
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $newDate)) {
                    foreach ($selectedIds as $pid) {
                        db()->prepare("UPDATE project_panels SET target_delivery_date = :d WHERE id = :id AND project_id = :proj")
                            ->execute([':d' => $newDate, ':id' => $pid, ':proj' => $id]);
                    }
                    $bulkMsg = 'ตั้ง Target Date ' . format_date_dmy($newDate) . ' ให้ ' . count($selectedIds) . ' ตู้';
                } else {
                    $bulkMsg = 'วันที่ไม่ถูกต้อง';
                }
                break;

            case 'bulk_add_task':
                $taskName = trim($_POST['bulk_task_name'] ?? '');
                if ($taskName !== '') {
                    $ins = db()->prepare(
                        "INSERT INTO project_tasks (project_id, panel_id, task_name, sort_order, status, progress_percent)
                         VALUES (:proj, :pid, :name, (SELECT COALESCE(MAX(sort_order),0)+1 FROM project_tasks t2 WHERE t2.panel_id = :pid2), 'pending', 0)"
                    );
                    foreach ($selectedIds as $pid) {
                        $ins->execute([':proj' => $id, ':pid' => $pid, ':pid2' => $pid, ':name' => $taskName]);
                        recompute_panel_from_tasks($pid);
                    }
                    $bulkMsg = 'เพิ่ม Task "' . e($taskName) . '" ให้ ' . count($selectedIds) . ' ตู้';
                }
                break;

            case 'bulk_delete':
                foreach ($selectedIds as $pid) {
                    delete_panel($pid);
                }
                $bulkMsg = 'ลบ ' . count($selectedIds) . ' ตู้แล้ว';
                break;
        }
    }
    header('Location: project_view.php?id=' . $id . '&msg=' . rawurlencode($bulkMsg) . '#panels');
    exit;
}

$st        = $p['effective_status'];
$od        = overdue_days($p);
$dr        = days_remaining($p);
$panels    = get_project_panels($id);
$pstats    = panel_stats($panels);
$panelProg = $pstats['total'] > 0 ? $pstats['overall'] : (int)$p['progress'];
$groupMap  = build_group_color_map($panels);
$dgGroups  = get_delivery_groups($id);
$returnUrl = 'project_view.php?id=' . $id;

// Fetch tasks per panel for display
$panelTaskMap = [];
foreach ($panels as $_pn) {
    $_pt = get_panel_tasks((int)$_pn['id']);
    if ($_pt) {
        $panelTaskMap[(int)$_pn['id']] = $_pt;
    }
}
unset($_pn, $_pt);

// Activity log
try {
    $actRows = db()->prepare(
        "SELECT * FROM activity_logs WHERE project_id = :id ORDER BY created_at DESC LIMIT 50"
    );
    $actRows->execute([':id' => $id]);
    $activities = $actRows->fetchAll();
} catch (Throwable) {
    $activities = [];
}

// Project Health
$health = 'on_track';
if ($st === 'overdue' || ($pstats['total'] > 0 && $pstats['overdue'] >= (int)ceil($pstats['total'] * 0.25))) {
    $health = 'delayed';
} elseif ($pstats['overdue'] > 0 || $st === 'near_due') {
    $health = 'at_risk';
}
$healthConf = [
    'on_track' => ['label' => 'On Track', 'emoji' => '🟢', 'cls' => 'pv-health-on-track'],
    'at_risk'  => ['label' => 'At Risk',  'emoji' => '🟡', 'cls' => 'pv-health-at-risk'],
    'delayed'  => ['label' => 'Delayed',  'emoji' => '🔴', 'cls' => 'pv-health-delayed'],
];
$hc = $healthConf[$health];

// Delivery group stats from panels
$groupStats = [];
foreach ($panels as $pn) {
    $g = trim((string)($pn['delivery_group'] ?? ''));
    if ($g === '') continue;
    if (!isset($groupStats[$g])) {
        $groupStats[$g] = [
            'total'     => 0,
            'delivered' => 0,
            'overdue'   => 0,
            'color'     => $groupMap[$g] ?? '#6B7280',
            'maxDate'   => null,
        ];
    }
    $groupStats[$g]['total']++;
    if ($pn['eff_status'] === 'delivered') $groupStats[$g]['delivered']++;
    if ($pn['eff_status'] === 'overdue')   $groupStats[$g]['overdue']++;
    if (!empty($pn['target_delivery_date'])) {
        if ($groupStats[$g]['maxDate'] === null || $pn['target_delivery_date'] > $groupStats[$g]['maxDate']) {
            $groupStats[$g]['maxDate'] = $pn['target_delivery_date'];
        }
    }
}

// Timeline window (panel-based gantt)
$today  = strtotime(date('Y-m-d'));
$tlStart = $tlEnd = null;
if ($panels) {
    $dates = [];
    if (!empty($p['start_date'])) $dates[] = strtotime($p['start_date']);
    foreach ($panels as $pn) {
        if (!empty($pn['target_delivery_date'])) $dates[] = strtotime($pn['target_delivery_date']);
    }
    if ($dates) {
        $tlStart = min($dates);
        $tlEnd   = max($dates);
    }
}
if (!$tlStart) {
    $tlStart = strtotime(date('Y-m-01'));
    $tlEnd   = strtotime('+3 months', $tlStart);
}
if ($tlEnd <= $tlStart) {
    $tlEnd = strtotime('+30 days', $tlStart);
}
$tlPad   = (int)max(86400 * 3, ($tlEnd - $tlStart) * 0.04);
$tlStart -= $tlPad;
$tlEnd   += $tlPad;
$tlSpan   = max(1, $tlEnd - $tlStart);

$tlPos   = fn(int $ts): float => max(0.0, min(100.0, (($ts - $tlStart) / $tlSpan) * 100.0));
$todayPct = $tlPos($today);

$tlMonths = [];
$cur = strtotime(date('Y-m-01', $tlStart));
while ($cur <= $tlEnd) {
    if ($cur >= $tlStart) {
        $tlMonths[] = ['pos' => $tlPos($cur), 'label' => date('M y', $cur)];
    }
    $cur = strtotime('+1 month', $cur);
}

// Unique groups and statuses (for filter dropdowns)
$filterGroups   = array_keys($groupMap);
$filterStatuses = array_values(array_unique(array_map(fn($pn) => $pn['eff_status'], $panels)));
sort($filterStatuses);

// Activity action config
$actConf = [
    'create'       => ['ico' => 'bi-plus-lg',        'cls' => 'add',    'label' => 'สร้างโครงการ'],
    'update'       => ['ico' => 'bi-pencil',          'cls' => 'edit',   'label' => 'แก้ไขโครงการ'],
    'delete'       => ['ico' => 'bi-trash',           'cls' => 'del',    'label' => 'ลบ'],
    'panel_add'    => ['ico' => 'bi-plus-circle',     'cls' => 'add',    'label' => 'เพิ่มตู้'],
    'panel_edit'   => ['ico' => 'bi-pencil-square',   'cls' => 'edit',   'label' => 'แก้ไขตู้'],
    'panel_status' => ['ico' => 'bi-arrow-right-circle', 'cls' => 'status', 'label' => 'อัปเดตสถานะ'],
    'panel_delete' => ['ico' => 'bi-dash-circle',     'cls' => 'del',    'label' => 'ลบตู้'],
];

render_header('โครงการ ' . $p['project_no'], 'projects.php');
?>

<?php if (isset($_GET['msg'])): ?>
<div class="alert alert-success alert-dismissible fade show mb-3">
  <i class="bi bi-check-circle me-2"></i><?= e($_GET['msg']) ?>
  <button class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- ===== Summary Header ===== -->
<div class="pv-header">
  <div>
    <div class="pv-hno">SO: <?= e($p['project_no']) ?></div>
    <h2 class="pv-hname"><?= e($p['project_name']) ?></h2>
    <?php if ($p['customer']): ?>
      <div class="pv-hcust"><i class="bi bi-building"></i><?= e($p['customer']) ?></div>
    <?php endif; ?>
    <div class="pv-hstatus">
      <span class="pv-health <?= $hc['cls'] ?>"><?= $hc['emoji'] ?> <?= $hc['label'] ?></span>
      <span class="badge-status <?= status_badge_class($st) ?>"><?= e(status_label($st)) ?></span>
    </div>
    <div class="pv-stats-row">
      <div class="pv-stat">
        <span class="pv-stat-n"><?= $pstats['total'] ?></span>
        <span class="pv-stat-l">ตู้ทั้งหมด</span>
      </div>
      <div class="pv-stat">
        <span class="pv-stat-n blue"><?= $pstats['producing'] ?></span>
        <span class="pv-stat-l">กำลังผลิต</span>
      </div>
      <div class="pv-stat">
        <span class="pv-stat-n ok"><?= $pstats['delivered'] ?></span>
        <span class="pv-stat-l">ส่งมอบแล้ว</span>
      </div>
      <div class="pv-stat">
        <span class="pv-stat-n danger"><?= $pstats['overdue'] ?></span>
        <span class="pv-stat-l">ล่าช้า</span>
      </div>
      <div class="pv-stat">
        <span class="pv-stat-n warn"><?= $pstats['pending'] ?></span>
        <span class="pv-stat-l">รอเริ่ม</span>
      </div>
    </div>
  </div>
  <div class="pv-hright">
    <div>
      <div class="pv-big-pct"><?= $panelProg ?>%</div>
      <div class="pv-big-pct-l">Progress รวม</div>
    </div>
    <div>
      <div class="pv-hdate"><i class="bi bi-calendar3 me-1"></i>กำหนดส่ง: <?= e(format_date_dmy($p['due_date'])) ?></div>
      <?php if ($st === 'overdue'): ?>
        <div class="pv-hdate-warn" style="color:#FCA5A5"><i class="bi bi-exclamation-octagon me-1"></i>ล่าช้า <?= $od ?> วัน</div>
      <?php elseif ($dr !== null): ?>
        <div class="pv-hdate-warn" style="color:<?= $dr <= NEAR_DUE_DAYS ? '#FCD34D' : '#9CA3AF' ?>">
          <i class="bi bi-clock me-1"></i>เหลือ <?= $dr ?> วัน
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- ===== Action buttons ===== -->
<div class="pv-actions">
  <a href="panel_add.php?project_id=<?= (int)$id ?>" class="btn btn-success btn-sm">
    <i class="bi bi-plus-lg me-1"></i>เพิ่มตู้
  </a>
  <a href="project_report_select.php?project_id=<?= (int)$p['id'] ?>" class="btn btn-info btn-sm text-white">
    <i class="bi bi-image me-1"></i>Overview Report
  </a>
  <a href="print_report.php?id=<?= (int)$p['id'] ?>" target="_blank" class="btn btn-outline-secondary btn-sm">
    <i class="bi bi-printer me-1"></i>พิมพ์
  </a>
  <div class="ms-auto d-flex gap-2">
    <a href="project_edit.php?id=<?= (int)$p['id'] ?>" class="btn btn-outline-primary btn-sm">
      <i class="bi bi-pencil me-1"></i>แก้ไขโครงการ
    </a>
    <a href="project_delete.php?id=<?= (int)$p['id'] ?>" class="btn btn-outline-danger btn-sm"
       onclick="return confirm('ยืนยันการลบโครงการนี้?')">
      <i class="bi bi-trash"></i>
    </a>
    <a href="projects.php" class="btn btn-light btn-sm"><i class="bi bi-arrow-left me-1"></i>กลับ</a>
  </div>
</div>

<!-- ===== Tabs ===== -->
<ul class="nav pv-nav" id="pvTabs" role="tablist">
  <li class="nav-item" role="presentation">
    <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-overview" role="tab">
      <i class="bi bi-grid-1x2"></i>Overview
    </button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-cabinets" role="tab">
      <i class="bi bi-hdd-stack"></i>รายการตู้
      <?php if ($pstats['total']): ?>
        <span class="badge bg-primary ms-1"><?= $pstats['total'] ?></span>
      <?php endif; ?>
    </button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-timeline" role="tab">
      <i class="bi bi-bar-chart-gantt"></i>Timeline
    </button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-delivery" role="tab">
      <i class="bi bi-truck"></i>ส่งมอบ
    </button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-reports" role="tab">
      <i class="bi bi-file-earmark-bar-graph"></i>รายงาน
    </button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-activity" role="tab">
      <i class="bi bi-clock-history"></i>Activity
      <?php if ($activities): ?>
        <span class="badge bg-secondary ms-1"><?= count($activities) ?></span>
      <?php endif; ?>
    </button>
  </li>
</ul>

<div class="tab-content pv-tab-content" id="pvTabContent">

  <!-- ============================================================ -->
  <!-- TAB: OVERVIEW                                                 -->
  <!-- ============================================================ -->
  <div class="tab-pane fade show active" id="tab-overview" role="tabpanel">

    <!-- KPI Cards -->
    <div class="pv-kpi-row">
      <div class="pv-kpi" style="--c:var(--gray-500)">
        <div class="pv-kpi-n"><?= $pstats['total'] ?></div>
        <div class="pv-kpi-l">ตู้ทั้งหมด</div>
      </div>
      <div class="pv-kpi" style="--c:var(--primary-color)">
        <div class="pv-kpi-n"><?= $pstats['producing'] ?></div>
        <div class="pv-kpi-l">กำลังดำเนินการ</div>
      </div>
      <div class="pv-kpi" style="--c:#F59E0B">
        <div class="pv-kpi-n"><?= ($pstats['count']['material'] ?? 0) ?></div>
        <div class="pv-kpi-l">รออุปกรณ์</div>
      </div>
      <div class="pv-kpi" style="--c:#06B6D4">
        <div class="pv-kpi-n"><?= ($pstats['count']['qc'] ?? 0) ?></div>
        <div class="pv-kpi-l">กำลัง FAT</div>
      </div>
      <div class="pv-kpi" style="--c:var(--success-color)">
        <div class="pv-kpi-n"><?= $pstats['delivered'] ?></div>
        <div class="pv-kpi-l">ส่งมอบแล้ว</div>
      </div>
      <div class="pv-kpi" style="--c:var(--danger-color)">
        <div class="pv-kpi-n"><?= $pstats['overdue'] ?></div>
        <div class="pv-kpi-l">ล่าช้า</div>
      </div>
    </div>

    <!-- Project info + Progress -->
    <div class="grid-3">
      <div class="panel span-2">
        <div class="panel-head"><i class="bi bi-clipboard-data"></i> ข้อมูลโครงการ</div>
        <div class="panel-body">
          <div class="detail-grid">
            <div><span class="dl">ลูกค้า</span><span class="dv"><?= e($p['customer'] ?: '-') ?></span></div>
            <div><span class="dl">มูลค่างาน</span><span class="dv fw-600"><?= money2($p['amount']) ?> บาท</span></div>
            <div><span class="dl">วันเริ่มงาน</span><span class="dv"><?= e(format_date_dmy($p['start_date'])) ?></span></div>
            <div><span class="dl">กำหนดส่งมอบ</span><span class="dv"><?= e(format_date_dmy($p['due_date'])) ?></span></div>
            <div><span class="dl">วันส่งมอบจริง</span><span class="dv"><?= e(format_date_dmy($p['delivery_date'])) ?></span></div>
            <div><span class="dl">วันเสร็จงาน</span><span class="dv"><?= e(format_date_dmy($p['completed_date'])) ?></span></div>
          </div>
          <?php if ($p['description']): ?>
            <hr><div class="mb-1"><span class="dl">รายละเอียดงาน</span></div>
            <p class="mb-0"><?= nl2br(e($p['description'])) ?></p>
          <?php endif; ?>
          <?php if ($p['remark']): ?>
            <div class="mt-3 mb-1"><span class="dl">หมายเหตุ</span></div>
            <p class="mb-0 text-muted small"><?= nl2br(e($p['remark'])) ?></p>
          <?php endif; ?>
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
          <div class="progress-ring" style="--p:<?= $panelProg ?>;--col:<?= status_color($st) ?>">
            <span><?= $panelProg ?>%</span>
          </div>
          <ul class="mini-stats mt-3">
            <?php if ($st === 'overdue'): ?>
              <li class="danger"><i class="bi bi-exclamation-octagon"></i> ล่าช้า <?= $od ?> วัน</li>
            <?php elseif ($st === 'completed'): ?>
              <li class="ok"><i class="bi bi-check-circle"></i> เสร็จสมบูรณ์</li>
            <?php elseif ($dr !== null): ?>
              <li class="<?= $dr <= NEAR_DUE_DAYS ? 'warn' : '' ?>">
                <i class="bi bi-clock"></i> เหลืออีก <?= $dr ?> วัน
              </li>
            <?php endif; ?>
            <?php if ($pstats['total']): ?>
              <li><i class="bi bi-hdd-stack"></i> ส่งมอบแล้ว <?= $pstats['delivered'] ?>/<?= $pstats['total'] ?> ตู้</li>
              <?php if ($pstats['overdue']): ?>
                <li class="danger"><i class="bi bi-exclamation-circle"></i> ล่าช้า <?= $pstats['overdue'] ?> ตู้</li>
              <?php endif; ?>
            <?php endif; ?>
            <li><i class="bi bi-calendar-plus"></i> สร้างเมื่อ <?= e(format_date_dmy($p['created_at'])) ?></li>
            <li><i class="bi bi-calendar-check"></i> แก้ไขล่าสุด <?= e(format_date_dmy($p['updated_at'])) ?></li>
          </ul>
        </div>
      </div>
    </div>

    <?php if ($panels): ?>
    <!-- Status distribution mini chart -->
    <div class="panel mt-2">
      <div class="panel-head"><i class="bi bi-bar-chart-steps"></i> สถานะตู้ทั้งหมด</div>
      <div class="panel-body">
        <div class="panel-minicards">
          <?php foreach (panel_status_labels() as $sk => $sl):
            $cnt = $pstats['count'][$sk] ?? 0;
            if (!$cnt) continue; ?>
            <div class="pmc">
              <span class="n" style="color:<?= panel_status_color($sk) ?>"><?= $cnt ?></span>
              <span class="l"><?= e($sl) ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
    <?php endif; ?>
  </div><!-- /tab-overview -->


  <!-- ============================================================ -->
  <!-- TAB: รายการตู้ (Cabinet List)                                -->
  <!-- ============================================================ -->
  <div class="tab-pane fade" id="tab-cabinets" role="tabpanel">

    <!-- Filter + Toolbar bar -->
    <div class="d-flex gap-2 align-items-center flex-wrap mb-2">
      <div class="fb-search" style="min-width:200px;flex:1">
        <i class="bi bi-search"></i>
        <input type="text" class="form-control" id="pvSearch" placeholder="ค้นหา Panel No. หรือชื่อตู้...">
      </div>
      <?php if (count($filterGroups) > 1): ?>
        <select class="form-select" style="width:auto;min-width:130px" id="pvGroupFilter">
          <option value="">ทุก Group</option>
          <?php foreach ($filterGroups as $fg): ?>
            <option value="<?= e($fg) ?>"><?= e($fg) ?></option>
          <?php endforeach; ?>
        </select>
      <?php endif; ?>
      <select class="form-select" style="width:auto;min-width:140px" id="pvStatusFilter">
        <option value="">ทุกสถานะ</option>
        <?php foreach ($filterStatuses as $fs): ?>
          <option value="<?= e($fs) ?>"><?= e(panel_status_label($fs)) ?></option>
        <?php endforeach; ?>
      </select>
      <a href="panel_add.php?project_id=<?= (int)$id ?>" class="btn btn-success btn-sm ms-auto">
        <i class="bi bi-plus-lg me-1"></i>เพิ่มตู้
      </a>
    </div>

    <!-- Bulk Action Bar (hidden until panels are selected) -->
    <div id="bulkBar" class="d-none mb-2">
      <form method="post" id="bulkForm">
        <input type="hidden" name="id" value="<?= $id ?>">
        <input type="hidden" name="_bulk_action" id="bulkActionInput" value="">
        <div class="d-flex align-items-center gap-2 flex-wrap p-2 rounded" style="background:#EFF6FF;border:1px solid #BFDBFE">
          <span class="fw-600 small text-primary" id="bulkCount">0 ตู้ที่เลือก</span>
          <button type="button" class="btn btn-outline-secondary btn-sm" id="bulkClearBtn">
            <i class="bi bi-x"></i> ล้างการเลือก
          </button>
          <div class="vr mx-1"></div>
          <!-- Auto Tasks -->
          <div class="d-flex align-items-center gap-1">
            <select name="bulk_template" class="form-select form-select-sm" style="width:auto">
              <option value="auto">Auto Template</option>
              <?php foreach (panel_task_templates() as $k => $t): ?>
                <option value="<?= e($k) ?>"><?= e($t['label']) ?></option>
              <?php endforeach; ?>
            </select>
            <select name="bulk_conflict" class="form-select form-select-sm" style="width:auto">
              <option value="add_missing">เพิ่มที่ขาด</option>
              <option value="replace">แทนที่ทั้งหมด</option>
              <option value="skip">ข้ามถ้ามีแล้ว</option>
            </select>
            <button type="button" class="btn btn-primary btn-sm" onclick="submitBulk('bulk_auto_tasks')">
              <i class="bi bi-diagram-3"></i> สร้าง Tasks
            </button>
          </div>
          <div class="vr mx-1"></div>
          <!-- Update Group -->
          <div class="d-flex align-items-center gap-1">
            <input type="text" name="bulk_group" class="form-control form-control-sm" placeholder="Group (A/B/C...)" style="width:110px">
            <button type="button" class="btn btn-secondary btn-sm" onclick="submitBulk('bulk_update_group')">
              <i class="bi bi-tag"></i> เปลี่ยน Group
            </button>
          </div>
          <div class="vr mx-1"></div>
          <!-- Update Date -->
          <div class="d-flex align-items-center gap-1">
            <input type="date" name="bulk_date" class="form-control form-control-sm" style="width:140px">
            <button type="button" class="btn btn-secondary btn-sm" onclick="submitBulk('bulk_update_date')">
              <i class="bi bi-calendar"></i> ตั้งวันส่ง
            </button>
          </div>
          <div class="vr mx-1"></div>
          <!-- Add Task -->
          <div class="d-flex align-items-center gap-1">
            <input type="text" name="bulk_task_name" class="form-control form-control-sm" placeholder="ชื่อ Task..." style="width:140px">
            <button type="button" class="btn btn-secondary btn-sm" onclick="submitBulk('bulk_add_task')">
              <i class="bi bi-plus-square"></i> เพิ่ม Task
            </button>
          </div>
          <div class="vr mx-1"></div>
          <!-- Delete -->
          <button type="button" class="btn btn-danger btn-sm"
                  onclick="if(confirm('ยืนยันลบตู้ที่เลือกทั้งหมด?')) submitBulk('bulk_delete')">
            <i class="bi bi-trash"></i> ลบ
          </button>
        </div>
      </form>
    </div>

    <div class="panel">
      <div class="panel-body p-0">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0 data-table" id="pvTable">
            <thead>
              <tr>
                <th style="width:36px">
                  <input type="checkbox" id="checkAll" class="form-check-input" title="เลือกทั้งหมด">
                </th>
                <th>Panel No.</th>
                <th>ชื่อตู้ / ประเภท</th>
                <th>Group</th>
                <th>สถานะ</th>
                <th style="min-width:120px">Progress</th>
                <th>ผู้รับผิดชอบ</th>
                <th>กำหนดส่ง</th>
                <th class="text-center" style="width:110px">จัดการ</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!$panels): ?>
                <tr><td colspan="9" class="text-center text-muted py-5">
                  <i class="bi bi-inbox display-6 d-block mb-2"></i>
                  ยังไม่มีตู้ในโครงการนี้
                </td></tr>
              <?php else: foreach ($panels as $pn):
                $es    = $pn['eff_status'];
                $sc    = panel_status_color($es);
                $elbl  = panel_effective_label($pn);
                $gc    = $groupMap[trim((string)($pn['delivery_group'] ?? ''))] ?? '#6B7280';
                $grp   = trim((string)($pn['delivery_group'] ?? ''));
                $ovd   = panel_overdue_days($pn);
              ?>
                <tr class="pv-row"
                    data-id="<?= (int)$pn['id'] ?>"
                    data-no="<?= e($pn['panel_no']) ?>"
                    data-name="<?= e($pn['panel_name']) ?>"
                    data-type="<?= e($pn['panel_type'] ?? '') ?>"
                    data-size="<?= e($pn['panel_size'] ?? '') ?>"
                    data-group="<?= e($grp) ?>"
                    data-group-color="<?= e($gc) ?>"
                    data-status="<?= e($es) ?>"
                    data-status-label="<?= e($elbl) ?>"
                    data-status-color="<?= e($sc) ?>"
                    data-db-status="<?= e($pn['status'] ?? 'pending') ?>"
                    data-progress="<?= (int)$pn['progress_percent'] ?>"
                    data-responsible="<?= e($pn['responsible'] ?? '') ?>"
                    data-target="<?= e(format_date_dmy($pn["target_delivery_date"] ?? '')) ?>"
                    data-actual="<?= e(format_date_dmy($pn["actual_delivery_date"] ?? '')) ?>"
                    data-remark="<?= e($pn['remark'] ?? '') ?>">

                  <td onclick="event.stopPropagation()">
                    <input type="checkbox" class="form-check-input pv-check" value="<?= (int)$pn['id'] ?>">
                  </td>
                  <td class="fw-600"><?= e($pn['panel_no']) ?></td>
                  <td>
                    <div class="fw-600"><?= e($pn['panel_name']) ?></div>
                    <div class="text-muted small"><?= e($pn['panel_type'] ?? '') ?><?= !empty($pn['panel_size']) ? ' · ' . e($pn['panel_size']) : '' ?></div>
                  </td>
                  <td>
                    <?php if ($grp): ?>
                      <span class="pv-grp" style="background:<?= e($gc) ?>"><?= e($grp) ?></span>
                    <?php else: ?>
                      <span class="text-muted">—</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <span id="panel-st-<?= (int)$pn['id'] ?>" class="pv-st" style="background:<?= e($sc) ?>"><?= e($elbl) ?></span>
                  </td>
                  <td>
                    <div class="pv-prog">
                      <div class="pv-prog-bar">
                        <div id="panel-prog-fill-<?= (int)$pn['id'] ?>" class="pv-prog-fill" style="width:<?= (int)$pn['progress_percent'] ?>%;background:<?= e($sc) ?>"></div>
                      </div>
                      <span id="panel-prog-pct-<?= (int)$pn['id'] ?>" class="pv-prog-pct"><?= (int)$pn['progress_percent'] ?>%</span>
                    </div>
                  </td>
                  <td class="small text-muted"><?= e($pn['responsible'] ?: '—') ?></td>
                  <td class="small">
                    <?= e(format_date_dmy($pn["target_delivery_date"] ?? '')) ?>
                    <?php if ($ovd > 0): ?>
                      <span class="badge bg-danger ms-1" style="font-size:9.5px">เลย <?= $ovd ?>ว</span>
                    <?php endif; ?>
                  </td>
                  <td class="text-center text-nowrap" onclick="event.stopPropagation()">
                    <?php $pnTaskCount = count($panelTaskMap[(int)$pn['id']] ?? []); ?>
                    <?php if ($pnTaskCount > 0): ?>
                      <button type="button" class="btn btn-sm btn-light me-1 pv-tasks-toggle"
                              data-panel-id="<?= (int)$pn['id'] ?>" title="ดูขั้นตอนงาน">
                        <i class="bi bi-list-task"></i>
                        <span class="badge bg-secondary ms-1" style="font-size:9px"><?= $pnTaskCount ?></span>
                      </button>
                    <?php endif; ?>
                    <a href="panel_edit.php?id=<?= (int)$pn['id'] ?>" class="btn btn-sm btn-light" title="แก้ไข"><i class="bi bi-pencil"></i></a>
                    <a href="panel_delete.php?id=<?= (int)$pn['id'] ?>"
                       class="btn btn-sm btn-light text-danger" title="ลบ"
                       onclick="return confirm('ยืนยันการลบตู้ <?= e($pn['panel_no']) ?>?')"><i class="bi bi-trash"></i></a>
                  </td>
                </tr>

                <?php if (!empty($panelTaskMap[(int)$pn['id']])): ?>
                <tr class="pv-tasks-row d-none" id="tasks-row-<?= (int)$pn['id'] ?>">
                  <td colspan="9" class="p-0">
                    <div class="px-3 py-2" style="background:#f8fafc;border-top:1px solid #e5e7eb">
                      <div class="d-flex align-items-center gap-2 mb-2">
                        <span class="fw-600 small text-muted">
                          <i class="bi bi-list-task me-1"></i>ขั้นตอนงาน — <?= e($pn['panel_no']) ?>
                        </span>
                        <a href="panel_edit.php?id=<?= (int)$pn['id'] ?>" class="btn btn-sm btn-outline-secondary ms-auto" style="font-size:11px">
                          <i class="bi bi-pencil-square"></i> จัดการ Tasks
                        </a>
                      </div>
                      <div class="table-responsive">
                        <table class="table table-sm mb-0" style="font-size:12px">
                          <thead>
                            <tr class="table-light">
                              <th style="width:24px">#</th>
                              <th>ขั้นตอน</th>
                              <th style="width:100px">แผนเริ่ม</th>
                              <th style="width:100px">แผนจบ</th>
                              <th style="width:90px">เริ่มจริง</th>
                              <th style="width:90px">จบจริง</th>
                              <th style="width:120px">สถานะ</th>
                              <th style="width:70px" class="text-center">Progress</th>
                              <th style="width:110px" class="text-center">ล่าช้า</th>
                            </tr>
                          </thead>
                          <tbody>
                            <?php
                              $todayStr = date('Y-m-d');
                              $tColors  = ['pending'=>'#9CA3AF','in_progress'=>'#FF7A00','completed'=>'#059669','overdue'=>'#EF4444'];
                            ?>
                            <?php foreach ($panelTaskMap[(int)$pn['id']] as $ti => $task): ?>
                            <?php
                              $tRawStatus  = $task['status'] ?? 'pending';
                              $tDelay      = get_task_delay_info($task);
                              $tIsOverdue  = ($tDelay['type'] === 'overdue');
                              $tStatus     = $tIsOverdue ? 'overdue' : $tRawStatus;
                              $tColor      = $tColors[$tStatus] ?? '#9CA3AF';
                              $tProg       = task_status_to_progress($tIsOverdue ? 'in_progress' : $tRawStatus);
                              $selectVal   = in_array($tRawStatus, ['pending','in_progress','completed'], true) ? $tRawStatus : 'pending';
                              $tid         = (int)$task['id'];
                              $tPanelId    = (int)$pn['id'];
                              $tEditable   = is_task_plan_editable($task);
                            ?>
                            <tr id="task-tr-<?= $tid ?>"
                                data-task-id="<?= $tid ?>"
                                data-panel-id="<?= $tPanelId ?>"
                                style="<?= $tIsOverdue ? 'background:#FEF2F2' : '' ?>">
                              <td class="text-muted small"><?= $ti + 1 ?></td>
                              <td class="fw-600" style="font-size:12px"><?= e($task['task_name']) ?></td>

                              <!-- แผนเริ่ม -->
                              <td class="small">
                                <span id="task-planned-start-<?= $tid ?>" class="text-muted">
                                  <?= e(format_date_dmy($task['start_date'] ?? '')) ?>
                                </span>
                                <?php if ($tEditable): ?>
                                  <button type="button" class="btn btn-xs btn-link p-0 ms-1 task-date-edit-btn"
                                          style="font-size:10px;color:#9CA3AF"
                                          data-task-id="<?= $tid ?>"
                                          data-panel-id="<?= $tPanelId ?>"
                                          data-task-name="<?= e($task['task_name']) ?>"
                                          data-task-status="<?= e($tRawStatus) ?>"
                                          data-planned-start="<?= e($task['start_date'] ?? '') ?>"
                                          data-planned-finish="<?= e($task['due_date'] ?? '') ?>"
                                          data-duration="<?= (int)($task['duration_days'] ?? 1) ?>"
                                          title="แก้ไขวันแผน">
                                    <i class="bi bi-pencil"></i>
                                  </button>
                                <?php else: ?>
                                  <i class="bi bi-lock-fill ms-1" style="font-size:9px;color:#D1D5DB" title="แก้ไขไม่ได้ (เริ่มงานแล้ว)"></i>
                                <?php endif; ?>
                              </td>

                              <!-- แผนจบ -->
                              <td class="small">
                                <span id="task-planned-finish-<?= $tid ?>" class="text-muted">
                                  <?= e(format_date_dmy($task['due_date'] ?? '')) ?>
                                </span>
                                <?php if (!$tEditable && !empty($task['due_date'])): ?>
                                  <i class="bi bi-lock-fill ms-1" style="font-size:9px;color:#D1D5DB"></i>
                                <?php endif; ?>
                              </td>

                              <td class="small"><span id="task-actual-start-<?= $tid ?>" class="text-muted"><?= e(format_date_dmy($task['actual_start_date'] ?? '')) ?></span></td>
                              <td class="small"><span id="task-actual-finish-<?= $tid ?>" class="text-muted"><?= e(format_date_dmy($task['completed_date'] ?? '')) ?></span></td>

                              <!-- สถานะ -->
                              <td>
                                <select id="task-status-sel-<?= $tid ?>"
                                        class="form-select form-select-sm task-status-sel"
                                        data-task-id="<?= $tid ?>"
                                        data-panel-id="<?= $tPanelId ?>"
                                        data-prev-value="<?= $selectVal ?>"
                                        data-task-name="<?= e($task['task_name']) ?>"
                                        style="width:118px;font-size:11px;border-color:<?= $tColor ?>;color:<?= $tColor ?>;font-weight:600"
                                        onchange="taskUpdateStatus(this)">
                                  <option value="pending"     <?= $selectVal === 'pending'     ? 'selected' : '' ?>>รอเริ่มงาน</option>
                                  <option value="in_progress" <?= $selectVal === 'in_progress' ? 'selected' : '' ?>>เริ่มงานแล้ว</option>
                                  <option value="completed"   <?= $selectVal === 'completed'   ? 'selected' : '' ?>>เสร็จแล้ว</option>
                                </select>
                              </td>

                              <!-- Progress -->
                              <td class="text-center">
                                <div class="d-flex flex-column align-items-center gap-1">
                                  <span id="task-prog-pct-<?= $tid ?>" style="font-size:11px;font-weight:600;color:<?= $tColor ?>"><?= $tProg ?>%</span>
                                  <div style="width:48px;height:5px;background:#e5e7eb;border-radius:3px">
                                    <div id="task-prog-fill-<?= $tid ?>" style="width:<?= $tProg ?>%;height:5px;background:<?= $tColor ?>;border-radius:3px"></div>
                                  </div>
                                </div>
                              </td>

                              <!-- ล่าช้า -->
                              <td id="task-days-late-<?= $tid ?>" class="text-center" style="white-space:nowrap">
                                <?= task_delay_badge_html($task) ?>
                              </td>
                            </tr>
                            <?php endforeach; ?>
                          </tbody>
                        </table>
                      </div>
                    </div>
                  </td>
                </tr>
                <?php endif; ?>

              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div><!-- /tab-cabinets -->


  <!-- ============================================================ -->
  <!-- TAB: TIMELINE                                                 -->
  <!-- ============================================================ -->
  <div class="tab-pane fade" id="tab-timeline" role="tabpanel">
    <?php if (!$panels): ?>
      <div class="panel"><div class="panel-body text-center text-muted py-5">
        <i class="bi bi-bar-chart-gantt display-6 d-block mb-2"></i>ยังไม่มีตู้
      </div></div>
    <?php else: ?>
    <div class="panel">
      <div class="panel-head">
        <i class="bi bi-bar-chart-gantt"></i> Timeline ตู้
        <span class="ms-auto">
          <span class="pv-gantt-legend d-inline-flex">
            <span class="pv-gantt-legend-dot"></span> วันนี้ (<?= e(format_date_dmy(date("Y-m-d"))) ?>)
          </span>
        </span>
      </div>
      <div class="panel-body" style="overflow-x:auto">
        <div style="min-width:600px">
          <!-- Axis -->
          <div class="pv-gantt-ax">
            <div class="pv-gl">ตู้</div>
            <div class="pv-gt">
              <?php foreach ($tlMonths as $mo): ?>
                <div class="pv-gmonth" style="left:<?= round($mo['pos'], 2) ?>%"><?= e($mo['label']) ?></div>
              <?php endforeach; ?>
            </div>
          </div>
          <!-- Rows -->
          <div class="pv-gantt-rows">
            <?php foreach ($panels as $pn):
              $es       = $pn['eff_status'];
              $gc       = $groupMap[trim((string)($pn['delivery_group'] ?? ''))] ?? null;
              $sc       = panel_status_color($es);
              $bc       = $gc ?? $sc;
              $bcSoft   = hex_tint($bc, 0.22);
              $hasTgt   = !empty($pn['target_delivery_date']);
              $pStart   = !empty($p['start_date']) ? strtotime($p['start_date']) : $tlStart;
              $pEnd     = $hasTgt ? strtotime($pn['target_delivery_date']) : null;
              $barLeft  = $hasTgt ? round($tlPos($pStart), 2) : null;
              $barRight = $hasTgt ? round($tlPos($pEnd), 2) : null;
              $barWidth = $hasTgt ? max(1.5, $barRight - $barLeft) : 0;
              $prog     = (int)$pn['progress_percent'];
              $grp      = trim((string)($pn['delivery_group'] ?? ''));
            ?>
            <div class="pv-gantt-row">
              <div class="pv-gantt-lbl" title="<?= e($pn['panel_no'] . ' — ' . $pn['panel_name']) ?>">
                <?php if ($grp): ?>
                  <span class="pv-grp" style="background:<?= e($gc ?? '#6B7280') ?>;font-size:10px;padding:1px 6px"><?= e($grp) ?></span>
                <?php endif; ?>
                <?= e($pn['panel_no']) ?>
                <span class="sub"><?= e($pn['panel_name']) ?></span>
              </div>
              <div class="pv-gantt-track">
                <!-- Month gridlines -->
                <?php foreach ($tlMonths as $mo): ?>
                  <div class="pv-gline" style="left:<?= round($mo['pos'], 2) ?>%"></div>
                <?php endforeach; ?>
                <!-- Today line -->
                <?php if ($todayPct >= 0 && $todayPct <= 100): ?>
                  <div class="pv-gtoday" style="left:<?= round($todayPct, 2) ?>%"></div>
                <?php endif; ?>
                <!-- Bar -->
                <?php if ($hasTgt): ?>
                  <div class="pv-gbar"
                       style="left:<?= $barLeft ?>%;width:<?= $barWidth ?>%;--bc:<?= e($bc) ?>;--bc-soft:<?= e($bcSoft) ?>">
                    <div class="pv-gfill" style="width:<?= $prog ?>%"></div>
                    <?php if ($barWidth > 12): ?>
                      <div class="pv-gpct"><?= $prog ?>%</div>
                    <?php endif; ?>
                  </div>
                <?php endif; ?>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>
    <?php endif; ?>
  </div><!-- /tab-timeline -->


  <!-- ============================================================ -->
  <!-- TAB: ส่งมอบ (Delivery)                                       -->
  <!-- ============================================================ -->
  <div class="tab-pane fade" id="tab-delivery" role="tabpanel">
    <?php if ($groupStats): ?>
    <div class="pv-dg-grid">
      <?php foreach ($groupStats as $gName => $gs): ?>
        <div class="pv-dg-card" style="--gc:<?= e($gs['color']) ?>">
          <div class="pv-dg-name">
            <span class="pv-grp" style="background:<?= e($gs['color']) ?>"><?= e($gName) ?></span>
          </div>
          <div class="pv-dg-row">
            <span>ตู้ทั้งหมด</span>
            <span class="v"><?= $gs['total'] ?> ใบ</span>
          </div>
          <div class="pv-dg-row">
            <span>ส่งมอบแล้ว</span>
            <span class="v" style="color:var(--success-color)"><?= $gs['delivered'] ?> ใบ</span>
          </div>
          <?php if ($gs['overdue']): ?>
          <div class="pv-dg-row">
            <span>ล่าช้า</span>
            <span class="v" style="color:var(--danger-color)"><?= $gs['overdue'] ?> ใบ</span>
          </div>
          <?php endif; ?>
          <div class="pv-dg-row">
            <span>Progress</span>
            <span class="v"><?= $gs['total'] > 0 ? round($gs['delivered'] / $gs['total'] * 100) : 0 ?>%</span>
          </div>
          <?php if ($gs['maxDate']): ?>
          <div class="pv-dg-row">
            <span>วันส่งมอบสุดท้าย</span>
            <span class="v small"><?= e(format_date_dmy($gs["maxDate"])) ?></span>
          </div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- Delivery Table -->
    <div class="panel mt-4">
      <div class="panel-head"><i class="bi bi-table"></i> รายการส่งมอบตามตู้</div>
      <div class="panel-body p-0">
        <div class="table-responsive">
          <table class="table data-table mb-0">
            <thead>
              <tr>
                <th>Panel No.</th>
                <th>ชื่อตู้</th>
                <th>Group</th>
                <th>กำหนดส่ง</th>
                <th>ส่งจริง</th>
                <th>สถานะ</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($panels as $pn):
                $es = $pn['eff_status'];
                $sc = panel_status_color($es);
                $grp = trim((string)($pn['delivery_group'] ?? ''));
                $gc  = $groupMap[$grp] ?? '#6B7280';
                $ovd = panel_overdue_days($pn);
              ?>
                <tr>
                  <td class="fw-600"><?= e($pn['panel_no']) ?></td>
                  <td><?= e($pn['panel_name']) ?></td>
                  <td><?php if ($grp): ?><span class="pv-grp" style="background:<?= e($gc) ?>"><?= e($grp) ?></span><?php else: ?>—<?php endif; ?></td>
                  <td class="small">
                    <?= e(format_date_dmy($pn["target_delivery_date"] ?? '')) ?>
                    <?php if ($ovd > 0): ?><span class="badge bg-danger ms-1" style="font-size:9px">เลย <?= $ovd ?>ว</span><?php endif; ?>
                  </td>
                  <td class="small"><?= e(format_date_dmy($pn["actual_delivery_date"] ?? '')) ?></td>
                  <td><span class="pv-st" style="background:<?= e($sc) ?>"><?= e(panel_status_label($es)) ?></span></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <?php elseif ($panels): ?>
      <div class="alert alert-info"><i class="bi bi-info-circle me-2"></i>ตู้ในโครงการนี้ยังไม่ได้กำหนด Delivery Group</div>
    <?php else: ?>
      <div class="panel"><div class="panel-body text-center text-muted py-5">ยังไม่มีข้อมูลตู้</div></div>
    <?php endif; ?>
  </div><!-- /tab-delivery -->


  <!-- ============================================================ -->
  <!-- TAB: รายงาน                                                   -->
  <!-- ============================================================ -->
  <div class="tab-pane fade" id="tab-reports" role="tabpanel">
    <div class="grid-3">
      <div class="panel">
        <div class="panel-head"><i class="bi bi-image"></i> Overview Report</div>
        <div class="panel-body text-center py-4">
          <i class="bi bi-file-image display-4 d-block mb-3 text-muted"></i>
          <p class="text-muted mb-3">สร้างรายงานภาพ PNG สรุปสถานะโครงการ พร้อม Gantt Chart</p>
          <a href="project_report_select.php?project_id=<?= (int)$p['id'] ?>" class="btn btn-info text-white">
            <i class="bi bi-image me-2"></i>Generate Overview Report
          </a>
        </div>
      </div>
      <div class="panel">
        <div class="panel-head"><i class="bi bi-printer"></i> พิมพ์รายงาน</div>
        <div class="panel-body text-center py-4">
          <i class="bi bi-printer display-4 d-block mb-3 text-muted"></i>
          <p class="text-muted mb-3">พิมพ์รายงานสรุปโครงการในรูปแบบ A4</p>
          <a href="print_report.php?id=<?= (int)$p['id'] ?>" target="_blank" class="btn btn-outline-dark">
            <i class="bi bi-printer me-2"></i>พิมพ์รายงาน
          </a>
        </div>
      </div>
      <div class="panel">
        <div class="panel-head"><i class="bi bi-clipboard-data"></i> สรุปโครงการ</div>
        <div class="panel-body">
          <table class="info-table w-100">
            <tr><td class="il">Project Health</td>
              <td class="iv"><span class="pv-health <?= $hc['cls'] ?>"><?= $hc['emoji'] ?> <?= $hc['label'] ?></span></td></tr>
            <tr><td class="il">Progress รวม</td>
              <td class="iv fw-700"><?= $panelProg ?>%</td></tr>
            <tr><td class="il">ตู้ทั้งหมด</td>
              <td class="iv"><?= $pstats['total'] ?> ใบ</td></tr>
            <tr><td class="il">ส่งมอบแล้ว</td>
              <td class="iv" style="color:var(--success-color)"><?= $pstats['delivered'] ?> ใบ</td></tr>
            <?php if ($pstats['overdue']): ?>
            <tr><td class="il">ล่าช้า</td>
              <td class="iv" style="color:var(--danger-color)"><?= $pstats['overdue'] ?> ใบ</td></tr>
            <?php endif; ?>
            <tr><td class="il">กำหนดส่ง</td>
              <td class="iv"><?= e(format_date_dmy($p["due_date"])) ?></td></tr>
            <tr><td class="il">มูลค่างาน</td>
              <td class="iv"><?= money2($p['amount']) ?> ฿</td></tr>
          </table>
        </div>
      </div>
    </div>
  </div><!-- /tab-reports -->


  <!-- ============================================================ -->
  <!-- TAB: Activity Log                                             -->
  <!-- ============================================================ -->
  <div class="tab-pane fade" id="tab-activity" role="tabpanel">
    <?php if (!$activities): ?>
      <div class="panel"><div class="panel-body text-center text-muted py-5">
        <i class="bi bi-clock-history display-6 d-block mb-2"></i>ยังไม่มีประวัติกิจกรรม
      </div></div>
    <?php else: ?>
    <div class="panel">
      <div class="panel-head"><i class="bi bi-clock-history"></i> ประวัติกิจกรรม</div>
      <div class="panel-body">
        <ul class="pv-act-list">
          <?php foreach ($activities as $act):
            $aAction = (string)($act['action'] ?? '');
            $conf    = $actConf[$aAction] ?? ['ico' => 'bi-info-circle', 'cls' => '', 'label' => $aAction];
          ?>
            <li class="pv-act-item">
              <div class="pv-act-ico <?= e($conf['cls']) ?>">
                <i class="bi <?= e($conf['ico']) ?>"></i>
              </div>
              <div class="pv-act-body">
                <div class="pv-act-detail">
                  <strong><?= e($conf['label']) ?></strong>
                  <?php if ($act['detail']): ?>
                    — <?= e($act['detail']) ?>
                  <?php endif; ?>
                </div>
                <div class="pv-act-meta">
                  <i class="bi bi-clock me-1"></i>
                  <?= e(format_date_dmy(substr((string)($act['created_at'] ?? ''), 0, 10))) ?>
                  <?= e(substr((string)($act['created_at'] ?? ''), 11, 5)) ?>
                  <?php if (!empty($act['actor']) && $act['actor'] !== 'system'): ?>
                    &nbsp;·&nbsp; <i class="bi bi-person me-1"></i><?= e($act['actor']) ?>
                  <?php endif; ?>
                </div>
              </div>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>
    <?php endif; ?>
  </div><!-- /tab-activity -->

</div><!-- /tab-content -->


<!-- ===== Cabinet Detail Drawer ===== -->
<div class="spa-drawer" id="pvDrawer">
  <div class="drawer-backdrop" id="pvBackdrop"></div>
  <div class="drawer-panel">
    <div class="drawer-header">
      <div>
        <div style="font-size:10.5px;letter-spacing:1.5px;color:#9CA3AF;text-transform:uppercase" id="drNo">—</div>
        <h5 class="mb-0" id="drName" style="max-width:340px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">—</h5>
      </div>
      <button class="btn-close" id="pvDrawerClose" aria-label="Close"></button>
    </div>
    <div class="drawer-body" id="drBody">
      <!-- populated by JS -->
    </div>
  </div>
</div>

<!-- Task Planned Date Edit Modal (AJAX) -->
<div class="modal fade" id="taskDateModal" tabindex="-1">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <input type="hidden" id="tdTaskId">
      <input type="hidden" id="tdPanelId">
      <input type="hidden" id="tdTaskCurrentStatus">
      <div class="modal-header py-2">
        <h6 class="modal-title"><i class="bi bi-calendar-range me-1"></i>แก้ไขวันแผน</h6>
        <button type="button" class="btn-close btn-sm" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="text-muted small mb-2 fw-600" id="tdTaskName"></div>
        <div id="tdLocked" class="alert alert-danger py-2 small d-none">
          <i class="bi bi-lock-fill me-1"></i>Task นี้เริ่มงานแล้ว — ไม่สามารถแก้ไขวันแผนได้
        </div>
        <div id="tdFields">
          <div class="row g-2 mb-2">
            <div class="col">
              <label class="form-label small fw-600 mb-1">แผนเริ่ม</label>
              <input type="date" id="tdPlannedStart" class="form-control form-control-sm">
            </div>
            <div class="col">
              <label class="form-label small fw-600 mb-1">แผนจบ</label>
              <input type="date" id="tdPlannedFinish" class="form-control form-control-sm">
            </div>
          </div>
          <div class="mb-2">
            <label class="form-label small fw-600 mb-1">ระยะเวลา (วัน)</label>
            <input type="number" id="tdDuration" class="form-control form-control-sm" min="1" style="width:90px">
            <div class="form-text" style="font-size:10px">แก้ Duration แล้วระบบจะคำนวณแผนจบให้อัตโนมัติ</div>
          </div>
          <div class="form-check mt-2">
            <input type="checkbox" id="tdRecalc" class="form-check-input" value="1">
            <label class="form-check-label small" for="tdRecalc">
              เลื่อน Tasks ถัดไปตาม Duration อัตโนมัติ
            </label>
          </div>
        </div>
      </div>
      <div class="modal-footer py-2">
        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
        <button type="button" class="btn btn-sm btn-primary" id="tdSaveBtn">บันทึก</button>
      </div>
    </div>
  </div>
</div>

<!-- Task Status Confirm Modal -->
<div class="modal fade" id="taskStatusConfirmModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header py-2">
        <h6 class="modal-title"><i class="bi bi-arrow-repeat me-1"></i>ยืนยันการเปลี่ยนสถานะ</h6>
      </div>
      <div class="modal-body py-3 text-center">
        <div id="scTaskName" class="fw-600 mb-3" style="font-size:13px"></div>
        <div class="d-flex align-items-center justify-content-center gap-2">
          <span id="scFromBadge" class="badge" style="font-size:11px;padding:5px 10px"></span>
          <i class="bi bi-arrow-right text-muted"></i>
          <span id="scToBadge"   class="badge" style="font-size:11px;padding:5px 10px"></span>
        </div>
      </div>
      <div class="modal-footer py-2 justify-content-center gap-2">
        <button type="button" class="btn btn-sm btn-secondary" id="scCancelBtn" style="min-width:80px">ยกเลิก</button>
        <button type="button" class="btn btn-sm btn-primary"   id="scConfirmBtn" style="min-width:80px">ยืนยัน</button>
      </div>
    </div>
  </div>
</div>

<script>
(function () {
  'use strict';

  /* ── Constants ── */
  const PROJECT_ID = <?= (int)$id ?>;

  /* ── Escape helper for innerHTML ── */
  function esc(s) {
    const d = document.createElement('div');
    d.textContent = String(s ?? '');
    return d.innerHTML;
  }

  /* ── Filter ── */
  const pvSearch = document.getElementById('pvSearch');
  const pvGroup  = document.getElementById('pvGroupFilter');
  const pvStatus = document.getElementById('pvStatusFilter');

  function applyFilter() {
    const s  = (pvSearch?.value ?? '').toLowerCase();
    const g  = (pvGroup?.value  ?? '').toLowerCase();
    const st = (pvStatus?.value ?? '').toLowerCase();
    document.querySelectorAll('#pvTable tbody .pv-row').forEach(function (row) {
      const text  = row.textContent.toLowerCase();
      const rGrp  = (row.dataset.group  ?? '').toLowerCase();
      const rSt   = (row.dataset.status ?? '').toLowerCase();
      const show  = (!s || text.includes(s)) && (!g || rGrp === g) && (!st || rSt === st);
      row.style.display = show ? '' : 'none';
      /* also hide/show the tasks sub-row */
      const pid     = row.dataset.id;
      const taskRow = pid ? document.getElementById('tasks-row-' + pid) : null;
      if (taskRow && !show) taskRow.style.display = 'none';
      else if (taskRow && show) taskRow.style.display = '';
    });
  }

  pvSearch?.addEventListener('input', applyFilter);
  pvGroup?.addEventListener('change', applyFilter);
  pvStatus?.addEventListener('change', applyFilter);

  /* ── Drawer ── */
  const drawer   = document.getElementById('pvDrawer');
  const backdrop = document.getElementById('pvBackdrop');
  const drClose  = document.getElementById('pvDrawerClose');
  const drNo     = document.getElementById('drNo');
  const drName   = document.getElementById('drName');
  const drBody   = document.getElementById('drBody');

  function openDrawer(d) {
    const id          = d.id;
    const statusColor = d.statusColor;
    const groupColor  = d.groupColor;
    const progress    = parseInt(d.progress, 10) || 0;

    const grpBadge = d.group
      ? '<span class="pv-grp me-1" style="background:' + esc(groupColor) + '">' + esc(d.group) + '</span>'
      : '';
    const stBadge = '<span class="pv-st" style="background:' + esc(statusColor) + '">' + esc(d.statusLabel) + '</span>';

    const remarkHtml = d.remark
      ? '<div class="alert alert-light p-2 mt-2" style="font-size:13.5px"><small class="text-muted d-block mb-1">หมายเหตุ</small>' + esc(d.remark) + '</div>'
      : '';

    drNo.textContent   = d.no   || '—';
    drName.textContent = d.name || '—';
    drBody.innerHTML   =
      '<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">' + grpBadge + stBadge + '</div>'
      + '<div class="pv-prog mb-1">'
        + '<div class="pv-prog-bar"><div class="pv-prog-fill" style="width:' + progress + '%;background:' + esc(statusColor) + '"></div></div>'
        + '<span class="pv-prog-pct">' + progress + '%</span>'
      + '</div>'
      + '<hr class="my-3">'
      + '<table class="table table-sm table-borderless mb-0" style="font-size:14px">'
        + '<tr><td class="text-muted ps-0" style="width:42%">ประเภท</td><td class="fw-600">' + (esc(d.type) || '—') + '</td></tr>'
        + '<tr><td class="text-muted ps-0">ขนาด</td><td>' + (esc(d.size) || '—') + '</td></tr>'
        + '<tr><td class="text-muted ps-0">ผู้รับผิดชอบ</td><td>' + (esc(d.responsible) || '—') + '</td></tr>'
        + '<tr><td class="text-muted ps-0">กำหนดส่ง</td><td>' + (esc(d.target) || '—') + '</td></tr>'
        + '<tr><td class="text-muted ps-0">ส่งจริง</td><td>' + (esc(d.actual) || '—') + '</td></tr>'
      + '</table>'
      + remarkHtml
      + '<div class="d-flex gap-2 mt-3 pt-2 border-top">'
        + '<a href="panel_edit.php?id=' + id + '" class="btn btn-primary btn-sm flex-fill"><i class="bi bi-pencil me-1"></i>แก้ไขตู้</a>'
        + '<a href="panel_delete.php?id=' + id + '" class="btn btn-outline-danger btn-sm"'
          + ' onclick="return confirm(\'ยืนยันการลบตู้?\')"><i class="bi bi-trash"></i></a>'
      + '</div>';

    drawer.classList.add('open');
    document.body.classList.add('spa-drawer-open');
  }

  function closeDrawer() {
    drawer.classList.remove('open');
    document.body.classList.remove('spa-drawer-open');
  }

  drClose?.addEventListener('click', closeDrawer);
  backdrop?.addEventListener('click', closeDrawer);
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeDrawer();
  });

  /* ── Row click → open drawer ── */
  document.querySelectorAll('.pv-row').forEach(function (row) {
    row.addEventListener('click', function (e) {
      if (e.target.closest('a, button')) return;
      openDrawer(this.dataset);
    });
  });

  /* Task status change via AJAX — with confirmation modal */
  (function () {
    var ST_LABEL = { pending: 'รอเริ่มงาน', in_progress: 'เริ่มงานแล้ว', completed: 'เสร็จแล้ว' };
    var ST_COLOR = { pending: '#9CA3AF',    in_progress: '#FF7A00',       completed: '#059669'    };

    var confirmModal = null;
    var pendingSel   = null;
    var pendingNew   = null;

    function getModal() {
      if (!confirmModal) confirmModal = new bootstrap.Modal(document.getElementById('taskStatusConfirmModal'));
      return confirmModal;
    }

    document.getElementById('scConfirmBtn').addEventListener('click', function () {
      if (!pendingSel) return;
      var sel = pendingSel; var newStat = pendingNew;
      pendingSel = null; pendingNew = null;
      getModal().hide();

      sel.disabled = true;
      fetch('api/task_update.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
          action:     'status',
          project_id: PROJECT_ID,
          task_id:    parseInt(sel.dataset.taskId, 10),
          new_status: newStat
        })
      })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (!data.ok) { alert('เกิดข้อผิดพลาด: ' + (data.error || 'unknown')); sel.disabled = false; return; }
        sel.dataset.prevValue = newStat;
        updateTaskRowDom(sel.dataset.taskId, data.task);
        updateCabinetRowDom(sel.dataset.panelId, data.panel);
        sel.disabled = false;
      })
      .catch(function (err) { console.error(err); sel.disabled = false; });
    });

    document.getElementById('scCancelBtn').addEventListener('click', function () {
      if (pendingSel) pendingSel.value = pendingSel.dataset.prevValue;
      pendingSel = null; pendingNew = null;
      getModal().hide();
    });

    window.taskUpdateStatus = function (sel) {
      var prev   = sel.dataset.prevValue;
      var newVal = sel.value;
      if (!prev || prev === newVal) return;

      sel.value = prev; /* revert visually until confirmed */

      document.getElementById('scTaskName').textContent    = sel.dataset.taskName || '';
      document.getElementById('scFromBadge').textContent   = ST_LABEL[prev]   || prev;
      document.getElementById('scFromBadge').style.background = ST_COLOR[prev]   || '#9CA3AF';
      document.getElementById('scToBadge').textContent     = ST_LABEL[newVal] || newVal;
      document.getElementById('scToBadge').style.background   = ST_COLOR[newVal] || '#9CA3AF';

      pendingSel = sel;
      pendingNew = newVal;
      getModal().show();
    };
  }());

  /* ── Restore active tab from ?_tab= param ── */
  /* Deferred: bootstrap.Tab is only available after render_footer() loads bootstrap.bundle.min.js */
  const activeTab = new URLSearchParams(location.search).get('_tab');
  if (activeTab) {
    document.addEventListener('DOMContentLoaded', function () {
      const tabBtn = document.querySelector('[data-bs-target="#tab-' + CSS.escape(activeTab) + '"]');
      if (tabBtn) new bootstrap.Tab(tabBtn).show();
      const url = new URL(location.href);
      url.searchParams.delete('_tab');
      history.replaceState(null, '', url.toString());
    });
  }

  /* ── Bulk action checkboxes ── */
  (function () {
    var checkAll = document.getElementById('checkAll');
    var bulkBar  = document.getElementById('bulkBar');
    var bulkCount = document.getElementById('bulkCount');
    var bulkForm  = document.getElementById('bulkForm');
    var bulkInput = document.getElementById('bulkActionInput');
    var clearBtn  = document.getElementById('bulkClearBtn');

    function getChecked() {
      return Array.from(document.querySelectorAll('.pv-check:checked'));
    }
    function updateBar() {
      var checked = getChecked();
      if (checked.length > 0) {
        bulkBar.classList.remove('d-none');
        bulkCount.textContent = checked.length + ' ตู้ที่เลือก';
      } else {
        bulkBar.classList.add('d-none');
      }
      if (checkAll) {
        var all = document.querySelectorAll('.pv-check');
        checkAll.indeterminate = checked.length > 0 && checked.length < all.length;
        checkAll.checked = checked.length === all.length && all.length > 0;
      }
    }
    document.querySelectorAll('.pv-check').forEach(function (cb) {
      cb.addEventListener('change', updateBar);
    });
    if (checkAll) {
      checkAll.addEventListener('change', function () {
        document.querySelectorAll('.pv-check').forEach(function (cb) {
          var row = cb.closest('tr');
          if (!row || row.style.display === 'none') return;
          cb.checked = checkAll.checked;
        });
        updateBar();
      });
    }
    if (clearBtn) {
      clearBtn.addEventListener('click', function () {
        document.querySelectorAll('.pv-check').forEach(function (cb) { cb.checked = false; });
        if (checkAll) { checkAll.checked = false; checkAll.indeterminate = false; }
        updateBar();
      });
    }
    window.submitBulk = function (action) {
      var checked = getChecked();
      if (!checked.length) return;
      // Append hidden inputs for selected panel IDs
      document.querySelectorAll('input[name="selected_panels[]"]').forEach(function (el) { el.remove(); });
      checked.forEach(function (cb) {
        var inp = document.createElement('input');
        inp.type = 'hidden'; inp.name = 'selected_panels[]'; inp.value = cb.value;
        bulkForm.appendChild(inp);
      });
      bulkInput.value = action;
      bulkForm.submit();
    };
  }());

  /* ── Task rows toggle ── */
  document.querySelectorAll('.pv-tasks-toggle').forEach(function (btn) {
    btn.addEventListener('click', function (e) {
      e.stopPropagation();
      const pid = this.dataset.panelId;
      const tr  = document.getElementById('tasks-row-' + pid);
      if (tr) {
        tr.classList.toggle('d-none');
        this.classList.toggle('active');
      }
    });
  });

  /* ── Auto-expand panel from ?open_panel=N ── */
  /* Deferred: bootstrap.Tab is only available after render_footer() loads bootstrap.bundle.min.js */
  (function () {
    const openPanelId = new URLSearchParams(location.search).get('open_panel');
    if (!openPanelId || openPanelId === '0') return;
    document.addEventListener('DOMContentLoaded', function () {
      const tr = document.getElementById('tasks-row-' + openPanelId);
      if (!tr) return;
      tr.classList.remove('d-none');
      const btn = document.querySelector('.pv-tasks-toggle[data-panel-id="' + openPanelId + '"]');
      if (btn) btn.classList.add('active');
      const cabTab = document.querySelector('[data-bs-target="#tab-cabinets"]');
      if (cabTab) new bootstrap.Tab(cabTab).show();
      setTimeout(function () { tr.scrollIntoView({ behavior: 'smooth', block: 'center' }); }, 300);
    });
  }());

  /* ══════════════════════════════════════════════
   *  AJAX: Task status & date updates (no reload)
   * ══════════════════════════════════════════════ */

  var STATUS_COLORS = {
    pending:     '#9CA3AF',
    in_progress: '#FF7A00',
    completed:   '#059669',
    overdue:     '#EF4444'
  };

  function renderDelayBadge(delay) {
    if (!delay || delay.type === 'on_track' || delay.type === 'no_date') {
      return '<span class="text-muted small">—</span>';
    }
    var icon = {overdue:'🔴', late_done:'🟠', early:'🟢', on_time:'🟢'}[delay.type] || '';
    return '<span style="background:' + esc(delay.color) + ';color:#fff;padding:1px 6px;border-radius:20px;font-size:10px;white-space:nowrap">'
      + icon + ' ' + esc(delay.label) + '</span>';
  }

  function updateTaskRowDom(tid, task) {
    var row = document.getElementById('task-tr-' + tid);
    if (row) row.style.background = task.is_overdue ? '#FEF2F2' : '';

    var sc = task.status_color || STATUS_COLORS[task.status] || '#9CA3AF';

    var sel = document.getElementById('task-status-sel-' + tid);
    if (sel) { sel.value = task.status; sel.style.borderColor = sc; sel.style.color = sc; }

    var pFill = document.getElementById('task-prog-fill-' + tid);
    var pPct  = document.getElementById('task-prog-pct-'  + tid);
    if (pFill) { pFill.style.width = task.progress_percent + '%'; pFill.style.background = sc; }
    if (pPct)  { pPct.textContent = task.progress_percent + '%'; pPct.style.color = sc; }

    var aStart  = document.getElementById('task-actual-start-'  + tid);
    var aFinish = document.getElementById('task-actual-finish-' + tid);
    if (aStart)  aStart.textContent  = task.actual_start_date_dmy  || '—';
    if (aFinish) aFinish.textContent = task.completed_date_dmy     || '—';

    var dlCell = document.getElementById('task-days-late-' + tid);
    if (dlCell) dlCell.innerHTML = task.delay_badge_html || renderDelayBadge(task.delay);

    // Update planned dates + edit button data attrs
    var ps = document.getElementById('task-planned-start-'  + tid);
    var pf = document.getElementById('task-planned-finish-' + tid);
    if (ps && task.start_date_dmy) ps.textContent = task.start_date_dmy;
    if (pf && task.due_date_dmy)   pf.textContent = task.due_date_dmy;
    var editBtn = document.querySelector('.task-date-edit-btn[data-task-id="' + tid + '"]');
    if (editBtn && task.start_date) editBtn.dataset.plannedStart  = task.start_date;
    if (editBtn && task.due_date)   editBtn.dataset.plannedFinish = task.due_date;
    if (editBtn) editBtn.dataset.taskStatus = task.status;
  }

  function updateCabinetRowDom(panelId, panel) {
    var st  = document.getElementById('panel-st-'        + panelId);
    var pf  = document.getElementById('panel-prog-fill-' + panelId);
    var pp  = document.getElementById('panel-prog-pct-'  + panelId);
    var sc  = panel.status_color || '#9CA3AF';

    if (st) { st.style.background = sc; st.textContent = panel.status_label; }
    if (pf) { pf.style.width = panel.progress_percent + '%'; pf.style.background = sc; }
    if (pp) pp.textContent = panel.progress_percent + '%';

    var pvRow = document.querySelector('.pv-row[data-id="' + panelId + '"]');
    if (pvRow) {
      pvRow.dataset.status      = panel.status;
      pvRow.dataset.statusLabel = panel.status_label;
      pvRow.dataset.statusColor = panel.status_color;
      pvRow.dataset.progress    = panel.progress_percent;
    }
  }

  /* ── Task planned-date edit modal ── */
  (function () {
    var modal    = document.getElementById('taskDateModal');
    if (!modal) return;
    var bsModal  = null;
    function getModal() {
      if (!bsModal) bsModal = new bootstrap.Modal(modal);
      return bsModal;
    }
    var fTaskId  = document.getElementById('tdTaskId');
    var fPanelId = document.getElementById('tdPanelId');
    var fStatus  = document.getElementById('tdTaskCurrentStatus');
    var fStart   = document.getElementById('tdPlannedStart');
    var fFinish  = document.getElementById('tdPlannedFinish');
    var fDur     = document.getElementById('tdDuration');
    var titleEl  = document.getElementById('tdTaskName');
    var lockedEl = document.getElementById('tdLocked');
    var fieldsEl = document.getElementById('tdFields');
    var saveBtn  = document.getElementById('tdSaveBtn');

    // Auto-calc finish when start or duration changes
    function recalcFinish() {
      var s   = fStart.value;
      var dur = parseInt(fDur.value, 10) || 1;
      if (!s) return;
      var d = new Date(s);
      d.setDate(d.getDate() + dur - 1);
      var mm = String(d.getMonth()+1).padStart(2,'0');
      var dd = String(d.getDate()).padStart(2,'0');
      fFinish.value = d.getFullYear() + '-' + mm + '-' + dd;
    }
    if (fStart) fStart.addEventListener('change', recalcFinish);
    if (fDur)   fDur.addEventListener('input',  recalcFinish);

    document.querySelectorAll('.task-date-edit-btn').forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        e.stopPropagation();
        var curStatus = this.dataset.taskStatus || 'pending';
        var editable  = (curStatus === 'pending');
        fTaskId.value   = this.dataset.taskId      || '';
        fPanelId.value  = this.dataset.panelId     || '';
        fStatus.value   = curStatus;
        fStart.value    = this.dataset.plannedStart  || '';
        fFinish.value   = this.dataset.plannedFinish || '';
        if (fDur) fDur.value = this.dataset.duration || '1';
        titleEl.textContent = this.dataset.taskName || 'Task';

        if (lockedEl) lockedEl.classList.toggle('d-none', editable);
        if (fieldsEl) fieldsEl.classList.toggle('d-none', !editable);
        if (saveBtn)  saveBtn.classList.toggle('d-none', !editable);
        getModal().show();
      });
    });

    if (saveBtn) {
      saveBtn.addEventListener('click', function () {
        var taskId  = fTaskId.value;
        var panelId = fPanelId.value;
        var start   = fStart.value;
        var finish  = fFinish.value;
        var dur     = parseInt(fDur?.value || '1', 10);
        var recalc  = document.getElementById('tdRecalc')?.checked ?? false;

        if (!start && !finish) { alert('กรุณาระบุวันแผนเริ่มหรือแผนจบ'); return; }
        if (finish && start && finish < start) { alert('แผนจบต้องไม่ก่อนแผนเริ่ม'); return; }

        saveBtn.disabled = true;

        fetch('api/task_update.php', {
          method: 'POST',
          headers: {'Content-Type': 'application/json'},
          body: JSON.stringify({
            action:         'dates',
            project_id:     PROJECT_ID,
            task_id:        parseInt(taskId, 10),
            planned_start:  start,
            planned_finish: finish,
            duration_days:  dur,
            recalculate:    recalc
          })
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (!data.ok) { alert('เกิดข้อผิดพลาด: ' + (data.error || 'unknown')); saveBtn.disabled = false; return; }
          updateTaskRowDom(taskId, data.task);
          updateCabinetRowDom(panelId, data.panel);
          if (data.updated_tasks && data.updated_tasks.length) {
            data.updated_tasks.forEach(function (t) {
              var ps = document.getElementById('task-planned-start-'  + t.id);
              var pf = document.getElementById('task-planned-finish-' + t.id);
              if (ps) ps.textContent = t.start_date_dmy || '';
              if (pf) pf.textContent = t.due_date_dmy   || '';
              var dlCell = document.getElementById('task-days-late-' + t.id);
              if (dlCell) dlCell.innerHTML = t.delay_badge_html || renderDelayBadge(t.delay);
              var eb = document.querySelector('.task-date-edit-btn[data-task-id="' + t.id + '"]');
              if (eb) { if (t.start_date) eb.dataset.plannedStart = t.start_date; if (t.due_date) eb.dataset.plannedFinish = t.due_date; }
            });
          }
          saveBtn.disabled = false;
          getModal().hide();
        })
        .catch(function (err) { console.error(err); saveBtn.disabled = false; });
      });
    }
  }());

}());
</script>

<?php render_footer(); ?>
