<?php
/*
 * request_blood.php  -  Information page for people who need blood.
 * (The real request form lives in requester/create_request.php and needs a login.)
 */
$pageTitle = 'Request Blood';
require_once __DIR__ . '/includes/header.php';
?>

<section class="bl-page-head">
    <div class="container">
        <h1>Request blood</h1>
        <p>Send a request with the hospital details and a medical document. An admin reviews it before donors are asked.</p>
    </div>
</section>

<section class="bl-section">
    <div class="container">

        <div class="alert alert-danger" role="alert">
            <strong>In an emergency, go to the hospital or call 999 first.</strong>
            BloodBank is not an emergency service and cannot promise how fast a donor will respond.
        </div>

        <div class="row g-5 mt-1">

            <div class="col-lg-7">
                <div class="bl-prose">
                    <h2 class="mt-0">Have these ready</h2>
                    <ul>
                        <li>Patient name and your own name and phone number</li>
                        <li>Blood group and the number of bags needed</li>
                        <li>The medical condition, hospital name, address, district and area</li>
                        <li>When the blood is needed (Normal or Emergency)</li>
                        <li>A medical document from the doctor or hospital: PDF, JPG, JPEG or PNG</li>
                    </ul>
                    <p class="text-secondary">
                        Only the admin and you can see the medical document. It is never shown to the public or to donors.
                    </p>

                    <h2>What the status of your request means</h2>
                    <table class="table align-middle">
                        <tbody>
                            <tr><td><span class="bl-status bl-status-pending">Pending</span></td><td>Sent. Waiting for an admin.</td></tr>
                            <tr><td><span class="bl-status bl-status-review">Under Review</span></td><td>The admin is checking the details and document.</td></tr>
                            <tr><td><span class="bl-status bl-status-approved">Approved</span></td><td>Accepted. The admin is looking for donors.</td></tr>
                            <tr><td><span class="bl-status bl-status-matched">Donor Matched</span></td><td>At least one donor has agreed to help.</td></tr>
                            <tr><td><span class="bl-status bl-status-completed">Completed</span></td><td>The admin confirmed that the blood was received.</td></tr>
                            <tr><td><span class="bl-status bl-status-rejected">Rejected</span></td><td>Not accepted. The admin leaves a reason.</td></tr>
                            <tr><td><span class="bl-status bl-status-cancelled">Cancelled</span></td><td>Stopped because blood is no longer needed.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="bl-auth-card">
                    <h2 class="h4">Start a request</h2>
                    <p class="text-secondary">You need a requester account so you can follow your request.</p>
                    <div class="d-grid gap-2">
                        <a class="btn btn-primary btn-lg" href="<?php echo BASE_URL; ?>/register.php?role=requester">Create a requester account</a>
                        <a class="btn btn-outline-blood" href="<?php echo BASE_URL; ?>/login.php">Login</a>
                    </div>
                </div>
            </div>

        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
