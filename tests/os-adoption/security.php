<?php
/** The security fixes S1, S3–S13 (S2 was fixed in the source), with OS_ENABLED off. */
require __DIR__ . '/lib.php';
$pdo = db();
set_os(false);
$pdo->exec("INSERT INTO settings (restaurant_id, setting_key, setting_value) VALUES (1, 'retell_api_key', 'proof-retell-key') ON CONFLICT DO NOTHING");
$pdo->exec("DELETE FROM settings WHERE restaurant_id = 1 AND setting_key = 'retell_api_key'; INSERT INTO settings (restaurant_id, setting_key, setting_value) VALUES (1, 'retell_api_key', 'proof-retell-key')");
$pdo->exec("DELETE FROM restaurant_phone_numbers WHERE phone_number = '+15555550100'; INSERT INTO restaurant_phone_numbers (restaurant_id, phone_number, label, is_primary, is_active) VALUES (1, '+15555550100', 'Main', 1, 1)");
$pdo->exec("UPDATE restaurants SET phone = '+15555550100' WHERE id = 1");
foreach (glob(dirname(__DIR__, 2) . '/logs/*.log') ?: [] as $f) file_put_contents($f, '');

echo "S1. An invitation needs its one-time code\n";
$j = login_as('owner@example.invalid', 'Test1234Aa');
$t = page_csrf($j);
$pdo->exec("DELETE FROM users WHERE email = 'invited@example.invalid'");
$r = req('POST', '/partials/settings/save-user.php', ['jar' => $j, 'headers' => ['HX-Request: true'], 'form' => ['csrf_token' => $t, 'first_name' => 'Invited', 'last_name' => 'Person', 'email' => 'invited@example.invalid', 'role' => 'user']]);
preg_match('/id="save-user-invite-code-value">([A-Z2-9]+)</', $r['body'], $m);
$code = $m[1] ?? '';
ok(strlen($code) === 10, "adding a staff member without a password shows a 10-character code once ({$r['code']})");
$u = $pdo->query("SELECT password_hash, invite_code_hash, invite_expires_at FROM users WHERE email = 'invited@example.invalid'")->fetch(PDO::FETCH_ASSOC);
ok($u && $u['password_hash'] === '!INVITED' && $u['invite_code_hash'] === hash('sha256', $code) && strtotime($u['invite_expires_at']) > time() + 13 * 86400, 'only its SHA-256 is stored, valid 14 days');
$accept = fn(array $extra) => req('POST', '/partials/auth/accept-invite.php', ['jar' => jar(), 'headers' => ['HX-Request: true'], 'form' => $extra]);
$ja = jar(); $cs = csrf_from(req('GET', '/register.php', ['jar' => $ja])['body']);
$r = req('POST', '/partials/auth/accept-invite.php', ['jar' => $ja, 'form' => ['csrf_token' => $cs, 'email' => 'invited@example.invalid', 'password' => 'Test1234Aa', 'confirm_password' => 'Test1234Aa']]);
ok(str_contains($r['body'], 'invite-error-required'), 'accepting with the email alone: refused');
$r = req('POST', '/partials/auth/accept-invite.php', ['jar' => $ja, 'form' => ['csrf_token' => $cs, 'email' => 'invited@example.invalid', 'invite_code' => 'WRONGCODE1', 'password' => 'Test1234Aa', 'confirm_password' => 'Test1234Aa']]);
ok(str_contains($r['body'], 'invite-error-code'), 'a wrong code: refused');
$r = req('POST', '/partials/auth/accept-invite.php', ['jar' => $ja, 'form' => ['csrf_token' => $cs, 'email' => 'invited@example.invalid', 'invite_code' => strtolower(substr($code, 0, 5)) . '-' . substr($code, 5), 'password' => 'Test1234Aa', 'confirm_password' => 'Test1234Aa']]);
ok(str_contains($r['headers'], 'HX-Redirect: /app.php'), 'the code (any case, with a dash): accepted and signed in');
$u = $pdo->query("SELECT password_hash, invite_code_hash FROM users WHERE email = 'invited@example.invalid'")->fetch(PDO::FETCH_ASSOC);
ok($u['password_hash'] !== '!INVITED' && $u['invite_code_hash'] === null, 'the password is set and the code used up');
$key = api_create_key(1, 1, 'proof');
$r = req('POST', '/api/v1/staff.php', ['headers' => ["Authorization: Bearer $key"], 'json' => ['first_name' => 'Api', 'last_name' => 'Invited', 'email' => 'api-invited@example.invalid', 'role' => 'user']]);
$d = json_decode($r['body'], true);
ok($r['code'] === 201 && strlen((string)($d['invite_code'] ?? '')) === 10 && !empty($d['staff']['invitation_pending']), "the REST add answers the code once ({$r['code']})");
$pdo->exec("DELETE FROM users WHERE email IN ('invited@example.invalid', 'api-invited@example.invalid')");

echo "S3. Every Retell request is signed with the business's own key\n";
$body = json_encode(['event' => 'call_started', 'args' => ['restaurant_slug' => 'test-studio'], 'call' => ['call_id' => 'proof', 'to_number' => '+15555550100', 'from_number' => '+15555550199']]);
$sign = function (string $body, string $key, int $ms = null): string { $ms ??= (int)(microtime(true) * 1000); return 'v=' . $ms . ',d=' . hash_hmac('sha256', $body . $ms, $key); };
ok(req('POST', '/api/retell/pro-webhook.php', ['raw' => $body, 'headers' => ['Content-Type: application/json']])['code'] === 401, 'pro-webhook with no signature: 401');
ok(req('POST', '/api/retell/pro-webhook.php', ['raw' => $body, 'headers' => ['Content-Type: application/json', 'X-Retell-Signature: ' . $sign($body, 'another-business-key')]])['code'] === 401, "another key's signature: 401");
ok(req('POST', '/api/retell/pro-webhook.php', ['raw' => $body, 'headers' => ['Content-Type: application/json', 'X-Retell-Signature: ' . $sign($body, 'proof-retell-key', (int)(microtime(true) * 1000) - 6 * 60 * 1000)]])['code'] === 401, 'a six-minute-old signature: 401');
$r = req('POST', '/api/retell/pro-webhook.php', ['raw' => $body, 'headers' => ['Content-Type: application/json', 'X-Retell-Signature: ' . $sign($body, 'proof-retell-key')]]);
ok($r['code'] === 200, "the business's own key: accepted ({$r['code']})");
ok(req('POST', '/api/retell/check-availability.php', ['raw' => $body, 'headers' => ['Content-Type: application/json']])['code'] === 401, 'a custom function with no signature: 401');
ok(req('POST', '/api/retell/webhook-call-end.php', ['raw' => $body, 'headers' => ['Content-Type: application/json']])['code'] === 401, 'webhook-call-end with no signature: 401');

echo "S4. The Twilio webhooks accept only the business's signed requests\n";
$form = ['From' => '+15555550199', 'To' => '+15555550100', 'Body' => 'Proof message body PROOFTEXT', 'MessageSid' => 'SMproof'];
$twsig = function (array $p, string $path, string $token): string { ksort($p, SORT_STRING); $d = 'http://127.0.0.1:8191' . $path; foreach ($p as $k => $v) $d .= $k . $v; return base64_encode(hash_hmac('sha1', $d, $token, true)); };
ok(req('POST', '/api/sms/twilio_pro.php', ['form' => $form])['code'] === 403, 'twilio_pro with no signature: 403');
ok(req('POST', '/api/sms/twilio_pro.php', ['form' => $form, 'headers' => ['X-Twilio-Signature: ' . $twsig($form, '/api/sms/twilio_pro.php', 'wrong-token')]])['code'] === 403, 'a wrong token: 403');
$r = req('POST', '/api/sms/twilio_pro.php', ['form' => $form, 'headers' => ['X-Twilio-Signature: ' . $twsig($form, '/api/sms/twilio_pro.php', 'proof-twilio-token')]]);
ok($r['code'] === 200, "the business's token: accepted ({$r['code']})");
ok(req('POST', '/api/sms/webhook.php', ['form' => $form])['code'] === 403, 'the AI SMS webhook with no signature: 403');

echo "S5. The inbound email webhook needs the secret URL\n";
ok(req('POST', '/api/email/webhook.php', ['form' => ['from' => 'a@example.invalid', 'to' => 'b@example.invalid', 'subject' => 'x', 'text' => 'PROOFTEXT']])['code'] === 403, 'no key: 403');
ok(req('POST', '/api/email/webhook.php?key=wrong', ['form' => ['from' => 'a@example.invalid']])['code'] === 403, 'a wrong key: 403');
ok(req('POST', '/api/email/webhook.php?key=proof-email-secret', ['form' => ['from' => 'a@example.invalid']])['code'] === 200, 'the key: accepted (then ignored for missing fields)');

echo "S7. The diagnostic pages are gone\n";
foreach (['/info.php', '/test-db.php', '/test-email.php'] as $p) ok(req('GET', $p)['code'] === 404, "$p: 404");

echo "S8. The Retell agent import needs an admin\n";
$r = req('GET', '/downloads/retell-agent-import.php?slug=test-studio');
ok($r['code'] === 302 && location($r) === '/login.php', 'without login: to the login page, no key');
ok(req('GET', '/downloads/retell-agent-import.php', ['jar' => $j])['code'] === 200, 'the admin: the file');

echo "S9. The public demo is off\n";
foreach (['/demo.php', '/call.php'] as $p) ok(req('GET', $p)['code'] === 404, "$p: 404");
ok(req('POST', '/api/retell/create-demo-call.php', ['json' => ['agent_id' => 'agent_x']])['code'] === 404, 'create-demo-call: 404');

echo "S10. Cron is not reachable over the web\n";
foreach (['/cron/generate-invoices.php', '/cron/mark-overdue.php'] as $p) ok(req('GET', $p)['code'] === 404, "$p: 404");

echo "S11. Switching needs the CSRF token\n";
ok(req('POST', '/partials/auth/switch-restaurant.php', ['jar' => $j, 'form' => ['restaurant_id' => 1]])['code'] === 403, 'switch-restaurant without the token: 403');
ok(str_contains(req('POST', '/partials/auth/switch-restaurant.php', ['jar' => $j, 'form' => ['restaurant_id' => 1, 'csrf_token' => $t]])['headers'], 'HX-Redirect: /app.php'), 'with it: switched');
ok(req('POST', '/partials/auth/switch-mode.php', ['jar' => $j, 'form' => ['mode' => 'professional']])['code'] === 403, 'switch-mode without the token: 403');

echo "S13. The SMS agent's context webhooks need the business's MCP key\n";
$ctx = ['to_number' => '+15555550100', 'from_number' => '+15555550199'];
ok(req('POST', '/api/sms/pro-webhook.php', ['form' => $ctx])['code'] === 401, 'pro-webhook (context) with no key: 401');
ok(req('POST', '/api/sms/pro-webhook.php', ['form' => $ctx, 'headers' => ['Authorization: Bearer some-other-key']])['code'] === 401, "another key: 401");
$r = req('POST', '/api/sms/pro-webhook.php', ['form' => $ctx, 'headers' => ['Authorization: Bearer proof-mcp-key-studio']]);
ok($r['code'] === 200 && (json_decode($r['body'], true)['business']['name'] ?? '') === 'Test Studio', "the business's key: the context ({$r['code']})");
ok(req('POST', '/api/sms/text-agent-webhook.php', ['json' => $ctx])['code'] === 401, 'text-agent-webhook with no key: 401');

echo "S12. The logs carry no message bodies or callers' numbers\n";
$logs = implode("\n", array_map('file_get_contents', glob(dirname(__DIR__, 2) . '/logs/*.log') ?: []));
ok(!str_contains($logs, 'PROOFTEXT') && !str_contains($logs, '5555550199'), "no message body, no caller's number in logs/ (" . strlen($logs) . ' bytes written)');
$pdo->exec("DELETE FROM api_tokens WHERE device_name = 'proof'");
finish();
