<?php
declare(strict_types=1);

/**
 * Admin — internship assignment CRUD (Section A(b), COMP1044).
 * INSERT / UPDATE `Internships`; dropdowns from `Students`, `Companies`, `Users` (Assessor).
 */

require_once __DIR__ . '/../../../includes/db_connect.php';
require_once __DIR__ . '/../../../includes/functions.php';

ensure_session();

if (($_SESSION['role'] ?? '') !== 'Admin') {
    header('Location: ' . app_route('index.php'));
    exit;
}

/**
 * List URL for internship search (full list on one page; browser scroll).
 */
function admin_internships_list_url(array $overrides = []): string
{
    $q = trim((string) ($overrides['q'] ?? ''));
    $p = [];
    if ($q !== '') {
        $p['q'] = $q;
    }
    $qs = http_build_query($p);
    return app_route('admin_internships.php') . ($qs !== '' ? '?' . $qs : '');
}

/**
 * Preserve list context when a POST action fails.
 */
function admin_internships_return_fields(string $listQ): string
{
    return '<input type="hidden" name="return_q" value="' . h($listQ) . '">';
}

$errorMessage = '';
$flashMessage = '';

if (isset($_SESSION['admin_internships_flash'])) {
    $flashMessage = (string) $_SESSION['admin_internships_flash'];
    unset($_SESSION['admin_internships_flash']);
}

/**
 * @return string|null Y-m-d or null if empty invalid
 */
function admin_internships_parse_date(?string $raw, bool $allowEmpty): ?string
{
    $raw = trim((string) $raw);
    if ($raw === '') {
        return $allowEmpty ? null : '';
    }

    $dt = DateTimeImmutable::createFromFormat('Y-m-d', $raw);
    if ($dt === false || $dt->format('Y-m-d') !== $raw) {
        return '';
    }

    return $raw;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['action'] ?? '') === 'save_internship') {
    if (!validate_csrf_token((string) ($_POST['csrf_token'] ?? ''))) {
        $errorMessage = app_public_error('csrf');
    }

    $internshipId = (int) ($_POST['internship_id'] ?? 0);
    $studentId = trim((string) ($_POST['student_id'] ?? ''));
    $companyId = (int) ($_POST['company_id'] ?? 0);
    $assessorId = (int) ($_POST['assessor_id'] ?? 0);
    $startRaw = trim((string) ($_POST['start_date'] ?? ''));
    $endRaw = trim((string) ($_POST['end_date'] ?? ''));
    $status = trim((string) ($_POST['status'] ?? ''));

    $startDate = admin_internships_parse_date($startRaw, false);
    $endDate = admin_internships_parse_date($endRaw, true);

    if ($studentId === '' || $companyId <= 0 || $assessorId <= 0) {
        $errorMessage = 'save_internship: student_id, company_id, and assessor_id are required.';
    } elseif ($startDate === '' || $startDate === null) {
        $errorMessage = 'save_internship: start_date must be YYYY-MM-DD.';
    } elseif ($endDate === '') {
        $errorMessage = 'save_internship: end_date invalid (use YYYY-MM-DD or leave blank for ongoing).';
    } elseif ($status !== 'Ongoing' && $status !== 'Completed') {
        $errorMessage = 'save_internship: status must be Ongoing or Completed.';
    } else {
        try {
            $stChk = $pdo->prepare('SELECT 1 FROM `Students` WHERE student_id = :sid LIMIT 1');
            $stChk->execute([':sid' => $studentId]);
            if (!$stChk->fetch()) {
                $errorMessage = 'save_internship: unknown student_id.';
            }

            $coChk = $pdo->prepare('SELECT 1 FROM `Companies` WHERE company_id = :cid LIMIT 1');
            $coChk->execute([':cid' => $companyId]);
            if (!$coChk->fetch()) {
                $errorMessage = 'save_internship: unknown company_id.';
            }

            $asChk = $pdo->prepare(
                'SELECT 1 FROM `Users` WHERE user_id = :aid AND role = \'Assessor\' LIMIT 1'
            );
            $asChk->execute([':aid' => $assessorId]);
            if (!$asChk->fetch()) {
                $errorMessage = 'save_internship: assessor_id must reference an Assessor user.';
            }
        } catch (PDOException $e) {
            app_log_exception('admin_internships.validate', $e);
            $errorMessage = app_public_error('db_read');
        }
    }

    if ($errorMessage === '') {
        try {
            if ($internshipId > 0) {
                $ex = $pdo->prepare('SELECT 1 FROM `Internships` WHERE internship_id = :iid LIMIT 1');
                $ex->execute([':iid' => $internshipId]);
                if (!$ex->fetch()) {
                    $errorMessage = 'save_internship: internship_id not found.';
                }
            }

            if ($errorMessage === '') {
                if ($internshipId > 0) {
                    $stmt = $pdo->prepare(
                        'UPDATE `Internships`
                         SET student_id = :sid,
                             company_id = :cid,
                             assessor_id = :aid,
                             start_date = :sd,
                             end_date = :ed,
                             status = :st
                         WHERE internship_id = :iid'
                    );
                    $stmt->bindValue(':sid', $studentId, PDO::PARAM_STR);
                    $stmt->bindValue(':cid', $companyId, PDO::PARAM_INT);
                    $stmt->bindValue(':aid', $assessorId, PDO::PARAM_INT);
                    $stmt->bindValue(':sd', $startDate, PDO::PARAM_STR);
                    if ($endDate === null) {
                        $stmt->bindValue(':ed', null, PDO::PARAM_NULL);
                    } else {
                        $stmt->bindValue(':ed', $endDate, PDO::PARAM_STR);
                    }
                    $stmt->bindValue(':st', $status, PDO::PARAM_STR);
                    $stmt->bindValue(':iid', $internshipId, PDO::PARAM_INT);
                    $stmt->execute();
                } else {
                    $stmt = $pdo->prepare(
                        'INSERT INTO `Internships` (student_id, company_id, assessor_id, start_date, end_date, status)
                         VALUES (:sid, :cid, :aid, :sd, :ed, :st)'
                    );
                    $stmt->bindValue(':sid', $studentId, PDO::PARAM_STR);
                    $stmt->bindValue(':cid', $companyId, PDO::PARAM_INT);
                    $stmt->bindValue(':aid', $assessorId, PDO::PARAM_INT);
                    $stmt->bindValue(':sd', $startDate, PDO::PARAM_STR);
                    if ($endDate === null) {
                        $stmt->bindValue(':ed', null, PDO::PARAM_NULL);
                    } else {
                        $stmt->bindValue(':ed', $endDate, PDO::PARAM_STR);
                    }
                    $stmt->bindValue(':st', $status, PDO::PARAM_STR);
                    $stmt->execute();
                }

                $retQ = trim((string) ($_POST['return_q'] ?? ''));
                $_SESSION['admin_internships_flash'] = $internshipId > 0 ? 'Internship updated.' : 'Internship created.';
                $loc = admin_internships_list_url(['q' => $retQ]);
                header('Location: ' . $loc);
                exit;
            }
        } catch (PDOException $e) {
            app_log_exception('admin_internships.save', $e);
            $sqlState = (string) ($e->errorInfo[0] ?? '');
            $mysqlCode = (int) ($e->errorInfo[1] ?? 0);
            $errorMessage = ($sqlState === '23000' || $mysqlCode === 1062)
                ? 'save_internship: foreign key or constraint violation.'
                : app_public_error('db_write');
        }
    }
}

$companyRows = [];
$assessorRows = [];
$studentRows = [];
$internshipRows = [];

$listQ = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $errorMessage !== '') {
    $listQ = trim((string) ($_POST['return_q'] ?? ''));
} else {
    $listQ = trim((string) ($_GET['q'] ?? ''));
}

$likeQ = $listQ === '' ? null : ('%' . $listQ . '%');
$joinFrom = ' FROM `Internships` i
         INNER JOIN `Companies` c ON c.company_id = i.company_id
         INNER JOIN `Users` ua ON ua.user_id = i.assessor_id
         INNER JOIN `Students` st ON st.student_id = i.student_id
         INNER JOIN `Users` u ON u.user_id = st.user_id';
$selectList = 'SELECT i.internship_id, i.student_id, i.company_id, i.assessor_id, i.start_date, i.end_date, i.status,
                c.company_name, ua.full_name AS assessor_name, u.full_name AS student_name'
    . $joinFrom;
$whereSearch = ' WHERE (
         CAST(i.internship_id AS CHAR) LIKE :l0
         OR i.student_id LIKE :l1
         OR u.full_name LIKE :l2
         OR c.company_name LIKE :l3
         OR ua.full_name LIKE :l4
     )';

try {
    $cStmt = $pdo->query(
        'SELECT company_id, company_name FROM `Companies` ORDER BY company_name ASC'
    );
    $companyRows = $cStmt->fetchAll();

    $aStmt = $pdo->query(
        'SELECT user_id, full_name, email FROM `Users` WHERE role = \'Assessor\' ORDER BY full_name ASC'
    );
    $assessorRows = $aStmt->fetchAll();

    $sStmt = $pdo->query(
        'SELECT st.student_id, u.full_name,
                (SELECT COUNT(*) FROM `Internships` ix WHERE ix.student_id = st.student_id) AS placement_count
         FROM `Students` st
         INNER JOIN `Users` u ON u.user_id = st.user_id
         ORDER BY placement_count ASC, st.student_id ASC'
    );
    $studentRows = $sStmt->fetchAll();

    if ($likeQ === null) {
        $iStmt = $pdo->query($selectList . ' ORDER BY i.internship_id DESC');
        $internshipRows = $iStmt->fetchAll();
    } else {
        $iStmt = $pdo->prepare(
            $selectList . $whereSearch . ' ORDER BY i.internship_id DESC'
        );
        $iStmt->bindValue(':l0', $likeQ, PDO::PARAM_STR);
        $iStmt->bindValue(':l1', $likeQ, PDO::PARAM_STR);
        $iStmt->bindValue(':l2', $likeQ, PDO::PARAM_STR);
        $iStmt->bindValue(':l3', $likeQ, PDO::PARAM_STR);
        $iStmt->bindValue(':l4', $likeQ, PDO::PARAM_STR);
        $iStmt->execute();
        $internshipRows = $iStmt->fetchAll();
    }
} catch (PDOException $e) {
    app_log_exception('admin_internships.load', $e);
    if ($errorMessage === '') {
        $errorMessage = app_public_error('db_read');
    }
}

$openAddInternship = $errorMessage !== '' && $_SERVER['REQUEST_METHOD'] === 'POST'
    && (string) ($_POST['action'] ?? '') === 'save_internship'
    && (int) ($_POST['internship_id'] ?? 0) === 0;
$rf = admin_internships_return_fields($listQ);

$pageTitle = 'Internships';
require_once __DIR__ . '/../../../includes/header.php';
?>

<?php if ($flashMessage !== '' || $errorMessage !== ''): ?>
<div class="admin-messages" id="admin-messages" aria-live="polite">
    <?php if ($flashMessage !== ''): ?>
        <div class="admin-toast admin-toast--success" id="msg-flash" role="status" data-auto-dismiss="true">
            <span class="admin-toast__text"><?= h($flashMessage) ?></span>
        </div>
    <?php endif; ?>
    <?php if ($errorMessage !== ''): ?>
        <div class="admin-toast admin-toast--error" id="msg-error" role="alert">
            <span class="admin-toast__text"><?= h($errorMessage) ?></span>
        </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<section
    class="card admin-internships"
    id="admin-internships"
    aria-labelledby="admin-internships-heading"
    data-api-create-company="<?= h(app_route('api_create_company.php')) ?>"
    data-csrf="<?= h(csrf_token()) ?>"
>
    <h2 class="visually-hidden" id="admin-internships-heading">Internship records</h2>
    <script type="application/json" id="admin-internships-company-data"><?= json_encode(
        array_values($companyRows),
        256 | 1
    ) ?></script>
    <div class="admin-internships__toolbar">
        <form method="get" class="admin-manage-search" action="<?= h(app_route('admin_internships.php')) ?>">
            <input type="search" name="q" class="input-nottingham admin-manage-search__input" id="q-internships" placeholder="Search by name, company, or ID…" value="<?= h($listQ) ?>" aria-label="Search internships" autocomplete="off">
        </form>
        <button type="button" class="btn-nottingham admin-btn-add" id="btn-toggle-add-internship" aria-expanded="<?= $openAddInternship ? 'true' : 'false' ?>" aria-controls="admin-add-internship-wrap">
            <svg class="admin-btn-add__icon" width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M19 11h-6V5h-2v6H5v2h6v6h2v-6h6v-2z"/></svg>
            <span>Add internship</span>
        </button>
    </div>
    <div class="admin-add-collapse<?= $openAddInternship ? ' is-open' : '' ?>" id="admin-add-internship-wrap" aria-hidden="<?= $openAddInternship ? 'false' : 'true' ?>">
        <div class="admin-add-collapse__inner">
            <h2 class="section-heading" id="heading-add-internship">Add internship</h2>
    <form class="form-save-internship admin-form" id="form-add-internship" method="post" action="<?= h(app_route('admin_internships.php')) ?>">
        <input type="hidden" name="action" value="save_internship">
        <input type="hidden" name="internship_id" value="0">
        <?= csrf_input() ?>
        <?= $rf ?>
        <fieldset class="fieldset" id="fieldset-add-internship">
            <div class="admin-field">
                <label class="admin-field__label" for="add_student_id">Student</label>
                <select class="input-nottingham" id="add_student_id" name="student_id" required>
                    <option value="">Select student</option>
                    <optgroup label="No placement yet">
                        <?php foreach ($studentRows as $st): ?>
                            <?php if ((int) $st['placement_count'] !== 0) {
                                continue;
                            } ?>
                            <option value="<?= h((string) $st['student_id']) ?>"><?= h((string) $st['student_id']) ?> — <?= h((string) $st['full_name']) ?></option>
                        <?php endforeach; ?>
                    </optgroup>
                    <optgroup label="With existing placements">
                        <?php foreach ($studentRows as $st): ?>
                            <?php if ((int) $st['placement_count'] === 0) {
                                continue;
                            } ?>
                            <option value="<?= h((string) $st['student_id']) ?>"><?= h((string) $st['student_id']) ?> — <?= h((string) $st['full_name']) ?> (<?= h((string) $st['placement_count']) ?>)</option>
                        <?php endforeach; ?>
                    </optgroup>
                </select>
            </div>
            <div class="admin-field company-combobox-wrap">
                <label class="admin-field__label" for="companySearch">Company</label>
                <div class="company-combobox">
                    <input type="hidden" name="company_id" id="hiddenCompanyId" value="" required>
                    <input
                        type="text"
                        id="companySearch"
                        class="form-control company-combobox__input"
                        placeholder="Type to search or create…"
                        autocomplete="off"
                        spellcheck="false"
                        aria-autocomplete="list"
                        aria-controls="companySuggestions"
                        aria-expanded="false"
                    >
                    <div
                        id="companySuggestions"
                        class="suggestions-dropdown"
                        style="display: none"
                        role="listbox"
                        aria-hidden="true"
                    ></div>
                </div>
            </div>
            <div class="admin-field">
                <label class="admin-field__label" for="add_assessor_id">Assessor</label>
                <select class="input-nottingham" id="add_assessor_id" name="assessor_id" required>
                    <option value="">Select assessor</option>
                    <?php foreach ($assessorRows as $a): ?>
                        <option value="<?= h((string) $a['user_id']) ?>"><?= h((string) $a['full_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="admin-field">
                <label class="admin-field__label" for="add_start_date">Start date</label>
                <input class="input-nottingham" id="add_start_date" name="start_date" type="text" required placeholder="YYYY-MM-DD">
            </div>
            <div class="admin-field">
                <label class="admin-field__label" for="add_end_date">End date</label>
                <input class="input-nottingham" id="add_end_date" name="end_date" type="text" placeholder="YYYY-MM-DD, or leave blank for ongoing">
            </div>
            <div class="admin-field">
                <label class="admin-field__label" for="add_status">Status</label>
                <select class="input-nottingham" id="add_status" name="status" required>
                    <option value="Ongoing">Ongoing</option>
                    <option value="Completed">Completed</option>
                </select>
            </div>
            <button class="btn-nottingham" type="submit" id="btn-add-internship">Create internship</button>
        </fieldset>
    </form>
        </div>
    </div>

    <h2 class="section-heading" id="heading-internship-list">All internships</h2>
    <div class="admin-internship-list admin-card-stagger" id="list-internships">
        <?php if (count($internshipRows) === 0): ?>
            <p class="admin-list-empty admin-anim-entrance">No internships found<?= $listQ !== '' ? ' for this search' : ' yet' ?>.</p>
        <?php else: ?>
        <?php foreach ($internshipRows as $row):
            $iid = (int) $row['internship_id'];
            $cardKey = 'int-' . (string) $iid;
            $panelId = 'panel-int-' . $cardKey;
            $statusRaw = (string) $row['status'];
            $statusPill = 'status-pill';
            if (strcasecmp($statusRaw, 'Ongoing') === 0) {
                $statusPill .= ' status-pill--ongoing';
            } elseif (strcasecmp($statusRaw, 'Completed') === 0) {
                $statusPill .= ' status-pill--completed';
            } else {
                $statusPill .= ' status-pill--neutral';
            }
            ?>
        <article class="admin-data-card admin-anim-entrance js-data-card" id="internship-card-<?= h($cardKey) ?>">
            <div class="admin-data-card__head">
                <div class="admin-data-card__title">
                    <span class="admin-data-card__id">Internship #<?= h((string) $iid) ?></span>
                    <div class="admin-data-card__name-row">
                        <h3 class="admin-data-card__name"><?= h((string) $row['student_name']) ?></h3>
                        <span class="<?= h($statusPill) ?>"><?= h($statusRaw) ?></span>
                    </div>
                </div>
                <div class="admin-data-card__actions">
                    <button class="btn-ghost-pill" type="button" data-toggle-panel="<?= h($panelId) ?>" aria-controls="<?= h($panelId) ?>" aria-expanded="false">Edit</button>
                </div>
            </div>
            <dl class="admin-dl">
                <dt>Student ID</dt>
                <dd><?= h((string) $row['student_id']) ?></dd>
                <dt>Company</dt>
                <dd><?= h((string) $row['company_name']) ?></dd>
                <dt>Assessor</dt>
                <dd><?= h((string) $row['assessor_name']) ?></dd>
                <dt>Dates</dt>
                <dd><?= h((string) $row['start_date']) ?><?= ($row['end_date'] !== null && (string) $row['end_date'] !== '') ? ' → ' . h((string) $row['end_date']) : ' → (ongoing)' ?></dd>
            </dl>
            <div class="admin-data-card__form js-data-card-panel" id="<?= h($panelId) ?>" hidden>
                <form class="form-save-internship admin-form" method="post" action="<?= h(app_route('admin_internships.php')) ?>">
                    <input type="hidden" name="action" value="save_internship">
                    <input type="hidden" name="internship_id" value="<?= h((string) $iid) ?>">
                    <?= csrf_input() ?>
                    <?= $rf ?>
                    <div class="admin-field">
                        <label class="admin-field__label" for="int_st_<?= h($cardKey) ?>">Student</label>
                        <select class="input-nottingham" id="int_st_<?= h($cardKey) ?>" name="student_id" required>
                            <?php foreach ($studentRows as $st): ?>
                                <option value="<?= h((string) $st['student_id']) ?>"<?= ((string) $row['student_id'] === (string) $st['student_id']) ? ' selected' : '' ?>>
                                    <?= h((string) $st['student_id']) ?> — <?= h((string) $st['full_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="admin-field company-combobox-wrap">
                        <label class="admin-field__label" for="companySearch-<?= h($cardKey) ?>">Company</label>
                        <div class="company-combobox">
                            <input
                                type="hidden"
                                name="company_id"
                                id="hiddenCompany-<?= h($cardKey) ?>"
                                value="<?= h((string) $row['company_id']) ?>"
                                required
                            >
                            <input
                                type="text"
                                id="companySearch-<?= h($cardKey) ?>"
                                class="form-control company-combobox__input"
                                value="<?= h((string) $row['company_name']) ?>"
                                placeholder="Type to search or create…"
                                autocomplete="off"
                                spellcheck="false"
                                aria-autocomplete="list"
                                aria-controls="companySuggestions-<?= h($cardKey) ?>"
                                aria-expanded="false"
                            >
                            <div
                                id="companySuggestions-<?= h($cardKey) ?>"
                                class="suggestions-dropdown"
                                style="display: none"
                                role="listbox"
                                aria-hidden="true"
                            ></div>
                        </div>
                    </div>
                    <div class="admin-field">
                        <label class="admin-field__label" for="int_as_<?= h($cardKey) ?>">Assessor</label>
                        <select class="input-nottingham" id="int_as_<?= h($cardKey) ?>" name="assessor_id" required>
                            <?php foreach ($assessorRows as $a): ?>
                                <option value="<?= h((string) $a['user_id']) ?>"<?= ((int) $row['assessor_id'] === (int) $a['user_id']) ? ' selected' : '' ?>>
                                    <?= h((string) $a['full_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="admin-field">
                        <label class="admin-field__label" for="int_sd_<?= h($cardKey) ?>">Start date</label>
                        <input class="input-nottingham" id="int_sd_<?= h($cardKey) ?>" name="start_date" type="text" required value="<?= h((string) $row['start_date']) ?>">
                    </div>
                    <div class="admin-field">
                        <label class="admin-field__label" for="int_ed_<?= h($cardKey) ?>">End date</label>
                        <input class="input-nottingham" id="int_ed_<?= h($cardKey) ?>" name="end_date" type="text" value="<?= h((string) ($row['end_date'] ?? '')) ?>" placeholder="Blank for ongoing">
                    </div>
                    <div class="admin-field">
                        <label class="admin-field__label" for="int_stt_<?= h($cardKey) ?>">Status</label>
                        <select class="input-nottingham" id="int_stt_<?= h($cardKey) ?>" name="status" required>
                            <option value="Ongoing"<?= ((string) $row['status'] === 'Ongoing') ? ' selected' : '' ?>>Ongoing</option>
                            <option value="Completed"<?= ((string) $row['status'] === 'Completed') ? ' selected' : '' ?>>Completed</option>
                        </select>
                    </div>
                    <button class="btn-nottingham" type="submit">Save internship</button>
                </form>
            </div>
        </article>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</section>

<?php
require_once __DIR__ . '/../../../includes/footer.php';
