<?php
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) { http_response_code(403); exit; }   // never run this file on its own
/*
 * includes/admin_requests.php
 * What an ADMIN can do with a blood request.
 *
 * The request workflow (only these steps are allowed):
 *
 *   Pending --> Under Review --> Approved --> Donor Matched --> Completed
 *      |             |             |              |
 *      +-> Rejected  +-> Rejected   +-> Cancelled  +-> Cancelled
 *   (an Emergency request may go Pending --> Approved directly)
 *
 *   - "Donor Matched" is set automatically when a donor accepts (see donor_actions.php).
 *   - "Completed" is set ONLY here, by an admin, after the blood was really given,
 *     and it records the donation(s). A donor saying "I accept" never completes a request.
 *   - Every change: ONE transaction, the request row locked (FOR UPDATE), a row in
 *     request_status_history, a message to the requester, and an entry in admin_logs.
 */
require_once __DIR__ . '/donor_actions.php';

/** Which status may follow which (Completed has its own function below). */
function allowed_next_statuses(string $status, string $urgency): array
{
    switch ($status) {
        case 'Pending':       return $urgency === 'Emergency' ? ['Under Review', 'Approved', 'Rejected'] : ['Under Review', 'Rejected'];
        case 'Under Review':  return ['Approved', 'Rejected'];
        case 'Approved':
        case 'Donor Matched': return ['Cancelled'];
        default:              return [];
    }
}

/** Change the status of a request. Returns [bool ok, string message]. */
function admin_change_status(PDO $pdo, int $requestId, int $adminId, string $to, string $remark): array
{
    $remark = mb_substr(trim($remark), 0, 200);
    if (in_array($to, ['Rejected', 'Cancelled'], true) && mb_strlen($remark) < 3) {
        return [false, 'Please write the reason (at least 3 letters). The requester will read it.'];
    }

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('SELECT status, urgency, requester_id FROM blood_requests WHERE request_id = ? FOR UPDATE');
        $stmt->execute([$requestId]);
        $req = $stmt->fetch();
        if (!$req) {
            $pdo->rollBack();
            return [false, 'Request not found.'];
        }
        if (!in_array($to, allowed_next_statuses($req['status'], $req['urgency']), true)) {
            $pdo->rollBack();
            return [false, 'A request that is ' . $req['status'] . ' cannot be changed to ' . $to . '.'];
        }

        $reviewing = in_array($to, ['Approved', 'Rejected'], true);
        $pdo->prepare(
            'UPDATE blood_requests
             SET status = ?, admin_remark = ?,
                 reviewed_by = IF(?, ?, reviewed_by), reviewed_at = IF(?, NOW(), reviewed_at)
             WHERE request_id = ?'
        )->execute([$to, $remark !== '' ? $remark : null, (int)$reviewing, $adminId, (int)$reviewing, $requestId]);

        $pdo->prepare(
            'INSERT INTO request_status_history (request_id, old_status, new_status, changed_by, remark) VALUES (?, ?, ?, ?, ?)'
        )->execute([$requestId, $req['status'], $to, $adminId, $remark !== '' ? $remark : null]);

        $texts = [
            'Under Review' => ['Review started',   "An admin started to review your request #$requestId."],
            'Approved'     => ['Request approved', "Your request #$requestId was approved. We are now looking for donors."],
            'Rejected'     => ['Request rejected', "Your request #$requestId was rejected. Reason: $remark"],
            'Cancelled'    => ['Request cancelled', "Your request #$requestId was cancelled by an admin. Reason: $remark"],
        ];
        add_notification($pdo, (int)$req['requester_id'], $texts[$to][0], $texts[$to][1],
                         'requester/request_details.php?id=' . $requestId);

        if ($to === 'Cancelled') {                      // donors who were asked / accepted must know
            $stmt = $pdo->prepare("SELECT donor_id FROM donor_responses WHERE request_id = ? AND response_status IN ('Pending','Accepted')");
            $stmt->execute([$requestId]);
            foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $donorId) {
                add_notification($pdo, (int)$donorId, 'Request cancelled',
                    "Request #$requestId was cancelled. Thank you for being ready to help.", 'donor/requests.php');
            }
        }

        log_admin_action($pdo, $adminId, 'Changed request status', 'blood_requests', $requestId,
                         $req['status'] . ' -> ' . $to);
        $pdo->commit();
        return [true, "Request #$requestId is now $to."];

    } catch (PDOException $ex) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        return [false, 'Something went wrong. Nothing was changed.'];
    }
}

/** Ask a donor to help with a request. */
function admin_assign_donor(PDO $pdo, int $requestId, int $donorId, int $adminId): array
{
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('SELECT status, blood_group_id FROM blood_requests WHERE request_id = ? FOR UPDATE');
        $stmt->execute([$requestId]);
        $req = $stmt->fetch();
        if (!$req || !in_array($req['status'], ['Approved', 'Donor Matched'], true)) {
            $pdo->rollBack();
            return [false, 'Donors can only be asked while the request is Approved or Donor Matched.'];
        }

        $stmt = $pdo->prepare(
            "SELECT u.full_name, d.blood_group_id, d.is_available
             FROM donors d JOIN users u ON u.user_id = d.user_id
             WHERE d.user_id = ? AND u.is_active = 1 AND u.role = 'donor'"
        );
        $stmt->execute([$donorId]);
        $donor = $stmt->fetch();
        if (!$donor) {
            $pdo->rollBack();
            return [false, 'This donor was not found or the account is deactivated.'];
        }
        if ((int)$donor['blood_group_id'] !== (int)$req['blood_group_id']) {
            $pdo->rollBack();
            return [false, 'The donor\'s blood group does not match the request.'];
        }
        if (!$donor['is_available']) {
            $pdo->rollBack();
            return [false, $donor['full_name'] . ' is marked as not available.'];
        }

        $stmt = $pdo->prepare('SELECT 1 FROM donor_responses WHERE request_id = ? AND donor_id = ?');
        $stmt->execute([$requestId, $donorId]);
        if ($stmt->fetchColumn()) {
            $pdo->rollBack();
            return [false, $donor['full_name'] . ' was already asked for this request.'];
        }

        $pdo->prepare("INSERT INTO donor_responses (request_id, donor_id, response_status, assigned_by) VALUES (?, ?, 'Pending', ?)")
            ->execute([$requestId, $donorId, $adminId]);
        add_notification($pdo, $donorId, 'New donation request',
            "A patient needs blood (request #$requestId). Please open the request and answer.", 'donor/requests.php');
        log_admin_action($pdo, $adminId, 'Matched donor', 'donor_responses', $requestId,
                         'Donor #' . $donorId . ' asked for request #' . $requestId);
        $pdo->commit();
        return [true, $donor['full_name'] . ' was asked to help.'];

    } catch (PDOException $ex) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        return [false, ((int)($ex->errorInfo[1] ?? 0) === 1062) ? 'This donor was already asked.' : 'Something went wrong. Nothing was changed.'];
    }
}

/** Take back a question that the donor has not answered yet. */
function admin_remove_pending_ask(PDO $pdo, int $requestId, int $donorId, int $adminId): array
{
    $stmt = $pdo->prepare("DELETE FROM donor_responses WHERE request_id = ? AND donor_id = ? AND response_status = 'Pending'");
    $stmt->execute([$requestId, $donorId]);
    if ($stmt->rowCount() === 0) {
        return [false, 'Only a question that is still waiting can be taken back.'];
    }
    log_admin_action($pdo, $adminId, 'Removed donor match', 'donor_responses', $requestId, 'Donor #' . $donorId);
    return [true, 'The question was taken back.'];
}

/**
 * The blood was received: record the donation(s) and mark the request Completed.
 * $bags = [ donor_id => number_of_bags ]  (only donors who ACCEPTED can be recorded)
 */
function admin_complete_request(PDO $pdo, int $requestId, int $adminId, array $bags, string $date, string $remark): array
{
    $remark = mb_substr(trim($remark), 0, 200);
    if (!is_valid_date($date) || $date > date('Y-m-d')) {
        return [false, 'Choose the donation date (it cannot be in the future).'];
    }
    $bags = array_filter($bags, fn($b) => $b !== '' && $b !== null && $b !== '0');
    if (!$bags) {
        return [false, 'Tick at least one donor who really donated. A request is completed only when blood was given.'];
    }

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('SELECT status, requester_id, DATE(created_at) AS created FROM blood_requests WHERE request_id = ? FOR UPDATE');
        $stmt->execute([$requestId]);
        $req = $stmt->fetch();
        if (!$req || $req['status'] !== 'Donor Matched') {
            $pdo->rollBack();
            return [false, 'Only a request that is Donor Matched can be completed.'];
        }
        if ($date < $req['created']) {
            $pdo->rollBack();
            return [false, 'The donation date cannot be before the request was made.'];
        }

        $stmt = $pdo->prepare("SELECT donor_id FROM donor_responses WHERE request_id = ? AND response_status = 'Accepted'");
        $stmt->execute([$requestId]);
        $accepted = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

        $insert = $pdo->prepare(
            'INSERT INTO donations (request_id, donor_id, bags_donated, donation_date, recorded_by, notes) VALUES (?, ?, ?, ?, ?, ?)'
        );
        $total = 0;
        foreach ($bags as $donorId => $count) {
            $donorId = (int)$donorId; $count = (int)$count;
            if (!in_array($donorId, $accepted, true) || $count < 1 || $count > 5) {
                $pdo->rollBack();
                return [false, 'Each donor must have accepted this request, and bags must be between 1 and 5.'];
            }
            $insert->execute([$requestId, $donorId, $count, $date, $adminId, 'Donated for request #' . $requestId]);
            $total += $count;
            add_notification($pdo, $donorId, 'Thank you!', "Your donation for request #$requestId was recorded. Thank you for saving a life.", 'donor/donation_history.php');
        }

        $pdo->prepare("UPDATE blood_requests SET status = 'Completed', completed_at = NOW() WHERE request_id = ?")->execute([$requestId]);
        $pdo->prepare(
            "INSERT INTO request_status_history (request_id, old_status, new_status, changed_by, remark) VALUES (?, 'Donor Matched', 'Completed', ?, ?)"
        )->execute([$requestId, $adminId, $remark !== '' ? $remark : "Blood received. $total bag(s) recorded."]);
        add_notification($pdo, (int)$req['requester_id'], 'Request completed',
            "Your request #$requestId was marked as completed. We hope the patient gets well soon.", 'requester/request_details.php?id=' . $requestId);
        log_admin_action($pdo, $adminId, 'Completed request', 'blood_requests', $requestId, "$total bag(s) from " . count($bags) . ' donor(s)');

        $pdo->commit();
        return [true, "Request #$requestId is completed. $total bag(s) were recorded."];

    } catch (PDOException $ex) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        return [false, 'Something went wrong. Nothing was changed.'];
    }
}
