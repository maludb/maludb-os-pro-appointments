<?php
require_once '/var/www/helpers/auth.php';
require_once '/var/www/helpers/retell.php';
require_once '/var/www/models/Session.php';
require_once '/var/www/models/Prospect.php';
require_once '/var/www/models/Product.php';
requireAuth();

$orgId = currentOrgId();
$prospectId = (int)($_POST['prospect_id'] ?? 0);
$productId = (int)($_POST['product_id'] ?? 0);
$callType = $_POST['call_type'] ?? 'cold_call';

$prospectModel = new Prospect();
$productModel = new Product();
$sessionModel = new Session();

$prospect = $prospectModel->getById($prospectId, $orgId);
$product = $productModel->getById($productId, $orgId);

if (!$prospect || !$product) {
    echo '<div class="alert alert-danger">Prospect or product not found.</div>';
    exit;
}

$sessionDbId = $sessionModel->create([
    'user_id' => currentUserId(),
    'org_id' => $orgId,
    'session_id' => bin2hex(random_bytes(6)),
    'prospect_id' => $prospectId,
    'product_id' => $productId,
    'call_type' => $callType,
    'transcript' => '',
    'quality_score' => null,
    'duration_ms' => 0,
]);

$agentId = $prospect['agent_id'] ?? '';
$prompt = assembleAgentPrompt($prospectId, $productId, $orgId, $callType);

$accessToken = '';
if (!empty($agentId) && !empty(getRetellApiKey())) {
    updateRetellAgent($agentId, ['agent_prompt' => $prompt]);
    $webCall = createRetellWebCall($agentId, ['session_id' => $sessionDbId]);
    $accessToken = $webCall['access_token'] ?? '';
}
?>

<div class="container-fluid" id="call-interface">
  <div class="row justify-content-center">
    <div class="col-lg-8">
      <div class="card mb-4" id="call-prospect-card">
        <div class="card-body d-flex align-items-center gap-3">
          <img src="<?= htmlspecialchars($prospect['image']) ?>" class="rounded-circle" width="60" height="60" alt="">
          <div>
            <h5 class="mb-0"><?= htmlspecialchars($prospect['prospect_name']) ?></h5>
            <p class="text-muted mb-0"><?= htmlspecialchars($prospect['prospect_role']) ?> at <?= htmlspecialchars($prospect['name']) ?></p>
            <span class="badge bg-secondary"><?= htmlspecialchars(str_replace('_',' ', $callType)) ?></span>
          </div>
        </div>
      </div>

      <div class="card text-center" id="call-card">
        <div class="card-body py-5">
          <div id="call-status" class="mb-3">
            <div class="spinner-grow text-primary" role="status" id="call-connecting"></div>
            <p class="text-muted">Connecting...</p>
          </div>
          <h2 id="call-timer" class="font-monospace mb-4" style="display:none;">00:00</h2>
          <div class="card mt-4" id="transcript-card" style="display:none;">
            <div class="card-header"><h6 class="mb-0"><i class="bi bi-chat-text me-2"></i>Live Transcript</h6></div>
            <div class="card-body" id="live-transcript" style="max-height:300px; overflow-y:auto;"></div>
          </div>
          <button id="end-call-btn" class="btn btn-danger btn-lg rounded-pill px-5 mt-3" onclick="endCall()" style="display:none;">
            <i class="bi bi-telephone-x me-2"></i> End Call
          </button>
        </div>
      </div>
    </div>
  </div>
</div>
<input type="hidden" id="retell-access-token" value="<?= htmlspecialchars($accessToken) ?>">
<input type="hidden" id="session-db-id" value="<?= $sessionDbId ?>">
<input type="hidden" id="prospect-id" value="<?= $prospectId ?>">
<input type="hidden" id="product-id" value="<?= $productId ?>">
