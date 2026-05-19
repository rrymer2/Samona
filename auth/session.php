<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

function current_user_row(): ?array {
    $cfg   = auth_config();
    $token = $_COOKIE[$cfg['session_cookie']] ?? null;
    if (!is_string($token) || $token === '') {
        return null;
    }

    $pdo  = db();
    $stmt = $pdo->prepare(
        'SELECT u.id, u.email, u.is_admin, s.id AS session_id
         FROM sessions s
         JOIN users u ON u.id = s.user_id
         WHERE s.token = ? AND s.expires_at > NOW()
         LIMIT 1'
    );
    $stmt->execute([$token]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }

    $pdo->prepare('UPDATE sessions SET last_seen = NOW() WHERE id = ?')
        ->execute([$row['session_id']]);

    return [
        'id'       => (int)$row['id'],
        'email'    => (string)$row['email'],
        'is_admin' => (int)($row['is_admin'] ?? 0) === 1,
    ];
}

function require_login(): array {
    $user = current_user_row();
    if (!$user) {
        header('Location: login.html');
        exit;
    }
    return $user;
}

function require_admin(string $loginRedirect = '../login.html'): array {
    $user = current_user_row();
    if (!$user) {
        header('Location: ' . $loginRedirect);
        exit;
    }
    if (empty($user['is_admin'])) {
        http_response_code(403);
        echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>403 Forbidden</title></head>'
           . '<body style="font-family:system-ui,-apple-system,Segoe UI,sans-serif;max-width:480px;margin:80px auto;padding:0 24px;color:#111;">'
           . '<h1 style="font-size:1.5rem;">403 Forbidden</h1>'
           . '<p>You are signed in as <strong>' . htmlspecialchars($user['email']) . '</strong>, but this area requires admin privileges.</p>'
           . '<p><a href="../dashboard.php">Back to dashboard</a></p>'
           . '</body></html>';
        exit;
    }
    return $user;
}
