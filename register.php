<?php
/*
 * register.php  -  Create an account (Donor or Requester)
 *
 * Order of this file:
 *   1. load helpers and the database
 *   2. if the form was sent (POST): check every field, then save to MySQL
 *   3. show the HTML form (with error messages if something was wrong)
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/donor_fields.php';

$pageTitle = 'Register';
$errors    = [];     // list of error messages
$old       = [];     // what the person typed, so we can fill the form again
$today     = date('Y-m-d');

// ?role=requester in the address opens the "I need blood" tab
$role = (isset($_GET['role']) && $_GET['role'] === 'requester') ? 'requester' : 'donor';

// Lists for the drop-down menus come from the database
$bloodGroups = $pdo->query('SELECT blood_group_id, group_name FROM blood_groups ORDER BY blood_group_id')->fetchAll();
$districts   = $pdo->query('SELECT district_id, district_name FROM districts ORDER BY district_name')->fetchAll();

/* ===================== 2. THE FORM WAS SENT ===================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $role = (($_POST['role'] ?? '') === 'requester') ? 'requester' : 'donor';

    $old = $_POST;                                   // remember typed values...
    unset($old['password'], $old['confirm_password']);   // ...but never passwords

    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Your session expired. Please try again.';
    } else {

        // ---- fields every account has ----
        $fullName = clean($_POST['full_name'] ?? '');
        $email    = strtolower(clean($_POST['email'] ?? ''));
        $phone    = normalize_phone(clean($_POST['phone'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $confirm  = (string)($_POST['confirm_password'] ?? '');

        if (strlen($fullName) < 2 || strlen($fullName) > 100) {
            $errors[] = 'Enter your full name (2 to 100 characters).';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 120) {
            $errors[] = 'Enter a valid email address.';
        }
        if (!preg_match('/^\+?[0-9]{7,15}$/', $phone)) {
            $errors[] = 'Enter a valid phone number (digits only, 7 to 15 numbers).';
        }
        if (strlen($password) < 8) {
            $errors[] = 'The password must have at least 8 characters.';
        } elseif (strlen($password) > 72) {
            $errors[] = 'The password must have 72 characters or fewer.';
        }
        if ($password !== $confirm) {
            $errors[] = 'The two passwords do not match.';
        }

        // ---- extra fields for donors (checked by the shared function) ----
        if ($role === 'donor') {
            [$donor, $donorErrors] = validate_donor_input($_POST, $bloodGroups, $districts);
            $errors = array_merge($errors, $donorErrors);
        }

        // ---- is the email or phone already used? ----
        if (!$errors) {
            $stmt = $pdo->prepare('SELECT email, phone FROM users WHERE email = ? OR phone = ?');
            $stmt->execute([$email, $phone]);
            foreach ($stmt->fetchAll() as $row) {
                if ($row['email'] === $email) {
                    $errors[] = 'This email address is already registered. Try to login instead.';
                }
                if ($row['phone'] === $phone) {
                    $errors[] = 'This phone number is already registered.';
                }
            }
        }

        // ---- everything is fine: save ----
        if (!$errors) {
            $hash = password_hash($password, PASSWORD_DEFAULT);   // never store the real password

            try {
                // A transaction = "all or nothing": if the donor row fails,
                // the user row is removed again.
                $pdo->beginTransaction();

                $stmt = $pdo->prepare(
                    'INSERT INTO users (full_name, email, password_hash, phone, role)
                     VALUES (?, ?, ?, ?, ?)'
                );
                $stmt->execute([$fullName, $email, $hash, $phone, $role]);
                $userId = (int)$pdo->lastInsertId();

                if ($role === 'donor') {
                    $stmt = $pdo->prepare(
                        'INSERT INTO donors (user_id, date_of_birth, gender, blood_group_id, address,
                                             district_id, area, last_donation_date, medical_issues, is_available)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                    );
                    $stmt->execute([
                        $userId, $donor['date_of_birth'], $donor['gender'], $donor['blood_group_id'],
                        $donor['address'], $donor['district_id'], $donor['area'],
                        ($donor['last_donation_date'] !== '' ? $donor['last_donation_date'] : null), // empty = NULL = never donated
                        ($donor['medical_issues'] !== '' ? $donor['medical_issues'] : null),
                        $donor['is_available'],
                    ]);
                }

                $pdo->commit();

                set_flash('success', 'Account created. You can login now.');
                redirect('/login.php');

            } catch (PDOException $ex) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                // 1062 = "duplicate entry": someone registered the same email at the same moment
                if (($ex->errorInfo[1] ?? 0) === 1062) {
                    $errors[] = 'This email address or phone number is already registered.';
                } else {
                    $errors[] = 'Something went wrong while saving. Please try again.';
                }
            }
        }
    }
}

// Only the form that was sent gets its old values back
$oD = ($role === 'donor')     ? $old : [];
$oR = ($role === 'requester') ? $old : [];

/* ===================== 3. SHOW THE PAGE ===================== */
require_once __DIR__ . '/includes/header.php';
?>

<section class="bl-section">
    <div class="container">
        <div class="mx-auto" style="max-width: 760px;">

            <h1 class="h2 mb-1">Create your account</h1>
            <p class="text-secondary mb-4">Choose what you want to do. You can change your details later.</p>

            <!-- the two-button switch -->
            <ul class="nav bl-role-switch mb-4" role="tablist">
                <li class="nav-item flex-fill" role="presentation">
                    <button class="nav-link w-100 <?php echo $role === 'donor' ? 'active' : ''; ?>"
                            data-bs-toggle="pill" data-bs-target="#donorTab" type="button" role="tab">
                        I want to donate blood
                    </button>
                </li>
                <li class="nav-item flex-fill" role="presentation">
                    <button class="nav-link w-100 <?php echo $role === 'requester' ? 'active' : ''; ?>"
                            data-bs-toggle="pill" data-bs-target="#requesterTab" type="button" role="tab">
                        I need blood
                    </button>
                </li>
            </ul>

            <div class="tab-content">

                <!-- ================= DONOR FORM ================= -->
                <div class="tab-pane fade <?php echo $role === 'donor' ? 'show active' : ''; ?>" id="donorTab" role="tabpanel">
                    <div class="bl-auth-card">

                        <?php if ($errors && $role === 'donor'): ?>
                            <div class="alert alert-danger" role="alert">
                                <strong>Please fix this:</strong>
                                <ul class="mb-0 mt-1">
                                    <?php foreach ($errors as $err): ?><li><?php echo e($err); ?></li><?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <form method="post" action="" class="needs-validation" novalidate>
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="role" value="donor">

                            <h2 class="bl-form-group-title">Account</h2>
                            <div class="row g-3">
                                <div class="col-12">
                                    <label for="d_name" class="form-label">Full name</label>
                                    <input type="text" class="form-control" id="d_name" name="full_name"
                                           value="<?php echo e($oD['full_name'] ?? ''); ?>"
                                           maxlength="100" autocomplete="name" required>
                                    <div class="invalid-feedback">Enter your full name.</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="d_email" class="form-label">Email address</label>
                                    <input type="email" class="form-control" id="d_email" name="email"
                                           value="<?php echo e($oD['email'] ?? ''); ?>"
                                           maxlength="120" autocomplete="email" required>
                                    <div class="invalid-feedback">Enter a valid email address.</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="d_phone" class="form-label">Contact number</label>
                                    <input type="tel" class="form-control" id="d_phone" name="phone"
                                           value="<?php echo e($oD['phone'] ?? ''); ?>"
                                           pattern="[0-9+\- ]{7,20}" maxlength="20" autocomplete="tel" required>
                                    <div class="invalid-feedback">Enter a valid phone number.</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="d_pass" class="form-label">Password</label>
                                    <input type="password" class="form-control" id="d_pass" name="password"
                                           minlength="8" maxlength="72" autocomplete="new-password" required>
                                    <div class="form-text">At least 8 characters.</div>
                                    <div class="invalid-feedback">Use at least 8 characters.</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="d_pass2" class="form-label">Confirm password</label>
                                    <input type="password" class="form-control" id="d_pass2" name="confirm_password"
                                           maxlength="72" autocomplete="new-password" required>
                                    <div class="invalid-feedback">The passwords do not match.</div>
                                </div>
                            </div>

                            <h2 class="bl-form-group-title">About you</h2>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label for="d_dob" class="form-label">Date of birth</label>
                                    <input type="date" class="form-control" id="d_dob" name="date_of_birth"
                                           value="<?php echo e($oD['date_of_birth'] ?? ''); ?>"
                                           max="<?php echo $today; ?>" required>
                                    <div class="invalid-feedback">Choose your date of birth.</div>
                                </div>
                                <div class="col-md-4">
                                    <label for="d_gender" class="form-label">Gender</label>
                                    <select class="form-select" id="d_gender" name="gender" required>
                                        <option value="">Choose...</option>
                                        <?php foreach (['Male', 'Female', 'Other'] as $g): ?>
                                            <option<?php echo selected($oD['gender'] ?? '', $g); ?>><?php echo $g; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="invalid-feedback">Choose one.</div>
                                </div>
                                <div class="col-md-4">
                                    <label for="d_group" class="form-label">Blood group</label>
                                    <select class="form-select" id="d_group" name="blood_group_id" required>
                                        <option value="">Choose...</option>
                                        <?php foreach ($bloodGroups as $bg): ?>
                                            <option value="<?php echo (int)$bg['blood_group_id']; ?>"<?php echo selected($oD['blood_group_id'] ?? '', $bg['blood_group_id']); ?>>
                                                <?php echo e($bg['group_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="invalid-feedback">Choose your blood group.</div>
                                </div>
                            </div>

                            <h2 class="bl-form-group-title">Where you live</h2>
                            <div class="row g-3">
                                <div class="col-12">
                                    <label for="d_address" class="form-label">Full address</label>
                                    <input type="text" class="form-control" id="d_address" name="address"
                                           value="<?php echo e($oD['address'] ?? ''); ?>"
                                           maxlength="255" autocomplete="street-address" required>
                                    <div class="invalid-feedback">Enter your address.</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="d_district" class="form-label">District</label>
                                    <select class="form-select" id="d_district" name="district_id" required>
                                        <option value="">Choose...</option>
                                        <?php foreach ($districts as $d): ?>
                                            <option value="<?php echo (int)$d['district_id']; ?>"<?php echo selected($oD['district_id'] ?? '', $d['district_id']); ?>>
                                                <?php echo e($d['district_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="invalid-feedback">Choose your district.</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="d_area" class="form-label">Area</label>
                                    <input type="text" class="form-control" id="d_area" name="area"
                                           value="<?php echo e($oD['area'] ?? ''); ?>"
                                           maxlength="100" placeholder="e.g. Mirpur 10" required>
                                    <div class="invalid-feedback">Enter your area.</div>
                                </div>
                            </div>

                            <h2 class="bl-form-group-title">Donation details</h2>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="d_last" class="form-label">Last blood donation date</label>
                                    <input type="date" class="form-control" id="d_last" name="last_donation_date"
                                           value="<?php echo e($oD['last_donation_date'] ?? ''); ?>"
                                           max="<?php echo $today; ?>">
                                    <div class="form-text">Leave empty if you have never donated.</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="d_avail" class="form-label">Are you available to donate now?</label>
                                    <select class="form-select" id="d_avail" name="is_available" required>
                                        <option value="1"<?php echo selected($oD['is_available'] ?? '1', '1'); ?>>Available</option>
                                        <option value="0"<?php echo selected($oD['is_available'] ?? '1', '0'); ?>>Unavailable</option>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label for="d_medical" class="form-label">Medical issues (if any)</label>
                                    <textarea class="form-control" id="d_medical" name="medical_issues" rows="3"
                                              maxlength="1000" placeholder="For example: diabetes, recent surgery, medicines you take"><?php echo e($oD['medical_issues'] ?? ''); ?></textarea>
                                    <div class="form-text">
                                        The admin may read this. It does not decide whether you can donate:
                                        a doctor or nurse at the donation centre does.
                                    </div>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary btn-lg w-100 mt-4">Create donor account</button>
                        </form>
                    </div>
                </div>

                <!-- ================= REQUESTER FORM ================= -->
                <div class="tab-pane fade <?php echo $role === 'requester' ? 'show active' : ''; ?>" id="requesterTab" role="tabpanel">
                    <div class="bl-auth-card">

                        <?php if ($errors && $role === 'requester'): ?>
                            <div class="alert alert-danger" role="alert">
                                <strong>Please fix this:</strong>
                                <ul class="mb-0 mt-1">
                                    <?php foreach ($errors as $err): ?><li><?php echo e($err); ?></li><?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <form method="post" action="" class="needs-validation" novalidate>
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="role" value="requester">

                            <div class="row g-3">
                                <div class="col-12">
                                    <label for="r_name" class="form-label">Full name</label>
                                    <input type="text" class="form-control" id="r_name" name="full_name"
                                           value="<?php echo e($oR['full_name'] ?? ''); ?>"
                                           maxlength="100" autocomplete="name" required>
                                    <div class="invalid-feedback">Enter your full name.</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="r_email" class="form-label">Email address</label>
                                    <input type="email" class="form-control" id="r_email" name="email"
                                           value="<?php echo e($oR['email'] ?? ''); ?>"
                                           maxlength="120" autocomplete="email" required>
                                    <div class="invalid-feedback">Enter a valid email address.</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="r_phone" class="form-label">Contact number</label>
                                    <input type="tel" class="form-control" id="r_phone" name="phone"
                                           value="<?php echo e($oR['phone'] ?? ''); ?>"
                                           pattern="[0-9+\- ]{7,20}" maxlength="20" autocomplete="tel" required>
                                    <div class="invalid-feedback">Enter a valid phone number.</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="r_pass" class="form-label">Password</label>
                                    <input type="password" class="form-control" id="r_pass" name="password"
                                           minlength="8" maxlength="72" autocomplete="new-password" required>
                                    <div class="form-text">At least 8 characters.</div>
                                    <div class="invalid-feedback">Use at least 8 characters.</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="r_pass2" class="form-label">Confirm password</label>
                                    <input type="password" class="form-control" id="r_pass2" name="confirm_password"
                                           maxlength="72" autocomplete="new-password" required>
                                    <div class="invalid-feedback">The passwords do not match.</div>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary btn-lg w-100 mt-4">Create requester account</button>
                            <p class="text-secondary small mt-3 mb-0">
                                After you login, you can send a blood request and upload the medical document.
                            </p>
                        </form>
                    </div>
                </div>

            </div>

            <p class="text-center mt-4 mb-0">
                Already have an account?
                <a href="<?php echo BASE_URL; ?>/login.php">Login</a>
            </p>

        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
