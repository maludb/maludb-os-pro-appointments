<?php
/** The adoption proofs of os-adopt/adapter.md §9, with OS_ENABLED on (restored to off at the end). */
require __DIR__ . '/lib.php';
$pdo = db();
$L = 'https://app.example.invalid/?app=' . APP;
set_os(true);
register_shutdown_function(fn() => set_os(false));
$fx = fixture();

echo "0. The first sync: every site becomes a professional business, seeded\n";
$out = sync_with(['scopes' => $fx['scopes'], 'feed' => $fx['feed']]);
ok(str_contains($out, '"scopes":2'), "sync: $out");
$biz = $pdo->query("SELECT os_scope_id, id, name, timezone, address_line1, is_active, location_type, status, slug FROM restaurants WHERE os_scope_id IS NOT NULL ORDER BY os_scope_id")->fetchAll(PDO::FETCH_ASSOC);
ok(count($biz) === 2 && $biz[0]['name'] === 'Downtown Studio' && $biz[0]['timezone'] === 'America/New_York' && $biz[1]['address_line1'] === 'Terminal B', 'Downtown Studio and Airport Clinic, with the kernel\'s name, time zone and address');
ok($biz[0]['location_type'] === 'professional' && $biz[0]['status'] === 'active' && $biz[0]['slug'] === 'downtown-studio', 'a professional business, active, with a slug from the name');
[$down, $air] = [(int)$biz[0]['id'], (int)$biz[1]['id']];
$seed = fn($rid) => (int)$pdo->query("SELECT count(*) FROM settings WHERE restaurant_id = $rid")->fetchColumn();
ok($seed($down) === 15 && (int)$pdo->query("SELECT count(*) FROM professional_profiles WHERE restaurant_id = $down")->fetchColumn() === 0, 'seeded like a sign-up (15 settings), no profile until an admin saves Settings');
ok((int)$pdo->query("SELECT count(*) FROM user_restaurants WHERE source = 'os'")->fetchColumn() === 0, 'access for members not signed on yet is ignored (never auto-created)');
sync_with(['scopes' => $fx['scopes'], 'feed' => $fx['feed']]);
ok((int)$pdo->query("SELECT count(*) FROM restaurants WHERE os_scope_id IS NOT NULL")->fetchColumn() === 2, 'a second sync creates nothing new');

echo "1. Every other way in goes to the launcher\n";
foreach (['/login.php', '/register.php', '/forgot-password.php', '/reset-password.php', '/google-callback.php?code=x'] as $p) {
    $r = req('GET', $p); ok($r['code'] === 302 && location($r) === $L, "$p → launcher");
}
foreach (['/partials/auth/login.php', '/partials/auth/register.php', '/partials/auth/accept-invite.php', '/partials/auth/forgot-password.php', '/partials/auth/reset-password.php', '/partials/auth/google-complete.php'] as $p) {
    $r = req('POST', $p, ['headers' => ['HX-Request: true'], 'form' => ['email' => 'owner@example.invalid', 'password' => 'Test1234Aa']]);
    ok($r['code'] === 401 && str_contains($r['headers'], "HX-Redirect: $L"), "$p (HTMX) → launcher");
}
$r = req('GET', '/app.php'); ok($r['code'] === 302 && location($r) === $L, 'no session: /app.php → launcher');
$r = req('POST', '/api/v1/auth.php?action=login', ['json' => ['email' => 'owner@example.invalid', 'password' => 'Test1234Aa']]);
ok($r['code'] === 403, "API password login: {$r['code']}");
ok(req('GET', '/sso.php')['code'] === 403, '/sso with no token: the one refusal page');

echo "2. First sign-on of an existing user links them by email, once\n";
$pdo->exec("UPDATE users SET os_member_id = NULL WHERE os_member_id IS NOT NULL");
$url = mint_handoff(1, $fx['claims']['1'], 102);
$j = jar();
$r = req('GET', $url, ['jar' => $j]);
ok($r['code'] === 302 && location($r) === '/app.php', "hand-off accepted ({$r['code']})");
$u = $pdo->query("SELECT id, os_member_id, role, is_platform_admin, first_name, last_name FROM users WHERE email = 'owner@example.invalid'")->fetch(PDO::FETCH_ASSOC);
ok((int)$u['id'] === 1 && (int)$u['os_member_id'] === 1, 'the existing user 1 is linked to member 1 (not a new user)');
ok($u['role'] === 'super-admin' && (int)$u['is_platform_admin'] === 1 && $u['first_name'] === 'Owner' && $u['last_name'] === 'Person', 'super-admin and name from the claims');
$app = req('GET', '/app.php', ['jar' => $j]);
ok($app['code'] === 200 && str_contains($app['body'], 'id="current-restaurant-name">Airport Clinic<'), 'the chosen business (scope 102, Airport Clinic) is open');
ok(substr_count($app['body'], 'id="restaurant-switch-') === 2, 'the switcher lists the two held businesses, not every business (Test Studio is not held)');
ok(str_contains($app['body'], 'nav-professional-dashboard'), 'the professional sidebar');
ok(req('GET', $url)['code'] === 403, 'the same URL again: refused (replay)');
ok(req('GET', mint_handoff(1, $fx['claims']['1'], null, 'hr'))['code'] === 403, 'a token for another application: refused');
ok(req('GET', mint_handoff(1, $fx['claims']['1'], null, APP, -5))['code'] === 403, 'an expired token: refused');
ok(req('GET', mint_handoff(1, $fx['claims']['1'], null, APP, 60, str_repeat('ab', 32)))['code'] === 403, 'a token signed with another key: refused');
$tamper = mint_handoff(1, $fx['claims']['1']); $tamper = preg_replace('/claims=([^&.]+)/', 'claims=x$1', $tamper);
ok(req('GET', $tamper)['code'] === 403, 'tampered claims: refused');
$other = $fx['claims']['26']; $other['email'] = 'owner@example.invalid';
ok(req('GET', mint_handoff(28, $other))['code'] === 403, 'a second member with the same email: refused, not linked to user 1');
ok((int)$pdo->query("SELECT count(*) FROM users WHERE os_member_id = 28")->fetchColumn() === 0, 'and no user was made for them');
ok(str_contains((string)$pdo->query("SELECT description FROM activity_log WHERE action = 'sign_on_refused' ORDER BY id DESC LIMIT 1")->fetchColumn(), 'email-in-use'), 'the reason is logged, not shown');

echo "3. Scopes: a held business opens; another is refused\n";
$jp = jar();
$r = req('GET', mint_handoff(26, $fx['claims']['26'], 101), ['jar' => $jp]);   // asks for Downtown, holds only Airport
$pu = $pdo->query("SELECT id FROM users WHERE os_member_id = 26")->fetchColumn();
ok($r['code'] === 302 && $pu, 'Priya signs on (a new user, created for member 26)');
$app = req('GET', '/app.php', ['jar' => $jp]);
ok(str_contains($app['body'], 'id="page-title">Airport Clinic') && substr_count($app['body'], 'id="restaurant-switch-') === 0, 'asked for Downtown, which she does not hold: the session opens at Airport Clinic, and with one business held there is no switcher');
preg_match('/name="csrf-token" content="([^"]+)"/', $app['body'], $m);
$r = req('POST', '/partials/auth/switch-restaurant.php', ['jar' => $jp, 'form' => ['restaurant_id' => $down, 'csrf_token' => $m[1] ?? '']]);
ok($r['code'] === 403, 'switching to Downtown: 403');
$pdo->prepare("INSERT INTO user_restaurants (user_id, restaurant_id, role, source) VALUES (?, ?, 'admin', 'local') ON CONFLICT DO NOTHING")->execute([$pu, $down]);
$r = req('POST', '/partials/auth/switch-restaurant.php', ['jar' => $jp, 'form' => ['restaurant_id' => $down, 'csrf_token' => $m[1] ?? '']]);
ok($r['code'] === 403, 'even with a local membership row at Downtown: 403 (only the kernel\'s grants count)');
$pdo->prepare("DELETE FROM user_restaurants WHERE user_id = ? AND restaurant_id = ? AND source = 'local'")->execute([$pu, $down]);
ok(req('GET', '/partials/settings/users.php', ['jar' => $jp])['code'] === 403, 'Staff role: the admin-only staff screen answers 403');
ok(req('GET', '/partials/professional/dashboard.php', ['jar' => $jp])['code'] === 200 && req('GET', '/partials/todos/list.php', ['jar' => $jp])['code'] === 200 && req('GET', '/partials/professional/calendar.php?view=week', ['jar' => $jp])['code'] === 403, 'Staff role: the dashboard and to-dos open; the calendar (manager+) answers 403, as the product always did');
$jm = jar();
req('GET', mint_handoff(27, $fx['claims']['27'], 101), ['jar' => $jm]);
$app = req('GET', '/app.php', ['jar' => $jm]);
ok(str_contains($app['body'], 'id="current-restaurant-name">Downtown Studio<') && substr_count($app['body'], 'id="restaurant-switch-') === 2, 'Marco, two scopes: the chosen one (Downtown Studio) opens, the switcher lists two');
ok(str_contains($app['body'], '<span class="fs-11 text-white-50">Manager</span>'), 'as manager at Downtown (the highest of his roles there)');

echo "4. People and businesses are managed in the kernel\n";
$r = req('GET', '/partials/settings/users.php', ['jar' => $j]);
ok(str_contains($r['body'], 'users-os-managed') && !str_contains($r['body'], 'users-add-btn') && !str_contains($r['body'], 'toggle-user.php'), 'staff screen: read-only, says so, no add/edit/deactivate');
$t = page_csrf($j);
$r = req('POST', '/partials/settings/save-user.php', ['jar' => $j, 'headers' => ['HX-Request: true'], 'form' => ['csrf_token' => $t, 'first_name' => 'A', 'last_name' => 'B', 'email' => 'x@example.invalid', 'role' => 'user']]);
ok($r['code'] === 403 && str_contains($r['body'], 'Managed in the operating system'), 'saving a staff member: refused, in those words');
ok(str_contains(req('GET', '/partials/settings/user-form.php', ['jar' => $j])['body'], 'user-form-os-managed'), 'the staff form: the notice instead');
$r = req('GET', '/partials/platform/restaurants.php', ['jar' => $j]);
ok(str_contains($r['body'], 'platform-restaurants-os-managed') && !str_contains($r['body'], 'platform-add-restaurant-btn'), 'businesses: no Add, says so');
$r = req('POST', '/partials/platform/save-restaurant.php', ['jar' => $j, 'headers' => ['HX-Request: true'], 'form' => ['csrf_token' => $t, 'name' => 'New', 'slug' => 'new-x', 'timezone' => 'UTC']]);
ok($r['code'] === 403 && str_contains($r['body'], 'Managed in the operating system'), 'creating a business: refused');
$r = req('GET', '/partials/platform/users.php', ['jar' => $j]);
ok(str_contains($r['body'], 'platform-users-os-managed') && !str_contains($r['body'], 'platform-add-user-btn') && !str_contains($r['body'], 'toggle-platform-user.php'), 'platform users: read-only');
ok(req('POST', '/partials/platform/toggle-platform-user.php', ['jar' => $j, 'form' => ['csrf_token' => $t, 'user_id' => (int)$pu]])['code'] === 403, 'deactivating a platform user: refused');
$key = api_create_key(1, $air, 'os proof');
$r = req('POST', '/api/v1/staff.php', ['headers' => ["Authorization: Bearer $key"], 'json' => ['first_name' => 'A', 'last_name' => 'B', 'email' => 'y@example.invalid', 'role' => 'user']]);
ok($r['code'] === 403 && str_contains($r['body'], 'MANAGED_BY_OS'), "adding staff through the API: {$r['code']} MANAGED_BY_OS");
ok(req('GET', '/api/v1/staff.php', ['headers' => ["Authorization: Bearer $key"]])['code'] === 200, 'reading staff through the API: still answers');
ok(req('GET', '/api/v1/services.php', ['headers' => ["Authorization: Bearer $key"]])['code'] === 200, "a linked, admitted user's API key works");
$pdo->exec("UPDATE users SET os_member_id = NULL WHERE id = 1");
ok(req('GET', '/api/v1/services.php', ['headers' => ["Authorization: Bearer $key"]])['code'] === 401, 'an unlinked user\'s key: 401');
$pdo->exec("UPDATE users SET os_member_id = 1 WHERE id = 1");

echo "5. Revocation: the next request lands on the launcher; the API key stops\n";
$pkey = api_create_key((int)$pu, $air, 'priya');
ok(req('GET', '/api/v1/services.php', ['headers' => ["Authorization: Bearer $pkey"]])['code'] === 200, "Priya's key works before");
$feed = $fx['feed']; $feed['access'] = [['member_id' => 26, 'role' => null, 'roles' => [], 'capability' => null, 'scopes' => []]];
$out = sync_with(['feed' => $feed]);
ok(str_contains($out, '"sessions_ended":1'), "revocation sync: $out");
$r = req('GET', '/app.php', ['jar' => $jp]);
ok($r['code'] === 302 && location($r) === $L, 'her next request → launcher');
ok($pdo->query("SELECT ended_by FROM member_sessions WHERE member_id = 26 ORDER BY created_at DESC LIMIT 1")->fetchColumn() === 'directory', "member_sessions: ended_by = 'directory'");
ok(req('GET', '/api/v1/services.php', ['headers' => ["Authorization: Bearer $pkey"]])['code'] === 401, 'her API key: 401');
$feed['access'] = []; $feed['members'] = [['id' => 27, 'display_name' => 'Marco Ruiz', 'email' => 'marco@example.invalid', 'business_role' => 'user', 'status' => 'suspended']];
sync_with(['feed' => $feed]);
ok(req('GET', '/app.php', ['jar' => $jm])['code'] === 302, 'a member suspended in the kernel: next request → launcher');
ok((int)$pdo->query("SELECT is_active FROM users WHERE os_member_id = 27")->fetchColumn() === 0, 'and the user is inactive here');

echo "6. A site added becomes a business; a site removed closes with its data\n";
$pdo->exec("INSERT INTO professional_clients (restaurant_id, first_name, last_name, phone, email) VALUES ($air, 'Keep', 'Me', '+15555550111', 'keep@example.invalid')");
$clientsBefore = (int)$pdo->query("SELECT count(*) FROM professional_clients WHERE restaurant_id = $air")->fetchColumn();
$feed = $fx['feed']; $feed['access'] = [];
$feed['scopes'] = [['scope_id' => 103, 'kind' => 'location', 'location_id' => 12, 'department_id' => null, 'name' => 'Harbor Practice', 'address' => '9 Pier Rd', 'timezone' => 'America/Los_Angeles', 'removed_at' => null, 'updated_at' => '2026-02-01T00:00:00Z'],
                   array_merge($fx['scopes'][1], ['removed_at' => '2026-02-01T00:00:00Z'])];
sync_with(['feed' => $feed]);
$h = $pdo->query("SELECT id, slug, timezone, is_active, location_type FROM restaurants WHERE os_scope_id = 103")->fetch(PDO::FETCH_ASSOC);
ok($h && str_starts_with($h['slug'], 'harbor-practice') && $h['timezone'] === 'America/Los_Angeles' && $h['location_type'] === 'professional' && $seed((int)$h['id']) === 15, 'Harbor Practice created, seeded, in its time zone');
ok((int)$pdo->query("SELECT is_active FROM restaurants WHERE id = $air")->fetchColumn() === 0, 'Airport Clinic closed');
ok((int)$pdo->query("SELECT count(*) FROM professional_clients WHERE restaurant_id = $air")->fetchColumn() === $clientsBefore, 'its clients kept');
$app = req('GET', '/app.php', ['jar' => $j]);
ok(str_contains($app['body'], 'id="page-title">Downtown Studio'), 'the owner, who was at Airport Clinic, is moved to a business still held (Downtown Studio)');
ok(req('GET', '/api/v1/services.php', ['headers' => ["Authorization: Bearer $key"]])['code'] === 401, "the owner's key for Airport Clinic: 401");

echo "7. Sign-out\n";
ok(req('POST', '/sso/logout.php', ['form' => ['notice' => 'garbage']])['code'] === 204, 'a bad notice: 204');
ok(req('GET', '/app.php', ['jar' => $j])['code'] === 200, 'and changes nothing');
ok(req('POST', '/sso/logout.php', ['form' => ['notice' => logout_notice(1, 'hr')]])['code'] === 204 && req('GET', '/app.php', ['jar' => $j])['code'] === 200, "another application's notice: 204, changes nothing");
ok(req('POST', '/sso/logout.php', ['raw' => json_encode(['notice' => logout_notice(1)]), 'headers' => ['Content-Type: application/json']])['code'] === 204, 'the kernel\'s notice (JSON): 204');
$r = req('GET', '/app.php', ['jar' => $j]);
ok($r['code'] === 302 && location($r) === $L, "every session of member 1 ended: next request → launcher");
$j2 = jar(); req('GET', mint_handoff(1, $fx['claims']['1'], 101), ['jar' => $j2]);
$r = req('GET', '/logout.php', ['jar' => $j2]);
ok(location($r) === $L, 'logging out here → launcher');
ok($pdo->query("SELECT ended_by FROM member_sessions WHERE member_id = 1 ORDER BY created_at DESC LIMIT 1")->fetchColumn() === 'member', "ended_by = 'member'");

echo "8. app_roles for the kernel's token only, on /api/mcp/kernel.php\n";
$mcp = fn($tok, $body) => req('POST', '/api/mcp/kernel.php', ['headers' => ["Authorization: Bearer $tok"], 'json' => $body]);
$kt = kernel_token();
$r = $mcp($kt, ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize', 'params' => ['protocolVersion' => '2025-06-18']]);
ok($r['code'] === 200 && str_contains($r['body'], 'serverInfo'), 'initialize as the kernel');
$r = json_decode($mcp($kt, ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list'])['body'], true);
ok(array_column($r['result']['tools'] ?? [], 'name') === ['app_roles'], 'tools/list shows app_roles alone');
$r = json_decode($mcp($kt, ['jsonrpc' => '2.0', 'id' => 3, 'method' => 'tools/call', 'params' => ['name' => 'app_roles', 'arguments' => new stdClass()]])['body'], true);
$doc = $r['result']['structuredContent'] ?? [];
ok(($doc['schema'] ?? '') === 'os.app-roles/1' && array_column($doc['roles'], 'key') === ['admin', 'manager', 'user'], 'app_roles: os.app-roles/1 with admin, manager, user');
ok(array_column($doc['roles'], 'capability') === ['admin', 'write', 'write'] && array_column($doc['roles'], 'is_admin') === [true, false, false], 'capabilities admin/write/write, admin the one is_admin');
ok(array_column($doc['rights'], 'key') === ['desk.view', 'appointments.manage', 'business.admin'] && array_column($doc['roles'], 'rights') === [['desk.view', 'appointments.manage', 'business.admin'], ['desk.view', 'appointments.manage'], ['desk.view']], 'the three rights, each role\'s set as its guards allow');
$r = json_decode($mcp($kt, ['jsonrpc' => '2.0', 'id' => 4, 'method' => 'tools/call', 'params' => ['name' => 'list_services', 'arguments' => []]])['body'], true);
ok(isset($r['error']), 'any other tool: refused');
ok($mcp(kernel_token('hr'), ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize'])['code'] === 401, "a kernel token for another application: 401");
ok($mcp('proof-mcp-key-studio', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize'])['code'] === 401, "a business's MCP key: 401 (not this server's credential)");
ok(req('POST', '/api/mcp/pro.php', ['headers' => ["Authorization: Bearer $kt"], 'json' => ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize']])['code'] === 401, "the kernel token on the receptionist's server: 401");
file_put_contents(sys_get_temp_dir() . '/pro-appointments-app-roles.json', json_encode($doc));

echo "9. The application switcher (the partial from a fixture; the kernel's feed is its judgement)\n";
$feedDoc = ['launcher_url' => 'https://app.example.invalid/', 'os_url' => 'https://os.example.invalid/', 'applications' => [
    ['id' => 7, 'key' => 'helpdesk', 'name' => 'Help Desk', 'icon' => 'feather-life-buoy', 'current' => false, 'launch_path' => '/launch/7', 'scopes' => []],
    ['id' => 9, 'key' => APP, 'name' => 'Pro Appointments', 'icon' => 'feather-calendar', 'current' => true, 'launch_path' => '/launch/9', 'scopes' => [['id' => 101, 'name' => 'Downtown Studio', 'role' => 'admin', 'launch_path' => '/launch/9?scope=101']]],
    ['id' => 3, 'key' => 'hr', 'name' => 'HR', 'icon' => 'feather-users', 'current' => false, 'launch_path' => '/launch/3', 'scopes' => []],
]];
ob_start(); $feed = $feedDoc; include dirname(__DIR__, 2) . '/html/partials/shared/app-switcher.php'; $html = ob_get_clean();
ok(str_contains($html, 'id="header-helpdesk-btn"') && str_contains($html, 'https://app.example.invalid/launch/7'), 'the Helpdesk button launches the Help Desk');
ok(str_contains($html, 'id="header-apps-item-9-101"') && str_contains($html, 'aria-current="page"') && !str_contains($html, 'href="https://app.example.invalid/launch/9?scope=101"'), 'this application (its site) is marked current, not linked');
ok(str_contains($html, 'href="https://app.example.invalid/launch/3"') && str_contains($html, 'id="header-apps-launcher"') && str_contains($html, 'https://os.example.invalid/'), 'HR links, the launcher and the operating system at the foot');
ob_start(); $feed = null; $_SESSION = []; include dirname(__DIR__, 2) . '/html/partials/shared/app-switcher.php'; $none = ob_get_clean();
ok(trim($none) === '', 'without a kernel answer the header shows nothing');

echo "10. Health\n";
$r = req('GET', '/api/v1/health.php');
ok($r['code'] === 200 && json_decode($r['body'], true) == ['ok' => true, 'application' => APP, 'version' => '1.0.0', 'database' => 'ok'], 'health: ' . $r['body']);
$pdo->exec("DELETE FROM api_tokens WHERE device_name IN ('os proof', 'priya')");
finish();
