<?php
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) { http_response_code(403); exit; }   // never run this file on its own
/*
 * includes/auth.php
 * Everything about "who is this visitor?":
 *   - starts the session (how the server remembers a visitor between pages)
 *   - login / logout helpers
 *   - require_login() and require_role() = the guards at the top of protected pages
 */
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/../config/database.php';    // gives us $pdo for the checks below

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,      // JavaScript cannot read the session cookie
        'samesite' => 'Lax',     // extra protection against fake forms
    ]);
    session_start();
}

/* Is somebody logged in? */
function is_logged_in(): bool
{
    return !empty($_SESSION['user_id']);
}

/* The logged-in person as an array: id, name, role  (or null) */
function current_user(): ?array
{
    if (!is_logged_in()) {
        return null;
    }
    return [
        'id'   => (int)$_SESSION['user_id'],
        'name' => (string)$_SESSION['full_name'],
        'role' => (string)$_SESSION['role'],
    ];
}

/* Each role has its own home page */
function dashboard_url(string $role): string
{
    $pages = [
        'admin'     => '/admin/dashboard.php',
        'donor'     => '/donor/dashboard.php',
        'requester' => '/requester/dashboard.php',
    ];
    return $pages[$role] ?? '/index.php';
}

/* Remember the person in the session after the password was correct */
function login_user(array $user): void
{
    session_regenerate_id(true);    // new session id: stops "session fixation" attacks
    $_SESSION['user_id']   = (int)$user['user_id'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['role']      = $user['role'];
}

/* Forget the person completely */
function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/* Log the person out and show them a message on the login page */
function force_logout(string $type, string $message): void
{
    logout_user();
    session_start();                 // a fresh, empty session...
    session_regenerate_id(true);
    set_flash($type, $message);      // ...just to carry the message
    redirect('/login.php');
}

/* Guard 1: you must be logged in */
function require_login(): void
{
    global $pdo;

    if (!is_logged_in()) {
        set_flash('warning', 'Please login to continue.');
        redirect('/login.php');
    }

    // Ask the database on EVERY protected page. If the admin deactivated this
    // account a minute ago, the person is logged out immediately.
    $stmt = $pdo->prepare('SELECT full_name, role, is_active FROM users WHERE user_id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user || !$user['is_active']) {
        force_logout('danger', 'Your account is not active. Please contact the admin.');
    }

    // Keep the session in step with the database (name or role may have changed)
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['role']      = $user['role'];
}

/* Guard 2: you must be logged in AND have one of these roles.
 * Example at the top of a page:  require_role('admin');             */
function require_role(string ...$roles): void
{
    require_login();

    if (!in_array($_SESSION['role'], $roles, true)) {
        http_response_code(403);                 // 403 = "forbidden"
        $pageTitle = 'Access denied';
        $home      = dashboard_url($_SESSION['role']);
        require __DIR__ . '/header.php';
        ?>
        <section class="bl-section">
            <div class="container">
                <div class="bl-auth-narrow text-center">
                    <h1 class="h2">Access denied</h1>
                    <p class="text-secondary">This page is only for <?php echo e(implode(' or ', $roles)); ?> accounts.</p>
                    <a class="btn btn-primary" href="<?php echo BASE_URL . $home; ?>">Go to my dashboard</a>
                </div>
            </div>
        </section>
        <?php
        require __DIR__ . '/footer.php';
        exit;
    }
}
