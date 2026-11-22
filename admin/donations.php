<?php
/*
 * admin/donations.php  -  Admin: every donation that was recorded.
 * Donations for a request are recorded when the request is completed (request_view.php).
 * Here the admin can also record a donation that has NO request:
 * a walk-in donor or a blood-donation camp.
 * A database TRIGGER then updates the donor's "last donation date" by itself.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/donor_actions.php';

require_role('admin');
$me = current_user();

/* ---------- record a walk-in / camp donation ---------- */
$formErrors = [];
$old = ['donor_id' => '', 'bags' => '1', 'date' => date('Y-m-d'), 'notes' => ''];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old = ['donor_id' => (string)($_POST['donor_id'] ?? ''), 'bags' => (string)($_POST['bags'] ?? ''),
            'date' => (string)($_POST['date'] ?? ''), 'notes' => clean($_POST['notes'] ?? '')];
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $formErrors[] = 'Your session expired. Please try again.';
    } else {
        $donorId = (int)$old['donor_id'];
        $bags    = (int)$old['bags'];
        $stmt = $pdo->prepare("SELECT u.full_name FROM donors d JOIN users u ON u.user_id = d.user_id WHERE d.user_id = ? AND u.is_active = 1 AND u.role = 'donor'");
        $stmt->execute([$donorId]);
        $donorName = $stmt->fetchColumn();

        if (!$donorName)                                        { $formErrors[] = 'Choose a donor from the list.'; }
        if ($bags < 1 || $bags > 5)                             { $formErrors[] = 'Bags must be a number from 1 to 5.'; }
        if (!is_valid_date($old['date']) || $old['date'] > date('Y-m-d')) { $formErrors[] = 'Choose the donation date (not in the future).'; }
        if (mb_strlen($old['notes']) > 200)                     { $formErrors[] = 'The note can have at most 200 letters.'; }

        if (!$formErrors) {
            $note = $old['notes'] !== '' ? $old['notes'] : 'Walk-in / camp donation';
            $pdo->prepare('INSERT INTO donations (request_id, donor_id, bags_donated, donation_date, recorded_by, notes) VALUES (NULL, ?, ?, ?, ?, ?)')
                ->execute([$donorId, $bags, $old['date'], $me['id'], $note]);
            log_admin_action($pdo, $me['id'], 'Recorded donation', 'donations', (int)$pdo->lastInsertId(), "$donorName, $bags bag(s), {$old['date']}");
            add_notification($pdo, $donorId, 'Thank you!', "Your donation on " . date('d M Y', strtotime($old['date'])) . " was recorded. Thank you.", 'donor/donation_history.php');
            set_flash('success', "Donation of $donorName was recorded.");
            redirect('/admin/donations.php');
        }
    }
}

/* ---------- filters ---------- */
$bloodGroups = $pdo->query('SELECT blood_group_id, group_name FROM blood_groups ORDER BY blood_group_id')->fetchAll();
$q       = substr(clean($_GET['q'] ?? ''), 0, 100);
$groupId = (int)($_GET['group'] ?? 0);
$from    = is_valid_date((string)($_GET['from'] ?? '')) ? $_GET['from'] : '';
$to      = is_valid_date((string)($_GET['to'] ?? '')) ? $_GET['to'] : '';
if (!in_array($groupId, array_column($bloodGroups, 'blood_group_id'))) { $groupId = 0; }

$where = ['1 = 1']; $params = [];
if ($q !== '')     { $where[] = 'u.full_name LIKE ?';     $params[] = '%' . addcslashes($q, '%_\\') . '%'; }
if ($groupId > 0)  { $where[] = 'd.blood_group_id = ?';   $params[] = $groupId; }
if ($from !== '')  { $where[] = 'dn.donation_date >= ?';  $params[] = $from; }
if ($to !== '')    { $where[] = 'dn.donation_date <= ?';  $params[] = $to; }
$whereSql = implode(' AND ', $where);
$join = 'FROM donations dn
         JOIN donors d        ON d.user_id         = dn.donor_id
         JOIN users u         ON u.user_id         = dn.donor_id
         JOIN blood_groups bg ON bg.blood_group_id = d.blood_group_id';

$stmt = $pdo->prepare("SELECT COUNT(*) AS n, COALESCE(SUM(dn.bags_donated), 0) AS bags, COUNT(DISTINCT dn.donor_id) AS donors $join WHERE $whereSql");
$stmt->execute($params);
$sum = $stmt->fetch();
$total = (int)$sum['n'];

$perPage = 15;
$totalPages = max(1, (int)ceil($total / $perPage));
$page   = min(max(1, (int)($_GET['page'] ?? 1)), $totalPages);
$offset = ($page - 1) * $perPage;

$stmt = $pdo->prepare(
    "SELECT dn.donation_id, dn.donation_date, dn.bags_donated, dn.notes, dn.request_id, u.user_id, u.full_name, bg.group_name,
            ra.full_name AS recorder
     $join LEFT JOIN users ra ON ra.user_id = dn.recorded_by
     WHERE $whereSql
     ORDER BY dn.donation_date DESC, dn.donation_id DESC
     LIMIT $perPage OFFSET $offset"
);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$donorList = $pdo->query(
    "SELECT u.user_id, u.full_name, bg.group_name FROM donors d
     JOIN users u ON u.user_id = d.user_id JOIN blood_groups bg ON bg.blood_group_id = d.blood_group_id
     WHERE u.is_active = 1 ORDER BY u.full_name"
)->fetchAll();

$filters = array_filter(['q' => $q, 'group' => $groupId ?: '', 'from' => $from, 'to' => $to], fn($v) => $v !== '');
function don_page_url(array $filters, int $p): string
{
    return BASE_URL . '/admin/donations.php?' . http_build_query($filters + ['page' => $p]);
}

$dashRole  = 'admin';
$pageTitle = 'Donation records';
require_once __DIR__ . '/../includes/dash_header.php';
?>

<h1 class="bl-dash-title">Donation records</h1>
<p class="text-secondary mb-3">Every donation recorded in BloodBank.</p>

<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3"><div class="bl-stat bl-stat-red"><div class="num"><?php echo $total; ?></div><div class="lbl">Donations (this filter)</div></div></div>
    <div class="col-6 col-lg-3"><div class="bl-stat"><div class="num"><?php echo (int)$sum['bags']; ?></div><div class="lbl">Bags</div></div></div>
    <div class="col-6 col-lg-3"><div class="bl-stat"><div class="num"><?php echo (int)$sum['donors']; ?></div><div class="lbl">Different donors</div></div></div>
</div>

<div class="bl-panel mb-4">
    <h2 class="bl-panel-title">Record a walk-in or camp donation</h2>
    <p class="small text-secondary">For a donation that is not linked to a request. Donations for a request are recorded when you complete that request.</p>
    <?php if ($formErrors): ?>
        <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($formErrors as $er): ?><li><?php echo e($er); ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>
    <form method="post" class="needs-validation" novalidate>
        <?php echo csrf_field(); ?>
        <div class="row g-3 align-items-end">
            <div class="col-md-4">
                <label for="donor_id" class="form-label">Donor</label>
                <select class="form-select" id="donor_id" name="donor_id" required>
                    <option value="">Choose...</option>
                    <?php foreach ($donorList as $dl): ?>
                        <option value="<?php echo (int)$dl['user_id']; ?>"<?php echo selected($old['donor_id'], $dl['user_id']); ?>><?php echo e($dl['full_name']); ?> (<?php echo e($dl['group_name']); ?>)</option>
                    <?php endforeach; ?>
                </select>
                <div class="invalid-feedback">Choose a donor.</div>
            </div>
            <div class="col-6 col-md-2">
                <label for="bags" class="form-label">Bags</label>
                <input type="number" class="form-control" id="bags" name="bags" min="1" max="5" value="<?php echo e($old['bags']); ?>" required>
            </div>
            <div class="col-6 col-md-3">
                <label for="date" class="form-label">Date</label>
                <input type="date" class="form-control" id="date" name="date" max="<?php echo date('Y-m-d'); ?>" value="<?php echo e($old['date']); ?>" required>
            </div>
            <div class="col-md-3">
                <label for="notes" class="form-label">Note (optional)</label>
                <input type="text" class="form-control" id="notes" name="notes" maxlength="200" value="<?php echo e($old['notes']); ?>" placeholder="e.g. Blood camp">
            </div>
            <div class="col-12"><button class="btn btn-primary">Record donation</button></div>
        </div>
    </form>
</div>

<form method="get" action="" class="bl-filter mb-4">
    <div class="row g-3 align-items-end">
        <div class="col-md-3">
            <label for="fq" class="form-label">Donor name</label>
            <input type="text" class="form-control" id="fq" name="q" maxlength="100" value="<?php echo e($q); ?>">
        </div>
        <div class="col-6 col-md-2">
            <label for="fg" class="form-label">Blood group</label>
            <select class="form-select" id="fg" name="group">
                <option value="">Any</option>
                <?php foreach ($bloodGroups as $bg): ?><option value="<?php echo (int)$bg['blood_group_id']; ?>"<?php echo selected($groupId, $bg['blood_group_id']); ?>><?php echo e($bg['group_name']); ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-2"><label for="ff" class="form-label">From</label><input type="date" class="form-control" id="ff" name="from" value="<?php echo e($from); ?>"></div>
        <div class="col-6 col-md-2"><label for="ft" class="form-label">To</label><input type="date" class="form-control" id="ft" name="to" value="<?php echo e($to); ?>"></div>
        <div class="col-6 col-md-3 d-flex gap-2">
            <button type="submit" class="btn btn-primary flex-grow-1">Filter</button>
            <a href="<?php echo BASE_URL; ?>/admin/donations.php" class="btn btn-outline-blood">Clear</a>
        </div>
    </div>
</form>

<div class="bl-panel">
    <?php if (!$rows): ?>
        <p class="mb-0 text-secondary">No donation matches these filters.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>Date</th><th>Donor</th><th>Blood</th><th>Bags</th><th>For</th><th>Notes</th><th>Recorded by</th></tr></thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td class="text-nowrap"><?php echo e(date('d M Y', strtotime($r['donation_date']))); ?></td>
                        <td><a href="<?php echo BASE_URL; ?>/admin/donor_view.php?id=<?php echo (int)$r['user_id']; ?>"><?php echo e($r['full_name']); ?></a></td>
                        <td><strong><?php echo e($r['group_name']); ?></strong></td>
                        <td><?php echo (int)$r['bags_donated']; ?></td>
                        <td><?php echo $r['request_id'] ? '<a href="' . BASE_URL . '/admin/request_view.php?id=' . (int)$r['request_id'] . '">Request #' . (int)$r['request_id'] . '</a>' : '<span class="text-secondary">Walk-in / camp</span>'; ?></td>
                        <td class="small"><?php echo e($r['notes'] ?? ''); ?></td>
                        <td class="small"><?php echo e($r['recorder'] ?? '-'); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ($totalPages > 1): ?>
            <nav class="mt-4" aria-label="Pages">
                <ul class="pagination justify-content-center mb-0 flex-wrap">
                    <li class="page-item<?php echo $page <= 1 ? ' disabled' : ''; ?>"><a class="page-link" href="<?php echo e(don_page_url($filters, $page - 1)); ?>">Previous</a></li>
                    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                        <li class="page-item<?php echo $p === $page ? ' active' : ''; ?>"><a class="page-link" href="<?php echo e(don_page_url($filters, $p)); ?>"><?php echo $p; ?></a></li>
                    <?php endfor; ?>
                    <li class="page-item<?php echo $page >= $totalPages ? ' disabled' : ''; ?>"><a class="page-link" href="<?php echo e(don_page_url($filters, $page + 1)); ?>">Next</a></li>
                </ul>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/dash_footer.php'; ?>
