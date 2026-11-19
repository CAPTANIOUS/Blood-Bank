<?php
/*
 * donor/dashboard.php  -  Donor home: my profile summary and counts.
 */
$dashRole  = 'donor';
$pageTitle = 'Donor Dashboard';

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/dash_header.php';    // also checks: only a donor may enter

$donorId = $me['id'];

// My donor details (JOIN to show the blood group and district as words)
$stmt = $pdo->prepare(
    'SELECT d.last_donation_date, d.is_available, d.area, d.blood_group_id, d.district_id,
            bg.group_name, di.district_name
     FROM donors d
     JOIN blood_groups bg ON bg.blood_group_id = d.blood_group_id
     JOIN districts di    ON di.district_id    = d.district_id
     WHERE d.user_id = ?'
);
$stmt->execute([$donorId]);
$donor = $stmt->fetch();

if (!$donor) {                       // an account with the donor role but no donor row
    echo '<div class="alert alert-danger">Your donor profile is missing. Please contact the admin.</div>';
    require_once __DIR__ . '/../includes/dash_footer.php';
    exit;
}

// Approved requests that need MY blood group in MY district
$stmt = $pdo->prepare(
    "SELECT COUNT(*) FROM blood_requests
     WHERE status = 'Approved' AND blood_group_id = ? AND district_id = ?"
);
$stmt->execute([$donor['blood_group_id'], $donor['district_id']]);
$matching = (int)$stmt->fetchColumn();

// Requests where an admin asked me and I have not answered yet
$stmt = $pdo->prepare("SELECT COUNT(*) FROM donor_responses WHERE donor_id = ? AND response_status = 'Pending'");
$stmt->execute([$donorId]);
$waiting = (int)$stmt->fetchColumn();

// My donation history: how many donations and bags (SUM)
$stmt = $pdo->prepare('SELECT COUNT(*) AS times, COALESCE(SUM(bags_donated), 0) AS bags FROM donations WHERE donor_id = ?');
$stmt->execute([$donorId]);
$history = $stmt->fetch();

// Days since the last donation
$daysSince = null;
if ($donor['last_donation_date']) {
    $daysSince = (new DateTime($donor['last_donation_date']))->diff(new DateTime('today'))->days;
}

$requestsUrl = page_url('donor/requests.php');
?>

<h1 class="bl-dash-title">Hello, <?php echo e($me['name']); ?></h1>
<p class="text-secondary mb-4">Here is your donor summary.</p>

<div class="row g-3 mb-4">
    <div class="col-6 col-xl-3">
        <div class="bl-stat bl-stat-red">
            <div class="num"><?php echo e($donor['group_name']); ?></div>
            <div class="lbl">Your blood group</div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="bl-stat">
            <div class="num fs-5 pt-2">
                <?php if ($donor['is_available']): ?>
                    <span class="bl-status bl-status-approved">Available</span>
                <?php else: ?>
                    <span class="bl-status bl-status-cancelled">Unavailable</span>
                <?php endif; ?>
            </div>
            <div class="lbl mt-2">Your availability</div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="bl-stat">
            <div class="num"><?php echo $matching; ?></div>
            <div class="lbl">Matching requests</div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="bl-stat">
            <div class="num"><?php echo $waiting; ?></div>
            <div class="lbl">Waiting for your answer</div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="bl-panel h-100">
            <h2 class="bl-panel-title">Your details</h2>
            <dl class="bl-dl mb-0">
                <dt>Location</dt>
                <dd><?php echo e($donor['area']); ?>, <?php echo e($donor['district_name']); ?></dd>
                <dt>Last donation</dt>
                <dd>
                    <?php if ($donor['last_donation_date']): ?>
                        <?php echo e(date('d M Y', strtotime($donor['last_donation_date']))); ?>
                        (<?php echo (int)$daysSince; ?> days ago)
                    <?php else: ?>
                        Not donated yet
                    <?php endif; ?>
                </dd>
                <dt>Donations with BloodBank</dt>
                <dd>
                    <?php echo (int)$history['times']; ?> <?php echo $history['times'] == 1 ? 'donation' : 'donations'; ?>,
                    <?php echo (int)$history['bags']; ?> <?php echo $history['bags'] == 1 ? 'bag' : 'bags'; ?>
                </dd>
            </dl>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="bl-panel h-100">
            <h2 class="bl-panel-title">A reminder</h2>
            <p class="mb-3">
                Blood banks and hospitals set their own waiting time between donations, and a doctor or
                nurse decides on the day whether you can donate. BloodBank cannot make that decision.
            </p>
            <?php if ($requestsUrl): ?>
                <a class="btn btn-primary" href="<?php echo e($requestsUrl); ?>">See matching requests</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/dash_footer.php'; ?>
