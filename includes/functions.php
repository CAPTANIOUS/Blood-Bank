<?php
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) { http_response_code(403); exit; }   // never run this file on its own
/*
 * includes/functions.php
 * Small helper functions used by many pages.
 */
require_once __DIR__ . '/../config/config.php';

/* ---------- Safe output (stops XSS attacks) ----------
 * ALWAYS print user data like this:  <?php echo e($name); ?>
 * It turns  <script>  into harmless text.                           */
function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/* ---------- Move to another page ---------- */
function redirect(string $path): void
{
    header('Location: ' . BASE_URL . $path);
    exit;
}

/* ---------- Remove spaces from the start and end of typed text ---------- */
function clean($value): string
{
    return trim((string)$value);
}

/* ---------- Flash message: shown once on the next page ----------
 * type = success | danger | warning | info                          */
function set_flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function get_flash(): ?array
{
    if (!isset($_SESSION['flash'])) {
        return null;
    }
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);          // show it only once
    return $flash;
}

/* ---------- CSRF protection ----------
 * Every form carries a secret token. A fake form on another website
 * cannot know it, so the server can reject such requests.           */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf($token): bool
{
    return isset($_SESSION['csrf_token'])
        && is_string($token)
        && hash_equals($_SESSION['csrf_token'], $token);
}

/* ---------- Validators ---------- */
function is_valid_date(string $date): bool
{
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d !== false && $d->format('Y-m-d') === $date;
}

// "0171 234-5678"  ->  "01712345678"
function normalize_phone(string $phone): string
{
    return preg_replace('/[\s-]+/', '', $phone);
}

// Used inside <option> tags to keep a choice after an error
function selected($current, $value): string
{
    return ((string)$current === (string)$value) ? ' selected' : '';
}

/* ---------- Is this page file present yet? ----------
 * Returns the web address of a page, or null if we have not built it yet.
 * The dashboard menus use this: a link switches on by itself the day we
 * create its page.                                                  */
function page_url(string $relativePath): ?string
{
    return file_exists(__DIR__ . '/../' . $relativePath)
        ? BASE_URL . '/' . $relativePath
        : null;
}

/* ---------- Coloured request-status label ---------- */
function status_badge(string $status): string
{
    $classes = [
        'Pending'       => 'pending',
        'Under Review'  => 'review',
        'Approved'      => 'approved',
        'Donor Matched' => 'matched',
        'Completed'     => 'completed',
        'Rejected'      => 'rejected',
        'Cancelled'     => 'cancelled',
    ];
    $class = $classes[$status] ?? 'pending';
    return '<span class="bl-status bl-status-' . $class . '">' . e($status) . '</span>';
}

/* ---------- Write an entry in the admin_logs table ---------- */
function log_admin_action(PDO $pdo, int $adminId, string $action, ?string $table = null,
                          ?int $recordId = null, ?string $details = null): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO admin_logs (admin_id, action, table_name, record_id, details, ip_address)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([$adminId, $action, $table, $recordId, $details, $_SERVER['REMOTE_ADDR'] ?? null]);
}
