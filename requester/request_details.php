<?php
/*
 * requester/request_details.php  -  One request: details, status timeline,
 * documents, donors who said yes, and the Cancel / Edit buttons.
 * A requester can only open THEIR OWN requests.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/request_fields.php';

require_role('requester');
$me = current_user();
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

function load_request(PDO $pdo, int $id, int $userId)
{
    $stmt = $pdo->prepare(
        'SELECT r.*, bg.group_name, di.district_name
         FROM blood_requests r
         JOIN blood_groups bg ON bg.blood_group_id = r.blood_group_id
         JOIN districts di    ON di.district_id    = r.district_id
         WHERE r.request_id = ? AND r.requester_id = ?'
    );
    $stmt->execute([$id, $userId]);
    return $stmt->fetch();
}

$request = load_request($pdo, $id, $me['id']);
if (!$request) {                                   // not found OR not yours: same answer
    set_flash('danger', 'Request not found.');
    redirect('/requester/my_requests.php');
}

/* ===================== A BUTTON WAS PRESSED ===================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $back = '/requester/request_details.php?id=' . $id;

    if (empty($_POST) && empty($_FILES) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        set_flash('danger', 'The file is too large. Each file can be 5 MB at most.');
        redirect($back);
    }
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'Your session expired. Please try again.');
        redirect($back);
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'cancel') {
        $reason = mb_substr(clean($_POST['reason'] ?? ''), 0, 200);
        if (cancel_blood_request($pdo, $id, $me['id'], $reason)) {
            set_flash('success', 'Your request was cancelled.');
        } else {
            set_flash('warning', 'This request cannot be cancelled any more.');
        }
        redirect($back);
    }

    if ($action === 'add_document') {
        if (!in_array($request['status'], ['Pending', 'Under Review'], true)) {
            set_flash('warning', 'Documents can only be added while the request is Pending or Under Review.');
            redirect($back);
        }
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM medical_documents WHERE request_id = ?');
        $stmt->execute([$id]);
        [$docs, $fileErrors] = validate_documents(collect_uploaded_files(), (int)$stmt->fetchColumn(), true);

        if ($fileErrors) {
            set_flash('danger', implode(' ', $fileErrors));
        } else {
            try {
                add_documents($pdo, $id, $docs);
                set_flash('success', 'The document was added.');
            } catch (Throwable $ex) {
                set_flash('danger', 'The document could not be saved. Please try again.');
            }
        }
        redirect($back);
    }

    redirect($back);
}

/* ===================== LOAD EVERYTHING FOR THE PAGE ===================== */
$stmt = $pdo->prepare('SELECT document_id, original_name, file_ext, file_size, uploaded_at FROM medical_documents WHERE request_id = ? ORDER BY document_id');
$stmt->execute([$id]);
$documents = $stmt->fetchAll();

$stmt = $pdo->prepare(
    'SELECT h.old_status, h.new_status, h.remark, h.changed_at, h.changed_by, u.role AS by_role
     FROM request_status_history h
     LEFT JOIN users u ON u.user_id = h.changed_by
     WHERE h.request_id = ?
     ORDER BY h.changed_at DESC, h.history_id DESC'
);
$stmt->execute([$id]);
$history = $stmt->fetchAll();

// Donors: counts for everyone, details only for those who ACCEPTED
$stmt = $pdo->prepare('SELECT response_status, COUNT(*) FROM donor_responses WHERE request_id = ? GROUP BY response_status');
$stmt->execute([$id]);
$counts = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
$asked  = array_sum($counts);

$stmt = $pdo->prepare(
    "SELECT u.full_name, u.phone, bg.group_name, di.district_name, d.area, r.donor_note, r.responded_at
     FROM donor_responses r
     JOIN donors d        ON d.user_id         = r.donor_id
     JOIN users u         ON u.user_id         = d.user_id
     JOIN blood_groups bg ON bg.blood_group_id = d.blood_group_id
     JOIN districts di    ON di.district_id    = d.district_id
     WHERE r.request_id = ? AND r.response_status = 'Accepted'
     ORDER BY r.responded_at"
);
$stmt->execute([$id]);
$acceptedDonors = $stmt->fetchAll();

$status      = $request['status'];
$canEdit     = ($status === 'Pending');
$canCancel   = in_array($status, ['Pending', 'Under Review', 'Approved', 'Donor Matched'], true);
$canAddDoc   = in_array($status, ['Pending', 'Under Review'], true) && count($documents) < MAX_DOCS_PER_REQUEST;
$showPhones  = in_array($status, ['Approved', 'Donor Matched'], true);   // contact only while it is useful

$nextStep = [
    'Pending'       => 'Waiting for an admin to start the review.',
    'Under Review'  => 'An admin is checking your details and document.',
    'Approved'      => 'Your request is approved. The admin is looking for matching donors.',
    'Donor Matched' => 'At least one donor agreed to help. The admin will confirm when the blood has been received.',
    'Completed'     => 'The admin confirmed that the blood was received. Thank you for using BloodBank.',
    'Rejected'      => 'This request was not accepted. See the admin\'s note below.',
    'Cancelled'     => 'This request was cancelled.',
][$status] ?? '';

$dashRole   = 'requester';
$activeFile = 'my_requests.php';
$pageTitle  = 'Request #' . $id;
require_once __DIR__ . '/../includes/dash_header.php';
?>

<p class="mb-2"><a href="<?php echo BASE_URL; ?>/requester/my_requests.php">&larr; My requests</a></p>

<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <h1 class="bl-dash-title mb-2">Request #<?php echo (int)$id; ?> &ndash; <?php echo e($request['patient_name']); ?></h1>
        <?php echo status_badge($status); ?>
        <?php if ($request['urgency'] === 'Emergency'): ?><span class="bl-status bl-status-rejected">Emergency</span><?php endif; ?>
        <p class="text-secondary mt-2 mb-0"><?php echo e($nextStep); ?></p>
    </div>
    <div class="d-flex gap-2 no-print">
        <button type="button" class="btn btn-outline-secondary" onclick="window.print()">Print</button>
        <?php if ($canEdit): ?>
            <a class="btn btn-outline-blood" href="<?php echo BASE_URL; ?>/requester/edit_request.php?id=<?php echo (int)$id; ?>">Edit</a>
        <?php endif; ?>
        <?php if ($canCancel): ?>
            <button type="button" class="btn btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#cancelBox"
                    aria-expanded="false" aria-controls="cancelBox">Cancel request</button>
        <?php endif; ?>
    </div>
</div>

<?php if ($canCancel): ?>
    <div class="collapse mb-4" id="cancelBox">
        <div class="bl-panel">
            <h2 class="bl-panel-title">Cancel this request?</h2>
            <p class="text-secondary">Use this when the blood is no longer needed. Donors who were asked will be told. You cannot undo it.</p>
            <form method="post" action="" data-confirm="Cancel request #<?php echo (int)$id; ?>? This cannot be undone.">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="id" value="<?php echo (int)$id; ?>">
                <input type="hidden" name="action" value="cancel">
                <label for="reason" class="form-label">Reason (optional)</label>
                <input type="text" class="form-control mb-3" id="reason" name="reason" maxlength="200" placeholder="e.g. Blood arranged by the hospital">
                <button type="submit" class="btn btn-primary">Yes, cancel the request</button>
            </form>
        </div>
    </div>
<?php endif; ?>

<?php if ($request['admin_remark']): ?>
    <div class="alert alert-<?php echo $status === 'Rejected' ? 'danger' : 'info'; ?>">
        <strong>Note from the admin:</strong> <?php echo e($request['admin_remark']); ?>
    </div>
<?php endif; ?>

<div class="row g-3 mb-3">
    <div class="col-lg-7">
        <div class="bl-panel h-100">
            <h2 class="bl-panel-title">Request details</h2>
            <dl class="row bl-dl mb-0">
                <dt class="col-sm-4">Blood needed</dt>
                <dd class="col-sm-8"><strong><?php echo e($request['group_name']); ?></strong>, <?php echo (int)$request['bags_required']; ?> <?php echo $request['bags_required'] == 1 ? 'bag' : 'bags'; ?></dd>
                <dt class="col-sm-4">Needed on</dt>
                <dd class="col-sm-8">
                    <?php echo e(date('d M Y', strtotime($request['required_date']))); ?>
                    <?php echo $request['required_time'] ? ' at ' . e(date('h:i A', strtotime($request['required_time']))) : ($request['urgency'] === 'Emergency' ? ' (emergency)' : ''); ?>
                </dd>
                <dt class="col-sm-4">Medical condition</dt>
                <dd class="col-sm-8"><?php echo e($request['medical_condition']); ?></dd>
                <dt class="col-sm-4">Hospital</dt>
                <dd class="col-sm-8"><?php echo e($request['hospital_name']); ?><br><span class="text-secondary"><?php echo e($request['hospital_address']); ?>, <?php echo e($request['area']); ?>, <?php echo e($request['district_name']); ?></span></dd>
                <dt class="col-sm-4">Contact</dt>
                <dd class="col-sm-8"><?php echo e($request['requester_name']); ?>, <?php echo e($request['contact_phone']); ?></dd>
                <?php if ($request['additional_info']): ?>
                    <dt class="col-sm-4">More information</dt>
                    <dd class="col-sm-8"><?php echo nl2br(e($request['additional_info'])); ?></dd>
                <?php endif; ?>
                <dt class="col-sm-4">Sent on</dt>
                <dd class="col-sm-8 mb-0"><?php echo e(date('d M Y, h:i A', strtotime($request['created_at']))); ?></dd>
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
                            <?php echo $h['changed_by'] == $me['id'] ? 'You' : ($h['by_role'] === 'admin' ? 'BloodBank admin' : 'System'); ?>
                        </div>
                        <?php if ($h['remark']): ?><div class="small"><?php echo e($h['remark']); ?></div><?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ol>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="bl-panel h-100">
            <h2 class="bl-panel-title">Medical documents</h2>
            <?php if (!$documents): ?>
                <p class="text-secondary">No document was uploaded.</p>
            <?php else: ?>
                <ul class="list-unstyled mb-3">
                    <?php foreach ($documents as $doc): ?>
                        <li class="d-flex justify-content-between align-items-center gap-2 py-2 border-bottom">
                            <span class="text-break">
                                <?php echo e($doc['original_name']); ?>
                                <span class="text-secondary small">(<?php echo strtoupper(e($doc['file_ext'])); ?>, <?php echo $doc['file_size'] >= 1048576 ? round($doc['file_size'] / 1048576, 1) . ' MB' : max(1, round($doc['file_size'] / 1024)) . ' KB'; ?>)</span>
                            </span>
                            <a class="btn btn-sm btn-outline-blood" target="_blank" rel="noopener"
                               href="<?php echo BASE_URL; ?>/document.php?id=<?php echo (int)$doc['document_id']; ?>">View</a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <?php if ($canAddDoc): ?>
                <form method="post" action="" enctype="multipart/form-data" class="needs-validation" novalidate>
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="id" value="<?php echo (int)$id; ?>">
                    <input type="hidden" name="action" value="add_document">
                    <label for="more_docs" class="form-label">Add another document</label>
                    <input type="file" class="form-control mb-1" id="more_docs" name="documents[]" required
                           accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png"
                           data-max-mb="<?php echo (int)(MAX_UPLOAD_BYTES / 1048576); ?>"
                           data-max-files="<?php echo (int)(MAX_DOCS_PER_REQUEST - count($documents)); ?>">
                    <div class="invalid-feedback" data-default="Choose a file.">Choose a file.</div>
                    <div class="form-text mb-2">PDF, JPG, JPEG or PNG, up to <?php echo (int)(MAX_UPLOAD_BYTES / 1048576); ?> MB.</div>
                    <button type="submit" class="btn btn-sm btn-primary">Upload</button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="bl-panel h-100">
            <h2 class="bl-panel-title">Donors</h2>
            <?php if ($asked === 0): ?>
                <p class="text-secondary mb-0">
                    <?php echo in_array($status, ['Pending', 'Under Review'], true)
                        ? 'Donors are asked after an admin approves the request.'
                        : 'No donors have been asked for this request.'; ?>
                </p>
            <?php else: ?>
                <p class="text-secondary">
                    <?php echo (int)$asked; ?> <?php echo $asked == 1 ? 'donor was' : 'donors were'; ?> asked:
                    <?php echo (int)($counts['Accepted'] ?? 0); ?> accepted,
                    <?php echo (int)($counts['Declined'] ?? 0); ?> declined,
                    <?php echo (int)($counts['Pending'] ?? 0); ?> waiting.
                </p>
                <?php foreach ($acceptedDonors as $d): ?>
                    <div class="bl-donor-card mb-2">
                        <div class="bl-donor-group"><?php echo e($d['group_name']); ?></div>
                        <div class="bl-donor-info">
                            <div class="bl-donor-name"><?php echo e($d['full_name']); ?></div>
                            <div class="bl-donor-meta"><?php echo e($d['area']); ?>, <?php echo e($d['district_name']); ?></div>
                            <?php if ($showPhones): ?>
                                <a href="tel:<?php echo e($d['phone']); ?>"><?php echo e($d['phone']); ?></a>
                            <?php endif; ?>
                            <?php if ($d['donor_note']): ?><div class="small fst-italic">&ldquo;<?php echo e($d['donor_note']); ?>&rdquo;</div><?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if ($acceptedDonors): ?>
                    <p class="small text-secondary mb-0 mt-2">
                        A donor who said yes still needs to be checked by the hospital or blood bank.
                        Your request is completed only when the admin confirms that the blood was received.
                    </p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/dash_footer.php'; ?>
