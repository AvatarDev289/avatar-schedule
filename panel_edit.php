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
        save_panel_attachments($id, 'panel_attachments');

        $msg = 'บันทึกการแก้ไขตู้แล้ว';
        if (!empty($_POST['auto_tasks'])) {
            $tpl            = trim($_POST['task_template'] ?? 'auto');
            $conflictAction = $_POST['conflict_action'] ?? 'add_missing';
            $existing       = count_panel_tasks($id);
            $startDate      = $data['planned_start_date'] ?? $pn['planned_start_date'] ?? date('Y-m-d');
            $projId         = (int)$pn['project_id'];
            $panelType      = $data['panel_type'] ?? $pn['panel_type'] ?? '';

            // Resolve DB template ID
            $dbTid = null;
            if (str_starts_with($tpl, 'db:')) {
                $dbTid = (int)substr($tpl, 3);
            } elseif ($tpl === 'auto') {
                $dbTid = resolve_db_template($panelType);
            }

            if ($conflictAction === 'skip' && $existing > 0) {
                // do nothing
            } elseif ($existing > 0 && $conflictAction === 'replace') {
                delete_panel_tasks($id);
                if ($dbTid) {
                    [$created] = create_tasks_from_db_template($projId, $id, $dbTid, $startDate);
                } else {
                    $created = create_panel_auto_tasks($projId, $id, $tpl);
                }
                $msg = 'แก้ไขตู้และสร้าง ' . ($created ?? 0) . ' ขั้นตอนใหม่ (แทนที่ของเดิม)';
            } elseif ($existing > 0 && $conflictAction === 'add_missing') {
                if ($dbTid) {
                    [$created, $skipped] = create_tasks_from_db_template($projId, $id, $dbTid, $startDate, skipExisting: true);
                } else {
                    $created = create_panel_auto_tasks($projId, $id, $tpl, skipExisting: true);
                }
                $msg = 'แก้ไขตู้' . (($created ?? 0) > 0 ? ' และเพิ่ม ' . $created . ' ขั้นตอนที่ยังขาด' : ' (ไม่มีขั้นตอนใหม่ที่ต้องเพิ่ม)');
            } else {
                // No existing tasks
                if ($dbTid) {
                    [$created] = create_tasks_from_db_template($projId, $id, $dbTid, $startDate);
                } else {
                    $created = create_panel_auto_tasks($projId, $id, $tpl);
                }
                $msg = 'แก้ไขตู้และสร้าง ' . ($created ?? 0) . ' ขั้นตอนงานอัตโนมัติ';
            }
        }

        header('Location: project_view.php?id=' . (int)$pn['project_id']
               . '&msg=' . rawurlencode($msg)
               . '&open_panel=' . $id . '#panels');
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
$html = preg_replace('/(<form\b[^>]*>)/', '$1' . "\n  <input type=\"hidden\" name=\"id\" value=\"" . (int)$id . "\">", $html, 1);
echo $html;
?>
<?php render_footer(); ?>
