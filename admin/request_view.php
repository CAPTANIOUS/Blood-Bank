<?php
/*
 * admin/request_view.php  -  Admin: one request, start to finish.
 *   - read the details and the medical documents
 *   - Start review / Approve / Reject / Cancel
 *   - ask donors to help and watch their answers
 *   - mark the request Completed and record the donations
 * Doing the work is in includes/admin_requests.php; this file shows the page.
 * Medical documents are checked by a HUMAN. BloodBank never certifies anyone.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/admin_requests.php';

require_role('admin');
$me = current_user();
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

$stmt = $pdo->prepare(
    "SELECT r.*, bg.group_name, di.district_name,
            u.full_name AS account_name, u.email AS account_email, u.phone AS account_phone,
            ra.full_name AS reviewer_name
     FROM blood_requests r
     JOIN blood_groups bg ON bg.blood_group_id = r.blood_group_id
     JOIN districts di    ON di.district_id    = r.district_id
     JOIN users u         ON u.user_id         = r.requester_id
     LEFT JOIN users ra   ON ra.user_id        = r.reviewed_by
     WHERE r.request_id = ?"
);
$stmt->execute([$id]);
$r = $stmt->fetch();
if (!$r) {
    set_flash('danger', 'Request not found.');
    redirect('/admin/manage_requests.php');
}

/* ---------- an action was sent ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'Your session expired. Please try again.');
    } else {
        $action = (string)($_POST['action'] ?? '');
        $result = [false, 'Unknown action.'];
        if ($action === 'status') {
            $result = admin_change_status($pdo, $id, $me['id'], (string)($_POST['to'] ?? ''), clean($_POST['remark'] ?? ''));
        } elseif ($action === 'assign') {
            $result = admin_assign_donor($pdo, $id, (int)($_POST['donor_id'] ?? 0), $me['id']);
        } elseif ($action === 'unassign') {
            $result = admin_remove_pending_ask($pdo, $id, (int)($_POST['donor_id'] ?? 0), $me['id']);
        } elseif ($action === 'complete') {
            $bagsIn = is_array($_POST['bags'] ?? null) ? $_POST['bags'] : [];
            $result = admin_complete_request($pdo, $id, $me['id'], $bagsIn, (string)($_POST['donation_date'] ?? ''), clean($_POST['remark'] ?? ''));
        }
        set_flash($result[0] ? 'success' : 'danger', $result[1]);
    }
    redirect('/admin/request_view.php?id=' . $id);
}

/* ---------- data for the page ---------- */
$stmt = $pdo->prepare('SELECT document_id, original_name, file_ext, file_size, uploaded_at FROM medical_documents WHERE request_id = ? ORDER BY document_id');
$stmt->execute([$id]);
$documents = $stmt->fetchAll();

$stmt = $pdo->prepare(
    'SELECT h.new_status, h.remark, h.changed_at, u.full_name, u.role
     FROM request_status_history h LEFT JOIN users u ON u.user_id = h.changed_by
     WHERE h.request_id = ? ORDER BY h.history_id'
);
$stmt->execute([$id]);
$history = $stmt->fetchAll();

$stmt = $pdo->prepare(
    "SELECT resp.donor_id, resp.response_status, resp.donor_note, resp.assigned_by, resp.created_at, resp.responded_at,
            u.full_name, u.phone, d.area, d.last_donation_date
     FROM donor_responses resp
     JOIN users u  ON u.user_id  = resp.donor_id
     JOIN donors d ON d.user_id  = resp.donor_id
     WHERE resp.request_id = ?
     ORDER BY FIELD(resp.response_status,'Accepted','Pending','Declined'), resp.created_at"
);
$stmt->execute([$id]);
$responses = $stmt->fetchAll();
$accepted  = array_values(array_filter($responses, fn($x) => $x['response_status'] === 'Accepted'));

$next      = allowed_next_statuses($r['status'], $r['urgency']);
$canMatch  = in_array($r['status'], ['Approved', 'Donor Matched'], true);

/* Donors who could be asked: same blood group, active, available, not asked yet */
$scope = (($_GET['scope'] ?? '') === 'all') ? 'all' : 'district';
$candidates = [];
if ($canMatch) {
    $sql = "SELECT u.user_id, u.full_name, u.phone, di.district_name, d.area, d.last_donation_date,
                   (d.medical_issues IS NOT NULL AND d.medical_issues <> '') AS has_note,
                   (d.district_id = ?) AS same_district
            FROM donors d
            JOIN users u      ON u.user_id      = d.user_id
            JOIN districts di ON di.district_id = d.district_id
            WHERE u.is_active = 1 AND d.is_available = 1 AND d.blood_group_id = ?
              AND NOT EXISTS (SELECT 1 FROM donor_responses x WHERE x.request_id = ? AND x.donor_id = d.user_id)"
         . ($scope === 'district' ? ' AND d.district_id = ?' : '')
         . ' ORDER BY same_district DESC, d.last_donation_date IS NOT NULL, d.last_donation_date ASC, u.full_name LIMIT 30';
    $params = [$r['district_id'], $r['blood_group_id'], $id];
    if ($scope === 'district') { $params[] = $r['district_id']; }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $candidates = $stmt->fetchAll();
}

$dashRole   = 'admin';
$activeFile = 'manage_requests.php';
$pageTitle  = 'Request #' . $id;
require_once __DIR__ . '/../includes/dash_header.php';

$when = date('d M Y', strtotime($r['required_date'])) . ($r['required_time'] ? ' at ' . date('h:i A', strtotime($r['required_time'])) : '');
?>

<p class="mb-2"><a href="<?php echo BASE_URL; ?>/admin/manage_requests.php">&larr; All requests</a></p>

<div class="mb-4">
    <h1 class="bl-dash-title mb-2">Request #<?php echo (int)$id; ?> &ndash; <?php echo e($r['patient_name']); ?></h1>
    <?php echo status_badge($r['status']); ?>
    <?php if ($r['urgency'] === 'Emergency'): ?><span class="bl-status bl-status-rejected">Emergency</span><?php endif; ?>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-7">
        <div class="bl-panel h-100">
            <h2 class="bl-panel-title">Request details</h2>
            <dl class="bl-dl mb-0">
                <dt>Blood needed</dt><dd><strong><?php echo e($r['group_name']); ?></strong>, <?php echo (int)$r['bags_required']; ?> <?php echo $r['bags_required'] == 1 ? 'bag' : 'bags'; ?></dd>
                <dt>Needed on</dt><dd><?php echo e($when); ?></dd>
                <dt>Medical condition</dt><dd><?php echo e($r['medical_condition']); ?></dd>
                <dt>Hospital</dt><dd><?php echo e($r['hospital_name']); ?><br><span class="text-secondary"><?php echo e($r['hospital_address']); ?>, <?php echo e($r['area']); ?>, <?php echo e($r['district_name']); ?></span></dd>
                <dt>Contact person</dt><dd><?php echo e($r['requester_name']); ?>, <?php echo e($r['contact_phone']); ?></dd>
                <dt>Account</dt><dd><?php echo e($r['account_name']); ?><br><span class="text-secondary"><?php echo e($r['account_email']); ?></span></dd>
                <?php if ($r['additional_info']): ?><dt>More information</dt><dd><?php echo nl2br(e($r['additional_info'])); ?></dd><?php endif; ?>
                <dt>Sent on</dt><dd><?php echo e(date('d M Y, h:i A', strtotime($r['created_at']))); ?></dd>
                <?php if ($r['reviewed_at']): ?>
                    <dt>Reviewed</dt><dd><?php echo e($r['reviewer_name'] ?? 'Admin'); ?>, <?php echo e(date('d M Y, h:i A', strtotime($r['reviewed_at']))); ?></dd>
                <?php endif; ?>
                <?php if ($r['admin_remark']): ?><dt>Admin remark</dt><dd><?php echo e($r['admin_remark']); ?></dd><?php endif; ?>
            </dl>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="bl-panel h-100">
            <h2 class="bl-panel-title">Status history</h2>
            <ol class="bl-timeline mb-0">
                <?php foreach ($history as $h): ?>
                    <li>
                        <div><?php echo status_badge($h['new_status']); ?></div>
                        <div class="small text-secondary">
                            <?php echo e(date('d M Y, h:i A', strtotime($h['changed_at']))); ?> &middot;
                            <?php echo e($h['full_name'] ?? 'System'); ?> (<?php echo e($h['role'] ?? 'system'); ?>)
                        </div>
                        <?php if ($h['remark']): ?><div class="small"><?php echo e($h['remark']); ?></div><?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ol>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-6">
        <div class="bl-panel h-100">
            <h2 class="bl-panel-title">Medical documents</h2>
            <?php if (!$documents): ?>
                <p class="text-secondary mb-0">No document was uploaded.</p>
            <?php else: ?>
                <ul class="list-group list-group-flush mb-2">
                    <?php foreach ($documents as $doc): ?>
                        <li class="list-group-item px-0 d-flex justify-content-between align-items-center gap-2">
                            <span><?php echo e($doc['original_name']); ?>
                                <span class="text-secondary small">(<?php echo e(strtoupper($doc['file_ext'])); ?>, <?php echo e(number_format($doc['file_size'] / 1024, 0)); ?> KB)</span></span>
                            <a class="btn btn-sm btn-outline-blood" target="_blank" rel="noopener"
                               href="<?php echo BASE_URL; ?>/document.php?id=<?php echo (int)$doc['document_id']; ?>">View</a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <p class="small text-secondary mb-0">Read the documents yourself before approving. Every time you open one it is saved in the admin log.</p>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="bl-panel h-100">
            <h2 class="bl-panel-title">Decision</h2>
            <?php if (!$next && $r['status'] !== 'Donor Matched'): ?>
                <p class="text-secondary mb-0">This request is <strong><?php echo e($r['status']); ?></strong>. No further step is possible.</p>
            <?php endif; ?>

            <?php if (in_array('Under Review', $next, true)): ?>
                <form method="post" class="mb-3">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="id" value="<?php echo (int)$id; ?>">
                    <input type="hidden" name="action" value="status"><input type="hidden" name="to" value="Under Review">
                    <button class="btn btn-primary">Start review</button>
                </form>
            <?php endif; ?>

            <?php if (in_array('Approved', $next, true)): ?>
                <form method="post" class="mb-3" data-confirm="Approve this request? Donors will be able to see it.">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="id" value="<?php echo (int)$id; ?>">
                    <input type="hidden" name="action" value="status"><input type="hidden" name="to" value="Approved">
                    <label class="form-label small mb-1" for="ap_remark">Note (optional)</label>
                    <input class="form-control mb-2" id="ap_remark" name="remark" maxlength="200" placeholder="e.g. Document checked">
                    <button class="btn btn-primary">Approve</button>
                </form>
            <?php endif; ?>

            <?php foreach (['Rejected' => 'Reject', 'Cancelled' => 'Cancel this request'] as $to => $label): ?>
                <?php if (in_array($to, $next, true)): ?>
                    <form method="post" class="mb-0 mt-2" data-confirm="<?php echo e($label); ?>? The requester will see your reason.">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="id" value="<?php echo (int)$id; ?>">
                        <input type="hidden" name="action" value="status"><input type="hidden" name="to" value="<?php echo e($to); ?>">
                        <label class="form-label small mb-1" for="rm_<?php echo e($to); ?>">Reason (the requester will read it)</label>
                        <input class="form-control mb-2" id="rm_<?php echo e($to); ?>" name="remark" maxlength="200" required>
                        <button class="btn btn-outline-secondary"><?php echo e($label); ?></button>
                    </form>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="bl-panel mb-3">
    <h2 class="bl-panel-title">Donors for this request</h2>
    <?php if (!$responses): ?>
        <p class="text-secondary">Nobody has been asked and nobody has volunteered yet.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>Donor</th><th>Answer</th><th>Note</th><th>Last donation</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($responses as $x): ?>
                    <tr>
                        <td>
                            <a href="<?php echo BASE_URL; ?>/admin/donor_view.php?id=<?php echo (int)$x['donor_id']; ?>"><?php echo e($x['full_name']); ?></a>
                            <div class="small text-secondary"><?php echo e($x['phone']); ?> &middot; <?php echo e($x['area']); ?></div>
                            <?php if ($x['assigned_by'] === null): ?><div class="small text-secondary">Volunteered</div><?php endif; ?>
                        </td>
                        <td>
                            <?php if ($x['response_status'] === 'Accepted'): ?><span class="bl-status bl-status-approved">Accepted</span>
                            <?php elseif ($x['response_status'] === 'Declined'): ?><span class="bl-status bl-status-cancelled">Declined</span>
                            <?php else: ?><span class="bl-status bl-status-pending">Waiting</span><?php endif; ?>
                        </td>
                        <td class="small"><?php echo e($x['donor_note'] ?? ''); ?></td>
                        <td class="small text-nowrap"><?php echo $x['last_donation_date'] ? e(date('d M Y', strtotime($x['last_donation_date']))) : 'Never'; ?></td>
                        <td class="text-end">
                            <?php if ($x['response_status'] === 'Pending' && $canMatch): ?>
                                <form method="post" class="m-0" data-confirm="Take back this question?">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="id" value="<?php echo (int)$id; ?>">
                                    <input type="hidden" name="action" value="unassign"><input type="hidden" name="donor_id" value="<?php echo (int)$x['donor_id']; ?>">
                                    <button class="btn btn-sm btn-outline-secondary">Take back</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php if ($canMatch): ?>
<div class="bl-panel mb-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
        <h2 class="bl-panel-title mb-0">Ask a donor (<?php echo e($r['group_name']); ?>)</h2>
        <div class="btn-group btn-group-sm">
            <a class="btn <?php echo $scope === 'district' ? 'btn-primary' : 'btn-outline-blood'; ?>" href="?id=<?php echo (int)$id; ?>&amp;scope=district">In <?php echo e($r['district_name']); ?></a>
            <a class="btn <?php echo $scope === 'all' ? 'btn-primary' : 'btn-outline-blood'; ?>" href="?id=<?php echo (int)$id; ?>&amp;scope=all">All districts</a>
        </div>
    </div>
    <p class="small text-secondary">Only active donors who are marked available and not asked yet. Donors who gave blood long ago are shown first.
        BloodBank does not decide who may donate: check the flags and ask the donor and the blood bank.</p>
    <?php if (!$candidates): ?>
        <p class="mb-0 text-secondary">No other available <?php echo e($r['group_name']); ?> donor was found<?php echo $scope === 'district' ? ' in this district. Try "All districts"' : ''; ?>.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>Donor</th><th>Place</th><th>Last donation</th><th>Flags</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($candidates as $c):
                    $days = $c['last_donation_date'] ? (new DateTime($c['last_donation_date']))->diff(new DateTime('today'))->days : null; ?>
                    <tr>
                        <td><?php echo e($c['full_name']); ?><div class="small text-secondary"><?php echo e($c['phone']); ?></div></td>
                        <td class="small"><?php echo e($c['area']); ?>, <?php echo e($c['district_name']); ?></td>
                        <td class="small text-nowrap"><?php echo $days === null ? 'Never' : e(date('d M Y', strtotime($c['last_donation_date']))) . ' (' . (int)$days . ' days ago)'; ?></td>
                        <td class="small">
                            <?php if ($days !== null && $days < 120): ?><span class="bl-status bl-status-pending">Gave blood recently</span><?php endif; ?>
                            <?php if ($c['has_note']): ?><span class="bl-status bl-status-review">Medical note</span><?php endif; ?>
                        </td>
                        <td class="text-end">
                            <form method="post" class="m-0">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="id" value="<?php echo (int)$id; ?>">
                                <input type="hidden" name="action" value="assign"><input type="hidden" name="donor_id" value="<?php echo (int)$c['user_id']; ?>">
                                <button class="btn btn-sm btn-primary">Ask</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php if ($r['status'] === 'Donor Matched'): ?>
<div class="bl-panel mb-3">
    <h2 class="bl-panel-title">Complete this request</h2>
    <p class="text-secondary">Do this only <strong>after the blood was really given</strong>. A donor saying "I accept" is not enough.
        Write how many bags each donor gave (leave 0 for a donor who did not donate).</p>
    <form method="post" data-confirm="Mark this request as Completed and record these donations? This cannot be undone.">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="id" value="<?php echo (int)$id; ?>">
        <input type="hidden" name="action" value="complete">
        <div class="table-responsive">
            <table class="table align-middle">
                <thead><tr><th>Accepted donor</th><th style="width:160px">Bags given (0-5)</th></tr></thead>
                <tbody>
                <?php foreach ($accepted as $x): ?>
                    <tr>
                        <td><?php echo e($x['full_name']); ?> <span class="small text-secondary"><?php echo e($x['phone']); ?></span></td>
                        <td><input type="number" class="form-control" name="bags[<?php echo (int)$x['donor_id']; ?>]" min="0" max="5" value="1" aria-label="Bags given by <?php echo e($x['full_name']); ?>"></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label" for="donation_date">Donation date</label>
                <input type="date" class="form-control" id="donation_date" name="donation_date" value="<?php echo date('Y-m-d'); ?>" max="<?php echo date('Y-m-d'); ?>" required>
            </div>
            <div class="col-md-5">
                <label class="form-label" for="c_remark">Note (optional)</label>
                <input class="form-control" id="c_remark" name="remark" maxlength="200" placeholder="e.g. Blood received at the hospital">
            </div>
            <div class="col-md-3"><button class="btn btn-primary w-100">Mark as Completed</button></div>
        </div>
    </form>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/dash_footer.php'; ?>
