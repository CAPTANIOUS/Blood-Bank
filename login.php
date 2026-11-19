<?php
/*
 * login.php  -  One login page for Admin, Donor and Requester.
 * After a correct password, each role is sent to its own dashboard.
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

// Already logged in? Go straight to your dashboard.
if (is_logged_in()) {
    redirect(dashboard_url($_SESSION['role']));
}

$pageTitle = 'Login';
$errors    = [];
$email     = '';

const MAX_ATTEMPTS = 5;     // wrong passwords allowed...
const LOCK_SECONDS = 60;    // ...before we make the person wait this long

// A fake password hash. If the email does not exist we still check against it,
// so the page takes the same time and attackers cannot guess which emails exist.
const DUMMY_HASH = '$2y$10$EchcalREKwxhxxxOccNH6OvFWyq0VUf4rBV8Hmg86nTioAhL4tqlK';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email    = strtolower(clean($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Your session expired. Please try again.';

    } elseif (($_SESSION['lock_until'] ?? 0) > time()) {
        $wait = $_SESSION['lock_until'] - time();
        $errors[] = "Too many wrong attempts. Please wait $wait seconds and try again.";

    } else {
        // Look for the account (prepared statement = safe from SQL injection)
        $stmt = $pdo->prepare(
            'SELECT user_id, full_name, password_hash, role, is_active
             FROM users WHERE email = ?'
        );
        $stmt->execute([$email]);
        $user = $stmt->fetch();            // one row, or false if not found

        $passwordOk = password_verify($password, $user['password_hash'] ?? DUMMY_HASH) && $user;

        if ($passwordOk && !$user['is_active']) {
            $errors[] = 'This account has been deactivated. Please contact the admin.';

        } elseif ($passwordOk) {
            // SUCCESS
            unset($_SESSION['failed_attempts'], $_SESSION['lock_until']);
            login_user($user);

            if ($user['role'] === 'admin') {
                log_admin_action($pdo, (int)$user['user_id'], 'Admin login');
            }

            set_flash('success', 'Welcome back, ' . $user['full_name'] . '.');
            redirect(dashboard_url($user['role']));

        } else {
            // WRONG email or password: count the failure
            $_SESSION['failed_attempts'] = ($_SESSION['failed_attempts'] ?? 0) + 1;
            if ($_SESSION['failed_attempts'] >= MAX_ATTEMPTS) {
                $_SESSION['lock_until']      = time() + LOCK_SECONDS;
                $_SESSION['failed_attempts'] = 0;
            }
            // Same message for "no such email" and "wrong password" on purpose
            $errors[] = 'The email or password is incorrect.';
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<section class="bl-section">
    <div class="container">
        <div class="bl-auth-narrow">

            <h1 class="h2 mb-1">Welcome back</h1>
            <p class="text-secondary mb-4">Login to see your dashboard.</p>

            <div class="bl-auth-card">

                <?php if ($errors): ?>
                    <div class="alert alert-danger" role="alert">
                        <?php foreach ($errors as $err): ?><div><?php echo e($err); ?></div><?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <form method="post" action="" class="needs-validation" novalidate>
                    <?php echo csrf_field(); ?>

                    <div class="mb-3">
                        <label for="email" class="form-label">Email address</label>
                        <input type="email" class="form-control" id="email" name="email"
                               value="<?php echo e($email); ?>" autocomplete="email" required>
                        <div class="invalid-feedback">Enter a valid email address.</div>
                    </div>

                    <div class="mb-4">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" name="password"
                               autocomplete="current-password" required>
                        <div class="invalid-feedback">Enter your password.</div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 btn-lg">Login</button>
                </form>
            </div>

            <p class="text-center mt-4 mb-0">
                New to BloodBank?
                <a href="<?php echo BASE_URL; ?>/register.php">Create an account</a>
            </p>

        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
