<?php
/*
 * donor/donation_history.php  -  Every donation recorded for this donor.
 * Donations are recorded by the admin after the blood is really given.
 */
$dashRole  = 'donor';
$pageTitle = 'Donation history';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/dash_header.php';

$stmt = $pdo->prepare(
    'SELECT dn.donation_date, dn.bags_donated, dn.notes, dn.request_id, r.hospital_name
     FROM donations dn
     LEFT JOIN blood_requests r ON r.request_id = dn.request_id
     WHERE dn.donor_id = ?
     ORDER BY dn.donation_date DESC, dn.donation_id DESC'
);
$stmt->execute([$me['id']]);
$rows = $stmt->fetchAll();
$bags = array_sum(array_column($rows, 'bags_donated'));
// The newest donation is first in the list (ORDER BY ... DESC), so it gives the "last" date
$lastDate  = $rows ? $rows[0]['donation_date'] : null;
$daysSince = $lastDate ? (int)(new DateTime($lastDate))->diff(new DateTime('today'))->days : null;
?>

<h1 class="bl-dash-title">Donation history</h1>
<p class="text-secondary mb-4">Donations recorded for you by the BloodBank admin.</p>

<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3"><div class="bl-stat bl-stat-red"><div class="num"><?php echo count($rows); ?></div><div class="lbl">Donations</div></div></div>
    <div class="col-6 col-lg-3"><div class="bl-stat"><div class="num"><?php echo (int)$bags; ?></div><div class="lbl">Bags given</div></div></div>
    <?php if ($lastDate): ?>
    <div class="col-6 col-lg-3"><div class="bl-stat"><div class="num"><?php echo e(date('d M', strtotime($lastDate))); ?></div><div class="lbl">Last donation (<?php echo e(date('Y', strtotime($lastDate))); ?>)</div></div></div>
    <div class="col-6 col-lg-3"><div class="bl-stat"><div class="num"><?php echo $daysSince; ?></div><div class="lbl">Days since last donation</div></div></div>
    <?php endif; ?>
</div>

<div class="bl-panel">
    <?php if (!$rows): ?>
        <p class="mb-0 text-secondary">No donation is recorded yet. When you donate for a request, the admin will add it here.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>Date</th><th>Bags</th><th>For</th><th>Notes</th></tr></thead>
                <tbody>
                <?php foreach ($rows as $d): ?>
                    <tr>
                        <td class="text-nowrap"><?php echo e(date('d M Y', strtotime($d['donation_date']))); ?></td>
                        <td><?php echo (int)$d['bags_donated']; ?></td>
                        <td><?php echo $d['request_id'] ? 'Request #' . (int)$d['request_id'] . ($d['hospital_name'] ? ' &ndash; ' . e($d['hospital_name']) : '') : '<span class="text-secondary">Walk-in / camp</span>'; ?></td>
                        <td><?php echo e($d['notes'] ?? ''); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/dash_footer.php'; ?>
