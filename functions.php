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
 * Compute the effective status of a project.
 * Priority order:
 *   1. completed_date set              -> completed  (manual override, always wins)
 *   2. all panels delivered            -> delivered
 *   3. some panels delivered           -> partial_delivery
 *   4. due_date < today                -> overdue
 *   5. due_date within NEAR_DUE_DAYS  -> near_due
 *   6. start_date <= today             -> in_progress
 *   7. otherwise                       -> pending
 *
 * Panel counts (panel_total / panel_delivered) must be pre-joined into $p
 * by get_projects() / get_project() — defaults to 0 if absent.
 */
function compute_status(array $p): string
{
    $today = strtotime(date('Y-m-d'));

    if (!empty($p['completed_date']) && $p['completed_date'] !== '0000-00-00') {
        return 'completed';
    }

    $panelTotal     = (int)($p['panel_total']     ?? 0);
    $panelDelivered = (int)($p['panel_delivered']  ?? 0);

    if ($panelTotal > 0) {
        if ($panelDelivered >= $panelTotal) {
            return 'delivered';
        }
        if ($panelDelivered > 0) {
            return 'partial_delivery';
        }
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

/** Fetch all projects (joined with dept + responsible person + panel delivery counts). */
function get_projects(array $filters = []): array
{
    $sql = "SELECT p.*, d.name AS department, u.name AS responsible,
                   COALESCE(pc.panel_total, 0)     AS panel_total,
                   COALESCE(pc.panel_delivered, 0) AS panel_delivered
            FROM projects p
            LEFT JOIN departments d ON d.id = p.department_id
            LEFT JOIN users u       ON u.id = p.responsible_id
            LEFT JOIN (
                SELECT project_id,
                       COUNT(*) AS panel_total,
                       SUM(CASE
                           WHEN status = 'delivered' THEN 1
                           WHEN actual_delivery_date IS NOT NULL
                                AND actual_delivery_date <= CURDATE() THEN 1
                           WHEN status_mode = 'MANUAL'
                                AND manual_status = 'm_delivered' THEN 1
                           ELSE 0
                       END) AS panel_delivered
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
                COALESCE(pc.panel_total, 0)     AS panel_total,
                COALESCE(pc.panel_delivered, 0) AS panel_delivered
         FROM projects p
         LEFT JOIN departments d ON d.id = p.department_id
         LEFT JOIN users u       ON u.id = p.responsible_id
         LEFT JOIN (
             SELECT project_id,
                    COUNT(*) AS panel_total,
                    SUM(CASE
                        WHEN status = 'delivered' THEN 1
                        WHEN actual_delivery_date IS NOT NULL
                             AND actual_delivery_date <= CURDATE() THEN 1
                        WHEN status_mode = 'MANUAL'
                             AND manual_status = 'm_delivered' THEN 1
                        ELSE 0
                    END) AS panel_delivered
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

/** All panel statuses (incl. computed overdue) -> Thai manufacturing labels. */
function panel_status_labels(): array
{
    return [
        'pending'        => 'รอเริ่มงาน',
        'design'         => 'กำลังเขียนแบบ',
        'material'       => 'รออุปกรณ์',
        'production'     => 'กำลังผลิต / ประกอบ',
        'wiring'         => 'กำลังเดินสาย',
        'qc'             => 'กำลัง FAT',
        'ready_delivery' => 'พร้อมส่งมอบ',
        'delivered'      => 'ส่งมอบแล้ว',
        'overdue'        => 'ล่าช้า',
    ];
}

function panel_status_colors(): array
{
    return [
        'pending'        => '#94A3B8', // Slate    — รอเริ่มงาน
        'design'         => '#3B82F6', // Blue     — กำลังเขียนแบบ
        'material'       => '#F59E0B', // Amber    — รออุปกรณ์
        'production'     => '#FF7A00', // Orange   — กำลังผลิต / ประกอบ (brand)
        'wiring'         => '#8B5CF6', // Purple   — กำลังเดินสาย
        'qc'             => '#06B6D4', // Cyan     — กำลัง FAT
        'ready_delivery' => '#059669', // Emerald  — พร้อมส่งมอบ
        'delivered'      => '#16A34A', // Green    — ส่งมอบแล้ว
        'overdue'        => '#EF4444', // Red      — ล่าช้า
    ];
}

/**
 * Status → progress percent mapping (Option B).
 * Statuses omitted from this map (overdue, m_overdue, m_on_hold, m_cancelled)
 * must NOT change progress — they are intentionally absent.
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
 * Effective (display) status of a panel.
 *
 * MANUAL mode: returns manual_status directly (user override).
 * AUTO mode:
 *   - actual_delivery_date set        -> delivered
 *   - target < today & not delivered  -> overdue
 *   - otherwise                       -> stored workflow step (status field)
 */
function compute_panel_status(array $panel): string
{
    if (($panel['status_mode'] ?? 'AUTO') === 'MANUAL') {
        $ms = $panel['manual_status'] ?? '';
        return ($ms !== '' && $ms !== null) ? $ms : ($panel['status'] ?? 'pending');
    }
    // AUTO logic — cache today per-request to avoid repeated strtotime calls across loops
    static $today = null;
    $today ??= strtotime(date('Y-m-d'));
    if (!empty($panel['actual_delivery_date']) && $panel['actual_delivery_date'] !== '0000-00-00') {
        // Only treat as delivered if the actual date is today or in the past
        if (strtotime($panel['actual_delivery_date']) <= $today) {
            return 'delivered';
        }
    }
    if (($panel['status'] ?? '') === 'delivered') {
        // Delivered via workflow buttons — verify actual date is not future
        $ado = $panel['actual_delivery_date'] ?? '';
        if (empty($ado) || $ado === '0000-00-00' || strtotime($ado) <= $today) {
            return 'delivered';
        }
        // actual_delivery_date is in the future — show as ready_delivery instead
        return 'ready_delivery';
    }
    if (!empty($panel['target_delivery_date']) && $panel['target_delivery_date'] !== '0000-00-00') {
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
    $delivered = ($count['delivered'] ?? 0) + ($count['m_delivered'] ?? 0);
    $overdue   = ($count['overdue']   ?? 0) + ($count['m_overdue']   ?? 0);
    $notProducing = ['pending','m_wait_start','delivered','m_delivered',
                     'overdue','m_overdue','m_on_hold','m_cancelled'];
    $producing = $total - array_sum(array_map(fn($k) => $count[$k] ?? 0, $notProducing));
    return [
        'total'     => $total,
        'count'     => $count,
        'delivered' => $delivered,
        'overdue'   => $overdue,
        'producing' => max(0, $producing),
        'pending'   => ($count['pending'] ?? 0) + ($count['m_wait_start'] ?? 0),
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
    $nullable  = fn($v) => ($v === '' || $v === null) ? null : $v;
    $actual    = $nullable($_POST['actual_delivery_date'] ?? '');
    // actual_delivery_date must be today or past — future dates are not valid for a completed delivery
    if ($actual !== null && strtotime($actual) > strtotime(date('Y-m-d'))) {
        $actual = null;
    }
    $mode      = in_array($_POST['status_mode'] ?? '', ['AUTO','MANUAL'], true)
                 ? $_POST['status_mode'] : 'AUTO';
    $manualVal = $nullable($_POST['manual_status'] ?? '');
    // Validate manual_status against allowed keys
    if ($manualVal !== null && !array_key_exists($manualVal, manual_status_options())) {
        $manualVal = null;
    }
    $data = [
        'panel_no'             => trim($_POST['panel_no'] ?? ''),
        'panel_name'           => trim($_POST['panel_name'] ?? ''),
        'panel_type'           => $nullable(trim($_POST['panel_type'] ?? '')),
        'panel_size'           => $nullable(trim($_POST['panel_size'] ?? '')),
        'delivery_group'       => $nullable(trim($_POST['delivery_group'] ?? '')),
        'target_delivery_date' => $nullable($_POST['target_delivery_date'] ?? ''),
        'actual_delivery_date' => $actual,
        'status_mode'          => $mode,
        'manual_status'        => ($mode === 'MANUAL') ? $manualVal : null,
        'responsible'          => $nullable(trim($_POST['responsible'] ?? '')),
        'remark'               => $nullable(trim($_POST['remark'] ?? '')),
        'sort_order'           => (int)($_POST['sort_order'] ?? 0),
        'progress_percent'     => max(0, min(100, (int)($_POST['progress_percent'] ?? 0))),
    ];
    // Auto-stamp delivered only when actual date is today or already past
    // Progress is NOT forced — stays whatever the user set
    if ($actual && strtotime($actual) <= strtotime(date('Y-m-d'))) {
        $data['status']      = 'delivered';
        $data['status_mode'] = 'AUTO'; // delivered via actual date overrides mode
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

function set_panel_status(int $id, string $status): void
{
    if (!in_array($status, panel_workflow_statuses(), true)) {
        return;
    }
    $params     = [':s' => $status, ':id' => $id];
    $extraSql   = '';

    // Auto-stamp actual delivery date when marking as delivered
    if ($status === 'delivered') {
        $extraSql .= ', actual_delivery_date = COALESCE(actual_delivery_date, CURDATE())';
    }

    // Update progress_percent from mapping (Option B).
    // Statuses absent from the map (overdue etc.) keep current progress.
    $progressMap = panel_status_progress_map();
    if (array_key_exists($status, $progressMap)) {
        $extraSql .= ', progress_percent = :prog';
        $params[':prog'] = $progressMap[$status];
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
