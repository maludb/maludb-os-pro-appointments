<?php
/**
 * The application switcher — the Helpdesk button and the dropdown of the person's applications in every
 * application's header (K31, 2026-10-09; the kernel's docs/build-specs/kernel-app-switcher.md; the integration
 * plugin's sign-on-and-directory.md §8, code php-sign-on-kit.md §8, fitted to this adopted application:
 * os_enabled(), os_launcher_url(), kernel_call() from helpers/os.php, the member id from the session).
 * Rendered by html/partials/shared/app-switcher.php. Standalone (OS_ENABLED off) it renders nothing.
 */
require_once __DIR__ . '/os.php';

/** The kernel's id of the signed-in person, or 0 when there is none (standalone, anonymous, an agent's token). */
function os_switcher_member_id(): int
{
    return session_status() === PHP_SESSION_ACTIVE ? (int)($_SESSION['os_member_id'] ?? 0) : 0;
}

/** The launcher's address (OS_LAUNCHER_URL, the installer's — scheme and all), with a trailing slash. */
function os_switcher_launcher_url(): string
{
    $url = (string)app_config('OS_LAUNCHER_URL', '');
    return $url === '' ? '' : rtrim($url, '/') . '/';
}

/** A launch path from the kernel's feed (`/launch/<id>`, `?scope=<id>`) on the launcher's address. */
function os_launch_href(string $path): string
{
    return os_switcher_launcher_url() . ltrim($path, '/');
}

/**
 * The applications the signed-in person may open, as the kernel's launcher would list them (GET /api/v1/apps/mine.php
 * as this person): {launcher_url, os_url, applications[{id, key, name, icon, business_area, current, launch_path,
 * scopes[{id, name, role, launch_path}]}]}. Cached in the session for five minutes (a fresh hand-off starts a fresh
 * session, so a new grant shows at the next sign-on at the latest); a kernel that does not answer leaves the last
 * answer in place and is asked again after a minute. Null standalone, for a person the kernel does not know, or
 * before the first answer.
 */
function os_my_applications(bool $refresh = false): ?array
{
    if (!os_enabled()) return null;
    $memberId = os_switcher_member_id();
    if ($memberId <= 0) return null;
    $cached = $_SESSION['os_apps'] ?? null;
    if (is_array($cached) && (int)($cached['member'] ?? 0) === $memberId) {
        $fresh = ($cached['at'] ?? 0) > time() - (($cached['feed'] ?? null) === null ? 60 : 300);
        if (!$refresh && $fresh) return $cached['feed'];
    } else {
        $cached = null;
    }
    $answer = kernel_call('GET', '/api/v1/apps/mine.php', null, ['X-Acting-Member: ' . $memberId], 5);
    $feed = $answer !== null && ($answer['status'] ?? 0) === 200 && is_array($answer['body'] ?? null) ? ($answer['body']['data'] ?? $answer['body']) : null;
    if (!is_array($feed) || !isset($feed['applications'])) {
        $_SESSION['os_apps'] = ['member' => $memberId, 'at' => time(), 'feed' => $cached['feed'] ?? null];
        return $cached['feed'] ?? null;
    }
    $_SESSION['os_apps'] = ['member' => $memberId, 'at' => time(), 'feed' => $feed];
    return $feed;
}
