<?php
/**
 * Staff invitations: a new person added without a password is '!INVITED' and sets their own password on the
 * registration page with the one-time code issued here (security fix S1). Only the code's SHA-256 is stored;
 * the code is shown to the admin once and is valid for 14 days.
 */
require_once __DIR__ . '/db.php';

/** A new one-time invitation code for $userId. */
function issue_invite_code(PDO $pdo, int $userId): string
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $code = '';
    for ($i = 0; $i < 10; $i++) $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    $pdo->prepare("UPDATE users SET invite_code_hash = ?, invite_expires_at = NOW() + INTERVAL '14 days' WHERE id = ?")
        ->execute([hash('sha256', $code), $userId]);
    return $code;
}

/** Does $code match the user's unexpired invitation? */
function invite_code_valid(array $user, string $code): bool
{
    $code = strtoupper(preg_replace('/[\s-]+/', '', $code));
    return $code !== '' && !empty($user['invite_code_hash'])
        && hash_equals($user['invite_code_hash'], hash('sha256', $code))
        && !empty($user['invite_expires_at']) && strtotime($user['invite_expires_at']) > time();
}
