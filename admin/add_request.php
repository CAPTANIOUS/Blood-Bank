<?php
/*
 * admin/add_request.php  -  Admin enters a request for someone who came in person or phoned.
 * Same form + same checks as the requester page (includes/request_form.php and
 * includes/request_fields.php), so the rules never differ.
 * The request starts as "Pending" like every other one: the admin then reviews it normally.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/request_fields.php';
require_once __DIR__ . '/../includes/donor_actions.php';

require_role('admin');
$me = current_user();

$errors = [];
$f      = null;

$bloodGroups = $pdo->query('SELECT blood_group_id, group_name FROM blood_groups ORDER BY blood_group_id')->fetchAll();
$districts   = $pdo->query('SELECT district_id, district_name FROM districts ORDER BY district_name')->fetchAll();
$accounts    = $pdo->query("SELECT user_id, full_name, email FROM users WHERE role = 'requester' AND is_active = 1 ORDER BY full_name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $f = $_POST;

    if (empty($_POST) && empty($_FILES) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        $errors[] = 'The files are too large. Each file can be 5 MB at most.';

    } elseif (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Your session expired. Please try again.';

    } else {
        [$data, $errors]     = validate_request_input($_POST, $bloodGroups, $districts);
        [$docs, $fileErrors] = validate_documents(collect_uploaded_files(), 0, false);   // document optional here
        $errors = array_merge($errors, $fileErrors);

        // Who owns the request? 0 = walk-in (recorded under the admin's own account)
        $accountId = (int)($_POST['account_id'] ?? 0);
        if ($accountId !== 0 && !in_array($accountId, array_column($accounts, 'user_id'))) {
            $errors[] = 'Choose a requester account from the list, or "Walk-in".';
        }

        if (!$errors) {
            try {
                $ownerId = $accountId ?: $me['id'];
                $remark  = $accountId ? 'Entered by an admin for this account' : 'Entered by an admin (walk-in or phone call)';
                $requestId = create_blood_request($pdo, $ownerId, $data, $docs, $me['id'], $remark);
                log_admin_action($pdo, $me['id'], 'Added request', 'blood_requests', $requestId, $accountId ? "For account #$accountId" : 'Walk-in');
                if ($accountId) {
                    add_notification($pdo, $accountId, 'Request added for you',
                        "An admin created blood request #$requestId for you.", 'requester/request_details.php?id=' . $requestId);
                }
                set_flash('success', "Request #$requestId was saved as Pending. Review it below.");
                redirect('/admin/request_view.php?id=' . $requestId);
            } catch (Throwable $ex) {
                $errors[] = 'Something went wrong while saving the request. Please try again.';
            }
        }
    }
} else {
    $f = ['bags_required' => 1, 'urgency' => 'Normal'];
}

/* the extra box at the top of the shared form */
$chosen = (int)($f['account_id'] ?? 0);
ob_start(); ?>
<div class="mb-4">
    <label for="account_id" class="form-label">Record this request under</label>
    <select class="form-select" id="account_id" name="account_id">
        <option value="0">Walk-in / phone call (no account)</option>
        <?php foreach ($accounts as $a): ?>
            <option value="<?php echo (int)$a['user_id']; ?>"<?php echo selected($chosen, $a['user_id']); ?>><?php echo e($a['full_name']); ?> (<?php echo e($a['email']); ?>)</option>
        <?php endforeach; ?>
    </select>
    <div class="form-text">Pick an account if the person already registered, so they can follow the request. The contact name and phone below are always the real contact.</div>
</div>
<?php
$extraTop = ob_get_clean();

$dashRole       = 'admin';
$pageTitle      = 'Add a request';
$showUpload     = true;
$uploadRequired = false;
$submitLabel    = 'Save request';
$cancelUrl      = BASE_URL . '/admin/manage_requests.php';
require_once __DIR__ . '/../includes/dash_header.php';
?>

<h1 class="bl-dash-title">Add a request</h1>
<p class="text-secondary mb-3">For a person who came in or phoned. The request is saved as <strong>Pending</strong>; you review and approve it on the next page.</p>

<div class="bl-panel">
    <?php require __DIR__ . '/../includes/request_form.php'; ?>
</div>

<?php require_once __DIR__ . '/../includes/dash_footer.php'; ?>
