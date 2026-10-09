<?php
/**
 * Standalone configuration — copy to config/local.php (git-ignored) and fill in. Under the Business OS the
 * installer writes config/.env instead and this file is not needed; .env wins over this file, the environment
 * over both (config/app.php).
 */
return [
    'APP_NAME'    => 'Pro Appointments',
    'APP_URL'     => 'https://example.com',          // the address Retell and Twilio reach this server at

    'DB_HOST'     => '127.0.0.1',
    'DB_PORT'     => '5432',
    'DB_NAME'     => 'pro_appointments',
    'DB_USER'     => 'pro_appointments',
    'DB_PASSWORD' => '',

    // Google sign-in (optional; standalone only — closed under the Business OS)
    'GOOGLE_CLIENT_ID'     => '',
    'GOOGLE_CLIENT_SECRET' => '',

    // Retell server-wide key, used when a business has not saved its own (optional)
    'RETELL_DEFAULT_API_KEY' => '',

    // Inbound email webhook: give the mail provider <APP_URL>/api/email/webhook.php?key=<this>
    // (a long random string, e.g. openssl rand -hex 24). Empty = the webhook accepts nothing.
    'EMAIL_WEBHOOK_SECRET' => '',

    // The public voice-agent demo pages (/demo.php, /call.php): '1' to turn them on.
    'DEMO_ENABLED' => '',

    // Business OS (written by the kernel's installer into config/.env; here only for building without a kernel)
    'OS_ENABLED'           => '0',
    'APP_KEY'              => 'pro_appointments',
    'ACTION_TOKEN_KEY'     => '',                    // development: openssl rand -hex 32
    'OS_LAUNCHER_URL'      => '',
    'OS_INTERNAL_URL'      => '',
    'OS_APPLICATION_TOKEN' => '',
];
