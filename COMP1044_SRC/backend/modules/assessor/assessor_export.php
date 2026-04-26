<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../includes/db_connect.php';
require_once __DIR__ . '/../../../includes/functions.php';

ensure_session();
require_role('Assessor');

$uid = current_user_id();
if ($uid === null) {
    header('Location: ' . app_route('index.php'));
    exit;
}

$assessorName = trim((string) ($_SESSION['full_name'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['export'] ?? '') === 'csv') {
    if (!validate_csrf_token((string) ($_POST['csrf_token'] ?? ''))) {
        $exportError = app_public_error('csrf');
    } else {
    try {
        $stmt = $pdo->prepare(
            'SELECT i.internship_id, i.student_id, i.status, i.start_date, i.end_date,
                    c.company_name,
                    a.score_tasks, a.score_safety, a.score_theory, a.score_report,
                    a.score_language, a.score_lifelong, a.score_proj_mgmt, a.score_time_mgmt,
                    a.total_mark, a.comments
             FROM `Internships` i
             INNER JOIN `Companies` c ON c.company_id = i.company_id
             LEFT JOIN `Assessments` a ON a.internship_id = i.internship_id
             WHERE i.assessor_id = :aid
             ORDER BY i.internship_id ASC'
        );
        $stmt->execute([':aid' => $uid]);
        $rows = $stmt->fetchAll();

        $filename = 'assessor_export_' . date('Ymd_His') . '.csv';
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $out = fopen('php://output', 'wb');
        if ($out === false) {
            throw new RuntimeException('Failed to open output stream.');
        }

        fputcsv($out, [
            'internship_id',
            'student_id',
            'company_name',
            'status',
            'start_date',
            'end_date',
            'score_tasks',
            'score_safety',
            'score_theory',
            'score_report',
            'score_language',
            'score_lifelong',
            'score_proj_mgmt',
            'score_time_mgmt',
            'total_mark',
            'comments',
        ]);

        foreach ($rows as $r) {
            fputcsv($out, [
                (string) $r['internship_id'],
                (string) $r['student_id'],
                (string) $r['company_name'],
                (string) $r['status'],
                (string) $r['start_date'],
                (string) ($r['end_date'] ?? ''),
                (string) ($r['score_tasks'] ?? ''),
                (string) ($r['score_safety'] ?? ''),
                (string) ($r['score_theory'] ?? ''),
                (string) ($r['score_report'] ?? ''),
                (string) ($r['score_language'] ?? ''),
                (string) ($r['score_lifelong'] ?? ''),
                (string) ($r['score_proj_mgmt'] ?? ''),
                (string) ($r['score_time_mgmt'] ?? ''),
                (string) ($r['total_mark'] ?? ''),
                (string) ($r['comments'] ?? ''),
            ]);
        }

        fclose($out);
        exit;
    } catch (Throwable $e) {
        app_log_exception('assessor_export.csv', $e);
        $exportError = app_public_error('db_write');
    }
    }
}

/** @return array{label: string, weight_label: string, weight_fraction: float} */
function assessor_export_rubric_row(string $key): array
{
    $m = [
        'score_tasks' => ['Tasks', '10%'],
        'score_safety' => ['Health & Safety', '10%'],
        'score_theory' => ['Theory', '10%'],
        'score_report' => ['Report', '15%'],
        'score_language' => ['Language', '10%'],
        'score_lifelong' => ['Lifelong learning', '15%'],
        'score_proj_mgmt' => ['Project management', '15%'],
        'score_time_mgmt' => ['Time management', '15%'],
    ];
    $p = $m[$key] ?? [$key, '0%'];
    $pct = (float) preg_replace('/[^0-9.]/', '', $p[1]);
    if ($pct <= 0) {
        $pct = 0.0;
    }

    return [
        'label' => $p[0],
        'weight_label' => $p[1],
        'weight_fraction' => $pct / 100.0,
    ];
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

$assessorDonutPcts = [10, 10, 10, 15, 10, 15, 15, 15];
$assessorDonutColors = [
    '0A1931', '122A54', '1E3A8A', '2563EB', '60A5FA', '93C5FD', 'C8A86B', '94A3B8',
];

function assessor_export_grade_from_total(float $t): string
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

/**
 * Static print-friendly donut: centre shows total and grade.
 *
 * @param list<float> $weightPcts
 * @param list<string> $colors
 */
function assessor_transcript_static_donut_svg(
    array $weightPcts,
    array $colors,
    string $centerLine1,
    string $centerLine2
): string {
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
    $ri = 0.6;
    $out = '<svg class="transcript-donut" viewBox="-1.05 -1.05 2.1 2.1" width="260" height="260" role="img" aria-label="Overall result summary" xmlns="http://www.w3.org/2000/svg">';
    $out .= '<title>Weighted result distribution</title>';
    $out .= '<g class="transcript-donut__slices">';
    for ($i = 0; $i < $n; $i++) {
        $w = (float) $weightPcts[$i];
        $c = $i < \count($colors) ? $colors[$i] : '64748B';
        $c = ltrim($c, '#');
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
        $out .= '<path d="' . h($d) . '" fill="#' . h($c) . '" />';
        $a0 = $a1;
    }
    $out .= '</g>';
    $c1 = h($centerLine1);
    $c2 = h($centerLine2);
    // Font size must be in SVG user units (viewBox width ≈2.1); CSS px on <text> breaks badly in Chrome (huge text → clip → dark square).
    $out .= '<g class="transcript-donut__label" text-anchor="middle" font-family="system-ui, var(--font-body, sans-serif)">'
        . '<text class="transcript-donut__line1" x="0" y="-0.06" font-size="0.13" font-weight="700" fill="#0f172a" dominant-baseline="middle">'
        . $c1
        . '</text>'
        . '<text class="transcript-donut__line2" x="0" y="0.075" font-size="0.067" font-weight="600" fill="#334155" dominant-baseline="middle">'
        . $c2
        . '</text>'
        . '</g>';
    $out .= '</svg>';

    return $out;
}

$previewId = 0;
if (isset($_GET['internship_id']) && trim((string) $_GET['internship_id']) !== '') {
    $previewId = (int) $_GET['internship_id'];
}

$transcript = null;
$transcriptListErr = null;

$listStmt = $pdo->prepare(
    'SELECT i.internship_id, i.student_id, i.status, c.company_name, a.total_mark
     FROM `Internships` i
     INNER JOIN `Companies` c ON c.company_id = i.company_id
     LEFT JOIN `Assessments` a ON a.internship_id = i.internship_id
     WHERE i.assessor_id = :aid
     ORDER BY i.internship_id DESC'
);
$listStmt->execute([':aid' => $uid]);
$placementList = $listStmt->fetchAll();

if ($previewId > 0) {
    $one = $pdo->prepare(
        'SELECT i.internship_id, i.student_id, i.status, i.start_date, i.end_date,
                c.company_name, s.cohort_year, p.prog_name,
                COALESCE(u.full_name, s.student_id) AS student_name,
                a.score_tasks, a.score_safety, a.score_theory, a.score_report,
                a.score_language, a.score_lifelong, a.score_proj_mgmt, a.score_time_mgmt,
                a.total_mark, a.comments
         FROM `Internships` i
         INNER JOIN `Students` s ON s.student_id = i.student_id
         INNER JOIN `Companies` c ON c.company_id = i.company_id
         INNER JOIN `Programmes` p ON p.prog_id = s.prog_id
         LEFT JOIN `Users` u ON u.user_id = s.user_id
         LEFT JOIN `Assessments` a ON a.internship_id = i.internship_id
         WHERE i.internship_id = :iid AND i.assessor_id = :aid
         LIMIT 1'
    );
    $one->execute([':iid' => $previewId, ':aid' => $uid]);
    $row = $one->fetch();
    if ($row) {
        $transcript = $row;
    } else {
        $transcriptListErr = 'That placement was not found or is not assigned to you.';
    }
}

$pageTitle = 'Export & official transcript';
$bodyClassExtra = ' page-assessor-export';
require_once __DIR__ . '/../../../includes/header.php';

$transcriptCohortLine = '';
if ($transcript !== null) {
    $transcriptCohortLine = internship_cohort_period_caption(
        isset($transcript['cohort_year']) ? (string) $transcript['cohort_year'] : null,
        isset($transcript['start_date']) ? (string) $transcript['start_date'] : null,
        isset($transcript['end_date']) ? (string) $transcript['end_date'] : null
    );
}

$exportBase = h(app_route('assessor_export.php'));
?>

<div class="assessor-export-page">
    <div class="assessor-export-toolbar no-print">
        <div class="assessor-export-toolbar__actions">
            <?php if ($transcript !== null): ?>
            <button type="button" class="assessor-export-btn-print" onclick="window.print()">Print / save as PDF</button>
            <a class="btn-ghost-pill" href="<?= $exportBase ?>">All placements</a>
            <?php endif; ?>
        </div>
        <form method="post" action="<?= $exportBase ?>" class="form-inline no-print" style="display:inline">
            <input type="hidden" name="export" value="csv">
            <?= csrf_input() ?>
            <button class="btn-nottingham" type="submit">Download CSV (all)</button>
        </form>
    </div>

    <?php if (isset($exportError)): ?>
        <div class="assessor-export-list no-print">
            <div class="alert-error" role="alert"><?= h($exportError) ?></div>
        </div>
    <?php endif; ?>

    <?php if ($transcriptListErr !== null): ?>
        <div class="assessor-export-list no-print">
            <div class="alert-error" role="alert"><?= h($transcriptListErr) ?></div>
        </div>
    <?php endif; ?>

    <?php if ($transcript === null): ?>
    <div class="assessor-export-bleed no-print">
        <div class="assessor-export-list">
            <h2>Official transcript — choose a placement</h2>
            <p class="lead" style="margin-top: 0; color: #475569">Select a record to open the A4 official preview, or download all rows as CSV.</p>
            <?php if ($placementList === []): ?>
                <p class="lead">No internship placements are assigned to you yet.</p>
            <?php else: ?>
            <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th scope="col">Student</th>
                        <th scope="col">Company</th>
                        <th scope="col">Status</th>
                        <th scope="col" class="num">Total (DB)</th>
                        <th scope="col">Transcript</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($placementList as $pl): ?>
                    <tr>
                        <td class="transcript-mono"><strong><?= h((string) $pl['student_id']) ?></strong></td>
                        <td><?= h((string) $pl['company_name']) ?></td>
                        <td><?= h((string) $pl['status']) ?></td>
                        <td class="num"><?= isset($pl['total_mark']) && $pl['total_mark'] !== null && $pl['total_mark'] !== '' ? h(number_format((float) $pl['total_mark'], 2, '.', '')) : '—' ?></td>
                        <td><a class="text-link" href="<?= $exportBase . '?internship_id=' . rawurlencode((string) $pl['internship_id']) ?>">View official preview</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php else: ?>
    <div class="assessor-export-bleed">
        <div class="assessor-export-wrap">
        <div class="transcript-paper" id="assessor-transcript-paper">
            <div class="transcript-paper__inner">
                <header class="transcript-head">
                    <div class="transcript-head__left">UNIVERSITY OF NOTTINGHAM</div>
                    <div class="transcript-head__right">
                        Official Assessment Transcript
                        <span>Internship results — quality assurance</span>
                    </div>
                </header>
                <div class="transcript-rule" role="separator" aria-hidden="true"></div>

                <dl class="transcript-meta">
                    <div class="transcript-meta__row"><dt>Student: </dt><dd><?= h((string) $transcript['student_name']) ?></dd></div>
                    <div class="transcript-meta__row"><dt>Student ID: </dt><dd class="transcript-mono"><?= h((string) $transcript['student_id']) ?></dd></div>
                    <div class="transcript-meta__row"><dt>Programme: </dt><dd><?= h((string) $transcript['prog_name']) ?></dd></div>
                    <div class="transcript-meta__row"><dt>Host organisation: </dt><dd><?= h((string) $transcript['company_name']) ?></dd></div>
                    <div class="transcript-meta__row"><dt>Placement status: </dt><dd><?= h((string) $transcript['status']) ?></dd></div>
                    <div class="transcript-meta__row" style="grid-column:1/-1">
                        <dt>Period: </dt><dd><?= h($transcriptCohortLine) ?></dd>
                    </div>
                    <div class="transcript-meta__row"><dt>Reference: </dt><dd class="transcript-mono">INT-<?= h((string) $transcript['internship_id']) ?></dd></div>
                    <div class="transcript-meta__row"><dt>Record generated: </dt><dd><?= h(date('d F Y')) ?></dd></div>
                </dl>

                <div class="transcript-table-wrap">
                    <table class="transcript-data-table">
                        <thead>
                            <tr>
                                <th scope="col">Criterion</th>
                                <th scope="col">Weighting</th>
                                <th scope="col" class="transcript-data-table__num">Mark (0–100)</th>
                                <th scope="col" class="transcript-data-table__weighted">Weighted</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php
                        $rowSum = 0.0;
                        foreach ($scoreFieldKeys as $sk):
                            $rf = assessor_export_rubric_row($sk);
                            $rawV = $transcript[$sk] ?? null;
                            if ($rawV === null || $rawV === '') {
                                $rawNum = null;
                            } else {
                                $rawNum = (float) $rawV;
                            }
                            $wtd = $rawNum === null ? null : $rawNum * $rf['weight_fraction'];
                            if ($wtd !== null) {
                                $rowSum += $wtd;
                            }
                            ?>
                            <tr>
                                <td><?= h($rf['label']) ?></td>
                                <td><?= h($rf['weight_label']) ?></td>
                                <td class="transcript-data-table__num"><?= $rawNum === null ? '—' : h(number_format($rawNum, 2, '.', '')) ?></td>
                                <td class="transcript-data-table__weighted"><?= $wtd === null ? '—' : h(number_format($wtd, 2, '.', '')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <p style="margin:0 0 0.4rem; font-size:0.8rem; font-weight:700; color:#0f172a">Aggregate result</p>
                <p style="margin:0 0 0.5rem; font-size:0.95rem" class="transcript-mono">Overall total (computed): <strong><?= h(number_format($rowSum, 2, '.', '')) ?></strong> / 100.00
                    <?php if (!isset($transcript['total_mark']) || $transcript['total_mark'] === null || $transcript['total_mark'] === ''): ?>
                        <span style="color:#94a3b8"> — No assessment stored yet.</span>
                    <?php endif; ?>
                </p>

                <section class="transcript-donut-section" aria-labelledby="tsum-h">
                    <h2 class="transcript-donut-section__label" id="tsum-h">Component weighting and overall classification</h2>
                    <?php
                    if (isset($transcript['total_mark']) && $transcript['total_mark'] !== null && $transcript['total_mark'] !== '') {
                        $tt = (float) $transcript['total_mark'];
                        echo assessor_transcript_static_donut_svg(
                            $assessorDonutPcts,
                            $assessorDonutColors,
                            number_format($tt, 2, '.', '') . ' / 100',
                            assessor_export_grade_from_total($tt)
                        );
                    } else {
                        echo assessor_transcript_static_donut_svg(
                            $assessorDonutPcts,
                            $assessorDonutColors,
                            '—',
                            'Pending'
                        );
                    }
                    ?>
                </section>

                <?php if (isset($transcript['comments']) && trim((string) $transcript['comments']) !== ''): ?>
                <dl class="transcript-comments">
                    <dt>Assessor comments</dt>
                    <dd><?= h((string) $transcript['comments']) ?></dd>
                </dl>
                <?php endif; ?>

                <footer class="transcript-foot">
                    <div class="transcript-foot__col">
                        <div class="transcript-foot__line" aria-hidden="true"></div>
                        <p class="transcript-foot__label">Assessor signature</p>
                        <?php if ($assessorName !== ''): ?>
                            <p class="transcript-foot__meta"><?= h($assessorName) ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="transcript-foot__col transcript-foot__col--date">
                        <div class="transcript-foot__line" aria-hidden="true"></div>
                        <p class="transcript-foot__label">Date</p>
                        <p class="transcript-foot__meta"><?= h(date('d F Y')) ?></p>
                    </div>
                </footer>
            </div>
        </div>
    </div>
    </div>
    <?php endif; ?>
</div>

<?php
require_once __DIR__ . '/../../../includes/footer.php';
