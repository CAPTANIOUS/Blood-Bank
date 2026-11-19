<?php
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) { http_response_code(403); exit; }   // never run this file on its own
/*
 * includes/header.php
 * The top part of every PUBLIC page: <head>, the navigation bar and
 * one-time messages. (Dashboards use dash_header.php instead.)
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/auth.php';      // starts the session (must happen before any HTML)

include __DIR__ . '/head.php';
?>
<body>

<?php include __DIR__ . '/navbar.php'; ?>

<main>

<?php
// A one-time message (for example "Account created") saved by the previous page
$flash = get_flash();
if ($flash): ?>
    <div class="container mt-3">
        <div class="alert alert-<?php echo e($flash['type']); ?> mb-0" role="alert" data-autohide>
            <?php echo e($flash['message']); ?>
        </div>
    </div>
<?php endif; ?>
