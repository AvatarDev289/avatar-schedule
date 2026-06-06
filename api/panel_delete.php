<?php
/**
 * api/panel_delete.php — Delete a panel (JSON response)
 * POST or GET: id=N
 */
require_once __DIR__ . '/../functions.php';
header('Content-Type: application/json; charset=utf-8');

try {
    $id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);

    if (!$id) {
        echo json_encode(['success' => false, 'error' => 'ไม่ระบุ id']);
        exit;
    }

    $pn = get_panel($id);
    if (!$pn) {
        echo json_encode(['success' => false, 'error' => 'ไม่พบตู้']);
        exit;
    }

    delete_panel($id);
    echo json_encode(['success' => true, 'msg' => 'ลบตู้ ' . $pn['panel_no'] . ' เรียบร้อยแล้ว']);

} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
