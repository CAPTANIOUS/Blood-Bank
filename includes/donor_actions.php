<?php
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) { http_response_code(403); exit; }   // never run this file on its own
/*
 * includes/donor_actions.php
 * Notifications + the donor's answers to blood requests.
 *
 * RULES (important for the viva):
 *   - A donor saying "I accept" moves the request to "Donor Matched". It NEVER completes it.
 *     Only an admin marks a request Completed, after the blood is really given.
 *   - BloodBank never decides if a donor is medically fit. We only show reminders.
 *   - Every change runs in ONE transaction and the request row is locked (FOR UPDATE),
 *     so two donors pressing "Accept" at the same moment cannot corrupt the status.
 */

/** Put one message in a user's notification list. */
function add_notification(PDO $pdo, int $userId, string $title, string $message, ?string $link = null): void
{
    $stmt = $pdo->prepare('INSERT INTO notifications (user_id, title, message, link) VALUES (?, ?, ?, ?)');
    $stmt->execute([$userId, mb_substr($title, 0, 120), mb_substr($message, 0, 255), $link]);
}

/** Tell every active admin. */
function notify_admins(PDO $pdo, string $title, string $message, ?string $link = null): void
{
    $ids = $pdo->query("SELECT user_id FROM users WHERE role = 'admin' AND is_active = 1")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($ids as $id) {
        add_notification($pdo, (int)$id, $title, $message, $link);
    }
}

/** A notification link is only used if it looks like  folder/page.php  or  folder/page.php?id=5 */
function safe_notification_link(?string $link): ?string
{
    if ($link !== null && preg_match('#^(admin|donor|requester)/[a-z_]+\.php(\?id=\d+)?$#', $link)) {
        return $link;
    }
    return null;
}

/**
 * The donor answers a request.
 *   $action:  accept | decline | volunteer | withdraw
 * Returns [true, 'message'] or [false, 'reason'].
 */
function donor_respond(PDO $pdo, int $donorId, int $requestId, string $action, string $note): array
{
    if (!in_array($action, ['accept', 'decline', 'volunteer', 'withdraw'], true)) {
        return [false, 'Unknown action.'];
    }
    $note = mb_substr(trim($note), 0, 255);
    $open = ['Approved', 'Donor Matched'];        // statuses where a donor can still help

    try {
        $pdo->beginTransaction();

        // 1. Lock the request so two donors cannot change it at the same time
        $stmt = $pdo->prepare(
            'SELECT status, requester_id, blood_group_id, district_id
             FROM blood_requests WHERE request_id = ? FOR UPDATE'
        );
        $stmt->execute([$requestId]);
        $req = $stmt->fetch();

        $stmt = $pdo->prepare('SELECT blood_group_id, district_id, is_available FROM donors WHERE user_id = ?');
        $stmt->execute([$donorId]);
        $donor = $stmt->fetch();

        $stmt = $pdo->prepare('SELECT response_id, response_status FROM donor_responses WHERE request_id = ? AND donor_id = ? FOR UPDATE');
        $stmt->execute([$requestId, $donorId]);
        $resp = $stmt->fetch();

        if (!$req || !$donor) {
            $pdo->rollBack();
            return [false, 'This request was not found.'];
        }
        if ($action !== 'decline' && $action !== 'withdraw' && !$donor['is_available']) {
            $pdo->rollBack();
            return [false, 'Your profile says you are not available. Change it in My profile first.'];
        }
        if (!in_array($req['status'], $open, true)) {
            $pdo->rollBack();
            return [false, 'This request is no longer open (it is ' . $req['status'] . ').'];
        }

        $becameMatched = false;
        $revertedToApproved = false;

        if ($action === 'volunteer') {
            if ($resp) {
                $pdo->rollBack();
                return [false, 'You already answered this request.'];
            }
            if ($req['status'] !== 'Approved'
                || (int)$req['blood_group_id'] !== (int)$donor['blood_group_id']
                || (int)$req['district_id'] !== (int)$donor['district_id']) {
                $pdo->rollBack();
                return [false, 'This request does not match your blood group and district.'];
            }
            $pdo->prepare(
                "INSERT INTO donor_responses (request_id, donor_id, response_status, assigned_by, donor_note, responded_at)
                 VALUES (?, ?, 'Accepted', NULL, ?, NOW())"
            )->execute([$requestId, $donorId, $note !== '' ? $note : null]);
            $becameMatched = true;
            $msg = 'Thank you! You volunteered for this request.';

        } elseif ($action === 'accept') {
            if (!$resp) {
                $pdo->rollBack();
                return [false, 'The admin has not asked you for this request.'];
            }
            if ($resp['response_status'] === 'Accepted') {
                $pdo->rollBack();
                return [false, 'You already accepted this request.'];
            }
            $pdo->prepare("UPDATE donor_responses SET response_status = 'Accepted', donor_note = ?, responded_at = NOW() WHERE response_id = ?")
                ->execute([$note !== '' ? $note : null, $resp['response_id']]);
            $becameMatched = true;
            $msg = 'Thank you! You accepted this request. The contact details are now shown below.';

        } elseif ($action === 'decline') {
            if (!$resp || $resp['response_status'] !== 'Pending') {
                $pdo->rollBack();
                return [false, 'There is nothing to decline.'];
            }
            $pdo->prepare("UPDATE donor_responses SET response_status = 'Declined', donor_note = ?, responded_at = NOW() WHERE response_id = ?")
                ->execute([$note !== '' ? $note : null, $resp['response_id']]);
            $msg = 'You declined this request. Thank you for answering.';

        } else { // withdraw
            if (!$resp || $resp['response_status'] !== 'Accepted') {
                $pdo->rollBack();
                return [false, 'You have not accepted this request.'];
            }
            $pdo->prepare("UPDATE donor_responses SET response_status = 'Declined', donor_note = ?, responded_at = NOW() WHERE response_id = ?")
                ->execute([$note !== '' ? $note : 'Withdrawn by the donor', $resp['response_id']]);

            // If nobody else is accepted, the request goes back to Approved (searching again)
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM donor_responses WHERE request_id = ? AND response_status = 'Accepted'");
            $stmt->execute([$requestId]);
            if ((int)$stmt->fetchColumn() === 0 && $req['status'] === 'Donor Matched') {
                $pdo->prepare("UPDATE blood_requests SET status = 'Approved' WHERE request_id = ?")->execute([$requestId]);
                $pdo->prepare(
                    "INSERT INTO request_status_history (request_id, old_status, new_status, changed_by, remark)
                     VALUES (?, 'Donor Matched', 'Approved', ?, 'The donor withdrew. Searching for another donor.')"
                )->execute([$requestId, $donorId]);
                $revertedToApproved = true;
            }
            $msg = 'You withdrew your acceptance. Thank you for telling us early.';
        }

        // First acceptance: Approved -> Donor Matched (NOT Completed)
        if ($becameMatched && $req['status'] === 'Approved') {
            $pdo->prepare("UPDATE blood_requests SET status = 'Donor Matched' WHERE request_id = ?")->execute([$requestId]);
            $pdo->prepare(
                "INSERT INTO request_status_history (request_id, old_status, new_status, changed_by, remark)
                 VALUES (?, 'Approved', 'Donor Matched', ?, ?)"
            )->execute([$requestId, $donorId, $action === 'volunteer' ? 'A donor volunteered' : 'Donor accepted']);
        }

        // Messages
        $link = 'requester/request_details.php?id=' . $requestId;
        if ($becameMatched) {
            add_notification($pdo, (int)$req['requester_id'], 'A donor is ready',
                "A donor accepted your request #$requestId. The admin will confirm the details.", $link);
            notify_admins($pdo, 'Donor accepted', "A donor accepted request #$requestId. Please follow up.", 'admin/manage_requests.php');
        } elseif ($action === 'decline') {
            notify_admins($pdo, 'Donor declined', "A donor declined request #$requestId. You may need to ask another donor.", 'admin/manage_requests.php');
        } else {
            notify_admins($pdo, 'Donor withdrew', "A donor withdrew from request #$requestId.", 'admin/manage_requests.php');
            if ($revertedToApproved) {
                add_notification($pdo, (int)$req['requester_id'], 'Searching again',
                    "The donor for request #$requestId can no longer come. We are looking for another donor.", $link);
            }
        }

        $pdo->commit();
        return [true, $msg];

    } catch (PDOException $ex) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        return [false, 'Something went wrong. Please try again.'];
    }
}
