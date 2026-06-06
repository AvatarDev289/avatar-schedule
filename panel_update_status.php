<?php
/**
 * panel_update_status.php — quick status change for a single panel.
 * Accepts GET/POST: id, status  (+ optional return URL).
 * progress_percent is set automatically from the status map.
 */
require_once __DIR__ . '/functions.php';

$id     = (int)($_REQUEST['id'] ?? 0);
$status = $_REQUEST['status'] ?? '';
$return = $_REQUEST['return'] ?? '';

$pn = $id ? get_panel($id) : null;

if ($pn && in_array($status, panel_workflow_statuses(), true)) {
    set_panel_status($id, $status);
    $msg = 'อัปเดตสถานะตู้ ' . $pn['panel_no'] . ' เป็น "' . panel_status_label($status) . '"';
} else {
    $msg = 'ไม่สามารถอัปเดตสถานะได้';
}

// redirect back: explicit return, else the project view, else panel tracking
if ($return) {
    $sep = (strpos($return, '?') !== false) ? '&' : '?';
    header('Location: ' . $return . $sep . 'msg=' . rawurlencode($msg));
} elseif ($pn) {
    header('Location: project_view.php?id=' . (int)$pn['project_id'] . '&msg=' . rawurlencode($msg) . '#panels');
} else {
    header('Location: panels.php?msg=' . rawurlencode($msg));
}
exit;
