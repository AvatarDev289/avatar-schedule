<?php
/**
 * functions.php — Helper functions + page layout
 * Avatar Electric — Schedule of Project Dashboard
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';

/* ======================================================================
 *  Small utilities
 * ==================================================================== */

/** Escape for safe HTML output. */
function e($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/** Format a money amount (THB). */
function money($amount): string
{
    return number_format((float)$amount, 0);
}

/** Format a money amount with 2 decimals. */
function money2($amount): string
{
    return number_format((float)$amount, 2);
}

/**
 * Format a date for display.
 * Thai Buddhist year + short month, e.g. 15 มี.ค. 2569
 */
function format_date(?string $date): string
{
    if (empty($date) || $date === '0000-00-00') {
        return '-';
    }
    $ts = strtotime($date);
    if ($ts === false) {
        return '-';
    }
    $months = [1=>'ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.',
               'ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];
    $d = (int)date('j', $ts);
    $m = (int)date('n', $ts);
    $y = (int)date('Y', $ts) + 543;
    return sprintf('%d %s %d', $d, $months[$m], $y);
}

/** Format date as dd/mm/yyyy (Gregorian). Returns '-' for empty / invalid dates. */
function format_date_dmy(?string $date): string
{
    if (empty($date) || $date === '0000-00-00') return '-';
    $ts = strtotime($date);
    if ($ts === false) return '-';
    return date('d/m/Y', $ts);
}

/**
 * Parse user-entered "DD/MM/YYYY" into "YYYY-MM-DD" for database storage.
 * Also accepts "YYYY-MM-DD" passthrough (already correct format).
 * Returns null for empty, invalid, or out-of-range dates.
 */
function parse_date_dmy(?string $raw): ?string
{
    if ($raw === null || trim($raw) === '') {
        return null;
    }
    $raw = trim($raw);
    // Passthrough: already YYYY-MM-DD
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
        $ts = strtotime($raw);
        return ($ts !== false) ? date('Y-m-d', $ts) : null;
    }
    // Parse DD/MM/YYYY
    if (!preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $raw, $m)) {
        return null;
    }
    [$_, $d, $mo, $y] = $m;
    if (!checkdate((int)$mo, (int)$d, (int)$y)) {
        return null;
    }
    return sprintf('%04d-%02d-%02d', (int)$y, (int)$mo, (int)$d);
}

/* ======================================================================
 *  Status logic
 * ==================================================================== */

/** All status keys -> Thai labels. */
function status_labels(): array
{
    return [
        'pending'          => 'รอเริ่มงาน',
        'in_progress'      => 'กำลังดำเนินการ',
        'near_due'         => 'ใกล้ครบกำหนด',
        'partial_delivery' => 'ส่งมอบบางส่วน',
        'delivered'        => 'ส่งมอบแล้ว',
        'completed'        => 'เสร็จแล้ว',
        'overdue'          => 'ล่าช้า / Overdue',
    ];
}

/** All status keys -> hex colors (Avatar Electric brand palette). */
function status_colors(): array
{
    return [
        'pending'          => '#6B7280', // gray-500
        'in_progress'      => '#3B82F6', // info blue
        'near_due'         => '#F59E0B', // warning
        'partial_delivery' => '#8B5CF6', // violet
        'delivered'        => '#059669', // emerald-dark
        'completed'        => '#10B981', // success
        'overdue'          => '#EF4444', // danger
    ];
}

function status_label(string $status): string
{
    return status_labels()[$status] ?? $status;
}

function status_color(string $status): string
{
    return status_colors()[$status] ?? '#6B7280';
}

/**
 * Mix a hex color with white (returns a lighter hex).
 * $colorPct = how much of the original color to keep (0..1).
 * Used instead of CSS color-mix() so html2canvas can render it.
 */
function hex_tint(string $hex, float $colorPct = 0.14): string
{
    $hex = ltrim($hex, '#');
    if (strlen($hex) === 3) {
        $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
    }
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    $mix = fn($c) => (int)round($colorPct * $c + (1 - $colorPct) * 255);
    return sprintf('#%02x%02x%02x', $mix($r), $mix($g), $mix($b));
}

/** Bootstrap badge classes per status. */
function status_badge_class(string $status): string
{
    return [
        'pending'          => 'badge-status pending',
        'in_progress'      => 'badge-status in_progress',
        'near_due'         => 'badge-status near_due',
        'partial_delivery' => 'badge-status partial_delivery',
        'delivered'        => 'badge-status delivered',
        'completed'        => 'badge-status completed',
        'overdue'          => 'badge-status overdue',
    ][$status] ?? 'badge-status pending';
}

/**
 * Compute the effective status of a project from its panel statuses.
 * Panel status counts (panel_total / panel_delivered / panel_overdue / panel_in_progress)
 * must be pre-joined by get_projects() / get_project().
 * Priority: completed > delivered > overdue > partial_delivery > in_progress > pending
 */
function compute_status(array $p): string
{
    if (!empty($p['completed_date']) && $p['completed_date'] !== '0000-00-00') {
        return 'completed';
    }

    $total       = (int)($p['panel_total']       ?? 0);
    $delivered   = (int)($p['panel_delivered']   ?? 0);
    $overdue     = (int)($p['panel_overdue']     ?? 0);
    $inProgress  = (int)($p['panel_in_progress'] ?? 0);

    if ($total === 0) return 'pending';
    if ($delivered >= $total) return 'delivered';
    if ($overdue > 0) return 'overdue';
    if ($delivered > 0) return 'partial_delivery';
    if ($inProgress > 0) return 'in_progress';
    return 'pending';
}

/**
 * Color for a task's 3-state status slug.
 */
function task_status_color(string $status): string
{
    return match ($status) {
        'in_progress' => '#FF7A00',
        'completed'   => '#059669',
        'overdue'     => '#EF4444',
        default       => '#9CA3AF',
    };
}

/**
 * Compute delay info for a single task array.
 * Returns: type (overdue|late_done|early|on_time|on_track|no_date), days (int), label (string), color (string).
 */
function get_task_delay_info(array $task): array
{
    $today    = date('Y-m-d');
    $status   = $task['status'] ?? 'pending';
    $dueDate  = $task['due_date'] ?? '';
    $doneDate = $task['completed_date'] ?? '';

    if ($status === 'completed') {
        if (empty($dueDate) || empty($doneDate)) {
            return ['type' => 'on_time', 'days' => 0, 'label' => 'ตรงแผน', 'color' => '#059669'];
        }
        $diff = (int)floor((strtotime($doneDate) - strtotime($dueDate)) / 86400);
        if ($diff > 0) {
            return ['type' => 'late_done', 'days' => $diff, 'label' => 'เสร็จล่าช้า ' . $diff . ' วัน', 'color' => '#F97316'];
        }
        if ($diff < 0) {
            return ['type' => 'early', 'days' => abs($diff), 'label' => 'ก่อนกำหนด ' . abs($diff) . ' วัน', 'color' => '#059669'];
        }
        return ['type' => 'on_time', 'days' => 0, 'label' => 'ตรงแผน', 'color' => '#059669'];
    }

    if (empty($dueDate)) {
        return ['type' => 'no_date', 'days' => 0, 'label' => '—', 'color' => '#9CA3AF'];
    }

    if ($dueDate < $today) {
        $diff = (int)floor((strtotime($today) - strtotime($dueDate)) / 86400);
        return ['type' => 'overdue', 'days' => $diff, 'label' => 'ล่าช้า ' . $diff . ' วัน', 'color' => '#EF4444'];
    }

    return ['type' => 'on_track', 'days' => 0, 'label' => '—', 'color' => '#9CA3AF'];
}

/**
 * Task plan (planned_start/finish/duration) is editable only when task has not started.
 * "Not started" = status is pending AND actual_start_date is empty AND progress_percent is 0.
 */
function is_task_plan_editable(array $task): bool
{
    if (($task['status'] ?? 'pending') !== 'pending') return false;
    if (!empty($task['actual_start_date']))           return false;
    if ((int)($task['progress_percent'] ?? 0) > 0)   return false;
    return true;
}

/**
 * Render HTML badge for task delay (used in PHP template + returned by AJAX).
 */
function task_delay_badge_html(array $task): string
{
    $d = get_task_delay_info($task);
    if ($d['type'] === 'on_track' || $d['type'] === 'no_date') {
        return '<span class="text-muted small">—</span>';
    }
    $icon = match ($d['type']) {
        'overdue'   => '🔴',
        'late_done' => '🟠',
        'early'     => '🟢',
        'on_time'   => '🟢',
        default     => '',
    };
    return '<span style="background:' . e($d['color']) . ';color:#fff;padding:1px 6px;border-radius:20px;font-size:10px;white-space:nowrap">'
        . $icon . ' ' . e($d['label']) . '</span>';
}

/**
 * Number of overdue days (positive = late, 0 = not overdue).
 */
function overdue_days(array $p): int
{
    if (!empty($p['completed_date'])) {
        return 0;
    }
    if (empty($p['due_date'])) {
        return 0;
    }
    $today = strtotime(date('Y-m-d'));
    $due   = strtotime($p['due_date']);
    if ($due >= $today) {
        return 0;
    }
    return (int)floor(($today - $due) / 86400);
}

/** Days remaining until due (negative = past due). */
function days_remaining(array $p): ?int
{
    if (empty($p['due_date'])) {
        return null;
    }
    $today = strtotime(date('Y-m-d'));
    $due   = strtotime($p['due_date']);
    return (int)floor(($due - $today) / 86400);
}

/* ======================================================================
 *  Data access helpers
 * ==================================================================== */

/** Fetch all projects (joined with dept + responsible person + panel delivery counts). */
function get_projects(array $filters = []): array
{
    $sql = "SELECT p.*, d.name AS department, u.name AS responsible,
                   COALESCE(pc.panel_total, 0)       AS panel_total,
                   COALESCE(pc.panel_delivered, 0)   AS panel_delivered,
                   COALESCE(pc.panel_overdue, 0)     AS panel_overdue,
                   COALESCE(pc.panel_in_progress, 0) AS panel_in_progress
            FROM projects p
            LEFT JOIN departments d ON d.id = p.department_id
            LEFT JOIN users u       ON u.id = p.responsible_id
            LEFT JOIN (
                SELECT project_id,
                       COUNT(*) AS panel_total,
                       SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) AS panel_delivered,
                       SUM(CASE WHEN status = 'overdue'   THEN 1 ELSE 0 END) AS panel_overdue,
                       SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) AS panel_in_progress
                FROM project_panels
                GROUP BY project_id
            ) pc ON pc.project_id = p.id";
    $where  = [];
    $params = [];

    if (!empty($filters['search'])) {
        $where[] = "(p.project_no LIKE :s OR p.project_name LIKE :s OR p.customer LIKE :s)";
        $params[':s'] = '%' . $filters['search'] . '%';
    }
    if (!empty($filters['department_id'])) {
        $where[] = "p.department_id = :dept";
        $params[':dept'] = (int)$filters['department_id'];
    }

    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY p.due_date IS NULL, p.due_date ASC, p.id DESC';

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    // attach computed status to every row
    foreach ($rows as &$r) {
        $r['effective_status'] = compute_status($r);
    }
    unset($r);

    // optional status filter (applied on computed status)
    if (!empty($filters['status'])) {
        $rows = array_values(array_filter(
            $rows,
            fn($r) => $r['effective_status'] === $filters['status']
        ));
    }

    return $rows;
}

/** Fetch a single project by id (with joins + panel delivery counts). */
function get_project(int $id): ?array
{
    $stmt = db()->prepare(
        "SELECT p.*, d.name AS department, u.name AS responsible,
                COALESCE(pc.panel_total, 0)       AS panel_total,
                COALESCE(pc.panel_delivered, 0)   AS panel_delivered,
                COALESCE(pc.panel_overdue, 0)     AS panel_overdue,
                COALESCE(pc.panel_in_progress, 0) AS panel_in_progress
         FROM projects p
         LEFT JOIN departments d ON d.id = p.department_id
         LEFT JOIN users u       ON u.id = p.responsible_id
         LEFT JOIN (
             SELECT project_id,
                    COUNT(*) AS panel_total,
                    SUM(CASE WHEN status = 'delivered'   THEN 1 ELSE 0 END) AS panel_delivered,
                    SUM(CASE WHEN status = 'overdue'     THEN 1 ELSE 0 END) AS panel_overdue,
                    SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) AS panel_in_progress
             FROM project_panels
             GROUP BY project_id
         ) pc ON pc.project_id = p.id
         WHERE p.id = :id"
    );
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }
    $row['effective_status'] = compute_status($row);
    return $row;
}

function get_departments(): array
{
    return db()->query("SELECT * FROM departments ORDER BY name")->fetchAll();
}

function get_users(): array
{
    return db()->query("SELECT * FROM users ORDER BY name")->fetchAll();
}

/**
 * Build dashboard statistics from the project list.
 * Returns counts and amounts per status + totals.
 */
function dashboard_stats(array $projects): array
{
    $keys = array_keys(status_labels());
    $count  = array_fill_keys($keys, 0);
    $amount = array_fill_keys($keys, 0.0);

    $totalAmount = 0.0;
    foreach ($projects as $p) {
        $s = $p['effective_status'];
        $count[$s]++;
        $amount[$s] += (float)$p['amount'];
        $totalAmount += (float)$p['amount'];
    }

    return [
        'total'        => count($projects),
        'count'        => $count,
        'amount'       => $amount,
        'total_amount' => $totalAmount,
    ];
}

/**
 * Late item counts across all projects.
 * "Late" = due_date < today AND not done.
 */
function get_late_stats(): array
{
    $today = date('Y-m-d');
    $lateTasks = (int)db()->query(
        "SELECT COUNT(*) FROM project_tasks
         WHERE due_date IS NOT NULL AND due_date < '$today'
           AND status NOT IN ('completed')"
    )->fetchColumn();

    $lateCabinets = (int)db()->query(
        "SELECT COUNT(*) FROM project_panels WHERE status = 'overdue'"
    )->fetchColumn();

    $lateProjects = (int)db()->query(
        "SELECT COUNT(DISTINCT project_id) FROM project_panels WHERE status = 'overdue'"
    )->fetchColumn();

    return [
        'late_tasks'    => $lateTasks,
        'late_cabinets' => $lateCabinets,
        'late_projects' => $lateProjects,
    ];
}

/** Count of projects grouped by month of due_date (current year). */
function projects_per_month(array $projects): array
{
    $months = array_fill(1, 12, 0);
    foreach ($projects as $p) {
        if (!empty($p['due_date'])) {
            $m = (int)date('n', strtotime($p['due_date']));
            $months[$m]++;
        }
    }
    return $months;
}

/* ======================================================================
 *  Project sub-data (tasks / panels / milestones / deliveries)
 * ==================================================================== */

function get_project_tasks(int $projectId): array
{
    $stmt = db()->prepare(
        "SELECT * FROM project_tasks WHERE project_id = :id
         ORDER BY sort_order, start_date, id"
    );
    $stmt->execute([':id' => $projectId]);
    return $stmt->fetchAll();
}

function get_project_panels(int $projectId): array
{
    $stmt = db()->prepare(
        "SELECT * FROM project_panels WHERE project_id = :id ORDER BY id"
    );
    $stmt->execute([':id' => $projectId]);
    $rows = $stmt->fetchAll();
    foreach ($rows as &$r) {
        $r['eff_status'] = compute_panel_status($r);
    }
    unset($r);
    return sort_panels($rows);
}

/**
 * Validate that the given panel ids really belong to $projectId.
 * Returns the subset of ids that are valid (as ints). Uses a prepared
 * statement with one placeholder per id — ids are never concatenated.
 */
function validate_panel_ids(int $projectId, array $ids): array
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn($v) => $v > 0)));
    if (!$ids) {
        return [];
    }
    $place = implode(',', array_fill(0, count($ids), '?'));
    $stmt  = db()->prepare("SELECT id FROM project_panels WHERE project_id = ? AND id IN ($place)");
    $stmt->execute(array_merge([$projectId], $ids));
    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

/** Fetch specific (already-validated) panels of a project, enriched. */
function get_project_panels_by_ids(int $projectId, array $ids): array
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn($v) => $v > 0)));
    if (!$ids) {
        return [];
    }
    $place = implode(',', array_fill(0, count($ids), '?'));
    $stmt  = db()->prepare(
        "SELECT * FROM project_panels WHERE project_id = ? AND id IN ($place) ORDER BY id"
    );
    $stmt->execute(array_merge([$projectId], $ids));
    $rows = $stmt->fetchAll();
    foreach ($rows as &$r) {
        $r['eff_status'] = compute_panel_status($r);
    }
    unset($r);
    return sort_panels($rows);
}

/* ======================================================================
 *  DB-backed Task Template System
 * ==================================================================== */

/** Fetch all active templates for dropdown. */
function get_task_templates(bool $activeOnly = true): array
{
    $sql = 'SELECT * FROM task_templates' . ($activeOnly ? ' WHERE is_active = 1' : '') . ' ORDER BY sort_order, id';
    return db()->query($sql)->fetchAll();
}

/** Fetch a single template by id. */
function get_task_template(int $id): ?array
{
    $st = db()->prepare('SELECT * FROM task_templates WHERE id = :id');
    $st->execute([':id' => $id]);
    return $st->fetch() ?: null;
}

/** Fetch all items for a template, ordered by sort_order. */
function get_task_template_items(int $templateId): array
{
    $st = db()->prepare('SELECT * FROM task_template_items WHERE template_id = :tid ORDER BY sort_order, id');
    $st->execute([':tid' => $templateId]);
    return $st->fetchAll();
}

/**
 * Auto-detect the best DB template for a panel_type string.
 * Matches cabinet_type prefix (case-insensitive). Falls back to 'Default Workflow'.
 */
function resolve_db_template(string $panelType): ?int
{
    $type = mb_strtoupper(trim($panelType));
    $rows = db()->query("SELECT id, cabinet_type FROM task_templates WHERE is_active = 1 AND cabinet_type IS NOT NULL ORDER BY sort_order, id")->fetchAll();
    foreach ($rows as $r) {
        if ($type !== '' && str_starts_with($type, mb_strtoupper(trim($r['cabinet_type'])))) {
            return (int)$r['id'];
        }
    }
    // Fallback: Default Workflow
    $st = db()->prepare("SELECT id FROM task_templates WHERE is_active = 1 ORDER BY sort_order, id LIMIT 1");
    $st->execute();
    $row = $st->fetch();
    return $row ? (int)$row['id'] : null;
}

/**
 * Create project_tasks from a DB template with auto-calculated planned dates.
 * $startDate: 'YYYY-MM-DD' — planned start of the cabinet.
 * $skipExisting: true = skip tasks with same name (case-insensitive).
 * Returns [created, skipped].
 */
function create_tasks_from_db_template(
    int    $projectId,
    int    $panelId,
    int    $templateId,
    string $startDate,
    bool   $skipExisting = false
): array {
    $items = get_task_template_items($templateId);
    if (!$items) return [0, 0];

    $existingNames = [];
    if ($skipExisting) {
        $st = db()->prepare('SELECT LOWER(task_name) FROM project_tasks WHERE panel_id = :pid');
        $st->execute([':pid' => $panelId]);
        $existingNames = $st->fetchAll(PDO::FETCH_COLUMN);
    }

    $ins = db()->prepare(
        "INSERT INTO project_tasks
           (project_id, panel_id, task_name, task_type, duration_days, sort_order,
            start_date, due_date, status, progress_percent,
            is_auto_created, template_name, template_id, template_item_id)
         VALUES
           (:proj, :pid, :name, :type, :dur, :ord,
            :ps, :pf, 'pending', 0,
            1, :tname, :tid, :iid)"
    );

    $tpl     = get_task_template($templateId);
    $tplName = $tpl ? $tpl['template_name'] : '';
    $cursor  = $startDate;
    $created = 0;
    $skipped = 0;

    foreach ($items as $item) {
        if ($skipExisting && in_array(mb_strtolower($item['task_name']), $existingNames, true)) {
            $skipped++;
            // Still advance cursor so ordering stays correct
            $cursor = date('Y-m-d', strtotime($cursor . ' +' . max(1, (int)$item['duration_days']) . ' days'));
            continue;
        }
        $dur     = max(1, (int)$item['duration_days']);
        $psDate  = $cursor;
        $pfDate  = date('Y-m-d', strtotime($psDate . ' +' . ($dur - 1) . ' days'));
        $ins->execute([
            ':proj' => $projectId,
            ':pid'  => $panelId,
            ':name' => $item['task_name'],
            ':type' => $item['task_type'] ?? null,
            ':dur'  => $dur,
            ':ord'  => (int)$item['sort_order'],
            ':ps'   => $psDate,
            ':pf'   => $pfDate,
            ':tname'=> $tplName,
            ':tid'  => $templateId,
            ':iid'  => (int)$item['id'],
        ]);
        $cursor = date('Y-m-d', strtotime($pfDate . ' +1 day'));
        $created++;
    }

    // Stamp panel planned_start_date if not already set
    if ($created > 0) {
        db()->prepare(
            "UPDATE project_panels
             SET planned_start_date = COALESCE(planned_start_date, :ps)
             WHERE id = :id"
        )->execute([':ps' => $startDate, ':id' => $panelId]);
        log_activity($projectId, 'auto_tasks', 'สร้างขั้นตอน ' . $created . ' รายการ จาก Template [' . $tplName . ']');
    }

    return [$created, $skipped];
}

/**
 * Recalculate planned_start_date / due_date for all tasks in a panel starting from a given sort_order.
 * Uses each task's duration_days to chain dates sequentially.
 */
function recalculate_task_dates_from(int $panelId, int $fromSortOrder = 1): void
{
    $tasks = db()->prepare(
        "SELECT id, sort_order, duration_days, start_date, due_date
         FROM project_tasks WHERE panel_id = :pid ORDER BY sort_order, id"
    );
    $tasks->execute([':pid' => $panelId]);
    $all = $tasks->fetchAll();
    if (!$all) return;

    // Find the cursor: due_date of task just before fromSortOrder
    $cursor = null;
    foreach ($all as $t) {
        if ((int)$t['sort_order'] < $fromSortOrder) {
            if (!empty($t['due_date'])) {
                $cursor = date('Y-m-d', strtotime($t['due_date'] . ' +1 day'));
            }
        }
    }
    // If no prior task, use first task's current start_date
    if ($cursor === null) {
        $cursor = $all[0]['start_date'] ?? date('Y-m-d');
    }

    $upd = db()->prepare(
        "UPDATE project_tasks SET start_date = :ps, due_date = :pf WHERE id = :id"
    );

    foreach ($all as $t) {
        if ((int)$t['sort_order'] < $fromSortOrder) continue;
        $dur    = max(1, (int)$t['duration_days']);
        $psDate = $cursor;
        $pfDate = date('Y-m-d', strtotime($psDate . ' +' . ($dur - 1) . ' days'));
        $upd->execute([':ps' => $psDate, ':pf' => $pfDate, ':id' => $t['id']]);
        $cursor = date('Y-m-d', strtotime($pfDate . ' +1 day'));
    }
}

/* ======================================================================
 *  Auto Workflow / Task Templates  (legacy built-in array, kept for compat)
 * ==================================================================== */

/** @deprecated Use DB-backed templates via get_task_templates() instead. */
function panel_task_templates(): array
{
    return [
        'MDB' => [
            'label' => 'MDB Workflow',
            'steps' => [
                'ขายรันงานเข้าระบบ', 'เขียนแบบ', 'อนุมัติแบบ',
                'สั่งซื้อเหล็ก', 'เหล็กเข้า', 'ตัด/พับ/เชื่อม', 'พ่นสี',
                'สั่งซื้ออุปกรณ์', 'อุปกรณ์เข้า', 'ประกอบอุปกรณ์',
                'Busbar', 'เดินสาย', 'QC', 'FAT',
                'แก้ไข Comment', 'เตรียมส่งมอบ', 'ส่งมอบ',
            ],
        ],
        'DB' => [
            'label' => 'DB Workflow',
            'steps' => [
                'เขียนแบบ', 'อนุมัติแบบ', 'สั่งซื้อเหล็ก', 'เหล็กเข้า', 'พ่นสี',
                'สั่งซื้ออุปกรณ์', 'อุปกรณ์เข้า', 'ประกอบอุปกรณ์',
                'เดินสาย', 'QC', 'เตรียมส่งมอบ', 'ส่งมอบ',
            ],
        ],
        'MCC' => [
            'label' => 'MCC Workflow',
            'steps' => [
                'เขียนแบบ', 'อนุมัติแบบ', 'สั่งซื้อเหล็ก', 'เหล็กเข้า',
                'ผลิตโครงตู้', 'พ่นสี', 'สั่งซื้ออุปกรณ์', 'อุปกรณ์เข้า',
                'ประกอบอุปกรณ์', 'Control Wiring', 'Power Wiring',
                'Function Test', 'FAT', 'แก้ไข Comment', 'เตรียมส่งมอบ', 'ส่งมอบ',
            ],
        ],
        'ATS' => [
            'label' => 'ATS Workflow',
            'steps' => [
                'เขียนแบบ', 'อนุมัติแบบ', 'สั่งซื้อเหล็ก', 'เหล็กเข้า', 'พ่นสี',
                'สั่งซื้ออุปกรณ์', 'อุปกรณ์เข้า', 'ติดตั้ง ATS Controller',
                'Power Wiring', 'Control Wiring', 'Function Test', 'FAT',
                'เตรียมส่งมอบ', 'ส่งมอบ',
            ],
        ],
        'default' => [
            'label' => 'Default Workflow',
            'steps' => [
                'เขียนแบบ', 'อนุมัติแบบ', 'สั่งซื้อวัสดุ', 'วัสดุเข้า',
                'ผลิต', 'ประกอบ', 'ตรวจสอบ', 'เตรียมส่งมอบ', 'ส่งมอบ',
            ],
        ],
    ];
}

/** Auto-detect template key from panel_type string. */
function resolve_panel_template(string $panelType): string
{
    $type = mb_strtoupper(trim($panelType));
    foreach (array_keys(panel_task_templates()) as $key) {
        if ($key === 'default') {
            continue;
        }
        if (str_starts_with($type, $key)) {
            return $key;
        }
    }
    return 'default';
}

/** Status → progress percent mapping (3-state simple system). */
function task_status_to_progress(string $status): int
{
    return match ($status) {
        'in_progress' => 50,
        'completed'   => 100,
        default       => 0,
    };
}

/** Thai display label for a task status slug. */
function task_status_label(string $status): string
{
    return match ($status) {
        'in_progress' => 'เริ่มงานแล้ว',
        'completed'   => 'เสร็จแล้ว',
        'overdue'     => 'ล่าช้า',
        default       => 'รอเริ่มงาน',
    };
}

/**
 * Update a task's status and auto-stamp actual dates.
 * Computes progress from status (pending=0, in_progress=50, completed=100).
 * Auto-sets actual_start_date when status → in_progress (if not yet set).
 * Auto-sets completed_date when status → completed (if not yet set).
 * Cascades recompute_panel_from_tasks() after update.
 */
function update_task_status(int $taskId, string $newStatus, ?string $actualStart = null, ?string $actualComplete = null): void
{
    $allowed = ['pending', 'in_progress', 'completed'];
    if (!in_array($newStatus, $allowed, true)) {
        return;
    }
    $today    = date('Y-m-d');
    $progress = task_status_to_progress($newStatus);

    // Fetch current task for context
    $row = db()->prepare("SELECT * FROM project_tasks WHERE id = :id")->execute([':id' => $taskId])
        ?: null;
    $stFetch = db()->prepare("SELECT * FROM project_tasks WHERE id = :id");
    $stFetch->execute([':id' => $taskId]);
    $task = $stFetch->fetch();
    if (!$task) return;

    // Auto-stamp: actual_start_date
    $stampStart = $actualStart;
    if ($newStatus === 'in_progress' || $newStatus === 'completed') {
        if ($stampStart === null && empty($task['actual_start_date'])) {
            $stampStart = $today;
        }
    }

    // Auto-stamp: completed_date
    $stampComplete = $actualComplete;
    if ($newStatus === 'completed') {
        if ($stampComplete === null && empty($task['completed_date'])) {
            $stampComplete = $today;
        }
    }
    // Clear completed_date if reverting away from completed
    if ($newStatus !== 'completed') {
        $stampComplete = null;
    }

    // Status is always what the user sets (pending / in_progress / completed).
    // Overdue is computed at display time from due_date vs today — never stored.
    $sql = "UPDATE project_tasks
            SET status = :st, progress_percent = :prog,
                actual_start_date = COALESCE(:astart, actual_start_date),
                completed_date    = :cdate
            WHERE id = :id";
    db()->prepare($sql)->execute([
        ':st'     => $newStatus,
        ':prog'   => $progress,
        ':astart' => $stampStart,
        ':cdate'  => $stampComplete,
        ':id'     => $taskId,
    ]);

    if ((int)$task['panel_id']) {
        recompute_panel_from_tasks((int)$task['panel_id']);
    }
}

/** Fetch all tasks belonging to a specific panel, ordered by sort_order. */
function get_panel_tasks(int $panelId): array
{
    $stmt = db()->prepare(
        "SELECT * FROM project_tasks WHERE panel_id = :pid ORDER BY sort_order, id"
    );
    $stmt->execute([':pid' => $panelId]);
    return $stmt->fetchAll();
}

/** Count tasks for a panel. */
function count_panel_tasks(int $panelId): int
{
    $stmt = db()->prepare("SELECT COUNT(*) FROM project_tasks WHERE panel_id = :pid");
    $stmt->execute([':pid' => $panelId]);
    return (int)$stmt->fetchColumn();
}

/** Delete all tasks for a panel. */
function delete_panel_tasks(int $panelId): void
{
    db()->prepare("DELETE FROM project_tasks WHERE panel_id = :pid")
        ->execute([':pid' => $panelId]);
}

/**
 * Create auto-workflow tasks for a panel from a named template.
 * $template: 'MDB'|'DB'|'MCC'|'ATS'|'default'|'auto'|'copy:N'
 * $skipExisting: true = add only task_names not already present
 * Returns number of tasks inserted.
 */
function create_panel_auto_tasks(
    int    $projectId,
    int    $panelId,
    string $template     = 'auto',
    bool   $skipExisting = false
): int {
    // Auto-detect from panel_type
    if ($template === 'auto') {
        $st = db()->prepare("SELECT panel_type FROM project_panels WHERE id = :id");
        $st->execute([':id' => $panelId]);
        $template = resolve_panel_template((string)($st->fetchColumn() ?? ''));
    }

    // Copy from another panel
    if (str_starts_with($template, 'copy:')) {
        $sourceId = (int)substr($template, 5);
        return _copy_panel_tasks($projectId, $panelId, $sourceId, $skipExisting);
    }

    $templates = panel_task_templates();
    $steps     = ($templates[$template] ?? $templates['default'])['steps'];

    // Build existing name set for duplicate check
    $existingNames = [];
    if ($skipExisting) {
        $st = db()->prepare("SELECT task_name FROM project_tasks WHERE panel_id = :pid");
        $st->execute([':pid' => $panelId]);
        $existingNames = array_map('mb_strtolower', $st->fetchAll(PDO::FETCH_COLUMN));
    }

    $ins = db()->prepare(
        "INSERT INTO project_tasks
           (project_id, panel_id, task_name, sort_order, status, progress_percent, is_auto_created, template_name)
         VALUES (:pid, :panid, :name, :ord, 'pending', 0, 1, :tpl)"
    );

    $created = 0;
    foreach ($steps as $i => $name) {
        if ($skipExisting && in_array(mb_strtolower($name), $existingNames, true)) {
            continue;
        }
        $ins->execute([
            ':pid'   => $projectId,
            ':panid' => $panelId,
            ':name'  => $name,
            ':ord'   => $i + 1,
            ':tpl'   => $template,
        ]);
        $created++;
    }

    if ($created > 0) {
        log_activity($projectId, 'auto_tasks',
            'สร้างขั้นตอนอัตโนมัติ ' . $created . ' รายการ [' . $template . ']');
    }
    return $created;
}

/** Copy tasks from $sourcePanelId to $targetPanelId (reset progress to 0). */
function _copy_panel_tasks(int $projectId, int $targetPanelId, int $sourcePanelId, bool $skipExisting): int
{
    $sourceTasks = get_panel_tasks($sourcePanelId);
    if (!$sourceTasks) {
        return 0;
    }

    $existingNames = [];
    if ($skipExisting) {
        $st = db()->prepare("SELECT task_name FROM project_tasks WHERE panel_id = :pid");
        $st->execute([':pid' => $targetPanelId]);
        $existingNames = array_map('mb_strtolower', $st->fetchAll(PDO::FETCH_COLUMN));
    }

    $ins = db()->prepare(
        "INSERT INTO project_tasks
           (project_id, panel_id, task_name, task_type, sort_order, status, progress_percent, is_auto_created, template_name)
         VALUES (:pid, :panid, :name, :type, :ord, 'pending', 0, 1, 'copy')"
    );

    $created = 0;
    foreach ($sourceTasks as $t) {
        if ($skipExisting && in_array(mb_strtolower($t['task_name']), $existingNames, true)) {
            continue;
        }
        $ins->execute([
            ':pid'   => $projectId,
            ':panid' => $targetPanelId,
            ':name'  => $t['task_name'],
            ':type'  => $t['task_type'] ?? null,
            ':ord'   => $t['sort_order'],
        ]);
        $created++;
    }
    return $created;
}

/**
 * Recompute panel progress_percent, status, and task_status_label from its tasks.
 * Progress = AVG of task_status_to_progress() per task (pending=0, in_progress=50, completed=100).
 * Tasks are the single source of truth.
 * Cascades up to recompute_project_progress().
 */
function recompute_panel_from_tasks(int $panelId): void
{
    $tasks = get_panel_tasks($panelId);

    if (!$tasks) {
        db()->prepare(
            "UPDATE project_panels
             SET progress_percent = 0, status = 'pending', task_status_label = NULL
             WHERE id = :id"
        )->execute([':id' => $panelId]);
    } else {
        $total     = count($tasks);
        $today     = date('Y-m-d');

        // Overdue = due_date < today AND status != completed (never stored, always computed)
        $sumProgress = 0;
        $hasOverdue  = false;
        foreach ($tasks as $t) {
            $s = $t['status'] ?? 'pending';
            // Overdue tasks count as in_progress (50%) for progress calculation
            $isOverdue = ($s !== 'completed'
                && !empty($t['due_date'])
                && $t['due_date'] < $today);
            if ($isOverdue) $hasOverdue = true;
            $sumProgress += task_status_to_progress($isOverdue ? 'in_progress' : $s);
        }
        $avgProgress = (int)round($sumProgress / $total);

        $doneCount = count(array_filter($tasks, fn($t) => ($t['status'] ?? '') === 'completed'));
        $allDone   = ($doneCount === $total);

        if ($allDone) {
            db()->prepare(
                "UPDATE project_panels
                 SET progress_percent = 100, status = 'delivered',
                     task_status_label = 'ส่งมอบแล้ว',
                     actual_delivery_date = COALESCE(actual_delivery_date, CURDATE())
                 WHERE id = :id"
            )->execute([':id' => $panelId]);
        } else {
            // First non-completed task = current stage
            $firstIncomplete = null;
            foreach ($tasks as $t) {
                if (($t['status'] ?? '') !== 'completed') { $firstIncomplete = $t; break; }
            }

            if ($avgProgress === 0 && !$hasOverdue) {
                $statusSlug  = 'pending';
                $statusLabel = null;
            } elseif ($firstIncomplete) {
                $statusSlug  = $hasOverdue ? 'overdue' : 'in_progress';
                $statusLabel = task_name_to_status_label($firstIncomplete['task_name'], $hasOverdue);
            } else {
                $statusSlug  = 'in_progress';
                $statusLabel = 'กำลังดำเนินการ';
            }

            db()->prepare(
                "UPDATE project_panels
                 SET progress_percent = :prog, status = :st, task_status_label = :lbl
                 WHERE id = :id"
            )->execute([':prog' => $avgProgress, ':st' => $statusSlug, ':lbl' => $statusLabel, ':id' => $panelId]);
        }
    }

    $pidSt = db()->prepare("SELECT project_id FROM project_panels WHERE id = :id");
    $pidSt->execute([':id' => $panelId]);
    $pid = (int)$pidSt->fetchColumn();
    if ($pid) {
        recompute_project_progress($pid);
    }
}

function get_project_milestones(int $projectId): array
{
    $stmt = db()->prepare(
        "SELECT * FROM project_milestones WHERE project_id = :id
         ORDER BY sort_order, milestone_date, id"
    );
    $stmt->execute([':id' => $projectId]);
    return $stmt->fetchAll();
}

function get_delivery_groups(int $projectId): array
{
    $stmt = db()->prepare(
        "SELECT * FROM delivery_groups WHERE project_id = :id
         ORDER BY sort_order, delivery_date, id"
    );
    $stmt->execute([':id' => $projectId]);
    return $stmt->fetchAll();
}

/* ======================================================================
 *  Panel / Cabinet status system
 * ==================================================================== */

/** Workflow statuses (the values a user can set). */
function panel_workflow_statuses(): array
{
    return ['pending','design','material','production','wiring','qc','ready_delivery','delivered'];
}

/** Panel status slugs → Thai display labels. */
function panel_status_labels(): array
{
    return [
        'pending'        => 'รอเริ่มงาน',
        'in_progress'    => 'กำลังดำเนินการ',
        'delivered'      => 'ส่งมอบแล้ว',
        'overdue'        => 'ล่าช้า',
        // Legacy workflow slugs (kept for backward compat with old data)
        'design'         => 'กำลังเขียนแบบ',
        'material'       => 'รออุปกรณ์',
        'production'     => 'กำลังผลิต / ประกอบ',
        'wiring'         => 'กำลังเดินสาย',
        'qc'             => 'กำลัง FAT',
        'ready_delivery' => 'พร้อมส่งมอบ',
    ];
}

function panel_status_colors(): array
{
    return [
        'pending'        => '#94A3B8', // Slate   — รอเริ่มงาน
        'in_progress'    => '#FF7A00', // Orange  — กำลังดำเนินการ (brand)
        'delivered'      => '#16A34A', // Green   — ส่งมอบแล้ว
        'overdue'        => '#EF4444', // Red     — ล่าช้า
        // Legacy workflow slugs
        'design'         => '#3B82F6',
        'material'       => '#F59E0B',
        'production'     => '#FF7A00',
        'wiring'         => '#8B5CF6',
        'qc'             => '#06B6D4',
        'ready_delivery' => '#059669',
    ];
}

/**
 * Map a task name → cabinet display status label.
 * If $isOverdue: returns "ล่าช้า: <taskName>" regardless of mapping.
 */
function task_name_to_status_label(string $taskName, bool $isOverdue = false): string
{
    if ($isOverdue) {
        return 'ล่าช้า: ' . $taskName;
    }
    $map = [
        'ขายรันงานเข้าระบบ' => 'รอขายรันงานเข้าระบบ',
        'เขียนแบบ'           => 'กำลังเขียนแบบ',
        'อนุมัติแบบ'         => 'รออนุมัติแบบ',
        'สั่งซื้อเหล็ก'      => 'สั่งซื้อเหล็กแล้ว',
        'เหล็กเข้า'          => 'รอเหล็กเข้า',
        'ตัดเหล็ก'           => 'กำลังตัดเหล็ก',
        'พับเหล็ก'           => 'กำลังพับเหล็ก',
        'เชื่อมประกอบ'       => 'กำลังเชื่อมประกอบ',
        'ตัด/พับ/เชื่อม'    => 'กำลังผลิตโครงตู้',
        'พ่นสี'              => 'กำลังพ่นสี',
        'ตู้เข้าโรงงาน'      => 'รอตู้เข้าโรงงาน',
        'สั่งซื้ออุปกรณ์'    => 'สั่งซื้ออุปกรณ์แล้ว',
        'อุปกรณ์เข้า'        => 'รออุปกรณ์เข้า',
        'ตรวจรับอุปกรณ์'    => 'กำลังตรวจรับอุปกรณ์',
        'ประกอบอุปกรณ์'      => 'กำลังประกอบอุปกรณ์',
        'Busbar'             => 'กำลังทำ Busbar',
        'เดินสาย'            => 'กำลังเดินสาย',
        'QC'                 => 'กำลังตรวจสอบ QC',
        'FAT'                => 'กำลัง FAT',
        'แก้ไข Comment'      => 'กำลังแก้ไข Comment',
        'เตรียมส่งมอบ'       => 'พร้อมส่งมอบ',
        'ส่งมอบ'             => 'ส่งมอบแล้ว',
    ];
    return $map[$taskName] ?? 'กำลัง' . $taskName;
}

/**
 * Return the display label for a panel row.
 * Uses task_status_label if set (task-derived), otherwise falls back to status slug label.
 */
function panel_effective_label(array $panel): string
{
    if (!empty($panel['task_status_label'])) {
        return $panel['task_status_label'];
    }
    return panel_status_label($panel['status'] ?? 'pending');
}

/**
 * @deprecated Progress should come from task AVG, not status mapping.
 * Kept only for backward compatibility with old data that has no tasks.
 */
function panel_status_progress_map(): array
{
    return [
        // AUTO workflow statuses
        'pending'        => 0,
        'design'         => 15,
        'material'       => 25,
        'production'     => 45,
        'wiring'         => 70,
        'qc'             => 85,
        'ready_delivery' => 95,
        'delivered'      => 100,
        // MANUAL override statuses (m_ prefix)
        'm_wait_start'   => 0,
        'm_wait_draw'    => 5,
        'm_drawing'      => 15,
        'm_wait_appr'    => 20,
        'm_wait_mat'     => 25,
        'm_part_mat'     => 30,
        'm_mat_rdy'      => 35,
        'm_fabrication'  => 45,
        'm_assembly'     => 60,
        'm_wiring'       => 70,
        'm_qc'           => 85,
        'm_fat'          => 90,
        'm_ready'        => 95,
        'm_delivered'    => 100,
        // m_overdue, m_on_hold, m_cancelled → intentionally omitted (no progress change)
    ];
}

/**
 * 17 manual-override statuses available when status_mode = MANUAL.
 * Keys use the m_ prefix to avoid collision with AUTO workflow slugs.
 */
function manual_status_options(): array
{
    return [
        'm_wait_start'   => 'รอขายรันงานเข้าระบบ',
        'm_wait_draw'    => 'รอเขียนแบบ',
        'm_drawing'      => 'กำลังเขียนแบบ',
        'm_wait_appr'    => 'รออนุมัติแบบ',
        'm_wait_mat'     => 'รออุปกรณ์เข้า',
        'm_part_mat'     => 'อุปกรณ์เข้าไม่ครบ',
        'm_mat_rdy'      => 'อุปกรณ์พร้อมผลิต',
        'm_fabrication'  => 'กำลังผลิตโครงตู้',
        'm_assembly'     => 'กำลังประกอบอุปกรณ์',
        'm_wiring'       => 'กำลังเดินสาย',
        'm_qc'           => 'กำลังตรวจสอบ QC',
        'm_fat'          => 'กำลัง FAT',
        'm_ready'        => 'พร้อมส่งมอบ',
        'm_delivered'    => 'ส่งมอบแล้ว',
        'm_overdue'      => 'ล่าช้า',
        'm_on_hold'      => 'พักงาน',
        'm_cancelled'    => 'ยกเลิก',
    ];
}

function manual_status_color(string $s): string
{
    return [
        'm_wait_start'   => '#6B7280', // gray
        'm_wait_draw'    => '#94A3B8', // slate (like pending)
        'm_drawing'      => '#3B82F6', // blue  (like design)
        'm_wait_appr'    => '#6B7280', // gray
        'm_wait_mat'     => '#F59E0B', // amber (like material)
        'm_part_mat'     => '#F59E0B', // amber
        'm_mat_rdy'      => '#F59E0B', // amber
        'm_fabrication'  => '#FF7A00', // orange — กำลังผลิตโครงตู้
        'm_assembly'     => '#8B5CF6', // purple — กำลังประกอบอุปกรณ์
        'm_wiring'       => '#6366F1', // indigo — กำลังเดินสาย
        'm_qc'           => '#06B6D4', // cyan   — กำลังตรวจสอบ QC
        'm_fat'          => '#10B981', // green  — กำลัง FAT
        'm_ready'        => '#059669', // emerald (like ready_delivery)
        'm_delivered'    => '#16A34A', // green (like delivered)
        'm_overdue'      => '#EF4444', // red (like overdue)
        'm_on_hold'      => '#6B7280', // gray
        'm_cancelled'    => '#6B7280', // gray
    ][$s] ?? '#6B7280';
}

/** Unified label lookup — covers both AUTO workflow slugs and MANUAL m_ slugs. */
function panel_status_label(string $s): string
{
    return panel_status_labels()[$s] ?? manual_status_options()[$s] ?? $s;
}

/** Unified color lookup — covers both AUTO workflow slugs and MANUAL m_ slugs. */
function panel_status_color(string $s): string
{
    return panel_status_colors()[$s] ?? manual_status_color($s);
}

/* ======================================================================
 *  Report: Group & Status color helpers (Project Overview Image)
 * ==================================================================== */

function report_group_palette(): array
{
    return [
        '#FF7A00', '#2563EB', '#10B981', '#8B5CF6', '#EF4444',
        '#14B8A6', '#F59E0B', '#6366F1', '#EC4899', '#64748B',
    ];
}

/**
 * Build a stable group → hex color mapping from a panel array.
 * Named groups A–F get fixed brand colors; extras get auto-palette colors
 * assigned by ksort discovery order (so the same group always gets the same color).
 */
function build_group_color_map(array $panels): array
{
    $fixed = [
        'Group A' => '#FF7A00',
        'Group B' => '#2563EB',
        'Group C' => '#10B981',
        'Group D' => '#8B5CF6',
        'Group E' => '#EF4444',
        'Group F' => '#14B8A6',
    ];
    $seen = [];
    foreach ($panels as $pn) {
        $g = trim((string)($pn['delivery_group'] ?? ''));
        if ($g !== '' && !isset($seen[$g])) {
            $seen[$g] = true;
        }
    }
    $palette = report_group_palette();
    $map = [];
    foreach (array_keys($seen) as $g) {
        // Use hash so a group's color stays stable even when other groups are added/removed
        $map[$g] = $fixed[$g] ?? $palette[abs(crc32($g)) % count($palette)];
    }
    return $map;
}

/** Hex color for a named delivery group (fallback: auto-palette by index). */
function getGroupColor(string $groupName, int $index = 0): string
{
    $fixed = [
        'Group A' => '#FF7A00',
        'Group B' => '#2563EB',
        'Group C' => '#10B981',
        'Group D' => '#8B5CF6',
        'Group E' => '#EF4444',
        'Group F' => '#14B8A6',
    ];
    if (isset($fixed[$groupName])) return $fixed[$groupName];
    $p = report_group_palette();
    return $p[abs(crc32($groupName)) % count($p)];
}

/**
 * Status hex color for report badges — more granular than panel_status_color().
 * Differentiates กำลังประกอบอุปกรณ์ (Purple) from กำลังเดินสาย (Indigo), etc.
 */
function getStatusColor(string $status): string
{
    static $map = null;
    if ($map === null) {
        $map = [
            'pending'        => '#94A3B8',
            'm_wait_start'   => '#94A3B8',
            'm_wait_draw'    => '#7DD3FC',
            'design'         => '#3B82F6',
            'm_drawing'      => '#3B82F6',
            'm_wait_appr'    => '#60A5FA',
            'material'       => '#F59E0B',
            'm_wait_mat'     => '#F59E0B',
            'm_part_mat'     => '#FB923C',
            'm_mat_rdy'      => '#F59E0B',
            'production'     => '#FF7A00',
            'm_fabrication'  => '#FF7A00',
            'm_assembly'     => '#8B5CF6', // purple — กำลังประกอบอุปกรณ์
            'wiring'         => '#8B5CF6', // purple — กำลังเดินสาย (AUTO)
            'm_wiring'       => '#6366F1', // indigo — กำลังเดินสาย (MANUAL)
            'qc'             => '#06B6D4', // cyan   — กำลัง FAT (AUTO)
            'm_qc'           => '#06B6D4', // cyan   — กำลังตรวจสอบ QC
            'm_fat'          => '#10B981', // green  — กำลัง FAT (MANUAL)
            'ready_delivery' => '#059669', // emerald — พร้อมส่งมอบ
            'm_ready'        => '#059669', // emerald
            'delivered'      => '#16A34A', // green
            'm_delivered'    => '#16A34A',
            'overdue'        => '#EF4444',
            'm_overdue'      => '#EF4444',
            'm_on_hold'      => '#64748B',
            'm_cancelled'    => '#374151',
        ];
    }
    return $map[$status] ?? '#94A3B8';
}

function getStatusLabel(string $status): string
{
    return panel_status_label($status);
}

/** HTML badge for a delivery group (inline-safe, html2canvas-compatible). */
function getGroupBadge(string $groupName, string $color = '#6B7280'): string
{
    $c = htmlspecialchars($color, ENT_QUOTES, 'UTF-8');
    $n = htmlspecialchars($groupName, ENT_QUOTES, 'UTF-8');
    return '<span class="rpt-grp-badge" style="background:' . $c . '">' . $n . '</span>';
}

/** HTML badge for a panel status (inline-safe, html2canvas-compatible). */
function getStatusBadge(string $status): string
{
    $color = getStatusColor($status);
    $label = htmlspecialchars(getStatusLabel($status), ENT_QUOTES, 'UTF-8');
    return '<span class="rpt-st-badge" style="background:' . $color . '">' . $label . '</span>';
}

/** progress percent that corresponds to a workflow status. */
function panel_progress_for_status(string $status): int
{
    return panel_status_progress_map()[$status] ?? 0;
}

/**
 * Effective status slug of a panel.
 * Task-driven: status field is maintained by recompute_panel_from_tasks().
 * Returns one of: 'pending' | 'in_progress' | 'overdue' | 'delivered'
 */
function compute_panel_status(array $panel): string
{
    return $panel['status'] ?? 'pending';
}

/** Overdue days for a single panel (0 if not overdue or no target date). */
function panel_overdue_days(array $panel): int
{
    if (($panel['status'] ?? 'pending') !== 'overdue') {
        return 0;
    }
    if (empty($panel['target_delivery_date']) || $panel['target_delivery_date'] === '0000-00-00') {
        return 0;
    }
    $today  = strtotime(date('Y-m-d'));
    $target = strtotime($panel['target_delivery_date']);
    return max(0, (int)floor(($today - $target) / 86400));
}

/* ----- Panel sort helpers ----- */

/**
 * Convert a delivery group name to a numeric sort key so panels always sort
 * Group A → B → C … → no-group regardless of how the group is named.
 *
 * Supported naming conventions (all map to the same key):
 *   Single letter        : "A" → 1, "B" → 2
 *   "Group X" / "Lot X" : "Group A" → 1, "Lot B" → 2
 *   Pure number          : "1" → 1, "2" → 2
 *   "Group N" / "Lot N" : "Group 1" → 1, "Lot 3" → 3
 *   Empty / "—"         : 9999 (always last)
 */
function panel_group_sort_key(string $g): int
{
    $g = trim($g);
    // Empty / sentinel "no group" values → always last
    if ($g === '' || $g === '—' || $g === '-') return 9999;
    if (in_array(strtolower($g), ['no group', 'none', 'n/a', 'ไม่ระบุ'], true)) return 9999;

    // Pure integer string → use value directly
    if (ctype_digit($g)) return (int)$g;

    // Single letter A-Z (e.g. "A", "B") → 1-26
    if (preg_match('/^[A-Za-z]$/u', $g)) return ord(strtoupper($g[0])) - 64;

    // Strip known prefixes (case-insensitive): "Group ", "Lot ", "Phase ", "ชุด", "กลุ่ม"
    $core = trim((string)preg_replace('/^(?:Group|Lot|Phase|ชุด|กลุ่ม)\s*/iu', '', $g));
    $hadPrefix = ($core !== $g);

    if ($hadPrefix) {
        // After prefix stripped: single letter → 1-26
        if (preg_match('/^[A-Za-z]$/u', $core)) return ord(strtoupper($core[0])) - 64;
        // Pure number: "Group 1" → 1
        if (ctype_digit($core)) return (int)$core;
        // Trailing letter: "Group A1" → last letter
        if (preg_match('/([A-Za-z])$/u', $core, $m)) return ord(strtoupper($m[1])) - 64;
        // Trailing number
        if (preg_match('/(\d+)$/u', $core, $m)) return (int)$m[1];
    }

    // Unknown format: stable hash 500-998 (after A-Z/1-N, before no-group 9999)
    return 500 + abs(crc32($g)) % 499;
}

/**
 * Sort a panel array consistently across every page:
 *   1. Delivery group (via panel_group_sort_key)
 *   2. target_delivery_date ASC (null/empty last within group)
 *   3. panel_no (natural / human sort)
 */
function sort_panels(array $panels): array
{
    usort($panels, function (array $a, array $b): int {
        $ga = panel_group_sort_key(trim((string)($a['delivery_group'] ?? '')));
        $gb = panel_group_sort_key(trim((string)($b['delivery_group'] ?? '')));
        if ($ga !== $gb) return $ga <=> $gb;

        $ta = (string)($a['target_delivery_date'] ?? '');
        $tb = (string)($b['target_delivery_date'] ?? '');
        $taBlank = ($ta === '' || $ta === '0000-00-00');
        $tbBlank = ($tb === '' || $tb === '0000-00-00');
        if ($taBlank !== $tbBlank) return $taBlank ? 1 : -1;
        if (!$taBlank && $ta !== $tb) return strcmp($ta, $tb);

        return strnatcasecmp((string)($a['panel_no'] ?? ''), (string)($b['panel_no'] ?? ''));
    });
    return $panels;
}

/* ----- Panel data access (with project join, for tracking page) ----- */

function get_panels(array $filters = []): array
{
    $sql = "SELECT pp.*, p.project_no, p.project_name
            FROM project_panels pp
            JOIN projects p ON p.id = pp.project_id";
    $where = [];
    $params = [];

    if (!empty($filters['project_id'])) {
        $where[] = 'pp.project_id = :pid';
        $params[':pid'] = (int)$filters['project_id'];
    }
    if (!empty($filters['delivery_group'])) {
        $where[] = 'pp.delivery_group = :grp';
        $params[':grp'] = $filters['delivery_group'];
    }
    if (!empty($filters['responsible'])) {
        $where[] = 'pp.responsible = :resp';
        $params[':resp'] = $filters['responsible'];
    }
    if (!empty($filters['search'])) {
        $where[] = '(pp.panel_no LIKE :s OR pp.panel_name LIKE :s)';
        $params[':s'] = '%' . $filters['search'] . '%';
    }
    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY p.project_no, pp.id';

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    foreach ($rows as &$r) {
        $r['eff_status'] = compute_panel_status($r);
    }
    unset($r);

    if (!empty($filters['status'])) {
        $rows = array_values(array_filter($rows, fn($r) => $r['eff_status'] === $filters['status']));
    }

    // Sort: project_no (natural) → group → delivery date → panel_no
    usort($rows, function (array $a, array $b): int {
        $pCmp = strnatcasecmp((string)($a['project_no'] ?? ''), (string)($b['project_no'] ?? ''));
        if ($pCmp !== 0) return $pCmp;

        $ga = panel_group_sort_key(trim((string)($a['delivery_group'] ?? '')));
        $gb = panel_group_sort_key(trim((string)($b['delivery_group'] ?? '')));
        if ($ga !== $gb) return $ga <=> $gb;

        $ta = (string)($a['target_delivery_date'] ?? '');
        $tb = (string)($b['target_delivery_date'] ?? '');
        $taBlank = ($ta === '' || $ta === '0000-00-00');
        $tbBlank = ($tb === '' || $tb === '0000-00-00');
        if ($taBlank !== $tbBlank) return $taBlank ? 1 : -1;
        if (!$taBlank && $ta !== $tb) return strcmp($ta, $tb);

        return strnatcasecmp((string)($a['panel_no'] ?? ''), (string)($b['panel_no'] ?? ''));
    });

    return $rows;
}

function get_panel(int $id): ?array
{
    $stmt = db()->prepare(
        "SELECT pp.*, p.project_no, p.project_name
         FROM project_panels pp
         JOIN projects p ON p.id = pp.project_id
         WHERE pp.id = :id"
    );
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    if (!$row) return null;
    $row['eff_status'] = compute_panel_status($row);
    return $row;
}

/** Distinct delivery groups / responsibles for filter dropdowns. */
function get_panel_groups(): array
{
    return db()->query(
        "SELECT DISTINCT delivery_group FROM project_panels
         WHERE delivery_group IS NOT NULL AND delivery_group <> ''
         ORDER BY delivery_group"
    )->fetchAll(PDO::FETCH_COLUMN);
}

function get_panel_responsibles(): array
{
    return db()->query(
        "SELECT DISTINCT responsible FROM project_panels
         WHERE responsible IS NOT NULL AND responsible <> ''
         ORDER BY responsible"
    )->fetchAll(PDO::FETCH_COLUMN);
}

/**
 * Panel statistics for dashboards.
 * Uses task-derived status slugs: pending | in_progress | overdue | delivered
 */
function panel_stats(array $panels): array
{
    $count = ['pending' => 0, 'in_progress' => 0, 'overdue' => 0, 'delivered' => 0];
    $sumProgress = 0;
    foreach ($panels as $pn) {
        $s = $pn['eff_status'] ?? compute_panel_status($pn);
        // Normalise legacy slugs to the four canonical ones
        if (in_array($s, ['design','material','production','wiring','qc','ready_delivery'], true)) {
            $s = 'in_progress';
        }
        $count[$s] = ($count[$s] ?? 0) + 1;
        $sumProgress += (int)$pn['progress_percent'];
    }
    $total = count($panels);
    return [
        'total'       => $total,
        'count'       => $count,
        'delivered'   => $count['delivered'],
        'overdue'     => $count['overdue'],
        'producing'   => $count['in_progress'],
        'pending'     => $count['pending'],
        'overall'     => $total ? (int)round($sumProgress / $total) : 0,
    ];
}

/** Average progress of a project's panels (null if it has none). */
function project_progress_from_panels(int $projectId): ?int
{
    $stmt = db()->prepare(
        "SELECT AVG(progress_percent) FROM project_panels WHERE project_id = :id"
    );
    $stmt->execute([':id' => $projectId]);
    $avg = $stmt->fetchColumn();
    return $avg === null ? null : (int)round((float)$avg);
}

/** Recompute & persist projects.progress from its panels (if any). */
function recompute_project_progress(int $projectId): void
{
    $avg = project_progress_from_panels($projectId);
    if ($avg === null) {
        return; // keep manual progress when there are no panels
    }
    db()->prepare("UPDATE projects SET progress = :p WHERE id = :id")
        ->execute([':p' => $avg, ':id' => $projectId]);
}

/* ----- Panel create / update / delete ----- */

function collect_panel_post(): array
{
    $nullable = fn($v) => ($v === '' || $v === null) ? null : $v;
    $actual   = $nullable($_POST['actual_delivery_date'] ?? '');
    if ($actual !== null && strtotime($actual) > strtotime(date('Y-m-d'))) {
        $actual = null;
    }
    $data = [
        'panel_no'             => trim($_POST['panel_no'] ?? ''),
        'panel_name'           => trim($_POST['panel_name'] ?? ''),
        'panel_type'           => $nullable(trim($_POST['panel_type'] ?? '')),
        'panel_size'           => $nullable(trim($_POST['panel_size'] ?? '')),
        'delivery_group'       => $nullable(trim($_POST['delivery_group'] ?? '')),
        'target_delivery_date' => $nullable($_POST['target_delivery_date'] ?? ''),
        'actual_delivery_date' => $actual,
        'responsible'          => $nullable(trim($_POST['responsible'] ?? '')),
        'remark'               => $nullable(trim($_POST['remark'] ?? '')),
        'sort_order'           => (int)($_POST['sort_order'] ?? 0),
        'planned_start_date'   => $nullable($_POST['planned_start_date'] ?? ''),
    ];
    // Actual delivery date → mark delivered (tasks will override on next recompute)
    if ($actual && strtotime($actual) <= strtotime(date('Y-m-d'))) {
        $data['status']           = 'delivered';
        $data['task_status_label'] = 'ส่งมอบแล้ว';
    }
    return $data;
}

function create_panel(int $projectId, array $data): int
{
    // New panels start at รอเริ่มงาน unless actual delivery date was provided
    if (!isset($data['status'])) {
        $data['status'] = 'pending';
    }
    if (!isset($data['progress_percent'])) {
        $data['progress_percent'] = 0;
    }
    $data['project_id'] = $projectId;
    $cols  = array_keys($data);
    $place = array_map(fn($c) => ':' . $c, $cols);
    $sql = 'INSERT INTO project_panels (' . implode(',', $cols) . ') VALUES (' . implode(',', $place) . ')';
    $params = [];
    foreach ($data as $k => $v) { $params[':' . $k] = $v; }
    db()->prepare($sql)->execute($params);
    $id = (int)db()->lastInsertId();
    recompute_project_progress($projectId);
    log_activity($projectId, 'panel_add', 'เพิ่มตู้ ' . ($data['panel_no'] ?? ''));
    return $id;
}

function update_panel(int $id, array $data): void
{
    $sets = [];
    $params = [':id' => $id];
    foreach ($data as $k => $v) {
        $sets[] = "$k = :$k";
        $params[':' . $k] = $v;
    }
    db()->prepare('UPDATE project_panels SET ' . implode(',', $sets) . ' WHERE id = :id')->execute($params);
    $pidSt = db()->prepare("SELECT project_id FROM project_panels WHERE id = :id");
    $pidSt->execute([':id' => $id]);
    $pid = (int)$pidSt->fetchColumn();
    recompute_project_progress($pid);
    log_activity($pid, 'panel_edit', 'แก้ไขตู้ ' . ($data['panel_no'] ?? ''));
}

/**
 * Direct status write for legacy workflow buttons (drawer nav).
 * Progress is now driven by tasks only — this only updates the status field.
 * @deprecated Use recompute_panel_from_tasks() for task-driven updates.
 */
function set_panel_status(int $id, string $status): void
{
    $allowed = ['pending', 'in_progress', 'delivered', 'overdue',
                'design', 'material', 'production', 'wiring', 'qc', 'ready_delivery'];
    if (!in_array($status, $allowed, true)) {
        return;
    }
    $extraSql = '';
    $params   = [':s' => $status, ':id' => $id];
    if ($status === 'delivered') {
        $extraSql = ', actual_delivery_date = COALESCE(actual_delivery_date, CURDATE())';
    }
    db()->prepare(
        "UPDATE project_panels SET status = :s $extraSql WHERE id = :id"
    )->execute($params);
    $pidSt2 = db()->prepare("SELECT project_id FROM project_panels WHERE id = :id");
    $pidSt2->execute([':id' => $id]);
    $pid = (int)$pidSt2->fetchColumn();
    recompute_project_progress($pid);
    log_activity($pid, 'panel_status', 'อัปเดตสถานะตู้ -> ' . $status);
}

function delete_panel(int $id): void
{
    $delSt = db()->prepare("SELECT project_id, panel_no FROM project_panels WHERE id = :id");
    $delSt->execute([':id' => $id]);
    $row = $delSt->fetch();
    if (!$row) return;
    db()->prepare("DELETE FROM project_panels WHERE id = :id")->execute([':id' => $id]);
    recompute_project_progress((int)$row['project_id']);
    log_activity((int)$row['project_id'], 'panel_delete', 'ลบตู้ ' . $row['panel_no']);
}

/**
 * Compute timeline window + per-task bar positions (percent based).
 * Window = min(start) .. max(end) across the project dates and its tasks.
 * Returns [start_ts, end_ts, total_days, bars[], months[]] where each bar has
 * left% / width% and milestones can be positioned with timeline_pos().
 */
function build_timeline(array $project, array $tasks): array
{
    $dates = [];
    foreach (['start_date', 'due_date', 'delivery_date', 'completed_date'] as $f) {
        if (!empty($project[$f])) {
            $dates[] = strtotime($project[$f]);
        }
    }
    foreach ($tasks as $t) {
        if (!empty($t['start_date'])) $dates[] = strtotime($t['start_date']);
        if (!empty($t['end_date']))   $dates[] = strtotime($t['end_date']);
    }
    if (!$dates) {
        $today  = strtotime(date('Y-m-d'));
        $dates  = [$today, strtotime('+30 days', $today)];
    }

    $start = min($dates);
    $end   = max($dates);
    if ($end <= $start) {
        $end = strtotime('+1 day', $start);
    }
    $span = max(1, ($end - $start));

    $pos = function ($ts) use ($start, $span) {
        $p = (($ts - $start) / $span) * 100;
        return max(0, min(100, $p));
    };

    $bars = [];
    foreach ($tasks as $t) {
        if (empty($t['start_date']) || empty($t['end_date'])) continue;
        $ts0 = strtotime($t['start_date']);
        $ts1 = strtotime($t['end_date']);
        if (!$ts0 || !$ts1) continue; // reject '0000-00-00' or unparseable strings
        if ($ts1 < $ts0) { $ts1 = $ts0; }
        $left  = $pos($ts0);
        $right = $pos($ts1);
        $width = max(1.5, $right - $left);
        $bars[] = $t + ['left' => $left, 'width' => $width];
    }

    // month gridlines (1st of each month inside the window)
    $months = [];
    $cur = strtotime(date('Y-m-01', $start));
    while ($cur <= $end) {
        if ($cur >= $start) {
            $months[] = ['pos' => $pos($cur), 'ts' => $cur];
        }
        $cur = strtotime('+1 month', $cur);
    }

    return [
        'start'      => $start,
        'end'        => $end,
        'span'       => $span,
        'total_days' => (int)ceil($span / 86400),
        'bars'       => $bars,
        'months'     => $months,
        'pos'        => $pos,    // closure: ts -> percent
        'today'      => strtotime(date('Y-m-d')),
    ];
}

/* ======================================================================
 *  Create / update / delete
 * ==================================================================== */

/** Collect & sanitise project fields from $_POST. */
function collect_project_post(): array
{
    $nullable = fn($v) => ($v === '' || $v === null) ? null : $v;
    return [
        'project_no'        => trim($_POST['project_no'] ?? ''),
        'project_name'      => trim($_POST['project_name'] ?? ''),
        'description'       => $nullable(trim($_POST['description'] ?? '')),
        'customer'          => $nullable(trim($_POST['customer'] ?? '')),
        'sale_name'         => $nullable(trim($_POST['sale_name'] ?? '')),
        'payment_terms'     => $nullable(trim($_POST['payment_terms'] ?? '')),
        'delivery_location'        => $nullable(trim($_POST['delivery_location'] ?? '')),
        'delivery_location_detail' => $nullable(trim($_POST['delivery_location_detail'] ?? '')),
        'department_id'     => $nullable($_POST['department_id'] ?? '') ? (int)$_POST['department_id'] : null,
        'responsible_id'    => $nullable($_POST['responsible_id'] ?? '') ? (int)$_POST['responsible_id'] : null,
        'start_date'        => $nullable($_POST['start_date'] ?? ''),
        'due_date'          => $nullable($_POST['due_date'] ?? ''),
        'delivery_date'     => $nullable($_POST['delivery_date'] ?? ''),
        'completed_date'    => $nullable($_POST['completed_date'] ?? ''),
        'status'            => $_POST['status'] ?? 'pending',
        'progress'          => max(0, min(100, (int)($_POST['progress'] ?? 0))),
        'amount'            => (float)($_POST['amount'] ?? 0),
        'remark'            => $nullable(trim($_POST['remark'] ?? '')),
    ];
}

/** Handle an uploaded file; returns stored filename or null. */
function handle_upload(string $field = 'attachment'): ?string
{
    if (empty($_FILES[$field]['name']) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    if (!is_dir(UPLOAD_DIR)) {
        @mkdir(UPLOAD_DIR, 0775, true);
    }
    $orig = basename($_FILES[$field]['name']);
    $ext  = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
    $allowed = ['pdf','png','jpg','jpeg','gif','doc','docx','xls','xlsx','dwg','zip'];
    if ($ext && !in_array($ext, $allowed, true)) {
        return null;
    }
    $safe = 'prj_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . ($ext ? '.' . $ext : '');
    if (move_uploaded_file($_FILES[$field]['tmp_name'], UPLOAD_DIR . '/' . $safe)) {
        return $safe;
    }
    return null;
}

/** Save multiple uploaded files and insert rows into project_attachments. Returns count saved. */
function save_project_attachments(int $projectId, string $field = 'attachments'): int
{
    if (empty($_FILES[$field]['name'])) {
        return 0;
    }
    if (!is_dir(UPLOAD_DIR)) {
        @mkdir(UPLOAD_DIR, 0775, true);
    }
    $allowed = ['pdf','png','jpg','jpeg','gif','webp','doc','docx','xls','xlsx','csv','dwg','zip','rar'];
    $names   = (array)$_FILES[$field]['name'];
    $tmps    = (array)$_FILES[$field]['tmp_name'];
    $errors  = (array)$_FILES[$field]['error'];
    $sizes   = (array)$_FILES[$field]['size'];
    $types   = (array)$_FILES[$field]['type'];
    $ins = db()->prepare(
        "INSERT INTO project_attachments (project_id, file_name, original_name, file_size, mime_type)
         VALUES (:pid, :fn, :on, :sz, :mt)"
    );
    $saved = 0;
    foreach ($names as $i => $orig) {
        if (($errors[$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) continue;
        $orig = basename($orig);
        $ext  = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
        if ($ext && !in_array($ext, $allowed, true)) continue;
        $safe = 'prj_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . ($ext ? '.' . $ext : '');
        if (!move_uploaded_file($tmps[$i], UPLOAD_DIR . '/' . $safe)) continue;
        $ins->execute([
            ':pid' => $projectId,
            ':fn'  => $safe,
            ':on'  => $orig,
            ':sz'  => (int)($sizes[$i] ?? 0),
            ':mt'  => $types[$i] ?? '',
        ]);
        $saved++;
    }
    return $saved;
}

/** Save multiple uploaded files for a panel. Returns count saved. */
function save_panel_attachments(int $panelId, string $field = 'panel_attachments'): int
{
    if (empty($_FILES[$field]['name'])) return 0;
    if (!is_dir(UPLOAD_DIR)) @mkdir(UPLOAD_DIR, 0775, true);
    $allowed = ['pdf','png','jpg','jpeg','gif','webp','doc','docx','xls','xlsx','csv','dwg','zip','rar'];
    $names   = (array)$_FILES[$field]['name'];
    $tmps    = (array)$_FILES[$field]['tmp_name'];
    $errors  = (array)$_FILES[$field]['error'];
    $sizes   = (array)$_FILES[$field]['size'];
    $types   = (array)$_FILES[$field]['type'];
    $ins = db()->prepare(
        "INSERT INTO panel_attachments (panel_id, file_name, original_name, file_size, mime_type)
         VALUES (:pid, :fn, :on, :sz, :mt)"
    );
    $saved = 0;
    foreach ($names as $i => $orig) {
        if (($errors[$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) continue;
        $orig = basename($orig);
        $ext  = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
        if ($ext && !in_array($ext, $allowed, true)) continue;
        $safe = 'pnl_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . ($ext ? '.' . $ext : '');
        if (!move_uploaded_file($tmps[$i], UPLOAD_DIR . '/' . $safe)) continue;
        $ins->execute([':pid' => $panelId, ':fn' => $safe, ':on' => $orig, ':sz' => (int)($sizes[$i] ?? 0), ':mt' => $types[$i] ?? '']);
        $saved++;
    }
    return $saved;
}

/** Fetch all attachments for a panel. */
function get_panel_attachments(int $panelId): array
{
    $st = db()->prepare("SELECT * FROM panel_attachments WHERE panel_id = :pid ORDER BY uploaded_at DESC");
    $st->execute([':pid' => $panelId]);
    return $st->fetchAll();
}

/** Delete a single panel attachment (scoped to panel for safety). */
function delete_panel_attachment(int $attachId, int $panelId): bool
{
    $st = db()->prepare("SELECT file_name FROM panel_attachments WHERE id = :id AND panel_id = :pid");
    $st->execute([':id' => $attachId, ':pid' => $panelId]);
    $row = $st->fetch();
    if (!$row) return false;
    $path = UPLOAD_DIR . '/' . $row['file_name'];
    if (file_exists($path)) @unlink($path);
    db()->prepare("DELETE FROM panel_attachments WHERE id = :id")->execute([':id' => $attachId]);
    return true;
}

/** Fetch all attachments for a project. */
function get_project_attachments(int $projectId): array
{
    $st = db()->prepare(
        "SELECT * FROM project_attachments WHERE project_id = :pid ORDER BY uploaded_at DESC"
    );
    $st->execute([':pid' => $projectId]);
    return $st->fetchAll();
}

/** Delete a single attachment (by id, scoped to project for safety). */
function delete_project_attachment(int $attachId, int $projectId): bool
{
    $st = db()->prepare("SELECT file_name FROM project_attachments WHERE id = :id AND project_id = :pid");
    $st->execute([':id' => $attachId, ':pid' => $projectId]);
    $row = $st->fetch();
    if (!$row) return false;
    $path = UPLOAD_DIR . '/' . $row['file_name'];
    if (file_exists($path)) @unlink($path);
    db()->prepare("DELETE FROM project_attachments WHERE id = :id")->execute([':id' => $attachId]);
    return true;
}

/** Insert a project, returns new id. */
function create_project(array $data, ?string $attachment = null): int
{
    $data['attachment'] = $attachment;
    $cols = array_keys($data);
    $place = array_map(fn($c) => ':' . $c, $cols);
    $sql = 'INSERT INTO projects (' . implode(',', $cols) . ') VALUES (' . implode(',', $place) . ')';
    $stmt = db()->prepare($sql);
    $params = [];
    foreach ($data as $k => $v) {
        $params[':' . $k] = $v;
    }
    $stmt->execute($params);
    $id = (int)db()->lastInsertId();
    log_activity($id, 'create', 'สร้างโครงการ ' . $data['project_no']);
    return $id;
}

/** Update a project by id. */
function update_project(int $id, array $data, ?string $attachment = null): void
{
    if ($attachment !== null) {
        $data['attachment'] = $attachment;
    }
    $sets = [];
    $params = [':id' => $id];
    foreach ($data as $k => $v) {
        $sets[] = "$k = :$k";
        $params[':' . $k] = $v;
    }
    $sql = 'UPDATE projects SET ' . implode(',', $sets) . ' WHERE id = :id';
    db()->prepare($sql)->execute($params);
    log_activity($id, 'update', 'แก้ไขโครงการ ' . ($data['project_no'] ?? ''));
}

function delete_project(int $id): void
{
    $stmt = db()->prepare('SELECT project_no FROM projects WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $no = $stmt->fetchColumn();
    db()->prepare('DELETE FROM activity_logs WHERE project_id = :id')->execute([':id' => $id]);
    db()->prepare('DELETE FROM projects WHERE id = :id')->execute([':id' => $id]);
    log_activity(null, 'delete', 'ลบโครงการ ' . $no);
}

/* ======================================================================
 *  Activity log
 * ==================================================================== */

function log_activity(?int $projectId, string $action, string $detail = '', string $actor = 'system'): void
{
    try {
        $stmt = db()->prepare(
            "INSERT INTO activity_logs (project_id, action, detail, actor)
             VALUES (:pid, :action, :detail, :actor)"
        );
        $stmt->execute([
            ':pid'    => $projectId,
            ':action' => $action,
            ':detail' => $detail,
            ':actor'  => $actor,
        ]);
    } catch (Throwable $e) {
        // logging must never break the app
    }
}

/* ======================================================================
 *  Layout: header + footer
 * ==================================================================== */

function render_header(string $title = '', string $active = ''): void
{
    $nav = [
        'index.php'       => ['ภาพรวม Dashboard',   'bi-speedometer2'],
        'projects.php'    => ['รายการ Project',      'bi-folder2-open'],
        'panels.php'      => ['ติดตามรายตู้',        'bi-hdd-stack'],
        'deliveries.php'  => ['ติดตามการส่งมอบ',    'bi-truck'],
        'reports.php'     => ['รายงาน',             'bi-file-earmark-bar-graph'],
        'settings.php'    => ['ตั้งค่าระบบ',         'bi-gear'],
    ];
    ?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title ? $title . ' — ' : '') ?>Avatar Electric</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&family=Sarabun:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
<div class="app-shell">
  <!-- Sidebar -->
  <aside class="sidebar">
    <div class="brand">
      <img src="assets/img/logo.png" alt="logo" onerror="this.style.display='none'">
      <div>
        <div class="brand-name">AVATAR</div>
        <div class="brand-sub">ELECTRIC</div>
      </div>
    </div>
    <nav class="side-nav">
      <?php foreach ($nav as $href => [$label, $icon]): ?>
        <a href="<?= e($href) ?>" class="<?= $active === $href ? 'active' : '' ?>">
          <i class="bi <?= e($icon) ?>"></i><span><?= e($label) ?></span>
        </a>
      <?php endforeach; ?>
    </nav>
    <div class="side-foot">
      <div>Schedule of Project</div>
      <div class="text-muted small">v1.0</div>
    </div>
  </aside>

  <!-- Main -->
  <div class="main">
    <header class="topbar">
      <div>
        <h1 class="page-title"><?= e($title ?: 'Dashboard') ?></h1>
        <div class="page-sub">ระบบบริหารแผนงานโครงการ บริษัท อวตาร อิเล็คทริค จำกัด</div>
      </div>
      <div class="topbar-right">
        <div class="data-date">
          <i class="bi bi-calendar3"></i>
          ข้อมูล ณ วันที่ <?= e(format_date_dmy(date('Y-m-d'))) ?>
        </div>
        <div class="user-chip"><i class="bi bi-person-circle"></i> ผู้ดูแลระบบ</div>
      </div>
    </header>
    <main class="content">
    <?php
}

function render_footer(): void
{
    ?>
    </main>
    <footer class="app-footer">
      © <?= date('Y') ?> Avatar Electric Co., Ltd. — Schedule of Project Dashboard
    </footer>
  </div><!-- /main -->
</div><!-- /app-shell -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script src="assets/js/app.js"></script>
</body>
</html>
    <?php
}
