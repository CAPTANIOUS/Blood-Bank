<?php
/*
 * admin/manage_requests.php  -  Admin: every blood request in one list.
 *
 * Filters: text (request number, patient, hospital, requester), status, urgency,
 *          blood group, district. 15 per page. Open emergencies come first.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('admin');

$dashRole  = 'admin';
$pageTitle = 'Blood requests';

$statuses    = ['Pending', 'Under Review', 'Approved', 'Donor Matched', 'Completed', 'Rejected', 'Cancelled'];
$bloodGroups = $pdo->query('SELECT blood_group_id, group_name FROM blood_groups ORDER BY blood_group_id')->fetchAll();
$districts   = $pdo->query('SELECT district_id, district_name FROM districts ORDER BY district_name')->fetchAll();

/* 1. Read the filters (anything unexpected is ignored) */
$q          = substr(clean($_GET['q'] ?? ''), 0, 100);
$status     = in_array($_GET['status'] ?? '', $statuses, true) ? $_GET['status'] : '';
$urgency    = in_array($_GET['urgency'] ?? '', ['Normal', 'Emergency'], true) ? $_GET['urgency'] : '';
$groupId    = (int)($_GET['group'] ?? 0);
$districtId = (int)($_GET['district'] ?? 0);
if (!in_array($groupId, array_column($bloodGroups, 'blood_group_id')))   { $groupId = 0; }
if (!in_array($districtId, array_column($districts, 'district_id')))     { $districtId = 0; }

/* 2. WHERE part */
$where  = ['1 = 1'];
$params = [];
if ($q !== '') {
    $like    = '%' . addcslashes($q, '%_\\') . '%';
    $where[] = '(r.patient_name LIKE ? OR r.hospital_name LIKE ? OR r.requester_name LIKE ? OR r.contact_phone LIKE ? OR r.request_id = ?)';
    array_push($params, $like, $like, $like, $like, ltrim($q, '#'));
}
if ($status !== '')   { $where[] = 'r.status = ?';         $params[] = $status; }
if ($urgency !== '')  { $where[] = 'r.urgency = ?';        $params[] = $urgency; }
if ($groupId > 0)     { $where[] = 'r.blood_group_id = ?'; $params[] = $groupId; }
if ($districtId > 0)  { $where[] = 'r.district_id = ?';    $params[] = $districtId; }
$whereSql = implode(' AND ', $where);

/* 3. Counts per status for the whole system (GROUP BY) */
$counts = $pdo->query('SELECT status, COUNT(*) FROM blood_requests GROUP BY status')->fetchAll(PDO::FETCH_KEY_PAIR);
$allCount = array_sum($counts);

/* 4. Count + one page */
$from = 'FROM blood_requests r
         JOIN blood_groups bg ON bg.blood_group_id = r.blood_group_id
         JOIN districts di    ON di.district_id    = r.district_id';
$stmt = $pdo->prepare("SELECT COUNT(*) $from WHERE $whereSql");
$stmt->execute($params);
$total = (int)$stmt->fetchColumn();

$perPage    = 15;
$totalPages = max(1, (int)ceil($total / $perPage));
$page       = min(max(1, (int)($_GET['page'] ?? 1)), $totalPages);
$offset     = ($page - 1) * $perPage;

$stmt = $pdo->prepare(
    "SELECT r.request_id, r.patient_name, r.hospital_name, r.urgency, r.status, r.bags_required,
            r.required_date, r.created_at, bg.group_name, di.district_name,
            (SELECT COUNT(*) FROM donor_responses x WHERE x.request_id = r.request_id AND x.response_status = 'Accepted') AS accepted,
            (SELECT COUNT(*) FROM donor_responses x WHERE x.request_id = r.request_id AND x.response_status = 'Pending')  AS waiting
     $from
     WHERE $whereSql
     ORDER BY (r.urgency = 'Emergency' AND r.status IN ('Pending','Under Review','Approved','Donor Matched')) DESC,
              r.required_date ASC, r.request_id DESC
     LIMIT $perPage OFFSET $offset"
);
$stmt->execute($params);
$requests = $stmt->fetchAll();

$filters = array_filter(['q' => $q, 'status' => $status, 'urgency' => $urgency, 'group' => $groupId ?: '', 'district' => $districtId ?: ''],
                        fn($v) => $v !== '');
function req_page_url(array $filters, int $p): string
{
    return BASE_URL . '/admin/manage_requests.php?' . http_build_query($filters + ['page' => $p]);
}

require_once __DIR__ . '/../includes/dash_header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
    <div>
        <h1 class="bl-dash-title mb-1">Blood requests</h1>
        <p class="text-secondary mb-0">Review, approve, match donors and complete requests.</p>
    </div>
    <?php if (page_url('admin/add_request.php')): ?>
        <a class="btn btn-primary" href="<?php echo BASE_URL; ?>/admin/add_request.php">Add a request</a>
    <?php endif; ?>
</div>

<!-- status tabs with counts -->
<ul class="bl-tabs nav mb-3">
    <li class="nav-item"><a class="nav-link<?php echo $status === '' ? ' active' : ''; ?>" href="<?php echo e(BASE_URL . '/admin/manage_requests.php'); ?>">All <span class="bl-tab-count"><?php echo (int)$allCount; ?></span></a></li>
    <?php foreach ($statuses as $s): ?>
        <li class="nav-item">
            <a class="nav-link<?php echo $status === $s ? ' active' : ''; ?>" href="<?php echo e(BASE_URL . '/admin/manage_requests.php?status=' . urlencode($s)); ?>">
                <?php echo e($s); ?> <span class="bl-tab-count"><?php echo (int)($counts[$s] ?? 0); ?></span>
            </a>
        </li>
    <?php endforeach; ?>
</ul>

<form method="get" action="" class="bl-filter mb-4">
    <div class="row g-3 align-items-end">
        <div class="col-md-4">
            <label for="q" class="form-label">Number, patient, hospital, requester or phone</label>
            <input type="text" class="form-control" id="q" name="q" maxlength="100" value="<?php echo e($q); ?>">
        </div>
        <div class="col-6 col-md-2">
            <label for="status" class="form-label">Status</label>
            <select class="form-select" id="status" name="status">
                <option value="">Any</option>
                <?php foreach ($statuses as $s): ?><option<?php echo selected($status, $s); ?>><?php echo e($s); ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label for="urgency" class="form-label">Urgency</label>
            <select class="form-select" id="urgency" name="urgency">
                <option value="">Any</option>
                <option<?php echo selected($urgency, 'Normal'); ?>>Normal</option>
                <option<?php echo selected($urgency, 'Emergency'); ?>>Emergency</option>
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label for="group" class="form-label">Blood group</label>
            <select class="form-select" id="group" name="group">
                <option value="">Any</option>
                <?php foreach ($bloodGroups as $bg): ?>
                    <option value="<?php echo (int)$bg['blood_group_id']; ?>"<?php echo selected($groupId, $bg['blood_group_id']); ?>><?php echo e($bg['group_name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label for="district" class="form-label">District</label>
            <select class="form-select" id="district" name="district">
                <option value="">Any</option>
                <?php foreach ($districts as $di): ?>
                    <option value="<?php echo (int)$di['district_id']; ?>"<?php echo selected($districtId, $di['district_id']); ?>><?php echo e($di['district_name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 d-flex gap-2">
            <button type="submit" class="btn btn-primary">Search</button>
            <a href="<?php echo BASE_URL; ?>/admin/manage_requests.php" class="btn btn-outline-blood">Clear</a>
        </div>
    </div>
</form>

<div class="bl-panel">
    <p class="text-secondary small"><?php echo $total; ?> <?php echo $total === 1 ? 'request' : 'requests'; ?> found</p>
    <?php if (!$requests): ?>
        <p class="mb-0 text-secondary">No request matches these filters.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr><th>#</th><th>Patient</th><th>Blood</th><th>Hospital</th><th>Needed on</th><th>Status</th><th>Donors</th><th></th></tr>
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
                        <td><?php echo e($r['hospital_name']); ?><div class="small text-secondary"><?php echo e($r['district_name']); ?></div></td>
                        <td class="text-nowrap"><?php echo e(date('d M Y', strtotime($r['required_date']))); ?></td>
                        <td><?php echo status_badge($r['status']); ?></td>
                        <td class="small text-nowrap"><?php echo (int)$r['accepted']; ?> accepted<br><span class="text-secondary"><?php echo (int)$r['waiting']; ?> waiting</span></td>
                        <td class="text-end"><a class="btn btn-sm btn-outline-blood" href="<?php echo BASE_URL; ?>/admin/request_view.php?id=<?php echo (int)$r['request_id']; ?>">Open</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
            <nav class="mt-4" aria-label="Pages">
                <ul class="pagination justify-content-center mb-0 flex-wrap">
                    <li class="page-item<?php echo $page <= 1 ? ' disabled' : ''; ?>"><a class="page-link" href="<?php echo e(req_page_url($filters, $page - 1)); ?>">Previous</a></li>
                    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                        <li class="page-item<?php echo $p === $page ? ' active' : ''; ?>"><a class="page-link" href="<?php echo e(req_page_url($filters, $p)); ?>"><?php echo $p; ?></a></li>
                    <?php endfor; ?>
                    <li class="page-item<?php echo $page >= $totalPages ? ' disabled' : ''; ?>"><a class="page-link" href="<?php echo e(req_page_url($filters, $page + 1)); ?>">Next</a></li>
                </ul>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/dash_footer.php'; ?>
