<?php
declare(strict_types=1);

/**
 * Admin — students + assessors CRUD (Section A(a), COMP1044).
 * Logic-first: POST actions, PDO prepared statements, transactions where needed.
 */

require_once __DIR__ . '/../../../includes/db_connect.php';
require_once __DIR__ . '/../../../includes/functions.php';

ensure_session();

if (($_SESSION['role'] ?? '') !== 'Admin') {
    header('Location: ' . app_route('index.php'));
    exit;
}

function manage_users_build_username(string $email, string $studentId): string
{
    $parts = explode('@', trim($email), 2);
    $local = strtolower($parts[0] ?? '');
    $candidate = preg_replace('/[^a-z0-9_]/', '_', $local);
    $candidate = trim((string) $candidate, '_');

    if ($candidate === '' || strlen($candidate) < 3) {
        $base = preg_replace('/[^a-z0-9]/', '', strtolower($studentId));
        $candidate = 'stud_' . ($base !== '' ? $base : 'user');
    }

    if (strlen($candidate) > 50) {
        $candidate = substr($candidate, 0, 50);
    }

    return $candidate;
}

/**
 * Build list URL (tab, sp, ap, q) for user management.
 */
function manage_users_list_url(array $overrides = []): string
{
    $o = array_merge(
        [
            'tab' => 'students',
            'sp' => 1,
            'ap' => 1,
            'q' => '',
        ],
        $overrides
    );
    $tab = ($o['tab'] ?? '') === 'assessors' ? 'assessors' : 'students';
    $sp = max(1, (int) ($o['sp'] ?? 1));
    $ap = max(1, (int) ($o['ap'] ?? 1));
    $q = trim((string) ($o['q'] ?? ''));
    $p = [
        'tab' => $tab,
        'sp' => $sp,
        'ap' => $ap,
    ];
    if ($q !== '') {
        $p['q'] = $q;
    }

    return app_route('admin_manage_users.php') . '?' . http_build_query($p);
}

/**
 * Preserves list context when a POST action fails (re-render with same tab/search/page).
 */
function manage_users_return_fields(string $listTab, string $listQ, int $sp, int $ap): string
{
    return '<input type="hidden" name="return_tab" value="' . h($listTab) . '">'
        . '<input type="hidden" name="return_q" value="' . h($listQ) . '">'
        . '<input type="hidden" name="return_sp" value="' . h((string) $sp) . '">'
        . '<input type="hidden" name="return_ap" value="' . h((string) $ap) . '">';
}

/**
 * Output SaaS-style pagination (students: varies sp; assessors: varies ap).
 *
 * @param 'students'|'assessors' $for
 */
function manage_users_paginate(
    string $for,
    int $curPage,
    int $totalPages,
    int $sp,
    int $ap,
    string $q,
    string $tab
): void {
    if ($totalPages < 1) {
        $totalPages = 1;
    }
    if ($curPage < 1) {
        $curPage = 1;
    }
    if ($curPage > $totalPages) {
        $curPage = $totalPages;
    }
    if ($for === 'students') {
        $uPrev = manage_users_list_url(['tab' => $tab, 'sp' => max(1, $curPage - 1), 'ap' => $ap, 'q' => $q]);
        $uNext = manage_users_list_url(['tab' => $tab, 'sp' => min($totalPages, $curPage + 1), 'ap' => $ap, 'q' => $q]);
    } else {
        $uPrev = manage_users_list_url(['tab' => $tab, 'sp' => $sp, 'ap' => max(1, $curPage - 1), 'q' => $q]);
        $uNext = manage_users_list_url(['tab' => $tab, 'sp' => $sp, 'ap' => min($totalPages, $curPage + 1), 'q' => $q]);
    }

    $window = 6;
    $start = max(1, (int) floor($curPage - $window / 2));
    $end = min($totalPages, $start + $window - 1);
    $start = max(1, $end - $window + 1);

    echo '<nav class="admin-pagination" aria-label="Pagination">';
    if ($curPage <= 1) {
        echo '<span class="admin-pagination__nav is-disabled" aria-disabled="true">Previous</span>';
    } else {
        echo '<a class="admin-pagination__nav" href="' . h($uPrev) . '">Previous</a>';
    }
    echo '<div class="admin-pagination__pages" role="list">';

    for ($i = $start; $i <= $end; $i += 1) {
        if ($for === 'students') {
            $u = manage_users_list_url(['tab' => $tab, 'sp' => $i, 'ap' => $ap, 'q' => $q]);
        } else {
            $u = manage_users_list_url(['tab' => $tab, 'sp' => $sp, 'ap' => $i, 'q' => $q]);
        }
        $isCur = $i === $curPage;
        if ($isCur) {
            echo '<span class="admin-pagination__page is-current" role="listitem" aria-current="page">' . h((string) $i) . '</span>';
        } else {
            echo '<a class="admin-pagination__page" role="listitem" href="' . h($u) . '">' . h((string) $i) . '</a>';
        }
    }

    echo '</div>';
    if ($curPage >= $totalPages) {
        echo '<span class="admin-pagination__nav is-disabled" aria-disabled="true">Next</span>';
    } else {
        echo '<a class="admin-pagination__nav" href="' . h($uNext) . '">Next</a>';
    }
    echo '</nav>';
}

$errorMessage = '';
$flashMessage = '';

if (isset($_SESSION['admin_manage_flash'])) {
    $flashMessage = (string) $_SESSION['admin_manage_flash'];
    unset($_SESSION['admin_manage_flash']);
}

$programmesBySchool = [];

try {
    $progJoinStmt = $pdo->prepare(
        'SELECT p.prog_id, p.prog_name, p.school_id, s.school_name
         FROM `Programmes` p
         INNER JOIN `Schools` s ON s.school_id = p.school_id
         ORDER BY s.school_name ASC, p.prog_name ASC'
    );
    $progJoinStmt->execute();
    $progRows = $progJoinStmt->fetchAll();

    foreach ($progRows as $row) {
        $schoolId = (int) $row['school_id'];
        if (!isset($programmesBySchool[$schoolId])) {
            $programmesBySchool[$schoolId] = [
                'school_name' => (string) $row['school_name'],
                'programmes' => [],
            ];
        }
        $programmesBySchool[$schoolId]['programmes'][] = $row;
    }

    uasort(
        $programmesBySchool,
        static function (array $a, array $b): int {
            return strcmp((string) $a['school_name'], (string) $b['school_name']);
        }
    );
} catch (PDOException $e) {
    app_log_exception('admin_manage_users.programme_preload', $e);
    $errorMessage = app_public_error('db_read');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token((string) ($_POST['csrf_token'] ?? ''))) {
        $errorMessage = app_public_error('csrf');
    }
    $action = (string) ($_POST['action'] ?? '');

    if ($errorMessage === '' && $action === 'add_student') {
        if ($errorMessage !== '') {
            // catalogue failed
        } else {
            $studentId = trim((string) ($_POST['student_id'] ?? ''));
            $fullName = trim((string) ($_POST['full_name'] ?? ''));
            $email = trim((string) ($_POST['email'] ?? ''));
            $progId = (int) ($_POST['prog_id'] ?? 0);
            $cohortRaw = trim((string) ($_POST['cohort_year'] ?? ''));

            if ($studentId === '' || $fullName === '' || $email === '' || $cohortRaw === '' || $progId <= 0) {
                $errorMessage = 'Student add: all fields required.';
            } elseif (strlen($studentId) > 20 || !preg_match('/^[A-Za-z0-9._-]+$/', $studentId)) {
                $errorMessage = 'Student add: invalid student ID.';
            } elseif (strlen($fullName) > 100 || strlen($email) > 100 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errorMessage = 'Student add: invalid name or email.';
            } else {
                $cohortYear = filter_var($cohortRaw, FILTER_VALIDATE_INT, [
                    'options' => ['min_range' => 1990, 'max_range' => 2100],
                ]);
                if ($cohortYear === false) {
                    $errorMessage = 'Student add: invalid cohort year.';
                }
            }

            if ($errorMessage === '') {
                try {
                    $vp = $pdo->prepare('SELECT 1 FROM `Programmes` WHERE prog_id = :p LIMIT 1');
                    $vp->execute([':p' => $progId]);
                    if (!$vp->fetch()) {
                        $errorMessage = 'Student add: invalid programme.';
                    }
                } catch (PDOException $e) {
                    app_log_exception('admin_manage_users.add_student_validate', $e);
                    $errorMessage = app_public_error('db_read');
                }
            }

            if ($errorMessage === '') {
                $passwordHash = password_hash('123123', PASSWORD_DEFAULT);
                $baseUsername = manage_users_build_username($email, $studentId);
                $username = $baseUsername;
                $usernameFree = false;
                try {
                    for ($s = 0; $s < 30; $s++) {
                        $chk = $pdo->prepare('SELECT 1 FROM `Users` WHERE username = :u LIMIT 1');
                        $chk->execute([':u' => $username]);
                        if (!$chk->fetch()) {
                            $usernameFree = true;
                            break;
                        }
                        $tag = '_' . (string) ($s + 1);
                        $username = substr($baseUsername, 0, max(1, 50 - strlen($tag))) . $tag;
                    }
                } catch (PDOException $e) {
                    app_log_exception('admin_manage_users.add_student_username', $e);
                    $errorMessage = app_public_error('db_read');
                }

                if ($errorMessage === '' && !$usernameFree) {
                    $errorMessage = 'Student add: could not allocate username.';
                }

                if ($errorMessage === '') {
                    try {
                        $pdo->beginTransaction();
                        $insU = $pdo->prepare(
                            'INSERT INTO `Users` (username, password_hash, role, full_name, email)
                             VALUES (:username, :ph, \'Student\', :fn, :em)'
                        );
                        $insU->execute([
                            ':username' => $username,
                            ':ph' => $passwordHash,
                            ':fn' => $fullName,
                            ':em' => $email,
                        ]);
                        $newUid = (int) $pdo->lastInsertId();
                        if ($newUid <= 0) {
                            throw new PDOException('No user_id after insert.');
                        }
                        $insS = $pdo->prepare(
                            'INSERT INTO `Students` (student_id, user_id, prog_id, cohort_year)
                             VALUES (:sid, :uid, :pid, :cy)'
                        );
                        $insS->execute([
                            ':sid' => $studentId,
                            ':uid' => $newUid,
                            ':pid' => $progId,
                            ':cy' => (string) $cohortYear,
                        ]);
                        $pdo->commit();
                        $_SESSION['admin_manage_flash'] = 'Student added (default password 123123).';
                        header('Location: ' . manage_users_list_url(['tab' => 'students', 'sp' => 1]));
                        exit;
                    } catch (PDOException $e) {
                        if ($pdo->inTransaction()) {
                            $pdo->rollBack();
                        }
                        app_log_exception('admin_manage_users.add_student_tx', $e);
                        $sqlState = (string) ($e->errorInfo[0] ?? '');
                        $mysqlCode = (int) ($e->errorInfo[1] ?? 0);
                        $errorMessage = ($sqlState === '23000' || $mysqlCode === 1062)
                            ? 'Student add: duplicate student ID, email, or username.'
                            : app_public_error('db_write');
                    }
                }
            }
        }
    } elseif ($errorMessage === '' && $action === 'update_student') {
        $studentId = trim((string) ($_POST['student_id'] ?? ''));
        $fullName = trim((string) ($_POST['full_name'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $progId = (int) ($_POST['prog_id'] ?? 0);
        $cohortRaw = trim((string) ($_POST['cohort_year'] ?? ''));

        if ($studentId === '' || $fullName === '' || $email === '' || $cohortRaw === '' || $progId <= 0) {
            $errorMessage = 'Student update: all fields required.';
        } elseif (strlen($fullName) > 100 || strlen($email) > 100 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errorMessage = 'Student update: invalid name or email.';
        } else {
            $cohortYear = filter_var($cohortRaw, FILTER_VALIDATE_INT, [
                'options' => ['min_range' => 1990, 'max_range' => 2100],
            ]);
            if ($cohortYear === false) {
                $errorMessage = 'Student update: invalid cohort year.';
            }
        }

        if ($errorMessage === '') {
            try {
                $pdo->beginTransaction();

                $rowStmt = $pdo->prepare(
                    'SELECT st.user_id FROM `Students` st WHERE st.student_id = :sid LIMIT 1'
                );
                $rowStmt->execute([':sid' => $studentId]);
                $userId = (int) ($rowStmt->fetchColumn() ?: 0);
                if ($userId <= 0) {
                    throw new PDOException('Student not found or missing user_id.');
                }

                $dup = $pdo->prepare(
                    'SELECT 1 FROM `Users` WHERE email = :em AND user_id <> :uid LIMIT 1'
                );
                $dup->execute([':em' => $email, ':uid' => $userId]);
                if ($dup->fetch()) {
                    throw new PDOException('Email already used by another account.');
                }

                $vp = $pdo->prepare('SELECT 1 FROM `Programmes` WHERE prog_id = :p LIMIT 1');
                $vp->execute([':p' => $progId]);
                if (!$vp->fetch()) {
                    throw new PDOException('Invalid programme.');
                }

                $updU = $pdo->prepare(
                    'UPDATE `Users` SET full_name = :fn, email = :em WHERE user_id = :uid AND role = \'Student\''
                );
                $updU->execute([':fn' => $fullName, ':em' => $email, ':uid' => $userId]);

                $updS = $pdo->prepare(
                    'UPDATE `Students` SET prog_id = :pid, cohort_year = :cy WHERE student_id = :sid'
                );
                $updS->execute([
                    ':pid' => $progId,
                    ':cy' => (string) $cohortYear,
                    ':sid' => $studentId,
                ]);

                $pdo->commit();
                $_SESSION['admin_manage_flash'] = 'Student updated.';
                header('Location: ' . manage_users_list_url(['tab' => 'students']));
                exit;
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                app_log_exception('admin_manage_users.update_student', $e);
                $errorMessage = app_public_error('db_write');
            }
        }
    } elseif ($errorMessage === '' && $action === 'delete_student') {
        $studentId = trim((string) ($_POST['student_id'] ?? ''));
        $confirm = trim((string) ($_POST['confirm_delete'] ?? ''));
        if ($studentId === '' || $confirm !== 'DELETE') {
            $errorMessage = 'Student delete: confirm by typing DELETE in the confirm field.';
        } else {
            try {
                $pdo->beginTransaction();
                $uidStmt = $pdo->prepare('SELECT user_id FROM `Students` WHERE student_id = :sid LIMIT 1');
                $uidStmt->execute([':sid' => $studentId]);
                $userId = $uidStmt->fetchColumn();
                if ($userId === false) {
                    throw new PDOException('Student not found.');
                }
                $userId = (int) $userId;

                $pdo->prepare('DELETE FROM `Internships` WHERE student_id = :sid')->execute([':sid' => $studentId]);
                $pdo->prepare('DELETE FROM `Students` WHERE student_id = :sid')->execute([':sid' => $studentId]);
                if ($userId > 0) {
                    $pdo->prepare('DELETE FROM `Users` WHERE user_id = :uid AND role = \'Student\'')->execute([':uid' => $userId]);
                }
                $pdo->commit();
                $_SESSION['admin_manage_flash'] = 'Student deleted.';
                header('Location: ' . manage_users_list_url(['tab' => 'students']));
                exit;
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                app_log_exception('admin_manage_users.delete_student', $e);
                $errorMessage = app_public_error('db_write');
            }
        }
    } elseif ($errorMessage === '' && $action === 'add_assessor') {
        $username = trim((string) ($_POST['username'] ?? ''));
        $fullName = trim((string) ($_POST['assessor_full_name'] ?? ''));
        $email = trim((string) ($_POST['assessor_email'] ?? ''));
        $pass = (string) ($_POST['assessor_password'] ?? '');
        if ($pass === '') {
            $pass = '123123';
        }

        if ($username === '' || $fullName === '' || $email === '') {
            $errorMessage = 'Assessor add: username, name, and email required.';
        } elseif (strlen($username) > 50 || !preg_match('/^[A-Za-z0-9._-]+$/', $username)) {
            $errorMessage = 'Assessor add: invalid username.';
        } elseif (strlen($fullName) > 100 || strlen($email) > 100 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errorMessage = 'Assessor add: invalid name or email.';
        } else {
            try {
                $ph = password_hash($pass, PASSWORD_DEFAULT);
                $ins = $pdo->prepare(
                    'INSERT INTO `Users` (username, password_hash, role, full_name, email)
                     VALUES (:u, :ph, \'Assessor\', :fn, :em)'
                );
                $ins->execute([':u' => $username, ':ph' => $ph, ':fn' => $fullName, ':em' => $email]);
                $_SESSION['admin_manage_flash'] = 'Assessor added.';
                header('Location: ' . manage_users_list_url(['tab' => 'assessors', 'ap' => 1]));
                exit;
            } catch (PDOException $e) {
                app_log_exception('admin_manage_users.add_assessor', $e);
                $sqlState = (string) ($e->errorInfo[0] ?? '');
                $mysqlCode = (int) ($e->errorInfo[1] ?? 0);
                $errorMessage = ($sqlState === '23000' || $mysqlCode === 1062)
                    ? 'Assessor add: duplicate username or email.'
                    : app_public_error('db_write');
            }
        }
    } elseif ($errorMessage === '' && $action === 'update_assessor') {
        $userId = (int) ($_POST['user_id'] ?? 0);
        $username = trim((string) ($_POST['username'] ?? ''));
        $fullName = trim((string) ($_POST['assessor_full_name'] ?? ''));
        $email = trim((string) ($_POST['assessor_email'] ?? ''));
        $newPass = (string) ($_POST['assessor_password'] ?? '');

        if ($userId <= 0 || $username === '' || $fullName === '' || $email === '') {
            $errorMessage = 'Assessor update: missing fields.';
        } elseif (strlen($username) > 50 || !preg_match('/^[A-Za-z0-9._-]+$/', $username)) {
            $errorMessage = 'Assessor update: invalid username.';
        } elseif (strlen($fullName) > 100 || strlen($email) > 100 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errorMessage = 'Assessor update: invalid name or email.';
        } else {
            try {
                $pdo->beginTransaction();

                $roleChk = $pdo->prepare('SELECT 1 FROM `Users` WHERE user_id = :id AND role = \'Assessor\' LIMIT 1');
                $roleChk->execute([':id' => $userId]);
                if (!$roleChk->fetch()) {
                    throw new PDOException('Not an assessor account.');
                }

                $dupU = $pdo->prepare(
                    'SELECT 1 FROM `Users` WHERE username = :un AND user_id <> :id LIMIT 1'
                );
                $dupU->execute([':un' => $username, ':id' => $userId]);
                if ($dupU->fetch()) {
                    throw new PDOException('Username taken.');
                }

                $dupE = $pdo->prepare(
                    'SELECT 1 FROM `Users` WHERE email = :em AND user_id <> :id LIMIT 1'
                );
                $dupE->execute([':em' => $email, ':id' => $userId]);
                if ($dupE->fetch()) {
                    throw new PDOException('Email taken.');
                }

                if ($newPass !== '') {
                    $ph = password_hash($newPass, PASSWORD_DEFAULT);
                    $upd = $pdo->prepare(
                        'UPDATE `Users` SET username = :un, full_name = :fn, email = :em, password_hash = :ph
                         WHERE user_id = :id AND role = \'Assessor\''
                    );
                    $upd->execute([
                        ':un' => $username,
                        ':fn' => $fullName,
                        ':em' => $email,
                        ':ph' => $ph,
                        ':id' => $userId,
                    ]);
                } else {
                    $upd = $pdo->prepare(
                        'UPDATE `Users` SET username = :un, full_name = :fn, email = :em
                         WHERE user_id = :id AND role = \'Assessor\''
                    );
                    $upd->execute([
                        ':un' => $username,
                        ':fn' => $fullName,
                        ':em' => $email,
                        ':id' => $userId,
                    ]);
                }

                $pdo->commit();
                $_SESSION['admin_manage_flash'] = 'Assessor updated.';
                header('Location: ' . manage_users_list_url(['tab' => 'assessors']));
                exit;
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                app_log_exception('admin_manage_users.update_assessor', $e);
                $errorMessage = app_public_error('db_write');
            }
        }
    } elseif ($errorMessage === '' && $action === 'delete_assessor') {
        $userId = (int) ($_POST['user_id'] ?? 0);
        $confirm = trim((string) ($_POST['confirm_delete'] ?? ''));
        if ($userId <= 0 || $confirm !== 'DELETE') {
            $errorMessage = 'Assessor delete: confirm with DELETE.';
        } else {
            try {
                $cnt = $pdo->prepare('SELECT COUNT(*) FROM `Internships` WHERE assessor_id = :id');
                $cnt->execute([':id' => $userId]);
                $n = (int) $cnt->fetchColumn();
                if ($n > 0) {
                    $errorMessage = 'Assessor delete: reassign or remove internships first (' . $n . ' row(s)).';
                } else {
                    $del = $pdo->prepare('DELETE FROM `Users` WHERE user_id = :id AND role = \'Assessor\'');
                    $del->execute([':id' => $userId]);
                    if ($del->rowCount() === 0) {
                        $errorMessage = 'Assessor delete: account not found.';
                    } else {
                        $_SESSION['admin_manage_flash'] = 'Assessor deleted.';
                        header('Location: ' . manage_users_list_url(['tab' => 'assessors']));
                        exit;
                    }
                }
            } catch (PDOException $e) {
                app_log_exception('admin_manage_users.delete_assessor', $e);
                $errorMessage = app_public_error('db_write');
            }
        }
    }
}

$studentRows = [];
$assessorRows = [];
$listTab = 'students';
$listQ = '';
$sp = 1;
$ap = 1;
$perPage = 10;
$totalStudents = 0;
$totalAssessors = 0;
$totalPagesS = 1;
$totalPagesA = 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $errorMessage !== '') {
    $listTab = (string) ($_POST['return_tab'] ?? 'students');
    if ($listTab !== 'assessors') {
        $listTab = 'students';
    }
    $listQ = trim((string) ($_POST['return_q'] ?? ''));
    $sp = max(1, (int) ($_POST['return_sp'] ?? 1));
    $ap = max(1, (int) ($_POST['return_ap'] ?? 1));
} else {
    $listTab = (string) ($_GET['tab'] ?? 'students');
    if ($listTab !== 'assessors') {
        $listTab = 'students';
    }
    $listQ = trim((string) ($_GET['q'] ?? ''));
    $sp = max(1, (int) ($_GET['sp'] ?? 1));
    $ap = max(1, (int) ($_GET['ap'] ?? 1));
}

$likeQ = $listQ === '' ? null : ('%' . $listQ . '%');
$studentFrom = 'FROM `Students` st
         INNER JOIN `Users` u ON u.user_id = st.user_id
         INNER JOIN `Programmes` p ON p.prog_id = st.prog_id
         INNER JOIN `Schools` sch ON sch.school_id = p.school_id';
$studentSelect = 'SELECT st.student_id, st.user_id, st.prog_id, st.cohort_year, u.full_name, u.email, p.prog_name, sch.school_name
         ' . $studentFrom;

try {
    if ($likeQ === null) {
        $totalStudents = (int) $pdo->query("SELECT COUNT(*) $studentFrom")->fetchColumn();
    } else {
        $cS = $pdo->prepare("SELECT COUNT(*) $studentFrom WHERE (st.student_id LIKE :l1 OR u.full_name LIKE :l2)");
        $cS->bindValue(':l1', $likeQ, PDO::PARAM_STR);
        $cS->bindValue(':l2', $likeQ, PDO::PARAM_STR);
        $cS->execute();
        $totalStudents = (int) $cS->fetchColumn();
    }

    $totalPagesS = max(1, (int) ceil($totalStudents / $perPage));
    if ($sp > $totalPagesS) {
        $sp = $totalPagesS;
    }
    $offS = ($sp - 1) * $perPage;

    if ($likeQ === null) {
        $listS = $pdo->prepare(
            $studentSelect . ' ORDER BY st.student_id ASC LIMIT :lim OFFSET :off'
        );
    } else {
        $listS = $pdo->prepare(
            $studentSelect
                . ' WHERE (st.student_id LIKE :l1 OR u.full_name LIKE :l2) ORDER BY st.student_id ASC LIMIT :lim OFFSET :off'
        );
        $listS->bindValue(':l1', $likeQ, PDO::PARAM_STR);
        $listS->bindValue(':l2', $likeQ, PDO::PARAM_STR);
    }
    $listS->bindValue(':lim', $perPage, PDO::PARAM_INT);
    $listS->bindValue(':off', $offS, PDO::PARAM_INT);
    $listS->execute();
    $studentRows = $listS->fetchAll();

    if ($likeQ === null) {
        $totalAssessors = (int) $pdo->query(
            "SELECT COUNT(*) FROM `Users` WHERE role = 'Assessor'"
        )->fetchColumn();
    } else {
        $cA = $pdo->prepare(
            "SELECT COUNT(*) FROM `Users` WHERE role = 'Assessor'
             AND (username LIKE :la OR full_name LIKE :lb OR email LIKE :lc)"
        );
        $cA->bindValue(':la', $likeQ, PDO::PARAM_STR);
        $cA->bindValue(':lb', $likeQ, PDO::PARAM_STR);
        $cA->bindValue(':lc', $likeQ, PDO::PARAM_STR);
        $cA->execute();
        $totalAssessors = (int) $cA->fetchColumn();
    }

    $totalPagesA = max(1, (int) ceil($totalAssessors / $perPage));
    if ($ap > $totalPagesA) {
        $ap = $totalPagesA;
    }
    $offA = ($ap - 1) * $perPage;

    if ($likeQ === null) {
        $listA = $pdo->prepare(
            'SELECT user_id, username, full_name, email FROM `Users` WHERE role = \'Assessor\''
                . ' ORDER BY full_name ASC LIMIT :lim OFFSET :off'
        );
    } else {
        $listA = $pdo->prepare(
            'SELECT user_id, username, full_name, email FROM `Users` WHERE role = \'Assessor\''
                . ' AND (username LIKE :la OR full_name LIKE :lb OR email LIKE :lc) ORDER BY full_name ASC LIMIT :lim OFFSET :off'
        );
        $listA->bindValue(':la', $likeQ, PDO::PARAM_STR);
        $listA->bindValue(':lb', $likeQ, PDO::PARAM_STR);
        $listA->bindValue(':lc', $likeQ, PDO::PARAM_STR);
    }
    $listA->bindValue(':lim', $perPage, PDO::PARAM_INT);
    $listA->bindValue(':off', $offA, PDO::PARAM_INT);
    $listA->execute();
    $assessorRows = $listA->fetchAll();
} catch (PDOException $e) {
    app_log_exception('admin_manage_users.list_load', $e);
    if ($errorMessage === '') {
        $errorMessage = app_public_error('db_read');
    }
}

$openAddStudent = $errorMessage !== '' && $_SERVER['REQUEST_METHOD'] === 'POST'
    && (string) ($_POST['action'] ?? '') === 'add_student';
$openAddAssessor = $errorMessage !== '' && $_SERVER['REQUEST_METHOD'] === 'POST'
    && (string) ($_POST['action'] ?? '') === 'add_assessor';
$rf = manage_users_return_fields($listTab, $listQ, $sp, $ap);

$pageTitle = 'Manage users';
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
    class="card admin-manage-users"
    id="admin-manage-users"
    data-initial-tab="<?= h($listTab) ?>"
    data-import-api="<?= h(app_route('api_import_students.php')) ?>"
    data-assessor-import-api="<?= h(app_route('api_import_assessors.php')) ?>"
    data-csrf="<?= h(csrf_token()) ?>"
    data-template-csv="<?= h(asset_url('assets/csv/student_import_template.csv')) ?>"
    data-template-csv-assessors="<?= h(asset_url('assets/csv/assessor_import_template.csv')) ?>"
    aria-labelledby="admin-user-lists-heading"
>
    <h2 class="visually-hidden" id="admin-user-lists-heading">User lists</h2>
    <div class="admin-tabs" role="tablist" aria-label="User type">
        <button type="button" class="admin-tab<?= $listTab === 'students' ? ' is-active' : '' ?>" role="tab" id="tab-students" aria-controls="panel-students" aria-selected="<?= $listTab === 'students' ? 'true' : 'false' ?>" tabindex="<?= $listTab === 'students' ? '0' : '-1' ?>">Students</button>
        <button type="button" class="admin-tab<?= $listTab === 'assessors' ? ' is-active' : '' ?>" role="tab" id="tab-assessors" aria-controls="panel-assessors" aria-selected="<?= $listTab === 'assessors' ? 'true' : 'false' ?>" tabindex="<?= $listTab === 'assessors' ? '0' : '-1' ?>">Assessors</button>
    </div>
    <div id="panel-students" class="admin-tabpanel" role="tabpanel" aria-labelledby="tab-students"<?= $listTab === 'assessors' ? ' hidden' : '' ?>>
        <div class="admin-manage-users__toolbar">
            <form method="get" class="admin-manage-search" action="<?= h(app_route('admin_manage_users.php')) ?>">
                <input type="hidden" name="tab" value="students">
                <input type="hidden" name="sp" value="1">
                <input type="hidden" name="ap" value="<?= (int) $ap ?>">
                <input type="search" name="q" class="input-nottingham admin-manage-search__input" id="q-students" placeholder="Search by Name or ID..." value="<?= h($listQ) ?>" aria-label="Search students by name or ID" autocomplete="off">
            </form>
            <div class="admin-manage-users__actions">
                <button type="button" class="btn-outline" id="btnOpenImport">↓ Import CSV</button>
                <button type="button" class="btn-nottingham admin-btn-add" id="btn-toggle-add-student" aria-expanded="<?= $openAddStudent ? 'true' : 'false' ?>" aria-controls="admin-add-student-wrap">
                    <svg class="admin-btn-add__icon" width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M19 11h-6V5h-2v6H5v2h6v6h2v-6h6v-2z"/></svg>
                    <span>Add Student</span>
                </button>
            </div>
        </div>
        <div class="admin-add-collapse<?= $openAddStudent ? ' is-open' : '' ?>" id="admin-add-student-wrap" aria-hidden="<?= $openAddStudent ? 'false' : 'true' ?>">
            <div class="admin-add-collapse__inner">
                <h2 class="section-heading" id="heading-add-student">Add student</h2>
                <form class="form-add-student admin-form" id="form-add-student" method="post" action="<?= h(app_route('admin_manage_users.php')) ?>">
                    <input type="hidden" name="action" value="add_student">
                    <?= csrf_input() ?>
                    <?= $rf ?>
                    <fieldset class="fieldset" id="fieldset-add-student">
            <div class="admin-field" id="field-student-id">
                <label class="admin-field__label" for="add_student_id">Student ID</label>
                <input class="input-nottingham" id="add_student_id" name="student_id" type="text" maxlength="20" required>
            </div>
            <div class="admin-field" id="field-full-name">
                <label class="admin-field__label" for="add_full_name">Full name</label>
                <input class="input-nottingham" id="add_full_name" name="full_name" type="text" maxlength="100" required>
            </div>
            <div class="admin-field" id="field-email">
                <label class="admin-field__label" for="add_email">Email</label>
                <input class="input-nottingham" id="add_email" name="email" type="email" maxlength="100" required>
            </div>
            <div class="admin-field" id="field-cohort">
                <label class="admin-field__label" for="add_cohort">Cohort year</label>
                <input class="input-nottingham" id="add_cohort" name="cohort_year" type="number" min="1990" max="2100" required>
            </div>
            <div class="admin-field" id="field-prog">
                <label class="admin-field__label" for="add_prog_id">Programme</label>
                <select class="input-nottingham" id="add_prog_id" name="prog_id" required>
                    <option value="">Select programme</option>
                    <?php foreach ($programmesBySchool as $group): ?>
                        <optgroup label="<?= h((string) $group['school_name']) ?>">
                            <?php foreach ($group['programmes'] as $p): ?>
                                <option value="<?= h((string) $p['prog_id']) ?>"><?= h((string) $p['prog_name']) ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endforeach; ?>
                </select>
            </div>
            <button class="btn-nottingham" type="submit" id="btn-add-student">Add student</button>
        </fieldset>
    </form>
            </div>
        </div>

    <h2 class="section-heading" id="heading-student-list">Students</h2>
    <div class="admin-user-list admin-card-stagger" id="list-students">
        <?php foreach ($studentRows as $sr):
            $sid = (string) $sr['student_id'];
            $cardKey = 'st-' . preg_replace('/[^A-Za-z0-9]+/', '-', $sid);
            $panelEdit = 'panel-edit-' . $cardKey;
            $panelDel = 'panel-del-' . $cardKey;
            ?>
        <article class="admin-data-card admin-anim-entrance js-data-card" id="student-card-<?= h($cardKey) ?>" data-entity="student" data-student-id="<?= h($sid) ?>">
            <div class="admin-data-card__head">
                <div class="admin-data-card__title">
                    <span class="admin-data-card__id"><?= h($sid) ?></span>
                    <h3 class="admin-data-card__name"><?= h((string) $sr['full_name']) ?></h3>
                </div>
                <div class="admin-data-card__actions">
                    <button class="btn-ghost-pill" type="button" data-toggle-panel="<?= h($panelEdit) ?>" aria-controls="<?= h($panelEdit) ?>" aria-expanded="false">Edit</button>
                    <button class="btn-ghost-pill btn-ghost-pill--danger" type="button" data-toggle-panel="<?= h($panelDel) ?>" aria-controls="<?= h($panelDel) ?>" aria-expanded="false">Delete</button>
                </div>
            </div>
            <dl class="admin-dl">
                <dt>Email</dt>
                <dd><?= h((string) $sr['email']) ?></dd>
                <dt>Programme</dt>
                <dd><?= h((string) $sr['prog_name']) ?></dd>
                <dt>School</dt>
                <dd><?= h((string) $sr['school_name']) ?></dd>
                <dt>Cohort</dt>
                <dd><?= h((string) $sr['cohort_year']) ?></dd>
            </dl>
            <div class="admin-data-card__form js-data-card-panel" id="<?= h($panelEdit) ?>" hidden>
                <form class="form-update-student admin-form" method="post" action="<?= h(app_route('admin_manage_users.php')) ?>" id="form-upd-<?= h($sid) ?>">
                    <input type="hidden" name="action" value="update_student">
                    <input type="hidden" name="student_id" value="<?= h($sid) ?>">
                    <?= csrf_input() ?>
                    <?= $rf ?>
                    <div class="admin-field">
                        <label class="admin-field__label" for="upd_name_<?= h($cardKey) ?>">Full name</label>
                        <input class="input-nottingham" id="upd_name_<?= h($cardKey) ?>" name="full_name" type="text" value="<?= h((string) $sr['full_name']) ?>" maxlength="100" required>
                    </div>
                    <div class="admin-field">
                        <label class="admin-field__label" for="upd_email_<?= h($cardKey) ?>">Email</label>
                        <input class="input-nottingham" id="upd_email_<?= h($cardKey) ?>" name="email" type="email" value="<?= h((string) $sr['email']) ?>" maxlength="100" required>
                    </div>
                    <div class="admin-field">
                        <label class="admin-field__label" for="upd_prog_<?= h($cardKey) ?>">Programme</label>
                        <select class="input-nottingham" id="upd_prog_<?= h($cardKey) ?>" name="prog_id" required>
                            <?php foreach ($programmesBySchool as $group): ?>
                                <optgroup label="<?= h((string) $group['school_name']) ?>">
                                    <?php foreach ($group['programmes'] as $p): ?>
                                        <option value="<?= h((string) $p['prog_id']) ?>"<?= ((int) $sr['prog_id'] === (int) $p['prog_id']) ? ' selected' : '' ?>><?= h((string) $p['prog_name']) ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="admin-field">
                        <label class="admin-field__label" for="upd_cohort_<?= h($cardKey) ?>">Cohort year</label>
                        <input class="input-nottingham" id="upd_cohort_<?= h($cardKey) ?>" name="cohort_year" type="number" min="1990" max="2100" value="<?= h((string) $sr['cohort_year']) ?>" required>
                    </div>
                    <button class="btn-nottingham" type="submit">Save changes</button>
                </form>
            </div>
            <div class="admin-data-card__form js-data-card-panel" id="<?= h($panelDel) ?>" hidden>
                <p class="admin-form-hint">Type <strong>DELETE</strong> to remove this record permanently.</p>
                <form class="form-delete-student admin-form js-confirm-delete" method="post" action="<?= h(app_route('admin_manage_users.php')) ?>" id="form-del-<?= h($sid) ?>" data-confirm-message="Delete this student record? This action cannot be undone.">
                    <input type="hidden" name="action" value="delete_student">
                    <input type="hidden" name="student_id" value="<?= h($sid) ?>">
                    <?= csrf_input() ?>
                    <?= $rf ?>
                    <div class="admin-field">
                        <label class="admin-field__label" for="del_conf_<?= h($cardKey) ?>">Confirmation</label>
                        <input class="input-nottingham" id="del_conf_<?= h($cardKey) ?>" name="confirm_delete" type="text" placeholder="DELETE" autocomplete="off" required>
                    </div>
                    <button class="btn-ghost-danger" type="submit">Delete student</button>
                </form>
            </div>
        </article>
        <?php endforeach; ?>
    </div>
    <?php manage_users_paginate('students', $sp, $totalPagesS, $sp, $ap, $listQ, $listTab); ?>
    </div>

    <div id="panel-assessors" class="admin-tabpanel" role="tabpanel" aria-labelledby="tab-assessors"<?= $listTab === 'students' ? ' hidden' : '' ?>>
        <div class="admin-manage-users__toolbar">
            <form method="get" class="admin-manage-search" action="<?= h(app_route('admin_manage_users.php')) ?>">
                <input type="hidden" name="tab" value="assessors">
                <input type="hidden" name="ap" value="1">
                <input type="hidden" name="sp" value="<?= (int) $sp ?>">
                <input type="search" name="q" class="input-nottingham admin-manage-search__input" id="q-assessors" placeholder="Search by Name or ID..." value="<?= h($listQ) ?>" aria-label="Search assessors" autocomplete="off">
            </form>
            <div class="admin-manage-users__actions">
                <button type="button" class="btn-outline" id="btnOpenImportAssessors">↓ Import CSV</button>
                <button type="button" class="btn-nottingham admin-btn-add" id="btn-toggle-add-assessor" aria-expanded="<?= $openAddAssessor ? 'true' : 'false' ?>" aria-controls="admin-add-assessor-wrap">
                    <svg class="admin-btn-add__icon" width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M19 11h-6V5h-2v6H5v2h6v6h2v-6h6v-2z"/></svg>
                    <span>Add Assessor</span>
                </button>
            </div>
        </div>
        <div class="admin-add-collapse<?= $openAddAssessor ? ' is-open' : '' ?>" id="admin-add-assessor-wrap" aria-hidden="<?= $openAddAssessor ? 'false' : 'true' ?>">
            <div class="admin-add-collapse__inner">
                <h2 class="section-heading" id="heading-add-assessor">Add assessor</h2>
                <form class="form-add-assessor admin-form" id="form-add-assessor" method="post" action="<?= h(app_route('admin_manage_users.php')) ?>">
                    <input type="hidden" name="action" value="add_assessor">
                    <?= csrf_input() ?>
                    <?= $rf ?>
                    <fieldset class="fieldset" id="fieldset-add-assessor">
            <div class="admin-field">
                <label class="admin-field__label" for="add_assessor_username">Username</label>
                <input class="input-nottingham" id="add_assessor_username" name="username" type="text" maxlength="50" required>
            </div>
            <div class="admin-field">
                <label class="admin-field__label" for="add_assessor_full_name">Full name</label>
                <input class="input-nottingham" id="add_assessor_full_name" name="assessor_full_name" type="text" maxlength="100" required>
            </div>
            <div class="admin-field">
                <label class="admin-field__label" for="add_assessor_email">Email</label>
                <input class="input-nottingham" id="add_assessor_email" name="assessor_email" type="email" maxlength="100" required>
            </div>
            <div class="admin-field">
                <label class="admin-field__label" for="add_assessor_password">Password <span class="admin-label-optional">(optional)</span></label>
                <input class="input-nottingham" id="add_assessor_password" name="assessor_password" type="password" maxlength="255" placeholder="Default: 123123">
            </div>
            <button class="btn-nottingham" type="submit" id="btn-add-assessor">Add assessor</button>
        </fieldset>
    </form>
            </div>
        </div>

    <h2 class="section-heading" id="heading-assessor-list">Assessors</h2>
    <div class="admin-user-list admin-card-stagger" id="list-assessors">
        <?php foreach ($assessorRows as $ar):
            $uid = (int) $ar['user_id'];
            $cardKey = 'as-' . (string) $uid;
            $panelEdit = 'panel-a-edit-' . $cardKey;
            $panelDel = 'panel-a-del-' . $cardKey;
            ?>
        <article class="admin-data-card admin-anim-entrance js-data-card" id="assessor-card-<?= h($cardKey) ?>">
            <div class="admin-data-card__head">
                <div class="admin-data-card__title">
                    <span class="admin-data-card__id">User #<?= h((string) $uid) ?></span>
                    <h3 class="admin-data-card__name"><?= h((string) $ar['full_name']) ?></h3>
                </div>
                <div class="admin-data-card__actions">
                    <button class="btn-ghost-pill" type="button" data-toggle-panel="<?= h($panelEdit) ?>" aria-controls="<?= h($panelEdit) ?>" aria-expanded="false">Edit</button>
                    <button class="btn-ghost-pill btn-ghost-pill--danger" type="button" data-toggle-panel="<?= h($panelDel) ?>" aria-controls="<?= h($panelDel) ?>" aria-expanded="false">Delete</button>
                </div>
            </div>
            <dl class="admin-dl">
                <dt>Username</dt>
                <dd><?= h((string) $ar['username']) ?></dd>
                <dt>Email</dt>
                <dd><?= h((string) $ar['email']) ?></dd>
            </dl>
            <div class="admin-data-card__form js-data-card-panel" id="<?= h($panelEdit) ?>" hidden>
                <form class="form-update-assessor admin-form" method="post" action="<?= h(app_route('admin_manage_users.php')) ?>">
                    <input type="hidden" name="action" value="update_assessor">
                    <input type="hidden" name="user_id" value="<?= h((string) $uid) ?>">
                    <?= csrf_input() ?>
                    <?= $rf ?>
                    <div class="admin-field">
                        <label class="admin-field__label" for="a_un_<?= h($cardKey) ?>">Username</label>
                        <input class="input-nottingham" id="a_un_<?= h($cardKey) ?>" name="username" value="<?= h((string) $ar['username']) ?>" maxlength="50" required>
                    </div>
                    <div class="admin-field">
                        <label class="admin-field__label" for="a_fn_<?= h($cardKey) ?>">Full name</label>
                        <input class="input-nottingham" id="a_fn_<?= h($cardKey) ?>" name="assessor_full_name" value="<?= h((string) $ar['full_name']) ?>" maxlength="100" required>
                    </div>
                    <div class="admin-field">
                        <label class="admin-field__label" for="a_em_<?= h($cardKey) ?>">Email</label>
                        <input class="input-nottingham" id="a_em_<?= h($cardKey) ?>" name="assessor_email" type="email" value="<?= h((string) $ar['email']) ?>" maxlength="100" required>
                    </div>
                    <div class="admin-field">
                        <label class="admin-field__label" for="a_pw_<?= h($cardKey) ?>">New password <span class="admin-label-optional">(optional)</span></label>
                        <input class="input-nottingham" id="a_pw_<?= h($cardKey) ?>" name="assessor_password" type="password" maxlength="255" placeholder="Leave blank to keep current">
                    </div>
                    <button class="btn-nottingham" type="submit">Save changes</button>
                </form>
            </div>
            <div class="admin-data-card__form js-data-card-panel" id="<?= h($panelDel) ?>" hidden>
                <p class="admin-form-hint">Type <strong>DELETE</strong> to remove this account.</p>
                <form class="form-delete-assessor admin-form js-confirm-delete" method="post" action="<?= h(app_route('admin_manage_users.php')) ?>" data-confirm-message="Delete this assessor account? This action cannot be undone.">
                    <input type="hidden" name="action" value="delete_assessor">
                    <input type="hidden" name="user_id" value="<?= h((string) $uid) ?>">
                    <?= csrf_input() ?>
                    <?= $rf ?>
                    <div class="admin-field">
                        <label class="admin-field__label" for="a_del_<?= h($cardKey) ?>">Confirmation</label>
                        <input class="input-nottingham" id="a_del_<?= h($cardKey) ?>" name="confirm_delete" type="text" placeholder="DELETE" autocomplete="off" required>
                    </div>
                    <button class="btn-ghost-danger" type="submit">Delete assessor</button>
                </form>
            </div>
        </article>
        <?php endforeach; ?>
    </div>
    <?php manage_users_paginate('assessors', $ap, $totalPagesA, $sp, $ap, $listQ, $listTab); ?>
    </div>
</section>

<div id="csvImportModal" class="csv-import-modal" hidden>
    <div class="csv-import-modal__backdrop" data-csv-modal-dismiss tabindex="-1" aria-hidden="true"></div>
    <div
        class="csv-import-modal__card"
        role="dialog"
        aria-modal="true"
        aria-labelledby="csv-import-title"
        data-csv-modal-card
    >
        <div class="csv-import-modal__head">
            <h2 class="csv-import-modal__title" id="csv-import-title">Bulk Import Students</h2>
            <button type="button" class="csv-import-modal__close" data-csv-modal-dismiss aria-label="Close">
                <span aria-hidden="true">×</span>
            </button>
        </div>
        <p class="csv-import-modal__error" id="csvImportError" role="alert" hidden></p>
        <div
            class="drop-zone"
            id="csvDropZone"
            tabindex="0"
            role="button"
            aria-label="Drop zone: drag a CSV file here or press Enter to browse"
        >
            <p class="drop-zone__text" id="csvDropText">Drag &amp; drop a CSV file here, or click to browse</p>
            <p class="drop-zone__loading" id="csvDropLoading" hidden>Uploading…</p>
        </div>
        <input type="file" id="csvFileInput" name="csv" accept=".csv,text/csv" hidden>
        <div class="csv-import-modal__footer">
            <a class="csv-import-modal__template-link" id="csvTemplateLink" href="<?= h(asset_url('assets/csv/student_import_template.csv')) ?>" download>Download CSV Template</a>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../../../includes/footer.php';
