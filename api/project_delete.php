<?php
/**
 * api/project_delete.php — Delete a project (JSON response)
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

    $p = get_project($id);
    if (!$p) {
        echo json_encode(['success' => false, 'error' => 'ไม่พบโครงการ']);
        exit;
    }

    delete_project($id);
    echo json_encode(['success' => true, 'msg' => 'ลบโครงการ ' . $p['project_no'] . ' เรียบร้อยแล้ว']);

} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
