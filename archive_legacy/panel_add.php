<?php
/**
 * panel_add.php — add a panel to a project
 */
require_once __DIR__ . '/functions.php';

$projectId = (int)($_GET['project_id'] ?? $_POST['project_id'] ?? 0);
$project   = get_project($projectId);

if (!$project) {
    render_header('ไม่พบโครงการ', 'projects.php');
    echo '<div class="alert alert-warning">ไม่พบโครงการสำหรับเพิ่มตู้</div>';
    render_footer();
    exit;
}

$errors = [];
$pn = ['status' => 'pending'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = collect_panel_post();
    if ($data['panel_no'] === '')   $errors[] = 'กรุณากรอก Panel No.';
    if ($data['panel_name'] === '') $errors[] = 'กรุณากรอกชื่อตู้';

    if (!$errors) {
        $panelId = create_panel($projectId, $data);

        $msg = 'เพิ่มตู้เรียบร้อยแล้ว';
        if (!empty($_POST['auto_tasks'])) {
            $tpl       = trim($_POST['task_template'] ?? 'auto');
            $startDate = $data['planned_start_date'] ?? date('Y-m-d');

            if (str_starts_with($tpl, 'db:')) {
                $dbTid = (int)substr($tpl, 3);
                [$created] = create_tasks_from_db_template($projectId, $panelId, $dbTid, $startDate);
            } elseif ($tpl === 'auto') {
                // Auto-detect DB template by panel_type
                $dbTid = resolve_db_template($data['panel_type'] ?? '');
                if ($dbTid) {
                    [$created] = create_tasks_from_db_template($projectId, $panelId, $dbTid, $startDate);
                } else {
                    $created = create_panel_auto_tasks($projectId, $panelId, 'auto');
                }
            } else {
                $created = create_panel_auto_tasks($projectId, $panelId, $tpl);
            }

            if (($created ?? 0) > 0) {
                $msg = 'เพิ่มตู้และสร้าง ' . $created . ' ขั้นตอนงานอัตโนมัติแล้ว';
            }
        }

        header('Location: project_view.php?id=' . $projectId
               . '&msg=' . rawurlencode($msg)
               . '&open_panel=' . $panelId . '#panels');
        exit;
    }
    $pn = array_merge($pn, $data);
}

$isEdit = false;
render_header('เพิ่มตู้ — ' . $project['project_no'], 'projects.php');
?>
<div class="mb-3">
  <a href="project_view.php?id=<?= (int)$projectId ?>#panels" class="text-decoration-none"><i class="bi bi-arrow-left"></i> กลับไปที่โครงการ <?= e($project['project_no']) ?></a>
</div>
<?php if ($errors): ?>
  <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<?php
ob_start();
require __DIR__ . '/_panel_form.php';
$html = ob_get_clean();
$html = preg_replace('/(<form[^>]*>)/', '$1' . "\n  <input type=\"hidden\" name=\"project_id\" value=\"" . (int)$projectId . "\">", $html, 1);
echo $html;
?>
<?php render_footer(); ?>
