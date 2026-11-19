<?php
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) { http_response_code(403); exit; }   // never run this file on its own
/*
 * includes/navbar.php
 * The public navigation bar. Visitors see Login/Register;
 * logged-in people see "My dashboard" and Logout.
 */

// Name of the page we are on, e.g. "about.php" (used to highlight the link)
$currentPage = basename($_SERVER['PHP_SELF']);

// Menu items:  file name => text shown
$menu = [
    'index.php'          => 'Home',
    'about.php'          => 'About',
    'find_donors.php'    => 'Find Donors',
    'become_donor.php'   => 'Become a Donor',
    'request_blood.php'  => 'Request Blood',
];
?>
<nav class="navbar navbar-expand-lg bl-navbar sticky-top">
    <div class="container">

        <a class="navbar-brand bl-brand" href="<?php echo BASE_URL; ?>/index.php">
            <svg class="bl-drop" viewBox="0 0 24 24" aria-hidden="true">
                <path fill="currentColor" d="M12 2.5c3.6 4.3 6.5 7.6 6.5 11.2a6.5 6.5 0 0 1-13 0C5.5 10.1 8.4 6.8 12 2.5z"/>
            </svg>
            <?php echo APP_NAME; ?>
        </a>

        <!-- the "hamburger" button that appears on phones -->
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
                data-bs-target="#mainMenu" aria-controls="mainMenu"
                aria-expanded="false" aria-label="Open menu">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="mainMenu">
            <ul class="navbar-nav mx-lg-auto mb-2 mb-lg-0">
                <?php foreach ($menu as $file => $label): ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($currentPage === $file) ? 'active' : ''; ?>"
                           href="<?php echo BASE_URL . '/' . $file; ?>"><?php echo $label; ?></a>
                    </li>
                <?php endforeach; ?>
            </ul>

            <div class="d-flex gap-2">
                <?php if (is_logged_in()): ?>
                    <a class="btn btn-outline-blood" href="<?php echo BASE_URL . dashboard_url($_SESSION['role']); ?>">My dashboard</a>
                    <form method="post" action="<?php echo BASE_URL; ?>/logout.php" class="m-0">
                        <?php echo csrf_field(); ?>
                        <button type="submit" class="btn btn-primary">Logout</button>
                    </form>
                <?php else: ?>
                    <a class="btn btn-outline-blood" href="<?php echo BASE_URL; ?>/login.php">Login</a>
                    <a class="btn btn-primary" href="<?php echo BASE_URL; ?>/register.php">Register</a>
                <?php endif; ?>
            </div>
        </div>

    </div>
</nav>
