<?php
declare(strict_types=1);

function stripe_config(): array {
    static $cfg = null;
    if ($cfg === null) {
        $path = __DIR__ . '/config.php';
        if (!is_file($path)) {
            throw new RuntimeException('payment/config.php is missing — copy payment/config.example.php and fill in your Stripe keys.');
        }
        $cfg = require $path;
    }
    return $cfg;
}

function stripe_request(string $method, string $endpoint, array $params = []): array {
    $cfg = stripe_config();
    $url = 'https://api.stripe.com/v1' . $endpoint;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERPWD, $cfg['secret_key'] . ':');
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Stripe-Version: 2024-06-20']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    if (strtoupper($method) === 'POST') {
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    } else {
        $query = $params ? '?' . http_build_query($params) : '';
        curl_setopt($ch, CURLOPT_URL, $url . $query);
    }

    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($body === false) {
        throw new RuntimeException('Stripe HTTP error: ' . $err);
    }
    $data = json_decode($body, true);
    if (!is_array($data)) {
        throw new RuntimeException('Stripe returned invalid JSON');
    }
    if ($code >= 400) {
        $msg = $data['error']['message'] ?? 'Stripe API error';
        throw new RuntimeException("Stripe API [{$code}]: " . $msg);
    }
    return $data;
}
