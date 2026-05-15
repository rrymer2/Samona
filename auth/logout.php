<?php
declare(strict_types=1);

require __DIR__ . '/db.php';

$cfg   = auth_config();
$token = $_COOKIE[$cfg['session_cookie']] ?? null;

if (is_string($token) && $token !== '') {
    db()->prepare('DELETE FROM sessions WHERE token = ?')->execute([$token]);
}

setcookie($cfg['session_cookie'], '', [
    'expires'  => time() - 3600,
    'path'     => '/',
    'secure'   => !empty($_SERVER['HTTPS']),
    'httponly' => true,
    'samesite' => 'Lax',
]);

header('Location: ../login.html');
