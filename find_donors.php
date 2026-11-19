<?php
/*
 * find_donors.php  -  Public donor search.
 *
 * Privacy rules:
 *   - only ACTIVE donors who marked themselves AVAILABLE are listed
 *   - visitors see name, blood group, district and area only
 *   - the phone number appears only after login
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

$pageTitle = 'Find Donors';

// Lists for the filter drop-downs
$bloodGroups = $pdo->query('SELECT blood_group_id, group_name FROM blood_groups ORDER BY blood_group_id')->fetchAll();
$districts   = $pdo->query('SELECT district_id, district_name FROM districts ORDER BY district_name')->fetchAll();

/* ---------- 1. Read the filters from the address (?group=A%2B&district=14&area=mirpur) ---------- */
$group = clean($_GET['group'] ?? '');
if (!in_array($group, array_column($bloodGroups, 'group_name'), true)) {
    $group = '';                                   // not a real blood group: ignore it
}

$districtId = (int)($_GET['district'] ?? 0);
if (!in_array($districtId, array_column($districts, 'district_id'))) {
    $districtId = 0;
}

$area = substr(clean($_GET['area'] ?? ''), 0, 100);

/* ---------- 2. Build the SQL safely: conditions are added one by one,
 *                values always go through "?" placeholders ---------- */
$where  = ['u.is_active = 1', 'd.is_available = 1'];
$params = [];

if ($group !== '') {
    $where[]  = 'bg.group_name = ?';
    $params[] = $group;
}
if ($districtId > 0) {
    $where[]  = 'd.district_id = ?';
    $params[] = $districtId;
}
if ($area !== '') {
    $where[]  = 'd.area LIKE ?';
    $params[] = '%' . addcslashes($area, '%_\\') . '%';   // a typed % or _ is treated as plain text
}
$whereSql = implode(' AND ', $where);

$from = 'FROM donors d
         JOIN users u         ON u.user_id         = d.user_id
         JOIN blood_groups bg ON bg.blood_group_id = d.blood_group_id
         JOIN districts di    ON di.district_id    = d.district_id';

/* ---------- 3. Count, then fetch one page (12 donors per page) ---------- */
$stmt = $pdo->prepare("SELECT COUNT(*) $from WHERE $whereSql");
$stmt->execute($params);
$total = (int)$stmt->fetchColumn();

$perPage    = 12;
$totalPages = max(1, (int)ceil($total / $perPage));
$page       = min(max(1, (int)($_GET['page'] ?? 1)), $totalPages);
$offset     = ($page - 1) * $perPage;

$stmt = $pdo->prepare(
    "SELECT u.full_name, u.phone, bg.group_name, di.district_name, d.area
     $from
     WHERE $whereSql
     ORDER BY u.full_name
     LIMIT $perPage OFFSET $offset"
);
$stmt->execute($params);
$donors = $stmt->fetchAll();

$loggedIn = is_logged_in();

// Keeps the filters inside the "page 2, 3 ..." links
function page_link(int $p, string $group, int $districtId, string $area): string
{
    return BASE_URL . '/find_donors.php?' . http_build_query([
        'group' => $group, 'district' => $districtId ?: '', 'area' => $area, 'page' => $p,
    ]);
}

require_once __DIR__ . '/includes/header.php';
?>

<section class="bl-page-head">
    <div class="container">
        <h1>Find donors</h1>
        <p>Search by blood group and place. Only donors who are available right now are listed.</p>
    </div>
</section>

<section class="bl-section pt-4">
    <div class="container">

        <!-- ============ FILTER FORM (GET = the filters stay in the address bar) ============ -->
        <form method="get" action="" class="bl-filter mb-4">
            <div class="row g-3 align-items-end">
                <div class="col-sm-6 col-lg-3">
                    <label for="group" class="form-label">Blood group</label>
                    <select class="form-select" id="group" name="group">
                        <option value="">Any</option>
                        <?php foreach ($bloodGroups as $bg): ?>
                            <option value="<?php echo e($bg['group_name']); ?>"<?php echo selected($group, $bg['group_name']); ?>>
                                <?php echo e($bg['group_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <label for="district" class="form-label">District</label>
                    <select class="form-select" id="district" name="district">
                        <option value="">Any</option>
                        <?php foreach ($districts as $di): ?>
                            <option value="<?php echo (int)$di['district_id']; ?>"<?php echo selected($districtId, $di['district_id']); ?>>
                                <?php echo e($di['district_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <label for="area" class="form-label">Area</label>
                    <input type="text" class="form-control" id="area" name="area" maxlength="100"
                           value="<?php echo e($area); ?>" placeholder="e.g. Mirpur">
                </div>
                <div class="col-sm-6 col-lg-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1">Search</button>
                    <a href="<?php echo BASE_URL; ?>/find_donors.php" class="btn btn-outline-blood">Clear</a>
                </div>
            </div>
        </form>

        <!-- ============ RESULTS ============ -->
        <p class="text-secondary mb-3" aria-live="polite">
            <?php echo $total; ?> <?php echo $total === 1 ? 'donor' : 'donors'; ?> found
        </p>

        <?php if (!$donors): ?>
            <div class="bl-panel text-center py-5">
                <h2 class="h5">No donors found</h2>
                <p class="text-secondary mb-3">Try another district, or leave the area empty.</p>
                <a href="<?php echo BASE_URL; ?>/find_donors.php" class="btn btn-outline-blood">Clear the search</a>
            </div>
        <?php else: ?>
            <div class="row g-3">
                <?php foreach ($donors as $d): ?>
                    <div class="col-md-6 col-xl-4">
                        <div class="bl-donor-card">
                            <div class="bl-donor-group"><?php echo e($d['group_name']); ?></div>
                            <div class="bl-donor-info">
                                <div class="bl-donor-name"><?php echo e($d['full_name']); ?></div>
                                <div class="bl-donor-meta"><?php echo e($d['area']); ?>, <?php echo e($d['district_name']); ?></div>
                                <div class="mt-1">
                                    <?php if ($loggedIn): ?>
                                        <a href="tel:<?php echo e($d['phone']); ?>"><?php echo e($d['phone']); ?></a>
                                    <?php else: ?>
                                        <a class="small" href="<?php echo BASE_URL; ?>/login.php">Login to see the phone number</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if ($totalPages > 1): ?>
                <nav class="mt-4" aria-label="Pages">
                    <ul class="pagination justify-content-center mb-0">
                        <li class="page-item<?php echo $page <= 1 ? ' disabled' : ''; ?>">
                            <a class="page-link" href="<?php echo e(page_link($page - 1, $group, $districtId, $area)); ?>">Previous</a>
                        </li>
                        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                            <li class="page-item<?php echo $p === $page ? ' active' : ''; ?>">
                                <a class="page-link" href="<?php echo e(page_link($p, $group, $districtId, $area)); ?>"><?php echo $p; ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item<?php echo $page >= $totalPages ? ' disabled' : ''; ?>">
                            <a class="page-link" href="<?php echo e(page_link($page + 1, $group, $districtId, $area)); ?>">Next</a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
        <?php endif; ?>

        <p class="text-secondary small mt-4 mb-0">
            Donors are volunteers and may say no. Please be polite, and remember that a doctor or blood bank
            decides whether a person can donate.
        </p>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
