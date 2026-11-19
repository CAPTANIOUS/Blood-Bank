<?php
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) { http_response_code(403); exit; }   // never run this file on its own
/*
 * includes/dash_header.php
 * The top of every DASHBOARD page: top bar + sidebar.
 * The page must set BEFORE including this file:
 *     $dashRole  = 'admin' | 'donor' | 'requester'
 *     $pageTitle = 'Title shown in the browser tab'
 */
if (!isset($dashRole)) { http_response_code(403); exit; }   // this file is only for pages that include it
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/menus.php';

require_role($dashRole);          // the guard: wrong role or not logged in = stopped here

$me          = current_user();
// A page may set $activeFile to highlight another menu item (e.g. donor_view.php lights up "Donors")
$currentFile = $activeFile ?? basename($_SERVER['PHP_SELF']);
$roleLabels  = ['admin' => 'Admin', 'donor' => 'Donor', 'requester' => 'Requester'];

include __DIR__ . '/head.php';
?>
<body class="bl-dash-body">

<!-- ============ TOP BAR ============ -->
<header class="bl-topbar">
    <button class="btn btn-outline-blood btn-sm d-lg-none" type="button"
            data-bs-toggle="offcanvas" data-bs-target="#dashSidebar" aria-controls="dashSidebar">Menu</button>

    <a class="bl-brand" href="<?php echo BASE_URL . dashboard_url($dashRole); ?>">
        <svg class="bl-drop" viewBox="0 0 24 24" aria-hidden="true">
            <path fill="currentColor" d="M12 2.5c3.6 4.3 6.5 7.6 6.5 11.2a6.5 6.5 0 0 1-13 0C5.5 10.1 8.4 6.8 12 2.5z"/>
        </svg>
        <?php echo APP_NAME; ?>
    </a>

    <div class="ms-auto d-flex align-items-center gap-3">
        <span class="d-none d-sm-inline">
            <?php echo e($me['name']); ?>
            <span class="bl-role-chip"><?php echo e($roleLabels[$dashRole]); ?></span>
        </span>
        <form method="post" action="<?php echo BASE_URL; ?>/logout.php" class="m-0">
            <?php echo csrf_field(); ?>
            <button type="submit" class="btn btn-outline-blood btn-sm">Logout</button>
        </form>
    </div>
</header>

<div class="bl-dash-wrap">

    <!-- ============ SIDEBAR (slides in on phones) ============ -->
    <aside class="offcanvas-lg offcanvas-start bl-sidebar bl-side-<?php echo e($dashRole); ?>"
           tabindex="-1" id="dashSidebar" aria-label="Menu">
        <div class="offcanvas-header d-lg-none">
            <span class="fw-bold">Menu</span>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas"
                    data-bs-target="#dashSidebar" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body">
            <nav class="bl-side-inner" aria-label="<?php echo e($roleLabels[$dashRole]); ?> menu">
                <?php foreach (dash_menu($dashRole) as $file => $label):
                    $url      = page_url($file);                       // null = page not built yet
                    $isActive = (basename($file) === $currentFile);
                ?>
                    <?php if ($url): ?>
                        <a class="bl-side-link<?php echo $isActive ? ' active' : ''; ?>"
                           href="<?php echo e($url); ?>"><?php echo e($label); ?></a>
                    <?php else: ?>
                        <span class="bl-side-link disabled" title="Coming soon"><?php echo e($label); ?></span>
                    <?php endif; ?>
                <?php endforeach; ?>
            </nav>
        </div>
    </aside>

    <!-- ============ PAGE CONTENT ============ -->
    <main class="bl-dash-main">
<?php
$flash = get_flash();
if ($flash): ?>
        <div class="alert alert-<?php echo e($flash['type']); ?>" role="alert" data-autohide>
            <?php echo e($flash['message']); ?>
        </div>
<?php endif; ?>
