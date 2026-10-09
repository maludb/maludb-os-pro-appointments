<?php
require_once '/var/www/helpers/auth.php';
require_once '/var/www/models/Prospect.php';
requireManager();

$orgId = currentOrgId();
$prospectModel = new Prospect();

$method = strtoupper($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] ?? $_SERVER['REQUEST_METHOD']);

$industries = ['Technology','Healthcare','Finance','Manufacturing','Retail','Education','Other'];
$personalities = [
    'Busy & skeptical',
    'Analytical & detail-oriented',
    'Friendly but indecisive',
    'Aggressive negotiator',
    'Warm & relationship-driven',
    'Technical & no-nonsense',
    'Budget-conscious & cautious'
];
$companySizes = ['1-10','11-50','51-200','201-1000','1000+'];
$callTypeOptions = [
    'cold_call' => 'Cold Call',
    'discovery' => 'Discovery',
    'demo' => 'Demo',
    'objection_handling' => 'Objection Handling',
    'closing' => 'Closing',
    'renewal' => 'Renewal'
];

$errors = [];
$prospectId = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

if ($method === 'POST') {
    $data = [
        'name' => trim($_POST['name'] ?? ''),
        'business_type' => trim($_POST['business_type'] ?? ''),
        'stub' => trim($_POST['stub'] ?? ''),
        'description' => trim($_POST['description'] ?? ''),
        'members' => (int)($_POST['members'] ?? 0),
        'monthly_spend_limit' => (int)($_POST['monthly_spend_limit'] ?? 0),
        'monthly_spend' => (int)($_POST['monthly_spend'] ?? 0),
        'image' => trim($_POST['image'] ?? ''),
        'agent_id' => trim($_POST['agent_id'] ?? ''),
        'prospect_name' => trim($_POST['prospect_name'] ?? ''),
        'prospect_role' => trim($_POST['prospect_role'] ?? ''),
        'prospect_personality' => trim($_POST['prospect_personality'] ?? ''),
        'prospect_talkativeness' => trim($_POST['prospect_talkativeness'] ?? 'Low'),
        'prosepect_communication_style' => trim($_POST['prosepect_communication_style'] ?? ''),
        'industry' => trim($_POST['industry'] ?? ''),
        'company_size' => trim($_POST['company_size'] ?? ''),
        'current_solution' => trim($_POST['current_solution'] ?? ''),
        'tools_used' => trim($_POST['tools_used'] ?? ''),
        'recent_events' => trim($_POST['recent_events'] ?? ''),
        'lead_volume' => trim($_POST['lead_volume'] ?? ''),
        'difficulty_level' => trim($_POST['difficulty_level'] ?? 'intermediate'),
    ];

    $selectedCallTypes = $_POST['call_types'] ?? [];
    $selectedCallTypes = array_intersect(array_keys($callTypeOptions), $selectedCallTypes);
    $data['call_types'] = implode(',', $selectedCallTypes ?: array_keys($callTypeOptions));

    if ($data['name'] === '') {
        $errors[] = 'Company Name is required';
    }
    if ($data['prospect_name'] === '') {
        $errors[] = 'Contact Name is required';
    }
    if ($data['prospect_role'] === '') {
        $errors[] = 'Job Title/Role is required';
    }
    if ($data['prospect_personality'] === '') {
        $errors[] = 'Personality is required';
    }

    if (empty($errors)) {
        if ($prospectId > 0) {
            $prospectModel->update($prospectId, $data, $orgId);
        } else {
            $prospectId = $prospectModel->create($data);
        }
        header('HX-Trigger: {"toast": {"message": "Prospect saved", "type": "success"}}');
        header('HX-Redirect: /partials/prospects/view.php?id=' . $prospectId);
        exit;
    }
}

$prospect = null;
if ($prospectId > 0) {
    $prospect = $prospectModel->getById($prospectId, $orgId);
}

$isEdit = $prospect !== null;
$values = $prospect ?? [
    'name' => '',
    'business_type' => '',
    'stub' => '',
    'description' => '',
    'members' => 0,
    'monthly_spend_limit' => 0,
    'monthly_spend' => 0,
    'image' => 'https://images.skilliks.ai/pending.php',
    'agent_id' => '',
    'prospect_name' => '',
    'prospect_role' => '',
    'prospect_personality' => 'Busy & skeptical',
    'prospect_talkativeness' => 'Low',
    'prosepect_communication_style' => '',
    'industry' => '',
    'company_size' => '',
    'current_solution' => '',
    'tools_used' => '',
    'recent_events' => '',
    'lead_volume' => '',
    'difficulty_level' => 'intermediate',
    'call_types' => 'cold_call,discovery,demo,objection_handling,closing,renewal',
];

$selectedCallTypes = array_filter(explode(',', $values['call_types'] ?? ''), fn($v) => $v !== '');
$selectedCallTypes = $selectedCallTypes ?: array_keys($callTypeOptions);
?>

<div class="container-fluid" id="prospect-form-page">
  <div class="d-flex justify-content-between align-items-center mb-4" id="prospect-form-header">
    <div id="prospect-form-title-wrap">
      <h4 class="mb-1" id="prospect-form-title"><?= $isEdit ? 'Edit Prospect' : 'Add Prospect' ?></h4>
      <p class="text-muted mb-0" id="prospect-form-subtitle">Configure persona details for practice sessions.</p>
    </div>
    <div id="prospect-form-actions">
      <a href="#" class="btn btn-outline-secondary" id="prospect-form-cancel"
         hx-get="/partials/prospects/list.php" hx-target="#page-content">Back to list</a>
    </div>
  </div>

  <?php if (!empty($errors)): ?>
    <div class="alert alert-danger" id="prospect-form-errors">
      <ul class="mb-0" id="prospect-form-errors-list">
        <?php foreach ($errors as $error): ?>
          <li><?= htmlspecialchars($error) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <form id="prospect-form" hx-post="/partials/prospects/form.php" hx-target="#page-content" hx-swap="outerHTML">
    <input type="hidden" name="id" value="<?= $prospectId ?>">

    <div class="card mb-4" id="prospect-company-card">
      <div class="card-header" id="prospect-company-card-header">
        <h6 class="mb-0" id="prospect-company-title">Company Information</h6>
      </div>
      <div class="card-body" id="prospect-company-card-body">
        <div class="row g-3" id="prospect-company-row">
          <div class="col-md-6" id="prospect-name-col">
            <label class="form-label" for="prospect-name">Company Name *</label>
            <input type="text" class="form-control" id="prospect-name" name="name" required
                   value="<?= htmlspecialchars($values['name']) ?>">
          </div>
          <div class="col-md-6" id="prospect-business-type-col">
            <label class="form-label" for="prospect-business-type">Business Type</label>
            <input type="text" class="form-control" id="prospect-business-type" name="business_type"
                   value="<?= htmlspecialchars($values['business_type']) ?>">
          </div>
          <div class="col-md-4" id="prospect-industry-col">
            <label class="form-label" for="prospect-industry">Industry</label>
            <select class="form-select" id="prospect-industry" name="industry">
              <option value="">Select Industry</option>
              <?php foreach ($industries as $industry): ?>
                <option value="<?= htmlspecialchars($industry) ?>" <?= $values['industry'] === $industry ? 'selected' : '' ?>><?= htmlspecialchars($industry) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4" id="prospect-company-size-col">
            <label class="form-label" for="prospect-company-size">Company Size</label>
            <select class="form-select" id="prospect-company-size" name="company_size">
              <option value="">Select Size</option>
              <?php foreach ($companySizes as $size): ?>
                <option value="<?= htmlspecialchars($size) ?>" <?= $values['company_size'] === $size ? 'selected' : '' ?>><?= htmlspecialchars($size) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4" id="prospect-image-col">
            <label class="form-label" for="prospect-image">Prospect Avatar URL</label>
            <input type="url" class="form-control" id="prospect-image" name="image"
                   value="<?= htmlspecialchars($values['image']) ?>">
            <small class="text-muted" id="prospect-image-help">Link to an image for the persona.</small>
          </div>
          <div class="col-12" id="prospect-description-col">
            <label class="form-label" for="prospect-description">Description</label>
            <textarea class="form-control" id="prospect-description" name="description" rows="3"><?= htmlspecialchars($values['description']) ?></textarea>
          </div>
        </div>
      </div>
    </div>

    <div class="card mb-4" id="prospect-persona-card">
      <div class="card-header" id="prospect-persona-card-header">
        <h6 class="mb-0" id="prospect-persona-title">Buyer Persona</h6>
      </div>
      <div class="card-body" id="prospect-persona-card-body">
        <div class="row g-3" id="prospect-persona-row">
          <div class="col-md-4" id="prospect-contact-name-col">
            <label class="form-label" for="prospect-contact-name">Contact Name *</label>
            <input type="text" class="form-control" id="prospect-contact-name" name="prospect_name" required
                   value="<?= htmlspecialchars($values['prospect_name']) ?>">
          </div>
          <div class="col-md-4" id="prospect-contact-role-col">
            <label class="form-label" for="prospect-contact-role">Job Title/Role *</label>
            <input type="text" class="form-control" id="prospect-contact-role" name="prospect_role" required
                   value="<?= htmlspecialchars($values['prospect_role']) ?>">
          </div>
          <div class="col-md-4" id="prospect-personality-col">
            <label class="form-label" for="prospect-personality">Personality *</label>
            <select class="form-select" id="prospect-personality" name="prospect_personality" required>
              <option value="">Select</option>
              <?php foreach ($personalities as $personality): ?>
                <option value="<?= htmlspecialchars($personality) ?>" <?= $values['prospect_personality'] === $personality ? 'selected' : '' ?>><?= htmlspecialchars($personality) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4" id="prospect-talkativeness-col">
            <label class="form-label d-block" for="prospect-talk-low">Talkativeness</label>
            <div class="d-flex gap-3" id="prospect-talkativeness-options">
              <?php foreach (['Low','Medium','High'] as $level): ?>
                <div class="form-check form-check-inline" id="prospect-talk-option-<?= strtolower($level) ?>">
                  <input class="form-check-input" type="radio" name="prospect_talkativeness" id="prospect-talk-<?= strtolower($level) ?>" value="<?= $level ?>" <?= $values['prospect_talkativeness'] === $level ? 'checked' : '' ?>>
                  <label class="form-check-label" for="prospect-talk-<?= strtolower($level) ?>"><?= $level ?></label>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
          <div class="col-md-8" id="prospect-communication-style-col">
            <label class="form-label" for="prospect-communication-style">Communication Style</label>
            <textarea class="form-control" id="prospect-communication-style" name="prosepect_communication_style" rows="2"><?= htmlspecialchars($values['prosepect_communication_style']) ?></textarea>
          </div>
          <div class="col-md-6" id="prospect-current-solution-col">
            <label class="form-label" for="prospect-current-solution">Current Solution</label>
            <input type="text" class="form-control" id="prospect-current-solution" name="current_solution"
                   value="<?= htmlspecialchars($values['current_solution']) ?>">
          </div>
          <div class="col-md-6" id="prospect-tools-used-col">
            <label class="form-label" for="prospect-tools-used">Tools Used</label>
            <input type="text" class="form-control" id="prospect-tools-used" name="tools_used"
                   value="<?= htmlspecialchars($values['tools_used']) ?>">
          </div>
          <div class="col-md-6" id="prospect-recent-events-col">
            <label class="form-label" for="prospect-recent-events">Recent Events</label>
            <input type="text" class="form-control" id="prospect-recent-events" name="recent_events"
                   value="<?= htmlspecialchars($values['recent_events']) ?>">
          </div>
          <div class="col-md-6" id="prospect-lead-volume-col">
            <label class="form-label" for="prospect-lead-volume">Lead Volume</label>
            <input type="text" class="form-control" id="prospect-lead-volume" name="lead_volume"
                   value="<?= htmlspecialchars($values['lead_volume']) ?>">
          </div>
        </div>
      </div>
    </div>

    <div class="card mb-4" id="prospect-settings-card">
      <div class="card-header" id="prospect-settings-card-header">
        <h6 class="mb-0" id="prospect-settings-title">Practice Settings</h6>
      </div>
      <div class="card-body" id="prospect-settings-card-body">
        <div class="row g-3" id="prospect-settings-row">
          <div class="col-md-4" id="prospect-difficulty-level-col">
            <label class="form-label d-block" for="prospect-difficulty-beginner">Difficulty Level</label>
            <div class="d-flex gap-3" id="prospect-difficulty-options">
              <?php foreach (['beginner' => 'Beginner', 'intermediate' => 'Intermediate', 'advanced' => 'Advanced'] as $key => $label): ?>
                <div class="form-check form-check-inline" id="prospect-difficulty-option-<?= $key ?>">
                  <input class="form-check-input" type="radio" name="difficulty_level" id="prospect-difficulty-<?= $key ?>" value="<?= $key ?>" <?= $values['difficulty_level'] === $key ? 'checked' : '' ?>>
                  <label class="form-check-label" for="prospect-difficulty-<?= $key ?>"><?= $label ?></label>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
          <div class="col-md-8" id="prospect-call-types-col">
            <label class="form-label">Applicable Call Types</label>
            <div class="row" id="prospect-call-types-row">
              <?php foreach ($callTypeOptions as $key => $label): ?>
                <div class="col-md-4" id="prospect-call-type-<?= $key ?>">
                  <div class="form-check" id="prospect-call-type-check-<?= $key ?>">
                    <input class="form-check-input" type="checkbox" name="call_types[]" id="call-type-<?= $key ?>" value="<?= $key ?>" <?= in_array($key, $selectedCallTypes) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="call-type-<?= $key ?>"><?= $label ?></label>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="card mb-4" id="prospect-meta-card">
      <div class="card-header" id="prospect-meta-card-header">
        <h6 class="mb-0" id="prospect-meta-title">Additional Details</h6>
      </div>
      <div class="card-body" id="prospect-meta-card-body">
        <div class="row g-3" id="prospect-meta-row">
          <div class="col-md-4" id="prospect-members-col">
            <label class="form-label" for="prospect-members">Members</label>
            <input type="number" class="form-control" id="prospect-members" name="members"
                   value="<?= htmlspecialchars($values['members']) ?>">
          </div>
          <div class="col-md-4" id="prospect-monthly-spend-col">
            <label class="form-label" for="prospect-monthly-spend">Monthly Spend</label>
            <input type="number" class="form-control" id="prospect-monthly-spend" name="monthly_spend"
                   value="<?= htmlspecialchars($values['monthly_spend']) ?>">
          </div>
          <div class="col-md-4" id="prospect-monthly-limit-col">
            <label class="form-label" for="prospect-monthly-limit">Monthly Spend Limit</label>
            <input type="number" class="form-control" id="prospect-monthly-limit" name="monthly_spend_limit"
                   value="<?= htmlspecialchars($values['monthly_spend_limit']) ?>">
          </div>
          <div class="col-md-6" id="prospect-agent-id-col">
            <label class="form-label" for="prospect-agent-id">Default Agent ID</label>
            <input type="text" class="form-control" id="prospect-agent-id" name="agent_id"
                   value="<?= htmlspecialchars($values['agent_id']) ?>">
          </div>
          <div class="col-md-6" id="prospect-stub-col">
            <label class="form-label" for="prospect-stub">Stub (optional)</label>
            <input type="text" class="form-control" id="prospect-stub" name="stub"
                   value="<?= htmlspecialchars($values['stub']) ?>">
          </div>
        </div>
      </div>
    </div>

    <div class="d-flex justify-content-end gap-2" id="prospect-form-buttons">
      <a href="#" class="btn btn-light" id="prospect-cancel-btn" hx-get="/partials/prospects/list.php" hx-target="#page-content">Cancel</a>
      <button type="submit" class="btn btn-primary" id="prospect-submit-btn"><?= $isEdit ? 'Save Changes' : 'Create Prospect' ?></button>
    </div>
  </form>
</div>
