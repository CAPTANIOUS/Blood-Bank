<?php
/*
 * 404.php  -  Friendly "page not found" page.
 * The root .htaccess file tells Apache to show this page for every missing address.
 */
require_once __DIR__ . '/config/config.php';

http_response_code(404);                 // 404 = "not found"
$pageTitle = 'Page not found';
require_once __DIR__ . '/includes/header.php';
?>

<section class="bl-section">
    <div class="container">
        <div class="bl-auth-narrow text-center">
            <p class="display-1 fw-bold text-danger mb-0">404</p>
            <h1 class="h2">This page was not found</h1>
            <p class="text-secondary">The address may be wrong, or the page may have moved.</p>
            <a class="btn btn-primary" href="<?php echo BASE_URL; ?>/index.php">Go to the home page</a>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
