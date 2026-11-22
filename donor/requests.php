<?php
/*
 * donor/requests.php  -  Requests the donor can help with.
 *   1. Asked of you   : an admin matched you and is waiting for your answer
 *   2. Matching       : approved requests for YOUR blood group in YOUR district (you may volunteer)
 *   3. Your answers   : what you accepted / declined
 * Patient name and contact details are shown ONLY after the donor accepts.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/donor_actions.php';

require_role('donor');                       // guard first: we may redirect before any HTML
$me      = current_user();
$donorId = $me['id'];

// ---------- an answer was sent ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'Your session expired. Please try again.');
    } else {
        [$ok, $msg] = donor_respond(
            $pdo, $donorId, (int)($_POST['request_id'] ?? 0),
            (string)($_POST['action'] ?? ''), clean($_POST['note'] ?? '')
        );
        set_flash($ok ? 'success' : 'danger', $msg);
    }
    redirect('/donor/requests.php');
}

$stmt = $pdo->prepare(
    'SELECT d.blood_group_id, d.district_id, d.is_available, d.last_donation_date, bg.group_name, di.district_name
     FROM donors d
     JOIN blood_groups bg ON bg.blood_group_id = d.blood_group_id
     JOIN districts di    ON di.district_id    = d.district_id
     WHERE d.user_id = ?'
);
$stmt->execute([$donorId]);
$donor = $stmt->fetch();

$base = "SELECT r.request_id, r.patient_name, r.requester_name, r.contact_phone, r.bags_required, r.medical_condition,
                r.hospital_name, r.hospital_address, r.area, r.urgency, r.required_date, r.required_time, r.status,
                bg.group_name, di.district_name,
                resp.response_status, resp.donor_note
         FROM blood_requests r
         JOIN blood_groups bg ON bg.blood_group_id = r.blood_group_id
         JOIN districts di    ON di.district_id    = r.district_id
         LEFT JOIN donor_responses resp ON resp.request_id = r.request_id AND resp.donor_id = ?";

// 1. Asked of me, still open
$stmt = $pdo->prepare($base . " WHERE resp.response_status = 'Pending' AND r.status IN ('Approved','Donor Matched')
                                ORDER BY (r.urgency = 'Emergency') DESC, r.required_date");
$stmt->execute([$donorId]);
$asked = $stmt->fetchAll();

// 2. Matching approved requests I have not answered (same blood group + district)
$stmt = $pdo->prepare($base . " WHERE resp.response_id IS NULL AND r.status = 'Approved'
                                AND r.blood_group_id = ? AND r.district_id = ?
                                ORDER BY (r.urgency = 'Emergency') DESC, r.required_date LIMIT 30");
$stmt->execute([$donorId, $donor['blood_group_id'], $donor['district_id']]);
$matching = $stmt->fetchAll();

// 3. My answers (newest 20)
$stmt = $pdo->prepare($base . " WHERE resp.response_status IN ('Accepted','Declined')
                                ORDER BY resp.responded_at DESC LIMIT 20");
$stmt->execute([$donorId]);
$answers = $stmt->fetchAll();

$daysSince = $donor['last_donation_date']
    ? (new DateTime($donor['last_donation_date']))->diff(new DateTime('today'))->days : null;

/** One request card (used by the three lists) */
function request_card(array $r, string $mode, bool $available): void
{
    $accepted = ($r['response_status'] === 'Accepted');
    $isOpen   = in_array($r['status'], ['Approved', 'Donor Matched'], true);
    $when     = date('d M Y', strtotime($r['required_date']))
              . ($r['required_time'] ? ' at ' . date('h:i A', strtotime($r['required_time'])) : '');
    ?>
    <div class="bl-panel mb-3">
        <div class="d-flex flex-wrap justify-content-between gap-2 mb-2">
            <div>
                <span class="fs-4 fw-bold text-danger"><?php echo e($r['group_name']); ?></span>
                <span class="text-secondary">&times; <?php echo (int)$r['bags_required']; ?> <?php echo $r['bags_required'] == 1 ? 'bag' : 'bags'; ?></span>
                <?php if ($r['urgency'] === 'Emergency'): ?><span class="bl-status bl-status-rejected ms-1">Emergency</span><?php endif; ?>
            </div>
            <div><?php echo status_badge($r['status']); ?></div>
        </div>
        <dl class="bl-dl mb-3">
            <dt>Needed</dt><dd><?php echo e($when); ?></dd>
            <dt>Hospital</dt>
            <dd><?php echo e($r['hospital_name']); ?>, <?php echo e($r['area']); ?>, <?php echo e($r['district_name']); ?>
                <?php if ($accepted && $isOpen): ?><br><span class="text-secondary"><?php echo e($r['hospital_address']); ?></span><?php endif; ?></dd>
            <dt>Reason</dt><dd><?php echo e($r['medical_condition']); ?></dd>
            <?php if ($accepted && $isOpen): ?>
                <dt>Patient</dt><dd><?php echo e($r['patient_name']); ?></dd>
                <dt>Contact</dt><dd><?php echo e($r['requester_name']); ?>, <a href="tel:<?php echo e($r['contact_phone']); ?>"><?php echo e($r['contact_phone']); ?></a></dd>
            <?php endif; ?>
            <?php if ($mode === 'answers'): ?>
                <dt>Your answer</dt>
                <dd><?php echo $accepted ? '<span class="bl-status bl-status-approved">Accepted</span>' : '<span class="bl-status bl-status-cancelled">Declined</span>'; ?>
                    <?php if ($r['donor_note']): ?><span class="text-secondary ms-2"><?php echo e($r['donor_note']); ?></span><?php endif; ?></dd>
            <?php endif; ?>
        </dl>

        <?php if ($mode === 'asked' || $mode === 'matching'): ?>
            <form method="post" class="row g-2 align-items-end" data-confirm="<?php echo $mode === 'asked' ? 'Send your answer?' : 'Volunteer for this request?'; ?>">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="request_id" value="<?php echo (int)$r['request_id']; ?>">
                <div class="col-md-6">
                    <label class="form-label small mb-1" for="note<?php echo (int)$r['request_id']; ?>">Note for the admin (optional)</label>
                    <input class="form-control form-control-sm" type="text" id="note<?php echo (int)$r['request_id']; ?>" name="note" maxlength="255">
                </div>
                <div class="col-md-6 d-flex gap-2">
                    <?php if ($mode === 'asked'): ?>
                        <button class="btn btn-primary" name="action" value="accept" <?php echo $available ? '' : 'disabled'; ?>>Accept</button>
                        <button class="btn btn-outline-secondary" name="action" value="decline">Decline</button>
                    <?php else: ?>
                        <button class="btn btn-primary" name="action" value="volunteer" <?php echo $available ? '' : 'disabled'; ?>>I can donate</button>
                    <?php endif; ?>
                </div>
            </form>
        <?php elseif ($accepted && $isOpen): ?>
            <form method="post" class="m-0" data-confirm="Withdraw your acceptance? The admin and the requester will be told.">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="request_id" value="<?php echo (int)$r['request_id']; ?>">
                <input type="hidden" name="note" value="Withdrawn by the donor">
                <button class="btn btn-sm btn-outline-secondary" name="action" value="withdraw">Withdraw</button>
            </form>
        <?php endif; ?>
    </div>
    <?php
}

$dashRole  = 'donor';
$pageTitle = 'Matching requests';
require_once __DIR__ . '/../includes/dash_header.php';
?>

<h1 class="bl-dash-title">Matching requests</h1>
<p class="text-secondary mb-3">Your blood group is <strong><?php echo e($donor['group_name']); ?></strong> in <strong><?php echo e($donor['district_name']); ?></strong>.</p>

<?php if (!$donor['is_available']): ?>
    <div class="alert alert-warning">Your profile says you are <strong>not available</strong>, so you cannot accept requests. You can change this in <a href="<?php echo BASE_URL; ?>/donor/profile.php">My profile</a>.</div>
<?php endif; ?>

<div class="alert alert-info">
    <strong>Please remember:</strong> BloodBank does not decide if you are fit to donate. Blood banks and hospitals have their own waiting
    times and health checks, and a doctor decides on the day.
    <?php if ($daysSince !== null && $daysSince < 120): ?>
        You gave blood <strong><?php echo (int)$daysSince; ?> days ago</strong>, so please ask the blood bank if it is too soon.
    <?php endif; ?>
</div>

<h2 class="h5 mt-4 mb-3">Waiting for your answer <span class="badge text-bg-danger"><?php echo count($asked); ?></span></h2>
<?php if (!$asked): ?>
    <p class="text-secondary">No admin request is waiting for you.</p>
<?php endif; foreach ($asked as $r) { request_card($r, 'asked', (bool)$donor['is_available']); } ?>

<h2 class="h5 mt-4 mb-3">Open requests that match you <span class="badge text-bg-secondary"><?php echo count($matching); ?></span></h2>
<?php if (!$matching): ?>
    <p class="text-secondary">No open request matches your blood group and district right now.</p>
<?php endif; foreach ($matching as $r) { request_card($r, 'matching', (bool)$donor['is_available']); } ?>

<h2 class="h5 mt-4 mb-3">Your answers</h2>
<?php if (!$answers): ?>
    <p class="text-secondary">You have not answered any request yet.</p>
<?php endif; foreach ($answers as $r) { request_card($r, 'answers', (bool)$donor['is_available']); } ?>

<?php require_once __DIR__ . '/../includes/dash_footer.php'; ?>
