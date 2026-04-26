<?php
declare(strict_types=1);

/**
 * Admin — result grid: multi-filter (GET), 1=1 + prepared WHERE, whitelisted ORDER BY, sortable headers.
 * GET: search, status, company, programme, assessor, min_mark, max_mark, sort, dir (q = legacy alias for search)
 */

require_once __DIR__ . '/../../../includes/db_connect.php';
require_once __DIR__ . '/../../../includes/functions.php';

ensure_session();
require_role('Admin');

/**
 * Whitelist: maps ?sort= GET to internal keys id | name | mark (ORDER BY is built from fixed SQL only).
 * Public names: student_id, student_name, total_mark (coursework / REST style).
 */
function admin_results_get_sort_to_internal(): array
{
    return [
        'student_id' => 'id',
        'id' => 'id',
        'student_name' => 'name',
        'name' => 'name',
        'total_mark' => 'mark',
        'mark' => 'mark',
    ];
}

/**
 * @return 'student_id'|'student_name'|'total_mark'
 */
function admin_results_internal_to_sort_get(string $internal): string
{
    return match ($internal) {
        'name' => 'student_name',
        'mark' => 'total_mark',
        default => 'student_id',
    };
}

/**
 * @return array{search: string, status: string, company: int, programme: int, assessor: int, min_mark: ?float, max_mark: ?float, sort: string, dir: string}
 */
function admin_results_parse_state(): array
{
    $rawSearch = (string) ($_GET['search'] ?? $_GET['q'] ?? '');
    $search = trim($rawSearch);
    $rawSt = strtolower(trim((string) ($_GET['status'] ?? 'all')));
    $status = in_array($rawSt, ['ongoing', 'completed'], true) ? $rawSt : 'all';

    $company = max(0, (int) ($_GET['company'] ?? 0));
    $programme = max(0, (int) ($_GET['programme'] ?? 0));
    $assessor = max(0, (int) ($_GET['assessor_id'] ?? $_GET['assessor'] ?? 0));

    $minMark = null;
    if (isset($_GET['min_mark']) && (string) $_GET['min_mark'] !== '' && is_numeric($_GET['min_mark'])) {
        $m = (float) $_GET['min_mark'];
        if ($m >= 0.0 && $m <= 100.0) {
            $minMark = $m;
        }
    }
    $maxMark = null;
    if (isset($_GET['max_mark']) && (string) $_GET['max_mark'] !== '' && is_numeric($_GET['max_mark'])) {
        $m = (float) $_GET['max_mark'];
        if ($m >= 0.0 && $m <= 100.0) {
            $maxMark = $m;
        }
    }

    $rawSort = strtolower(trim((string) ($_GET['sort'] ?? 'student_id')));
    $map = admin_results_get_sort_to_internal();
    $sort = $map[$rawSort] ?? 'id';
    $rawDir = strtoupper((string) ($_GET['dir'] ?? 'asc'));
    $dir = $rawDir === 'DESC' ? 'desc' : 'asc';

    return [
        'search' => $search,
        'status' => $status,
        'company' => $company,
        'programme' => $programme,
        'assessor' => $assessor,
        'min_mark' => $minMark,
        'max_mark' => $maxMark,
        'sort' => $sort,
        'dir' => $dir,
    ];
}

/**
 * @param array{search: string, status: string, company: int, programme: int, assessor: int, min_mark: ?float, max_mark: ?float, sort: string, dir: string} $base
 * @param array<string, mixed> $overrides
 */
function admin_results_build_url(array $base, array $overrides = []): string
{
    $m = array_merge($base, $overrides);
    $q = [];
    if (($m['search'] ?? '') !== '') {
        $q['search'] = (string) $m['search'];
    }
    $st = strtolower((string) ($m['status'] ?? 'all'));
    if (in_array($st, ['ongoing', 'completed'], true)) {
        $q['status'] = $st;
    }
    if ((int) ($m['company'] ?? 0) > 0) {
        $q['company'] = (string) (int) $m['company'];
    }
    if ((int) ($m['programme'] ?? 0) > 0) {
        $q['programme'] = (string) (int) $m['programme'];
    }
    if ((int) ($m['assessor'] ?? 0) > 0) {
        $q['assessor_id'] = (string) (int) $m['assessor'];
    }
    if (array_key_exists('min_mark', $m) && $m['min_mark'] !== null) {
        $q['min_mark'] = (string) (float) $m['min_mark'];
    }
    if (array_key_exists('max_mark', $m) && $m['max_mark'] !== null) {
        $q['max_mark'] = (string) (float) $m['max_mark'];
    }
    $intSort = (string) ($m['sort'] ?? 'id');
    $intDir = (string) ($m['dir'] ?? 'asc');
    if ($intSort !== 'id' || $intDir !== 'asc') {
        $q['sort'] = admin_results_internal_to_sort_get($intSort);
        $q['dir'] = $intDir;
    }
    $qs = http_build_query($q);
    return app_route('admin_results.php') . ($qs !== '' ? '?' . $qs : '');
}

/**
 * @param array{search: string, status: string, company: int, programme: int, assessor: int, min_mark: ?float, max_mark: ?float, sort: string, dir: string} $s
 * @return array{search: string, status: string, company: int, programme: int, assessor: int, min_mark: ?float, max_mark: ?float, sort: string, dir: string}
 */
function admin_results_state_clear_advanced(array $s): array
{
    $s['company'] = 0;
    $s['programme'] = 0;
    $s['assessor'] = 0;
    $s['min_mark'] = null;
    $s['max_mark'] = null;
    return $s;
}

/**
 * @return array{search: string, status: string, company: int, programme: int, assessor: int, min_mark: ?float, max_mark: ?float, sort: string, dir: string}
 */
function admin_results_state_clear_all(): array
{
    return [
        'search' => '',
        'status' => 'all',
        'company' => 0,
        'programme' => 0,
        'assessor' => 0,
        'min_mark' => null,
        'max_mark' => null,
        'sort' => 'id',
        'dir' => 'asc',
    ];
}

/**
 * @param 'id'|'name'|'mark' $col
 * @param array{search: string, status: string, company: int, programme: int, assessor: int, min_mark: ?float, max_mark: ?float, sort: string, dir: string} $s
 */
function admin_results_sort_link_href(string $col, array $s): string
{
    if ($col === 'id') {
        $d = ($s['sort'] === 'id') ? ($s['dir'] === 'asc' ? 'desc' : 'asc') : 'asc';
        return admin_results_build_url($s, ['sort' => 'id', 'dir' => $d]);
    }
    if ($col === 'name') {
        $d = ($s['sort'] === 'name') ? ($s['dir'] === 'asc' ? 'desc' : 'asc') : 'asc';
        return admin_results_build_url($s, ['sort' => 'name', 'dir' => $d]);
    }
    if ($col === 'mark') {
        $d = ($s['sort'] === 'mark') ? ($s['dir'] === 'asc' ? 'desc' : 'asc') : 'desc';
        return admin_results_build_url($s, ['sort' => 'mark', 'dir' => $d]);
    }
    return admin_results_build_url($s);
}

$s = admin_results_parse_state();
$search = $s['search'];
$statusKey = $s['status'];
$companyId = $s['company'];
$progId = $s['programme'];
$assessorId = $s['assessor'];
$minMark = $s['min_mark'];
$maxMark = $s['max_mark'];
$sortKey = $s['sort'];
$dir = $s['dir'];

$rows = [];
$companyOptions = [];
$programmeOptions = [];
$assessorOptions = [];
$dbError = '';
$pageError = '';

$baseFrom = 'FROM `Internships` i
            INNER JOIN `Students` st ON st.student_id = i.student_id
            INNER JOIN `Users` u ON u.user_id = st.user_id
            INNER JOIN `Programmes` p ON p.prog_id = st.prog_id
            INNER JOIN `Schools` sch ON sch.school_id = p.school_id
            INNER JOIN `Companies` c ON c.company_id = i.company_id
            INNER JOIN `Users` ua ON ua.user_id = i.assessor_id
            LEFT JOIN `Assessments` a ON a.internship_id = i.internship_id';

try {
    $companyOptions = $pdo->query('SELECT company_id, company_name FROM `Companies` ORDER BY company_name ASC')
        ->fetchAll(PDO::FETCH_ASSOC);
    $programmeOptions = $pdo->query('SELECT prog_id, prog_name FROM `Programmes` ORDER BY prog_name ASC')
        ->fetchAll(PDO::FETCH_ASSOC);
    $assessorOptions = $pdo->query("SELECT user_id, full_name FROM `Users` WHERE role = 'Assessor' ORDER BY full_name ASC")
        ->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    app_log_exception('admin_results.options', $e);
    $dbError = app_public_error('db_read');
}

if ($minMark !== null && $maxMark !== null && $minMark > $maxMark) {
    $pageError = 'Min mark cannot be greater than max mark.';
}

if ($dbError === '' && $pageError === '') {
    /*
     * SQL pattern: WHERE 1=1 AND … ; only whitelisted ORDER BY fragments; user input only via ? placeholders.
     * PDO: $pdo->prepare($sql)->execute($params) with named parameters.
     * mysqli (equivalent): $stmt = $conn->prepare($sql); $stmt->bind_param('ssiiidd', ...) matching placeholder order.
     */
    $where = ['1=1'];
    $params = [];
    if ($search !== '') {
        $where[] = '(st.student_id LIKE :search1 OR u.full_name LIKE :search2)';
        $like = '%' . $search . '%';
        $params[':search1'] = $like;
        $params[':search2'] = $like;
    }
    if ($statusKey === 'ongoing') {
        $where[] = "i.status = 'Ongoing'";
    } elseif ($statusKey === 'completed') {
        $where[] = "i.status = 'Completed'";
    }
    if ($companyId > 0) {
        $where[] = 'c.company_id = :cid';
        $params[':cid'] = $companyId;
    }
    if ($progId > 0) {
        $where[] = 'st.prog_id = :pid';
        $params[':pid'] = $progId;
    }
    if ($assessorId > 0) {
        $where[] = 'i.assessor_id = :aid';
        $params[':aid'] = $assessorId;
    }
    if ($minMark !== null) {
        $where[] = 'a.total_mark IS NOT NULL AND a.total_mark >= :minm';
        $params[':minm'] = $minMark;
    }
    if ($maxMark !== null) {
        $where[] = 'a.total_mark IS NOT NULL AND a.total_mark <= :maxm';
        $params[':maxm'] = $maxMark;
    }

    $dirSql = $dir === 'desc' ? 'DESC' : 'ASC';
    $orderBy = match ($sortKey) {
        'name' => 'u.full_name ' . $dirSql . ', st.student_id ASC, i.internship_id ASC',
        'mark' => '(a.total_mark IS NULL) ASC, a.total_mark ' . $dirSql . ', st.student_id ASC, i.internship_id ASC',
        default => 'st.student_id ' . $dirSql . ', i.internship_id ASC',
    };

    $sql = 'SELECT st.student_id, u.full_name, p.prog_name, sch.school_name,
                   i.internship_id, i.status, c.company_name,
                   a.total_mark, ua.full_name AS assessor_name
            ' . $baseFrom . '
            WHERE ' . implode(' AND ', $where) . '
            ORDER BY ' . $orderBy;
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
    } catch (PDOException $e) {
        app_log_exception('admin_results.list', $e);
        $dbError = app_public_error('db_read');
    }
}

$sortHrefId = admin_results_sort_link_href('id', $s);
$sortHrefName = admin_results_sort_link_href('name', $s);
$sortHrefMark = admin_results_sort_link_href('mark', $s);
$sortGetParam = admin_results_internal_to_sort_get($sortKey);

$filtersActive =
    $search !== ''
    || $statusKey !== 'all'
    || $companyId > 0
    || $progId > 0
    || $assessorId > 0
    || $minMark !== null
    || $maxMark !== null
    || $sortKey !== 'id'
    || $dir !== 'asc';

$hrefClearAll = admin_results_build_url(admin_results_state_clear_all());
$hrefClearAdvanced = admin_results_build_url(admin_results_state_clear_advanced($s));
$pageTitle = 'Internship results (admin)';

$svgArrowUp = '<svg class="admin-results-sort__icon" viewBox="0 0 12 12" width="10" height="10" aria-hidden="true" focusable="false"><path fill="currentColor" d="M6 2 10 7H2L6 2z"/></svg>';
$svgArrowDown = '<svg class="admin-results-sort__icon" viewBox="0 0 12 12" width="10" height="10" aria-hidden="true" focusable="false"><path fill="currentColor" d="M6 10 2 5h8L6 10z"/></svg>';

require_once __DIR__ . '/../../../includes/header.php';
?>

<section class="card admin-results" id="admin-results">
    <p class="lead lead-reset admin-results__intro">Refine the grid by ID, name, status, and advanced scope. All filters are combined; sort state is kept in the URL.</p>

    <div class="admin-results-toolbar" role="region" aria-label="Filter results">
        <form
            class="admin-results-toolbar__search"
            method="get"
            action="<?= h(app_route('admin_results.php')) ?>"
            role="search"
        >
            <?php if ($statusKey !== 'all'): ?><input type="hidden" name="status" value="<?= h($statusKey) ?>"><?php endif; ?>
            <?php if ($companyId > 0): ?><input type="hidden" name="company" value="<?= h((string) $companyId) ?>"><?php endif; ?>
            <?php if ($progId > 0): ?><input type="hidden" name="programme" value="<?= h((string) $progId) ?>"><?php endif; ?>
            <?php if ($assessorId > 0): ?><input type="hidden" name="assessor_id" value="<?= h((string) $assessorId) ?>"><?php endif; ?>
            <?php if ($minMark !== null): ?><input type="hidden" name="min_mark" value="<?= h((string) $minMark) ?>"><?php endif; ?>
            <?php if ($maxMark !== null): ?><input type="hidden" name="max_mark" value="<?= h((string) $maxMark) ?>"><?php endif; ?>
            <?php if ($sortKey !== 'id' || $dir !== 'asc'): ?>
            <input type="hidden" name="sort" value="<?= h($sortGetParam) ?>">
            <input type="hidden" name="dir" value="<?= h($dir) ?>">
            <?php endif; ?>
            <label class="visually-hidden" for="admin-results-q">Search by student ID or legal name</label>
            <div class="admin-results-search">
                <svg class="admin-results-search__icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                    <circle cx="11" cy="11" r="8" />
                    <path d="m21 21-4.3-4.3" />
                </svg>
                <input
                    class="admin-results-search__input"
                    id="admin-results-q"
                    name="search"
                    type="search"
                    maxlength="120"
                    placeholder="Search by ID or name…"
                    value="<?= h($search) ?>"
                    autocomplete="off"
                    inputmode="search"
                >
            </div>
        </form>
        <button
            type="button"
            class="filter-panel__toggle"
            id="filter-panel-toggle"
            aria-expanded="false"
            aria-controls="filter-panel"
        >
            <span class="filter-panel__toggle-icon" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="4" y1="6" x2="20" y2="6" />
                    <line x1="4" y1="12" x2="16" y2="12" />
                    <line x1="4" y1="18" x2="12" y2="18" />
                </svg>
            </span>
            Advanced Filters
        </button>
    </div>

    <div
        class="filter-panel"
        id="filter-panel"
        role="region"
        aria-label="Advanced filters"
        aria-hidden="true"
        inert
    >
        <form class="filter-panel__form" method="get" action="<?= h(app_route('admin_results.php')) ?>" id="filter-panel-form">
            <p class="filter-panel__lead">Choose status, scope, and marks, then <strong>Apply filters</strong>. The search field above respects the last applied filter set when you press Enter.</p>
            <input type="hidden" name="search" value="<?= h($search) ?>">
            <input type="hidden" name="sort" value="<?= h($sortGetParam) ?>">
            <input type="hidden" name="dir" value="<?= h($dir) ?>">

            <fieldset class="filter-panel__fieldset">
                <legend class="filter-panel__legend">Placement status</legend>
                <div class="filter-panel__pill-row" role="radiogroup" aria-label="Placement status">
                    <div class="filter-panel__pill">
                        <input
                            class="visually-hidden"
                            type="radio"
                            name="status"
                            value="all"
                            id="f-st-all"
                            <?= $statusKey === 'all' ? ' checked' : '' ?>
                        >
                        <label class="filter-panel__pill-label" for="f-st-all">All</label>
                    </div>
                    <div class="filter-panel__pill">
                        <input
                            class="visually-hidden"
                            type="radio"
                            name="status"
                            value="ongoing"
                            id="f-st-ongoing"
                            <?= $statusKey === 'ongoing' ? ' checked' : '' ?>
                        >
                        <label class="filter-panel__pill-label" for="f-st-ongoing">Ongoing</label>
                    </div>
                    <div class="filter-panel__pill">
                        <input
                            class="visually-hidden"
                            type="radio"
                            name="status"
                            value="completed"
                            id="f-st-completed"
                            <?= $statusKey === 'completed' ? ' checked' : '' ?>
                        >
                        <label class="filter-panel__pill-label" for="f-st-completed">Completed</label>
                    </div>
                </div>
            </fieldset>

            <div class="filter-panel__row filter-panel__row--three">
                <div class="filter-panel__field">
                    <label class="filter-panel__label" for="f-company">Company</label>
                    <select class="filter-panel__select" id="f-company" name="company">
                        <option value="">All</option>
                        <?php foreach ($companyOptions as $row): ?>
                            <option value="<?= h((string) $row['company_id']) ?>"<?= (int) $row['company_id'] === $companyId ? ' selected' : '' ?>><?= h((string) $row['company_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-panel__field">
                    <label class="filter-panel__label" for="f-prog">Programme</label>
                    <select class="filter-panel__select" id="f-prog" name="programme">
                        <option value="">All</option>
                        <?php foreach ($programmeOptions as $row): ?>
                            <option value="<?= h((string) $row['prog_id']) ?>"<?= (int) $row['prog_id'] === $progId ? ' selected' : '' ?>><?= h((string) $row['prog_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-panel__field">
                    <label class="filter-panel__label" for="f-ass">Assessor</label>
                    <select class="filter-panel__select" id="f-ass" name="assessor_id">
                        <option value="">All</option>
                        <?php foreach ($assessorOptions as $row): ?>
                            <option value="<?= h((string) $row['user_id']) ?>"<?= (int) $row['user_id'] === $assessorId ? ' selected' : '' ?>><?= h((string) $row['full_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="filter-panel__row filter-panel__row--marks">
                <div class="filter-panel__field">
                    <label class="filter-panel__label" for="f-min">Min mark</label>
                    <input
                        class="filter-panel__input"
                        id="f-min"
                        name="min_mark"
                        type="number"
                        min="0"
                        max="100"
                        step="0.01"
                        placeholder="0–100"
                        value="<?= $minMark !== null ? h((string) $minMark) : '' ?>"
                    >
                </div>
                <div class="filter-panel__field">
                    <label class="filter-panel__label" for="f-max">Max mark</label>
                    <input
                        class="filter-panel__input"
                        id="f-max"
                        name="max_mark"
                        type="number"
                        min="0"
                        max="100"
                        step="0.01"
                        placeholder="0–100"
                        value="<?= $maxMark !== null ? h((string) $maxMark) : '' ?>"
                    >
                </div>
            </div>

            <div class="filter-panel__actions">
                <p class="filter-panel__hint">Changes apply to the list only after you submit.</p>
                <div class="filter-panel__actions-btns">
                    <a class="filter-panel__clear" href="<?= h($hrefClearAdvanced) ?>">Clear all</a>
                    <button class="btn-nottingham" type="submit">Apply filters</button>
                </div>
            </div>
        </form>
    </div>

    <?php if ($dbError !== ''): ?>
        <div class="alert-error" role="alert"><?= h($dbError) ?></div>
    <?php elseif ($pageError !== ''): ?>
        <div class="alert-error" role="alert"><?= h($pageError) ?></div>
    <?php elseif ($rows === []): ?>
        <div class="admin-results-empty" id="admin-results-empty" role="status" aria-live="polite">
            <div class="admin-results-empty__icon" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" width="64" height="64" fill="none" stroke="var(--color-border-card)" stroke-width="1.25">
                    <rect x="12" y="20" width="40" height="32" rx="3" />
                    <path d="M20 20V16a4 4 0 0 1 4-4h16a4 4 0 0 1 4 4v4" />
                    <line x1="20" y1="32" x2="44" y2="32" />
                    <line x1="20" y1="40" x2="36" y2="40" />
                </svg>
            </div>
            <h2 class="admin-results-empty__title">No placements match this view</h2>
            <p class="admin-results-empty__text">Try another combination of filters, or clear constraints to return to the full list.</p>
            <?php if ($filtersActive): ?>
                <p class="admin-results-empty__action">
                    <a class="admin-results-empty__link" href="<?= h($hrefClearAll) ?>">Clear all filters</a>
                </p>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="table-scroll">
            <table class="data-table data-table--saas data-table--results" id="admin-results-table">
                <thead>
                    <tr>
                        <th class="data-table__sortable<?= $sortKey === 'id' ? ' data-table__th--sorted' : '' ?>" scope="col"<?= $sortKey === 'id' ? ' aria-sort="' . ($dir === 'asc' ? 'ascending' : 'descending') . '"' : '' ?>>
                            <a class="data-table__sortable-head<?= $sortKey === 'id' ? ' data-table__sortable-head--active' : '' ?>" href="<?= h($sortHrefId) ?>">
                                <span>Student ID</span>
                                <?php if ($sortKey === 'id'): ?>
                                    <span class="admin-results-sort__glyph" aria-hidden="true"><?= $dir === 'asc' ? $svgArrowUp : $svgArrowDown ?></span>
                                <?php else: ?>
                                    <span class="data-table__sort-static" aria-hidden="true"><span class="data-table__sort-static__up">↑</span><span class="data-table__sort-static__down">↓</span></span>
                                <?php endif; ?>
                            </a>
                        </th>
                        <th class="data-table__sortable<?= $sortKey === 'name' ? ' data-table__th--sorted' : '' ?>" scope="col"<?= $sortKey === 'name' ? ' aria-sort="' . ($dir === 'asc' ? 'ascending' : 'descending') . '"' : '' ?>>
                            <a class="data-table__sortable-head<?= $sortKey === 'name' ? ' data-table__sortable-head--active' : '' ?>" href="<?= h($sortHrefName) ?>">
                                <span>Name</span>
                                <?php if ($sortKey === 'name'): ?>
                                    <span class="admin-results-sort__glyph" aria-hidden="true"><?= $dir === 'asc' ? $svgArrowUp : $svgArrowDown ?></span>
                                <?php else: ?>
                                    <span class="data-table__sort-static" aria-hidden="true"><span class="data-table__sort-static__up">↑</span><span class="data-table__sort-static__down">↓</span></span>
                                <?php endif; ?>
                            </a>
                        </th>
                        <th scope="col">Programme</th>
                        <th scope="col">School</th>
                        <th scope="col">Company</th>
                        <th scope="col">Status</th>
                        <th scope="col">Assessor</th>
                        <th
                            class="data-table__sortable admin-results__col-total<?= $sortKey === 'mark' ? ' data-table__th--sorted' : '' ?>"
                            scope="col"
                            <?= $sortKey === 'mark' ? ' aria-sort="' . ($dir === 'asc' ? 'ascending' : 'descending') . '"' : '' ?>
                        >
                            <a class="data-table__sortable-head<?= $sortKey === 'mark' ? ' data-table__sortable-head--active' : '' ?>" href="<?= h($sortHrefMark) ?>">
                                <span>Total mark</span>
                                <?php if ($sortKey === 'mark'): ?>
                                    <span class="admin-results-sort__glyph" aria-hidden="true"><?= $dir === 'asc' ? $svgArrowUp : $svgArrowDown ?></span>
                                <?php else: ?>
                                    <span class="data-table__sort-static" aria-hidden="true"><span class="data-table__sort-static__up">↑</span><span class="data-table__sort-static__down">↓</span></span>
                                <?php endif; ?>
                            </a>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $r): ?>
                        <?php
                        $statusRaw = (string) $r['status'];
                        $statusClass = 'status-pill';
                        if (strcasecmp($statusRaw, 'Ongoing') === 0) {
                            $statusClass .= ' status-pill--ongoing';
                        } elseif (strcasecmp($statusRaw, 'Completed') === 0) {
                            $statusClass .= ' status-pill--completed';
                        } else {
                            $statusClass .= ' status-pill--neutral';
                        }
                        $totalVal = null;
                        if ($r['total_mark'] !== null && (string) $r['total_mark'] !== '') {
                            $totalVal = (float) $r['total_mark'];
                        }
                        ?>
                        <tr>
                            <td><?= h((string) $r['student_id']) ?></td>
                            <td><?= h((string) $r['full_name']) ?></td>
                            <td><?= h((string) $r['prog_name']) ?></td>
                            <td><?= h((string) $r['school_name']) ?></td>
                            <td><?= h((string) $r['company_name']) ?></td>
                            <td>
                                <span class="<?= h($statusClass) ?>">
                                    <?php if (strcasecmp($statusRaw, 'Ongoing') === 0): ?>
                                        <span class="status-pill__breath" aria-hidden="true"></span>
                                    <?php endif; ?>
                                    <?= h($statusRaw) ?>
                                </span>
                            </td>
                            <td><?= h((string) $r['assessor_name']) ?></td>
                            <td class="admin-results__col-total">
                                <?php if ($totalVal !== null): ?>
                                    <?php
                                    $isDist = $totalVal > 70;
                                    $markClass = 'mark-total' . ($isDist ? ' distinction-mark' : '');
                                    ?>
                                    <span class="<?= h($markClass) ?>"><?= h(number_format($totalVal, 2, '.', '')) ?></span>
                                <?php else: ?>
                                    <span class="text-muted">Not recorded</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<script src="<?= h(asset_url('assets/js/modules/admin-results.js')) ?>"></script>
<?php
require_once __DIR__ . '/../../../includes/footer.php';
