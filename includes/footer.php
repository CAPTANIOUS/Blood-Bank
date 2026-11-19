<?php if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) { http_response_code(403); exit; } ?>
</main>

<footer class="bl-footer">
    <div class="container">
        <div class="row gy-4">
            <div class="col-lg-5">
                <div class="bl-brand text-white mb-2">
                    <svg class="bl-drop" viewBox="0 0 24 24" aria-hidden="true">
                        <path fill="currentColor" d="M12 2.5c3.6 4.3 6.5 7.6 6.5 11.2a6.5 6.5 0 0 1-13 0C5.5 10.1 8.4 6.8 12 2.5z"/>
                    </svg>
                    <?php echo APP_NAME; ?>
                </div>
                <p class="mb-0 bl-footer-text">
                    BloodBank connects people who need blood with people willing to donate.
                    It does not give medical advice. Whether someone can donate or receive
                    blood is decided by a doctor or a blood bank, never by this website.
                </p>
            </div>
            <div class="col-6 col-lg-3 offset-lg-1">
                <h2 class="h6 text-white">Explore</h2>
                <ul class="list-unstyled mb-0">
                    <li><a href="<?php echo BASE_URL; ?>/find_donors.php">Find donors</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/become_donor.php">Become a donor</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/request_blood.php">Request blood</a></li>
                </ul>
            </div>
            <div class="col-6 col-lg-3">
                <h2 class="h6 text-white">Account</h2>
                <ul class="list-unstyled mb-0">
                    <li><a href="<?php echo BASE_URL; ?>/login.php">Login</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/register.php">Register</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/about.php">About BloodBank</a></li>
                </ul>
            </div>
        </div>
        <hr class="bl-footer-line">
        <p class="small mb-0 bl-footer-text">&copy; <?php echo date('Y'); ?> BloodBank &ndash; University DBMS project</p>
    </div>
</footer>

<?php include __DIR__ . '/scripts.php'; ?>
