<?php
// frontend/student/ai-assistant.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('student');

$userId = $_SESSION['user']['id'];
$initialSubject = sanitize($_GET['subject'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Academic Assistant - StudentOS AI</title>
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
                        <h1><i class="fas fa-robot" style="color: var(--ai-accent);"></i> AI Academic Assistant</h1>
                        <p class="page-subtitle">Your personal AI tutor powered by Gemini: ask questions, clarify doubts, and analyze syllabus topics</p>
                    </div>
                </div>

                <!-- Quick Prompts Pill Bar -->
                <div style="display: flex; gap: 8px; margin-bottom: 16px; overflow-x: auto; padding-bottom: 4px;">
                    <button class="btn btn-outline" style="font-size: 12px; padding: 6px 14px; white-space: nowrap;" onclick="fillPrompt('Explain DBMS Normalization (1NF to BCNF) with simple real-world examples.')">
                        💡 Explain Normalization
                    </button>
                    <button class="btn btn-outline" style="font-size: 12px; padding: 6px 14px; white-space: nowrap;" onclick="fillPrompt('What is the difference between Process and Thread in Operating Systems?')">
                        ⚙️ Process vs Thread
                    </button>
                    <button class="btn btn-outline" style="font-size: 12px; padding: 6px 14px; white-space: nowrap;" onclick="fillPrompt('How does Dijkstra\'s Shortest Path algorithm work? Provide step-by-step logic.')">
                        🔍 Dijkstra Algorithm
                    </button>
                    <button class="btn btn-outline" style="font-size: 12px; padding: 6px 14px; white-space: nowrap;" onclick="fillPrompt('Give me a 3-day revision strategy for upcoming Midterm exams.')">
                        📅 3-Day Revision Plan
                    </button>
                </div>

                <!-- Chat Box Container -->
                <div class="ai-chat-box">
                    <div class="ai-chat-messages" id="chatMessages">
                        <div class="ai-message bot">
                            <div class="ai-avatar"><i class="fas fa-robot"></i></div>
                            <div class="ai-bubble">
                                Hello <?php echo htmlspecialchars($_SESSION['user']['first_name']); ?>! I am your StudentOS AI Academic Assistant. How can I help you master your coursework today?
                            </div>
                        </div>
                    </div>

                    <div class="ai-chat-input-bar">
                        <input type="text" id="userInput" placeholder="Ask anything about your courses, homework, or exam prep..." onkeydown="if(event.key==='Enter') sendMessage()">
                        <button class="btn btn-primary" id="sendBtn" onclick="sendMessage()">
                            <i class="fas fa-paper-plane"></i> Send
                        </button>
                    </div>
                </div>
            </div>
            <?php include_once __DIR__ . '/../components/footer.php'; ?>
        </main>
    </div>

    <script src="../assets/js/utils.js"></script>
    <script src="../assets/js/notifications.js"></script>
    <script>
    let activeConversationId = null;

    function fillPrompt(text) {
        const input = document.getElementById('userInput');
        input.value = text;
        input.focus();
    }

    async function sendMessage() {
        const input = document.getElementById('userInput');
        const question = input.value.trim();
        if (!question) return;

        input.value = '';
        const container = document.getElementById('chatMessages');

        // Add user bubble
        container.innerHTML += `
            <div class="ai-message user">
                <div class="ai-avatar"><i class="fas fa-user"></i></div>
                <div class="ai-bubble">${escapeHTML(question)}</div>
            </div>
        `;

        // Add typing placeholder
        const typingId = 'typing-' + Date.now();
        container.innerHTML += `
            <div class="ai-message bot" id="${typingId}">
                <div class="ai-avatar"><i class="fas fa-robot"></i></div>
                <div class="ai-bubble"><i class="fas fa-spinner fa-spin"></i> Thinking...</div>
            </div>
        `;
        container.scrollTop = container.scrollHeight;

        try {
            const res = await fetch('/StudentOS-AI-project/backend/api/ai.php?path=assistant', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ question: question, conversation_id: activeConversationId })
            });
            const data = await res.json();
            const botBubble = document.getElementById(typingId);

            if (data && data.answer) {
                if (data.conversation_id) activeConversationId = data.conversation_id;
                botBubble.querySelector('.ai-bubble').innerHTML = data.answer.replace(/\n/g, '<br>');
            } else {
                botBubble.querySelector('.ai-bubble').innerHTML = 'I encountered a brief issue. Please try rephrasing your question.';
            }
        } catch (err) {
            const botBubble = document.getElementById(typingId);
            if (botBubble) {
                botBubble.querySelector('.ai-bubble').innerHTML = 'Sorry, could not connect to AI service. Please check your network or server status.';
            }
        }
        container.scrollTop = container.scrollHeight;
    }
    </script>
</body>
</html>
