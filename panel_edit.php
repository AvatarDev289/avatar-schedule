<?php
/**
 * panel_edit.php — edit a panel
 */
require_once __DIR__ . '/functions.php';

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$pn = get_panel($id);

if (!$pn) {
    render_header('ไม่พบตู้', 'projects.php');
    echo '<div class="alert alert-warning">ไม่พบตู้ที่ต้องการแก้ไข</div>';
    render_footer();
    exit;
}

$project = get_project((int)$pn['project_id']);
$errors  = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = collect_panel_post();
    if ($data['panel_no'] === '')   $errors[] = 'กรุณากรอก Panel No.';
    if ($data['panel_name'] === '') $errors[] = 'กรุณากรอกชื่อตู้';

    if (!$errors) {
        update_panel($id, $data);
        header('Location: project_view.php?id=' . (int)$pn['project_id'] . '&msg=' . rawurlencode('บันทึกการแก้ไขตู้แล้ว') . '#panels');
        exit;
    }
    $pn = array_merge($pn, $data);
}

$isEdit = true;
render_header('แก้ไขตู้ ' . $pn['panel_no'], 'projects.php');
?>
<div class="mb-3">
  <a href="project_view.php?id=<?= (int)$pn['project_id'] ?>#panels" class="text-decoration-none"><i class="bi bi-arrow-left"></i> กลับไปที่โครงการ <?= e($project['project_no']) ?></a>
</div>
<?php if ($errors): ?>
  <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<?php
ob_start();
require __DIR__ . '/_panel_form.php';
$html = ob_get_clean();
$html = preg_replace('/(<form[^>]*>)/', '$1' . "\n  <input type=\"hidden\" name=\"id\" value=\"" . (int)$id . "\">", $html, 1);
echo $html;
?>
<?php render_footer(); ?>
