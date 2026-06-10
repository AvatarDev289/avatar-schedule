<?php
/**
 * api/panel_attachment_delete.php
 * DELETE a panel attachment by id.
 * POST JSON: {"id": 123}
 */
require_once dirname(__DIR__) . '/functions.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$body  = json_decode(file_get_contents('php://input'), true);
$attId = (int)($body['id'] ?? 0);

if ($attId <= 0) {
    echo json_encode(['ok' => false, 'error' => 'Invalid id']);
    exit;
}

$st = db()->prepare("SELECT panel_id FROM panel_attachments WHERE id = :id");
$st->execute([':id' => $attId]);
$row = $st->fetch();

if (!$row) {
    echo json_encode(['ok' => false, 'error' => 'Not found']);
    exit;
}

$ok = delete_panel_attachment($attId, (int)$row['panel_id']);
echo json_encode(['ok' => $ok]);
