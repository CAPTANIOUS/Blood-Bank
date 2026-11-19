<?php
/*
 * requester/create_request.php  -  A requester sends a new blood request.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/request_fields.php';

require_role('requester');
$me = current_user();

$errors = [];
$f      = null;      // what was typed (shown again after an error)

$bloodGroups = $pdo->query('SELECT blood_group_id, group_name FROM blood_groups ORDER BY blood_group_id')->fetchAll();
$districts   = $pdo->query('SELECT district_id, district_name FROM districts ORDER BY district_name')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $f = $_POST;

    // When the files are bigger than the server allows, PHP throws away the whole form
    if (empty($_POST) && empty($_FILES) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        $errors[] = 'The files are too large. Each file can be 5 MB at most.';

    } elseif (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Your session expired. Please try again.';

    } else {
        [$data, $errors]    = validate_request_input($_POST, $bloodGroups, $districts);
        [$docs, $fileErrors] = validate_documents(collect_uploaded_files(), 0, true);
        $errors = array_merge($errors, $fileErrors);

        if (!$errors) {
            try {
                $requestId = create_blood_request($pdo, $me['id'], $data, $docs, $me['id']);
                set_flash('success', 'Your request was sent. An admin will review it soon.');
                redirect('/requester/request_details.php?id=' . $requestId);
            } catch (Throwable $ex) {
                $errors[] = 'Something went wrong while saving your request. Please try again.';
            }
        }
    }
} else {
    // first visit: fill in what we already know about the person
    $stmt = $pdo->prepare('SELECT full_name, phone FROM users WHERE user_id = ?');
    $stmt->execute([$me['id']]);
    $u = $stmt->fetch();
    $f = ['requester_name' => $u['full_name'], 'contact_phone' => $u['phone'], 'bags_required' => 1, 'urgency' => 'Normal'];
}

$dashRole    = 'requester';
$pageTitle   = 'New Blood Request';
$showUpload  = true;
$submitLabel = 'Send request';
$cancelUrl   = BASE_URL . '/requester/dashboard.php';
require_once __DIR__ . '/../includes/dash_header.php';
?>

<h1 class="bl-dash-title">New blood request</h1>
<p class="text-secondary mb-3">An admin checks every request and its document before donors are asked.</p>

<div class="alert alert-danger" role="alert">
    <strong>In an emergency, go to the hospital or call 999 first.</strong>
    BloodBank is not an emergency service.
</div>

<div class="bl-panel">
    <?php require __DIR__ . '/../includes/request_form.php'; ?>
</div>

<?php require_once __DIR__ . '/../includes/dash_footer.php'; ?>
