<?php
// frontend/student/ai-assistant.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('student');

$userId = $_SESSION['user']['id'];
$initialSubject = sanitize($_GET['subject'] ?? '');

$bodyClass = 'ai-app-screen-mode';
$pageTitle = 'AI Academic Assistant - StudentOS AI';
include_once __DIR__ . '/../components/header.php';
?>
                <!-- Compact AI Assistant Topbar -->
                <div class="ai-app-topbar">
                    <div class="ai-topbar-left">
                        <div class="ai-google-icon-box">
                            <i class="fab fa-google"></i>
                        </div>
                        <div>
                            <h1 class="ai-topbar-heading">
                                AI Academic Assistant
                                <span class="ai-mode-pill"><i class="fab fa-google" style="color: #EA4335;"></i> Google AI Overview</span>
                            </h1>
                            <div class="ai-topbar-sub">Synthesizing instant structured overviews, practical examples, and core academic concepts</div>
                        </div>
                    </div>

                    <div class="ai-topbar-right">
                        
                        <button type="button" class="ai-reset-btn" onclick="clearChat()" title="Start fresh conversation">
                            <i class="fas fa-redo-alt"></i> <span>Reset</span>
                        </button>
                    </div>
                </div>

                <!-- Chat Box Container (Flex Full Height) -->
                <div class="ai-chat-box">
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

                    <div class="ai-chat-input-bar">
                        <div class="ai-input-pill-wrapper">
                            <i class="fas fa-search" style="color: #4285F4; font-size: 14px;"></i>
                            <input type="text" id="userInput" placeholder="Ask anything like on Google ..." onkeydown="if(event.key==='Enter') sendMessage()" autofocus>
                            <button class="ai-send-btn" id="sendBtn" onclick="sendMessage()">
                                <i class="fas fa-search"></i> <span>Search</span>
                            </button>
                        </div>
                        <div class="ai-disclaimer-subline">
                            <span><i class="fab fa-google" style="color: #4285F4;"></i> Google Gemini 3.6 Flash</span> • <span>StudentOS AI Model</span> • <span>Verify critical academic concepts with core syllabus</span>
                        </div>
                    </div>
                </div>
            </div>
            <?php include_once __DIR__ . '/../components/footer.php'; ?>
        </main>
    </div>

    <style>
    /* Viewport lock: eliminate body scroll completely */
    html, body.ai-app-screen-mode {
        height: 100vh;
        max-height: 100vh;
        overflow: hidden !important;
    }

    body.ai-app-screen-mode .dashboard-layout {
        height: calc(100vh - 72px);
        min-height: calc(100vh - 72px);
        max-height: calc(100vh - 72px);
        overflow: hidden !important;
    }

    body.ai-app-screen-mode .dashboard-main {
        height: 100%;
        max-height: 100%;
        min-height: 0;
        overflow: hidden !important;
        display: flex;
        flex-direction: column;
    }

    body.ai-app-screen-mode .dashboard-content {
        height: 100%;
        max-height: 100%;
        min-height: 0;
        flex: 1;
        display: flex;
        flex-direction: column;
        padding: 12px 20px 10px 20px !important;
        overflow: hidden !important;
        gap: 10px;
    }

    body.ai-app-screen-mode .dashboard-footer {
        display: none !important;
    }

    /* Compact Topbar */
    .ai-app-topbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 12px;
        padding: 8px 16px;
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: var(--radius-lg);
        box-shadow: 0 2px 8px rgba(0,0,0,0.03);
        flex-shrink: 0;
    }

    .ai-topbar-left {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .ai-google-icon-box {
        width: 34px;
        height: 34px;
        border-radius: 9px;
        background: linear-gradient(135deg, rgba(66, 133, 244, 0.12), rgba(52, 168, 83, 0.12));
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 16px;
        color: #4285F4;
        border: 1px solid rgba(66, 133, 244, 0.25);
    }

    .ai-topbar-heading {
        font-size: 14.5px;
        font-weight: 700;
        color: var(--text-primary);
        margin: 0;
        display: flex;
        align-items: center;
        gap: 8px;
        line-height: 1.2;
    }

    .ai-topbar-sub {
        font-size: 11px;
        color: var(--text-muted);
        margin-top: 1px;
    }

    .ai-mode-pill {
        background: rgba(66, 133, 244, 0.1);
        color: #4285F4;
        border: 1px solid rgba(66, 133, 244, 0.25);
        font-weight: 600;
        padding: 2px 7px;
        border-radius: 12px;
        font-size: 10.5px;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }

    .ai-topbar-right {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: nowrap;
        overflow-x: auto;
    }

    .ai-prompts-scroller {
        display: flex;
        align-items: center;
        gap: 6px;
        overflow-x: auto;
        max-width: 580px;
        scrollbar-width: none;
        -ms-overflow-style: none;
    }
    .ai-prompts-scroller::-webkit-scrollbar {
        display: none;
    }

    .ai-quick-pill {
        background: var(--bg-secondary);
        border: 1px solid var(--border-color);
        color: var(--text-secondary);
        font-size: 11.5px;
        font-weight: 500;
        padding: 4px 10px;
        border-radius: 14px;
        white-space: nowrap;
        cursor: pointer;
        transition: all 0.15s ease;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    .ai-quick-pill:hover {
        background: rgba(66, 133, 244, 0.08);
        border-color: #4285F4;
        color: #4285F4;
        transform: translateY(-1px);
    }

    .ai-reset-btn {
        border-radius: 14px;
        font-size: 11px;
        padding: 4px 10px;
        height: 26px;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        white-space: nowrap;
        color: var(--text-muted);
        border: 1px solid var(--border-color);
        background: transparent;
        cursor: pointer;
        transition: all 0.15s ease;
    }
    .ai-reset-btn:hover {
        color: var(--danger);
        border-color: rgba(239, 68, 68, 0.3);
        background: rgba(239, 68, 68, 0.06);
    }

    /* Chat Box Container */
    body.ai-app-screen-mode .ai-chat-box {
        flex: 1 !important;
        min-height: 0 !important;
        height: auto !important;
        display: flex !important;
        flex-direction: column !important;
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: var(--radius-lg);
        box-shadow: 0 4px 20px rgba(0,0,0,0.04);
        overflow: hidden;
    }

    .ai-chat-messages {
        flex: 1;
        min-height: 0;
        overflow-y: auto;
        padding: 18px 22px;
        display: flex;
        flex-direction: column;
        gap: 14px;
        scroll-behavior: smooth;
    }

    .ai-chat-messages::-webkit-scrollbar {
        width: 6px;
    }
    .ai-chat-messages::-webkit-scrollbar-track {
        background: transparent;
    }
    .ai-chat-messages::-webkit-scrollbar-thumb {
        background: rgba(150, 150, 150, 0.25);
        border-radius: 4px;
    }
    .ai-chat-messages::-webkit-scrollbar-thumb:hover {
        background: rgba(150, 150, 150, 0.45);
    }

    /* Input Bar Docked At Bottom */
    .ai-chat-input-bar {
        flex-shrink: 0;
        padding: 10px 18px 8px 18px;
        background: var(--bg-card);
        border-top: 1px solid var(--border-color);
        display: flex;
        flex-direction: column;
        gap: 5px;
    }

    .ai-input-pill-wrapper {
        display: flex;
        align-items: center;
        gap: 10px;
        background: var(--bg-secondary);
        border: 1.5px solid rgba(66, 133, 244, 0.3);
        border-radius: 28px;
        padding: 4px 6px 4px 16px;
        transition: all 0.2s ease;
        box-shadow: 0 2px 10px rgba(0,0,0,0.03);
    }
    .ai-input-pill-wrapper:focus-within {
        border-color: #4285F4;
        box-shadow: 0 0 0 3px rgba(66, 133, 244, 0.15);
        background: var(--bg-card);
    }
    .ai-input-pill-wrapper input {
        flex: 1;
        background: transparent;
        border: none;
        outline: none;
        color: var(--text-primary);
        font-size: 13.5px;
        padding: 6px 0;
    }
    .ai-input-pill-wrapper input::placeholder {
        color: var(--text-muted);
    }

    .ai-send-btn {
        border-radius: 20px;
        padding: 7px 16px;
        background: #4285F4;
        border: none;
        color: white;
        font-size: 12.5px;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        cursor: pointer;
        transition: all 0.2s ease;
        box-shadow: 0 2px 8px rgba(66, 133, 244, 0.3);
    }
    .ai-send-btn:hover {
        background: #3367D6;
        transform: translateY(-1px);
    }

    .ai-disclaimer-subline {
        font-size: 11px;
        color: var(--text-muted);
        text-align: center;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        opacity: 0.85;
    }

    <script src="../assets/js/utils.js"></script>
    <script src="../assets/js/notifications.js"></script>
    <script>
    let activeConversationId = null;

    function clearChat() {
        activeConversationId = null;
        const container = document.getElementById('chatMessages');
        container.innerHTML = `
            <div class="ai-message bot">
                <div class="ai-avatar" style="background: linear-gradient(135deg, #4285F4, #34A853); color: white;"><i class="fab fa-google"></i></div>
                <div class="ai-bubble google-overview-bubble">
                    <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px; font-size: 12px; font-weight: 700; color: #4285F4; text-transform: uppercase; letter-spacing: 0.5px;">
                        <i class="fab fa-google" style="color: #EA4335;"></i> Google-Style AI Overview
                    </div>
                    Hello <strong><?php echo htmlspecialchars($_SESSION['user']['first_name']); ?></strong>! Conversation reset. Ask any question below to get an instant, structured Google-style AI overview!
                </div>
            </div>
        `;
        const input = document.getElementById('userInput');
        input.value = '';
        input.focus();
    }

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
            const savedKey = localStorage.getItem('user_ai_api_key') || '';
            const headers = typeof getAuthHeaders === 'function' 
                ? getAuthHeaders({ 'Content-Type': 'application/json' }) 
                : { 'Content-Type': 'application/json' };
            const payload = { 
                question: question, 
                conversation_id: activeConversationId
            };
            if (savedKey && savedKey.trim().length > 10) {
                payload.api_key = savedKey.trim();
            }

            // Dynamically resolve API URL so it works in any directory structure
            let apiUrl = '../../backend/api/ai.php?path=assistant';
            if (window.location.pathname.toLowerCase().includes('/studentos-ai-project/')) {
                apiUrl = '/StudentOS-AI-project/backend/api/ai.php?path=assistant';
            }

            const res = await fetch(apiUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: headers,
                body: JSON.stringify(payload)
            });

            const botBubble = document.getElementById(typingId);

            if (!res.ok) {
                let errMsg = 'Server error (' + res.status + ')';
                try {
                    const errData = await res.json();
                    if (errData && errData.error) errMsg = errData.error;
                } catch(e) {}
                if (botBubble) {
                    botBubble.querySelector('.ai-bubble').innerHTML = `<div style="color: #EA4335;"><i class="fas fa-exclamation-triangle"></i> ${escapeHTML(errMsg)}</div>`;
                }
                return;
            }

            const data = await res.json();

            if (data && data.answer) {
                if (data.conversation_id) activeConversationId = data.conversation_id;
                botBubble.querySelector('.ai-bubble').innerHTML = formatGoogleAnswer(data.answer);
            } else if (data && data.error) {
                botBubble.querySelector('.ai-bubble').innerHTML = `<div style="color: #EA4335;"><i class="fas fa-exclamation-triangle"></i> ${escapeHTML(data.error)}</div>`;
            } else {
                botBubble.querySelector('.ai-bubble').innerHTML = 'I encountered an issue generating the Google-style overview. Please try rephrasing your search query.';
            }
        } catch (err) {
            console.error('AI Assistant Error:', err);
            const botBubble = document.getElementById(typingId);
            if (botBubble) {
                const msg = err && err.message ? err.message : 'Could not connect to AI service';
                botBubble.querySelector('.ai-bubble').innerHTML = `<div style="color: #EA4335;"><i class="fas fa-exclamation-circle"></i> Error: ${escapeHTML(msg)}. Please check your network or server status.</div>`;
            }
        }
        container.scrollTop = container.scrollHeight;
    }
    </script>
</body>
</html>
