<?php
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) { http_response_code(403); exit; }   // never run this file on its own
/*
 * includes/scripts.php
 * The JavaScript files at the very bottom of every page (public and dashboard).
 */
$localJs = __DIR__ . '/../assets/js/bootstrap.bundle.min.js';
$bootstrapJs = file_exists($localJs)
    ? BASE_URL . '/assets/js/bootstrap.bundle.min.js'
    : 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js';
?>
<script src="<?php echo $bootstrapJs; ?>"></script>
<script src="<?php echo BASE_URL; ?>/assets/js/main.js?v=<?php echo (int)@filemtime(__DIR__ . '/../assets/js/main.js'); ?>"></script>
</body>
</html>
