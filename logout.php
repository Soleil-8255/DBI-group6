<?php
declare(strict_types=1);

/**
 * Hand-off to COMP1044_SRC (same session scope as that folder if cookie path is parent).
 * Prefer: log out from the in-app control while browsing COMP1044_SRC.
 */

$dir = (string) dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/'));
$dir = rtrim($dir, '/\\');
if ($dir === '' || $dir === '.') {
    $dir = '';
}
$prefix = $dir === '' ? '' : $dir;
header('Location: ' . $prefix . '/COMP1044_SRC/logout.php', true, 302);
exit;
