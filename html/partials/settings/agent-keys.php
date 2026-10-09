<?php
/**
 * Agent API Keys — long-lived REST/MCP credentials for AI agents.
 * Each key acts as the user who created it, on the current business only.
 * Also included by save-agent-key.php, which sets $newAgentKey after creating one.
 */
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/csrf.php';

requireAdmin();

$restaurantId = currentRestaurantId();

$keysStmt = db()->prepare(
    "SELECT t.id, t.device_name, t.created_at, t.expires_at, t.last_used_at,
            u.first_name, u.last_name
     FROM api_tokens t
     JOIN users u ON u.id = t.user_id
     WHERE t.restaurant_id = ? AND t.expires_at > NOW()
     ORDER BY t.created_at DESC"
);
$keysStmt->execute([$restaurantId]);
$agentKeys = $keysStmt->fetchAll();

$fmtDate = function (?string $value): string {
    return $value ? date('M j, Y', strtotime($value)) : '—';
};
?>
<div class="card mb-4" id="integrations-agent-keys-card">
    <div class="card-header" id="integrations-agent-keys-header">
        <h6 class="mb-0" id="integrations-agent-keys-title">Agent API Keys</h6>
    </div>
    <div class="card-body" id="integrations-agent-keys-body">
        <p class="text-muted" id="integrations-agent-keys-desc">
            Keys let an AI agent manage this business through the REST API (<code>/api/v1/</code>) and the
            management MCP server (<code>/api/mcp/manage.php</code>). A key acts as the person who created it,
            with their role, on this business only. It is valid for one year.
        </p>

        <?php if (!empty($newAgentKey)): ?>
        <div class="alert alert-success" id="integrations-agent-keys-new">
            <div class="fw-semibold mb-2" id="integrations-agent-keys-new-title">Copy this key now — it will not be shown again.</div>
            <div class="input-group" id="integrations-agent-keys-new-group">
                <input type="text" class="form-control font-monospace" id="integrations-agent-keys-new-value"
                       value="<?php echo htmlspecialchars($newAgentKey); ?>" readonly>
                <button type="button" class="btn btn-outline-secondary" id="integrations-agent-keys-copy"
                        onclick="navigator.clipboard.writeText(document.getElementById('integrations-agent-keys-new-value').value);this.innerHTML='<i class=\'feather-check\'></i> Copied';">
                    <i class="feather-copy"></i> Copy
                </button>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($agentKeyError)): ?>
        <div class="alert alert-danger" id="integrations-agent-keys-error"><?php echo htmlspecialchars($agentKeyError); ?></div>
        <?php endif; ?>

        <form hx-post="/partials/settings/save-agent-key.php"
              hx-target="#integrations-agent-keys"
              hx-swap="innerHTML"
              class="row g-2 mb-3" id="integrations-agent-keys-form">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="create">
            <div class="col-md-8" id="integrations-agent-keys-name-wrap">
                <input type="text" class="form-control" id="integrations-agent-keys-name" name="name"
                       maxlength="100" placeholder="Key name, e.g. Claude scheduling agent" required>
            </div>
            <div class="col-md-4" id="integrations-agent-keys-create-wrap">
                <button type="submit" class="btn btn-primary w-100" id="integrations-agent-keys-create-btn">
                    <i class="feather-plus me-1"></i> Create Key
                </button>
            </div>
        </form>

        <?php if (empty($agentKeys)): ?>
        <p class="text-muted mb-0" id="integrations-agent-keys-empty">No active keys.</p>
        <?php else: ?>
        <div class="table-responsive" id="integrations-agent-keys-table-wrap">
            <table class="table table-sm align-middle mb-0" id="integrations-agent-keys-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Acts as</th>
                        <th>Created</th>
                        <th>Last used</th>
                        <th>Expires</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($agentKeys as $key): ?>
                    <tr id="integrations-agent-key-row-<?php echo (int)$key['id']; ?>">
                        <td><?php echo htmlspecialchars($key['device_name'] ?: 'Unnamed'); ?></td>
                        <td><?php echo htmlspecialchars(trim($key['first_name'] . ' ' . $key['last_name'])); ?></td>
                        <td><?php echo $fmtDate($key['created_at']); ?></td>
                        <td><?php echo $fmtDate($key['last_used_at']); ?></td>
                        <td><?php echo $fmtDate($key['expires_at']); ?></td>
                        <td class="text-end">
                            <button class="btn btn-sm btn-outline-danger" id="integrations-agent-key-revoke-<?php echo (int)$key['id']; ?>"
                                    hx-post="/partials/settings/save-agent-key.php"
                                    hx-vals='{"action": "revoke", "id": "<?php echo (int)$key['id']; ?>", "csrf_token": "<?php echo generate_csrf_token(); ?>"}'
                                    hx-target="#integrations-agent-keys"
                                    hx-swap="innerHTML"
                                    hx-confirm="Revoke this key? Agents using it will stop working immediately.">
                                Revoke
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>
