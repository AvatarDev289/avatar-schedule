<?php
/**
 * export_excel.php — Export project list as an Excel-readable file (.xls / HTML table)
 * Honors the same filters as projects.php (search / status / department_id).
 */
require_once __DIR__ . '/functions.php';

$filters = [
    'search'        => trim($_GET['search'] ?? ''),
    'status'        => $_GET['status'] ?? '',
    'department_id' => $_GET['department_id'] ?? '',
];
$projects = get_projects($filters);

$filename = 'avatar_projects_' . date('Ymd_His') . '.xls';

header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

// BOM so Excel reads Thai UTF-8 correctly
echo "\xEF\xBB\xBF";
?>
<html xmlns:x="urn:schemas-microsoft-com:office:excel">
<head>
<meta charset="utf-8">
<style>
  table { border-collapse: collapse; }
  th, td { border: 1px solid #888; padding: 4px 8px; font-family: 'Tahoma', sans-serif; font-size: 12px; }
  th { background: #FF7A00; color: #fff; }
  .title { font-size: 16px; font-weight: bold; }
  .num { mso-number-format: "\#\,\#\#0"; }
</style>
</head>
<body>
<table>
  <tr><td colspan="13" class="title">Avatar Electric — รายงานโครงการ (Schedule of Project)</td></tr>
  <tr><td colspan="13">ข้อมูล ณ วันที่ <?= e(format_date_dmy(date('Y-m-d'))) ?> | จำนวน <?= count($projects) ?> โครงการ</td></tr>
  <tr></tr>
  <tr>
    <th>#</th>
    <th>Project No.</th>
    <th>ชื่อโครงการ</th>
    <th>ลูกค้า</th>
    <th>แผนก</th>
    <th>ผู้รับผิดชอบ</th>
    <th>วันเริ่ม</th>
    <th>กำหนดส่ง</th>
    <th>วันส่งมอบ</th>
    <th>วันเสร็จ</th>
    <th>คืบหน้า (%)</th>
    <th>มูลค่า (บาท)</th>
    <th>สถานะ</th>
  </tr>
  <?php $i = 1; $total = 0; foreach ($projects as $p): $total += (float)$p['amount']; ?>
  <tr>
    <td><?= $i++ ?></td>
    <td><?= e($p['project_no']) ?></td>
    <td><?= e($p['project_name']) ?></td>
    <td><?= e($p['customer']) ?></td>
    <td><?= e($p['department']) ?></td>
    <td><?= e($p['responsible']) ?></td>
    <td><?= e(format_date_dmy($p['start_date'])) ?></td>
    <td><?= e(format_date_dmy($p['due_date'])) ?></td>
    <td><?= e(format_date_dmy($p['delivery_date'])) ?></td>
    <td><?= e(format_date_dmy($p['completed_date'])) ?></td>
    <td><?= (int)$p['progress'] ?></td>
    <td class="num"><?= money($p['amount']) ?></td>
    <td><?= e(status_label($p['effective_status'])) ?></td>
  </tr>
  <?php endforeach; ?>
  <tr>
    <th colspan="11" style="text-align:right">มูลค่ารวมทั้งสิ้น</th>
    <th class="num"><?= money($total) ?></th>
    <th></th>
  </tr>
</table>
</body>
</html>
