<?php
// Copy this file to payment/config.php and fill in your real Stripe keys.
// payment/config.php is gitignored so secrets stay out of the repo.
//
// Get keys at https://dashboard.stripe.com/apikeys
// Use TEST keys (sk_test_..., pk_test_...) during development and switch
// to LIVE keys only when you're ready to charge real cards.
//
// Amounts below are in minor units (cents for USD).

return [
    'secret_key'      => 'sk_test_REPLACE_ME',
    'publishable_key' => 'pk_test_REPLACE_ME',
    // From Stripe Dashboard → Developers → Webhooks → your endpoint → "Signing secret".
    // Required for payment/webhook.php to verify event authenticity.
    'webhook_secret'  => 'whsec_REPLACE_ME',
    'currency'        => 'usd',
    'min_amount'      => 100,        // $1.00
    'max_amount'      => 100000000,  // $1,000,000.00
    'success_url'     => 'https://samonaindustries.com/payment/success.php?session_id={CHECKOUT_SESSION_ID}',
    'cancel_url'      => 'https://samonaindustries.com/payment/cancel.php',
];
