<?php
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'belov_bd1.2');
define('DB_USER', 'admin');
define('DB_PASS', 'admin');
define('DB_CHARSET', 'utf8mb4');

define('SITE_NAME', 'Автокаталог');
define('MAX_LOGIN_ATTEMPTS', 3);
define('PASSWORD_LIFETIME_DAYS', 90);       
define('LOCKOUT_MINUTES', 60);              
define('AES_KEY', 'change-me-32-bytes-secret-key!!');

ini_set('display_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

function db(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s',
        DB_HOST, DB_PORT, DB_NAME, DB_CHARSET);

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
        ]);
        return $pdo;
    } catch (PDOException $e) {
        http_response_code(500);
        die('<h1>Не удалось подключиться к БД</h1>'
          . '<pre>' . htmlspecialchars($e->getMessage()) . '</pre>');
    }
}