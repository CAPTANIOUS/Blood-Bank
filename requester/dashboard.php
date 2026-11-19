<?php
/*
 * requester/dashboard.php  -  Requester home: my requests at a glance.
 */
$dashRole  = 'requester';
$pageTitle = 'Requester Dashboard';

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/dash_header.php';    // also checks: only a requester may enter

$userId = $me['id'];

// How many of MY requests are in each status (GROUP BY ... WHERE requester_id = me)
$stmt = $pdo->prepare('SELECT status, COUNT(*) AS total FROM blood_requests WHERE requester_id = ? GROUP BY status');
$stmt->execute([$userId]);
$counts = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

$total  = array_sum($counts);
$active = ($counts['Pending'] ?? 0) + ($counts['Under Review'] ?? 0)
        + ($counts['Approved'] ?? 0) + ($counts['Donor Matched'] ?? 0);

// My 5 newest requests
$stmt = $pdo->prepare(
    'SELECT r.request_id, r.patient_name, bg.group_name, r.hospital_name, r.required_date, r.status
     FROM blood_requests r
     JOIN blood_groups bg ON bg.blood_group_id = r.blood_group_id
     WHERE r.requester_id = ?
     ORDER BY r.created_at DESC
     LIMIT 5'
);
$stmt->execute([$userId]);
$recent = $stmt->fetchAll();

$newRequestUrl = page_url('requester/create_request.php');
$myRequestsUrl = page_url('requester/my_requests.php');
?>

<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-4">
    <div>
        <h1 class="bl-dash-title">Hello, <?php echo e($me['name']); ?></h1>
        <p class="text-secondary mb-0">Follow your blood requests here.</p>
    </div>
    <?php if ($newRequestUrl): ?>
        <a class="btn btn-primary" href="<?php echo e($newRequestUrl); ?>">New blood request</a>
    <?php endif; ?>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="bl-stat"><div class="num"><?php echo $total; ?></div><div class="lbl">All my requests</div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="bl-stat bl-stat-alert"><div class="num"><?php echo $active; ?></div><div class="lbl">Still open</div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="bl-stat"><div class="num"><?php echo (int)($counts['Completed'] ?? 0); ?></div><div class="lbl">Completed</div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="bl-stat"><div class="num"><?php echo (int)($counts['Rejected'] ?? 0) + (int)($counts['Cancelled'] ?? 0); ?></div><div class="lbl">Rejected or cancelled</div></div>
    </div>
</div>

<div class="bl-panel">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <h2 class="bl-panel-title mb-0">My latest requests</h2>
        <?php if ($myRequestsUrl && $recent): ?>
            <a href="<?php echo e($myRequestsUrl); ?>">See all</a>
        <?php endif; ?>
    </div>

    <?php if (!$recent): ?>
        <p class="text-secondary mb-0">
            You have not sent a request yet.
            <?php if ($newRequestUrl): ?>
                <a href="<?php echo e($newRequestUrl); ?>">Send your first request</a>.
            <?php endif; ?>
        </p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr><th>#</th><th>Patient</th><th>Blood</th><th>Hospital</th><th>Needed on</th><th>Status</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($recent as $r): ?>
                        <tr>
                            <td><?php echo (int)$r['request_id']; ?></td>
                            <td><?php echo e($r['patient_name']); ?></td>
                            <td><strong><?php echo e($r['group_name']); ?></strong></td>
                            <td><?php echo e($r['hospital_name']); ?></td>
                            <td><?php echo e(date('d M Y', strtotime($r['required_date']))); ?></td>
                            <td><?php echo status_badge($r['status']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/dash_footer.php'; ?>
