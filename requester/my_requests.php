<?php
/*
 * requester/my_requests.php  -  All the requests of the logged-in requester.
 * Filter: All / Open / Completed / Closed (rejected or cancelled). 10 per page.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('requester');
$me = current_user();

$dashRole  = 'requester';
$pageTitle = 'My Requests';

// The tabs: name => [label, statuses it contains]
$tabs = [
    'all'       => ['All', null],
    'open'      => ['Open', ['Pending', 'Under Review', 'Approved', 'Donor Matched']],
    'completed' => ['Completed', ['Completed']],
    'closed'    => ['Rejected / Cancelled', ['Rejected', 'Cancelled']],
];
$show = array_key_exists($_GET['show'] ?? '', $tabs) ? $_GET['show'] : 'all';

// How many requests in each tab (GROUP BY once, then add up in PHP)
$stmt = $pdo->prepare('SELECT status, COUNT(*) FROM blood_requests WHERE requester_id = ? GROUP BY status');
$stmt->execute([$me['id']]);
$byStatus = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
$tabCount = [];
foreach ($tabs as $key => [$label, $statuses]) {
    $tabCount[$key] = $statuses === null
        ? array_sum($byStatus)
        : array_sum(array_intersect_key($byStatus, array_flip($statuses)));
}

// The list itself
$where  = 'r.requester_id = ?';
$params = [$me['id']];
if ($tabs[$show][1] !== null) {
    $marks   = implode(',', array_fill(0, count($tabs[$show][1]), '?'));
    $where  .= " AND r.status IN ($marks)";
    $params  = array_merge($params, $tabs[$show][1]);
}

$stmt = $pdo->prepare("SELECT COUNT(*) FROM blood_requests r WHERE $where");
$stmt->execute($params);
$total      = (int)$stmt->fetchColumn();
$perPage    = 10;
$totalPages = max(1, (int)ceil($total / $perPage));
$page       = min(max(1, (int)($_GET['page'] ?? 1)), $totalPages);
$offset     = ($page - 1) * $perPage;

$stmt = $pdo->prepare(
    "SELECT r.request_id, r.patient_name, r.bags_required, r.hospital_name, r.urgency,
            r.required_date, r.status, r.created_at, bg.group_name
     FROM blood_requests r
     JOIN blood_groups bg ON bg.blood_group_id = r.blood_group_id
     WHERE $where
     ORDER BY r.created_at DESC, r.request_id DESC
     LIMIT $perPage OFFSET $offset"
);
$stmt->execute($params);
$requests = $stmt->fetchAll();

require_once __DIR__ . '/../includes/dash_header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-4">
    <div>
        <h1 class="bl-dash-title">My requests</h1>
        <p class="text-secondary mb-0">Follow the status of every blood request you sent.</p>
    </div>
    <a class="btn btn-primary" href="<?php echo BASE_URL; ?>/requester/create_request.php">New blood request</a>
</div>

<ul class="nav nav-pills bl-tabs mb-3">
    <?php foreach ($tabs as $key => [$label, $statuses]): ?>
        <li class="nav-item">
            <a class="nav-link<?php echo $show === $key ? ' active' : ''; ?>"
               href="<?php echo BASE_URL; ?>/requester/my_requests.php?show=<?php echo e($key); ?>">
                <?php echo e($label); ?> <span class="bl-tab-count"><?php echo (int)$tabCount[$key]; ?></span>
            </a>
        </li>
    <?php endforeach; ?>
</ul>

<div class="bl-panel">
    <?php if (!$requests): ?>
        <p class="mb-0 text-secondary">
            <?php echo $show === 'all' ? 'You have not sent a request yet.' : 'No requests in this list.'; ?>
        </p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr><th>#</th><th>Patient</th><th>Blood</th><th>Hospital</th><th>Needed on</th><th>Status</th><th></th></tr>
                </thead>
                <tbody>
                <?php foreach ($requests as $r): ?>
                    <tr>
                        <td><?php echo (int)$r['request_id']; ?></td>
                        <td>
                            <?php echo e($r['patient_name']); ?>
                            <?php if ($r['urgency'] === 'Emergency'): ?><span class="bl-status bl-status-rejected ms-1">Emergency</span><?php endif; ?>
                        </td>
                        <td><strong><?php echo e($r['group_name']); ?></strong> <span class="text-secondary small">&times;<?php echo (int)$r['bags_required']; ?></span></td>
                        <td><?php echo e($r['hospital_name']); ?></td>
                        <td class="text-nowrap"><?php echo e(date('d M Y', strtotime($r['required_date']))); ?></td>
                        <td><?php echo status_badge($r['status']); ?></td>
                        <td class="text-end">
                            <a class="btn btn-sm btn-outline-blood" href="<?php echo BASE_URL; ?>/requester/request_details.php?id=<?php echo (int)$r['request_id']; ?>">Details</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
            <nav class="mt-4" aria-label="Pages">
                <ul class="pagination justify-content-center mb-0">
                    <li class="page-item<?php echo $page <= 1 ? ' disabled' : ''; ?>">
                        <a class="page-link" href="?show=<?php echo e($show); ?>&amp;page=<?php echo $page - 1; ?>">Previous</a>
                    </li>
                    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                        <li class="page-item<?php echo $p === $page ? ' active' : ''; ?>">
                            <a class="page-link" href="?show=<?php echo e($show); ?>&amp;page=<?php echo $p; ?>"><?php echo $p; ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item<?php echo $page >= $totalPages ? ' disabled' : ''; ?>">
                        <a class="page-link" href="?show=<?php echo e($show); ?>&amp;page=<?php echo $page + 1; ?>">Next</a>
                    </li>
                </ul>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/dash_footer.php'; ?>
