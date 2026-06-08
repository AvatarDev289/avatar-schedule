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

$st        = $p['effective_status'];
$od        = overdue_days($p);
$dr        = days_remaining($p);
$panels    = get_project_panels($id);
$pstats    = panel_stats($panels);
$panelProg = $pstats['total'] > 0 ? $pstats['overall'] : (int)$p['progress'];
$groupMap  = build_group_color_map($panels);
$dgGroups  = get_delivery_groups($id);
$returnUrl = 'project_view.php?id=' . $id;

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

    <!-- Filter bar -->
    <div class="d-flex gap-2 align-items-center flex-wrap mb-3">
      <div class="fb-search" style="min-width:240px;flex:1">
        <i class="bi bi-search"></i>
        <input type="text" class="form-control" id="pvSearch" placeholder="ค้นหา Panel No. หรือชื่อตู้...">
      </div>
      <?php if (count($filterGroups) > 1): ?>
        <select class="form-select" style="width:auto;min-width:140px" id="pvGroupFilter">
          <option value="">ทุก Group</option>
          <?php foreach ($filterGroups as $fg): ?>
            <option value="<?= e($fg) ?>"><?= e($fg) ?></option>
          <?php endforeach; ?>
        </select>
      <?php endif; ?>
      <select class="form-select" style="width:auto;min-width:150px" id="pvStatusFilter">
        <option value="">ทุกสถานะ</option>
        <?php foreach ($filterStatuses as $fs): ?>
          <option value="<?= e($fs) ?>"><?= e(panel_status_label($fs)) ?></option>
        <?php endforeach; ?>
      </select>
      <a href="panel_add.php?project_id=<?= (int)$id ?>" class="btn btn-success btn-sm ms-auto">
        <i class="bi bi-plus-lg me-1"></i>เพิ่มตู้
      </a>
    </div>

    <div class="panel">
      <div class="panel-body p-0">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0 data-table" id="pvTable">
            <thead>
              <tr>
                <th>Panel No.</th>
                <th>ชื่อตู้ / ประเภท</th>
                <th>Group</th>
                <th>สถานะ</th>
                <th style="min-width:120px">Progress</th>
                <th>ผู้รับผิดชอบ</th>
                <th>กำหนดส่ง</th>
                <th class="text-center" style="width:90px">จัดการ</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!$panels): ?>
                <tr><td colspan="8" class="text-center text-muted py-5">
                  <i class="bi bi-inbox display-6 d-block mb-2"></i>
                  ยังไม่มีตู้ในโครงการนี้
                </td></tr>
              <?php else: foreach ($panels as $pn):
                $es       = $pn['eff_status'];
                $sc       = panel_status_color($es);
                $gc       = $groupMap[trim((string)($pn['delivery_group'] ?? ''))] ?? '#6B7280';
                $grp      = trim((string)($pn['delivery_group'] ?? ''));
                $ovd      = panel_overdue_days($pn);
                $wf       = panel_workflow_statuses();
                $dbIdx    = ($ki = array_search($pn['status'] ?? 'pending', $wf)) !== false ? $ki : 0;
                $prevS    = $dbIdx > 0 ? $wf[$dbIdx - 1] : '';
                $nextS    = $dbIdx < count($wf) - 1 ? $wf[$dbIdx + 1] : '';
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
                    data-status-label="<?= e(panel_status_label($es)) ?>"
                    data-status-color="<?= e($sc) ?>"
                    data-db-status="<?= e($pn['status'] ?? 'pending') ?>"
                    data-mode="<?= e($pn['status_mode'] ?? 'AUTO') ?>"
                    data-progress="<?= (int)$pn['progress_percent'] ?>"
                    data-responsible="<?= e($pn['responsible'] ?? '') ?>"
                    data-target="<?= e(format_date_dmy($pn["target_delivery_date"] ?? '')) ?>"
                    data-actual="<?= e(format_date_dmy($pn["actual_delivery_date"] ?? '')) ?>"
                    data-remark="<?= e($pn['remark'] ?? '') ?>"
                    data-prev-status="<?= e($prevS) ?>"
                    data-next-status="<?= e($nextS) ?>">

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
                    <span class="pv-st" style="background:<?= e($sc) ?>"><?= e(panel_status_label($es)) ?></span>
                    <?php if (($pn['status_mode'] ?? 'AUTO') === 'MANUAL'): ?>
                      <span class="badge bg-secondary ms-1" style="font-size:9px" title="Manual Override">M</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <div class="pv-prog">
                      <div class="pv-prog-bar">
                        <div class="pv-prog-fill" style="width:<?= (int)$pn['progress_percent'] ?>%;background:<?= e($sc) ?>"></div>
                      </div>
                      <span class="pv-prog-pct"><?= (int)$pn['progress_percent'] ?>%</span>
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
                    <a href="panel_edit.php?id=<?= (int)$pn['id'] ?>" class="btn btn-sm btn-light" title="แก้ไข"><i class="bi bi-pencil"></i></a>
                    <a href="panel_delete.php?id=<?= (int)$pn['id'] ?>"
                       class="btn btn-sm btn-light text-danger" title="ลบ"
                       onclick="return confirm('ยืนยันการลบตู้ <?= e($pn['panel_no']) ?>?')"><i class="bi bi-trash"></i></a>
                  </td>
                </tr>
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


<script>
(function () {
  'use strict';

  /* ── Constants ── */
  const WORKFLOW = <?= json_encode(panel_workflow_statuses()) ?>;
  const WF_LABELS = <?= json_encode(panel_status_labels()) ?>;
  const PROJECT_ID = <?= (int)$id ?>;
  const RETURN_TAB = 'cabinets';

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
    const id         = d.id;
    const mode       = d.mode;
    const dbStatus   = d.dbStatus;
    const statusColor = d.statusColor;
    const groupColor  = d.groupColor;
    const progress    = parseInt(d.progress, 10) || 0;
    const retUrl      = encodeURIComponent('project_view.php?id=' + PROJECT_ID + '&_tab=' + RETURN_TAB);

    /* workflow nav */
    const wfIdx  = WORKFLOW.indexOf(dbStatus);
    const prevS  = (mode === 'AUTO' && wfIdx > 0) ? WORKFLOW[wfIdx - 1] : null;
    const nextS  = (mode === 'AUTO' && wfIdx < WORKFLOW.length - 1) ? WORKFLOW[wfIdx + 1] : null;

    const prevBtn = prevS
      ? '<a href="panel_update_status.php?id=' + id + '&status=' + prevS + '&return=' + retUrl
        + '" class="btn btn-sm btn-outline-secondary">‹ ' + esc(WF_LABELS[prevS] ?? prevS) + '</a>'
      : '';
    const nextBtn = nextS
      ? '<a href="panel_update_status.php?id=' + id + '&status=' + nextS + '&return=' + retUrl
        + '" class="btn btn-success btn-sm">' + esc(WF_LABELS[nextS] ?? nextS) + ' ›</a>'
      : '';
    const wfRow = (prevBtn || nextBtn)
      ? '<div class="d-flex gap-2 align-items-center flex-wrap mt-2">' + prevBtn + nextBtn + '</div>'
      : '';

    const grpBadge = d.group
      ? '<span class="pv-grp me-1" style="background:' + esc(groupColor) + '">' + esc(d.group) + '</span>'
      : '';
    const stBadge = '<span class="pv-st" style="background:' + esc(statusColor) + '">' + esc(d.statusLabel) + '</span>';
    const manualBadge = (mode === 'MANUAL')
      ? '<span class="badge bg-secondary ms-1" style="font-size:9px">Manual</span>'
      : '';

    const remarkHtml = d.remark
      ? '<div class="alert alert-light p-2 mt-2" style="font-size:13.5px"><small class="text-muted d-block mb-1">หมายเหตุ</small>' + esc(d.remark) + '</div>'
      : '';

    drNo.textContent   = d.no   || '—';
    drName.textContent = d.name || '—';
    drBody.innerHTML   =
      '<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">' + grpBadge + stBadge + manualBadge + '</div>'
      + '<div class="pv-prog mb-1">'
        + '<div class="pv-prog-bar"><div class="pv-prog-fill" style="width:' + progress + '%;background:' + esc(statusColor) + '"></div></div>'
        + '<span class="pv-prog-pct">' + progress + '%</span>'
      + '</div>'
      + wfRow
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

  /* ── Restore active tab from ?_tab= param ── */
  const activeTab = new URLSearchParams(location.search).get('_tab');
  if (activeTab) {
    const tabBtn = document.querySelector('[data-bs-target="#tab-' + CSS.escape(activeTab) + '"]');
    if (tabBtn) {
      const bsTab = new bootstrap.Tab(tabBtn);
      bsTab.show();
    }
    /* clean up URL without reloading */
    const url = new URL(location.href);
    url.searchParams.delete('_tab');
    history.replaceState(null, '', url.toString());
  }

}());
</script>

<?php render_footer(); ?>
