<?php
/*
 * admin/export_donors.php  -  Admin: download the donor list as a CSV file (opens in Excel).
 * It uses the SAME filters as admin/manage_donors.php, so "what you see is what you download".
 * Every download is written to admin_logs, because the file contains phone numbers and emails.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('admin');

/* A cell that starts with = + - @ can run as a formula in Excel ("CSV injection").
 * A leading ' makes Excel treat it as plain text. (Phone numbers may start with +.) */
function csv_safe(string $value, bool $isPhone = false): string
{
    $danger = $isPhone ? '=@' : '=+-@';
    if ($value !== '' && (strpbrk($value[0], $danger) !== false || $value[0] === "\t" || $value[0] === "\r")) {
        return "'" . $value;
    }
    return $value;
}

/* ---------- Filters (same names and rules as manage_donors.php) ---------- */
$q          = substr(clean($_GET['q'] ?? ''), 0, 100);
$groupId    = (int)($_GET['group'] ?? 0);
$districtId = (int)($_GET['district'] ?? 0);
$area       = substr(clean($_GET['area'] ?? ''), 0, 100);
$avail      = in_array($_GET['avail'] ?? '', ['yes', 'no'], true) ? $_GET['avail'] : '';
$status     = in_array($_GET['status'] ?? '', ['active', 'deactivated'], true) ? $_GET['status'] : '';

$where  = ['1 = 1'];
$params = [];
if ($q !== '') {
    $like    = '%' . addcslashes($q, '%_\\') . '%';
    $where[] = '(u.full_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)';
    array_push($params, $like, $like, $like);
}
if ($groupId > 0)    { $where[] = 'd.blood_group_id = ?'; $params[] = $groupId; }
if ($districtId > 0) { $where[] = 'd.district_id = ?';    $params[] = $districtId; }
if ($area !== '')    { $where[] = 'd.area LIKE ?';        $params[] = '%' . addcslashes($area, '%_\\') . '%'; }
if ($avail === 'yes')          { $where[] = 'd.is_available = 1'; }
if ($avail === 'no')           { $where[] = 'd.is_available = 0'; }
if ($status === 'active')      { $where[] = 'u.is_active = 1'; }
if ($status === 'deactivated') { $where[] = 'u.is_active = 0'; }

$stmt = $pdo->prepare(
    'SELECT u.user_id, u.full_name, u.email, u.phone, bg.group_name, di.district_name, d.area,
            d.last_donation_date, d.is_available, u.is_active
     FROM donors d
     JOIN users u         ON u.user_id         = d.user_id
     JOIN blood_groups bg ON bg.blood_group_id = d.blood_group_id
     JOIN districts di    ON di.district_id    = d.district_id
     WHERE ' . implode(' AND ', $where) . '
     ORDER BY u.full_name'
);
$stmt->execute($params);
$rows = $stmt->fetchAll();

log_admin_action($pdo, (int)$_SESSION['user_id'], 'Exported donors (CSV)', 'donors', null, count($rows) . ' donor(s)');

/* ---------- Send the file ---------- */
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="bloodbank_donors_' . date('Y-m-d') . '.csv"');
header('Cache-Control: no-store');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF");            // BOM: Excel then reads Bangla / special letters correctly
fputcsv($out, ['ID', 'Name', 'Email', 'Phone', 'Blood group', 'District', 'Area', 'Last donation', 'Available', 'Account'], ',', '"', '');
foreach ($rows as $r) {
    fputcsv($out, [
        $r['user_id'],
        csv_safe($r['full_name']),
        csv_safe($r['email']),
        csv_safe($r['phone'], true),
        $r['group_name'],
        $r['district_name'],
        csv_safe($r['area']),
        $r['last_donation_date'] ?? '',
        $r['is_available'] ? 'Yes' : 'No',
        $r['is_active'] ? 'Active' : 'Deactivated',
    ], ',', '"', '');
}
fclose($out);
exit;
