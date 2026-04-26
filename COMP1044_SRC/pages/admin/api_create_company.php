<?php
declare(strict_types=1);

/**
 * JSON API: create a company (Admin). Companies.state_id is required by schema; we use the first state in DB.
 */

require_once __DIR__ . '/../../includes/db_connect.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

ensure_session();

if (($_SESSION['role'] ?? '') !== 'Admin') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Forbidden.'], 256);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed.'], 256);
    exit;
}

$raw = (string) file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid JSON.'], 256);
    exit;
}

$csrf = (string) ($data['csrf_token'] ?? '');
if (!validate_csrf_token($csrf)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => app_public_error('csrf')], 256);
    exit;
}

$name = trim((string) ($data['company_name'] ?? ''));
if ($name === '' || strlen($name) > 150) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Company name is required (max 150 characters).'], 256);
    exit;
}

try {
    $st = $pdo->query('SELECT state_id FROM `States` ORDER BY state_id ASC LIMIT 1');
    $row = $st ? $st->fetch(PDO::FETCH_ASSOC) : false;
    $stateId = $row ? (int) $row['state_id'] : 0;
    if ($stateId <= 0) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'No state configured in database.'], 256);
        exit;
    }

    $ins = $pdo->prepare(
        'INSERT INTO `Companies` (state_id, company_name, contact_person) VALUES (:sid, :n, NULL)'
    );
    $ins->execute([':sid' => $stateId, ':n' => $name]);
    $newId = (int) $pdo->lastInsertId();
    if ($newId <= 0) {
        throw new PDOException('No company_id after insert.');
    }
    echo json_encode(['ok' => true, 'company_id' => $newId, 'company_name' => $name], 256);
} catch (PDOException $e) {
    app_log_exception('api_create_company', $e);
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => app_public_error('db_write')], 256);
}
