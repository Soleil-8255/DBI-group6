<?php
declare(strict_types=1);

/**
 * INNOVATION 3 — Grade “circuit breaker”: notification feed (list layout).
 * Dismiss is client-only (localStorage); no server-side dismiss / DB writes.
 */

require_once __DIR__ . '/../../../includes/db_connect.php';
require_once __DIR__ . '/../../../includes/functions.php';

ensure_session();
require_role('Admin');

/**
 * Human-readable time from `timestamp` (MySQL) or similar.
 */
function admin_alerts_time_human(string $raw): string
{
    $raw = trim($raw);
    if ($raw === '') {
        return '—';
    }
    $norm = str_replace('T', ' ', $raw);
    if (strlen($norm) > 19) {
        $norm = substr($norm, 0, 19);
    }
    $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $norm);
    if ($dt === false) {
        return $raw;
    }
    $now = new DateTimeImmutable('now');
    $ts = $dt->getTimestamp();
    $nowTs = $now->getTimestamp();
    $diff = $nowTs - $ts;
    if ($diff < 0) {
        $diff = 0;
    }
    if ($diff < 50) {
        return 'Just now';
    }
    if ($diff < 3600) {
        $m = max(1, (int) floor($diff / 60));
        return $m === 1 ? '1 minute ago' : $m . ' minutes ago';
    }
    if ($diff < 86400) {
        $h = max(1, (int) floor($diff / 3600));
        return $h === 1 ? '1 hour ago' : $h . ' hours ago';
    }
    if ($diff < 604800) {
        $d = (int) floor($diff / 86400);
        if ($d === 1) {
            return 'Yesterday';
        }
        return $d . ' days ago';
    }
    return $dt->format('M j, Y');
}

/**
 * @return 'alert-critical'|'alert-info'
 */
function admin_alerts_severity_class(string $description): string
{
    $d = mb_strtolower($description, 'UTF-8');
    if (str_contains($d, 'scored 0') || str_contains($d, 'error')) {
        return 'alert-critical';
    }
    return 'alert-info';
}

$sql = 'SELECT log_id, user_id, action_type, description, `timestamp`
        FROM `Audit_Logs`
        WHERE action_type IN (\'GRADE_ALERT\', \'GRADE_UPDATED\')
        ORDER BY `timestamp` DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute();
$alerts = $stmt->fetchAll();

$pageTitle = 'Grade Alerts';
require_once __DIR__ . '/../../../includes/header.php';
?>

<section
    class="card admin-alerts"
    id="admin-alerts"
    aria-labelledby="alerts-page-title"
>
    <h1 class="page-title" id="alerts-page-title">Grade Circuit-Breaker Alerts</h1>

    <div class="alert-feed" id="alert-feed">
        <?php if ($alerts === []): ?>
            <p class="alert-feed__empty admin-anim-entrance">No alert entries found.</p>
        <?php else: ?>
            <p class="alert-feed__zero" id="alert-feed-zero" hidden>No alert entries found.</p>
            <ul class="alert-feed__list admin-card-stagger" role="list" id="alert-feed-list">
                <?php foreach ($alerts as $a):
                    $desc = (string) ($a['description'] ?? '');
                    $sev = admin_alerts_severity_class($desc);
                    $logId = (int) $a['log_id'];
                    $tsRaw = (string) ($a['timestamp'] ?? '');
                    $timeHuman = admin_alerts_time_human($tsRaw);
                    $act = (string) ($a['action_type'] ?? '');
                    $uid = $a['user_id'] ?? null;
                    $uidText = 'System';
                    if ($uid !== null && $uid !== '' && (int) $uid > 0) {
                        $uidText = 'User #' . (int) $uid;
                    }
                    ?>
                <li class="alert-feed__item admin-anim-entrance <?= h($sev) ?>" data-log-id="<?= h((string) $logId) ?>" role="listitem">
                    <div class="alert-feed__main">
                        <span class="alert-feed__icon" aria-hidden="true">
                            <?php if ($sev === 'alert-critical'): ?>
                            <svg class="alert-feed__svg" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
                                <path d="M12 2 2 20h20L12 2Z" fill="#fef2f2" stroke="#b91c1c" stroke-width="1.2" stroke-linejoin="round"/>
                                <path d="M12 8v4" stroke="#991b1b" stroke-width="1.4" stroke-linecap="round"/>
                                <circle cx="12" cy="16" r="0.8" fill="#991b1b"/>
                            </svg>
                            <?php else: ?>
                            <svg class="alert-feed__svg" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
                                <circle cx="12" cy="12" r="9" fill="#f0f6ff" stroke="#122a54" stroke-width="1.2"/>
                                <path d="M12 8v4M12 15v.4" stroke="#122a54" stroke-width="1.4" stroke-linecap="round"/>
                            </svg>
                            <?php endif; ?>
                        </span>
                        <div class="alert-feed__body">
                            <p class="alert-feed__message"><?= h($desc) ?></p>
                            <p class="alert-feed__meta">
                                <span class="alert-feed__type"><?= h($act) ?></span>
                                <span class="alert-feed__sep" aria-hidden="true">·</span>
                                <span class="alert-feed__user"><?= h($uidText) ?></span>
                            </p>
                        </div>
                    </div>
                    <div class="alert-feed__aside">
                        <time
                            class="alert-feed__time"
                            datetime="<?= h($tsRaw) ?>"
                        ><?= h($timeHuman) ?></time>
                        <button
                            type="button"
                            class="alert-feed__dismiss"
                            data-log-id="<?= h((string) $logId) ?>"
                            aria-label="Dismiss this alert"
                        >
                            <svg class="alert-feed__dismiss-icon" width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true" focusable="false">
                                <path d="M3 8.2 6 11.1 12.2 3.2" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <span class="alert-feed__dismiss-text">Dismiss</span>
                        </button>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</section>

<?php
require_once __DIR__ . '/../../../includes/footer.php';
