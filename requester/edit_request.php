<?php
/*
 * requester/edit_request.php  -  Change a request. Only while it is still Pending.
 * (Once an admin starts reviewing it, the details are locked.)
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/request_fields.php';

require_role('requester');
$me = current_user();
$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare('SELECT * FROM blood_requests WHERE request_id = ? AND requester_id = ?');
$stmt->execute([$id, $me['id']]);
$request = $stmt->fetch();

if (!$request) {
    set_flash('danger', 'Request not found.');
    redirect('/requester/my_requests.php');
}
if ($request['status'] !== 'Pending') {
    set_flash('warning', 'This request can no longer be edited because it is already ' . $request['status'] . '.');
    redirect('/requester/request_details.php?id=' . $id);
}

$bloodGroups = $pdo->query('SELECT blood_group_id, group_name FROM blood_groups ORDER BY blood_group_id')->fetchAll();
$districts   = $pdo->query('SELECT district_id, district_name FROM districts ORDER BY district_name')->fetchAll();

$errors = [];
$f      = $request;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $f = $_POST;

    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Your session expired. Please try again.';
    } else {
        [$data, $errors] = validate_request_input($_POST, $bloodGroups, $districts);

        if (!$errors) {
            if (update_blood_request($pdo, $id, $me['id'], $data)) {
                set_flash('success', 'Your request was updated.');
            } else {
                set_flash('warning', 'The request could not be changed because an admin has already started reviewing it.');
            }
            redirect('/requester/request_details.php?id=' . $id);
        }
    }
}

$dashRole    = 'requester';
$activeFile  = 'my_requests.php';
$pageTitle   = 'Edit Request #' . $id;
$showUpload  = false;
$submitLabel = 'Save changes';
$cancelUrl   = BASE_URL . '/requester/request_details.php?id=' . $id;
require_once __DIR__ . '/../includes/dash_header.php';
?>

<p class="mb-2"><a href="<?php echo e($cancelUrl); ?>">&larr; Back to request #<?php echo (int)$id; ?></a></p>
<h1 class="bl-dash-title">Edit request #<?php echo (int)$id; ?></h1>
<p class="text-secondary mb-4">You can change the details until an admin starts reviewing the request. Documents are managed on the request page.</p>

<div class="bl-panel">
    <?php require __DIR__ . '/../includes/request_form.php'; ?>
</div>

<?php require_once __DIR__ . '/../includes/dash_footer.php'; ?>
