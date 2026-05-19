<?php
declare(strict_types=1);

// Stripe webhook receiver — handles checkout.session.completed,
// checkout.session.expired, charge.refunded, and charge.failed.
//
// Configure on the Stripe side:
//   Dashboard → Developers → Webhooks → Add endpoint
//   URL: https://samonaindustries.com/payment/webhook.php
//   Events: checkout.session.completed, checkout.session.expired,
//           charge.refunded, charge.failed
//   Copy the signing secret (whsec_...) into payment/config.php → 'webhook_secret'.

require __DIR__ . '/../auth/db.php';
require __DIR__ . '/stripe.php';

// Read the raw request body BEFORE any output or framework parsing.
$payload = file_get_contents('php://input');
if ($payload === false) {
    http_response_code(400);
    exit;
}

$cfg    = stripe_config();
$secret = (string)($cfg['webhook_secret'] ?? '');

if ($secret === '' || strpos($secret, 'whsec_REPLACE_ME') === 0) {
    error_log('[payment/webhook] webhook_secret not configured');
    http_response_code(500);
    exit;
}

$header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
$parts  = [];
foreach (explode(',', $header) as $segment) {
    if (strpos($segment, '=') !== false) {
        [$k, $v] = explode('=', $segment, 2);
        $parts[$k] = $v;
    }
}

$timestamp = (string)($parts['t']  ?? '');
$signature = (string)($parts['v1'] ?? '');

if ($timestamp === '' || $signature === '') {
    error_log('[payment/webhook] missing Stripe-Signature parts');
    http_response_code(400);
    exit;
}

// 5-minute replay-window tolerance (Stripe's default).
if (abs(time() - (int)$timestamp) > 300) {
    error_log('[payment/webhook] timestamp out of tolerance');
    http_response_code(400);
    exit;
}

$expected = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);
if (!hash_equals($expected, $signature)) {
    error_log('[payment/webhook] signature mismatch');
    http_response_code(400);
    exit;
}

// Signature valid — safe to process.
$event = json_decode($payload, true);
if (!is_array($event) || !isset($event['id'], $event['type'], $event['data']['object'])) {
    http_response_code(400);
    exit;
}

$eventId = (string) $event['id'];
$type    = (string) $event['type'];
$obj     = $event['data']['object'];
$pdo     = db();

// Log every verified event. Unique constraint on stripe_event_id absorbs
// Stripe retries: a duplicate ON DUPLICATE KEY UPDATE is a no-op and we
// detect by checking processed_at below.
$alreadyProcessed = false;
try {
    $pdo->prepare(
        'INSERT INTO payments_events (stripe_event_id, type, payload)
              VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE received_at = received_at'
    )->execute([$eventId, $type, $payload]);

    $check = $pdo->prepare(
        'SELECT processed_at FROM payments_events WHERE stripe_event_id = ? LIMIT 1'
    );
    $check->execute([$eventId]);
    $row = $check->fetch();
    $alreadyProcessed = $row && $row['processed_at'] !== null;
} catch (Throwable $e) {
    error_log('[payment/webhook] payments_events log failed: ' . $e->getMessage());
}

if ($alreadyProcessed) {
    http_response_code(200);
    header('Content-Type: application/json');
    echo '{"received":true,"replay":true}';
    exit;
}

try {
    switch ($type) {
        case 'checkout.session.completed':
            $sid = (string)($obj['id']               ?? '');
            $pi  = (string)($obj['payment_intent']   ?? '');
            $em  = (string)($obj['customer_details']['email'] ?? '');
            if ($sid !== '') {
                $pdo->prepare(
                    'UPDATE payments
                        SET status = "paid",
                            paid_at = NOW(),
                            stripe_payment_intent_id = COALESCE(NULLIF(?, ""), stripe_payment_intent_id),
                            customer_email = COALESCE(NULLIF(?, ""), customer_email)
                      WHERE stripe_session_id = ?'
                )->execute([$pi, $em, $sid]);
            }
            break;

        case 'checkout.session.expired':
            $sid = (string)($obj['id'] ?? '');
            if ($sid !== '') {
                $pdo->prepare(
                    'UPDATE payments
                        SET status = "expired"
                      WHERE stripe_session_id = ? AND status = "pending"'
                )->execute([$sid]);
            }
            break;

        case 'charge.refunded':
            $pi = (string)($obj['payment_intent'] ?? '');
            if ($pi !== '') {
                $pdo->prepare(
                    'UPDATE payments SET status = "refunded" WHERE stripe_payment_intent_id = ?'
                )->execute([$pi]);
            }
            break;

        case 'charge.failed':
            $pi = (string)($obj['payment_intent'] ?? '');
            if ($pi !== '') {
                $pdo->prepare(
                    'UPDATE payments
                        SET status = "failed"
                      WHERE stripe_payment_intent_id = ? AND status IN ("pending", "paid")'
                )->execute([$pi]);
            }
            break;

        default:
            // Unhandled event types are not errors — Stripe sends many we don't subscribe to.
            break;
    }
} catch (Throwable $e) {
    error_log('[payment/webhook] handler error for ' . $type . ': ' . $e->getMessage());
    try {
        $pdo->prepare(
            'UPDATE payments_events SET error = ? WHERE stripe_event_id = ?'
        )->execute([$e->getMessage(), $eventId]);
    } catch (Throwable $_) { /* swallow */ }
    http_response_code(500);
    exit;
}

try {
    $pdo->prepare(
        'UPDATE payments_events SET processed_at = NOW() WHERE stripe_event_id = ?'
    )->execute([$eventId]);
} catch (Throwable $e) {
    error_log('[payment/webhook] processed_at UPDATE failed: ' . $e->getMessage());
}

http_response_code(200);
header('Content-Type: application/json');
echo '{"received":true}';
