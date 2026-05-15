<?php
declare(strict_types=1);

require __DIR__ . '/db.php';

header('Content-Type: application/json');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'method_not_allowed']);
    exit;
}

$payload = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($payload)) {
    $payload = $_POST;
}

$email    = trim((string)($payload['email']    ?? ''));
$password =       (string)($payload['password'] ?? '');

if ($email === '' || $password === '') {
    http_response_code(400);
    echo json_encode(['error' => 'missing_credentials']);
    exit;
}

$pdo = db();
$ip  = client_ip();
$ua  = client_ua();

$stmt = $pdo->prepare('SELECT id, password_hash FROM users WHERE email = ? LIMIT 1');
$stmt->execute([$email]);
$user = $stmt->fetch();

$userId = $user ? (int)$user['id'] : null;
$ok     = $user && password_verify($password, $user['password_hash']);

$pdo->prepare(
    'INSERT INTO login_events (user_id, ip, user_agent, success) VALUES (?, ?, ?, ?)'
)->execute([$userId, $ip, $ua, $ok ? 1 : 0]);

if (!$ok) {
    http_response_code(401);
    echo json_encode(['error' => 'invalid_credentials']);
    exit;
}

if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
    $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
        ->execute([password_hash($password, PASSWORD_DEFAULT), $userId]);
}

$cfg          = auth_config();
$lifetimeSec  = (int)$cfg['session_lifetime_days'] * 86400;
$token        = bin2hex(random_bytes(32));
$expiresAtSql = (new DateTimeImmutable('@' . (time() + $lifetimeSec)))->format('Y-m-d H:i:s');

$pdo->prepare(
    'INSERT INTO sessions (user_id, token, expires_at, last_seen) VALUES (?, ?, ?, NOW())'
)->execute([$userId, $token, $expiresAtSql]);

setcookie($cfg['session_cookie'], $token, [
    'expires'  => time() + $lifetimeSec,
    'path'     => '/',
    'secure'   => !empty($_SERVER['HTTPS']),
    'httponly' => true,
    'samesite' => 'Lax',
]);

echo json_encode(['ok' => true, 'redirect' => 'dashboard.php']);
