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
        'SELECT u.id, u.email, s.id AS session_id
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

    return ['id' => (int)$row['id'], 'email' => $row['email']];
}

function require_login(): array {
    $user = current_user_row();
    if (!$user) {
        header('Location: login.html');
        exit;
    }
    return $user;
}
