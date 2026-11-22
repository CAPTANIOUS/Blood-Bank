<?php
/*
 * admin/dashboard.php  -  Admin home: totals and the newest requests.
 * The SQL here uses COUNT, GROUP BY, JOIN and ORDER BY ... LIMIT.
 */
$dashRole  = 'admin';
$pageTitle = 'Admin Dashboard';

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/dash_header.php';    // also checks: only an admin may enter

// 1. Totals (COUNT)
$totalDonors   = (int)$pdo->query('SELECT COUNT(*) FROM donors')->fetchColumn();
$totalRequests = (int)$pdo->query('SELECT COUNT(*) FROM blood_requests')->fetchColumn();

// 2. How many requests in each status (GROUP BY). Result: ['Pending' => 1, 'Approved' => 1, ...]
$statusCounts = $pdo->query('SELECT status, COUNT(*) AS total FROM blood_requests GROUP BY status')
                    ->fetchAll(PDO::FETCH_KEY_PAIR);

$statuses    = ['Pending', 'Under Review', 'Approved', 'Donor Matched', 'Completed', 'Rejected', 'Cancelled'];
$needsReview = ($statusCounts['Pending'] ?? 0) + ($statusCounts['Under Review'] ?? 0);

// 3. The 5 newest requests (JOIN to get readable names instead of ID numbers)
$recent = $pdo->query(
    'SELECT r.request_id, r.patient_name, bg.group_name, d.district_name,
            r.hospital_name, r.urgency, r.status, r.created_at
     FROM blood_requests r
     JOIN blood_groups bg ON bg.blood_group_id = r.blood_group_id
     JOIN districts d     ON d.district_id     = r.district_id
     ORDER BY r.created_at DESC
     LIMIT 5'
)->fetchAll();
?>

<h1 class="bl-dash-title">Dashboard</h1>
<p class="text-secondary mb-4">A quick look at BloodBank today.</p>

<div class="row g-3 mb-4">
    <div class="col-6 col-xl-3">
        <div class="bl-stat">
            <div class="num"><?php echo $totalDonors; ?></div>
            <div class="lbl">Registered donors</div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="bl-stat">
            <div class="num"><?php echo $totalRequests; ?></div>
            <div class="lbl">Blood requests</div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="bl-stat bl-stat-alert">
            <div class="num"><?php echo $needsReview; ?></div>
            <div class="lbl">Waiting for review</div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="bl-stat">
            <div class="num"><?php echo (int)($statusCounts['Completed'] ?? 0); ?></div>
            <div class="lbl">Completed</div>
        </div>
    </div>
</div>

<div class="bl-panel mb-4">
    <h2 class="bl-panel-title">Requests by status</h2>
    <div class="d-flex flex-wrap gap-3">
        <?php foreach ($statuses as $s): ?>
            <div class="bl-status-count">
                <?php echo status_badge($s); ?>
                <strong><?php echo (int)($statusCounts[$s] ?? 0); ?></strong>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="bl-panel">
    <h2 class="bl-panel-title">Newest requests</h2>

    <?php if (!$recent): ?>
        <p class="text-secondary mb-0">No requests have been sent yet.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>#</th><th>Patient</th><th>Blood</th><th>Hospital</th><th>District</th><th>Urgency</th><th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent as $r): ?>
                        <tr>
                            <td><?php echo (int)$r['request_id']; ?></td>
                            <td><?php echo e($r['patient_name']); ?></td>
                            <td><strong><?php echo e($r['group_name']); ?></strong></td>
                            <td><?php echo e($r['hospital_name']); ?></td>
                            <td><?php echo e($r['district_name']); ?></td>
                            <td><?php echo e($r['urgency']); ?></td>
                            <td><?php echo status_badge($r['status']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/dash_footer.php'; ?>
