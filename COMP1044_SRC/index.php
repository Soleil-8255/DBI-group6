<?php
declare(strict_types=1);

/**
 * Login entry + role routing (Admin / Assessor). Schema: `Users` table in COMP1044_database.sql.
 */

require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/functions.php';

ensure_session();

$errorMessage = '';
$formUsername = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usernameInput = trim((string) ($_POST['username'] ?? ''));
    $passwordInput = (string) ($_POST['password'] ?? '');
    $formUsername = $usernameInput;

    if ($usernameInput === '' || $passwordInput === '') {
        $errorMessage = 'Please enter both username and password.';
    } else {
        $sql = 'SELECT user_id, username, password_hash, role, full_name
                FROM `Users`
                WHERE username = :username
                LIMIT 1';

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':username' => $usernameInput]);
            $userRow = $stmt->fetch();
        } catch (PDOException $e) {
            app_log_exception('auth.login_query', $e);
            $errorMessage = app_public_error('db_read');
            $userRow = false;
        }

        if ($errorMessage === '' && $userRow && password_verify($passwordInput, (string) $userRow['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int) $userRow['user_id'];
            $_SESSION['username'] = (string) $userRow['username'];
            $_SESSION['role'] = (string) $userRow['role'];
            $_SESSION['full_name'] = (string) $userRow['full_name'];

            $role = $_SESSION['role'];
            if ($role === 'Admin') {
                header('Location: ' . app_route('admin_dashboard.php'));
                exit;
            }
            if ($role === 'Assessor') {
                header('Location: ' . app_route('assessor_dashboard.php'));
                exit;
            }
            if ($role === 'Student') {
                header('Location: ' . app_route('student_dashboard.php'));
                exit;
            }

            header('Location: ' . app_route('index.php'));
            exit;
        }

        if ($errorMessage === '') {
            $errorMessage = 'Invalid username or password.';
        }
    }
} elseif (current_user_id() !== null) {
    $role = current_user_role();
    if ($role === 'Admin') {
        header('Location: ' . app_route('admin_dashboard.php'));
        exit;
    }
    if ($role === 'Assessor') {
        header('Location: ' . app_route('assessor_dashboard.php'));
        exit;
    }
    if ($role === 'Student') {
        header('Location: ' . app_route('student_dashboard.php'));
        exit;
    }
}

/** When empty, use readonly until focus to reduce browser autofill on first paint */
$loginUsernameReadonly = ($formUsername === '');

$pageTitle = 'Login';
$appShell = 'login';
require_once __DIR__ . '/includes/header.php';
?>

<div class="login-editorial">
    <aside class="login-heritage" aria-hidden="true">
        <div class="login-heritage-inner">
            <p class="login-heritage-wordmark">University of Nottingham</p>
        </div>
    </aside>
    <section class="login-panel" aria-label="Sign in">
        <div class="login-panel-inner">
            <div class="login-eyebrow-wrap">
                <p class="login-eyebrow">Internship Result Portal</p>
            </div>
            <div class="login-floating-card">
            <?php if (current_user_role() === 'Student'): ?>
                <div class="login-welcome-heading">
                    <h2 class="login-welcome-title">Welcome Back, Scholar</h2>
                </div>
                <div class="notice-panel" id="student-login-notice">
                    <p class="lead lead-reset">You are signed in as a <strong>Student</strong>.</p>
                    <p class="notice-actions">
                        <a class="btn-login-submit" href="<?= h(app_route('student_dashboard.php')) ?>">Open my dashboard</a>
                        <a class="text-link text-link--login" href="<?= h(app_route('logout.php')) ?>">Sign out</a>
                    </p>
                </div>
            <?php else: ?>
                <div class="login-welcome-heading">
                    <h2 class="login-welcome-title">Welcome Back, Scholar</h2>
                </div>
                <p class="login-subtitle">Secure access for administrators, assessors, and students.</p>

                <?php if ($errorMessage !== ''): ?>
                    <div class="alert-error-login login-error-animate" id="login-error" role="alert">
                        <span class="alert-error-login__icon" aria-hidden="true">
                            <svg class="alert-error-login__svg" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="none">
                                <circle cx="10" cy="10" r="8.25" stroke="currentColor" stroke-width="1.5"/>
                                <path d="M10 6.25V11" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                <circle cx="10" cy="14.25" r="0.9" fill="currentColor"/>
                            </svg>
                        </span>
                        <span class="alert-error-login__text"><?= h($errorMessage) ?></span>
                    </div>
                <?php endif; ?>

                <form class="form-grid login-form" id="login-form" method="post" action="<?= h(app_route('index.php')) ?>" autocomplete="off" novalidate>
                    <div class="form-field">
                        <label class="form-label form-label--login" for="username">Username</label>
                        <input
                            class="form-input form-input--login-field"
                            id="username"
                            name="username"
                            type="text"
                            required
                            maxlength="50"
                            value="<?= h($formUsername) ?>"
                            autocomplete="off"
                            autocapitalize="none"
                            spellcheck="false"
                            inputmode="text"
                            <?= $loginUsernameReadonly ? 'readonly ' : '' ?>
                            onfocus="this.removeAttribute('readonly')"
                        >
                    </div>
                    <div class="form-field">
                        <label class="form-label form-label--login" for="password">Password</label>
                        <input
                            class="form-input form-input--login-field"
                            id="password"
                            name="password"
                            type="password"
                            required
                            maxlength="255"
                            autocomplete="off"
                            spellcheck="false"
                            readonly
                            onfocus="this.removeAttribute('readonly')"
                        >
                    </div>
                    <div class="form-actions">
                        <button class="btn-login-submit" type="submit" id="login-submit">Sign in</button>
                    </div>
                </form>
            <?php endif; ?>
            </div>
        </div>
    </section>
</div>

<?php
require_once __DIR__ . '/includes/footer.php';
