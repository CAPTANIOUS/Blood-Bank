<?php
/*
 * admin/donor_view.php  -  Admin: everything about ONE donor.
 * Shows the profile, the medical note (for human review only), the
 * donation history and how the donor answered requests.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('admin');

$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare(
    "SELECT u.user_id, u.full_name, u.email, u.phone, u.is_active, u.created_at,
            d.date_of_birth, d.gender, d.address, d.area, d.last_donation_date,
            d.medical_issues, d.is_available,
            bg.group_name, di.district_name
     FROM users u
     JOIN donors d        ON d.user_id         = u.user_id
     JOIN blood_groups bg ON bg.blood_group_id = d.blood_group_id
     JOIN districts di    ON di.district_id    = d.district_id
     WHERE u.user_id = ? AND u.role = 'donor'"
);
$stmt->execute([$id]);
$donor = $stmt->fetch();

if (!$donor) {
    set_flash('danger', 'Donor not found.');
    redirect('/admin/manage_donors.php');
}

// Donation history (LEFT JOIN: a donation may have no request attached)
$stmt = $pdo->prepare(
    'SELECT dn.donation_date, dn.bags_donated, dn.notes, dn.request_id, r.patient_name
     FROM donations dn
     LEFT JOIN blood_requests r ON r.request_id = dn.request_id
     WHERE dn.donor_id = ?
     ORDER BY dn.donation_date DESC'
);
$stmt->execute([$id]);
$donations = $stmt->fetchAll();
$totalBags = array_sum(array_column($donations, 'bags_donated'));

// How this donor answered requests (GROUP BY)
$stmt = $pdo->prepare('SELECT response_status, COUNT(*) FROM donor_responses WHERE donor_id = ? GROUP BY response_status');
$stmt->execute([$id]);
$responses = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

$age = (new DateTime($donor['date_of_birth']))->diff(new DateTime('today'))->y;
$daysSince = $donor['last_donation_date']
    ? (new DateTime($donor['last_donation_date']))->diff(new DateTime('today'))->days
    : null;

$dashRole   = 'admin';
$activeFile = 'manage_donors.php';        // keeps "Donors" highlighted in the sidebar
$pageTitle  = 'Donor: ' . $donor['full_name'];
require_once __DIR__ . '/../includes/dash_header.php';
?>

<p class="mb-2"><a href="<?php echo BASE_URL; ?>/admin/manage_donors.php">&larr; All donors</a></p>

<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <h1 class="bl-dash-title mb-2"><?php echo e($donor['full_name']); ?></h1>
        <?php echo $donor['is_active']
            ? '<span class="bl-status bl-status-approved">Active account</span>'
            : '<span class="bl-status bl-status-rejected">Deactivated account</span>'; ?>
        <?php echo $donor['is_available']
            ? '<span class="bl-status bl-status-approved">Available</span>'
            : '<span class="bl-status bl-status-cancelled">Unavailable</span>'; ?>
    </div>
    <div class="d-flex gap-2">
        <a class="btn btn-outline-blood" href="<?php echo BASE_URL; ?>/admin/donor_edit.php?id=<?php echo (int)$id; ?>">Edit</a>
        <form method="post" action="<?php echo BASE_URL; ?>/admin/donor_status.php" class="m-0"
              data-confirm="<?php echo $donor['is_active']
                  ? 'Deactivate this donor? They will not be able to login and will disappear from Find Donors.'
                  : 'Reactivate this donor account?'; ?>">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="id" value="<?php echo (int)$id; ?>">
            <input type="hidden" name="make" value="<?php echo $donor['is_active'] ? 'deactivate' : 'activate'; ?>">
            <input type="hidden" name="back" value="view">
            <button type="submit" class="btn <?php echo $donor['is_active'] ? 'btn-outline-secondary' : 'btn-primary'; ?>">
                <?php echo $donor['is_active'] ? 'Deactivate' : 'Reactivate'; ?>
            </button>
        </form>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-6">
        <div class="bl-panel h-100">
            <h2 class="bl-panel-title">Profile</h2>
            <dl class="bl-dl mb-0">
                <dt>Blood group</dt><dd><strong><?php echo e($donor['group_name']); ?></strong></dd>
                <dt>Contact</dt><dd><?php echo e($donor['phone']); ?><br><?php echo e($donor['email']); ?></dd>
                <dt>Date of birth</dt><dd><?php echo e(date('d M Y', strtotime($donor['date_of_birth']))); ?> (<?php echo (int)$age; ?> years)</dd>
                <dt>Gender</dt><dd><?php echo e($donor['gender']); ?></dd>
                <dt>Address</dt><dd><?php echo e($donor['address']); ?><br><?php echo e($donor['area']); ?>, <?php echo e($donor['district_name']); ?></dd>
                <dt>Registered on</dt><dd><?php echo e(date('d M Y', strtotime($donor['created_at']))); ?></dd>
            </dl>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="bl-panel mb-3">
            <h2 class="bl-panel-title">Donation information</h2>
            <dl class="bl-dl mb-0">
                <dt>Last donation</dt>
                <dd>
                    <?php if ($donor['last_donation_date']): ?>
                        <?php echo e(date('d M Y', strtotime($donor['last_donation_date']))); ?> (<?php echo (int)$daysSince; ?> days ago)
                    <?php else: ?>
                        Never donated
                    <?php endif; ?>
                </dd>
                <dt>Donations recorded in BloodBank</dt>
                <dd><?php echo count($donations); ?> (<?php echo (int)$totalBags; ?> <?php echo $totalBags == 1 ? 'bag' : 'bags'; ?>)</dd>
                <dt>Answers to requests</dt>
                <dd>
                    Accepted <?php echo (int)($responses['Accepted'] ?? 0); ?> &middot;
                    Declined <?php echo (int)($responses['Declined'] ?? 0); ?> &middot;
                    Waiting <?php echo (int)($responses['Pending'] ?? 0); ?>
                </dd>
            </dl>
            <p class="small text-secondary mb-0">
                BloodBank does not decide who can donate. Waiting times and health checks are set by the
                blood bank or hospital, and a doctor decides on the day.
            </p>
        </div>

        <div class="bl-panel">
            <h2 class="bl-panel-title">Medical note written by the donor</h2>
            <?php if ($donor['medical_issues']): ?>
                <div class="alert alert-warning mb-2"><?php echo nl2br(e($donor['medical_issues'])); ?></div>
                <p class="small text-secondary mb-0">Flagged for your review. Check with the donor and the medical team before anyone is asked to donate.</p>
            <?php else: ?>
                <p class="mb-0 text-secondary">The donor did not write any medical issue.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="bl-panel">
    <h2 class="bl-panel-title">Donation history</h2>
    <?php if (!$donations): ?>
        <p class="mb-0 text-secondary">No donations are recorded for this donor yet.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>Date</th><th>Bags</th><th>For request</th><th>Notes</th></tr></thead>
                <tbody>
                <?php foreach ($donations as $dn): ?>
                    <tr>
                        <td class="text-nowrap"><?php echo e(date('d M Y', strtotime($dn['donation_date']))); ?></td>
                        <td><?php echo (int)$dn['bags_donated']; ?></td>
                        <td><?php echo $dn['request_id'] ? '#' . (int)$dn['request_id'] . ' &ndash; ' . e($dn['patient_name']) : '<span class="text-secondary">Not linked</span>'; ?></td>
                        <td><?php echo e($dn['notes'] ?? ''); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/dash_footer.php'; ?>
