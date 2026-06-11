<?php
/**
 * project_edit.php — Edit an existing project
 */
require_once __DIR__ . '/functions.php';

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$p  = get_project($id);

if (!$p) {
    render_header('ไม่พบโครงการ', 'projects.php');
    echo '<div class="alert alert-warning">ไม่พบโครงการที่ต้องการแก้ไข</div>';
    render_footer();
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = collect_project_post();

    if ($data['project_no'] === '')   $errors[] = 'กรุณากรอก Project No.';
    if ($data['project_name'] === '') $errors[] = 'กรุณากรอกชื่อโครงการ';

    if (!$errors) {
        $stmt = db()->prepare('SELECT COUNT(*) FROM projects WHERE project_no = :no AND id <> :id');
        $stmt->execute([':no' => $data['project_no'], ':id' => $id]);
        if ($stmt->fetchColumn() > 0) {
            $errors[] = 'Project No. นี้ถูกใช้กับโครงการอื่นแล้ว';
        }
    }

    if (!$errors) {
        $attachment = handle_upload('attachment'); // null if no new file
        update_project($id, $data, $attachment);
        header('Location: project_view.php?id=' . $id . '&msg=' . rawurlencode('บันทึกการแก้ไขแล้ว'));
        exit;
    }
    // keep submitted values, but preserve existing attachment for display
    $p = array_merge($p, $data);
}

$isEdit          = true;
$panelProgExists = (project_progress_from_panels($id) !== null);

render_header('แก้ไขโครงการ ' . $p['project_no'], 'projects.php');
?>

<?php if ($errors): ?>
  <div class="alert alert-danger">
    <ul class="mb-0"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
  </div>
<?php endif; ?>

<?php
// inject a hidden id field into the shared form via output buffering:
ob_start();
require __DIR__ . '/_project_form.php';
$formHtml = ob_get_clean();
// add hidden id field right after the opening form tag
$formHtml = preg_replace('/(<form[^>]*>)/',
    '$1' . "\n  <input type=\"hidden\" name=\"id\" value=\"" . (int)$id . "\">", $formHtml, 1);
echo $formHtml;
?>

<?php render_footer(); ?>
