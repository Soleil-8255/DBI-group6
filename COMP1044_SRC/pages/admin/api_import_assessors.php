<?php
declare(strict_types=1);

/**
 * JSON API: bulk-import assessors from CSV (Admin only).
 * Default password: 123123. Optional "password" column. Uses INSERT IGNORE for duplicates.
 *
 * json_encode(..., 256) is the same as the JSON_UNESCAPED_UNICODE flag (Intelephense may flag the constant name).
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

$csrf = (string) ($_POST['csrf_token'] ?? '');
if (!validate_csrf_token($csrf)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => app_public_error('csrf')], 256);
    exit;
}

if (!isset($_FILES['csv']) || !is_array($_FILES['csv'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'No file uploaded.'], 256);
    exit;
}

$uploadError = (int) ($_FILES['csv']['error'] ?? UPLOAD_ERR_NO_FILE);
if ($uploadError !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'File upload failed.'], 256);
    exit;
}

$tmp = (string) ($_FILES['csv']['tmp_name'] ?? '');
$origName = (string) ($_FILES['csv']['name'] ?? '');
if ($tmp === '' || !is_uploaded_file($tmp)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid upload.'], 256);
    exit;
}

$ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
if ($ext !== '' && $ext !== 'csv') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Please upload a .csv file.'], 256);
    exit;
}

$handle = fopen($tmp, 'rb');
if ($handle === false) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Could not read the file.'], 256);
    exit;
}

$imported = 0;
$skipped = 0;
$invalid = 0;

$header = fgetcsv($handle);
if ($header === false) {
    fclose($handle);
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'The CSV is empty.'], 256);
    exit;
}

if (isset($header[0]) && is_string($header[0]) && strlen($header[0]) >= 3
    && substr($header[0], 0, 3) === "\xEF\xBB\xBF") {
    $header[0] = substr($header[0], 3);
}

$map = [];
foreach ($header as $i => $name) {
    $key = strtolower(trim((string) $name));
    if ($key !== '') {
        $map[$key] = (int) $i;
    }
}

$req = ['username', 'full_name', 'email'];
foreach ($req as $col) {
    if (!isset($map[$col])) {
        fclose($handle);
        http_response_code(400);
        echo json_encode([
            'ok' => false,
            'error' => 'Missing required column: ' . $col . '. Use the provided template (header row).',
        ], 256);
        exit;
    }
}
$hasPasswordCol = isset($map['password']);

$ins = $pdo->prepare(
    'INSERT IGNORE INTO `Users` (username, password_hash, role, full_name, email)
     VALUES (:u, :ph, \'Assessor\', :fn, :em)'
);

try {
    while (($row = fgetcsv($handle)) !== false) {
        if (count($row) === 1 && trim((string) ($row[0] ?? '')) === '') {
            continue;
        }

        $username = trim((string) ($row[$map['username']] ?? ''));
        $fullName = trim((string) ($row[$map['full_name']] ?? ''));
        $email = trim((string) ($row[$map['email']] ?? ''));
        $passRaw = $hasPasswordCol ? trim((string) ($row[$map['password']] ?? '')) : '';

        if ($username === '' || $fullName === '' || $email === '') {
            $invalid++;
            continue;
        }
        if (strlen($username) > 50 || !preg_match('/^[A-Za-z0-9._-]+$/', $username)) {
            $invalid++;
            continue;
        }
        if (strlen($fullName) > 100 || strlen($email) > 100 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $invalid++;
            continue;
        }
        if ($passRaw !== '' && strlen($passRaw) > 255) {
            $invalid++;
            continue;
        }
        if ($passRaw === '') {
            $passRaw = '123123';
        }

        try {
            $ph = password_hash($passRaw, PASSWORD_DEFAULT);
            $ins->execute([':u' => $username, ':ph' => $ph, ':fn' => $fullName, ':em' => $email]);
            $newId = (int) $pdo->lastInsertId();
            if ($newId <= 0) {
                $skipped++;
            } else {
                $imported++;
            }
        } catch (PDOException $e) {
            app_log_exception('api_import_assessors.row', $e);
            $invalid++;
        }
    }
} finally {
    fclose($handle);
}

$msg = 'Successfully imported ' . (string) $imported . ' assessor' . ($imported === 1 ? '' : 's') . '.';
if ($skipped > 0) {
    $msg .= ' Skipped ' . (string) $skipped . ' duplicate row' . ($skipped === 1 ? '' : 's') . '.';
}
if ($invalid > 0) {
    $msg .= ' ' . (string) $invalid . ' row' . ($invalid === 1 ? ' was' : 's were') . ' invalid.';
}

echo json_encode(
    [
        'ok' => true,
        'imported' => $imported,
        'skipped' => $skipped,
        'invalid' => $invalid,
        'message' => $msg,
    ],
    256
);
