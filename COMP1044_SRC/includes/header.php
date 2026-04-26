<?php
declare(strict_types=1);

/**
 * Global layout: HTML head, asset tags, and shell (editorial login vs floating dashboard).
 * Prerequisite: ensure_session() must be called before this include.
 */

if (!isset($pageTitle)) {
    $pageTitle = 'Internship Result Management System';
}

$appShell = $appShell ?? 'default';
$adminDashboardGreeting = $adminDashboardGreeting ?? false;
$role = current_user_role();
$displayName = isset($_SESSION['full_name']) ? (string) $_SESSION['full_name'] : '';
$useLoginShell = ($appShell === 'login');
$useDashboardShell = ($role !== null && !$useLoginShell);
$bodyClass = 'app-body';
if ($useLoginShell) {
    $bodyClass .= ' app-body--login';
} elseif ($useDashboardShell) {
    $bodyClass .= ' app-body--dashboard';
}

$dashboardHome = match ($role) {
    'Admin' => app_route('admin_dashboard.php'),
    'Assessor' => app_route('assessor_dashboard.php'),
    'Student' => app_route('student_dashboard.php'),
    default => app_route('index.php'),
};

$scriptBasename = strtolower(basename(str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''))));
$assessorEvaluateNavActive = ($role === 'Assessor' && $scriptBasename === 'assessor_evaluate.php') ? ' nav-link--active' : '';
$sessionUserId = ($role !== null && current_user_id() !== null) ? (string) current_user_id() : '';
$bodyClassExtra = $bodyClassExtra ?? '';
$pageSubtitle = $pageSubtitle ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($pageTitle) ?> — UNM</title>
    <link rel="icon" type="image/png" sizes="32x32" href="<?= h(asset_v('assets/images/favicon-32.png')) ?>">
    <link rel="icon" type="image/png" sizes="16x16" href="<?= h(asset_v('assets/images/favicon-16.png')) ?>">
    <link rel="stylesheet" href="<?= h(asset_v('assets/css/style.css')) ?>">
    <?php /* JS order: utils (window.Irms) -> app (global UI) -> page/feature modules (safe no-op if DOM missing) */ ?>
    <script src="<?= h(asset_url('assets/js/core/utils.js')) ?>" defer></script>
    <script src="<?= h(asset_url('assets/js/core/app.js')) ?>" defer></script>
    <script src="<?= h(asset_url('assets/js/modules/student-radar.js')) ?>" defer></script>
    <script src="<?= h(asset_url('assets/js/modules/student-dashboard.js')) ?>" defer></script>
    <script src="<?= h(asset_url('assets/js/modules/innovations.js')) ?>" defer></script>
    <script src="<?= h(asset_url('assets/js/modules/admin-manage-users.js')) ?>" defer></script>
    <script src="<?= h(asset_url('assets/js/modules/admin-internships.js')) ?>" defer></script>
    <script src="<?= h(asset_url('assets/js/modules/admin-alerts.js')) ?>" defer></script>
</head>
<body
    class="<?= h($bodyClass . $bodyClassExtra) ?>"
    <?php if ($sessionUserId !== ''): ?>data-session-user-id="<?= h($sessionUserId) ?>"<?php endif; ?>
>
<?php if ($useLoginShell): ?>
<?php elseif ($useDashboardShell): ?>
<div class="dashboard-shell">
    <header class="app-header" role="banner">
        <div class="app-header__inner">
            <div class="app-header__left">
                <a class="app-header__logo-link" href="<?= h($dashboardHome) ?>" aria-label="Dashboard home" title="Home">
                    <img
                        class="app-header__logo"
                        src="<?= h(asset_url('assets/images/uon-top-left-logo.png')) ?>"
                        alt="University of Nottingham Malaysia"
                        width="160"
                        height="36"
                    >
                </a>
                <button
                    type="button"
                    class="app-header__menu-btn"
                    id="sidebar-toggle"
                    aria-label="Toggle sidebar"
                    aria-controls="app-sidebar"
                    aria-expanded="true"
                >
                    <svg class="app-header__menu-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                        <line x1="3" y1="6" x2="21" y2="6" />
                        <line x1="3" y1="12" x2="21" y2="12" />
                        <line x1="3" y1="18" x2="21" y2="18" />
                    </svg>
                </button>
                <div class="header-dynamic-title" aria-hidden="true"><?= h($pageTitle) ?></div>
            </div>
            <div class="app-header__right">
                <div class="dashboard-user-menu" data-dashboard-user-menu>
                    <button
                        type="button"
                        class="dashboard-user-menu__trigger"
                        id="user-menu-trigger"
                        aria-haspopup="true"
                        aria-expanded="false"
                        aria-controls="user-menu-panel"
                    >
                        <span class="dashboard-user-avatar" aria-hidden="true"><?= h(user_avatar_initials($displayName)) ?></span>
                    </button>
                    <div
                        class="dashboard-user-menu__panel"
                        id="user-menu-panel"
                        role="menu"
                        aria-hidden="true"
                    >
                        <?php if ($displayName !== ''): ?>
                        <p class="dashboard-user-menu__name" role="none"><?= h($displayName) ?></p>
                        <?php endif; ?>
                        <a class="dashboard-user-menu__item" href="<?= h(app_route('logout.php')) ?>" role="menuitem">Logout</a>
                    </div>
                </div>
            </div>
        </div>
    </header>
    <div class="dashboard-app-body">
    <aside class="sidebar-island" id="app-sidebar" aria-label="Primary navigation">
        <nav class="sidebar-island-nav">
            <?php if ($role === 'Admin'): ?>
                <a class="nav-link<?= nav_active_class('admin_dashboard.php') ?>" href="<?= h(app_route('admin_dashboard.php')) ?>" title="Dashboard"><?= nav_svg_icon('dashboard') ?><span class="nav-link__text">Dashboard</span></a>
                <a class="nav-link<?= nav_active_class('admin_manage_users.php') ?>" href="<?= h(app_route('admin_manage_users.php')) ?>" title="Users"><?= nav_svg_icon('users') ?><span class="nav-link__text">Users</span></a>
                <a class="nav-link<?= nav_active_class('admin_internships.php') ?>" href="<?= h(app_route('admin_internships.php')) ?>" title="Internships"><?= nav_svg_icon('briefcase') ?><span class="nav-link__text">Internships</span></a>
                <a class="nav-link<?= nav_active_class('admin_results.php') ?>" href="<?= h(app_route('admin_results.php')) ?>" title="Results"><?= nav_svg_icon('document') ?><span class="nav-link__text">Results</span></a>
                <a class="nav-link<?= nav_active_class('admin_workload.php') ?>" href="<?= h(app_route('admin_workload.php')) ?>" title="Workload"><?= nav_svg_icon('chart') ?><span class="nav-link__text">Workload</span></a>
                <a class="nav-link<?= nav_active_class('admin_alerts.php') ?>" href="<?= h(app_route('admin_alerts.php')) ?>" title="Alerts"><?= nav_svg_icon('bell') ?><span class="nav-link__text">Alerts</span></a>
            <?php elseif ($role === 'Assessor'): ?>
                <a class="nav-link<?= nav_active_class('assessor_dashboard.php') ?>" href="<?= h(app_route('assessor_dashboard.php')) ?>" title="My students"><?= nav_svg_icon('users') ?><span class="nav-link__text">My students</span></a>
                <a class="nav-link<?= $assessorEvaluateNavActive ?>" href="<?= h(app_route('assessor_evaluate.php')) ?>" title="Evaluate — open evaluation or pick a placement from the dashboard"><?= nav_svg_icon('clipboard') ?><span class="nav-link__text">Evaluate</span></a>
                <a class="nav-link<?= nav_active_class('assessor_export.php') ?>" href="<?= h(app_route('assessor_export.php')) ?>" title="Export"><?= nav_svg_icon('download') ?><span class="nav-link__text">Export</span></a>
            <?php elseif ($role === 'Student'): ?>
                <a class="nav-link<?= nav_active_class('student_dashboard.php') ?>" href="<?= h(app_route('student_dashboard.php')) ?>" title="My results"><?= nav_svg_icon('award') ?><span class="nav-link__text">My results</span></a>
            <?php endif; ?>
        </nav>
    </aside>
    <div class="dashboard-stage">
        <main class="dashboard-main">
        <header class="dashboard-page-header">
            <h1 class="dashboard-page-title page-main-title" id="page-main-title"><?= h($pageTitle) ?></h1>
            <?php if (!empty($pageSubtitle)): ?>
                <p class="dashboard-topbar__greet page-subtitle" id="page-subtitle"><?= h((string) $pageSubtitle) ?></p>
            <?php endif; ?>
            <?php if (!empty($adminDashboardGreeting)): ?>
            <p
                class="dashboard-topbar__greet"
                id="dashboard-greeting"
                data-user-name="<?= h($displayName) ?>"
                aria-live="polite"
            ></p>
            <?php endif; ?>
        </header>
<?php else: ?>
<header class="site-header">
    <div class="brand">
        <span class="brand-mark">UNM</span>
        <span class="brand-text">Internship Results</span>
    </div>
    <nav class="navbar" aria-label="Primary">
        <a class="nav-link" href="<?= h(app_route('index.php')) ?>">Login</a>
    </nav>
</header>
<main class="site-main">
<?php endif; ?>
