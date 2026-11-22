<?php
/*
 * admin/donor_status.php  -  Activate or deactivate a donor account.
 * No page of its own: it only handles a POST form, does the change,
 * writes an entry in admin_logs and sends the admin back.
 * We DEACTIVATE (is_active = 0) instead of deleting, so request and
 * donation history is never lost.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/admin/manage_donors.php');
}

$id   = (int)($_POST['id'] ?? 0);
$make = $_POST['make'] ?? '';
$back = ($_POST['back'] ?? '') === 'view' ? 'view' : 'list';

// Where to go afterwards. Only OUR two pages are possible (no open redirect).
if ($back === 'view') {
    $goBack = '/admin/donor_view.php?id=' . $id;
} else {
    $qs     = str_replace(["\r", "\n"], '', substr((string)($_POST['qs'] ?? ''), 0, 300));
    $goBack = '/admin/manage_donors.php' . ($qs !== '' ? '?' . $qs : '');
}

if (!verify_csrf($_POST['csrf_token'] ?? '')) {
    set_flash('danger', 'Your session expired. Please try again.');
    redirect($goBack);
}
if (!in_array($make, ['activate', 'deactivate'], true)) {
    set_flash('danger', 'Unknown action.');
    redirect($goBack);
}

// The target must really be a donor (an admin cannot switch off another admin here)
$stmt = $pdo->prepare("SELECT full_name, is_active FROM users WHERE user_id = ? AND role = 'donor'");
$stmt->execute([$id]);
$donor = $stmt->fetch();

if (!$donor) {
    set_flash('danger', 'Donor not found.');
    redirect('/admin/manage_donors.php');
}

$newValue = ($make === 'activate') ? 1 : 0;

if ((int)$donor['is_active'] === $newValue) {
    set_flash('info', 'Nothing changed: the account was already ' . ($newValue ? 'active.' : 'deactivated.'));
    redirect($goBack);
}

$stmt = $pdo->prepare('UPDATE users SET is_active = ? WHERE user_id = ?');
$stmt->execute([$newValue, $id]);

log_admin_action(
    $pdo, current_user()['id'],
    $newValue ? 'Reactivated donor' : 'Deactivated donor',
    'users', $id, $donor['full_name']
);

set_flash('success', $donor['full_name'] . ($newValue ? ' was reactivated.' : ' was deactivated.'));
redirect($goBack);
