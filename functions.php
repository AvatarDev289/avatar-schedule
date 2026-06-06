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

/* ======================================================================
 *  Status logic
 * ==================================================================== */

/** All status keys -> Thai labels. */
function status_labels(): array
{
    return [
        'pending'     => 'รอเริ่มงาน',
        'in_progress' => 'กำลังดำเนินการ',
        'near_due'    => 'ใกล้ครบกำหนด',
        'completed'   => 'เสร็จแล้ว',
        'overdue'     => 'ล่าช้า / Overdue',
    ];
}

/** All status keys -> hex colors (Avatar Electric brand palette). */
function status_colors(): array
{
    return [
        'pending'     => '#6B7280', // gray-500
        'in_progress' => '#3B82F6', // info blue
        'near_due'    => '#F59E0B', // warning
        'completed'   => '#10B981', // success
        'overdue'     => '#EF4444', // danger
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
        'pending'     => 'badge-status pending',
        'in_progress' => 'badge-status in_progress',
        'near_due'    => 'badge-status near_due',
        'completed'   => 'badge-status completed',
        'overdue'     => 'badge-status overdue',
    ][$status] ?? 'badge-status pending';
}

/**
 * Compute the effective status of a project from its dates.
 * Business rules:
 *   - completed_date set                       -> completed
 *   - not done & due_date < today              -> overdue
 *   - not done & due_date within NEAR_DUE_DAYS  -> near_due
 *   - started (start_date <= today) not done   -> in_progress
 *   - otherwise                                -> pending
 */
function compute_status(array $p): string
{
    $today = strtotime(date('Y-m-d'));

    if (!empty($p['completed_date']) && $p['completed_date'] !== '0000-00-00') {
        return 'completed';
    }

    $due   = !empty($p['due_date'])   ? strtotime($p['due_date'])   : null;
    $start = !empty($p['start_date']) ? strtotime($p['start_date']) : null;

    if ($due !== null) {
        if ($due < $today) {
            return 'overdue';
        }
        $diffDays = (int)floor(($due - $today) / 86400);
        if ($diffDays <= NEAR_DUE_DAYS) {
            return 'near_due';
        }
    }

    if ($start !== null && $start <= $today) {
        return 'in_progress';
    }

    return 'pending';
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

/** Fetch all projects (joined with dept + responsible person). */
function get_projects(array $filters = []): array
{
    $sql = "SELECT p.*, d.name AS department, u.name AS responsible
            FROM projects p
            LEFT JOIN departments d ON d.id = p.department_id
            LEFT JOIN users u       ON u.id = p.responsible_id";
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

/** Fetch a single project by id (with joins). */
function get_project(int $id): ?array
{
    $stmt = db()->prepare(
        "SELECT p.*, d.name AS department, u.name AS responsible
         FROM projects p
         LEFT JOIN departments d ON d.id = p.department_id
         LEFT JOIN users u       ON u.id = p.responsible_id
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
        "SELECT * FROM project_panels WHERE project_id = :id
         ORDER BY sort_order, id"
    );
    $stmt->execute([':id' => $projectId]);
    $rows = $stmt->fetchAll();
    foreach ($rows as &$r) {
        $r['eff_status'] = compute_panel_status($r);
    }
    unset($r);
    return $rows;
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
        "SELECT * FROM project_panels WHERE project_id = ? AND id IN ($place)
         ORDER BY sort_order, id"
    );
    $stmt->execute(array_merge([$projectId], $ids));
    $rows = $stmt->fetchAll();
    foreach ($rows as &$r) {
        $r['eff_status'] = compute_panel_status($r);
    }
    unset($r);
    return $rows;
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

/** All panel statuses (incl. computed overdue) -> Thai labels. */
function panel_status_labels(): array
{
    return [
        'pending'        => 'รอเริ่ม',
        'design'         => 'ออกแบบ',
        'material'       => 'เตรียมอุปกรณ์',
        'production'     => 'ผลิตโครงตู้',
        'wiring'         => 'ประกอบ & Wiring',
        'qc'             => 'ตรวจสอบ QC',
        'ready_delivery' => 'พร้อมส่ง',
        'delivered'      => 'ส่งมอบแล้ว',
        'overdue'        => 'ล่าช้า',
    ];
}

function panel_status_colors(): array
{
    // Avatar Electric brand status palette (matches the rebrand spec)
    return [
        'pending'        => '#F59E0B', // Pending
        'design'         => '#3B82F6', // Engineering
        'material'       => '#0EA5E9', // Material
        'production'     => '#FF7A00', // Production (primary orange)
        'wiring'         => '#8B5CF6', // Wiring
        'qc'             => '#10B981', // Testing / QC
        'ready_delivery' => '#059669', // Ready delivery
        'delivered'      => '#16A34A', // Delivered
        'overdue'        => '#EF4444', // Overdue
    ];
}

/** status -> standard progress percent. */
function panel_status_progress_map(): array
{
    return [
        'pending'        => 0,
        'design'         => 10,
        'material'       => 25,
        'production'     => 45,
        'wiring'         => 65,
        'qc'             => 85,
        'ready_delivery' => 95,
        'delivered'      => 100,
    ];
}

function panel_status_label(string $s): string { return panel_status_labels()[$s] ?? $s; }
function panel_status_color(string $s): string { return panel_status_colors()[$s] ?? '#6B7280'; }

/** progress percent that corresponds to a workflow status. */
function panel_progress_for_status(string $status): int
{
    return panel_status_progress_map()[$status] ?? 0;
}

/**
 * Effective (display) status of a panel:
 *   - actual_delivery_date set            -> delivered
 *   - not delivered & target < today      -> overdue
 *   - otherwise                           -> stored workflow status
 */
function compute_panel_status(array $panel): string
{
    if (!empty($panel['actual_delivery_date']) && $panel['actual_delivery_date'] !== '0000-00-00') {
        return 'delivered';
    }
    if (($panel['status'] ?? '') === 'delivered') {
        return 'delivered';
    }
    if (!empty($panel['target_delivery_date']) && $panel['target_delivery_date'] !== '0000-00-00') {
        $today  = strtotime(date('Y-m-d'));
        $target = strtotime($panel['target_delivery_date']);
        if ($target < $today) {
            return 'overdue';
        }
    }
    return $panel['status'] ?? 'pending';
}

/** Overdue days for a single panel (0 if not overdue). */
function panel_overdue_days(array $panel): int
{
    if (compute_panel_status($panel) !== 'overdue') {
        return 0;
    }
    $today  = strtotime(date('Y-m-d'));
    $target = strtotime($panel['target_delivery_date']);
    return (int)floor(($today - $target) / 86400);
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
    $sql .= ' ORDER BY p.project_no, pp.sort_order, pp.id';

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
 * "producing" = active work (not pending / delivered / overdue).
 */
function panel_stats(array $panels): array
{
    $keys  = array_keys(panel_status_labels());
    $count = array_fill_keys($keys, 0);
    $sumProgress = 0;
    foreach ($panels as $pn) {
        $s = $pn['eff_status'] ?? compute_panel_status($pn);
        $count[$s] = ($count[$s] ?? 0) + 1;
        $sumProgress += (int)$pn['progress_percent'];
    }
    $total     = count($panels);
    $delivered = $count['delivered'];
    $overdue   = $count['overdue'];
    $producing = $count['design'] + $count['material'] + $count['production']
               + $count['wiring'] + $count['qc'] + $count['ready_delivery'];
    return [
        'total'     => $total,
        'count'     => $count,
        'delivered' => $delivered,
        'overdue'   => $overdue,
        'producing' => $producing,
        'pending'   => $count['pending'],
        'overall'   => $total ? (int)round($sumProgress / $total) : 0,
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
    $status   = $_POST['status'] ?? 'pending';
    if (!in_array($status, panel_workflow_statuses(), true)) {
        $status = 'pending';
    }
    $actual = $nullable($_POST['actual_delivery_date'] ?? '');
    // delivering implies status delivered, and vice-versa keep consistent
    if ($status === 'delivered' && !$actual) {
        $actual = date('Y-m-d');
    }
    return [
        'panel_no'             => trim($_POST['panel_no'] ?? ''),
        'panel_name'           => trim($_POST['panel_name'] ?? ''),
        'panel_type'           => $nullable(trim($_POST['panel_type'] ?? '')),
        'panel_size'           => $nullable(trim($_POST['panel_size'] ?? '')),
        'delivery_group'       => $nullable(trim($_POST['delivery_group'] ?? '')),
        'target_delivery_date' => $nullable($_POST['target_delivery_date'] ?? ''),
        'actual_delivery_date' => $actual,
        'status'               => $status,
        // progress always follows the status map (rule: progress must match status)
        'progress_percent'     => panel_progress_for_status($status),
        'responsible'          => $nullable(trim($_POST['responsible'] ?? '')),
        'remark'               => $nullable(trim($_POST['remark'] ?? '')),
        'sort_order'           => (int)($_POST['sort_order'] ?? 0),
    ];
}

function create_panel(int $projectId, array $data): int
{
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
    $pid = (int)(db()->query("SELECT project_id FROM project_panels WHERE id = " . (int)$id)->fetchColumn());
    recompute_project_progress($pid);
    log_activity($pid, 'panel_edit', 'แก้ไขตู้ ' . ($data['panel_no'] ?? ''));
}

function set_panel_status(int $id, string $status): void
{
    if (!in_array($status, panel_workflow_statuses(), true)) {
        return;
    }
    $progress = panel_progress_for_status($status);
    $actualSql = '';
    if ($status === 'delivered') {
        // stamp actual delivery date if not already set
        $actualSql = ', actual_delivery_date = COALESCE(actual_delivery_date, CURDATE())';
    }
    db()->prepare(
        "UPDATE project_panels
         SET status = :s, progress_percent = :p $actualSql
         WHERE id = :id"
    )->execute([':s' => $status, ':p' => $progress, ':id' => $id]);

    $pid = (int)(db()->query("SELECT project_id FROM project_panels WHERE id = " . (int)$id)->fetchColumn());
    recompute_project_progress($pid);
    log_activity($pid, 'panel_status', 'อัปเดตสถานะตู้ -> ' . $status);
}

function delete_panel(int $id): void
{
    $row = db()->query("SELECT project_id, panel_no FROM project_panels WHERE id = " . (int)$id)->fetch();
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
        $ts0 = strtotime($t['start_date']);
        $ts1 = strtotime($t['end_date']);
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
        'project_no'     => trim($_POST['project_no'] ?? ''),
        'project_name'   => trim($_POST['project_name'] ?? ''),
        'description'    => $nullable(trim($_POST['description'] ?? '')),
        'customer'       => $nullable(trim($_POST['customer'] ?? '')),
        'department_id'  => $nullable($_POST['department_id'] ?? '') ? (int)$_POST['department_id'] : null,
        'responsible_id' => $nullable($_POST['responsible_id'] ?? '') ? (int)$_POST['responsible_id'] : null,
        'start_date'     => $nullable($_POST['start_date'] ?? ''),
        'due_date'       => $nullable($_POST['due_date'] ?? ''),
        'delivery_date'  => $nullable($_POST['delivery_date'] ?? ''),
        'completed_date' => $nullable($_POST['completed_date'] ?? ''),
        'status'         => $_POST['status'] ?? 'pending',
        'progress'       => max(0, min(100, (int)($_POST['progress'] ?? 0))),
        'amount'         => (float)($_POST['amount'] ?? 0),
        'remark'         => $nullable(trim($_POST['remark'] ?? '')),
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
        'index.php'       => ['ภาพรวม Dashboard', 'bi-speedometer2'],
        'projects.php'    => ['รายการ Project', 'bi-folder2-open'],
        'panels.php'      => ['ติดตามรายตู้ (Panel)', 'bi-hdd-stack'],
        'project_add.php' => ['เพิ่ม Project', 'bi-plus-circle'],
        'print_report.php'=> ['รายงานผู้บริหาร', 'bi-printer'],
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
          ข้อมูล ณ วันที่ <?= e(format_date(date('Y-m-d'))) ?>
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
