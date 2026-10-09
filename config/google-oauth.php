<?php
/**
 * Google OAuth 2.0 Configuration — from app_config() (GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET in the environment,
 * config/.env or config/local.php). Empty = Google sign-in is not offered.
 *
 * The redirect URI is built dynamically in helpers/google-auth.php from the current host; register each
 * domain's /google-callback.php in the Google Cloud Console.
 */
require_once __DIR__ . '/app.php';

define('GOOGLE_CLIENT_ID', (string)app_config('GOOGLE_CLIENT_ID', ''));
define('GOOGLE_CLIENT_SECRET', (string)app_config('GOOGLE_CLIENT_SECRET', ''));
