<?php
/**
 * project_add.php — Create a new project
 */
require_once __DIR__ . '/functions.php';

$errors = [];
$p = ['status' => 'pending', 'progress' => 0, 'amount' => 0];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = collect_project_post();

    if ($data['project_no'] === '')   $errors[] = 'กรุณากรอก Project No.';
    if ($data['project_name'] === '') $errors[] = 'กรุณากรอกชื่อโครงการ';

    // duplicate check
    if (!$errors) {
        $stmt = db()->prepare('SELECT COUNT(*) FROM projects WHERE project_no = :no');
        $stmt->execute([':no' => $data['project_no']]);
        if ($stmt->fetchColumn() > 0) {
            $errors[] = 'Project No. นี้มีอยู่แล้วในระบบ';
        }
    }

    if (!$errors) {
        $attachment = handle_upload('attachment');
        $id = create_project($data, $attachment);
        save_project_attachments($id, 'attachments');
        header('Location: projects.php?msg=' . rawurlencode('เพิ่มโครงการเรียบร้อยแล้ว'));
        exit;
    }
    $p = array_merge($p, $data);
}

$isEdit = false;

render_header('เพิ่มโครงการใหม่', 'project_add.php');
?>

<?php if ($errors): ?>
  <div class="alert alert-danger">
    <ul class="mb-0"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/_project_form.php'; ?>

<?php render_footer(); ?>
