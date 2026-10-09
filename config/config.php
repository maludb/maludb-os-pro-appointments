<?php
// Retell AI configuration: the server-wide key (RETELL_API_KEY / RETELL_DEFAULT_API_KEY in the environment,
// config/.env or config/local.php); a business's own key in its settings table overrides it (helpers/retell-auth.php).
require_once __DIR__ . '/app.php';
define('RETELL_API_KEY', (string)app_config('RETELL_API_KEY', app_config('RETELL_DEFAULT_API_KEY', '')));
