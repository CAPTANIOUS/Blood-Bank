<?php
/*
 * admin/donor_edit.php  -  Admin edits a donor's account and profile.
 * Uses the SAME validation function as registration and the donor's own
 * profile page (includes/donor_fields.php), so the rules never differ.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/donor_fields.php';

require_role('admin');                // guard first (we may redirect before showing HTML)

$id    = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$today = date('Y-m-d');

$bloodGroups = $pdo->query('SELECT blood_group_id, group_name FROM blood_groups ORDER BY blood_group_id')->fetchAll();
$districts   = $pdo->query('SELECT district_id, district_name FROM districts ORDER BY district_name')->fetchAll();

function load_donor(PDO $pdo, int $id)
{
    $stmt = $pdo->prepare(
        "SELECT u.user_id, u.full_name, u.email, u.phone, u.is_active,
                d.date_of_birth, d.gender, d.blood_group_id, d.address, d.district_id,
                d.area, d.last_donation_date, d.medical_issues, d.is_available
         FROM users u JOIN donors d ON d.user_id = u.user_id
         WHERE u.user_id = ? AND u.role = 'donor'"
    );
    $stmt->execute([$id]);
    return $stmt->fetch();
}

$current = load_donor($pdo, $id);
if (!$current) {
    set_flash('danger', 'Donor not found.');
    redirect('/admin/manage_donors.php');
}

$errors = [];
$posted = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $posted = $_POST;

    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Your session expired. Please try again.';
    } else {
        $fullName = clean($_POST['full_name'] ?? '');
        $email    = strtolower(clean($_POST['email'] ?? ''));
        $phone    = normalize_phone(clean($_POST['phone'] ?? ''));

        if (strlen($fullName) < 2 || strlen($fullName) > 100) {
            $errors[] = 'Enter the full name (2 to 100 characters).';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 120) {
            $errors[] = 'Enter a valid email address.';
        }
        if (!preg_match('/^\+?[0-9]{7,15}$/', $phone)) {
            $errors[] = 'Enter a valid phone number (digits only, 7 to 15 numbers).';
        }

        [$data, $donorErrors] = validate_donor_input($_POST, $bloodGroups, $districts);
        $errors = array_merge($errors, $donorErrors);

        if (!$errors) {                  // email / phone must not belong to SOMEONE ELSE
            $stmt = $pdo->prepare('SELECT email, phone FROM users WHERE (email = ? OR phone = ?) AND user_id <> ?');
            $stmt->execute([$email, $phone, $id]);
            foreach ($stmt->fetchAll() as $row) {
                if ($row['email'] === $email) { $errors[] = 'This email address belongs to another account.'; }
                if ($row['phone'] === $phone) { $errors[] = 'This phone number belongs to another account.'; }
            }
        }

        if (!$errors) {
            try {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare('UPDATE users SET full_name = ?, email = ?, phone = ? WHERE user_id = ?');
                $stmt->execute([$fullName, $email, $phone, $id]);

                $stmt = $pdo->prepare(
                    'UPDATE donors
                     SET date_of_birth = ?, gender = ?, blood_group_id = ?, address = ?, district_id = ?,
                         area = ?, last_donation_date = ?, medical_issues = ?, is_available = ?
                     WHERE user_id = ?'
                );
                $stmt->execute([
                    $data['date_of_birth'], $data['gender'], $data['blood_group_id'], $data['address'],
                    $data['district_id'], $data['area'],
                    ($data['last_donation_date'] !== '' ? $data['last_donation_date'] : null),
                    ($data['medical_issues'] !== '' ? $data['medical_issues'] : null),
                    $data['is_available'], $id,
                ]);

                // Which fields changed? (names only: we do not copy private medical text into the log)
                $new = ['full_name' => $fullName, 'email' => $email, 'phone' => $phone] + $data;
                $new['last_donation_date'] = $data['last_donation_date'] !== '' ? $data['last_donation_date'] : null;
                $new['medical_issues']     = $data['medical_issues'] !== '' ? $data['medical_issues'] : null;
                $changed = [];
                foreach ($new as $field => $value) {
                    if ((string)($current[$field] ?? '') !== (string)($value ?? '')) {
                        $changed[] = $field;
                    }
                }
                log_admin_action($pdo, current_user()['id'], 'Edited donor', 'donors', $id,
                                 $changed ? 'Changed: ' . implode(', ', $changed) : 'No field changed');

                $pdo->commit();
                set_flash('success', 'Donor details were saved.');
                redirect('/admin/donor_view.php?id=' . $id);

            } catch (PDOException $ex) {
                if ($pdo->inTransaction()) { $pdo->rollBack(); }
                $errors[] = (($ex->errorInfo[1] ?? 0) === 1062)
                    ? 'This email address or phone number belongs to another account.'
                    : 'Something went wrong while saving. Please try again.';
            }
        }
    }
}

// After an error show what the admin typed; otherwise show the saved data
$f = $posted ?? $current;
$f['medical_issues']     = $f['medical_issues'] ?? '';
$f['last_donation_date'] = $f['last_donation_date'] ?? '';

$dashRole   = 'admin';
$activeFile = 'manage_donors.php';
$pageTitle  = 'Edit donor';
require_once __DIR__ . '/../includes/dash_header.php';
?>

<p class="mb-2"><a href="<?php echo BASE_URL; ?>/admin/donor_view.php?id=<?php echo (int)$id; ?>">&larr; Back to donor</a></p>
<h1 class="bl-dash-title">Edit donor</h1>
<p class="text-secondary mb-4">Changes are saved in the admin log. To change the account status use Deactivate / Reactivate on the donor page.</p>

<div class="bl-panel">
    <?php if ($errors): ?>
        <div class="alert alert-danger" role="alert">
            <strong>Please fix this:</strong>
            <ul class="mb-0 mt-1">
                <?php foreach ($errors as $err): ?><li><?php echo e($err); ?></li><?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" action="?id=<?php echo (int)$id; ?>" class="needs-validation" novalidate>
        <?php echo csrf_field(); ?>
        <input type="hidden" name="id" value="<?php echo (int)$id; ?>">

        <div class="row g-3">
            <div class="col-md-6">
                <label for="e_name" class="form-label">Full name</label>
                <input type="text" class="form-control" id="e_name" name="full_name" maxlength="100" value="<?php echo e($f['full_name']); ?>" required>
                <div class="invalid-feedback">Enter the full name.</div>
            </div>
            <div class="col-md-6">
                <label for="e_email" class="form-label">Email address</label>
                <input type="email" class="form-control" id="e_email" name="email" maxlength="120" value="<?php echo e($f['email']); ?>" required>
                <div class="invalid-feedback">Enter a valid email address.</div>
            </div>
            <div class="col-md-6">
                <label for="e_phone" class="form-label">Contact number</label>
                <input type="tel" class="form-control" id="e_phone" name="phone" maxlength="20" pattern="[0-9+\- ]{7,20}" value="<?php echo e($f['phone']); ?>" required>
                <div class="invalid-feedback">Enter a valid phone number.</div>
            </div>
            <div class="col-md-3">
                <label for="e_dob" class="form-label">Date of birth</label>
                <input type="date" class="form-control" id="e_dob" name="date_of_birth" max="<?php echo $today; ?>" value="<?php echo e($f['date_of_birth']); ?>" required>
                <div class="invalid-feedback">Choose the date of birth.</div>
            </div>
            <div class="col-md-3">
                <label for="e_gender" class="form-label">Gender</label>
                <select class="form-select" id="e_gender" name="gender" required>
                    <option value="">Choose...</option>
                    <?php foreach (['Male', 'Female', 'Other'] as $g): ?>
                        <option<?php echo selected($f['gender'], $g); ?>><?php echo $g; ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="invalid-feedback">Choose one.</div>
            </div>

            <div class="col-md-4">
                <label for="e_group" class="form-label">Blood group</label>
                <select class="form-select" id="e_group" name="blood_group_id" required>
                    <option value="">Choose...</option>
                    <?php foreach ($bloodGroups as $bg): ?>
                        <option value="<?php echo (int)$bg['blood_group_id']; ?>"<?php echo selected($f['blood_group_id'], $bg['blood_group_id']); ?>><?php echo e($bg['group_name']); ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="invalid-feedback">Choose the blood group.</div>
            </div>
            <div class="col-md-4">
                <label for="e_district" class="form-label">District</label>
                <select class="form-select" id="e_district" name="district_id" required>
                    <option value="">Choose...</option>
                    <?php foreach ($districts as $di): ?>
                        <option value="<?php echo (int)$di['district_id']; ?>"<?php echo selected($f['district_id'], $di['district_id']); ?>><?php echo e($di['district_name']); ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="invalid-feedback">Choose the district.</div>
            </div>
            <div class="col-md-4">
                <label for="e_area" class="form-label">Area</label>
                <input type="text" class="form-control" id="e_area" name="area" maxlength="100" value="<?php echo e($f['area']); ?>" required>
                <div class="invalid-feedback">Enter the area.</div>
            </div>

            <div class="col-12">
                <label for="e_address" class="form-label">Full address</label>
                <input type="text" class="form-control" id="e_address" name="address" maxlength="255" value="<?php echo e($f['address']); ?>" required>
                <div class="invalid-feedback">Enter the address.</div>
            </div>

            <div class="col-md-6">
                <label for="e_last" class="form-label">Last blood donation date</label>
                <input type="date" class="form-control" id="e_last" name="last_donation_date" max="<?php echo $today; ?>" value="<?php echo e($f['last_donation_date']); ?>">
                <div class="form-text">Leave empty if the donor has never donated.</div>
            </div>
            <div class="col-md-6">
                <label for="e_avail" class="form-label">Available to donate now?</label>
                <select class="form-select" id="e_avail" name="is_available">
                    <option value="1"<?php echo selected($f['is_available'], '1'); ?>>Available</option>
                    <option value="0"<?php echo selected($f['is_available'], '0'); ?>>Unavailable</option>
                </select>
            </div>

            <div class="col-12">
                <label for="e_medical" class="form-label">Medical issues (if any)</label>
                <textarea class="form-control" id="e_medical" name="medical_issues" rows="3" maxlength="1000"><?php echo e($f['medical_issues']); ?></textarea>
            </div>
        </div>

        <div class="d-flex gap-2 mt-4">
            <button type="submit" class="btn btn-primary">Save changes</button>
            <a class="btn btn-outline-blood" href="<?php echo BASE_URL; ?>/admin/donor_view.php?id=<?php echo (int)$id; ?>">Cancel</a>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/dash_footer.php'; ?>
