<?php if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) { http_response_code(403); exit; } ?>
    </main>
</div>

<?php include __DIR__ . '/scripts.php'; ?>
