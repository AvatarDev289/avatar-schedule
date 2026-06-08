<?php
/**
 * api/task_update.php — AJAX endpoint for task status + date updates.
 * Returns JSON: {ok, task, updated_tasks, panel, project}
 *
 * Actions:
 *   status  → update task status (pending|in_progress|completed)
 *   dates   → update planned start/finish; optionally cascade to subsequent tasks
 */
declare(strict_types=1);
require_once dirname(__DIR__) . '/functions.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$body      = json_decode(file_get_contents('php://input'), true) ?? [];
$action    = $body['action']     ?? '';
$projectId = (int)($body['project_id'] ?? 0);
$taskId    = (int)($body['task_id']    ?? 0);

if (!$projectId || !$taskId || !in_array($action, ['status', 'dates'], true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid params']);
    exit;
}

// Security: task must belong to this project
$st = db()->prepare("SELECT * FROM project_tasks WHERE id = :id AND project_id = :pid");
$st->execute([':id' => $taskId, ':pid' => $projectId]);
$task = $st->fetch();

if (!$task) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'task not found']);
    exit;
}

$panelId = (int)$task['panel_id'];

/* -----------------------------------------------------------------------
 *  Action: status
 * --------------------------------------------------------------------- */
if ($action === 'status') {
    $allowed   = ['pending', 'in_progress', 'completed'];
    $newStatus = in_array($body['new_status'] ?? '', $allowed, true) ? $body['new_status'] : 'pending';
    update_task_status($taskId, $newStatus);
}

/* -----------------------------------------------------------------------
 *  Action: dates
 * --------------------------------------------------------------------- */
if ($action === 'dates') {
    // Server-side lock: reject if task has already started
    if (!is_task_plan_editable($task)) {
        echo json_encode(['ok' => false, 'error' => 'task_started']);
        exit;
    }

    $newStart  = preg_match('/^\d{4}-\d{2}-\d{2}$/', $body['planned_start']  ?? '') ? $body['planned_start']  : null;
    $newFinish = preg_match('/^\d{4}-\d{2}-\d{2}$/', $body['planned_finish'] ?? '') ? $body['planned_finish'] : null;
    $newDur    = isset($body['duration_days']) && is_numeric($body['duration_days']) ? max(1, (int)$body['duration_days']) : null;
    $doRecalc  = !empty($body['recalculate']);

    $sets   = [];
    $params = [':id' => $taskId];
    if ($newStart)        { $sets[] = 'start_date    = :s'; $params[':s'] = $newStart; }
    if ($newFinish)       { $sets[] = 'due_date       = :f'; $params[':f'] = $newFinish; }
    if ($newDur !== null) { $sets[] = 'duration_days  = :d'; $params[':d'] = $newDur; }
    if ($sets) {
        db()->prepare('UPDATE project_tasks SET ' . implode(', ', $sets) . ' WHERE id = :id')
            ->execute($params);
    }
    if ($doRecalc) {
        recalculate_task_dates_from($panelId, (int)$task['sort_order'] + 1);
    }
    recompute_panel_from_tasks($panelId);
}

/* -----------------------------------------------------------------------
 *  Build response
 * --------------------------------------------------------------------- */
$updSt = db()->prepare("SELECT * FROM project_tasks WHERE id = :id");
$updSt->execute([':id' => $taskId]);
$updTask = $updSt->fetch();

$updPanel   = get_panel($panelId);
$projProgSt = db()->prepare("SELECT progress FROM projects WHERE id = :id");
$projProgSt->execute([':id' => $projectId]);
$projProgress = (int)$projProgSt->fetchColumn();

$delay       = get_task_delay_info($updTask);
$tStatus     = $updTask['status'] ?? 'pending';
$tIsOverdue  = $delay['type'] === 'overdue';

$taskResp = [
    'id'                    => $taskId,
    'status'                => $tStatus,
    'progress_percent'      => (int)$updTask['progress_percent'],
    'status_color'          => task_status_color($tIsOverdue ? 'overdue' : $tStatus),
    'actual_start_date'     => $updTask['actual_start_date'],
    'actual_start_date_dmy' => format_date_dmy($updTask['actual_start_date']),
    'completed_date'        => $updTask['completed_date'],
    'completed_date_dmy'    => format_date_dmy($updTask['completed_date']),
    'start_date'            => $updTask['start_date'],
    'start_date_dmy'        => format_date_dmy($updTask['start_date']),
    'due_date'              => $updTask['due_date'],
    'due_date_dmy'          => format_date_dmy($updTask['due_date']),
    'is_overdue'            => $tIsOverdue,
    'delay'                 => $delay,
    'delay_badge_html'      => task_delay_badge_html($updTask),
];

// For recalculate: include all sibling tasks with updated dates
$updatedTasks = [];
if ($action === 'dates' && !empty($body['recalculate'])) {
    foreach (get_panel_tasks($panelId) as $t) {
        if ((int)$t['id'] === $taskId) continue;
        $td = get_task_delay_info($t);
        $updatedTasks[] = [
            'id'              => (int)$t['id'],
            'start_date'      => $t['start_date'],
            'start_date_dmy'  => format_date_dmy($t['start_date']),
            'due_date'        => $t['due_date'],
            'due_date_dmy'    => format_date_dmy($t['due_date']),
            'delay'           => $td,
            'delay_badge_html'=> task_delay_badge_html($t),
        ];
    }
}

$panelStatus = $updPanel['status'] ?? 'pending';
$panelLabel  = panel_effective_label($updPanel);
$panelColor  = panel_status_color($panelStatus);

echo json_encode([
    'ok'            => true,
    'task'          => $taskResp,
    'updated_tasks' => $updatedTasks,
    'panel'         => [
        'id'               => $panelId,
        'status'           => $panelStatus,
        'status_label'     => $panelLabel,
        'status_color'     => $panelColor,
        'progress_percent' => (int)$updPanel['progress_percent'],
    ],
    'project'       => [
        'id'       => $projectId,
        'progress' => $projProgress,
    ],
], JSON_UNESCAPED_UNICODE);
