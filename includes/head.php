<?php
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) { http_response_code(403); exit; }   // never run this file on its own
/*
 * includes/head.php
 * The <head> part of a page, shared by the public pages AND the dashboards.
 * The page must set $pageTitle before this file is included.
 */
require_once __DIR__ . '/../config/config.php';

if (!isset($pageTitle)) {
    $pageTitle = APP_NAME;
}

// Use Bootstrap from our own folder if we downloaded it (works offline),
// otherwise load it from the internet (CDN).
$localCss = __DIR__ . '/../assets/css/bootstrap.min.css';
$bootstrapCss = file_exists($localCss)
    ? BASE_URL . '/assets/css/bootstrap.min.css'
    : 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo e($pageTitle); ?> | <?php echo APP_NAME; ?></title>

    <!-- small red drop as the browser-tab icon -->
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'%3E%3Cpath fill='%23B3001B' d='M12 2.5c3.6 4.3 6.5 7.6 6.5 11.2a6.5 6.5 0 0 1-13 0C5.5 10.1 8.4 6.8 12 2.5z'/%3E%3C/svg%3E">

    <link rel="stylesheet" href="<?php echo $bootstrapCss; ?>">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css?v=<?php echo (int)@filemtime(__DIR__ . '/../assets/css/style.css'); ?>">
</head>
