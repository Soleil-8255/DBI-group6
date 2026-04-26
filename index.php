<?php
declare(strict_types=1);

/**
 * Hand-off to the hand-in app folder. For grading, either open this URL or
 * set the site document root to the COMP1044_SRC directory.
 */

header('Location: ' . (string) (rtrim((string) dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/')), '/\\') . '/COMP1044_SRC/index.php'), true, 302);
exit;
