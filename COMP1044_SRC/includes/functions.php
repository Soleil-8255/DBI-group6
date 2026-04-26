<?php
declare(strict_types=1);

/**
 * Shared PHP helpers: XSS-safe output, session/auth guards, and future grade utilities.
 *
 * ============================================================================
 * FIVE INNOVATIONS — WHERE THEY WILL BE IMPLEMENTED (course deliverable map)
 * ============================================================================
 * Innovation 1 — Multi-axis performance radar (Chart.js or similar):
 *   Primary: assets/js/modules/innovations.js  |  Data: `Assessments` / `Internships` joins (API or page-embedded JSON); schema: COMP1044_database.sql.
 *
 * Innovation 2 — Assessor workload heatmap / status view:
 *   Primary: admin_workload.php  |  Data: view `Assessor_Workload_View` and/or `Internships` grouped by assessor_id.
 *
 * Innovation 3 — Grade “circuit breaker” early warning (exceptional / failing marks):
 *   Primary: admin_alerts.php  |  Data: `Audit_Logs` (e.g. action_type / description for GRADE_ALERT, GRADE_UPDATED).
 *
 * Innovation 4 — One-click transcript export (CSV and optional PDF):
 *   Primary: assessor_export.php  |  Data: `Students`, `Internships`, `Assessments` for assessor-owned rows only.
 *
 * Innovation 5 — Self-assessment vs assessor score “cognitive bias” visualisation:
 *   Primary: assets/js/modules/innovations.js  |  Data: future self-rating storage vs `Assessments.total_mark` (COMP1044_database.sql).
 * ============================================================================
 */

/**
 * Escape output for HTML contexts (XSS mitigation). Use for every dynamic string in templates.
 */
function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Web path prefix for static assets when the app is deployed in a subdirectory.
 * Must point at the app root (folder that contains index.php and assets/), not the
 * directory of the current script — otherwise requests to pages/admin/*.php resolve
 * CSS/JS to pages/admin/assets/... (404, unstyled pages).
 */
function app_base_path(): string
{
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/'));
    $lower = strtolower($script);
    $pagesPos = strpos($lower, '/pages/');
    if ($pagesPos !== false) {
        $root = substr($script, 0, $pagesPos);
        if ($root === '' || $root === '/') {
            return '';
        }

        return rtrim($root, '/');
    }

    $dir = dirname($script);
    if ($dir === '/' || $dir === '\\' || $dir === '.') {
        return '';
    }

    return rtrim($dir, '/');
}

/**
 * Build a URL path to a static file (e.g. under assets/).
 */
function asset_url(string $relativePath): string
{
    $base = app_base_path();
    $path = ltrim($relativePath, '/');

    return ($base === '' ? '' : $base . '/') . $path;
}

/**
 * Project root (parent of /includes), for resolving asset paths on disk.
 */
function app_root_path(): string
{
    return dirname(__DIR__, 1);
}

/**
 * Asset URL with ?v= filemtime to avoid stale browser cache after deploy/edits.
 * For style.css, version bumps if main bundle OR assessor evaluate page CSS changes.
 */
function asset_v(string $relativePath): string
{
    $rel = ltrim((string) $relativePath, '/');
    $relFs = str_replace('/', DIRECTORY_SEPARATOR, $rel);
    $full = app_root_path() . DIRECTORY_SEPARATOR . $relFs;
    $mtime = is_file($full) ? (int) filemtime($full) : 0;

    if ($rel === 'assets/css/style.css') {
        $pagesDir = app_root_path() . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'pages' . DIRECTORY_SEPARATOR;
        foreach (['assessor-evaluate.css', 'assessor-export.css', 'student-dashboard.css', 'student-radar.css'] as $depFile) {
            $dep = $pagesDir . $depFile;
            if (is_file($dep)) {
                $mtime = max($mtime, (int) filemtime($dep));
            }
        }
    }

    $url = asset_url($relativePath);

    return $mtime > 0 ? $url . '?v=' . (string) $mtime : $url;
}

/**
 * Build a URL path to a top-level PHP page (not under assets/).
 */
function app_route(string $phpFile): string
{
    $base = app_base_path();
    $path = ltrim($phpFile, '/');

    $pageMap = [
        'admin_dashboard.php' => 'pages/admin/admin_dashboard.php',
        'admin_manage_users.php' => 'pages/admin/admin_manage_users.php',
        'api_import_students.php' => 'pages/admin/api_import_students.php',
        'api_import_assessors.php' => 'pages/admin/api_import_assessors.php',
        'api_create_company.php' => 'pages/admin/api_create_company.php',
        'admin_internships.php' => 'pages/admin/admin_internships.php',
        'admin_results.php' => 'pages/admin/admin_results.php',
        'admin_alerts.php' => 'pages/admin/admin_alerts.php',
        'admin_workload.php' => 'pages/admin/admin_workload.php',
        'assessor_dashboard.php' => 'pages/assessor/assessor_dashboard.php',
        'assessor_evaluate.php' => 'pages/assessor/assessor_evaluate.php',
        'assessor_export.php' => 'pages/assessor/assessor_export.php',
        'student_dashboard.php' => 'pages/student/student_dashboard.php',
    ];
    if (isset($pageMap[$path])) {
        $path = $pageMap[$path];
    }

    return ($base === '' ? '' : $base . '/') . $path;
}

function ensure_session(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

function csrf_token(): string
{
    ensure_session();
    if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token']) || $_SESSION['csrf_token'] === '') {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return (string) $_SESSION['csrf_token'];
}

function validate_csrf_token(?string $submittedToken): bool
{
    ensure_session();
    $sessionToken = isset($_SESSION['csrf_token']) && is_string($_SESSION['csrf_token'])
        ? $_SESSION['csrf_token']
        : '';
    if ($sessionToken === '' || $submittedToken === null || $submittedToken === '') {
        return false;
    }

    return hash_equals($sessionToken, $submittedToken);
}

function csrf_input(): string
{
    return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
}

function app_log_exception(string $context, Throwable $e): void
{
    $message = '[IRMS][' . $context . '] ' . get_class($e) . ': ' . $e->getMessage();
    error_log($message);
}

function app_public_error(string $key = 'generic'): string
{
    $messages = [
        'generic' => 'The request could not be completed right now. Please try again.',
        'db_read' => 'Data could not be loaded right now. Please try again later.',
        'db_write' => 'Changes could not be saved. Please review your input and try again.',
        'csrf' => 'Your session security token is invalid or expired. Refresh and try again.',
        'auth' => 'You are not authorized to perform this action.',
    ];

    return $messages[$key] ?? $messages['generic'];
}

/**
 * One-line label for assessor UIs, e.g. "Cohort: 2024 | Period: Jan 2026 - Apr 2026".
 */
function internship_cohort_period_caption(?string $cohortYear, ?string $startSql, ?string $endSql): string
{
    $cohort = '—';
    if ($cohortYear !== null && trim((string) $cohortYear) !== '') {
        $cohort = trim((string) $cohortYear);
    }

    $period = '—';
    if ($startSql !== null && $startSql !== '') {
        try {
            $s = new DateTimeImmutable($startSql);
            $left = $s->format('M Y');
        } catch (\Throwable $e) {
            $left = '—';
        }
        if ($left !== '—' && $endSql !== null && $endSql !== '') {
            try {
                $end = new DateTimeImmutable($endSql);
                $period = $left . ' - ' . $end->format('M Y');
            } catch (\Throwable $e) {
                $period = $left . ' – …';
            }
        } elseif ($left !== '—') {
            $period = $left . ' – …';
        }
    }

    return 'Cohort: ' . $cohort . ' | Period: ' . $period;
}

function current_user_id(): ?int
{
    return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
}

function current_user_role(): ?string
{
    return isset($_SESSION['role']) ? (string) $_SESSION['role'] : null;
}

function require_login(): void
{
    if (current_user_id() === null) {
        header('Location: ' . app_route('index.php'));
        exit;
    }
}

function require_role(string $role): void
{
    require_login();
    if (current_user_role() !== $role) {
        http_response_code(403);
        exit('Access denied.');
    }
}

/**
 * CSS class suffix for active sidebar/nav item (leading space if active).
 */
function nav_active_class(string $phpBasename): string
{
    $current = strtolower(basename(str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''))));
    $target = strtolower($phpBasename);

    return $current === $target ? ' nav-link--active' : '';
}

/**
 * Inline stroke SVG (Feather / Heroicons style) for dashboard sidebar. Safe static markup only.
 */
function nav_svg_icon(string $id): string
{
    $svgs = [
        'dashboard' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>',
        'users' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
        'briefcase' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>',
        'document' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M16 13H8"/><path d="M16 17H8"/><path d="M10 9H8"/></svg>',
        'chart' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 20V10"/><path d="M12 20V4"/><path d="M6 20v-6"/></svg>',
        'bell' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>',
        'logout' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>',
        'clipboard' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/><path d="M9 12h6"/><path d="M9 16h6"/></svg>',
        'download' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>',
        'award' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="7"/><path d="M8.21 13.89L7 23l5-3 5 3-1.21-9.12"/></svg>',
    ];
    $inner = $svgs[$id] ?? $svgs['dashboard'];

    return '<span class="nav-link__icon">' . $inner . '</span>';
}

/**
 * Two-letter initials for dashboard avatar chip (ASCII safe).
 */
function user_avatar_initials(string $fullName): string
{
    $trimmed = trim($fullName);
    if ($trimmed === '') {
        return '?';
    }

    $parts = preg_split('/\s+/u', $trimmed) ?: [];
    $initials = '';
    foreach (array_slice($parts, 0, 2) as $part) {
        $initials .= strtoupper(substr((string) $part, 0, 1));
    }

    return $initials !== '' ? $initials : '?';
}

/**
 * Reserved for weighted totals, moderation rules, and data normalisation (keep DB triggers and PHP in sync).
 */
function placeholder_grade_helpers(): void
{
    // Future: centralise mark calculations if business rules exceed DB trigger scope.
}
