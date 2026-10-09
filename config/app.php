<?php
/**
 * Application configuration — one function, three sources, in this order:
 *   1. the process environment (Apache SetEnv, systemd Environment=, a shell export);
 *   2. config/.env — KEY=VALUE lines, the file the Business OS installer writes (git-ignored);
 *   3. config/local.php — a PHP array for a standalone install (git-ignored; see config/local.example.php);
 *   4. the default given by the caller.
 * Nothing that differs per server is a constant in code: the database, the Google client, the Retell key,
 * the application's URL and the kernel's keys all come through here.
 */

function app_config(string $key, $default = null)
{
    static $env = null, $local = null;
    if ($env === null) {
        $env = [];
        $file = __DIR__ . '/.env';
        if (is_readable($file)) {
            foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                $line = trim($line);
                if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) continue;
                [$k, $v] = explode('=', $line, 2);
                $k = trim($k);
                if (str_starts_with($k, 'export ')) $k = trim(substr($k, 7));
                $v = trim($v);
                if ($v !== '' && ($v[0] === '"' || $v[0] === "'") && str_ends_with($v, $v[0])) $v = substr($v, 1, -1);
                $env[$k] = $v;
            }
        }
    }
    if ($local === null) {
        $file = __DIR__ . '/local.php';
        $local = is_readable($file) ? (array)(require $file) : [];
    }
    $v = getenv($key);
    if ($v !== false && $v !== '') return $v;
    if (isset($env[$key]) && $env[$key] !== '') return $env[$key];
    if (array_key_exists($key, $local)) return $local[$key];
    return $default;
}

/** The application's own address (APP_URL), without a trailing slash; the request's host when unset. */
function app_url(): string
{
    $url = (string)app_config('APP_URL', '');
    if ($url !== '') return rtrim($url, '/');
    $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https' ? 'https' : 'http';
    return $proto . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
}
