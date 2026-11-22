<?php
/*
 * index.php  -  Home page
 */
$pageTitle = 'Home';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/header.php';

// Live numbers for the strip under the banner (one small query each, no private data)
$stats = $pdo->query(
    "SELECT (SELECT COUNT(*) FROM donors d JOIN users u ON u.user_id = d.user_id
              WHERE u.is_active = 1 AND d.is_available = 1)                 AS donors,
            (SELECT COUNT(*) FROM blood_requests WHERE status = 'Completed') AS completed,
            (SELECT COALESCE(SUM(bags_donated), 0) FROM donations)           AS bags"
)->fetch();

// The 8 blood groups used by the search tiles
$bloodGroups = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
?>

<!-- ============ HERO ============ -->
<section class="bl-hero">
    <div class="container">
        <div class="row align-items-center gy-5">

            <div class="col-lg-6">
                <h1>Find a blood donor near you, fast.</h1>
                <p class="lead mt-3 mb-4">
                    BloodBank connects families who need blood with people willing to give it.
                    Every request is checked by an admin before any donor is contacted.
                </p>
                <div class="d-flex flex-wrap gap-2">
                    <a href="<?php echo BASE_URL; ?>/request_blood.php" class="btn btn-primary btn-lg">Request blood</a>
                    <a href="<?php echo BASE_URL; ?>/become_donor.php" class="btn btn-outline-blood btn-lg">Become a donor</a>
                </div>
            </div>

            <div class="col-lg-5 offset-lg-1">
                <div class="bl-finder">
                    <h2>Who needs blood?</h2>
                    <p>Pick a blood group to see available donors.</p>
                    <div class="row g-2 g-sm-3">
                        <?php foreach ($bloodGroups as $group): ?>
                            <div class="col-3">
                                <a class="bl-tile"
                                   href="<?php echo BASE_URL; ?>/find_donors.php?group=<?php echo urlencode($group); ?>"
                                   aria-label="Find <?php echo $group; ?> donors"><?php echo $group; ?></a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

        </div>
    </div>
</section>

<!-- ============ LIVE NUMBERS ============ -->
<section class="py-4 bg-white border-bottom">
    <div class="container">
        <div class="row text-center g-3">
            <div class="col-4"><div class="display-6 fw-bold text-danger"><?php echo (int)$stats['donors']; ?></div><div class="text-secondary small">Donors ready to help</div></div>
            <div class="col-4"><div class="display-6 fw-bold text-danger"><?php echo (int)$stats['completed']; ?></div><div class="text-secondary small">Requests completed</div></div>
            <div class="col-4"><div class="display-6 fw-bold text-danger"><?php echo (int)$stats['bags']; ?></div><div class="text-secondary small">Blood bags donated</div></div>
        </div>
    </div>
</section>

<!-- ============ HOW IT WORKS ============ -->
<section class="bl-section bl-section-white">
    <div class="container">
        <h2>How BloodBank works</h2>
        <p class="sub mb-5">One website, two paths. Both end with an admin confirming that the blood was really received.</p>

        <div class="row g-4">
            <div class="col-md-6">
                <div class="bl-card">
                    <h3>If you need blood</h3>
                    <ol class="bl-steps">
                        <li><strong>Create an account and send a request</strong>
                            <span>Add the patient, hospital, blood group and a medical document.</span></li>
                        <li><strong>An admin reviews it</strong>
                            <span>Requests with clear information are approved.</span></li>
                        <li><strong>Matching donors respond</strong>
                            <span>You can see who accepted and follow the status.</span></li>
                        <li><strong>The admin marks it completed</strong>
                            <span>Only after the blood has actually been received.</span></li>
                    </ol>
                </div>
            </div>

            <div class="col-md-6">
                <div class="bl-card">
                    <h3>If you want to donate</h3>
                    <ol class="bl-steps">
                        <li><strong>Register as a donor</strong>
                            <span>Share your blood group, district and last donation date.</span></li>
                        <li><strong>Set your availability</strong>
                            <span>Switch to unavailable whenever you need a break.</span></li>
                        <li><strong>Accept or decline requests</strong>
                            <span>You only see requests that match your blood group.</span></li>
                        <li><strong>Keep your donation history</strong>
                            <span>Every completed donation is saved in your profile.</span></li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ============ PROMISES ============ -->
<section class="bl-section">
    <div class="container">
        <div class="row g-4">
            <div class="col-md-4">
                <div class="bl-promise">
                    <h3>Requests are checked</h3>
                    <p>An admin reads each request and its medical document before donors are asked.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="bl-promise">
                    <h3>Phone numbers stay private</h3>
                    <p>Visitors can search donors by blood group and area. Contact details need a login.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="bl-promise">
                    <h3>Doctors decide eligibility</h3>
                    <p>BloodBank shows reminders only. A medical professional decides who can donate.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ============ CALL TO ACTION ============ -->
<section class="bl-cta">
    <div class="container">
        <div class="row align-items-center gy-3">
            <div class="col-lg-8">
                <h2 class="mb-1">Ready to give blood, or ask for it?</h2>
                <p class="mb-0">Registration takes about two minutes.</p>
            </div>
            <div class="col-lg-4 text-lg-end">
                <a href="<?php echo BASE_URL; ?>/register.php" class="btn btn-light-on-red btn-lg">Create an account</a>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
