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

$internshipId = 0;
if (isset($_GET['internship_id']) && trim((string) $_GET['internship_id']) !== '') {
    $internshipId = (int) $_GET['internship_id'];
} elseif (isset($_GET['id']) && trim((string) $_GET['id']) !== '') {
    $internshipId = (int) $_GET['id'];
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && $internshipId <= 0) {
    $pageTitle = 'Evaluate Internship';
    require_once __DIR__ . '/../../../includes/header.php';
    $dashboardUrl = h(app_route('assessor_dashboard.php'));
    echo '<div class="app-dashboard-shell empty-state-outer" role="status" aria-live="polite">'
        . '<div class="empty-state-wrapper">'
        . '<span class="empty-state-icon" aria-hidden="true">'
        . '<svg width="88" height="88" viewBox="0 0 88 88" fill="none" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="">'
        . '<rect x="16" y="10" width="48" height="64" rx="4" stroke="currentColor" stroke-width="2.25"/>'
        . '<path d="M28 10V6a4 4 0 0 1 4-4h20a4 4 0 0 1 4 4v4" stroke="currentColor" stroke-width="2.25" stroke-linecap="round"/>'
        . '<line x1="30" y1="32" x2="58" y2="32" stroke="currentColor" stroke-width="2" stroke-linecap="round" opacity="0.55"/>'
        . '<line x1="30" y1="44" x2="52" y2="44" stroke="currentColor" stroke-width="2" stroke-linecap="round" opacity="0.45"/>'
        . '<line x1="30" y1="56" x2="46" y2="56" stroke="currentColor" stroke-width="2" stroke-linecap="round" opacity="0.4"/>'
        . '<circle cx="58" cy="60" r="10" fill="var(--color-bg-base)" stroke="currentColor" stroke-width="1.75"/>'
        . '<text x="58" y="64" text-anchor="middle" font-size="14" font-weight="600" fill="currentColor" font-family="Inter, system-ui, sans-serif">?</text>'
        . '</svg></span>'
        . '<h2 class="empty-state-title">No Internship Selected</h2>'
        . '<p class="empty-state-text">Please navigate to your dashboard and select a specific student\'s internship record to begin the evaluation.</p>'
        . '<a href="' . $dashboardUrl . '" class="btn btn-primary">← Return to Dashboard</a>'
        . '</div></div>';
    require_once __DIR__ . '/../../../includes/footer.php';
    exit;
}

function assessor_parse_score_component(string $raw): ?float
{
    $raw = trim($raw);
    if ($raw === '') {
        return null;
    }

    $value = filter_var($raw, FILTER_VALIDATE_FLOAT);
    if ($value === false) {
        return null;
    }

    $n = (int) round($value);
    if ($n < 0 || $n > 100) {
        return null;
    }

    return (float) $n;
}

/**
 * @return array{weight_fraction: float, max_weighted_points: float, weight_label: string, label: string}
 */
function assessor_rubric_enrich(string $label, string $weightLabel): array
{
    $pct = (float) preg_replace('/[^0-9.]/', '', $weightLabel);
    if ($pct <= 0) {
        $pct = 0.0;
    }
    $fraction = $pct / 100.0;

    return [
        'label' => $label,
        'weight_label' => $weightLabel,
        'weight_fraction' => $fraction,
        'max_weighted_points' => $pct,
    ];
}

/**
 * Interactive native SVG: donut (annulus) sectors + center labels (updated by JS on hover).
     *
     * @param list<float|int> $weightPcts
     * @param list<string>    $shortLabels
     * @param list<string>    $colors      Hex with or without #
     */
    function assessor_weight_hero_svg(array $weightPcts, array $shortLabels, array $colors): string
    {
        $n = \count($weightPcts);
        if ($n === 0) {
            return '';
        }
        $sumW = 0.0;
        foreach ($weightPcts as $w) {
            $sumW += (float) $w;
        }
        if ($sumW <= 0) {
            $sumW = 100.0;
        }
        $a0 = -M_PI / 2.0;
        $ro = 1.0;
        $ri = 0.46;
        $out = '<svg class="eval-hero-pie eval-hero-donut eval-pie--interactive" viewBox="-1.05 -1.05 2.1 2.1" width="240" height="240" role="img" aria-label="Rubric weighting: hover sectors for details">';
        $out .= '<title>Rubric weight distribution (total 100%)</title>';
        $out .= '<g class="eval-pie-slices">';
        for ($i = 0; $i < $n; $i++) {
            $w = (float) $weightPcts[$i];
            $t = $i < \count($shortLabels) ? $shortLabels[$i] : (string) $i;
            $c = $i < \count($colors) ? $colors[$i] : '64748B';
            $c = ltrim($c, '#');
            $pctInt = (int) round($w);
            $frac = $w / $sumW;
            $a1 = $a0 + $frac * 2.0 * M_PI;
            $dAng = $a1 - $a0;
            $large = (abs($dAng) > M_PI) ? 1 : 0;
            $d = sprintf(
                'M %F %F A %F %F 0 %d 1 %F %F L %F %F A %F %F 0 %d 0 %F %F Z',
                $ro * cos($a0),
                $ro * sin($a0),
                $ro,
                $ro,
                $large,
                $ro * cos($a1),
                $ro * sin($a1),
                $ri * cos($a1),
                $ri * sin($a1),
                $ri,
                $ri,
                $large,
                $ri * cos($a0),
                $ri * sin($a0)
            );
            $delay = round(0.05 * $i, 2);
            $out .= '<g class="eval-pie-sector" data-label="' . h($t) . '" data-pct="' . h((string) $pctInt) . '" style="animation-delay: ' . h((string) $delay) . 's">'
                . '<title>' . h($t . ' ' . (string) $pctInt . '%') . '</title>'
                . '<path d="' . h($d) . '" fill="#' . h($c) . '" class="eval-pie-sector__path" />'
                . '</g>';
            $a0 = $a1;
        }
        $out .= '</g>';
        $out .= '<g class="eval-pie-center" text-anchor="middle" font-family="system-ui, var(--font-body, sans-serif)">'
            . '<text id="eval-pie-center-l1" x="0" y="-0.05" font-size="0.19" fill="#122A54" font-weight="700">Weights</text>'
            . '<text id="eval-pie-center-l2" x="0" y="0.12" font-size="0.14" fill="#64748B" font-weight="600">100%</text>'
            . '</g>';
        $out .= '</svg>';

        return $out;
    }

$scoreFieldKeys = [
    'score_tasks',
    'score_safety',
    'score_theory',
    'score_report',
    'score_language',
    'score_lifelong',
    'score_proj_mgmt',
    'score_time_mgmt',
];

$rubricFields = [
    'score_tasks' => assessor_rubric_enrich('Tasks', '10%'),
    'score_safety' => assessor_rubric_enrich('Health', '10%'),
    'score_theory' => assessor_rubric_enrich('Theory', '10%'),
    'score_report' => assessor_rubric_enrich('Report', '15%'),
    'score_language' => assessor_rubric_enrich('Language', '10%'),
    'score_lifelong' => assessor_rubric_enrich('Learning', '15%'),
    'score_proj_mgmt' => assessor_rubric_enrich('Project', '15%'),
    'score_time_mgmt' => assessor_rubric_enrich('Time', '15%'),
];

$evalCardSections = [
    [
        'id' => 'eval-card-project',
        'title' => 'Project & Time Management',
        'keys' => ['score_tasks', 'score_proj_mgmt', 'score_time_mgmt'],
    ],
    [
        'id' => 'eval-card-technical',
        'title' => 'Technical & Safety',
        'keys' => ['score_safety', 'score_theory', 'score_language'],
    ],
    [
        'id' => 'eval-card-report',
        'title' => 'Report & Learning',
        'keys' => ['score_report', 'score_lifelong'],
    ],
];

$assessorWeightHeroPcts = [10, 10, 10, 15, 10, 15, 15, 15];
$assessorWeightHeroLabels = ['Tasks', 'Health', 'Theory', 'Report', 'Language', 'Learning', 'Project', 'Time'];
/* Executive report palette: navy / blues / grey + champagne gold accent */
$assessorWeightHeroColors = [
    '122A54',
    '1E3A5F',
    '1D4ED8',
    '3B82F6',
    '93C5FD',
    '64748B',
    '94A3B8',
    'C8A86B',
];

$errorMessage = '';
$successMessage = '';

if (isset($_SESSION['evaluate_flash'])) {
    $successMessage = (string) $_SESSION['evaluate_flash'];
    unset($_SESSION['evaluate_flash']);
}

$formValues = ['comments' => ''];
foreach ($scoreFieldKeys as $k) {
    $formValues[$k] = '0';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['action'] ?? '') === 'save_assessment') {
    if (!validate_csrf_token((string) ($_POST['csrf_token'] ?? ''))) {
        $errorMessage = app_public_error('csrf');
    }
    $internshipId = (int) ($_POST['internship_id'] ?? 0);
    $comments = trim((string) ($_POST['comments'] ?? ''));

    foreach ($scoreFieldKeys as $k) {
        $formValues[$k] = (string) ($_POST[$k] ?? '');
    }
    $formValues['comments'] = $comments;

    if ($errorMessage === '' && $internshipId <= 0) {
        $errorMessage = 'Invalid placement reference.';
    } elseif ($errorMessage === '' && $comments === '') {
        $errorMessage = 'Comments are required to justify the marks awarded.';
    } elseif ($errorMessage === '' && strlen($comments) > 65535) {
        $errorMessage = 'Comments are too long.';
    } else {
        $parsedScores = [];
        foreach ($scoreFieldKeys as $k) {
            $parsed = assessor_parse_score_component($formValues[$k]);
            if ($parsed === null) {
                $errorMessage = 'Each component score must be an integer from 0 to 100.';
                break;
            }
            $parsedScores[$k] = $parsed;
        }

        if ($errorMessage === '') {
            try {
                $ownStmt = $pdo->prepare(
                    'SELECT i.internship_id, i.student_id, i.status, c.company_name
                     FROM `Internships` i
                     INNER JOIN `Companies` c ON c.company_id = i.company_id
                     WHERE i.internship_id = :iid AND i.assessor_id = :aid
                     LIMIT 1'
                );
                $ownStmt->execute([':iid' => $internshipId, ':aid' => $uid]);
                $ownRow = $ownStmt->fetch();

                if (!$ownRow) {
                    $errorMessage = 'You are not authorised to assess this placement.';
                } else {
                    $existsStmt = $pdo->prepare(
                        'SELECT assessment_id FROM `Assessments` WHERE internship_id = :iid LIMIT 1'
                    );
                    $existsStmt->execute([':iid' => $internshipId]);
                    $existingId = $existsStmt->fetchColumn();

                    if ($existingId) {
                        $upd = $pdo->prepare(
                            'UPDATE `Assessments`
                             SET score_tasks = :score_tasks,
                                 score_safety = :score_safety,
                                 score_theory = :score_theory,
                                 score_report = :score_report,
                                 score_language = :score_language,
                                 score_lifelong = :score_lifelong,
                                 score_proj_mgmt = :score_proj_mgmt,
                                 score_time_mgmt = :score_time_mgmt,
                                 comments = :comments
                             WHERE internship_id = :iid'
                        );
                        $upd->execute([
                            ':score_tasks' => $parsedScores['score_tasks'],
                            ':score_safety' => $parsedScores['score_safety'],
                            ':score_theory' => $parsedScores['score_theory'],
                            ':score_report' => $parsedScores['score_report'],
                            ':score_language' => $parsedScores['score_language'],
                            ':score_lifelong' => $parsedScores['score_lifelong'],
                            ':score_proj_mgmt' => $parsedScores['score_proj_mgmt'],
                            ':score_time_mgmt' => $parsedScores['score_time_mgmt'],
                            ':comments' => $comments,
                            ':iid' => $internshipId,
                        ]);
                    } else {
                        $ins = $pdo->prepare(
                            'INSERT INTO `Assessments` (
                                internship_id, score_tasks, score_safety, score_theory, score_report,
                                score_language, score_lifelong, score_proj_mgmt, score_time_mgmt, comments
                            ) VALUES (
                                :iid, :score_tasks, :score_safety, :score_theory, :score_report,
                                :score_language, :score_lifelong, :score_proj_mgmt, :score_time_mgmt, :comments
                            )'
                        );
                        $ins->execute([
                            ':iid' => $internshipId,
                            ':score_tasks' => $parsedScores['score_tasks'],
                            ':score_safety' => $parsedScores['score_safety'],
                            ':score_theory' => $parsedScores['score_theory'],
                            ':score_report' => $parsedScores['score_report'],
                            ':score_language' => $parsedScores['score_language'],
                            ':score_lifelong' => $parsedScores['score_lifelong'],
                            ':score_proj_mgmt' => $parsedScores['score_proj_mgmt'],
                            ':score_time_mgmt' => $parsedScores['score_time_mgmt'],
                            ':comments' => $comments,
                        ]);
                    }

                    $totStmt = $pdo->prepare(
                        'SELECT total_mark FROM `Assessments` WHERE internship_id = :iid LIMIT 1'
                    );
                    $totStmt->execute([':iid' => $internshipId]);
                    $totalMark = $totStmt->fetchColumn();
                    $totalStr = $totalMark !== false ? number_format((float) $totalMark, 2, '.', '') : 'n/a';

                    $_SESSION['evaluate_flash'] = 'Assessment saved. Computed total mark: ' . $totalStr . ' / 100.';
                    header('Location: ' . app_route('assessor_evaluate.php') . '?internship_id=' . $internshipId);
                    exit;
                }
            } catch (PDOException $e) {
                app_log_exception('assessor_evaluate.save', $e);
                $errorMessage = app_public_error('db_write');
            }
        }
    }
}

$placement = null;
$existingAssessment = null;

if ($internshipId > 0) {
    try {
        $sql = 'SELECT i.internship_id,
                        i.student_id,
                        i.status,
                        i.start_date,
                        i.end_date,
                        c.company_name,
                        s.cohort_year,
                        COALESCE(u.full_name, s.student_id) AS student_full_name,
                        p.prog_name AS programme_name
                FROM `Internships` i
                INNER JOIN `Students` s ON s.student_id = i.student_id
                INNER JOIN `Companies` c ON c.company_id = i.company_id
                INNER JOIN `Programmes` p ON p.prog_id = s.prog_id
                LEFT JOIN `Users` u ON u.user_id = s.user_id
                WHERE i.internship_id = :iid AND i.assessor_id = :aid
                LIMIT 1';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':iid' => $internshipId, ':aid' => $uid]);
        $placement = $stmt->fetch() ?: null;

        if ($placement !== null) {
            $aStmt = $pdo->prepare(
                'SELECT score_tasks, score_safety, score_theory, score_report, score_language,
                        score_lifelong, score_proj_mgmt, score_time_mgmt, comments, total_mark
                 FROM `Assessments`
                 WHERE internship_id = :iid
                 LIMIT 1'
            );
            $aStmt->execute([':iid' => $internshipId]);
            $existingAssessment = $aStmt->fetch() ?: null;

            $repopulateFromPost = $_SERVER['REQUEST_METHOD'] === 'POST' && $errorMessage !== '';
            if (!$repopulateFromPost && $existingAssessment !== null) {
                foreach ($scoreFieldKeys as $k) {
                    $cell = $existingAssessment[$k] ?? null;
                    if ($cell !== null && $cell !== '') {
                        $formValues[$k] = (string) (int) round((float) $cell);
                    } else {
                        $formValues[$k] = '0';
                    }
                }
                $formValues['comments'] = (string) ($existingAssessment['comments'] ?? '');
            }
        }
    } catch (PDOException $e) {
        app_log_exception('assessor_evaluate.load', $e);
        $errorMessage = app_public_error('db_read');
        $placement = null;
        $existingAssessment = null;
    }
}

$pageTitle = 'Evaluate Internship';
require_once __DIR__ . '/../../../includes/header.php';

$initialTotal = 0.0;
if ($placement !== null) {
    foreach ($scoreFieldKeys as $k) {
        $raw = assessor_parse_score_component($formValues[$k] ?? '0') ?? 0.0;
        $initialTotal += $raw * $rubricFields[$k]['weight_fraction'];
    }
}
?>

<div
    class="app-dashboard-shell"
    id="assessor-evaluate-root"
>
    <section class="card app-card-pro admin-anim-entrance" aria-labelledby="assessor-evaluate-heading">
    <?php if ($placement === null): ?>
        <h2 class="section-heading" id="assessor-evaluate-heading">Assessment form</h2>
    <?php endif; ?>

    <?php if ($successMessage !== ''): ?>
        <div class="alert-success" role="status"><?= h($successMessage) ?></div>
    <?php endif; ?>

    <?php if ($errorMessage !== ''): ?>
        <div class="alert-error" role="alert"><?= h($errorMessage) ?></div>
    <?php endif; ?>

    <?php if ($placement === null && $internshipId > 0): ?>
        <p class="lead" role="status">This placement could not be loaded or you do not have access. <a class="text-link" href="<?= h(app_route('assessor_dashboard.php')) ?>">Return to dashboard</a></p>
    <?php elseif ($placement !== null): ?>
        <?php
        $heroName = (string) ($placement['student_full_name'] ?? $placement['student_id'] ?? '');
        $heroProg = (string) ($placement['programme_name'] ?? '');
        $statKey = strtolower((string) ($placement['status'] ?? ''));
        $heroStatusClass = $statKey === 'completed' ? 'eval-hero-card__badge--completed' : 'eval-hero-card__badge--ongoing';
        $cohortPeriodLine = internship_cohort_period_caption(
            isset($placement['cohort_year']) ? (string) $placement['cohort_year'] : null,
            isset($placement['start_date']) ? (string) $placement['start_date'] : null,
            isset($placement['end_date']) ? (string) $placement['end_date'] : null
        );
        ?>

        <div class="eval-hero-card">
            <div class="eval-hero-card__profile">
                <h2 class="eval-hero-card__name" id="assessor-evaluate-heading"><?= h($heroName) ?></h2>
                <p class="eval-hero-card__meta">
                    <span class="eval-hero-card__id">ID: <?= h((string) $placement['student_id']) ?></span>
                </p>
                <?php if ($heroProg !== ''): ?>
                    <p class="eval-hero-card__meta eval-hero-card__meta--prog"><?= h($heroProg) ?></p>
                <?php endif; ?>
                <p class="eval-hero-card__meta eval-hero-card__meta--company"><?= h((string) $placement['company_name']) ?></p>
                <p class="eval-hero-card__meta eval-hero-card__meta--period eval-hero-card__meta--cohort-line" role="group" aria-label="Cohort and internship period">
                    <?= h($cohortPeriodLine) ?>
                </p>
                <span class="eval-hero-card__badge <?= h($heroStatusClass) ?>"><?= h((string) $placement['status']) ?></span>
                <?php if ($existingAssessment !== null && isset($existingAssessment['total_mark'])): ?>
                    <p class="eval-hero-card__db-total" role="status">Last saved total (DB):
                        <strong><?= h(number_format((float) $existingAssessment['total_mark'], 2, '.', '')) ?></strong> / 100
                    </p>
                <?php endif; ?>
            </div>
            <div class="eval-hero-card__weighting" aria-labelledby="eval-hero-weighting-heading">
                <h3 class="eval-hero-card__chart-title" id="eval-hero-weighting-heading">Rubric weighting (100%)</h3>
                <div class="eval-hero-card__chart-inner">
                    <?= assessor_weight_hero_svg($assessorWeightHeroPcts, $assessorWeightHeroLabels, $assessorWeightHeroColors) ?>
                </div>
            </div>
        </div>

        <div id="innovation-charts-root" class="visually-hidden" data-internship-id="<?= h((string) $placement['internship_id']) ?>"></div>

        <div class="eval-workspace">
            <div class="eval-form-area">
                <form method="post" action="<?= h(app_route('assessor_evaluate.php')) ?>" class="form-stack eval-matrix-form" id="assessor-evaluate-form">
                    <input type="hidden" name="action" value="save_assessment">
                    <input type="hidden" name="internship_id" value="<?= h((string) $placement['internship_id']) ?>">
                    <?= csrf_input() ?>

                    <?php foreach ($evalCardSections as $sec): ?>
                        <section
                            class="eval-card-section"
                            id="<?= h($sec['id']) ?>"
                            aria-labelledby="<?= h($sec['id']) ?>-h"
                        >
                            <h3 class="eval-card-section__title" id="<?= h($sec['id']) ?>-h"><?= h($sec['title']) ?></h3>
                            <div class="eval-card-section__body">
                            <?php foreach ($sec['keys'] as $name):
                                if (!isset($rubricFields[$name])) {
                                    continue;
                                }
                                $rm = $rubricFields[$name];
                                $v = $formValues[$name] ?? '0';
                                $numV = $v !== '' ? h($v) : '0';
                                $wf = $rm['weight_fraction'];
                                $maxW = $rm['max_weighted_points'];
                                $raw0 = (float) preg_replace('/[^0-9.\-]/', '', (string) $v);
                                if ($raw0 < 0) {
                                    $raw0 = 0.0;
                                }
                                if ($raw0 > 100) {
                                    $raw0 = 100.0;
                                }
                                $raw0 = (float) (int) round($raw0);
                                $wShow = $raw0 * $wf;
                                ?>
                        <div
                            class="rubric-line rubric-line--atomic"
                            data-eval-criterion="<?= h($name) ?>"
                            data-legend="<?= h($rm['label']) ?>"
                            data-weight="<?= h((string) $wf) ?>"
                            data-max-weighted="<?= h((string) $maxW) ?>"
                        >
                            <div class="rubric-line__left">
                                <div class="rubric-line__title-block">
                                    <span class="rubric-line__label"><?= h($rm['label']) ?></span>
                                    <span class="rubric-line__weight">(<?= h($rm['weight_label']) ?>)</span>
                                </div>
                                <p class="rubric-line__weighted" id="w-<?= h($name) ?>">
                                    <span class="weighted-label">Weighted</span>
                                    <span class="calc-score"><?= h(number_format($wShow, 2, '.', '')) ?></span>
                                    <span class="rubric-weight-sep"> / </span>
                                    <span class="max-score"><?= h(number_format((float) $maxW, 2, '.', '')) ?></span>
                                </p>
                            </div>
                            <div class="rubric-line__input-col">
                                <input
                                    class="massive-score-input massive-score-input--int"
                                    id="num-<?= h($name) ?>"
                                    name="<?= h($name) ?>"
                                    type="number"
                                    min="0"
                                    max="100"
                                    step="1"
                                    required
                                    inputmode="numeric"
                                    pattern="[0-9]*"
                                    value="<?= $numV ?>"
                                    data-weight="<?= h((string) $wf) ?>"
                                    aria-label="<?= h($rm['label']) ?>, integer 0 to 100"
                                >
                            </div>
                        </div>
                            <?php endforeach; ?>
                            </div>
                        </section>
                    <?php endforeach; ?>

                    <div class="form-field">
                        <label class="form-label" for="comments">Comments (required)</label>
                        <textarea
                            class="form-input"
                            id="comments"
                            name="comments"
                            rows="5"
                            required
                            maxlength="60000"
                            placeholder="Justify the marks in line with faculty expectations."
                        ><?= h($formValues['comments']) ?></textarea>
                    </div>

                </form>
            </div>

            <aside class="eval-summary-panel" aria-label="Live score summary">
                <h3 class="eval-summary-panel__title">Current total</h3>
                <div class="live-total-row">
                <p class="live-total-mark">
                    <span class="live-total-mark__value"><?= h(number_format($initialTotal, 2, '.', '')) ?></span><span class="live-total-mark__suffix"> / 100</span>
                </p>
                <?php
                $it = $initialTotal;
                $bClass = 'eval-grade-badge--pass';
                $bLabel = 'Pass';
                $bData = 'pass';
                if ($it < 40) {
                    $bClass = 'eval-grade-badge--fail';
                    $bLabel = 'Fail';
                    $bData = 'fail';
                } elseif ($it < 60) {
                    $bClass = 'eval-grade-badge--pass';
                    $bLabel = 'Pass';
                    $bData = 'pass';
                } elseif ($it < 70) {
                    $bClass = 'eval-grade-badge--merit';
                    $bLabel = 'Merit';
                    $bData = 'merit';
                } else {
                    $bClass = 'eval-grade-badge--distinction eval-grade-badge--distinction-glow';
                    $bLabel = 'Distinction';
                    $bData = 'distinction';
                }
                ?>
                <span class="badge eval-grade-badge <?= h($bClass) ?>" data-grade-badge data-grade="<?= h($bData) ?>"><?= h($bLabel) ?></span>
                </div>
                <p class="eval-summary-hint">Class bands: &lt;40 Fail · 40–59 Pass · 60–69 Merit · ≥70 Distinction. Total = weighted sum.</p>
                <button
                    type="submit"
                    class="btn-nottingham eval-save-btn"
                    id="eval-save-btn"
                    form="assessor-evaluate-form"
                >
                    <span class="eval-save-btn__face eval-save-btn__face--idle">
                        <span class="eval-save-btn__label">Save assessment</span>
                    </span>
                    <span class="eval-save-btn__face eval-save-btn__face--busy" hidden>
                        <span class="eval-save-btn__spinner" aria-hidden="true"></span>
                        <span class="eval-save-btn__loading-text">Saving…</span>
                    </span>
                </button>
            </aside>
        </div>
    <?php endif; ?>
    </section>
</div>

<?php if ($placement !== null): ?>
<script src="<?= h(asset_v('assets/js/modules/assessor-evaluate.js')) ?>" defer></script>
<?php endif; ?>
<?php
require_once __DIR__ . '/../../../includes/footer.php';
