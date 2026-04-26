<?php
declare(strict_types=1);

/**
 * Admin landing dashboard: KPIs, bento quick actions, recent audit feed.
 *
 * Innovation cross-links:
 * - Innovation 2: admin_workload.php
 * - Innovation 3: admin_alerts.php
 */

require_once __DIR__ . '/../../../includes/db_connect.php';
require_once __DIR__ . '/../../../includes/functions.php';

ensure_session();
require_role('Admin');

$metricStudents = 0;
$metricPlacements = 0;
$metricAssessorLoad = 0;
$metricAlerts = 0;
$recentActivities = [];
$metricsError = false;

try {
    $metricStudents = (int) $pdo->query('SELECT COUNT(*) FROM `Students`')->fetchColumn();
} catch (PDOException $e) {
    app_log_exception('admin_dashboard.metric_students', $e);
    $metricsError = true;
}
try {
    $metricPlacements = (int) $pdo->query(
        "SELECT COUNT(*) FROM `Internships` WHERE `status` = 'Ongoing'"
    )->fetchColumn();
} catch (PDOException $e) {
    app_log_exception('admin_dashboard.metric_placements', $e);
    $metricsError = true;
}
try {
    $metricAssessorLoad = (int) $pdo->query(
        'SELECT COUNT(DISTINCT `assessor_id`) FROM `Internships` WHERE `status` = \'Ongoing\''
    )->fetchColumn();
} catch (PDOException $e) {
    app_log_exception('admin_dashboard.metric_assessors', $e);
    $metricsError = true;
}
try {
    $metricAlerts = (int) $pdo->query(
        "SELECT COUNT(*) FROM `Audit_Logs` WHERE `action_type` = 'GRADE_ALERT'"
    )->fetchColumn();
} catch (PDOException $e) {
    app_log_exception('admin_dashboard.metric_alerts', $e);
    $metricsError = true;
}

try {
    $actStmt = $pdo->query(
        'SELECT `action_type`, `description`, `timestamp` FROM `Audit_Logs` ORDER BY `timestamp` DESC LIMIT 10'
    );
    $recentActivities = $actStmt->fetchAll();
} catch (PDOException $e) {
    app_log_exception('admin_dashboard.recent_activities', $e);
    $recentActivities = [];
    $metricsError = true;
}

$pageTitle = 'Admin Dashboard';
$adminDashboardGreeting = true;
require_once __DIR__ . '/../../../includes/header.php';

$svgStroke = 'none';
$svgColor = '#1A3A6E';
$svg = static function (string $inner) use ($svgColor): string {
    return '<svg class="admin-dash-svg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="' . $svgColor . '" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $inner . '</svg>';
};
?>

<div class="admin-dash">
    <h2 class="visually-hidden">Dashboard overview</h2>
    <?php if ($metricsError): ?>
        <p class="admin-dash__alert">Some figures could not be loaded. If this persists, check the database connection.</p>
    <?php endif; ?>

    <div class="admin-kpi-stagger" role="group" aria-label="Key statistics">
        <article class="admin-kpi-card admin-anim-entrance" aria-label="Total students">
            <div class="admin-kpi-card__icon">
                <?= $svg('<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>') ?>
            </div>
            <p class="admin-kpi-card__label">Total students</p>
            <p class="admin-kpi-card__value" aria-live="polite"><?= h((string) $metricStudents) ?></p>
        </article>
        <article class="admin-kpi-card admin-anim-entrance" aria-label="Active placements">
            <div class="admin-kpi-card__icon">
                <?= $svg('<rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>') ?>
            </div>
            <p class="admin-kpi-card__label">Active placements</p>
            <p class="admin-kpi-card__value" aria-live="polite"><?= h((string) $metricPlacements) ?></p>
        </article>
        <article class="admin-kpi-card admin-anim-entrance" aria-label="Assessor workloads">
            <div class="admin-kpi-card__icon">
                <?= $svg('<path d="M18 20V10"/><path d="M12 20V4"/><path d="M6 20v-6"/>') ?>
            </div>
            <p class="admin-kpi-card__label">Assessor workloads</p>
            <p class="admin-kpi-card__value" aria-live="polite"><?= h((string) $metricAssessorLoad) ?></p>
        </article>
        <article class="admin-kpi-card admin-anim-entrance" aria-label="System alerts">
            <div class="admin-kpi-card__icon">
                <?= $svg('<path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>') ?>
            </div>
            <p class="admin-kpi-card__label">System alerts</p>
            <p class="admin-kpi-card__value" aria-live="polite"><?= h((string) $metricAlerts) ?></p>
        </article>
    </div>

    <section class="admin-bento-wrap" aria-labelledby="admin-bento-heading">
        <h2 class="admin-bento-wrap__title admin-anim-entrance" id="admin-bento-heading">Quick actions</h2>
        <div class="admin-bento-stagger">
            <a class="admin-bento-card admin-anim-entrance" href="<?= h(app_route('admin_manage_users.php')) ?>">
                <span class="admin-bento-card__icon"><?= $svg('<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>') ?></span>
                <span class="admin-bento-card__body">
                    <span class="admin-bento-card__name">User management</span>
                    <span class="admin-bento-card__desc">Add, edit, or remove students and assessors</span>
                </span>
            </a>
            <a class="admin-bento-card admin-anim-entrance" href="<?= h(app_route('admin_internships.php')) ?>">
                <span class="admin-bento-card__icon"><?= $svg('<rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>') ?></span>
                <span class="admin-bento-card__body">
                    <span class="admin-bento-card__name">Internship assignment</span>
                    <span class="admin-bento-card__desc">Create and manage placement records</span>
                </span>
            </a>
            <a class="admin-bento-card admin-anim-entrance" href="<?= h(app_route('admin_results.php')) ?>">
                <span class="admin-bento-card__icon"><?= $svg('<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M16 13H8"/><path d="M16 17H8"/><path d="M10 9H8"/>') ?></span>
                <span class="admin-bento-card__body">
                    <span class="admin-bento-card__name">Result viewing</span>
                    <span class="admin-bento-card__desc">Search by student ID or name</span>
                </span>
            </a>
            <a class="admin-bento-card admin-anim-entrance" href="<?= h(app_route('admin_workload.php')) ?>">
                <span class="admin-bento-card__icon"><?= $svg('<path d="M18 20V10"/><path d="M12 20V4"/><path d="M6 20v-6"/>') ?></span>
                <span class="admin-bento-card__body">
                    <span class="admin-bento-card__name">Assessor workload</span>
                    <span class="admin-bento-card__desc">Innovation 2: heatmap and status overview</span>
                </span>
            </a>
            <a class="admin-bento-card admin-anim-entrance" href="<?= h(app_route('admin_alerts.php')) ?>">
                <span class="admin-bento-card__icon"><?= $svg('<path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>') ?></span>
                <span class="admin-bento-card__body">
                    <span class="admin-bento-card__name">Grade alerts</span>
                    <span class="admin-bento-card__desc">Innovation 3: circuit-breaker from audit log</span>
                </span>
            </a>
        </div>
    </section>

    <section class="admin-recent-activity admin-recent-activity--entrance" aria-labelledby="admin-recent-heading">
        <h2 class="admin-recent-activity__heading" id="admin-recent-heading">Recent activity</h2>
        <?php if ($recentActivities === []): ?>
            <p class="admin-recent-activity__empty">No audit entries yet.</p>
        <?php else: ?>
            <ul class="admin-activity-list">
                <?php foreach ($recentActivities as $row): ?>
                    <?php
                    $ts = (string) ($row['timestamp'] ?? '');
                    $tsDisp = $ts;
                    if ($ts !== '') {
                        $d = date_create($ts);
                        if ($d !== false) {
                            $tsDisp = $d->format('M j, Y g:i a');
                        }
                    }
                    ?>
                    <li class="admin-activity-list__item">
                        <span class="admin-activity-list__type"><?= h((string) ($row['action_type'] ?? '')) ?></span>
                        <span class="admin-activity-list__time"><?= h($tsDisp) ?></span>
                        <span class="admin-activity-list__desc"><?= h((string) ($row['description'] ?? '')) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</div>

<?php
require_once __DIR__ . '/../../../includes/footer.php';
