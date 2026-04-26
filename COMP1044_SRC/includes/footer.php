<?php
declare(strict_types=1);

/**
 * Closes the active layout shell (login / dashboard / fallback).
 */

$appShell = $appShell ?? 'default';
$role = current_user_role();
$useLoginShell = ($appShell === 'login');
$useDashboardShell = ($role !== null && !$useLoginShell);
?>
<?php if ($useLoginShell): ?>
<?php elseif ($useDashboardShell): ?>
        </main>
        <footer class="site-footer site-footer--dashboard">
            <p>&copy; <?= h((string) date('Y')) ?> University of Nottingham Malaysia Campus — Internship Result Management System (COMP1044).</p>
        </footer>
    </div>
    </div>
</div>
<?php else: ?>
</main>
<footer class="site-footer">
    <p>&copy; <?= h((string) date('Y')) ?> University of Nottingham Malaysia Campus — Internship Result Management System (COMP1044).</p>
</footer>
<?php endif; ?>
</body>
</html>
