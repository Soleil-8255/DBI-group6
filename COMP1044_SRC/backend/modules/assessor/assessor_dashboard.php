<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../includes/db_connect.php';
require_once __DIR__ . '/../../../includes/functions.php';

ensure_session();
require_role('Assessor');

$uid = current_user_id();
if ($uid === null) {
    exit;
}

$fullName = trim((string) ($_SESSION['full_name'] ?? ''));
$greetFirst = '';
if ($fullName !== '') {
    $parts = preg_split('/\s+/', $fullName, 2, PREG_SPLIT_NO_EMPTY);
    $greetFirst = (string) ($parts[0] ?? '');
}

$totalStudents = 0;
$pendingCount = 0;
$completedCount = 0;
try {
    $kpiStmt = $pdo->prepare(
        'SELECT
            COUNT(*) AS `total`,
            COALESCE(SUM(CASE WHEN `i`.`status` = :c_done THEN 1 ELSE 0 END), 0) AS `completed`,
            COALESCE(SUM(CASE WHEN `i`.`status` <> :c_pending_base THEN 1 ELSE 0 END), 0) AS `pending`
         FROM `Internships` `i`
         WHERE `i`.`assessor_id` = :aid'
    );
    $kpiStmt->execute([
        ':aid' => $uid,
        ':c_done' => 'Completed',
        ':c_pending_base' => 'Completed',
    ]);
    $kpi = $kpiStmt->fetch();
    if ($kpi) {
        $totalStudents = (int) $kpi['total'];
        $completedCount = (int) $kpi['completed'];
        $pendingCount = (int) $kpi['pending'];
    }
} catch (PDOException $e) {
    app_log_exception('assessor_dashboard.kpi', $e);
}

$needle = trim((string) ($_GET['q'] ?? ''));

$sql = 'SELECT i.internship_id, i.student_id, i.status, i.start_date, i.end_date, c.company_name,
               s.cohort_year
        FROM `Internships` i
        INNER JOIN `Companies` c ON c.company_id = i.company_id
        INNER JOIN `Students` s ON s.student_id = i.student_id
        WHERE i.assessor_id = :aid';
$params = [':aid' => $uid];

if ($needle !== '') {
    $sql .= ' AND (i.student_id LIKE :n1 OR c.company_name LIKE :n2)';
    $like = '%' . $needle . '%';
    $params[':n1'] = $like;
    $params[':n2'] = $like;
}

$sql .= ' ORDER BY i.start_date DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$evaluateBase = app_route('assessor_evaluate.php');

$pageTitle = 'Assessor Dashboard';
require_once __DIR__ . '/../../../includes/header.php';
?>

<div class="app-dashboard-shell">
    <div class="assessor-workbench">
        <h2 class="assessor-workbench__greeting" id="assessor-welcome">
            <?php if ($greetFirst !== ''): ?>
                Welcome back, <?= h($greetFirst) ?>!
            <?php else: ?>
                Welcome back!
            <?php endif; ?>
        </h2>

        <div class="admin-workload__kpi-stagger" role="group" aria-label="Placement overview">
            <article class="admin-kpi-card admin-anim-entrance" aria-label="Total assignments">
                <p class="admin-kpi-card__label">Total assignments</p>
                <p class="admin-kpi-card__value"><?= h((string) $totalStudents) ?></p>
            </article>
            <article class="admin-kpi-card admin-anim-entrance" aria-label="Pending evaluation">
                <p class="admin-kpi-card__label">Pending evaluation</p>
                <p class="admin-kpi-card__value admin-kpi-card__value--alert"><?= h((string) $pendingCount) ?></p>
            </article>
            <article class="admin-kpi-card admin-anim-entrance" aria-label="Completed">
                <p class="admin-kpi-card__label">Completed</p>
                <p class="admin-kpi-card__value"><?= h((string) $completedCount) ?></p>
            </article>
        </div>
    </div>

    <section class="card app-card-pro admin-anim-entrance" aria-labelledby="assessor-placements-heading">
        <h2 class="section-heading" id="assessor-placements-heading">Your placement queue</h2>
        <p class="assessor-workbench__lede">Search by student ID or company. Press <kbd>Enter</kbd> to run the filter.</p>

        <form class="assessor-workbench-search" method="get" action="<?= h(app_route('assessor_dashboard.php')) ?>" role="search">
            <div class="assessor-workbench-search__row">
                <div class="admin-results-toolbar__search">
                    <div class="admin-results-search" role="search">
                        <svg class="admin-results-search__icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                            <circle cx="11" cy="11" r="7" />
                            <path d="M21 21l-4.2-4.2" />
                        </svg>
                        <label class="visually-hidden" for="assessor-wb-q">Search assignments</label>
                        <input
                            class="admin-results-search__input"
                            id="assessor-wb-q"
                            name="q"
                            type="search"
                            maxlength="120"
                            placeholder="Search by student ID or company…"
                            value="<?= h($needle) ?>"
                            autocomplete="off"
                            inputmode="search"
                        >
                    </div>
                </div>
                <?php if ($needle !== ''): ?>
                    <a class="text-link assessor-workbench-search__clear" href="<?= h(app_route('assessor_dashboard.php')) ?>">Clear</a>
                <?php endif; ?>
            </div>
        </form>

        <?php if ($rows === []): ?>
            <div class="assessor-workbench-empty" role="status">
                <?php if ($needle !== '' && $totalStudents > 0): ?>
                    <p class="lead">No placements match <strong><?= h($needle) ?></strong>. Try a different search.</p>
                <?php else: ?>
                    <p class="lead">No internship placements are assigned to you yet.</p>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-scroll assessor-workbench__table-wrap">
                <table class="data-table data-table--saas data-table--assessor-wb" id="assessor-workbench-table">
                    <thead>
                        <tr>
                            <th scope="col">Student</th>
                            <th scope="col">Company</th>
                            <th scope="col">Cohort &amp; period</th>
                            <th scope="col">Status</th>
                            <th scope="col">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $r): ?>
                            <?php
                            $st = (string) $r['status'];
                            $isOngoing = strcasecmp($st, 'Ongoing') === 0;
                            $isCompleted = strcasecmp($st, 'Completed') === 0;
                            $evalUrl = $evaluateBase . '?internship_id=' . rawurlencode((string) $r['internship_id']);
                            $periodLabel = internship_cohort_period_caption(
                                isset($r['cohort_year']) ? (string) $r['cohort_year'] : null,
                                isset($r['start_date']) ? (string) $r['start_date'] : null,
                                isset($r['end_date']) ? (string) $r['end_date'] : null
                            );
                            ?>
                            <tr>
                                <td><strong class="assessor-wb-mono"><?= h((string) $r['student_id']) ?></strong></td>
                                <td><?= h((string) $r['company_name']) ?></td>
                                <td class="assessor-wb-period"><?= h($periodLabel) ?></td>
                                <td>
                                    <?php if ($isOngoing): ?>
                                        <span class="status-pill status-pill--ongoing" title="Ongoing">
                                            <span class="status-pill__breath" aria-hidden="true"></span>
                                            Ongoing
                                        </span>
                                    <?php elseif ($isCompleted): ?>
                                        <span class="status-pill status-pill--completed" title="Completed">Completed</span>
                                    <?php else: ?>
                                        <span class="status-pill status-pill--neutral"><?= h($st) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($isOngoing): ?>
                                        <a class="btn-nottingham" href="<?= h($evalUrl) ?>">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                                                <path d="M12 20h9" />
                                                <path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z" />
                                            </svg>
                                            Evaluate
                                        </a>
                                    <?php elseif ($isCompleted): ?>
                                        <a class="btn-ghost-pill" href="<?= h($evalUrl) ?>">View Result</a>
                                    <?php else: ?>
                                        <a class="btn-ghost-pill" href="<?= h($evalUrl) ?>">Open</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</div>

<?php
$assessorNeedEvalToast = isset($_GET['need_eval']) && (string) ($_GET['need_eval'] ?? '') !== '0';
if ($assessorNeedEvalToast): ?>
<div id="assessor-eval-hint-toast" class="assessor-eval-hint-toast" role="status" aria-live="polite">Please select a student to evaluate first.</div>
<script>
(function () {
    var t = document.getElementById('assessor-eval-hint-toast');
    if (!t) {
        return;
    }
    requestAnimationFrame(function () {
        t.classList.add('assessor-eval-hint-toast--visible');
    });
    window.setTimeout(function () {
        t.classList.remove('assessor-eval-hint-toast--visible');
        window.setTimeout(function () {
            if (t && t.parentNode) {
                t.parentNode.removeChild(t);
            }
        }, 400);
    }, 4500);
    if (window.history && window.history.replaceState) {
        try {
            var u = new URL(window.location.href);
            u.searchParams.delete('need_eval');
            var q = u.searchParams.toString();
            window.history.replaceState({}, '', u.pathname + (q ? '?' + q : '') + u.hash);
        } catch (e) {}
    }
})();
</script>
<?php endif; ?>
<?php
require_once __DIR__ . '/../../../includes/footer.php';
