<?php
/*
 * admin/manage_donors.php  -  Admin: search and manage all donors.
 *
 * Filters: text (name / email / phone), blood group, district, area,
 *          availability and account status. 15 donors per page.
 * The medical note is only FLAGGED here. Nobody is marked "eligible" or "not eligible".
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('admin');            // guard first

$dashRole   = 'admin';
$pageTitle  = 'Donors';

$bloodGroups = $pdo->query('SELECT blood_group_id, group_name FROM blood_groups ORDER BY blood_group_id')->fetchAll();
$districts   = $pdo->query('SELECT district_id, district_name FROM districts ORDER BY district_name')->fetchAll();

/* ---------- 1. Read the filters ---------- */
$q          = substr(clean($_GET['q'] ?? ''), 0, 100);
$groupId    = (int)($_GET['group'] ?? 0);
$districtId = (int)($_GET['district'] ?? 0);
$area       = substr(clean($_GET['area'] ?? ''), 0, 100);
$avail      = in_array($_GET['avail'] ?? '', ['yes', 'no'], true) ? $_GET['avail'] : '';
$status     = in_array($_GET['status'] ?? '', ['active', 'deactivated'], true) ? $_GET['status'] : '';

if (!in_array($groupId, array_column($bloodGroups, 'blood_group_id')))   { $groupId = 0; }
if (!in_array($districtId, array_column($districts, 'district_id')))     { $districtId = 0; }

/* ---------- 2. Build the WHERE part (values always go through "?") ---------- */
$where  = ['1 = 1'];
$params = [];

if ($q !== '') {
    $like     = '%' . addcslashes($q, '%_\\') . '%';
    $where[]  = '(u.full_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)';
    array_push($params, $like, $like, $like);
}
if ($groupId > 0)    { $where[] = 'd.blood_group_id = ?'; $params[] = $groupId; }
if ($districtId > 0) { $where[] = 'd.district_id = ?';    $params[] = $districtId; }
if ($area !== '')    { $where[] = 'd.area LIKE ?';        $params[] = '%' . addcslashes($area, '%_\\') . '%'; }
if ($avail === 'yes')        { $where[] = 'd.is_available = 1'; }
if ($avail === 'no')         { $where[] = 'd.is_available = 0'; }
if ($status === 'active')      { $where[] = 'u.is_active = 1'; }
if ($status === 'deactivated') { $where[] = 'u.is_active = 0'; }
$whereSql = implode(' AND ', $where);

$from = 'FROM donors d
         JOIN users u         ON u.user_id         = d.user_id
         JOIN blood_groups bg ON bg.blood_group_id = d.blood_group_id
         JOIN districts di    ON di.district_id    = d.district_id';

/* ---------- 3. Summary numbers for the whole donor list (aggregate functions) ---------- */
$summary = $pdo->query(
    "SELECT COUNT(*)                                      AS total,
            COALESCE(SUM(u.is_active = 1), 0)             AS active,
            COALESCE(SUM(u.is_active = 1 AND d.is_available = 1), 0) AS available,
            COALESCE(SUM(d.medical_issues IS NOT NULL AND d.medical_issues <> ''), 0) AS with_note
     FROM donors d JOIN users u ON u.user_id = d.user_id"
)->fetch();

/* ---------- 4. Count the matches, then fetch one page ---------- */
$stmt = $pdo->prepare("SELECT COUNT(*) $from WHERE $whereSql");
$stmt->execute($params);
$total = (int)$stmt->fetchColumn();

$perPage    = 15;
$totalPages = max(1, (int)ceil($total / $perPage));
$page       = min(max(1, (int)($_GET['page'] ?? 1)), $totalPages);
$offset     = ($page - 1) * $perPage;

$stmt = $pdo->prepare(
    "SELECT u.user_id, u.full_name, u.email, u.phone, u.is_active,
            bg.group_name, di.district_name, d.area, d.last_donation_date, d.is_available,
            (d.medical_issues IS NOT NULL AND d.medical_issues <> '') AS has_note
     $from
     WHERE $whereSql
     ORDER BY u.full_name
     LIMIT $perPage OFFSET $offset"
);
$stmt->execute($params);
$donors = $stmt->fetchAll();

// The filters as a query string (used by the page links and to come back here after an action)
$filters = ['q' => $q, 'group' => $groupId ?: '', 'district' => $districtId ?: '',
            'area' => $area, 'avail' => $avail, 'status' => $status];
function page_link(int $p, array $filters): string
{
    return BASE_URL . '/admin/manage_donors.php?' . http_build_query($filters + ['page' => $p]);
}
$returnQuery = http_build_query($filters + ['page' => $page]);

require_once __DIR__ . '/../includes/dash_header.php';
?>

<h1 class="bl-dash-title">Donors</h1>
<p class="text-secondary mb-4">Search, view, edit or deactivate donor accounts.</p>

<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3"><div class="bl-stat"><div class="num"><?php echo (int)$summary['total']; ?></div><div class="lbl">All donors</div></div></div>
    <div class="col-6 col-lg-3"><div class="bl-stat"><div class="num"><?php echo (int)$summary['active']; ?></div><div class="lbl">Active accounts</div></div></div>
    <div class="col-6 col-lg-3"><div class="bl-stat"><div class="num"><?php echo (int)$summary['available']; ?></div><div class="lbl">Active and available</div></div></div>
    <div class="col-6 col-lg-3"><div class="bl-stat bl-stat-alert"><div class="num"><?php echo (int)$summary['with_note']; ?></div><div class="lbl">With a medical note</div></div></div>
</div>

<!-- ============ FILTERS ============ -->
<form method="get" action="" class="bl-filter mb-4">
    <div class="row g-3 align-items-end">
        <div class="col-md-6 col-xl-4">
            <label for="q" class="form-label">Name, email or phone</label>
            <input type="text" class="form-control" id="q" name="q" maxlength="100" value="<?php echo e($q); ?>">
        </div>
        <div class="col-sm-6 col-md-3 col-xl-2">
            <label for="group" class="form-label">Blood group</label>
            <select class="form-select" id="group" name="group">
                <option value="">Any</option>
                <?php foreach ($bloodGroups as $bg): ?>
                    <option value="<?php echo (int)$bg['blood_group_id']; ?>"<?php echo selected($groupId, $bg['blood_group_id']); ?>><?php echo e($bg['group_name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-sm-6 col-md-3 col-xl-3">
            <label for="district" class="form-label">District</label>
            <select class="form-select" id="district" name="district">
                <option value="">Any</option>
                <?php foreach ($districts as $di): ?>
                    <option value="<?php echo (int)$di['district_id']; ?>"<?php echo selected($districtId, $di['district_id']); ?>><?php echo e($di['district_name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-sm-6 col-md-4 col-xl-3">
            <label for="area" class="form-label">Area</label>
            <input type="text" class="form-control" id="area" name="area" maxlength="100" value="<?php echo e($area); ?>">
        </div>
        <div class="col-sm-6 col-md-3 col-xl-2">
            <label for="avail" class="form-label">Availability</label>
            <select class="form-select" id="avail" name="avail">
                <option value="">Any</option>
                <option value="yes"<?php echo selected($avail, 'yes'); ?>>Available</option>
                <option value="no"<?php echo selected($avail, 'no'); ?>>Unavailable</option>
            </select>
        </div>
        <div class="col-sm-6 col-md-3 col-xl-2">
            <label for="status" class="form-label">Account</label>
            <select class="form-select" id="status" name="status">
                <option value="">Any</option>
                <option value="active"<?php echo selected($status, 'active'); ?>>Active</option>
                <option value="deactivated"<?php echo selected($status, 'deactivated'); ?>>Deactivated</option>
            </select>
        </div>
        <div class="col-12 col-md-5 col-xl-3 d-flex gap-2">
            <button type="submit" class="btn btn-primary flex-grow-1">Search</button>
            <a href="<?php echo BASE_URL; ?>/admin/manage_donors.php" class="btn btn-outline-blood">Clear</a>
        </div>
    </div>
</form>

<!-- ============ RESULTS ============ -->
<div class="bl-panel">
    <p class="text-secondary mb-3" aria-live="polite"><?php echo $total; ?> <?php echo $total === 1 ? 'donor' : 'donors'; ?> found</p>

    <?php if (!$donors): ?>
        <p class="mb-0">No donors match these filters.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Donor</th><th>Blood</th><th>Location</th><th>Last donation</th>
                        <th>Availability</th><th>Account</th><th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($donors as $d): ?>
                    <tr>
                        <td>
                            <a class="fw-semibold" href="<?php echo BASE_URL; ?>/admin/donor_view.php?id=<?php echo (int)$d['user_id']; ?>"><?php echo e($d['full_name']); ?></a>
                            <?php if ($d['has_note']): ?>
                                <span class="bl-status bl-status-pending ms-1" title="This donor wrote a medical note. Open the donor to read it.">Medical note</span>
                            <?php endif; ?>
                            <div class="small text-secondary"><?php echo e($d['phone']); ?> &middot; <?php echo e($d['email']); ?></div>
                        </td>
                        <td><strong><?php echo e($d['group_name']); ?></strong></td>
                        <td><?php echo e($d['area']); ?>, <?php echo e($d['district_name']); ?></td>
                        <td class="text-nowrap">
                            <?php echo $d['last_donation_date'] ? e(date('d M Y', strtotime($d['last_donation_date']))) : '<span class="text-secondary">Never</span>'; ?>
                        </td>
                        <td>
                            <?php echo $d['is_available']
                                ? '<span class="bl-status bl-status-approved">Available</span>'
                                : '<span class="bl-status bl-status-cancelled">Unavailable</span>'; ?>
                        </td>
                        <td>
                            <?php echo $d['is_active']
                                ? '<span class="bl-status bl-status-approved">Active</span>'
                                : '<span class="bl-status bl-status-rejected">Deactivated</span>'; ?>
                        </td>
                        <td class="text-end text-nowrap">
                            <a class="btn btn-sm btn-outline-blood" href="<?php echo BASE_URL; ?>/admin/donor_view.php?id=<?php echo (int)$d['user_id']; ?>">View</a>
                            <a class="btn btn-sm btn-outline-blood" href="<?php echo BASE_URL; ?>/admin/donor_edit.php?id=<?php echo (int)$d['user_id']; ?>">Edit</a>
                            <form method="post" action="<?php echo BASE_URL; ?>/admin/donor_status.php" class="d-inline"
                                  data-confirm="<?php echo $d['is_active']
                                      ? 'Deactivate this donor? They will not be able to login and will disappear from Find Donors.'
                                      : 'Reactivate this donor account?'; ?>">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="id" value="<?php echo (int)$d['user_id']; ?>">
                                <input type="hidden" name="make" value="<?php echo $d['is_active'] ? 'deactivate' : 'activate'; ?>">
                                <input type="hidden" name="back" value="list">
                                <input type="hidden" name="qs" value="<?php echo e($returnQuery); ?>">
                                <button type="submit" class="btn btn-sm <?php echo $d['is_active'] ? 'btn-outline-secondary' : 'btn-primary'; ?>">
                                    <?php echo $d['is_active'] ? 'Deactivate' : 'Reactivate'; ?>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
            <nav class="mt-4" aria-label="Pages">
                <ul class="pagination justify-content-center mb-0 flex-wrap">
                    <li class="page-item<?php echo $page <= 1 ? ' disabled' : ''; ?>">
                        <a class="page-link" href="<?php echo e(page_link($page - 1, $filters)); ?>">Previous</a>
                    </li>
                    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                        <li class="page-item<?php echo $p === $page ? ' active' : ''; ?>">
                            <a class="page-link" href="<?php echo e(page_link($p, $filters)); ?>"><?php echo $p; ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item<?php echo $page >= $totalPages ? ' disabled' : ''; ?>">
                        <a class="page-link" href="<?php echo e(page_link($page + 1, $filters)); ?>">Next</a>
                    </li>
                </ul>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/dash_footer.php'; ?>
