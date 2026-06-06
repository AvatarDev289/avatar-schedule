<?php
/**
 * api/project_save.php — Create or update a project (JSON response)
 * POST: id (optional, 0=create), + project fields
 */
require_once __DIR__ . '/../functions.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'errors' => ['Method not allowed']]);
    exit;
}

try {
    $id   = (int)($_POST['id'] ?? 0);
    $data = collect_project_post();

    $errors = [];
    if ($data['project_no']   === '') $errors[] = 'กรุณากรอก Project No.';
    if ($data['project_name'] === '') $errors[] = 'กรุณากรอกชื่อโครงการ';

    if (!$errors) {
        // Duplicate project_no check
        $sql  = 'SELECT COUNT(*) FROM projects WHERE project_no = :no' . ($id ? ' AND id != :id' : '');
        $stmt = db()->prepare($sql);
        $params = [':no' => $data['project_no']];
        if ($id) $params[':id'] = $id;
        $stmt->execute($params);
        if ((int)$stmt->fetchColumn() > 0) {
            $errors[] = 'Project No. นี้มีอยู่แล้วในระบบ';
        }
    }

    if ($errors) {
        echo json_encode(['success' => false, 'errors' => $errors]);
        exit;
    }

    $attachment = handle_upload('attachment');

    if ($id) {
        update_project($id, $data, $attachment);
        echo json_encode(['success' => true, 'id' => $id, 'msg' => 'แก้ไขโครงการเรียบร้อยแล้ว']);
    } else {
        $newId = create_project($data, $attachment);
        echo json_encode(['success' => true, 'id' => $newId, 'msg' => 'เพิ่มโครงการเรียบร้อยแล้ว']);
    }

} catch (Throwable $e) {
    echo json_encode(['success' => false, 'errors' => ['เกิดข้อผิดพลาด: ' . $e->getMessage()]]);
}
