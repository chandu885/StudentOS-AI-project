<?php
// frontend/student/pdf-qa.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('student');

$userId = $_SESSION['user']['id'];
$db = getDbConnection();

$documents = [];
if ($db) {
    $stmt = $db->prepare("SELECT id, title, description, file_path FROM documents WHERE is_public = 1 OR user_id = ? ORDER BY id ASC");
    if ($stmt) {
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $documents[$row['id']] = $row['title'];
        }
        $stmt->close();
    }
}

$firstDocId = !empty($documents) ? (int)array_key_first($documents) : 0;
$docId = isset($_GET['doc_id']) && isset($documents[(int)$_GET['doc_id']]) ? (int)$_GET['doc_id'] : $firstDocId;
$activeDocTitle = $documents[$docId] ?? 'Document';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PDF Q&A / Document RAG - StudentOS AI</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/normalize/8.0.1/normalize.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/variables.css">
    <link rel="stylesheet" href="../assets/css/reset.css">
    <link rel="stylesheet" href="../assets/css/global.css">
    <link rel="stylesheet" href="../assets/css/components.css">
    <link rel="stylesheet" href="../assets/css/responsive.css">
</head>
<body>
    <div class="dashboard-layout">
        <?php include_once __DIR__ . '/../components/sidebar.php'; ?>
        
        <main class="dashboard-main">
            <?php include_once __DIR__ . '/../components/navbar.php'; ?>
            
            <div class="dashboard-content">
                <div class="page-header">
                    <div>
                        <h1><i class="fas fa-file-pdf" style="color: var(--danger);"></i> PDF Q&A (Document RAG)</h1>
                        <p class="page-subtitle">Chat directly with textbook chapters and lecture notes using Retrieval-Augmented Generation</p>
                    </div>
                </div>

                <?php if (empty($documents)): ?>
                    <div class="card">
                        <div class="card-body">
                            <div class="empty-state">
                                <i class="fas fa-file-pdf"></i>
                                <p>No documents found in the database. Upload documents to start chatting with them.</p>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="card">
                        <div class="card-body" style="padding: 16px 20px;">
                            <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
                                <div style="display: flex; align-items: center; gap: 12px;">
                                    <i class="fas fa-file-pdf" style="font-size: 28px; color: var(--danger);"></i>
                                    <div>
                                        <label style="font-size: 11px; color: var(--text-muted); text-transform: uppercase; font-weight: 600;">Active Document</label>
                                        <select id="docSelect" class="form-control" style="width: auto; height: 36px; padding: 4px 10px; margin-top: 2px;" onchange="window.location.href='pdf-qa.php?doc_id=' + this.value">
                                            <?php foreach ($documents as $id => $name): ?>
                                                <option value="<?php echo $id; ?>" <?php echo $id === $docId ? 'selected' : ''; ?>><?php echo htmlspecialchars($name); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <span class="badge badge-success"><i class="fas fa-check-circle"></i> Vector Indexed (FAISS)</span>
                            </div>
                        </div>
                    </div>

                    <!-- Chat Box -->
                    <div class="ai-chat-box" style="height: 520px;">
                        <div class="ai-chat-messages" id="ragMessages">
                            <div class="ai-message bot">
                                <div class="ai-avatar" style="background: var(--danger);"><i class="fas fa-file-pdf"></i></div>
                                <div class="ai-bubble">
                                    I am ready to answer questions based on <strong><?php echo htmlspecialchars($activeDocTitle); ?></strong>. Ask me to explain definitions, find theorems, or synthesize specific sections!
                                </div>
                            </div>
                        </div>

                        <div class="ai-chat-input-bar">
                            <input type="text" id="ragInput" placeholder="Ask a question about this document..." onkeydown="if(event.key==='Enter') sendRagQuestion()">
                            <button class="btn btn-primary" onclick="sendRagQuestion()"><i class="fas fa-paper-plane"></i> Ask Document</button>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            <?php include_once __DIR__ . '/../components/footer.php'; ?>
        </main>
    </div>

    <script src="../assets/js/utils.js"></script>
    <script src="../assets/js/notifications.js"></script>
    <script>
    const currentDocId = <?php echo $docId; ?>;

    async function sendRagQuestion() {
        const input = document.getElementById('ragInput');
        const question = input.value.trim();
        if (!question) return;

        input.value = '';
        const container = document.getElementById('ragMessages');

        // Add user bubble
        container.innerHTML += `
            <div class="ai-message user">
                <div class="ai-avatar"><i class="fas fa-user"></i></div>
                <div class="ai-bubble">${escapeHTML(question)}</div>
            </div>
        `;

        const typingId = 'typing-' + Date.now();
        container.innerHTML += `
            <div class="ai-message bot" id="${typingId}">
                <div class="ai-avatar" style="background: var(--danger);"><i class="fas fa-file-pdf"></i></div>
                <div class="ai-bubble"><i class="fas fa-spinner fa-spin"></i> Retrieving relevant chunks & synthesizing...</div>
            </div>
        `;
        container.scrollTop = container.scrollHeight;

        try {
            const headers = typeof getAuthHeaders === 'function' 
                ? getAuthHeaders({ 'Content-Type': 'application/json' }) 
                : { 'Content-Type': 'application/json' };
            const res = await fetch('/StudentOS-AI-project/backend/api/ai.php?path=pdf-qa', {
                method: 'POST',
                credentials: 'same-origin',
                headers: headers,
                body: JSON.stringify({ document_id: currentDocId, question: question })
            });
            const data = await res.json();
            const botBubble = document.getElementById(typingId);

            if (data && data.answer) {
                botBubble.querySelector('.ai-bubble').innerHTML = `
                    ${data.answer.replace(/\n/g, '<br>')}
                    <div style="margin-top:8px;font-size:11px;color:var(--text-muted);border-top:1px solid var(--border-color);padding-top:4px;">
                        <i class="fas fa-bookmark"></i> Document Citation: ${data.citation || (data.sources && data.sources.length ? data.sources.join(', ') : 'Document Reference')}
                    </div>
                `;
            } else {
                botBubble.querySelector('.ai-bubble').innerHTML = data && data.error ? escapeHTML(data.error) : 'Unable to retrieve answer for the specified document.';
            }
        } catch (err) {
            const botBubble = document.getElementById(typingId);
            if (botBubble) {
                botBubble.querySelector('.ai-bubble').innerHTML = 'Unable to connect to Document AI service. Please verify your connection.';
            }
        }
        container.scrollTop = container.scrollHeight;
    }
    </script>
</body>
</html>
