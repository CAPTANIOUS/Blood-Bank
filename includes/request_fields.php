<?php
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) { http_response_code(403); exit; }   // never run this file on its own
/*
 * includes/request_fields.php
 * Everything about blood REQUESTS that more than one page needs:
 *   - validate_request_input()   checks the typed form fields
 *   - validate_documents()       checks the uploaded files (type, size, real content)
 *   - create_blood_request()     saves the request + history + files in ONE transaction
 *   - update_blood_request()     edit (only while Pending)
 *   - cancel_blood_request()     cancel + history + tell donors
 *   - add_documents()            upload more files to an existing request
 * Used by the requester pages today and by the admin's "add a request" page later.
 */
require_once __DIR__ . '/functions.php';

/* ------------------------------------------------------------------
 * 1. The typed fields
 * Returns [ cleaned values, list of error messages ]
 * ------------------------------------------------------------------ */
function validate_request_input(array $in, array $bloodGroups, array $districts): array
{
    $errors = [];
    $today  = date('Y-m-d');

    $data = [
        'patient_name'      => clean($in['patient_name'] ?? ''),
        'requester_name'    => clean($in['requester_name'] ?? ''),
        'contact_phone'     => normalize_phone(clean($in['contact_phone'] ?? '')),
        'blood_group_id'    => (int)($in['blood_group_id'] ?? 0),
        'bags_required'     => (int)($in['bags_required'] ?? 0),
        'medical_condition' => clean($in['medical_condition'] ?? ''),
        'hospital_name'     => clean($in['hospital_name'] ?? ''),
        'hospital_address'  => clean($in['hospital_address'] ?? ''),
        'district_id'       => (int)($in['district_id'] ?? 0),
        'area'              => clean($in['area'] ?? ''),
        'urgency'           => clean($in['urgency'] ?? ''),
        'required_date'     => clean($in['required_date'] ?? ''),
        'required_time'     => clean($in['required_time'] ?? ''),
        'additional_info'   => clean($in['additional_info'] ?? ''),
    ];

    if (mb_strlen($data['patient_name']) < 2 || mb_strlen($data['patient_name']) > 100) {
        $errors[] = 'Enter the patient name (2 to 100 characters).';
    }
    if (mb_strlen($data['requester_name']) < 2 || mb_strlen($data['requester_name']) > 100) {
        $errors[] = 'Enter the name of the person we should contact (2 to 100 characters).';
    }
    if (!preg_match('/^\+?[0-9]{7,15}$/', $data['contact_phone'])) {
        $errors[] = 'Enter a valid contact number (digits only, 7 to 15 numbers).';
    }
    if (!in_array($data['blood_group_id'], array_column($bloodGroups, 'blood_group_id'))) {
        $errors[] = 'Choose the blood group that is needed.';
    }
    if ($data['bags_required'] < 1 || $data['bags_required'] > 20) {
        $errors[] = 'The number of blood bags must be between 1 and 20.';
    }
    if (mb_strlen($data['medical_condition']) < 3 || mb_strlen($data['medical_condition']) > 255) {
        $errors[] = 'Describe the medical condition (3 to 255 characters).';
    }
    if (mb_strlen($data['hospital_name']) < 2 || mb_strlen($data['hospital_name']) > 150) {
        $errors[] = 'Enter the hospital name (2 to 150 characters).';
    }
    if (mb_strlen($data['hospital_address']) < 5 || mb_strlen($data['hospital_address']) > 255) {
        $errors[] = 'Enter the hospital address (5 to 255 characters).';
    }
    if (!in_array($data['district_id'], array_column($districts, 'district_id'))) {
        $errors[] = 'Choose the hospital district.';
    }
    if (mb_strlen($data['area']) < 2 || mb_strlen($data['area']) > 100) {
        $errors[] = 'Enter the hospital area (2 to 100 characters).';
    }
    if (mb_strlen($data['additional_info']) > 1000) {
        $errors[] = 'Additional information: please use 1000 characters or fewer.';
    }

    // ---- urgency decides what we ask about the date ----
    if (!in_array($data['urgency'], ['Normal', 'Emergency'], true)) {
        $errors[] = 'Choose Normal or Emergency.';
    } elseif ($data['urgency'] === 'Emergency') {
        $data['required_date'] = $today;        // needed now: date = today, no time
        $data['required_time'] = '';
    } else {                                     // Normal: the person tells us when
        $date = $data['required_date'];
        $time = $data['required_time'];

        if (!is_valid_date($date)) {
            $errors[] = 'Choose the date when the blood is needed.';
        } elseif ($date < $today) {
            $errors[] = 'The required date cannot be in the past.';
        } elseif ($date > date('Y-m-d', strtotime('+120 days'))) {
            $errors[] = 'The required date must be within the next 120 days.';
        }

        if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time)) {
            $errors[] = 'Choose the time when the blood is needed.';
        } elseif (is_valid_date($date) && $date === $today && $time <= date('H:i')) {
            $errors[] = 'For today, choose a time later than now (or choose Emergency).';
        }
    }

    return [$data, $errors];
}

/* ------------------------------------------------------------------
 * 2. The uploaded files
 * ------------------------------------------------------------------ */

/* PHP gives $_FILES in a strange shape. This turns it into a simple list of files
 * and skips empty slots (no file chosen).                                       */
function collect_uploaded_files(string $field = 'documents'): array
{
    $files = [];
    if (empty($_FILES[$field]) || !is_array($_FILES[$field]['name'])) {
        return $files;
    }
    foreach ($_FILES[$field]['name'] as $i => $name) {
        if ($_FILES[$field]['error'][$i] === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        $files[] = [
            'name'  => (string)$name,
            'tmp'   => (string)$_FILES[$field]['tmp_name'][$i],
            'size'  => (int)$_FILES[$field]['size'][$i],
            'error' => (int)$_FILES[$field]['error'][$i],
        ];
    }
    return $files;
}

/* A safe version of the file name that we may show on the page */
function safe_original_name(string $name, string $ext): string
{
    $name = basename(str_replace('\\', '/', $name));
    $name = preg_replace('/[^\p{L}\p{N}\s._()\-]/u', '', $name);
    $name = trim(preg_replace('/\s+/u', ' ', $name));
    if (mb_strlen($name) > 150) {
        $name = mb_substr($name, 0, 150);
    }
    return ($name === '' || $name === '.' || $name === '..') ? 'document.' . $ext : $name;
}

/*
 * $files        = list from collect_uploaded_files()
 * $alreadyHave  = documents the request has already
 * $required     = must at least one file be sent?
 * Returns [ list of accepted files, list of error messages ]
 */
function validate_documents(array $files, int $alreadyHave, bool $required): array
{
    $errors   = [];
    $accepted = [];

    if (!$files) {
        if ($required) {
            $errors[] = 'Upload at least one medical document (PDF, JPG, JPEG or PNG).';
        }
        return [[], $errors];
    }
    if (!function_exists('finfo_open')) {
        return [[], ['The server cannot check uploaded files (the PHP "fileinfo" extension is off).']];
    }
    if ($alreadyHave + count($files) > MAX_DOCS_PER_REQUEST) {
        return [[], ['A request can have at most ' . MAX_DOCS_PER_REQUEST . ' documents.']];
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    // real content type  =>  extensions we accept for it
    $types = [
        'application/pdf' => ['pdf' => 'pdf'],
        'image/jpeg'      => ['jpg' => 'jpg', 'jpeg' => 'jpg'],
        'image/png'       => ['png' => 'png'],
    ];

    foreach ($files as $file) {
        $label = '"' . safe_original_name($file['name'], 'file') . '"';

        if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
            $errors[] = "$label is too large (the limit is 5 MB per file).";
            continue;
        }
        if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp'])) {
            $errors[] = "$label could not be uploaded. Please try again.";
            continue;
        }
        if ($file['size'] < 1) {
            $errors[] = "$label is empty.";
            continue;
        }
        if ($file['size'] > MAX_UPLOAD_BYTES) {
            $errors[] = "$label is too large (the limit is 5 MB per file).";
            continue;
        }

        // 1) the extension the person typed
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        // 2) what is REALLY inside the file (PHP reads the first bytes, not the name)
        $mime = (string)finfo_file($finfo, $file['tmp']);

        if (!isset($types[$mime]) || !isset($types[$mime][$ext])) {
            $errors[] = "$label is not allowed. Only PDF, JPG, JPEG and PNG files are accepted.";
            continue;
        }
        // a picture must really be a picture
        if ($mime !== 'application/pdf' && @getimagesize($file['tmp']) === false) {
            $errors[] = "$label is not a valid image.";
            continue;
        }

        $storedExt  = $types[$mime][$ext];
        $accepted[] = [
            'tmp'      => $file['tmp'],
            'original' => safe_original_name($file['name'], $storedExt),
            'ext'      => $storedExt,
            'size'     => $file['size'],
            'stored'   => bin2hex(random_bytes(16)) . '.' . $storedExt,   // random: cannot be guessed
        ];
    }
    finfo_close($finfo);

    return [$errors ? [] : $accepted, $errors];
}

/* Move the files into uploads/ and write one row per file.
 * Returns the paths it created, so the caller can delete them if something fails later. */
function store_documents(PDO $pdo, int $requestId, array $docs): array
{
    if (!is_dir(UPLOAD_DIR) && !mkdir(UPLOAD_DIR, 0755, true)) {
        throw new RuntimeException('Cannot create the uploads folder.');
    }
    $moved = [];
    $stmt  = $pdo->prepare(
        'INSERT INTO medical_documents (request_id, original_name, stored_name, file_ext, file_size)
         VALUES (?, ?, ?, ?, ?)'
    );
    foreach ($docs as $doc) {
        $path = UPLOAD_DIR . $doc['stored'];
        if (!move_uploaded_file($doc['tmp'], $path)) {
            foreach ($moved as $m) { @unlink($m); }
            throw new RuntimeException('Cannot save an uploaded file.');
        }
        @chmod($path, 0644);
        $moved[] = $path;
        $stmt->execute([$requestId, $doc['original'], $doc['stored'], $doc['ext'], $doc['size']]);
    }
    return $moved;
}

/* ------------------------------------------------------------------
 * 3. Saving
 * ------------------------------------------------------------------ */

/* A new request. Status starts as Pending. Returns the new request id.
 * $changedBy = who pressed the button (the requester, or an admin).        */
function create_blood_request(PDO $pdo, int $requesterId, array $data, array $docs, int $changedBy, string $remark = 'Request submitted'): int
{
    $moved = [];
    try {
        $pdo->beginTransaction();       // all or nothing

        $stmt = $pdo->prepare(
            'INSERT INTO blood_requests
                (requester_id, patient_name, requester_name, contact_phone, blood_group_id, bags_required,
                 medical_condition, hospital_name, hospital_address, district_id, area,
                 urgency, required_date, required_time, additional_info)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $requesterId, $data['patient_name'], $data['requester_name'], $data['contact_phone'],
            $data['blood_group_id'], $data['bags_required'], $data['medical_condition'],
            $data['hospital_name'], $data['hospital_address'], $data['district_id'], $data['area'],
            $data['urgency'], $data['required_date'],
            ($data['required_time'] !== '' ? $data['required_time'] : null),
            ($data['additional_info'] !== '' ? $data['additional_info'] : null),
        ]);
        $requestId = (int)$pdo->lastInsertId();

        $stmt = $pdo->prepare(
            'INSERT INTO request_status_history (request_id, old_status, new_status, changed_by, remark)
             VALUES (?, NULL, ?, ?, ?)'
        );
        $stmt->execute([$requestId, 'Pending', $changedBy, $remark]);

        $moved = store_documents($pdo, $requestId, $docs);

        $pdo->commit();
        return $requestId;

    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        foreach ($moved as $path) { @unlink($path); }     // no orphan files
        throw $ex;
    }
}

/* Edit a request. Allowed only while it is still Pending.
 * Returns false when the request is not Pending any more (or is not yours). */
function update_blood_request(PDO $pdo, int $requestId, int $requesterId, array $data): bool
{
    $pdo->beginTransaction();
    // FOR UPDATE locks the row, so an admin cannot approve it at the same moment
    $stmt = $pdo->prepare('SELECT status FROM blood_requests WHERE request_id = ? AND requester_id = ? FOR UPDATE');
    $stmt->execute([$requestId, $requesterId]);
    $status = $stmt->fetchColumn();

    if ($status !== 'Pending') {
        $pdo->rollBack();
        return false;
    }

    $stmt = $pdo->prepare(
        'UPDATE blood_requests
         SET patient_name = ?, requester_name = ?, contact_phone = ?, blood_group_id = ?, bags_required = ?,
             medical_condition = ?, hospital_name = ?, hospital_address = ?, district_id = ?, area = ?,
             urgency = ?, required_date = ?, required_time = ?, additional_info = ?
         WHERE request_id = ?'
    );
    $stmt->execute([
        $data['patient_name'], $data['requester_name'], $data['contact_phone'], $data['blood_group_id'],
        $data['bags_required'], $data['medical_condition'], $data['hospital_name'], $data['hospital_address'],
        $data['district_id'], $data['area'], $data['urgency'], $data['required_date'],
        ($data['required_time'] !== '' ? $data['required_time'] : null),
        ($data['additional_info'] !== '' ? $data['additional_info'] : null),
        $requestId,
    ]);
    $pdo->commit();
    return true;
}

/* Cancel a request (the requester says "I do not need it any more").
 * Donors who were asked or had accepted get a notification.
 * Returns false when the request can no longer be cancelled.               */
function cancel_blood_request(PDO $pdo, int $requestId, int $requesterId, string $reason): bool
{
    $pdo->beginTransaction();
    $stmt = $pdo->prepare(
        'SELECT r.status, bg.group_name
         FROM blood_requests r JOIN blood_groups bg ON bg.blood_group_id = r.blood_group_id
         WHERE r.request_id = ? AND r.requester_id = ? FOR UPDATE'
    );
    $stmt->execute([$requestId, $requesterId]);
    $row = $stmt->fetch();

    if (!$row || !in_array($row['status'], ['Pending', 'Under Review', 'Approved', 'Donor Matched'], true)) {
        $pdo->rollBack();
        return false;
    }

    $pdo->prepare("UPDATE blood_requests SET status = 'Cancelled' WHERE request_id = ?")->execute([$requestId]);

    $remark = 'Cancelled by requester' . ($reason !== '' ? ': ' . $reason : '');
    $pdo->prepare(
        'INSERT INTO request_status_history (request_id, old_status, new_status, changed_by, remark)
         VALUES (?, ?, ?, ?, ?)'
    )->execute([$requestId, $row['status'], 'Cancelled', $requesterId, mb_substr($remark, 0, 255)]);

    // tell the donors who were waiting or had said yes
    $stmt = $pdo->prepare("SELECT donor_id FROM donor_responses WHERE request_id = ? AND response_status IN ('Pending', 'Accepted')");
    $stmt->execute([$requestId]);
    $notify = $pdo->prepare('INSERT INTO notifications (user_id, title, message, link) VALUES (?, ?, ?, ?)');
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $donorId) {
        $notify->execute([
            $donorId, 'Request cancelled',
            'The ' . $row['group_name'] . ' request #' . $requestId . ' was cancelled by the requester. Thank you for being ready to help.',
            'donor/requests.php',
        ]);
    }

    $pdo->commit();
    return true;
}

/* Add more documents to a request that is still being checked */
function add_documents(PDO $pdo, int $requestId, array $docs): void
{
    $moved = [];
    try {
        $pdo->beginTransaction();
        $moved = store_documents($pdo, $requestId, $docs);
        $pdo->commit();
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        foreach ($moved as $path) { @unlink($path); }
        throw $ex;
    }
}
