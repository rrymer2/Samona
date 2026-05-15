<?php
declare(strict_types=1);

function auth_config(): array {
    static $cfg = null;
    if ($cfg === null) {
        $path = __DIR__ . '/config.php';
        if (!is_file($path)) {
            throw new RuntimeException('auth/config.php is missing — copy auth/config.example.php and fill it in.');
        }
        $cfg = require $path;
    }
    return $cfg;
}

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $cfg = auth_config();
        $pdo = new PDO($cfg['dsn'], $cfg['user'], $cfg['password'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}

function client_ip(): ?string {
    return $_SERVER['REMOTE_ADDR'] ?? null;
}

function client_ua(): ?string {
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;
    return $ua !== null ? substr($ua, 0, 255) : null;
}
