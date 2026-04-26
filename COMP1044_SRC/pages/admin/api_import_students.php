<?php
declare(strict_types=1);

/**
 * JSON API: bulk-import students from CSV (Admin only).
 * Default password: 123123. Uses INSERT IGNORE + orphan cleanup for duplicate handling.
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

/**
 * @return array{0: string, 1: string}
 */
function import_build_username(PDO $pdo, string $email, string $studentId): array
{
    $parts = explode('@', trim($email), 2);
    $local = strtolower($parts[0] ?? '');
    $candidate = preg_replace('/[^a-z0-9_]/', '_', $local);
    $candidate = trim((string) $candidate, '_');

    if ($candidate === '' || strlen($candidate) < 3) {
        $base = preg_replace('/[^a-z0-9]/', '', strtolower($studentId));
        $candidate = 'stud_' . ($base !== '' ? $base : 'user');
    }

    if (strlen($candidate) > 50) {
        $candidate = substr($candidate, 0, 50);
    }

    $baseUsername = $candidate;
    $username = $baseUsername;
    for ($s = 0; $s < 30; $s++) {
        $chk = $pdo->prepare('SELECT 1 FROM `Users` WHERE username = :u LIMIT 1');
        $chk->execute([':u' => $username]);
        if (!$chk->fetch()) {
            return [$username, ''];
        }
        $tag = '_' . (string) ($s + 1);
        $username = substr($baseUsername, 0, max(1, 50 - strlen($tag))) . $tag;
    }

    return ['', 'Could not allocate a unique username.'];
}

$passwordHash = password_hash('123123', PASSWORD_DEFAULT);

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

$req = ['student_id', 'full_name', 'email', 'cohort_year', 'prog_id'];
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

$insUserIgnore = $pdo->prepare(
    'INSERT IGNORE INTO `Users` (username, password_hash, role, full_name, email)
     VALUES (:username, :ph, \'Student\', :fn, :em)'
);
$insStudentIgnore = $pdo->prepare(
    'INSERT IGNORE INTO `Students` (student_id, user_id, prog_id, cohort_year)
     VALUES (:sid, :uid, :pid, :cy)'
);
$delUser = $pdo->prepare('DELETE FROM `Users` WHERE user_id = :uid AND role = \'Student\' LIMIT 1');
$progOk = $pdo->prepare('SELECT 1 FROM `Programmes` WHERE prog_id = :p LIMIT 1');

try {
    while (($row = fgetcsv($handle)) !== false) {
        if (count($row) === 1 && trim((string) ($row[0] ?? '')) === '') {
            continue;
        }

        $studentId = trim((string) ($row[$map['student_id']] ?? ''));
        $fullName = trim((string) ($row[$map['full_name']] ?? ''));
        $email = trim((string) ($row[$map['email']] ?? ''));
        $cohortRaw = trim((string) ($row[$map['cohort_year']] ?? ''));
        $progId = (int) ($row[$map['prog_id']] ?? 0);

        if ($studentId === '' || $fullName === '' || $email === '' || $cohortRaw === '' || $progId <= 0) {
            $invalid++;
            continue;
        }
        if (strlen($studentId) > 20 || !preg_match('/^[A-Za-z0-9._-]+$/', $studentId)) {
            $invalid++;
            continue;
        }
        if (strlen($fullName) > 100 || strlen($email) > 100 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $invalid++;
            continue;
        }
        $cohortYear = filter_var($cohortRaw, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1990, 'max_range' => 2100],
        ]);
        if ($cohortYear === false) {
            $invalid++;
            continue;
        }

        $progOk->execute([':p' => $progId]);
        if (!$progOk->fetch()) {
            $invalid++;
            continue;
        }

        [$username, $allocErr] = import_build_username($pdo, $email, $studentId);
        if ($username === '' || $allocErr !== '') {
            $invalid++;
            continue;
        }

        $pdo->beginTransaction();
        try {
            $insUserIgnore->execute([
                ':username' => $username,
                ':ph' => $passwordHash,
                ':fn' => $fullName,
                ':em' => $email,
            ]);
            $newUid = (int) $pdo->lastInsertId();

            if ($newUid <= 0) {
                $pdo->commit();
                $skipped++;
                continue;
            }

            $insStudentIgnore->execute([
                ':sid' => $studentId,
                ':uid' => $newUid,
                ':pid' => $progId,
                ':cy' => (string) $cohortYear,
            ]);

            if ($insStudentIgnore->rowCount() === 0) {
                $delUser->execute([':uid' => $newUid]);
                $skipped++;
            } else {
                $imported++;
            }
            $pdo->commit();
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            app_log_exception('api_import_students.row', $e);
            $invalid++;
        }
    }
} finally {
    fclose($handle);
}

$msg = 'Successfully imported ' . (string) $imported . ' student' . ($imported === 1 ? '' : 's') . '.';
if ($skipped > 0) {
    $msg .= ' Skipped ' . (string) $skipped . ' duplicate or conflicting row' . ($skipped === 1 ? '' : 's') . '.';
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
