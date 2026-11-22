<?php
/*
 * document.php  -  The ONLY way to open a medical document.
 *
 * The files in uploads/ are locked (see uploads/.htaccess), so a browser
 * cannot open them by address. This page checks WHO is asking:
 *     admin               -> may open any document
 *     requester           -> only documents of THEIR OWN requests
 *     donor / visitor     -> never
 * and then sends the file.
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

require_login();                        // a visitor is sent to the login page
$me = current_user();

function not_found(): void
{
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Document not found.';
    exit;
}

$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare(
    'SELECT d.original_name, d.stored_name, d.file_ext, r.requester_id, d.request_id
     FROM medical_documents d
     JOIN blood_requests r ON r.request_id = d.request_id
     WHERE d.document_id = ?'
);
$stmt->execute([$id]);
$doc = $stmt->fetch();

if (!$doc) {
    not_found();
}

$allowed = ($me['role'] === 'admin')
        || ($me['role'] === 'requester' && (int)$doc['requester_id'] === $me['id']);

if (!$allowed) {
    not_found();                        // same answer as "does not exist": reveals nothing
}

// basename() = the stored name can never point to another folder
$path = UPLOAD_DIR . basename($doc['stored_name']);
if (!is_file($path)) {
    not_found();
}

$mimeByExt = ['pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png'];
$mime = $mimeByExt[$doc['file_ext']] ?? null;
if ($mime === null) {
    not_found();
}

if ($me['role'] === 'admin') {          // an audit trail: who looked at private medical papers
    log_admin_action($pdo, $me['id'], 'Viewed medical document', 'medical_documents', $id,
                     'Request #' . $doc['request_id']);
}

// A safe download name (letters, digits, dot, dash, underscore only)
$niceName = preg_replace('/[^A-Za-z0-9._-]+/', '_', $doc['original_name']);
if ($niceName === '' || $niceName[0] === '.') {
    $niceName = 'document.' . $doc['file_ext'];
}

while (ob_get_level() > 0) { ob_end_clean(); }
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
header('Content-Disposition: inline; filename="' . $niceName . '"');
header('X-Content-Type-Options: nosniff');          // the browser must trust our Content-Type
header('Cache-Control: private, no-store');         // do not keep medical files in shared caches
readfile($path);
exit;
