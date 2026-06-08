<?php
/**
 * panel_update_status.php — advance / regress a panel one workflow step.
 * Accepts GET: id, status, return.
 * Enforces sequential progression: can only move ±1 step at a time.
 */
require_once __DIR__ . '/functions.php';

$id     = (int)($_REQUEST['id'] ?? 0);
$status = $_REQUEST['status'] ?? '';
$return = $_REQUEST['return'] ?? '';

$pn = $id ? get_panel($id) : null;

if ($pn) {
    if (($pn['status_mode'] ?? 'AUTO') !== 'AUTO') {
        $msg = 'ตู้นี้อยู่ในโหมด Manual Override — ต้องสลับเป็น Auto ก่อนจึงจะเลื่อนขั้นตอนได้';
    } else {
        $wf     = panel_workflow_statuses();
        $curIdx = array_search($pn['status'] ?? 'pending', $wf);
        $newIdx = array_search($status, $wf);

        // Allow move only if both indices are valid and differ by exactly 1 step
        $isAdj    = ($curIdx !== false && $newIdx !== false && abs((int)$newIdx - (int)$curIdx) === 1);
        $isRevert = ($isAdj && (int)$newIdx < (int)$curIdx);

        if ($isAdj && $isRevert && ($pn['status'] ?? '') === 'delivered') {
            // Cannot regress from delivered — panel has already been handed over
            $msg = 'ไม่สามารถย้อนสถานะจาก "ส่งมอบแล้ว" ได้ — ตู้ถูกส่งมอบไปแล้ว';
        } elseif ($isAdj) {
            set_panel_status($id, $status);
            $msg = 'อัปเดตสถานะตู้ ' . $pn['panel_no'] . ' เป็น "' . panel_status_label($status) . '"';
        } else {
            $msg = 'ไม่สามารถข้ามขั้นตอนได้ — ต้องเลื่อนทีละขั้น';
        }
    }
} else {
    $msg = 'ไม่พบตู้ที่ต้องการอัปเดตสถานะ';
}

if ($return) {
    $sep = (strpos($return, '?') !== false) ? '&' : '?';
    header('Location: ' . $return . $sep . 'msg=' . rawurlencode($msg));
} elseif ($pn) {
    header('Location: project_view.php?id=' . (int)$pn['project_id'] . '&msg=' . rawurlencode($msg) . '#panels');
} else {
    header('Location: panels.php?msg=' . rawurlencode($msg));
}
exit;
