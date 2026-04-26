<?php
declare(strict_types=1);

/**
 * Internship Result Management System — PDO bootstrap (UNM / internship_system).
 * Schema: `COMP1044_database.sql` (e.g. `Users`, `Internships`, `Audit_Logs`).
 *
 * Security: shared `$pdo` + prepared statements only elsewhere; never expose raw DB errors to clients
 * when IRMS_DB_DEBUG_CONNECTION is false.
 */

// -------------------------------------------------------------------------
// TEMPORARY DEBUG — set to false before submission / any public deployment
// When true: connection failures print the real PDO message on-screen to fix
// host, port, db name, user, password, or MySQL not running.
// -------------------------------------------------------------------------
const IRMS_DB_DEBUG_CONNECTION = false;

// XAMPP (Windows) defaults: MySQL on 3306, root with empty password. MAMP often uses 8889 + "root" password.
$dsn = "mysql:host=127.0.0.1;port=3306;dbname=internship_system;charset=utf8mb4";
$dbUser = 'root';
$dbPass = '';

$pdoOptions = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, $pdoOptions);
} catch (PDOException $e) {
    error_log('[IRMS] Database connection failed: ' . $e->getMessage());
    http_response_code(503);
    if (IRMS_DB_DEBUG_CONNECTION) {
        $msg = htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        exit(
            '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>DB connection error</title></head><body>'
            . '<h1>Database connection failed (debug)</h1>'
            . '<p><strong>PDO message:</strong> ' . $msg . '</p>'
            . '<p>Typical fixes: start MySQL/MAMP; confirm <code>host</code>/<code>port</code>; '
            . 'match <code>dbname</code> to your imported schema; set correct <code>root</code> password in this file.</p>'
            . '</body></html>'
        );
    }
    exit('The system is temporarily unavailable. Please try again later.');
}
