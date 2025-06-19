<?php
// Define ADMIN_EMAIL constant if not already defined
if (!defined('ADMIN_EMAIL')) {
    define('ADMIN_EMAIL', getenv('ADMIN_EMAIL') ?: 'admin@painter-near-me.co.uk');
}

return [
    'host' => 'smtp.painter-near-me.co.uk',
    'username' => ADMIN_EMAIL,
    'password' => getenv('SMTP_PASSWORD') ?: 'co=tMCdL16.X', // Use environment variable in production
    'port' => 465,
    'encryption' => 'ssl',
    'from_email' => ADMIN_EMAIL,
    'from_name' => 'Painter Near Me',
    'reply_to' => ADMIN_EMAIL,
]; 