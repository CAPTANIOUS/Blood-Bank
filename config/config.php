<?php
/*
 * config/config.php
 * Settings used by every page. If you ever rename the project folder,
 * change BASE_URL here and nothing else.
 */

define('APP_NAME', 'BloodBank');

// The web address of the project folder inside htdocs.
// http://localhost/BloodBank  ->  '/BloodBank'
define('BASE_URL', '/BloodBank');

// ---- Medical document uploads ----
// Files are saved here with a random name. A .htaccess file in the uploads
// folder stops the browser from opening them directly: they can only be
// viewed through document.php, which checks who is asking.
define('UPLOAD_DIR', __DIR__ . '/../uploads/medical_documents/');
define('MAX_UPLOAD_BYTES', 5 * 1024 * 1024);   // 5 MB per file
define('MAX_DOCS_PER_REQUEST', 3);             // files allowed for one request

// A logged-in person who does nothing for this long is logged out (in seconds).
define('SESSION_IDLE_SECONDS', 30 * 60);       // 30 minutes

// All dates and times use Bangladesh time.
date_default_timezone_set('Asia/Dhaka');

// Debug switch.  true  = show PHP errors on the page (good while building and testing)
//                false = hide them (use this for a real website). Errors go to the PHP log instead.
define('APP_DEBUG', true);
ini_set('display_errors', APP_DEBUG ? '1' : '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

// Small safety headers sent with every page.
if (!headers_sent()) {
    header('X-Frame-Options: SAMEORIGIN');          // no other site can show BloodBank inside a frame
    header('X-Content-Type-Options: nosniff');      // the browser must not guess file types
    header('Referrer-Policy: same-origin');         // do not tell other sites which page you came from
}
