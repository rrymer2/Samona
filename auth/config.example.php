<?php
// Copy this file to auth/config.php and fill in your real credentials.
// auth/config.php is gitignored so secrets stay out of the repo.

return [
    'dsn'                   => 'mysql:host=localhost;dbname=samoma;charset=utf8mb4',
    'user'                  => 'samoma_user',
    'password'              => 'CHANGE_ME',
    'session_cookie'        => 'samoma_session',
    'session_lifetime_days' => 14,
];
