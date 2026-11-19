<?php
/*
 * about.php  -  About BloodBank
 */
$pageTitle = 'About';
require_once __DIR__ . '/includes/header.php';
?>

<section class="bl-page-head">
    <div class="container">
        <h1>About BloodBank</h1>
        <p>A simple way to connect people who need blood with people who are willing to donate.</p>
    </div>
</section>

<section class="bl-section">
    <div class="container">
        <div class="bl-prose">
            <h2>Why BloodBank exists</h2>
            <p>
                When a patient needs blood, families often start by calling friends and posting in
                social media groups. BloodBank puts this in one place: a request with the hospital
                details and a medical document, an admin who checks it, and donors who can say yes or no.
            </p>

            <h2>Three kinds of accounts</h2>
            <ul>
                <li><strong>Donor</strong> &ndash; registers once, keeps a profile, chooses when to be available and sees matching requests.</li>
                <li><strong>Requester</strong> &ndash; sends blood requests, uploads the medical document and follows the status.</li>
                <li><strong>Admin</strong> &ndash; checks requests, matches donors and confirms when blood has really been received.</li>
            </ul>

            <h2>What BloodBank does not do</h2>
            <p>
                BloodBank does not decide who is healthy enough to donate or receive blood.
                It only shows reminders. That decision belongs to a doctor or a blood bank.
                BloodBank is also not an emergency service: if a life is in immediate danger,
                go to the hospital or call 999 first.
            </p>

            <h2>About this project</h2>
            <p>
                BloodBank is a university DBMS project. It is built with HTML, CSS, JavaScript,
                Bootstrap 5, core PHP and a MySQL database.
            </p>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
