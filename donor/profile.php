<?php
/*
 * donor/profile.php  -  A donor looks at and edits their own data.
 * This page has THREE small forms. A hidden field called "action" tells
 * the PHP code at the top which one was sent:
 *     action = profile       (details)
 *     action = availability  (Available / Unavailable switch)
 *     action = password      (change password)
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/donor_fields.php';

require_role('donor');            // guard first, because we may redirect before showing HTML
$me      = current_user();
$userId  = $me['id'];
$today   = date('Y-m-d');

$errors   = [];      // problems in the profile form
$pwErrors = [];      // problems in the password form
$posted   = null;    // what was typed in the profile form (to show it again after an error)

$bloodGroups = $pdo->query('SELECT blood_group_id, group_name FROM blood_groups ORDER BY blood_group_id')->fetchAll();
$districts   = $pdo->query('SELECT district_id, district_name FROM districts ORDER BY district_name')->fetchAll();

/* ===================== A FORM WAS SENT ===================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';

    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Your session expired. Please try again.';

    /* ---------- 1. Availability switch ---------- */
    } elseif ($action === 'availability') {
        $available = (($_POST['is_available'] ?? '') === '1') ? 1 : 0;
        $stmt = $pdo->prepare('UPDATE donors SET is_available = ? WHERE user_id = ?');
        $stmt->execute([$available, $userId]);
        set_flash('success', $available ? 'You are now shown as Available.' : 'You are now shown as Unavailable.');
        redirect('/donor/profile.php');

    /* ---------- 2. Profile details ---------- */
    } elseif ($action === 'profile') {
        $posted   = $_POST;
        $fullName = clean($_POST['full_name'] ?? '');
        $phone    = normalize_phone(clean($_POST['phone'] ?? ''));

        if (strlen($fullName) < 2 || strlen($fullName) > 100) {
            $errors[] = 'Enter your full name (2 to 100 characters).';
        }
        if (!preg_match('/^\+?[0-9]{7,15}$/', $phone)) {
            $errors[] = 'Enter a valid phone number (digits only, 7 to 15 numbers).';
        }

        [$data, $donorErrors] = validate_donor_input($_POST, $bloodGroups, $districts);
        $errors = array_merge($errors, $donorErrors);

        if (!$errors) {                      // is the phone number used by SOMEONE ELSE?
            $stmt = $pdo->prepare('SELECT user_id FROM users WHERE phone = ? AND user_id <> ?');
            $stmt->execute([$phone, $userId]);
            if ($stmt->fetch()) {
                $errors[] = 'This phone number belongs to another account.';
            }
        }

        if (!$errors) {
            try {
                $pdo->beginTransaction();      // both updates succeed, or none

                $stmt = $pdo->prepare('UPDATE users SET full_name = ?, phone = ? WHERE user_id = ?');
                $stmt->execute([$fullName, $phone, $userId]);

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
                    $data['is_available'],
                    $userId,
                ]);

                $pdo->commit();
                set_flash('success', 'Your profile was updated.');
                redirect('/donor/profile.php');

            } catch (PDOException $ex) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $errors[] = (($ex->errorInfo[1] ?? 0) === 1062)
                    ? 'This phone number belongs to another account.'
                    : 'Something went wrong while saving. Please try again.';
            }
        }

    /* ---------- 3. Change password ---------- */
    } elseif ($action === 'password') {
        $current = (string)($_POST['current_password'] ?? '');
        $new     = (string)($_POST['password'] ?? '');
        $confirm = (string)($_POST['confirm_password'] ?? '');

        $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE user_id = ?');
        $stmt->execute([$userId]);
        $hash = (string)$stmt->fetchColumn();

        if (!password_verify($current, $hash)) {
            $pwErrors[] = 'Your current password is not correct.';
        }
        if (strlen($new) < 8) {
            $pwErrors[] = 'The new password must have at least 8 characters.';
        } elseif (strlen($new) > 72) {
            $pwErrors[] = 'The new password must have 72 characters or fewer.';
        }
        if ($new !== $confirm) {
            $pwErrors[] = 'The two new passwords do not match.';
        }
        if (!$pwErrors && $new === $current) {
            $pwErrors[] = 'Choose a password that is different from the current one.';
        }

        if (!$pwErrors) {
            $stmt = $pdo->prepare('UPDATE users SET password_hash = ? WHERE user_id = ?');
            $stmt->execute([password_hash($new, PASSWORD_DEFAULT), $userId]);
            session_regenerate_id(true);       // new session id after a security change
            set_flash('success', 'Your password was changed.');
            redirect('/donor/profile.php');
        }
    }
}

/* ===================== LOAD THE CURRENT DATA ===================== */
$stmt = $pdo->prepare(
    'SELECT u.full_name, u.email, u.phone,
            d.date_of_birth, d.gender, d.blood_group_id, d.address, d.district_id,
            d.area, d.last_donation_date, d.medical_issues, d.is_available
     FROM users u
     JOIN donors d ON d.user_id = u.user_id
     WHERE u.user_id = ?'
);
$stmt->execute([$userId]);
$current = $stmt->fetch();

// After an error show what the person typed; otherwise show the saved data
$f = $posted ?? $current;
$f['medical_issues']     = $f['medical_issues'] ?? '';
$f['last_donation_date'] = $f['last_donation_date'] ?? '';

/* ===================== SHOW THE PAGE ===================== */
$dashRole  = 'donor';
$pageTitle = 'My Profile';
require_once __DIR__ . '/../includes/dash_header.php';
?>

<h1 class="bl-dash-title">My profile</h1>
<p class="text-secondary mb-4">Keep your details up to date so the right requests reach you.</p>

<!-- ============ AVAILABILITY ============ -->
<div class="bl-panel mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <h2 class="bl-panel-title mb-1">Availability</h2>
            <p class="mb-0 text-secondary">
                Right now you are
                <?php if ($current['is_available']): ?>
                    <span class="bl-status bl-status-approved">Available</span>
                    and visible in Find Donors.
                <?php else: ?>
                    <span class="bl-status bl-status-cancelled">Unavailable</span>
                    and hidden from Find Donors.
                <?php endif; ?>
            </p>
        </div>
        <form method="post" action="" class="m-0">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="availability">
            <input type="hidden" name="is_available" value="<?php echo $current['is_available'] ? '0' : '1'; ?>">
            <button type="submit" class="btn btn-outline-blood">
                <?php echo $current['is_available'] ? 'Mark me as unavailable' : 'Mark me as available'; ?>
            </button>
        </form>
    </div>
</div>

<!-- ============ PROFILE DETAILS ============ -->
<div class="bl-panel mb-4">
    <h2 class="bl-panel-title">Your details</h2>

    <?php if ($errors): ?>
        <div class="alert alert-danger" role="alert">
            <strong>Please fix this:</strong>
            <ul class="mb-0 mt-1">
                <?php foreach ($errors as $err): ?><li><?php echo e($err); ?></li><?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" action="" class="needs-validation" novalidate>
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="profile">

        <div class="row g-3">
            <div class="col-md-6">
                <label for="p_name" class="form-label">Full name</label>
                <input type="text" class="form-control" id="p_name" name="full_name" maxlength="100"
                       value="<?php echo e($f['full_name']); ?>" required>
                <div class="invalid-feedback">Enter your full name.</div>
            </div>
            <div class="col-md-6">
                <label for="p_email" class="form-label">Email address</label>
                <input type="email" class="form-control" id="p_email" value="<?php echo e($current['email']); ?>" disabled>
                <div class="form-text">The email address is your login and cannot be changed here.</div>
            </div>
            <div class="col-md-6">
                <label for="p_phone" class="form-label">Contact number</label>
                <input type="tel" class="form-control" id="p_phone" name="phone" maxlength="20"
                       pattern="[0-9+\- ]{7,20}" value="<?php echo e($f['phone']); ?>" required>
                <div class="invalid-feedback">Enter a valid phone number.</div>
            </div>
            <div class="col-md-3">
                <label for="p_dob" class="form-label">Date of birth</label>
                <input type="date" class="form-control" id="p_dob" name="date_of_birth"
                       max="<?php echo $today; ?>" value="<?php echo e($f['date_of_birth']); ?>" required>
                <div class="invalid-feedback">Choose your date of birth.</div>
            </div>
            <div class="col-md-3">
                <label for="p_gender" class="form-label">Gender</label>
                <select class="form-select" id="p_gender" name="gender" required>
                    <option value="">Choose...</option>
                    <?php foreach (['Male', 'Female', 'Other'] as $g): ?>
                        <option<?php echo selected($f['gender'], $g); ?>><?php echo $g; ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="invalid-feedback">Choose one.</div>
            </div>

            <div class="col-md-4">
                <label for="p_group" class="form-label">Blood group</label>
                <select class="form-select" id="p_group" name="blood_group_id" required>
                    <option value="">Choose...</option>
                    <?php foreach ($bloodGroups as $bg): ?>
                        <option value="<?php echo (int)$bg['blood_group_id']; ?>"<?php echo selected($f['blood_group_id'], $bg['blood_group_id']); ?>>
                            <?php echo e($bg['group_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="invalid-feedback">Choose your blood group.</div>
            </div>
            <div class="col-md-4">
                <label for="p_district" class="form-label">District</label>
                <select class="form-select" id="p_district" name="district_id" required>
                    <option value="">Choose...</option>
                    <?php foreach ($districts as $di): ?>
                        <option value="<?php echo (int)$di['district_id']; ?>"<?php echo selected($f['district_id'], $di['district_id']); ?>>
                            <?php echo e($di['district_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="invalid-feedback">Choose your district.</div>
            </div>
            <div class="col-md-4">
                <label for="p_area" class="form-label">Area</label>
                <input type="text" class="form-control" id="p_area" name="area" maxlength="100"
                       value="<?php echo e($f['area']); ?>" required>
                <div class="invalid-feedback">Enter your area.</div>
            </div>

            <div class="col-12">
                <label for="p_address" class="form-label">Full address</label>
                <input type="text" class="form-control" id="p_address" name="address" maxlength="255"
                       value="<?php echo e($f['address']); ?>" required>
                <div class="invalid-feedback">Enter your address.</div>
            </div>

            <div class="col-md-6">
                <label for="p_last" class="form-label">Last blood donation date</label>
                <input type="date" class="form-control" id="p_last" name="last_donation_date"
                       max="<?php echo $today; ?>" value="<?php echo e($f['last_donation_date']); ?>">
                <div class="form-text">Leave empty if you have never donated.</div>
            </div>
            <div class="col-md-6">
                <label for="p_avail" class="form-label">Available to donate now?</label>
                <select class="form-select" id="p_avail" name="is_available">
                    <option value="1"<?php echo selected($f['is_available'], '1'); ?>>Available</option>
                    <option value="0"<?php echo selected($f['is_available'], '0'); ?>>Unavailable</option>
                </select>
            </div>

            <div class="col-12">
                <label for="p_medical" class="form-label">Medical issues (if any)</label>
                <textarea class="form-control" id="p_medical" name="medical_issues" rows="3"
                          maxlength="1000"><?php echo e($f['medical_issues']); ?></textarea>
                <div class="form-text">
                    The admin may read this. It does not decide whether you can donate:
                    a doctor or nurse at the donation centre does.
                </div>
            </div>
        </div>

        <button type="submit" class="btn btn-primary mt-4">Save changes</button>
    </form>
</div>

<!-- ============ CHANGE PASSWORD ============ -->
<div class="bl-panel">
    <h2 class="bl-panel-title">Change password</h2>

    <?php if ($pwErrors): ?>
        <div class="alert alert-danger" role="alert">
            <ul class="mb-0">
                <?php foreach ($pwErrors as $err): ?><li><?php echo e($err); ?></li><?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" action="" class="needs-validation" novalidate>
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="password">

        <div class="row g-3">
            <div class="col-md-4">
                <label for="c_current" class="form-label">Current password</label>
                <input type="password" class="form-control" id="c_current" name="current_password"
                       maxlength="72" autocomplete="current-password" required>
                <div class="invalid-feedback">Enter your current password.</div>
            </div>
            <div class="col-md-4">
                <label for="c_new" class="form-label">New password</label>
                <input type="password" class="form-control" id="c_new" name="password"
                       minlength="8" maxlength="72" autocomplete="new-password" required>
                <div class="invalid-feedback">Use at least 8 characters.</div>
            </div>
            <div class="col-md-4">
                <label for="c_confirm" class="form-label">Confirm new password</label>
                <input type="password" class="form-control" id="c_confirm" name="confirm_password"
                       maxlength="72" autocomplete="new-password" required>
                <div class="invalid-feedback">The passwords do not match.</div>
            </div>
        </div>

        <button type="submit" class="btn btn-outline-blood mt-4">Change password</button>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/dash_footer.php'; ?>
