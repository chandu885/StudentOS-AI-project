<?php
// frontend/super-admin/ai-settings.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

require_once BASE_PATH . '/backend/models/SystemModel.php';

requireRole('super-admin');

$userId = $_SESSION['user']['id'];
$systemModel = new SystemModel();
$successMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['gemini_api_key'])) {
        $keyVal = trim($_POST['gemini_api_key']);
        if ($keyVal !== '' && strpos($keyVal, 'XXXXX') === false) {
            $systemModel->updateAISetting('gemini_api_key', $keyVal);
        }
    }
    if (isset($_POST['gemini_key_name'])) {
        $systemModel->updateAISetting('gemini_key_name', trim($_POST['gemini_key_name']));
    }
    if (isset($_POST['gemini_project_name'])) {
        $systemModel->updateAISetting('gemini_project_name', trim($_POST['gemini_project_name']));
    }
    if (isset($_POST['gemini_project_number'])) {
        $systemModel->updateAISetting('gemini_project_number', trim($_POST['gemini_project_number']));
    }
    if (isset($_POST['model'])) {
        $systemModel->updateAISetting('default_model', trim($_POST['model']));
    }
    if (isset($_POST['temperature'])) {
        $systemModel->updateAISetting('temperature', trim($_POST['temperature']));
    }
    if (isset($_POST['chunk_size'])) {
        $systemModel->updateAISetting('chunk_size', trim($_POST['chunk_size']));
    }
    if (isset($_POST['top_k'])) {
        $systemModel->updateAISetting('top_k', trim($_POST['top_k']));
    }
    $successMsg = 'Google Gemini AI Key settings and hyperparameters successfully updated!';
}

$aiSettings = $systemModel->getAISettings();
$currentApiKey = !empty($aiSettings['gemini_api_key']) ? $aiSettings['gemini_api_key'] : (getenv('GEMINI_API_KEY') ?: '');
$currentKeyName = $aiSettings['gemini_key_name'] ?? 'chandan';
$currentProjectName = $aiSettings['gemini_project_name'] ?? 'project/406491916720';
$currentProjectNumber = $aiSettings['gemini_project_number'] ?? '406491916720';
$maskedKey = !empty($currentApiKey) ? substr($currentApiKey, 0, 6) . '...' . substr($currentApiKey, -4) : '';
$currentModel = $aiSettings['default_model'] ?? 'gemini-3.6-flash';
$currentTemp = $aiSettings['temperature'] ?? '0.7';
$currentChunkSize = $aiSettings['chunk_size'] ?? '512';
$currentTopK = $aiSettings['top_k'] ?? '4';
?>
<?php
$pageTitle = 'AI Engine Settings - StudentOS AI';
include_once __DIR__ . '/../components/header.php';
?>
                <div class="page-header">
                    <div>
                        <h1><i class="fas fa-brain" style="color: var(--ai-accent);"></i> AI Key Settings & Architecture</h1>
                        <p class="page-subtitle">Configure Google Gemini Authorization API Key, Cloud Project bindings, and models</p>
                    </div>
                    <span class="badge badge-success" style="padding: 6px 14px; font-size: 13px; display: inline-flex; align-items: center; gap: 6px;">
                        <i class="fas fa-check-circle"></i> Key Configured (AQ. Key Active)
                    </span>
                </div>

                <?php if ($successMsg): ?>
                    <div class="alert alert-success" style="background: rgba(34, 197, 94, 0.15); border: 1px solid var(--success); color: var(--success); padding: 12px 16px; border-radius: var(--radius-md); margin-bottom: 20px;">
                        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($successMsg); ?>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-key"></i> Google Gemini Authorization API Key Configuration</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="ai-settings.php">
                            <div class="form-group">
                                <label for="apiKey">Gemini API Key (Authorization Key)</label>
                                <input type="text" name="gemini_api_key" id="apiKey" class="form-control" value="<?php echo htmlspecialchars($currentApiKey); ?>" placeholder="AQ.Ab8RN...">
                                <small style="font-size: 11px; color: var(--text-muted);">Configured Authorization Key with project binding. Passed via <code>x-goog-api-key</code>.</small>
                            </div>

                            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px;">
                                <div class="form-group">
                                    <label for="keyName">Key Name / Owner</label>
                                    <input type="text" name="gemini_key_name" id="keyName" class="form-control" value="<?php echo htmlspecialchars($currentKeyName); ?>" placeholder="chandan">
                                </div>
                                <div class="form-group">
                                    <label for="projectName">Cloud Project Name</label>
                                    <input type="text" name="gemini_project_name" id="projectName" class="form-control" value="<?php echo htmlspecialchars($currentProjectName); ?>" placeholder="project/406491916720">
                                </div>
                                <div class="form-group">
                                    <label for="projectNumber">Cloud Project Number</label>
                                    <input type="text" name="gemini_project_number" id="projectNumber" class="form-control" value="<?php echo htmlspecialchars($currentProjectNumber); ?>" placeholder="406491916720">
                                </div>
                            </div>

                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                                <div class="form-group">
                                    <label for="aiModel">Primary Reasoning Model</label>
                                    <select name="model" id="aiModel" class="form-control">
                                        <option value="gemini-3.6-flash" <?php echo $currentModel === 'gemini-3.6-flash' ? 'selected' : ''; ?>>Gemini 3.6 Flash (Recommended - Ultra Fast & Multimodal)</option>
                                        <option value="gemini-3.8-flash" <?php echo $currentModel === 'gemini-3.8-flash' ? 'selected' : ''; ?>>Gemini 3.8 Flash (High Intelligence)</option>
                                        <option value="gemini-2.5-flash" <?php echo $currentModel === 'gemini-2.5-flash' ? 'selected' : ''; ?>>Gemini 2.5 Flash</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="temperature">Model Temperature (0.0 to 1.0)</label>
                                    <input type="number" step="0.1" name="temperature" id="temperature" class="form-control" value="<?php echo htmlspecialchars($currentTemp); ?>" min="0.0" max="1.0">
                                </div>
                            </div>

                            <div style="border-top: 1px solid var(--border-color); padding-top: 16px; margin-top: 8px;">
                                <h4 style="font-size: 14px; margin-bottom: 12px; color: var(--text-primary);"><i class="fas fa-vector-square"></i> Document RAG & Vector Chunking</h4>
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                                    <div class="form-group">
                                        <label for="chunkSize">Chunk Size (Tokens)</label>
                                        <input type="number" name="chunk_size" id="chunkSize" class="form-control" value="<?php echo htmlspecialchars($currentChunkSize); ?>">
                                    </div>
                                    <div class="form-group">
                                        <label for="topK">RAG Top-K Nearest Chunks</label>
                                        <input type="number" name="top_k" id="topK" class="form-control" value="<?php echo htmlspecialchars($currentTopK); ?>">
                                    </div>
                                </div>
                            </div>

                            <div style="display: flex; justify-content: flex-end; margin-top: 16px;">
                                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save AI Engine Settings</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <?php include_once __DIR__ . '/../components/footer.php'; ?>
        </main>
    </div>

    <script src="../assets/js/utils.js"></script>
    <script src="../assets/js/notifications.js"></script>
</body>
</html>
