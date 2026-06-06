<?php
/**
 * project_delete.php — Delete a project
 */
require_once __DIR__ . '/functions.php';

$id = (int)($_GET['id'] ?? 0);
if ($id > 0) {
    $p = get_project($id);
    if ($p) {
        // remove attachment file if present
        if (!empty($p['attachment'])) {
            $file = UPLOAD_DIR . '/' . $p['attachment'];
            if (is_file($file)) {
                @unlink($file);
            }
        }
        delete_project($id);
        header('Location: projects.php?msg=' . rawurlencode('ลบโครงการเรียบร้อยแล้ว'));
        exit;
    }
}

header('Location: projects.php?msg=' . rawurlencode('ไม่พบโครงการที่ต้องการลบ'));
exit;
