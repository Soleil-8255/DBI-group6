<?php
declare(strict_types=1);

/**
 * Student personal dashboard: hero + tabs (Overview / Scores / Feedback) + JSON for radar.
 */

require_once __DIR__ . '/../../../includes/db_connect.php';
require_once __DIR__ . '/../../../includes/functions.php';

ensure_session();

if (($_SESSION['role'] ?? '') !== 'Student') {
    header('Location: ' . app_route('index.php'));
    exit;
}

$userId = current_user_id();
if ($userId === null) {
    header('Location: ' . app_route('index.php'));
    exit;
}

$profile = null;
$placements = [];
$dbError = '';

function student_dashboard_format_mark($value): string
{
    if ($value === null || $value === '') {
        return '—';
    }

    return number_format((float) $value, 2, '.', '');
}

function student_dashboard_grade_from_total(float $t): string
{
    if ($t < 40.0) {
        return 'Fail';
    }
    if ($t < 60.0) {
        return 'Pass';
    }
    if ($t < 70.0) {
        return 'Merit';
    }

    return 'Distinction';
}

function student_hero_name_initial(string $displayName): string
{
    $t = trim($displayName);
    if ($t === '') {
        return '?';
    }
    if (function_exists('mb_substr') && function_exists('mb_strtoupper')) {
        return mb_strtoupper(mb_substr($t, 0, 1, 'UTF-8'), 'UTF-8');
    }

    return strtoupper($t[0] ?? '?');
}

function student_hero_badge_class(string $grade): string
{
    if ($grade === 'Distinction') {
        return 'student-grade-badge--distinction';
    }
    if ($grade === 'Merit') {
        return 'student-grade-badge--merit';
    }
    if ($grade === 'Pass') {
        return 'student-grade-badge--pass';
    }
    if ($grade === 'Fail') {
        return 'student-grade-badge--fail';
    }

    return 'student-grade-badge--neutral';
}

/**
 * Internship focus row: company, assessor, period (+ compact secondary). Mirrored in student-dashboard.js.
 */
function student_hero_meta_grid_html(array $row, ?string $cohortYear): string
{
    $id = (int) ($row['internship_id'] ?? 0);
    $co = h((string) ($row['company_name'] ?? ''));
    $as = h((string) ($row['assessor_name'] ?? ''));
    $pc = h(
        internship_cohort_period_caption(
            $cohortYear,
            isset($row['start_date']) ? (string) $row['start_date'] : null,
            isset($row['end_date']) ? (string) $row['end_date'] : null
        )
    );
    $st = h((string) ($row['company_state'] ?? ''));
    $status = h((string) ($row['status'] ?? ''));
    $ref = h('INT-' . (string) $id);

    return
        '<ul class="meta-grid" role="list">' .
        '<li class="meta-grid__item"><div class="meta-grid__text"><span class="meta-grid__label">Company</span><span class="meta-grid__value">' . $co . '</span></div></li>' .
        '<li class="meta-grid__item"><div class="meta-grid__text"><span class="meta-grid__label">Assessor</span><span class="meta-grid__value">' . $as . '</span></div></li>' .
        '<li class="meta-grid__item"><div class="meta-grid__text"><span class="meta-grid__label">Period</span><span class="meta-grid__value">' . $pc . '</span></div></li>' .
        '</ul>' .
        '<p class="meta-sub"><span class="meta-sub__ref">' . $ref . '</span> · ' .
        '<span class="meta-sub__status">' . $status . '</span> · ' .
        '<span class="meta-sub__state">' . $st . '</span></p>';
}

function student_hero_outcome_block(?array $p): string
{
    if ($p === null) {
        return
            '<div class="hero-outcome hero-outcome--pending" role="status">' .
            '<p class="hero-outcome__pending-title">No placement on record</p>' .
            '<p class="hero-outcome__pending-hint">Add an internship in the registry to see outcomes here.</p></div>';
    }
    $tm = $p['total_mark'] ?? null;
    $grade = (string) ($p['grade'] ?? '');
    if ($tm === null) {
        return
            '<div class="hero-outcome hero-outcome--pending" role="status">' .
            '<p class="hero-outcome__pending-title">Under evaluation</p>' .
            '<p class="hero-outcome__pending-hint">Your total and classification will show here once published.</p></div>';
    }
    $fmt = h(number_format((float) $tm, 2, '.', ''));
    $g = $grade !== '' ? $grade : student_dashboard_grade_from_total((float) $tm);
    $bcls = h(student_hero_badge_class($g));

    return
        '<div class="hero-outcome hero-outcome--scored">' .
        '<p class="hero-outcome__label">Total score</p>' .
        '<p class="hero-outcome__value" aria-label="Total score out of 100"><span class="hero-outcome__value-num">' . $fmt . '</span><span class="hero-outcome__value-suffix" aria-hidden="true">/100</span></p>' .
        '<p class="student-grade-badge ' . $bcls . '">' . h($g) . '</p></div>';
}

$scoreRows = [
    ['label' => 'Practical tasks', 'key' => 'score_tasks', 'weight' => '10%'],
    ['label' => 'Health & safety', 'key' => 'score_safety', 'weight' => '10%'],
    ['label' => 'Theory application', 'key' => 'score_theory', 'weight' => '10%'],
    ['label' => 'Written report', 'key' => 'score_report', 'weight' => '15%'],
    ['label' => 'Language & communication', 'key' => 'score_language', 'weight' => '10%'],
    ['label' => 'Lifelong learning', 'key' => 'score_lifelong', 'weight' => '15%'],
    ['label' => 'Project management', 'key' => 'score_proj_mgmt', 'weight' => '15%'],
    ['label' => 'Time management', 'key' => 'score_time_mgmt', 'weight' => '15%'],
];

try {
    $profileStmt = $pdo->prepare(
        'SELECT st.student_id, st.cohort_year, p.prog_name, sch.school_name
         FROM `Students` st
         INNER JOIN `Programmes` p ON p.prog_id = st.prog_id
         INNER JOIN `Schools` sch ON sch.school_id = p.school_id
         WHERE st.user_id = :uid
         LIMIT 1'
    );
    $profileStmt->execute([':uid' => $userId]);
    $profile = $profileStmt->fetch() ?: null;

    if ($profile !== null) {
        $placeStmt = $pdo->prepare(
            'SELECT i.internship_id, i.status, i.start_date, i.end_date,
                    c.company_name, st.state_name AS company_state,
                    ua.full_name AS assessor_name,
                    a.assessment_id, a.score_tasks, a.score_safety, a.score_theory,
                    a.score_report, a.score_language, a.score_lifelong,
                    a.score_proj_mgmt, a.score_time_mgmt, a.total_mark, a.comments
             FROM `Internships` i
             INNER JOIN `Students` s ON s.student_id = i.student_id
             INNER JOIN `Companies` c ON c.company_id = i.company_id
             INNER JOIN `States` st ON st.state_id = c.state_id
             INNER JOIN `Users` ua ON ua.user_id = i.assessor_id
             LEFT JOIN `Assessments` a ON a.internship_id = i.internship_id
             WHERE s.user_id = :uid
             ORDER BY i.start_date DESC, i.internship_id DESC'
        );
        $placeStmt->execute([':uid' => $userId]);
        $placements = $placeStmt->fetchAll();
    }
} catch (PDOException $e) {
    app_log_exception('student_dashboard.query', $e);
    $dbError = app_public_error('db_read');
}

$placementsForJson = [];
$cohortYear = $profile === null ? null : (string) $profile['cohort_year'];
foreach ($placements as $row) {
    $hasAssessment = $row['assessment_id'] !== null && (string) $row['assessment_id'] !== '';
    $status = (string) $row['status'];
    $canRadar = (strcasecmp($status, 'Completed') === 0) && $hasAssessment;

    $periodCaption = internship_cohort_period_caption(
        $cohortYear,
        isset($row['start_date']) ? (string) $row['start_date'] : null,
        isset($row['end_date']) ? (string) $row['end_date'] : null
    );

    $scores = [];
    $detail = [];
    if ($canRadar) {
        foreach ($scoreRows as $sr) {
            $raw = $row[$sr['key']] ?? null;
            $f = (float) ($raw ?? 0);
            $scores[] = $f;
            $detail[] = [
                'label' => $sr['label'],
                'weight' => $sr['weight'],
                'mark' => $raw === null || $raw === '' ? null : (float) $raw,
            ];
        }
    } else {
        $scores = null;
    }

    $totalMark = $row['total_mark'] ?? null;
    $grade = '';
    if ($canRadar && $totalMark !== null && $totalMark !== '') {
        $grade = student_dashboard_grade_from_total((float) $totalMark);
    }

    $placementsForJson[] = [
        'internship_id' => (int) $row['internship_id'],
        'company_name' => (string) $row['company_name'],
        'status' => (string) $row['status'],
        'state' => (string) $row['company_state'],
        'assessor_name' => (string) $row['assessor_name'],
        'can_radar' => $canRadar,
        'period_caption' => $periodCaption,
        'scores' => $scores,
        'scores_detail' => $canRadar ? $detail : null,
        'total_mark' => $hasAssessment && $totalMark !== null && $totalMark !== '' ? (float) $totalMark : null,
        'comments' => $hasAssessment && isset($row['comments']) ? (string) $row['comments'] : null,
        'grade' => $grade,
    ];
}

$placementsJson = json_encode($placementsForJson, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if ($placementsJson === false) {
    $placementsJson = '[]';
}

$bodyClassExtra = ' page-student-dashboard';
$pageTitle = 'My Internship Results';
$pageSubtitle = 'View your academic outcomes and assessor feedback.';
$displayName = (string) ($_SESSION['full_name'] ?? '');

require_once __DIR__ . '/../../../includes/header.php';
?>

<?php if ($dbError !== ''): ?>
    <div class="student-dashboard-error" role="alert">
        <div class="alert-error"><?= h($dbError) ?></div>
    </div>
<?php elseif ($profile === null): ?>
    <section class="card app-card-pro">
        <h2 class="visually-hidden">No profile</h2>
        <p class="lead">No student profile is linked to this account. Please contact the registry office.</p>
    </section>
<?php else: ?>
    <div class="student-dashboard-page" id="student-dashboard-root" data-student-placements="<?= h($placementsJson) ?>">
        <?php
        $nameInitial = student_hero_name_initial($displayName);
        $idline = (string) $profile['student_id'] . ' | ' . (string) $profile['prog_name'];
        $contextLine = (string) $profile['school_name'] . ' — Cohort ' . (string) $profile['cohort_year'];
        $firstPData = $placementsForJson[0] ?? null;
        ?>
        <section class="hero-info-card" aria-label="Your profile and placement summary">
            <div class="hero-info-card__row">
                <div class="hero-avatar" aria-hidden="true">
                    <span class="hero-avatar__letter" id="student-hero-initial"><?= h($nameInitial) ?></span>
                </div>
                <div class="hero-info-card__body">
                    <h2 class="hero-info-card__title"><?= h($displayName) ?></h2>
                    <p class="hero-info-card__idline"><?= h($idline) ?></p>
                    <p class="hero-info-card__context"><?= h($contextLine) ?></p>
                    <div
                        class="hero-info-card__internship"
                        id="student-hero-internship"
                        <?php if ($placements === []): ?>
                            hidden
                        <?php endif; ?>
                    >
                        <?php if ($placements !== []): ?>
                            <?= student_hero_meta_grid_html($placements[0], $cohortYear) ?>
                        <?php endif; ?>
                    </div>
                    <p
                        class="hero-info-card__internship-empty"
                        id="student-hero-internship-empty"
                        <?php if ($placements !== []): ?>
                            hidden
                        <?php endif; ?>
                    >No internship placement on file yet. Your school and cohort are shown above.</p>
                </div>
                <div class="hero-info-card__outcome-wrap" id="student-hero-outcome-root">
                    <?php
                    if ($placements === []) {
                        echo student_hero_outcome_block(null);
                    } else {
                        echo student_hero_outcome_block($firstPData);
                    }
                    ?>
                </div>
            </div>
            <?php if (count($placements) > 1): ?>
            <div class="hero-info-card__toolbar">
                <label class="form-label" for="student-placement-select">Viewing placement</label>
                <select id="student-placement-select" class="form-input hero-info-card__select">
                    <?php foreach ($placements as $i => $pr):
                        $cap = internship_cohort_period_caption(
                            $cohortYear,
                            isset($pr['start_date']) ? (string) $pr['start_date'] : null,
                            isset($pr['end_date']) ? (string) $pr['end_date'] : null
                        );
                    ?>
                    <option value="<?= h((string) (int) $pr['internship_id']) ?>"><?= h((string) $pr['company_name']) ?> — <?= h($cap) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
        </section>

    <?php if ($placements === []): ?>
        <section class="card app-card-pro">
            <p class="lead" style="margin: 0">You do not have any internship placements on record yet.</p>
        </section>
    <?php else: ?>
        <div>
            <div class="student-tabs" role="tablist" aria-label="Result sections">
                <button type="button" class="tab-btn" role="tab" data-tab="overview" aria-selected="true" id="tab-overview">Overview</button>
                <button type="button" class="tab-btn" role="tab" data-tab="details" aria-selected="false" id="tab-details">Score details</button>
                <button type="button" class="tab-btn" role="tab" data-tab="feedback" aria-selected="false" id="tab-feedback">Assessor feedback</button>
            </div>
            <div class="student-tab-panels">
                <div
                    class="tab-pane"
                    id="tab-panel-overview"
                    data-tab-panel="overview"
                    role="tabpanel"
                    aria-labelledby="tab-overview"
                >
                    <p class="student-dashboard-hint" id="radar-no-data-hint" hidden>There is no completed assessment to chart for this placement yet. Open <strong>Score details</strong> for the latest status, or return when the placement is completed and marked.</p>
                    <div class="radar-reveal-block">
                        <button type="button" id="radar-reveal-btn">Reveal Performance Radar</button>
                    </div>
                    <div id="student-radar-canvas-wrap" class="table-scroll radar-canvas-wrap" style="max-width: 100%" hidden>
                        <canvas id="student-radar-canvas" width="560" height="560" aria-label="Performance radar chart">Canvas not supported</canvas>
                    </div>
                    <div class="radar-secondary-actions" id="radar-secondary-actions">
                        <button type="button" id="radar-export-btn" hidden>Export radar PNG</button>
                    </div>
                </div>
                <div
                    class="tab-pane"
                    id="tab-panel-details"
                    data-tab-panel="details"
                    role="tabpanel"
                    aria-labelledby="tab-details"
                    hidden
                >
                    <div class="table-scroll">
                        <table class="student-academic-table" aria-label="Component scores">
                            <thead>
                                <tr>
                                    <th scope="col">Criterion</th>
                                    <th scope="col">Weighting</th>
                                    <th scope="col" class="num">Mark (0–100)</th>
                                </tr>
                            </thead>
                            <tbody id="student-detail-tbody">
                                <tr><td colspan="3" class="student-dashboard-hint">Select a placement from the summary card to load details.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div
                    class="tab-pane"
                    id="tab-panel-feedback"
                    data-tab-panel="feedback"
                    role="tabpanel"
                    aria-labelledby="tab-feedback"
                    hidden
                >
                    <div id="student-feedback-block">
                        <p class="student-dashboard-hint">Select a placement to view assessor feedback and final classification.</p>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
    </div>
<?php endif; ?>

<?php
require_once __DIR__ . '/../../../includes/footer.php';
