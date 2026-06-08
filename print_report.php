<?php
/**
 * print_report.php — Executive print report.
 * If ?id= is supplied -> single project report, otherwise full list report
 * (honoring projects.php filters).
 */
require_once __DIR__ . '/functions.php';

$singleId = (int)($_GET['id'] ?? 0);
$single   = $singleId ? get_project($singleId) : null;

$filters = [
    'search'        => trim($_GET['search'] ?? ''),
    'status'        => $_GET['status'] ?? '',
    'department_id' => $_GET['department_id'] ?? '',
];
$projects = $single ? [$single] : get_projects($filters);
$stats    = dashboard_stats($projects);
$fragment = !empty($_GET['fragment']); // SPA fragment mode — skip HTML wrapper
?>
<?php if (!$fragment): ?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<title>รายงานโครงการ — Avatar Electric</title>
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
  * { box-sizing: border-box; }
  body { font-family: 'Sarabun', sans-serif; color:#1f2937; margin:0; padding:28px 34px; }
  .rep-head { display:flex; justify-content:space-between; align-items:flex-start;
              border-bottom:3px solid #FF7A00; padding-bottom:12px; margin-bottom:18px; }
  .rep-head h1 { margin:0; font-size:22px; color:#FF7A00; }
  .rep-head .sub { color:#555; font-size:13px; }
  .company { text-align:right; font-size:13px; color:#444; }
  .company .name { font-size:18px; font-weight:700; color:#FF7A00; letter-spacing:1px; }
  .summary { display:flex; gap:10px; margin-bottom:18px; flex-wrap:wrap; }
  .sx { flex:1; min-width:120px; border:1px solid #e5e7eb; border-top:3px solid #FF7A00;
        border-radius:6px; padding:8px 12px; }
  .sx .n { font-size:22px; font-weight:700; }
  .sx .l { font-size:12px; color:#666; }
  table { width:100%; border-collapse:collapse; font-size:12.5px; }
  th, td { border:1px solid #cbd5e1; padding:6px 8px; text-align:left; }
  th { background:#FF7A00; color:#fff; font-weight:600; }
  tbody tr:nth-child(even){ background:#f8fafc; }
  .text-end{ text-align:right; } .text-center{ text-align:center; }
  .badge { padding:2px 8px; border-radius:10px; color:#fff; font-size:11px; white-space:nowrap; }
  .tfoot td { font-weight:700; background:#eef2f7; }
  .rep-foot { margin-top:26px; display:flex; justify-content:space-between; font-size:13px; }
  .sign { text-align:center; width:220px; }
  .sign .line { margin-top:48px; border-top:1px dotted #555; padding-top:4px; }
  .dl { color:#6b7280; font-size:12px; } .dv{ font-weight:600; }
  .detail-table td { border:none; padding:4px 6px; }
  @media print { .no-print{ display:none; } body{ padding:0; } @page { margin:14mm; } }
  .toolbar { text-align:right; margin-bottom:14px; }
  .btn { background:#FF7A00; color:#fff; border:none; padding:8px 16px; border-radius:6px;
         cursor:pointer; font-family:inherit; font-size:13px; text-decoration:none; }
</style>
</head>
<body>
<?php endif; // end !$fragment head ?>

<div class="toolbar no-print">
  <a class="btn" href="javascript:window.print()">🖨️ พิมพ์รายงาน</a>
  <?php if (!$fragment): ?>
  <a class="btn" style="background:#6B7280" href="<?= $single ? 'project_view.php?id='.$singleId : 'projects.php' ?>">ย้อนกลับ</a>
  <?php endif; ?>
</div>

<div class="rep-head">
  <div>
    <h1><?= $single ? 'รายงานรายละเอียดโครงการ' : 'รายงานสรุปแผนงานโครงการ' ?></h1>
    <div class="sub">Schedule of Project Report — ข้อมูล ณ วันที่ <?= e(format_date_dmy(date('Y-m-d'))) ?></div>
  </div>
  <div class="company">
    <div class="name">AVATAR ELECTRIC</div>
    <div>บริษัท อวตาร อิเล็คทริค จำกัด</div>
    <div>Avatar Electric Co., Ltd.</div>
  </div>
</div>

<?php if ($single): $p = $single; $st = $p['effective_status']; ?>
  <!-- ===== Single project ===== -->
  <h2 style="color:#FF7A00;font-size:18px;margin:0 0 4px"><?= e($p['project_no']) ?> — <?= e($p['project_name']) ?></h2>
  <p>
    <span class="badge" style="background:<?= status_color($st) ?>"><?= e(status_label($st)) ?></span>
    &nbsp; ความคืบหน้า <?= (int)$p['progress'] ?>%
  </p>
  <table class="detail-table" style="margin-top:8px">
    <tr>
      <td><span class="dl">ลูกค้า</span><br><span class="dv"><?= e($p['customer'] ?: '-') ?></span></td>
      <td><span class="dl">แผนก</span><br><span class="dv"><?= e($p['department'] ?: '-') ?></span></td>
      <td><span class="dl">ผู้รับผิดชอบ</span><br><span class="dv"><?= e($p['responsible'] ?: '-') ?></span></td>
      <td><span class="dl">มูลค่างาน</span><br><span class="dv"><?= money2($p['amount']) ?> บาท</span></td>
    </tr>
    <tr>
      <td><span class="dl">วันเริ่ม</span><br><span class="dv"><?= e(format_date_dmy($p['start_date'])) ?></span></td>
      <td><span class="dl">กำหนดส่ง</span><br><span class="dv"><?= e(format_date_dmy($p['due_date'])) ?></span></td>
      <td><span class="dl">วันส่งมอบ</span><br><span class="dv"><?= e(format_date_dmy($p['delivery_date'])) ?></span></td>
      <td><span class="dl">วันเสร็จ</span><br><span class="dv"><?= e(format_date_dmy($p['completed_date'])) ?></span></td>
    </tr>
  </table>
  <p style="margin-top:14px"><span class="dl">รายละเอียดงาน</span><br><?= nl2br(e($p['description'] ?: '-')) ?></p>
  <p><span class="dl">หมายเหตุ</span><br><?= nl2br(e($p['remark'] ?: '-')) ?></p>

<?php else: ?>
  <!-- ===== Summary cards ===== -->
  <div class="summary">
    <div class="sx"><div class="n"><?= (int)$stats['total'] ?></div><div class="l">โครงการทั้งหมด</div></div>
    <div class="sx"><div class="n" style="color:<?= status_color('completed') ?>"><?= (int)$stats['count']['completed'] ?></div><div class="l">เสร็จแล้ว</div></div>
    <div class="sx"><div class="n" style="color:<?= status_color('in_progress') ?>"><?= (int)$stats['count']['in_progress'] ?></div><div class="l">กำลังดำเนินการ</div></div>
    <div class="sx"><div class="n" style="color:<?= status_color('near_due') ?>"><?= (int)$stats['count']['near_due'] ?></div><div class="l">ใกล้ครบกำหนด</div></div>
    <div class="sx"><div class="n" style="color:<?= status_color('overdue') ?>"><?= (int)$stats['count']['overdue'] ?></div><div class="l">ล่าช้า</div></div>
    <div class="sx"><div class="n" style="color:#FF7A00;font-size:18px"><?= money($stats['total_amount']) ?></div><div class="l">มูลค่ารวม (บาท)</div></div>
  </div>

  <!-- ===== Project table ===== -->
  <table>
    <thead>
      <tr>
        <th>#</th><th>Project No.</th><th>ชื่อโครงการ / ลูกค้า</th><th>กำหนดส่ง</th>
        <th class="text-center">คืบหน้า</th><th class="text-end">มูลค่า</th><th>สถานะ</th>
      </tr>
    </thead>
    <tbody>
      <?php $i=1; foreach ($projects as $p): $st=$p['effective_status']; ?>
      <tr>
        <td><?= $i++ ?></td>
        <td><?= e($p['project_no']) ?></td>
        <td><strong><?= e($p['project_name']) ?></strong><br><span class="dl"><?= e($p['customer']) ?></span></td>
        <td><?= e(format_date_dmy($p['due_date'])) ?></td>
        <td class="text-center"><?= (int)$p['progress'] ?>%</td>
        <td class="text-end"><?= money($p['amount']) ?></td>
        <td><span class="badge" style="background:<?= status_color($st) ?>"><?= e(status_label($st)) ?></span></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
    <tfoot>
      <tr class="tfoot">
        <td colspan="5" class="text-end">มูลค่ารวมทั้งสิ้น</td>
        <td class="text-end"><?= money($stats['total_amount']) ?></td>
        <td></td>
      </tr>
    </tfoot>
  </table>
<?php endif; ?>

<div class="rep-foot">
  <div class="sign"><div class="line">ผู้จัดทำรายงาน</div></div>
  <div class="sign"><div class="line">ผู้จัดการโครงการ</div></div>
  <div class="sign"><div class="line">ผู้บริหาร</div></div>
</div>

<?php if (!$fragment): ?>
<script>window.onload = function(){ /* auto open print dialog: window.print(); */ };</script>
</body>
</html>
<?php endif; ?>
