<?php
/*
 * logout.php  -  Ends the session.
 * Logout is a POST form (with the CSRF token), so another website cannot
 * log you out just by showing you an image or a link.
 */
require_once __DIR__ . '/includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST['csrf_token'] ?? '')) {
    logout_user();
    session_start();                                   // fresh, empty session...
    session_regenerate_id(true);                       // ...with a brand-new id and cookie...
    set_flash('success', 'You have been logged out.'); // ...just to show this message
}

redirect('/login.php');
