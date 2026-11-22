<?php
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) { http_response_code(403); exit; }   // never run this file on its own
/*
 * includes/notifications_page.php
 * ONE notifications page for both donors and requesters.
 * A small file in donor/ or requester/ sets $role and includes this.
 *   ?go=ID  -> marks that notification read, then opens its link (only the owner's own)
 *   POST    -> "Mark all as read"
 */
if (!isset($role)) { http_response_code(403); exit; }       // only donor/ and requester/ pages may include this
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/donor_actions.php';

require_role($role);
$me = current_user();

if (isset($_GET['go'])) {
    $stmt = $pdo->prepare('SELECT link FROM notifications WHERE notification_id = ? AND user_id = ?');
    $stmt->execute([(int)$_GET['go'], $me['id']]);
    $row = $stmt->fetch();
    if ($row) {
        $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE notification_id = ? AND user_id = ?')
            ->execute([(int)$_GET['go'], $me['id']]);
        $target = safe_notification_link($row['link']);
        if ($target) { redirect('/' . $target); }
    }
    redirect('/' . $role . '/notifications.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (verify_csrf($_POST['csrf_token'] ?? '')) {
        $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?')->execute([$me['id']]);
        set_flash('success', 'All notifications are marked as read.');
    }
    redirect('/' . $role . '/notifications.php');
}

$perPage = 15;
$stmt = $pdo->prepare('SELECT COUNT(*), COALESCE(SUM(is_read = 0), 0) FROM notifications WHERE user_id = ?');
$stmt->execute([$me['id']]);
[$total, $unread] = array_map('intval', $stmt->fetch(PDO::FETCH_NUM));
$totalPages = max(1, (int)ceil($total / $perPage));
$page   = min(max(1, (int)($_GET['page'] ?? 1)), $totalPages);
$offset = ($page - 1) * $perPage;

$stmt = $pdo->prepare("SELECT notification_id, title, message, link, is_read, created_at
                       FROM notifications WHERE user_id = ?
                       ORDER BY notification_id DESC LIMIT $perPage OFFSET $offset");
$stmt->execute([$me['id']]);
$items = $stmt->fetchAll();

$dashRole  = $role;
$pageTitle = 'Notifications';
require_once __DIR__ . '/dash_header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
    <div>
        <h1 class="bl-dash-title mb-1">Notifications</h1>
        <p class="text-secondary mb-0"><?php echo $unread; ?> unread</p>
    </div>
    <?php if ($unread > 0): ?>
        <form method="post" class="m-0">
            <?php echo csrf_field(); ?>
            <button class="btn btn-outline-blood" type="submit">Mark all as read</button>
        </form>
    <?php endif; ?>
</div>

<div class="bl-panel">
    <?php if (!$items): ?>
        <p class="mb-0 text-secondary">You have no notifications yet.</p>
    <?php else: ?>
        <ul class="list-group list-group-flush">
            <?php foreach ($items as $n): $target = safe_notification_link($n['link']); ?>
                <li class="list-group-item px-0 d-flex flex-wrap justify-content-between gap-2">
                    <div>
                        <div class="<?php echo $n['is_read'] ? '' : 'fw-bold'; ?>">
                            <?php if (!$n['is_read']): ?><span class="bl-status bl-status-rejected me-1">New</span><?php endif; ?>
                            <?php echo e($n['title']); ?>
                        </div>
                        <div class="text-secondary"><?php echo e($n['message']); ?></div>
                        <div class="small text-secondary"><?php echo e(date('d M Y, h:i A', strtotime($n['created_at']))); ?></div>
                    </div>
                    <?php if ($target): ?>
                        <a class="btn btn-sm btn-outline-blood align-self-center" href="<?php echo BASE_URL; ?>/<?php echo e($role); ?>/notifications.php?go=<?php echo (int)$n['notification_id']; ?>">Open</a>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>

        <?php if ($totalPages > 1): ?>
            <nav class="mt-4" aria-label="Pages">
                <ul class="pagination justify-content-center mb-0">
                    <li class="page-item<?php echo $page <= 1 ? ' disabled' : ''; ?>"><a class="page-link" href="?page=<?php echo $page - 1; ?>">Previous</a></li>
                    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                        <li class="page-item<?php echo $p === $page ? ' active' : ''; ?>"><a class="page-link" href="?page=<?php echo $p; ?>"><?php echo $p; ?></a></li>
                    <?php endfor; ?>
                    <li class="page-item<?php echo $page >= $totalPages ? ' disabled' : ''; ?>"><a class="page-link" href="?page=<?php echo $page + 1; ?>">Next</a></li>
                </ul>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/dash_footer.php'; ?>
