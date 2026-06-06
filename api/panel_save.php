<?php
/**
 * api/panel_save.php — Create or update a panel (JSON response)
 * POST: id (edit) or project_id (add), + panel fields
 */
require_once __DIR__ . '/../functions.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'errors' => ['Method not allowed']]);
    exit;
}

try {
    $id        = (int)($_POST['id'] ?? 0);
    $projectId = (int)($_POST['project_id'] ?? 0);
    $data      = collect_panel_post();

    $errors = [];
    if ($data['panel_no']   === '') $errors[] = 'กรุณากรอก Panel No.';
    if ($data['panel_name'] === '') $errors[] = 'กรุณากรอกชื่อตู้';

    if ($errors) {
        echo json_encode(['success' => false, 'errors' => $errors]);
        exit;
    }

    if ($id) {
        $pn = get_panel($id);
        if (!$pn) {
            echo json_encode(['success' => false, 'errors' => ['ไม่พบตู้ที่ต้องการแก้ไข']]);
            exit;
        }
        update_panel($id, $data);
        echo json_encode(['success' => true, 'id' => $id, 'msg' => 'บันทึกการแก้ไขตู้ ' . $data['panel_no'] . ' เรียบร้อยแล้ว']);
    } else {
        if (!$projectId) {
            echo json_encode(['success' => false, 'errors' => ['ไม่ระบุโครงการ']]);
            exit;
        }
        $project = get_project($projectId);
        if (!$project) {
            echo json_encode(['success' => false, 'errors' => ['ไม่พบโครงการ id=' . $projectId]]);
            exit;
        }
        $newId = create_panel($projectId, $data);
        echo json_encode(['success' => true, 'id' => $newId, 'msg' => 'เพิ่มตู้ ' . $data['panel_no'] . ' เรียบร้อยแล้ว']);
    }

} catch (Throwable $e) {
    echo json_encode(['success' => false, 'errors' => ['เกิดข้อผิดพลาด: ' . $e->getMessage()]]);
}
