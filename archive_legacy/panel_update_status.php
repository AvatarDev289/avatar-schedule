<?php
/**
 * panel_update_status.php — legacy endpoint, now redirects to project_view.
 * Status is driven by Tasks only; direct panel status changes are no longer supported.
 */
require_once __DIR__ . '/functions.php';

$id     = (int)($_REQUEST['id'] ?? 0);
$return = $_REQUEST['return'] ?? '';
$msg    = 'สถานะตู้ถูกกำหนดจาก Tasks อัตโนมัติ — อัปเดต Task ใน Project View แทน';

$pn = $id ? get_panel($id) : null;

if ($return) {
    $sep = (strpos($return, '?') !== false) ? '&' : '?';
    header('Location: ' . $return . $sep . 'msg=' . rawurlencode($msg));
} elseif ($pn) {
    header('Location: project_view.php?id=' . (int)$pn['project_id'] . '&msg=' . rawurlencode($msg) . '#panels');
} else {
    header('Location: panels.php?msg=' . rawurlencode($msg));
}
exit;
