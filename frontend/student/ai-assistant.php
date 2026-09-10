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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/normalize/8.0.1/normalize.min.css">
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
                        <h1><i class="fab fa-google" style="color: #4285F4; margin-right: 6px;"></i><i class="fas fa-robot" style="color: var(--ai-accent);"></i> AI Academic Assistant</h1>
                        <p class="page-subtitle">Instant Google-style search answers, AI overviews, and academic tutoring powered by Gemini</p>
                    </div>
                    <span class="badge" style="background: rgba(66, 133, 244, 0.12); color: #4285F4; border: 1px solid rgba(66, 133, 244, 0.3); font-weight: 600; padding: 6px 12px; border-radius: 20px; font-size: 12px; display: inline-flex; align-items: center; gap: 6px;">
                        <i class="fab fa-google" style="color: #EA4335;"></i> Google AI Overview Mode
                    </span>
                </div>

                <!-- Quick Prompts Pill Bar -->
                <div style="display: flex; gap: 8px; margin-bottom: 16px; overflow-x: auto; padding-bottom: 4px;">
                    <button class="btn btn-outline" style="font-size: 12px; padding: 6px 14px; white-space: nowrap; border-radius: 20px;" onclick="fillAndSend('Explain DBMS Normalization (1NF to BCNF) with simple real-world examples.')">
                        🔍 Explain Normalization
                    </button>
                    <button class="btn btn-outline" style="font-size: 12px; padding: 6px 14px; white-space: nowrap; border-radius: 20px;" onclick="fillAndSend('What is the difference between Process and Thread in Operating Systems?')">
                        ⚙️ Process vs Thread
                    </button>
                    <button class="btn btn-outline" style="font-size: 12px; padding: 6px 14px; white-space: nowrap; border-radius: 20px;" onclick="fillAndSend('How does Dijkstra\'s Shortest Path algorithm work? Provide step-by-step logic.')">
                        🧭 Dijkstra Algorithm
                    </button>
                    <button class="btn btn-outline" style="font-size: 12px; padding: 6px 14px; white-space: nowrap; border-radius: 20px;" onclick="fillAndSend('Give me a high-yield revision strategy for upcoming Midterm exams.')">
                        📅 7-Day Revision Plan
                    </button>
                    <button class="btn btn-outline" style="font-size: 12px; padding: 6px 14px; white-space: nowrap; border-radius: 20px;" onclick="fillAndSend('What classes do I have scheduled this week?')">
                        ⏰ My Timetable
                    </button>
                    <button class="btn btn-outline" style="font-size: 12px; padding: 6px 14px; white-space: nowrap; border-radius: 20px;" onclick="fillAndSend('What assignments are pending?')">
                        📝 Pending Assignments
                    </button>
                </div>

                <!-- Chat Box Container -->
                <div class="ai-chat-box" style="height: 600px;">
                    <div class="ai-chat-messages" id="chatMessages">
                        <div class="ai-message bot">
                            <div class="ai-avatar" style="background: linear-gradient(135deg, #4285F4, #34A853); color: white;"><i class="fab fa-google"></i></div>
                            <div class="ai-bubble google-overview-bubble">
                                <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px; font-size: 12px; font-weight: 700; color: #4285F4; text-transform: uppercase; letter-spacing: 0.5px;">
                                    <i class="fab fa-google" style="color: #EA4335;"></i> Google-Style AI Overview
                                </div>
                                Hello <strong><?php echo htmlspecialchars($_SESSION['user']['first_name']); ?></strong>! I provide fast, structured answers modeled after Google Search AI Overviews with direct summaries, core highlights, knowledge cards, and suggested follow-ups. Ask any question below to get started!
                            </div>
                        </div>
                    </div>

                    <div class="ai-chat-input-bar" style="border-radius: 28px; box-shadow: 0 4px 14px rgba(0,0,0,0.06); border: 1px solid rgba(66, 133, 244, 0.25);">
                        <i class="fas fa-search" style="color: #4285F4; margin-left: 8px; font-size: 15px;"></i>
                        <input type="text" id="userInput" placeholder="Ask anything like on Google (e.g. 'Difference between 3NF and BCNF', 'What is Dijkstra algorithm')..." onkeydown="if(event.key==='Enter') sendMessage()">
                        <button class="btn btn-primary" id="sendBtn" onclick="sendMessage()" style="border-radius: 20px; padding: 8px 20px; background: #4285F4; border-color: #4285F4;">
                            <i class="fas fa-search"></i> Search
                        </button>
                    </div>
                </div>
            </div>
            <?php include_once __DIR__ . '/../components/footer.php'; ?>
        </main>
    </div>

    <style>
    .google-overview-bubble {
        background: var(--bg-card) !important;
        border: 1px solid rgba(66, 133, 244, 0.25) !important;
        border-radius: 12px !important;
        padding: 16px 20px !important;
        box-shadow: 0 4px 16px rgba(66, 133, 244, 0.05);
        color: var(--text-primary) !important;
        max-width: 85% !important;
        line-height: 1.65;
    }
    .google-overview-card {
        background: rgba(66, 133, 244, 0.04);
        border-left: 4px solid #4285F4;
        padding: 12px 16px;
        border-radius: 0 8px 8px 0;
        margin: 10px 0 14px 0;
        font-size: 14px;
    }
    .google-header-tag {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #4285F4;
        background: rgba(66, 133, 244, 0.08);
        padding: 3px 8px;
        border-radius: 12px;
        margin-bottom: 8px;
    }
    .paa-container {
        margin-top: 14px;
        padding-top: 12px;
        border-top: 1px solid var(--border-color);
    }
    .paa-title {
        font-size: 12px;
        font-weight: 700;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 8px;
        display: flex;
        align-items: center;
        gap: 6px;
    }
    .paa-chips-grid {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }
    .paa-chip-btn {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 8px 12px;
        border-radius: 8px;
        background: var(--bg-primary);
        border: 1px solid var(--border-color);
        color: var(--text-primary);
        font-size: 13px;
        cursor: pointer;
        text-align: left;
        transition: all 0.2s ease;
    }
    .paa-chip-btn:hover {
        background: rgba(66, 133, 244, 0.08);
        border-color: #4285F4;
        color: #4285F4;
        transform: translateX(3px);
    }
    .paa-chip-btn i {
        color: #4285F4;
        font-size: 11px;
    }
    </style>

    <script src="../assets/js/utils.js"></script>
    <script src="../assets/js/notifications.js"></script>
    <script>
    let activeConversationId = null;

    function fillAndSend(text) {
        const input = document.getElementById('userInput');
        input.value = text;
        sendMessage();
    }

    function formatGoogleAnswer(markdown) {
        if (!markdown) return '';
        
        let html = markdown;

        // Split out People Also Ask if present
        let paaSection = '';
        const paaMatch = html.match(/###\s*❓\s*People Also Ask([\s\S]*)$/i) || html.match(/\*\*People Also Ask\*\*([\s\S]*)$/i);
        if (paaMatch) {
            html = html.replace(paaMatch[0], '');
            const questions = paaMatch[1].split(/\n/).map(l => l.replace(/^[•\-\*\d\.]+\s*/, '').trim()).filter(l => l.length > 5);
            if (questions.length > 0) {
                paaSection = `
                    <div class="paa-container">
                        <div class="paa-title"><i class="fas fa-question-circle" style="color: #FBBC05;"></i> People also ask</div>
                        <div class="paa-chips-grid">
                            ${questions.map(q => `<button class="paa-chip-btn" onclick="fillAndSend('${escapeHTML(q).replace(/'/g, "\\'")}')"><i class="fas fa-search"></i> <span>${escapeHTML(q)}</span></button>`).join('')}
                        </div>
                    </div>
                `;
            }
        }

        // Replace headers
        html = html.replace(/^###\s*(.*?)$/gm, '<h4 style="font-size: 14px; font-weight: 700; color: var(--text-primary); margin: 12px 0 6px 0; display:flex; align-items:center; gap:6px;">$1</h4>');
        html = html.replace(/^##\s*(.*?)$/gm, '<h3 style="font-size: 16px; font-weight: 700; color: #4285F4; margin: 14px 0 8px 0;">$1</h3>');

        // Quick Answer card styling
        html = html.replace(/\*\*Quick Answer:\*\*\s*(.*?)(?=\n\n|\n###|$)/s, '<div class="google-overview-card"><strong style="color:#4285F4;">Quick Answer:</strong> $1</div>');

        // Bold
        html = html.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
        // Italics
        html = html.replace(/\*([^\*]+)\*/g, '<em>$1</em>');

        // Bullets
        html = html.replace(/^[•\-\*]\s+(.*?)$/gm, '<div style="display:flex; gap:8px; margin:4px 0 4px 8px;"><i class="fas fa-circle" style="font-size:6px; color:#4285F4; margin-top:7px; flex-shrink:0;"></i><span>$1</span></div>');

        // Numbered list
        html = html.replace(/^(\d+)\.\s+(.*?)$/gm, '<div style="display:flex; gap:8px; margin:4px 0 4px 8px;"><strong style="color:#4285F4; flex-shrink:0;">$1.</strong><span>$2</span></div>');

        // Tables simple parse
        html = html.replace(/\|(.+)\|/g, function(match) {
            if (match.includes('---')) return '';
            const cols = match.split('|').map(c => c.trim()).filter(c => c.length > 0);
            return '<div style="display:flex; gap:12px; padding:4px 0; border-bottom:1px solid var(--border-color);">' + cols.map(c => `<div style="flex:1; font-size:12px;">${c}</div>`).join('') + '</div>';
        });

        // Linebreaks
        html = html.replace(/\n\n/g, '<div style="height:8px;"></div>');

        return `
            <div class="google-header-tag"><i class="fab fa-google" style="color: #EA4335;"></i> Google-Style AI Overview</div>
            ${html}
            ${paaSection}
        `;
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
                <div class="ai-avatar" style="background: linear-gradient(135deg, #4285F4, #34A853); color:white;"><i class="fab fa-google"></i></div>
                <div class="ai-bubble google-overview-bubble">
                    <i class="fas fa-spinner fa-spin" style="color:#4285F4;"></i> Searching & synthesizing Google-style answer...
                </div>
            </div>
        `;
        container.scrollTop = container.scrollHeight;

        try {
            const headers = typeof getAuthHeaders === 'function' 
                ? getAuthHeaders({ 'Content-Type': 'application/json' }) 
                : { 'Content-Type': 'application/json' };
            const res = await fetch('/StudentOS-AI-project/backend/api/ai.php?path=assistant', {
                method: 'POST',
                credentials: 'same-origin',
                headers: headers,
                body: JSON.stringify({ question: question, conversation_id: activeConversationId })
            });
            const data = await res.json();
            const botBubble = document.getElementById(typingId);

            if (data && data.answer) {
                if (data.conversation_id) activeConversationId = data.conversation_id;
                botBubble.querySelector('.ai-bubble').innerHTML = formatGoogleAnswer(data.answer);
            } else {
                botBubble.querySelector('.ai-bubble').innerHTML = 'I encountered an issue generating the Google-style overview. Please try rephrasing your search query.';
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
