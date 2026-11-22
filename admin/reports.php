<?php
/*
 * admin/reports.php  -  Admin: the numbers in one page (printable).
 * Every number comes from ONE SQL query, so each block is easy to explain:
 *   GROUP BY, COUNT, SUM, AVG, JOIN, subqueries, LEFT JOIN.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('admin');

$dashRole  = 'admin';
$pageTitle = 'Reports';

/* 1. Big numbers */
$totals = $pdo->query(
    "SELECT (SELECT COUNT(*) FROM blood_requests)                                   AS requests,
            (SELECT COUNT(*) FROM blood_requests WHERE status = 'Completed')        AS completed,
            (SELECT COUNT(*) FROM blood_requests WHERE status IN ('Pending','Under Review','Approved','Donor Matched')) AS open_requests,
            (SELECT COALESCE(SUM(bags_donated), 0) FROM donations)                  AS bags,
            (SELECT COUNT(*) FROM donations)                                        AS donations,
            (SELECT COUNT(*) FROM donors d JOIN users u ON u.user_id = d.user_id
              WHERE u.is_active = 1 AND d.is_available = 1)                         AS available_donors,
            (SELECT COUNT(*) FROM donors)                                           AS donors"
)->fetch();

/* 2. Requests by status (GROUP BY) */
$byStatus = $pdo->query('SELECT status, COUNT(*) FROM blood_requests GROUP BY status')->fetchAll(PDO::FETCH_KEY_PAIR);
$statuses = ['Pending', 'Under Review', 'Approved', 'Donor Matched', 'Completed', 'Rejected', 'Cancelled'];
$maxStatus = max(1, $byStatus ? max($byStatus) : 1);

/* 3. Blood still needed (open requests) against donors who are available - per blood group.
 *    The numbers come from the database VIEW v_blood_group_summary (see database/bloodbank.sql). */
$supply = $pdo->query(
    "SELECT group_name, bags_needed, donors_available
     FROM v_blood_group_summary
     ORDER BY FIELD(group_name, 'A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-')"
)->fetchAll();
$maxSupply = 1;
foreach ($supply as $s) { $maxSupply = max($maxSupply, (int)$s['bags_needed'], (int)$s['donors_available']); }

/* 4. Requests per district (top 8) */
$byDistrict = $pdo->query(
    'SELECT di.district_name, COUNT(*) AS n FROM blood_requests r JOIN districts di ON di.district_id = r.district_id
     GROUP BY di.district_id, di.district_name ORDER BY n DESC, di.district_name LIMIT 8'
)->fetchAll();
$maxDistrict = max(1, $byDistrict ? (int)$byDistrict[0]['n'] : 1);

/* 5. Last 6 months */
$months = $pdo->query(
    "SELECT DATE_FORMAT(created_at, '%Y-%m') AS m, DATE_FORMAT(MIN(created_at), '%b %Y') AS label,
            COUNT(*) AS n, SUM(status = 'Completed') AS done
     FROM blood_requests
     WHERE created_at >= DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 5 MONTH)
     GROUP BY m ORDER BY m"
)->fetchAll();
$maxMonth = 1;
foreach ($months as $m) { $maxMonth = max($maxMonth, (int)$m['n']); }

/* 6. Average time to complete (hours) and emergencies */
$speed = $pdo->query(
    "SELECT AVG(TIMESTAMPDIFF(HOUR, created_at, completed_at)) AS hours, COUNT(*) AS n
     FROM blood_requests WHERE status = 'Completed' AND completed_at IS NOT NULL"
)->fetch();
$emerg = $pdo->query("SELECT COUNT(*) FROM blood_requests WHERE urgency = 'Emergency'")->fetchColumn();

/* 6b. Normal and Emergency requests side by side (GROUP BY + COUNT + SUM + AVG) */
$byUrgency = $pdo->query(
    "SELECT urgency, COUNT(*) AS n, SUM(status = 'Completed') AS done, ROUND(AVG(bags_required), 1) AS avg_bags
     FROM blood_requests GROUP BY urgency ORDER BY FIELD(urgency, 'Emergency', 'Normal')"
)->fetchAll();

/* 7. Top donors (by bags) */
$top = $pdo->query(
    'SELECT u.user_id, u.full_name, bg.group_name, COUNT(*) AS times, SUM(dn.bags_donated) AS bags
     FROM donations dn JOIN users u ON u.user_id = dn.donor_id
     JOIN donors d ON d.user_id = dn.donor_id JOIN blood_groups bg ON bg.blood_group_id = d.blood_group_id
     GROUP BY u.user_id, u.full_name, bg.group_name ORDER BY bags DESC, times DESC, u.full_name LIMIT 5'
)->fetchAll();

require_once __DIR__ . '/../includes/dash_header.php';

/** a small horizontal bar, width in percent (always an integer: safe to print) */
function bar(int $value, int $max, string $class = ''): string
{
    $w = $max > 0 ? (int)round($value * 100 / $max) : 0;
    return '<div class="bl-bar ' . $class . '"><span style="width:' . max(0, min(100, $w)) . '%"></span></div>';
}
?>

<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
    <div>
        <h1 class="bl-dash-title mb-1">Reports</h1>
        <p class="text-secondary mb-0">Made on <?php echo e(date('d M Y, h:i A')); ?></p>
    </div>
    <button type="button" class="btn btn-outline-blood no-print" onclick="window.print()">Print this page</button>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3"><div class="bl-stat bl-stat-red"><div class="num"><?php echo (int)$totals['requests']; ?></div><div class="lbl">Requests in total</div></div></div>
    <div class="col-6 col-lg-3"><div class="bl-stat"><div class="num"><?php echo (int)$totals['open_requests']; ?></div><div class="lbl">Open requests</div></div></div>
    <div class="col-6 col-lg-3"><div class="bl-stat"><div class="num"><?php echo (int)$totals['completed']; ?></div><div class="lbl">Completed</div></div></div>
    <div class="col-6 col-lg-3"><div class="bl-stat"><div class="num"><?php echo (int)$totals['bags']; ?></div><div class="lbl">Bags donated (<?php echo (int)$totals['donations']; ?> donations)</div></div></div>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-6">
        <div class="bl-panel h-100">
            <h2 class="bl-panel-title">Requests by status</h2>
            <?php foreach ($statuses as $s): $n = (int)($byStatus[$s] ?? 0); ?>
                <div class="d-flex justify-content-between small mb-1"><span><?php echo status_badge($s); ?></span><strong><?php echo $n; ?></strong></div>
                <div class="mb-2"><?php echo bar($n, $maxStatus); ?></div>
            <?php endforeach; ?>
            <p class="small text-secondary mb-0 mt-2"><?php echo (int)$emerg; ?> of <?php echo (int)$totals['requests']; ?> requests were emergencies.
                <?php if ($speed['n'] > 0): ?>A completed request took <strong><?php echo e(number_format((float)$speed['hours'], 1)); ?> hours</strong> on average (from sent to completed).<?php endif; ?></p>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="bl-panel h-100">
            <h2 class="bl-panel-title">Blood needed and donors available</h2>
            <p class="small text-secondary">Bags still needed by open requests, against donors who are active and available.</p>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><th>Group</th><th>Bags needed</th><th>Donors available</th></tr></thead>
                    <tbody>
                    <?php foreach ($supply as $s): $short = (int)$s['bags_needed'] > (int)$s['donors_available']; ?>
                        <tr>
                            <td><strong><?php echo e($s['group_name']); ?></strong></td>
                            <td style="min-width:120px"><div class="small"><?php echo (int)$s['bags_needed']; ?></div><?php echo bar((int)$s['bags_needed'], $maxSupply); ?></td>
                            <td style="min-width:120px"><div class="small"><?php echo (int)$s['donors_available']; ?>
                                <?php if ($short): ?><span class="bl-status bl-status-rejected ms-1">Not enough</span><?php endif; ?></div><?php echo bar((int)$s['donors_available'], $maxSupply, 'alt'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-6">
        <div class="bl-panel h-100">
            <h2 class="bl-panel-title">Last 6 months</h2>
            <?php if (!$months): ?><p class="text-secondary mb-0">No request in the last 6 months.</p><?php endif; ?>
            <?php foreach ($months as $m): ?>
                <div class="d-flex justify-content-between small mb-1"><span><?php echo e($m['label']); ?></span><span><strong><?php echo (int)$m['n']; ?></strong> sent, <?php echo (int)$m['done']; ?> completed</span></div>
                <div class="mb-2"><?php echo bar((int)$m['n'], $maxMonth); ?></div>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="bl-panel h-100">
            <h2 class="bl-panel-title">Busiest districts</h2>
            <?php if (!$byDistrict): ?><p class="text-secondary mb-0">No request yet.</p><?php endif; ?>
            <?php foreach ($byDistrict as $d): ?>
                <div class="d-flex justify-content-between small mb-1"><span><?php echo e($d['district_name']); ?></span><strong><?php echo (int)$d['n']; ?></strong></div>
                <div class="mb-2"><?php echo bar((int)$d['n'], $maxDistrict); ?></div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="bl-panel mb-3">
    <h2 class="bl-panel-title">Emergency and normal requests</h2>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>Type</th><th>Requests</th><th>Completed</th><th>Average bags</th></tr></thead>
            <tbody>
            <?php foreach ($byUrgency as $u): ?>
                <tr>
                    <td><strong><?php echo e($u['urgency']); ?></strong></td>
                    <td><?php echo (int)$u['n']; ?></td>
                    <td><?php echo (int)$u['done']; ?></td>
                    <td><?php echo e($u['avg_bags']); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="bl-panel">
    <h2 class="bl-panel-title">Top donors</h2>
    <p class="small text-secondary">Donors: <?php echo (int)$totals['donors']; ?> registered, <?php echo (int)$totals['available_donors']; ?> active and available now.</p>
    <?php if (!$top): ?>
        <p class="mb-0 text-secondary">No donation is recorded yet.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>Donor</th><th>Blood</th><th>Donations</th><th>Bags</th></tr></thead>
                <tbody>
                <?php foreach ($top as $t): ?>
                    <tr>
                        <td><a href="<?php echo BASE_URL; ?>/admin/donor_view.php?id=<?php echo (int)$t['user_id']; ?>"><?php echo e($t['full_name']); ?></a></td>
                        <td><strong><?php echo e($t['group_name']); ?></strong></td>
                        <td><?php echo (int)$t['times']; ?></td>
                        <td><?php echo (int)$t['bags']; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/dash_footer.php'; ?>
