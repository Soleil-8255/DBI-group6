<?php
declare(strict_types=1);

/**
 * INNOVATION 2 — Assessor workload: KPI grid, capacity bars, status pills, ghost actions.
 * Data: same rules as `Assessor_Workload_View` in COMP1044_database.sql, with assessor_id for deep links.
 */

require_once __DIR__ . '/../../../includes/db_connect.php';
require_once __DIR__ . '/../../../includes/functions.php';

ensure_session();
require_role('Admin');

/** Max “safe” placements for the capacity bar (width = min(100%, n/cap*100%)). */
const WORKLOAD_CAPACITY_MAX = 10;

/** Assessor counts as “overloaded” in the KPI if assigned students >= this value. */
const WORKLOAD_KPI_OVERLOADED_THRESHOLD = 10;

/**
 * @return 'workload-bar__fill--high'|'workload-bar__fill--mid'|'workload-bar__fill--low'
 */
function workload_bar_fill_class(int $students, int $capacityMax = WORKLOAD_CAPACITY_MAX): string
{
    if ($students >= $capacityMax) {
        return 'workload-bar__fill--high';
    }
    if ($students >= 5) {
        return 'workload-bar__fill--mid';
    }
    return 'workload-bar__fill--low';
}

/**
 * @return 'workload-pill--overloaded'|'workload-pill--busy'|'workload-pill--optimal'
 */
function workload_status_pill_class(string $rawStatus): string
{
    $s = trim($rawStatus);
    if (strcasecmp($s, 'Overloaded') === 0) {
        return 'workload-pill--overloaded';
    }
    if (strcasecmp($s, 'Busy') === 0) {
        return 'workload-pill--busy';
    }
    return 'workload-pill--optimal';
}

/**
 * @return 'workload-pill--overloaded'|'workload-pill--busy'|'workload-pill--optimal'
 */
function workload_status_pill_label(string $rawStatus): string
{
    $s = trim($rawStatus);
    if (strcasecmp($s, 'Overloaded') === 0) {
        return 'OVERLOADED';
    }
    if (strcasecmp($s, 'Busy') === 0) {
        return 'BUSY';
    }
    if (strcasecmp($s, 'Optimal') === 0) {
        return 'OPTIMAL';
    }
    return $s !== '' ? strtoupper($s) : '—';
}

/**
 * @param array<int, array<string, mixed>> $rows
 * @return array<int, array<string, mixed>>
 */
function workload_filter_by_search(array $rows, string $q): array
{
    $q = trim($q);
    if ($q === '') {
        return $rows;
    }
    $qLower = mb_strtolower($q, 'UTF-8');
    $out = [];
    foreach ($rows as $r) {
        $name = (string) ($r['assessor_name'] ?? '');
        if (mb_stripos($name, $q, 0, 'UTF-8') !== false) {
            $out[] = $r;
        }
    }
    return $out;
}

$sql = 'SELECT
            u.user_id AS assessor_id,
            u.full_name AS assessor_name,
            COUNT(i.internship_id) AS students_assigned,
            CASE
                WHEN COUNT(i.internship_id) >= 5 THEN \'Overloaded\'
                WHEN COUNT(i.internship_id) BETWEEN 3 AND 4 THEN \'Busy\'
                ELSE \'Optimal\'
            END AS workload_status
        FROM `Users` u
        LEFT JOIN `Internships` i ON u.user_id = i.assessor_id
        WHERE u.role = \'Assessor\'
        GROUP BY u.user_id, u.full_name
        ORDER BY u.full_name ASC';
$stmt = $pdo->query($sql);
$workloadRowsAll = $stmt->fetchAll();

$listQ = trim((string) ($_GET['q'] ?? ''));
$workloadRows = workload_filter_by_search($workloadRowsAll, $listQ);

$totalAssessors = count($workloadRowsAll);
$sumStudents = 0;
$overloadedAssessors = 0;
foreach ($workloadRowsAll as $r) {
    $n = (int) ($r['students_assigned'] ?? 0);
    $sumStudents += $n;
    if ($n >= WORKLOAD_KPI_OVERLOADED_THRESHOLD) {
        $overloadedAssessors += 1;
    }
}
$avgLoad = $totalAssessors > 0
    ? round($sumStudents / $totalAssessors, 1)
    : 0.0;
$kpiAverageDisplay = $totalAssessors > 0
    ? number_format($avgLoad, 1, '.', '')
    : '—';

$hrefManageAssessorsBase = app_route('admin_manage_users.php') . '?tab=assessors';

$pageTitle = 'Assessor Workload';
require_once __DIR__ . '/../../../includes/header.php';
?>

<section class="card admin-workload" id="admin-workload" aria-labelledby="page-main-title">
    <div class="admin-workload__kpi-stagger" role="group" aria-label="Workload key figures">
        <article class="admin-kpi-card admin-anim-entrance" aria-label="Total assessors">
            <p class="admin-kpi-card__label">Total assessors</p>
            <p class="admin-kpi-card__value"><?= h((string) $totalAssessors) ?></p>
        </article>
        <article class="admin-kpi-card admin-anim-entrance" aria-label="Overloaded assessors">
            <p class="admin-kpi-card__label">Overloaded (≥<?= h((string) WORKLOAD_KPI_OVERLOADED_THRESHOLD) ?> students)</p>
            <p class="admin-kpi-card__value admin-kpi-card__value--alert"><?= h((string) $overloadedAssessors) ?></p>
        </article>
        <article class="admin-kpi-card admin-anim-entrance" aria-label="Average load">
            <p class="admin-kpi-card__label">Average load (students / assessor)</p>
            <p class="admin-kpi-card__value"><?= h($kpiAverageDisplay) ?></p>
        </article>
    </div>

    <form class="admin-workload__search" method="get" action="<?= h(app_route('admin_workload.php')) ?>" role="search">
        <label class="visually-hidden" for="workload-search-q">Search by assessor name</label>
        <div class="admin-workload__search-row">
            <input
                type="search"
                name="q"
                id="workload-search-q"
                class="input-nottingham admin-workload__search-input"
                placeholder="Search assessors by name…"
                value="<?= h($listQ) ?>"
                autocomplete="off"
            >
            <button class="btn-nottingham" type="submit">Search</button>
        </div>
    </form>

    <div class="admin-workload__table-wrap" id="workload-root" aria-live="polite">
        <?php if ($workloadRowsAll === []): ?>
            <p class="admin-workload__empty">No assessor accounts in the system.</p>
        <?php elseif ($workloadRows === []): ?>
            <p class="admin-workload__empty">No assessors match <strong><?= h($listQ) ?></strong>. <a class="text-link" href="<?= h(app_route('admin_workload.php')) ?>">Clear search</a></p>
        <?php else: ?>
            <div class="table-scroll">
                <table class="data-table data-table--workload">
                    <thead>
                        <tr>
                            <th scope="col">Assessor</th>
                            <th scope="col">Total students</th>
                            <th scope="col">Workload status</th>
                            <th scope="col" class="data-table--workload__th-action"><span class="visually-hidden">Action</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($workloadRows as $w):
                            $name = (string) ($w['assessor_name'] ?? '');
                            $uid = (int) ($w['assessor_id'] ?? 0);
                            $n = (int) ($w['students_assigned'] ?? 0);
                            $st = (string) ($w['workload_status'] ?? '');
                            $cap = WORKLOAD_CAPACITY_MAX;
                            $pct = $cap > 0 ? min(100.0, ($n / $cap) * 100.0) : 0.0;
                            $barClass = workload_bar_fill_class($n, $cap);
                            $pillClass = workload_status_pill_class($st);
                            $pillLabel = workload_status_pill_label($st);
                            $detailsHref = $hrefManageAssessorsBase . '&q=' . rawurlencode($name);
                            ?>
                        <tr>
                            <td>
                                <span class="workload-name"><?= h($name) ?></span>
                            </td>
                            <td class="workload-col-capacity">
                                <div
                                    class="workload-bar"
                                    role="progressbar"
                                    aria-valuemin="0"
                                    aria-valuemax="<?= h((string) $cap) ?>"
                                    aria-valuenow="<?= h((string) $n) ?>"
                                    aria-label="Students assigned: <?= h((string) $n) ?> of <?= h((string) $cap) ?>"
                                >
                                    <div class="workload-bar__meta">
                                        <span class="workload-bar__label"><?= h((string) $n) ?> / <?= h((string) $cap) ?></span>
                                    </div>
                                    <div class="workload-bar__track">
                                        <div
                                            class="workload-bar__fill <?= h($barClass) ?>"
                                            style="width: <?= h((string) round($pct, 1)) ?>%;"
                                        ></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="workload-pill <?= h($pillClass) ?>"><?= h($pillLabel) ?></span>
                            </td>
                            <td class="data-table--workload__action">
                                <a class="workload-ghost-link" href="<?= h($detailsHref) ?>">
                                    <span class="workload-ghost-link__text">View details</span>
                                    <svg class="workload-ghost-link__icon" width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                        <path fill="currentColor" d="M12.3 4 19 10.6v2.8L12.2 20l-1.3-1.3 4.1-4.2H5v-2h10l-3.5-3.4L12.2 4z"/>
                                    </svg>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php
require_once __DIR__ . '/../../../includes/footer.php';
