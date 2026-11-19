<?php
/*
 * become_donor.php  -  Information page for people who want to donate
 */
$pageTitle = 'Become a Donor';
require_once __DIR__ . '/includes/header.php';
?>

<section class="bl-page-head">
    <div class="container">
        <h1>Become a donor</h1>
        <p>Register once. When a request matches your blood group and area, you decide whether to help.</p>
    </div>
</section>

<section class="bl-section">
    <div class="container">
        <div class="row g-5">

            <div class="col-lg-7">
                <div class="bl-prose">
                    <h2 class="mt-0">What you will be asked</h2>
                    <ul>
                        <li>Your name, email address, phone number and a password</li>
                        <li>Date of birth, gender and blood group</li>
                        <li>Your address, district and area</li>
                        <li>The date of your last blood donation, if any</li>
                        <li>Any medical issues you want to mention</li>
                    </ul>

                    <h2>What you control</h2>
                    <ul>
                        <li>Switch between <strong>Available</strong> and <strong>Unavailable</strong> at any time.</li>
                        <li><strong>Accept or decline</strong> each request. Declining has no penalty.</li>
                        <li>Your phone number is shown only to logged-in users, never to the public.</li>
                    </ul>

                    <h2>Before you donate</h2>
                    <p>
                        Blood banks and hospitals have their own health checks and rules about
                        how long to wait between donations. BloodBank does not check this for you
                        and never says that you are &ldquo;eligible&rdquo;. A doctor or nurse at the
                        donation centre makes that decision.
                    </p>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="bl-auth-card">
                    <h2 class="h4">Ready to help?</h2>
                    <p class="text-secondary">Creating a donor account takes about two minutes.</p>
                    <div class="d-grid gap-2">
                        <a class="btn btn-primary btn-lg" href="<?php echo BASE_URL; ?>/register.php?role=donor">Register as a donor</a>
                        <a class="btn btn-outline-blood" href="<?php echo BASE_URL; ?>/login.php">I already have an account</a>
                    </div>
                </div>
            </div>

        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
