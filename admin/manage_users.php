<?php
/*
 * admin/manage_users.php  -  Admin: every account (admin, donor, requester).
 * The admin can deactivate / reactivate an account. Nothing is ever deleted:
 * the history of requests and donations must stay complete.
 * A deactivated person cannot log in, and is logged out at their next click.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('admin');
$me = current_user();

/* ---------- activate / deactivate ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $back = '/admin/manage_users.php';
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'Your session expired. Please try again.');
    } else {
        $uid  = (int)($_POST['id'] ?? 0);
        $make = ($_POST['make'] ?? '') === 'activate' ? 1 : 0;
        $stmt = $pdo->prepare('SELECT full_name, role, is_active FROM users WHERE user_id = ?');
        $stmt->execute([$uid]);
        $u = $stmt->fetch();

        if (!$u) {
            set_flash('danger', 'Account not found.');
        } elseif ($uid === (int)$me['id']) {
            set_flash('danger', 'You cannot deactivate your own account.');
        } elseif ((int)$u['is_active'] === $make) {
            set_flash('warning', $u['full_name'] . ' is already ' . ($make ? 'active' : 'deactivated') . '.');
        } else {
            $pdo->prepare('UPDATE users SET is_active = ? WHERE user_id = ?')->execute([$make, $uid]);
            log_admin_action($pdo, $me['id'], $make ? 'Reactivated account' : 'Deactivated account', 'users', $uid, ucfirst($u['role']) . ': ' . $u['full_name']);
            set_flash('success', $u['full_name'] . ($make ? ' can log in again.' : ' was deactivated and cannot log in.'));
        }
    }
    $qs = (string)($_POST['return'] ?? '');
    redirect($back . (preg_match('/^[A-Za-z0-9=&%_.+-]*$/', $qs) && $qs !== '' ? '?' . $qs : ''));
}

$roles = ['admin' => 'Admin', 'donor' => 'Donor', 'requester' => 'Requester'];
$q      = substr(clean($_GET['q'] ?? ''), 0, 100);
$role   = array_key_exists($_GET['role'] ?? '', $roles) ? $_GET['role'] : '';
$active = in_array($_GET['active'] ?? '', ['yes', 'no'], true) ? $_GET['active'] : '';

$where = ['1 = 1']; $params = [];
if ($q !== '') {
    $like = '%' . addcslashes($q, '%_\\') . '%';
    $where[] = '(u.full_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)';
    array_push($params, $like, $like, $like);
}
if ($role !== '')       { $where[] = 'u.role = ?'; $params[] = $role; }
if ($active === 'yes')  { $where[] = 'u.is_active = 1'; }
if ($active === 'no')   { $where[] = 'u.is_active = 0'; }
$whereSql = implode(' AND ', $where);

$counts = $pdo->query('SELECT role, COUNT(*) FROM users GROUP BY role')->fetchAll(PDO::FETCH_KEY_PAIR);
$inactive = (int)$pdo->query('SELECT COUNT(*) FROM users WHERE is_active = 0')->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM users u WHERE $whereSql");
$stmt->execute($params);
$total = (int)$stmt->fetchColumn();

$perPage = 15;
$totalPages = max(1, (int)ceil($total / $perPage));
$page   = min(max(1, (int)($_GET['page'] ?? 1)), $totalPages);
$offset = ($page - 1) * $perPage;

$stmt = $pdo->prepare(
    "SELECT u.user_id, u.full_name, u.email, u.phone, u.role, u.is_active, u.created_at,
            (SELECT COUNT(*) FROM blood_requests r WHERE r.requester_id = u.user_id) AS requests
     FROM users u WHERE $whereSql
     ORDER BY FIELD(u.role, 'admin', 'donor', 'requester'), u.full_name
     LIMIT $perPage OFFSET $offset"
);
$stmt->execute($params);
$users = $stmt->fetchAll();

$filters = array_filter(['q' => $q, 'role' => $role, 'active' => $active], fn($v) => $v !== '');
$returnQuery = http_build_query($filters + ['page' => $page]);
function user_page_url(array $filters, int $p): string
{
    return BASE_URL . '/admin/manage_users.php?' . http_build_query($filters + ['page' => $p]);
}

$dashRole  = 'admin';
$pageTitle = 'Users';
require_once __DIR__ . '/../includes/dash_header.php';
?>

<h1 class="bl-dash-title">Users</h1>
<p class="text-secondary mb-3">All accounts. Accounts are deactivated, never deleted, so history stays complete.</p>

<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3"><div class="bl-stat"><div class="num"><?php echo (int)($counts['admin'] ?? 0); ?></div><div class="lbl">Admins</div></div></div>
    <div class="col-6 col-lg-3"><div class="bl-stat"><div class="num"><?php echo (int)($counts['donor'] ?? 0); ?></div><div class="lbl">Donors</div></div></div>
    <div class="col-6 col-lg-3"><div class="bl-stat"><div class="num"><?php echo (int)($counts['requester'] ?? 0); ?></div><div class="lbl">Requesters</div></div></div>
    <div class="col-6 col-lg-3"><div class="bl-stat <?php echo $inactive ? 'bl-stat-red' : ''; ?>"><div class="num"><?php echo $inactive; ?></div><div class="lbl">Deactivated</div></div></div>
</div>

<form method="get" action="" class="bl-filter mb-4">
    <div class="row g-3 align-items-end">
        <div class="col-md-5">
            <label for="q" class="form-label">Name, email or phone</label>
            <input type="text" class="form-control" id="q" name="q" maxlength="100" value="<?php echo e($q); ?>">
        </div>
        <div class="col-6 col-md-2">
            <label for="role" class="form-label">Role</label>
            <select class="form-select" id="role" name="role">
                <option value="">Any</option>
                <?php foreach ($roles as $k => $label): ?><option value="<?php echo e($k); ?>"<?php echo selected($role, $k); ?>><?php echo e($label); ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label for="active" class="form-label">Account</label>
            <select class="form-select" id="active" name="active">
                <option value="">Any</option>
                <option value="yes"<?php echo selected($active, 'yes'); ?>>Active</option>
                <option value="no"<?php echo selected($active, 'no'); ?>>Deactivated</option>
            </select>
        </div>
        <div class="col-md-3 d-flex gap-2">
            <button type="submit" class="btn btn-primary flex-grow-1">Search</button>
            <a href="<?php echo BASE_URL; ?>/admin/manage_users.php" class="btn btn-outline-blood">Clear</a>
        </div>
    </div>
</form>

<div class="bl-panel">
    <p class="text-secondary small"><?php echo $total; ?> <?php echo $total === 1 ? 'account' : 'accounts'; ?> found</p>
    <?php if (!$users): ?>
        <p class="mb-0 text-secondary">No account matches these filters.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>Name</th><th>Contact</th><th>Role</th><th>Account</th><th>Joined</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($users as $u): $isMe = ((int)$u['user_id'] === (int)$me['id']); ?>
                    <tr>
                        <td>
                            <?php if ($u['role'] === 'donor'): ?>
                                <a href="<?php echo BASE_URL; ?>/admin/donor_view.php?id=<?php echo (int)$u['user_id']; ?>"><?php echo e($u['full_name']); ?></a>
                            <?php else: echo e($u['full_name']); endif; ?>
                            <?php if ($isMe): ?><span class="small text-secondary">(you)</span><?php endif; ?>
                            <?php if ($u['role'] === 'requester'): ?><div class="small text-secondary"><?php echo (int)$u['requests']; ?> <?php echo $u['requests'] == 1 ? 'request' : 'requests'; ?></div><?php endif; ?>
                        </td>
                        <td class="small"><?php echo e($u['email']); ?><br><?php echo e($u['phone']); ?></td>
                        <td><span class="bl-role-chip"><?php echo e($roles[$u['role']]); ?></span></td>
                        <td><?php echo $u['is_active'] ? '<span class="bl-status bl-status-approved">Active</span>' : '<span class="bl-status bl-status-rejected">Deactivated</span>'; ?></td>
                        <td class="small text-nowrap"><?php echo e(date('d M Y', strtotime($u['created_at']))); ?></td>
                        <td class="text-end">
                            <?php if (!$isMe): ?>
                                <form method="post" class="m-0" data-confirm="<?php echo $u['is_active'] ? 'Deactivate ' . e($u['full_name']) . '? They will not be able to log in.' : 'Reactivate this account?'; ?>">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="id" value="<?php echo (int)$u['user_id']; ?>">
                                    <input type="hidden" name="make" value="<?php echo $u['is_active'] ? 'deactivate' : 'activate'; ?>">
                                    <input type="hidden" name="return" value="<?php echo e($returnQuery); ?>">
                                    <button class="btn btn-sm <?php echo $u['is_active'] ? 'btn-outline-secondary' : 'btn-primary'; ?>"><?php echo $u['is_active'] ? 'Deactivate' : 'Reactivate'; ?></button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
            <nav class="mt-4" aria-label="Pages">
                <ul class="pagination justify-content-center mb-0 flex-wrap">
                    <li class="page-item<?php echo $page <= 1 ? ' disabled' : ''; ?>"><a class="page-link" href="<?php echo e(user_page_url($filters, $page - 1)); ?>">Previous</a></li>
                    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                        <li class="page-item<?php echo $p === $page ? ' active' : ''; ?>"><a class="page-link" href="<?php echo e(user_page_url($filters, $p)); ?>"><?php echo $p; ?></a></li>
                    <?php endfor; ?>
                    <li class="page-item<?php echo $page >= $totalPages ? ' disabled' : ''; ?>"><a class="page-link" href="<?php echo e(user_page_url($filters, $page + 1)); ?>">Next</a></li>
                </ul>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/dash_footer.php'; ?>
