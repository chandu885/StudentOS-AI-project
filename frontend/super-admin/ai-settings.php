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
    $successMsg = 'Gemini AI engine parameters and token quotas successfully updated!';
}

$aiSettings = $systemModel->getAISettings();
$currentApiKey = $aiSettings['gemini_api_key'] ?? '';
$maskedKey = !empty($currentApiKey) ? substr($currentApiKey, 0, 6) . '...' . substr($currentApiKey, -4) : '';
$currentModel = $aiSettings['default_model'] ?? 'gemini-1.5-flash';
$currentTemp = $aiSettings['temperature'] ?? '0.7';
$currentChunkSize = $aiSettings['chunk_size'] ?? '512';
$currentTopK = $aiSettings['top_k'] ?? '4';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Engine Settings - StudentOS AI</title>
    <link rel="stylesheet" href="../assets/css/variables.css">
    <link rel="stylesheet" href="../assets/css/reset.css">
    <link rel="stylesheet" href="../assets/css/global.css">
    <link rel="stylesheet" href="../assets/css/components.css">
    <link rel="stylesheet" href="../assets/css/responsive.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>
    <div class="dashboard-layout">
        <?php include_once __DIR__ . '/../components/sidebar.php'; ?>
        
        <main class="dashboard-main">
            <?php include_once __DIR__ . '/../components/navbar.php'; ?>
            
            <div class="dashboard-content">
                <div class="page-header">
                    <div>
                        <h1><i class="fas fa-brain" style="color: var(--ai-accent);"></i> AI Engine Architecture & Hyperparameters</h1>
                        <p class="page-subtitle">Configure Google Gemini LLM keys, FAISS vector embeddings, and RAG retrieval thresholds</p>
                    </div>
                </div>

                <?php if ($successMsg): ?>
                    <div class="alert alert-success" style="background: rgba(34, 197, 94, 0.15); border: 1px solid var(--success); color: var(--success); padding: 12px 16px; border-radius: var(--radius-md); margin-bottom: 20px;">
                        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($successMsg); ?>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-sliders"></i> LLM & RAG Vector Configuration</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="ai-settings.php">
                            <div class="form-group">
                                <label for="apiKey">Gemini API Key</label>
                                <input type="text" name="gemini_api_key" id="apiKey" class="form-control" value="<?php echo htmlspecialchars($currentApiKey); ?>" placeholder="AIzaSy...">
                                <small style="font-size: 11px; color: var(--text-muted);">Stored securely in server database for AI Tutor, Quiz, and RAG operations.</small>
                            </div>

                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                                <div class="form-group">
                                    <label for="aiModel">Primary Reasoning Model</label>
                                    <select name="model" id="aiModel" class="form-control">
                                        <option value="gemini-1.5-flash" <?php echo $currentModel === 'gemini-1.5-flash' ? 'selected' : ''; ?>>Gemini 1.5 Flash (Fast, High-Throughput)</option>
                                        <option value="gemini-1.5-pro" <?php echo $currentModel === 'gemini-1.5-pro' ? 'selected' : ''; ?>>Gemini 1.5 Pro (Deep Reasoning & Multimodal)</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="temperature">Model Temperature (Creativity vs Determinism)</label>
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
