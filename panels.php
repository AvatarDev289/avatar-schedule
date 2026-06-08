<?php
/**
 * panels.php — Panel / Cabinet tracking dashboard
 * Summary cards · status pie chart · filters · tracking table
 */
require_once __DIR__ . '/functions.php';

$filters = [
    'search'         => trim($_GET['search'] ?? ''),
    'project_id'     => $_GET['project_id'] ?? '',
    'status'         => $_GET['status'] ?? '',
    'delivery_group' => $_GET['delivery_group'] ?? '',
    'responsible'    => $_GET['responsible'] ?? '',
];

$panels = get_panels($filters);
$stats  = panel_stats($panels);

$projects     = get_projects();
$groups       = get_panel_groups();
$responsibles = get_panel_responsibles();
$plabels      = panel_status_labels();
$pcolors      = panel_status_colors();

$returnUrl = 'panels.php?' . http_build_query(array_filter($filters));

render_header('ติดตามสถานะรายตู้ (Panel Tracking)', 'panels.php');
?>

<?php if (isset($_GET['msg'])): ?>
  <div class="alert alert-success alert-dismissible fade show">
    <?= e($_GET['msg']) ?><button class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endif; ?>

<!-- Summary cards -->
<div class="cards-row" style="grid-template-columns:repeat(5,1fr)">
  <div class="stat-card" style="--c:var(--primary-color)"><div class="stat-icon"><i class="bi bi-hdd-stack"></i></div>
    <div class="stat-body"><div class="stat-value"><?= $stats['total'] ?></div><div class="stat-label">ตู้ทั้งหมด</div></div></div>
  <div class="stat-card" style="--c:var(--success-color)"><div class="stat-icon"><i class="bi bi-box-seam"></i></div>
    <div class="stat-body"><div class="stat-value"><?= $stats['delivered'] ?></div><div class="stat-label">ส่งมอบแล้ว</div></div></div>
  <div class="stat-card" style="--c:var(--info-color)"><div class="stat-icon"><i class="bi bi-gear-wide-connected"></i></div>
    <div class="stat-body"><div class="stat-value"><?= $stats['producing'] ?></div><div class="stat-label">กำลังดำเนินการ</div></div></div>
  <div class="stat-card" style="--c:var(--danger-color)"><div class="stat-icon"><i class="bi bi-exclamation-octagon"></i></div>
    <div class="stat-body"><div class="stat-value"><?= $stats['overdue'] ?></div><div class="stat-label">ล่าช้า</div></div></div>
  <div class="stat-card" style="--c:var(--warning-color)"><div class="stat-icon"><i class="bi bi-graph-up"></i></div>
    <div class="stat-body"><div class="stat-value"><?= $stats['overall'] ?>%</div><div class="stat-label">Progress เฉลี่ย</div></div></div>
</div>

<div class="grid-3">
  <!-- Pie chart -->
  <div class="panel">
    <div class="panel-head"><i class="bi bi-pie-chart-fill"></i> สัดส่วนสถานะรายตู้</div>
    <div class="panel-body chart-wrap">
      <canvas id="panelPie" height="240"></canvas>
      <div class="chart-center"><div class="cc-num"><?= $stats['total'] ?></div><div class="cc-label">ตู้</div></div>
    </div>
    <ul class="legend">
      <?php foreach ($plabels as $k => $lb): if (($stats['count'][$k] ?? 0) === 0) continue; ?>
        <li><span class="dot" style="background:<?= $pcolors[$k] ?>"></span>
          <span class="lg-label"><?= e($lb) ?></span><span class="lg-num"><?= (int)$stats['count'][$k] ?></span></li>
      <?php endforeach; ?>
    </ul>
  </div>

  <!-- Filters + table -->
  <div class="panel span-2">
    <div class="panel-head"><i class="bi bi-funnel"></i> ตัวกรอง & ตาราง Panel Tracking</div>
    <div class="panel-body pb-2">
      <form class="filter-bar" method="get">
        <div class="fb-search"><i class="bi bi-search"></i>
          <input type="text" name="search" class="form-control" placeholder="ค้นหา Panel No. / ชื่อตู้" value="<?= e($filters['search']) ?>">
        </div>
        <select name="project_id" class="form-select">
          <option value="">ทุกโครงการ</option>
          <?php foreach ($projects as $pr): ?>
            <option value="<?= (int)$pr['id'] ?>" <?= (string)$filters['project_id']===(string)$pr['id']?'selected':'' ?>>
              <?= e($pr['project_no']) ?> — <?= e(mb_substr($pr['project_name'],0,28)) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <select name="status" class="form-select">
          <option value="">ทุกสถานะ</option>
          <?php foreach ($plabels as $k => $lb): ?>
            <option value="<?= e($k) ?>" <?= $filters['status']===$k?'selected':'' ?>><?= e($lb) ?></option>
          <?php endforeach; ?>
        </select>
        <select name="delivery_group" class="form-select">
          <option value="">ทุก Group</option>
          <?php foreach ($groups as $g): ?>
            <option value="<?= e($g) ?>" <?= $filters['delivery_group']===$g?'selected':'' ?>>Group <?= e($g) ?></option>
          <?php endforeach; ?>
        </select>
        <select name="responsible" class="form-select">
          <option value="">ทุกผู้รับผิดชอบ</option>
          <?php foreach ($responsibles as $r): ?>
            <option value="<?= e($r) ?>" <?= $filters['responsible']===$r?'selected':'' ?>><?= e($r) ?></option>
          <?php endforeach; ?>
        </select>
        <button class="btn btn-primary"><i class="bi bi-funnel"></i> กรอง</button>
        <a href="panels.php" class="btn btn-outline-secondary">ล้าง</a>
      </form>
    </div>

    <div class="panel-body p-0">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 data-table">
          <thead>
            <tr>
              <th>Panel No.</th><th>โครงการ</th><th>ชื่อตู้</th><th>Group</th>
              <th>กำหนดส่ง</th><th style="width:120px">Progress</th><th style="width:190px">สถานะ</th><th>ผู้รับผิดชอบ</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$panels): ?>
              <tr><td colspan="8" class="text-center text-muted py-5">ไม่พบข้อมูลตู้ตามเงื่อนไข</td></tr>
            <?php else: foreach ($panels as $pn): $es = $pn['eff_status']; $ovd = panel_overdue_days($pn); ?>
              <tr>
                <td class="fw-600"><?= e($pn['panel_no']) ?></td>
                <td>
                  <a href="project_view.php?id=<?= (int)$pn['project_id'] ?>#panels" class="link-title"><?= e($pn['project_no']) ?></a>
                </td>
                <td>
                  <div class="fw-600"><?= e($pn['panel_name']) ?></div>
                  <div class="text-muted small"><?= e($pn['panel_type']) ?></div>
                </td>
                <td><?php if ($pn['delivery_group']): ?><span class="grp-badge"><?= e($pn['delivery_group']) ?></span><?php else: ?>-<?php endif; ?></td>
                <td class="small"><?= e(format_date_dmy($pn['target_delivery_date'])) ?>
                  <?php if ($ovd>0): ?><span class="badge bg-danger ms-1">เลย <?= $ovd ?>ว</span><?php endif; ?></td>
                <td>
                  <div class="progress-mini"><div class="bar" style="width:<?= (int)$pn['progress_percent'] ?>%;background:<?= panel_status_color($es) ?>"></div></div>
                  <span class="small text-muted"><?= (int)$pn['progress_percent'] ?>%</span>
                </td>
                <td>
                  <?php
                    $mode  = $pn['status_mode'] ?? 'AUTO';
                    $wf    = panel_workflow_statuses();
                    $dbIdx = ($k = array_search($pn['status'] ?? 'pending', $wf)) !== false ? $k : 0;
                    $prevS = $dbIdx > 0 ? $wf[$dbIdx - 1] : null;
                    $nextS = $dbIdx < count($wf) - 1 ? $wf[$dbIdx + 1] : null;
                    $sUrl  = fn($s) => 'panel_update_status.php?id=' . (int)$pn['id'] . '&status=' . urlencode($s) . '&return=' . urlencode($returnUrl);
                  ?>
                  <div class="d-flex align-items-center gap-1 flex-nowrap">
                    <?php if ($mode === 'AUTO'): ?>
                      <?php if ($prevS): ?>
                        <a href="<?= e($sUrl($prevS)) ?>" class="btn btn-sm btn-outline-secondary py-0 px-1" title="<?= e(panel_status_label($prevS)) ?>">‹</a>
                      <?php else: ?>
                        <span class="btn btn-sm btn-light py-0 px-1" style="opacity:.25;cursor:default">‹</span>
                      <?php endif; ?>
                    <?php endif; ?>
                    <span class="status-pill" style="background:<?= panel_status_color($es) ?>"><?= e(panel_status_label($es)) ?></span>
                    <?php if ($mode === 'MANUAL'): ?>
                      <span class="badge" style="background:#6B7280;font-size:10px;padding:2px 5px" title="Manual Override">M</span>
                    <?php else: ?>
                      <?php if ($nextS): ?>
                        <a href="<?= e($sUrl($nextS)) ?>" class="btn btn-sm btn-success py-0 px-1" title="<?= e(panel_status_label($nextS)) ?>">›</a>
                      <?php else: ?>
                        <span class="btn btn-sm btn-light py-0 px-1" style="opacity:.25;cursor:default">›</span>
                      <?php endif; ?>
                    <?php endif; ?>
                  </div>
                </td>
                <td class="small"><?= e($pn['responsible'] ?: '-') ?></td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<script>
window.PANEL_PIE = {
  labels: <?= json_encode(array_values(array_map(fn($k)=>$plabels[$k], array_keys(array_filter($stats['count'], fn($v)=>$v>0)))), JSON_UNESCAPED_UNICODE) ?>,
  data:   <?= json_encode(array_values(array_filter($stats['count'], fn($v)=>$v>0))) ?>,
  colors: <?= json_encode(array_values(array_map(fn($k)=>$pcolors[$k], array_keys(array_filter($stats['count'], fn($v)=>$v>0))))) ?>
};
(function(){
  var el = document.getElementById('panelPie');
  if (el && window.Chart && window.PANEL_PIE.data.length){
    new Chart(el, { type:'doughnut',
      data:{ labels:window.PANEL_PIE.labels, datasets:[{ data:window.PANEL_PIE.data,
        backgroundColor:window.PANEL_PIE.colors, borderColor:'#fff', borderWidth:2, hoverOffset:6 }] },
      options:{ cutout:'66%', plugins:{ legend:{display:false},
        tooltip:{ callbacks:{ label:function(c){ var t=c.dataset.data.reduce((a,b)=>a+b,0);
          return ' '+c.label+': '+c.parsed+' ('+Math.round(c.parsed/t*100)+'%)'; } } } } }
    });
  }
})();
</script>

<?php render_footer(); ?>
