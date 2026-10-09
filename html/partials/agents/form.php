<?php
require_once '/var/www/helpers/auth.php';
require_once '/var/www/models/Agent.php';
require_once '/var/www/helpers/retell.php';
requireManager();

$orgId = currentOrgId();
$agentModel = new Agent();
$method = strtoupper($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] ?? $_SERVER['REQUEST_METHOD']);
$agentId = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$errors = [];

$voiceOptions = [];
$voicesResp = listRetellVoices();
if (empty($voicesResp['error']) && !empty($voicesResp['voices'])) {
    foreach ($voicesResp['voices'] as $voice) {
        if (!empty($voice['name'])) {
            $voiceOptions[] = $voice['name'];
        }
    }
}
if (empty($voiceOptions)) {
    $voiceOptions = ['alloy', 'verse', 'sarah', 'matilda'];
}

$llmOptions = ['gpt-4o', 'gpt-4o-mini', 'claude-sonnet', 'claude-haiku'];

$agent = null;
if ($agentId > 0) {
    $agent = $agentModel->getById($agentId, $orgId);
}

$values = $agent ?? [
    'name' => '',
    'description' => '',
    'retell_agent_id' => '',
    'voice_name' => $voiceOptions[0] ?? '',
    'llm' => $llmOptions[0] ?? '',
    'image' => 'https://images.skilliks.ai/pending.php',
];

$prompts = $agent ? $agentModel->getPrompts($agentId) : [];
$settings = $agent ? $agentModel->getSettings($agentId) : [];
$outputSettings = $agent ? $agentModel->getOutputSettings($agentId) : [];

if ($method === 'POST') {
    $data = [
        'name' => trim($_POST['name'] ?? ''),
        'description' => trim($_POST['description'] ?? ''),
        'retell_agent_id' => trim($_POST['retell_agent_id'] ?? ''),
        'voice_name' => trim($_POST['voice_name'] ?? ''),
        'llm' => trim($_POST['llm'] ?? ''),
        'image' => trim($_POST['image'] ?? ''),
    ];

    if ($data['name'] === '') {
        $errors[] = 'Agent name is required';
    }

    if (empty($errors)) {
        if ($agentId > 0) {
            $agentModel->update($agentId, $data, $orgId);
        } else {
            $agentId = $agentModel->create($data);
        }

        // Prompts
        $promptIds = $_POST['prompt_id'] ?? [];
        $promptNames = $_POST['prompt_name'] ?? [];
        $promptValues = $_POST['prompt_value'] ?? [];
        foreach ($promptNames as $idx => $pName) {
            $pName = trim($pName);
            $pVal = trim($promptValues[$idx] ?? '');
            $pId = (int)($promptIds[$idx] ?? 0);
            if ($pName === '' && $pVal === '') {
                continue;
            }
            if ($pId > 0) {
                $agentModel->updatePrompt($pId, ['name' => $pName, 'value' => $pVal]);
            } else {
                $agentModel->createPrompt(['model_id' => $agentId, 'name' => $pName, 'value' => $pVal]);
            }
        }

        // Settings
        $settingNames = $_POST['setting_name'] ?? [];
        $settingValues = $_POST['setting_value'] ?? [];
        foreach ($settingNames as $idx => $sName) {
            $sName = trim($sName);
            if ($sName === '') {
                continue;
            }
            $sVal = trim($settingValues[$idx] ?? '');
            $agentModel->saveSetting($agentId, $sName, $sVal, currentUserId());
        }

        // Output settings
        $outputFormats = $_POST['output_format'] ?? [];
        $outputValues = $_POST['output_value'] ?? [];
        foreach ($outputFormats as $idx => $fmt) {
            $fmt = trim($fmt);
            if ($fmt === '') {
                continue;
            }
            $val = trim($outputValues[$idx] ?? '');
            $agentModel->saveOutputSetting($agentId, $fmt, $val, currentUserId());
        }

        header('HX-Trigger: {"toast": {"message": "Agent saved", "type": "success"}}');
        header('HX-Redirect: /partials/agents/list.php');
        exit;
    } else {
        $values = array_merge($values, $data);
    }
}

$isEdit = $agentId > 0;
?>

<div class="container-fluid" id="agent-form-page">
  <div class="d-flex justify-content-between align-items-center mb-4" id="agent-form-header">
    <div id="agent-form-title-wrap">
      <h4 class="mb-1" id="agent-form-title"><?= $isEdit ? 'Edit Agent' : 'Add Agent' ?></h4>
      <p class="text-muted mb-0" id="agent-form-subtitle">Configure Retell agent details, prompts, and settings.</p>
    </div>
    <div id="agent-form-actions">
      <a href="#" class="btn btn-outline-secondary" id="agent-form-cancel"
         hx-get="/partials/agents/list.php" hx-target="#page-content">Back to list</a>
    </div>
  </div>

  <?php if (!empty($errors)): ?>
    <div class="alert alert-danger" id="agent-form-errors">
      <ul class="mb-0" id="agent-form-errors-list">
        <?php foreach ($errors as $err): ?>
          <li><?= htmlspecialchars($err) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <form id="agent-form" hx-post="/partials/agents/form.php" hx-target="#page-content" hx-swap="outerHTML">
    <input type="hidden" name="id" value="<?= $agentId ?>">

    <div class="card mb-4" id="agent-main-card">
      <div class="card-body" id="agent-main-body">
        <div class="row g-3" id="agent-main-row">
          <div class="col-md-6" id="agent-name-col">
            <label class="form-label" for="agent-name">Agent Name *</label>
            <input type="text" class="form-control" id="agent-name" name="name" required value="<?= htmlspecialchars($values['name']) ?>">
          </div>
          <div class="col-md-6" id="agent-avatar-col">
            <label class="form-label" for="agent-image">Agent Avatar URL</label>
            <input type="url" class="form-control" id="agent-image" name="image" value="<?= htmlspecialchars($values['image']) ?>">
          </div>
          <div class="col-12" id="agent-description-col">
            <label class="form-label" for="agent-description">Description</label>
            <textarea class="form-control" id="agent-description" name="description" rows="2"><?= htmlspecialchars($values['description']) ?></textarea>
          </div>
          <div class="col-md-4" id="agent-retell-col">
            <label class="form-label" for="agent-retell">Retell Agent ID</label>
            <input type="text" class="form-control" id="agent-retell" name="retell_agent_id" value="<?= htmlspecialchars($values['retell_agent_id']) ?>">
          </div>
          <div class="col-md-4" id="agent-voice-col">
            <label class="form-label" for="agent-voice">Voice</label>
            <select class="form-select" id="agent-voice" name="voice_name">
              <?php foreach ($voiceOptions as $voice): ?>
                <option value="<?= htmlspecialchars($voice) ?>" <?= $values['voice_name'] === $voice ? 'selected' : '' ?>><?= htmlspecialchars($voice) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4" id="agent-llm-col">
            <label class="form-label" for="agent-llm">LLM Model</label>
            <select class="form-select" id="agent-llm" name="llm">
              <?php foreach ($llmOptions as $llm): ?>
                <option value="<?= htmlspecialchars($llm) ?>" <?= $values['llm'] === $llm ? 'selected' : '' ?>><?= htmlspecialchars($llm) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      </div>
    </div>

    <div class="card mb-4" id="agent-prompts-card">
      <div class="card-header d-flex justify-content-between align-items-center" id="agent-prompts-header">
        <h6 class="mb-0" id="agent-prompts-title">Prompt Templates</h6>
        <button type="button" class="btn btn-outline-primary btn-sm" id="add-prompt-btn" onclick="addPromptRow()">
          <i class="bi bi-plus-lg"></i> Add Prompt
        </button>
      </div>
      <div class="card-body" id="agent-prompts-body">
        <div id="prompt-list">
          <?php if (empty($prompts)): ?>
            <div class="mb-3" id="prompt-row-0">
              <input type="hidden" name="prompt_id[]" value="0">
              <div class="mb-2"><input type="text" class="form-control" name="prompt_name[]" placeholder="Prompt name"></div>
              <textarea class="form-control" name="prompt_value[]" rows="3" placeholder="Prompt text"></textarea>
            </div>
          <?php else: ?>
            <?php foreach ($prompts as $idx => $prompt): ?>
              <div class="mb-3" id="prompt-row-<?= $idx ?>">
                <input type="hidden" name="prompt_id[]" value="<?= $prompt['id'] ?>">
                <div class="mb-2"><input type="text" class="form-control" name="prompt_name[]" value="<?= htmlspecialchars($prompt['name']) ?>" placeholder="Prompt name"></div>
                <textarea class="form-control" name="prompt_value[]" rows="3" placeholder="Prompt text"><?= htmlspecialchars($prompt['value'] ?? '') ?></textarea>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="card mb-4" id="agent-settings-card">
      <div class="card-header d-flex justify-content-between align-items-center" id="agent-settings-header">
        <h6 class="mb-0" id="agent-settings-title">Agent Settings</h6>
        <button type="button" class="btn btn-outline-primary btn-sm" id="add-setting-btn" onclick="addSettingRow()">
          <i class="bi bi-plus-lg"></i> Add Setting
        </button>
      </div>
      <div class="card-body" id="agent-settings-body">
        <div id="setting-list">
          <?php
          $defaultSettings = [
              ['name' => 'temperature', 'value' => '0.7'],
              ['name' => 'max_tokens', 'value' => '2048'],
          ];
          $settingsToShow = !empty($settings) ? $settings : $defaultSettings;
          foreach ($settingsToShow as $idx => $setting): ?>
            <div class="row g-2 align-items-center mb-2" id="setting-row-<?= $idx ?>">
              <div class="col-md-5" id="setting-name-col-<?= $idx ?>">
                <input type="text" class="form-control" name="setting_name[]" value="<?= htmlspecialchars($setting['name']) ?>" placeholder="Setting name">
              </div>
              <div class="col-md-7" id="setting-value-col-<?= $idx ?>">
                <input type="text" class="form-control" name="setting_value[]" value="<?= htmlspecialchars($setting['value']) ?>" placeholder="Value">
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <div class="card mb-4" id="agent-output-card">
      <div class="card-header d-flex justify-content-between align-items-center" id="agent-output-header">
        <h6 class="mb-0" id="agent-output-title">Output Settings</h6>
        <button type="button" class="btn btn-outline-primary btn-sm" id="add-output-btn" onclick="addOutputRow()">
          <i class="bi bi-plus-lg"></i> Add Output Setting
        </button>
      </div>
      <div class="card-body" id="agent-output-body">
        <div id="output-list">
          <?php if (empty($outputSettings)): ?>
            <div class="row g-2 align-items-center mb-2" id="output-row-0">
              <div class="col-md-5" id="output-format-col-0">
                <input type="text" class="form-control" name="output_format[]" value="JSON" placeholder="Format (e.g., JSON)">
              </div>
              <div class="col-md-7" id="output-value-col-0">
                <input type="text" class="form-control" name="output_value[]" value="" placeholder="Output config">
              </div>
            </div>
          <?php else: ?>
            <?php foreach ($outputSettings as $idx => $out): ?>
              <div class="row g-2 align-items-center mb-2" id="output-row-<?= $idx ?>">
                <div class="col-md-5" id="output-format-col-<?= $idx ?>">
                  <input type="text" class="form-control" name="output_format[]" value="<?= htmlspecialchars($out['format']) ?>" placeholder="Format (e.g., JSON)">
                </div>
                <div class="col-md-7" id="output-value-col-<?= $idx ?>">
                  <input type="text" class="form-control" name="output_value[]" value="<?= htmlspecialchars($out['value']) ?>" placeholder="Output config">
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="d-flex justify-content-end gap-2" id="agent-form-buttons">
      <a href="#" class="btn btn-light" id="agent-cancel-btn" hx-get="/partials/agents/list.php" hx-target="#page-content">Cancel</a>
      <button type="submit" class="btn btn-primary" id="agent-submit-btn"><?= $isEdit ? 'Save Changes' : 'Create Agent' ?></button>
    </div>
  </form>
</div>

<script>
function addPromptRow() {
  const list = document.getElementById('prompt-list');
  const idx = list.querySelectorAll('[id^="prompt-row-"]').length;
  const div = document.createElement('div');
  div.className = 'mb-3';
  div.id = `prompt-row-${idx}`;
  div.innerHTML = `
    <input type="hidden" name="prompt_id[]" value="0">
    <div class="mb-2"><input type="text" class="form-control" name="prompt_name[]" placeholder="Prompt name"></div>
    <textarea class="form-control" name="prompt_value[]" rows="3" placeholder="Prompt text"></textarea>`;
  list.appendChild(div);
}
function addSettingRow() {
  const list = document.getElementById('setting-list');
  const idx = list.querySelectorAll('[id^="setting-row-"]').length;
  const row = document.createElement('div');
  row.className = 'row g-2 align-items-center mb-2';
  row.id = `setting-row-${idx}`;
  row.innerHTML = `
    <div class="col-md-5" id="setting-name-col-${idx}">
      <input type="text" class="form-control" name="setting_name[]" placeholder="Setting name">
    </div>
    <div class="col-md-7" id="setting-value-col-${idx}">
      <input type="text" class="form-control" name="setting_value[]" placeholder="Value">
    </div>`;
  list.appendChild(row);
}
function addOutputRow() {
  const list = document.getElementById('output-list');
  const idx = list.querySelectorAll('[id^="output-row-"]').length;
  const row = document.createElement('div');
  row.className = 'row g-2 align-items-center mb-2';
  row.id = `output-row-${idx}`;
  row.innerHTML = `
    <div class="col-md-5" id="output-format-col-${idx}">
      <input type="text" class="form-control" name="output_format[]" placeholder="Format (e.g., JSON)">
    </div>
    <div class="col-md-7" id="output-value-col-${idx}">
      <input type="text" class="form-control" name="output_value[]" placeholder="Output config">
    </div>`;
  list.appendChild(row);
}
</script>
