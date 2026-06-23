<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../auth/db.php';
require_once __DIR__ . '/stripe.php';

$user = require_login();

function redirect_with_error(string $msg): void {
    header('Location: ../payment.php?error=' . urlencode($msg));
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}

$amount    = trim((string)($_POST['amount']    ?? ''));
$reference = trim((string)($_POST['reference'] ?? ''));
$email     = trim((string)($_POST['email']     ?? ''));

if (!is_numeric($amount) || (float)$amount <= 0) {
    redirect_with_error('Please enter a valid amount.');
}
$amountCents = (int) round(((float) $amount) * 100);

$cfg = stripe_config();

if ($amountCents < $cfg['min_amount']) {
    redirect_with_error('Amount is below the $' . number_format($cfg['min_amount'] / 100, 2) . ' minimum.');
}
if ($amountCents > $cfg['max_amount']) {
    redirect_with_error('Amount exceeds the $' . number_format($cfg['max_amount'] / 100, 2) . ' maximum.');
}
if ($reference === '' || mb_strlen($reference) > 50) {
    redirect_with_error('Reference is required and must be 50 characters or fewer.');
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    redirect_with_error('Please enter a valid email address for the receipt.');
}

try {
    $session = stripe_request('POST', '/checkout/sessions', [
        'mode'                => 'payment',
        'success_url'         => $cfg['success_url'],
        'cancel_url'          => $cfg['cancel_url'],
        'customer_email'      => $email,
        'client_reference_id' => 'user_' . $user['id'],
        'line_items'          => [[
            'price_data' => [
                'currency'     => $cfg['currency'],
                'unit_amount'  => $amountCents,
                'product_data' => [
                    'name'        => 'Samoma Industries — ' . $reference,
                    'description' => 'Payment from ' . $email,
                ],
            ],
            'quantity' => 1,
        ]],
        'metadata' => [
            'user_id'   => (string) $user['id'],
            'reference' => $reference,
        ],
    ]);
} catch (Throwable $e) {
    error_log('[payment] Stripe session create failed: ' . $e->getMessage());
    redirect_with_error('Payment system error. Please try again or contact support.');
}

if (empty($session['url']) || empty($session['id'])) {
    redirect_with_error('Stripe did not return a checkout URL. Please try again.');
}

// Best-effort audit row. The webhook is the authoritative source of truth
// for status changes; if this INSERT fails we still let the user pay and
// log it for later reconciliation.
try {
    db()->prepare(
        'INSERT INTO payments
           (user_id, stripe_session_id, amount, currency, reference, customer_email, status)
         VALUES (?, ?, ?, ?, ?, ?, "pending")'
    )->execute([
        (int) $user['id'],
        (string) $session['id'],
        (float) $amount,
        $cfg['currency'],
        $reference,
        $email,
    ]);
} catch (Throwable $e) {
    error_log('[payment] payments INSERT failed for session ' . $session['id'] . ': ' . $e->getMessage());
}

header('Location: ' . $session['url']);
exit;
