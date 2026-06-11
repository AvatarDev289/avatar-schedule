<?php
/**
 * panel_delete.php — delete a panel
 */
require_once __DIR__ . '/functions.php';

$id = (int)($_GET['id'] ?? 0);
$pn = $id ? get_panel($id) : null;

if ($pn) {
    $pid = (int)$pn['project_id'];
    delete_panel($id);
    header('Location: project_view.php?id=' . $pid . '&msg=' . rawurlencode('ลบตู้เรียบร้อยแล้ว') . '#panels');
    exit;
}

header('Location: projects.php?msg=' . rawurlencode('ไม่พบตู้ที่ต้องการลบ'));
exit;
