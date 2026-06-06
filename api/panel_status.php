<?php
/**
 * api/panel_status.php — Update panel status (JSON response)
 * POST: id=N, status=<workflow_status>
 */
require_once __DIR__ . '/../functions.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

try {
    $id     = (int)($_POST['id'] ?? 0);
    $status = $_POST['status'] ?? '';

    if (!$id) {
        echo json_encode(['success' => false, 'error' => 'ไม่ระบุ id']);
        exit;
    }

    if (!in_array($status, panel_workflow_statuses(), true)) {
        echo json_encode(['success' => false, 'error' => 'สถานะไม่ถูกต้อง: ' . $status]);
        exit;
    }

    $pn = get_panel($id);
    if (!$pn) {
        echo json_encode(['success' => false, 'error' => 'ไม่พบตู้ id=' . $id]);
        exit;
    }

    set_panel_status($id, $status);

    echo json_encode([
        'success'  => true,
        'msg'      => 'อัปเดตสถานะตู้ ' . $pn['panel_no'] . ' → ' . panel_status_label($status),
        'progress' => panel_progress_for_status($status),
        'status'   => $status,
    ]);

} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
