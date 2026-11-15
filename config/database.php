<?php
/*
 * config/database.php
 * Connects PHP to the MySQL database using PDO.
 * Any page that needs the database does:  require_once '.../config/database.php';
 * After that, the variable $pdo is ready to use.
 */
require_once __DIR__ . '/config.php';

// XAMPP's default MySQL login: user "root" with an EMPTY password.
$dbHost = 'localhost';
$dbName = 'bloodbank';
$dbUser = 'root';
$dbPass = '';

try {
    $pdo = new PDO(
        "mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4",
        $dbUser,
        $dbPass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // show real errors
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // rows come back as arrays
            PDO::ATTR_EMULATE_PREPARES   => false,                  // real prepared statements
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    echo '<h1>Cannot connect to the database</h1>';
    echo '<p>Check that <strong>MySQL</strong> is started in XAMPP and that the database '
       . '<strong>bloodbank</strong> exists in phpMyAdmin.</p>';
    if (APP_DEBUG) {                       // the technical reason is shown only while testing
        echo '<pre>' . htmlspecialchars($e->getMessage()) . '</pre>';
    }
    exit;
}
