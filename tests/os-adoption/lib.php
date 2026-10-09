<?php
/**
 * Proofs of the OS adoption and its security fixes — FOR A SCRATCH COPY ONLY: they write rows (users,
 * businesses, settings, keys, phone numbers), set Retell/Twilio keys and flip OS_ENABLED in config/local.php.
 *
 * A scratch copy: an empty database loaded from docs/sql/pg_schema.sql, nav_permissions.sql, os_adoption.sql;
 * config/local.php pointing at it with APP_URL http://127.0.0.1:8191, EMAIL_WEBHOOK_SECRET 'proof-email-secret',
 * 'OS_ENABLED'=>'0', APP_KEY 'pro_appointments', a development ACTION_TOKEN_KEY (openssl rand -hex 32) and
 * OS_LAUNCHER_URL https://app.example.invalid/; user 1 owner@example.invalid / Test1234Aa (super-admin) holding
 * business 1 "Test Studio" (slug test-studio, professional) as admin, with settings mcp_api_key
 * 'proof-mcp-key-studio' and sms_api_secret 'proof-twilio-token'; the site served with
 *   php -S 127.0.0.1:8191 -t html
 * Then  php tests/os-adoption/<file>.php  — each ends "all passed" or with the count failed. Run security.php
 * first (OS off), then os-sign-on.php (it expects no OS rows yet).
 */
chdir(dirname(__DIR__, 2));
require_once 'helpers/os.php';
const BASE = 'http://127.0.0.1:8191';
const APP = 'pro_appointments';
$GLOBALS['fails'] = 0;
function ok(bool $c, string $l): void { if (!$c) $GLOBALS['fails']++; echo ($c ? "  ok   " : "  FAIL ") . "$l\n"; }
function req(string $method, string $path, array $opt = []): array {
    $ch = curl_init(BASE . $path);
    $h = $opt['headers'] ?? [];
    curl_setopt_array($ch, [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true,
        CURLOPT_COOKIEJAR => $opt['jar'] ?? '/dev/null', CURLOPT_COOKIEFILE => $opt['jar'] ?? '/dev/null']);
    if (isset($opt['form'])) curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($opt['form']));
    if (isset($opt['json'])) { curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($opt['json'])); $h[] = 'Content-Type: application/json'; }
    if (isset($opt['raw'])) curl_setopt($ch, CURLOPT_POSTFIELDS, $opt['raw']);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $h);
    $r = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); $hs = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    return ['code' => $code, 'headers' => substr($r, 0, $hs), 'body' => substr($r, $hs)];
}
function csrf_from(string $html): string { preg_match('/name="csrf_token" value="([^"]+)"/', $html, $m); return $m[1] ?? ''; }
function jar(): string { return tempnam(sys_get_temp_dir(), 'pajar'); }
function finish(): void { echo $GLOBALS['fails'] ? "{$GLOBALS['fails']} FAILED\n" : "all passed\n"; exit($GLOBALS['fails'] ? 1 : 0); }
function login_as(string $email, string $pw): string {
    $j = jar(); $p = req('GET', '/login.php', ['jar' => $j]);
    req('POST', '/partials/auth/login.php', ['jar' => $j, 'form' => ['csrf_token' => csrf_from($p['body']), 'email' => $email, 'password' => $pw]]);
    return $j;
}
function page_csrf(string $j): string { preg_match('/name="csrf-token" content="([^"]+)"/', req('GET', '/app.php', ['jar' => $j])['body'], $m); return $m[1] ?? ''; }
function set_os(bool $on): void {
    $p = dirname(__DIR__, 2) . '/config/local.php';
    $s = file_get_contents($p);
    $s = preg_replace("/'OS_ENABLED'=>'[01]'/", "'OS_ENABLED'=>'" . ($on ? '1' : '0') . "'", $s);
    file_put_contents($p, $s);
}
function devkey(): string { return (require dirname(__DIR__, 2) . '/config/local.php')['ACTION_TOKEN_KEY']; }
function mint_handoff(int $member, array $claims, ?int $scope = null, string $app = APP, int $ttl = 60, ?string $key = null): string {
    $key ??= devkey();
    $payload = $member . '.' . (time() + $ttl) . '.' . $app . '.' . bin2hex(random_bytes(16));
    $token = $payload . '.' . hash_hmac('sha256', 'sso:' . $payload, $key);
    $claims = $claims + ['member_id' => $member]; $claims['scope'] = $scope;
    $text = rtrim(strtr(base64_encode(json_encode($claims)), '+/', '-_'), '=');
    return '/sso.php?' . http_build_query(['token' => $token, 'claims' => $text . '.' . hash_hmac('sha256', $text, $key)]);
}
function fixture(): array { return json_decode(file_get_contents(dirname(__DIR__, 2) . '/scripts/os-dev-directory.json'), true); }
function kernel_token(string $app = APP): string {
    $payload = (time() + 60) . '.' . $app . '.' . bin2hex(random_bytes(16));
    return 'kernel.' . $payload . '.' . hash_hmac('sha256', 'kernel:' . $payload, devkey());
}
function logout_notice(int $member, string $app = APP): string {
    $payload = $member . '.' . time() . '.' . $app;
    return $payload . '.' . hash_hmac('sha256', 'sso-logout:' . $payload, devkey());
}
function sync_with(array $doc): string {
    $f = tempnam(sys_get_temp_dir(), 'fx'); file_put_contents($f, json_encode($doc));
    $out = shell_exec('php ' . dirname(__DIR__, 2) . '/scripts/os-directory-sync.php --from-file ' . escapeshellarg($f) . ' 2>&1');
    unlink($f); return trim((string)$out);
}
function location(array $r): string { return preg_match('/^Location: (.+)$/mi', $r['headers'], $m) ? trim($m[1]) : ''; }
/** An API key (api_tokens) for a user in a business; answers the raw token. */
function api_create_key(int $userId, int $restaurantId, string $name): string {
    $raw = bin2hex(random_bytes(32));
    db()->prepare("INSERT INTO api_tokens (user_id, restaurant_id, token_hash, device_name, expires_at) VALUES (?, ?, ?, ?, NOW() + INTERVAL '1 day')")
        ->execute([$userId, $restaurantId, hash('sha256', $raw), $name]);
    return $raw;
}
