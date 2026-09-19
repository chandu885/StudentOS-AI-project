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
            let apiUrl = '../../backend/api/ai.php?path=assistant&stream=1';
            if (window.location.pathname.toLowerCase().includes('/studentos-ai-project/')) {
                apiUrl = '/StudentOS-AI-project/backend/api/ai.php?path=assistant&stream=1';
            }
            payload.stream = 1;

            const res = await fetch(apiUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: Object.assign({}, headers, { 'Accept': 'text/event-stream, application/json' }),
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

            const contentType = res.headers.get('content-type') || '';

            if (contentType.includes('text/event-stream') && res.body) {
                const reader = res.body.getReader();
                const decoder = new TextDecoder('utf-8');
                let accumulatedText = '';
                let streamBuffer = '';

                while (true) {
                    const { done, value } = await reader.read();
                    if (done) break;
                    streamBuffer += decoder.decode(value, { stream: true });
                    const lines = streamBuffer.split('\n');
                    streamBuffer = lines.pop(); // keep last incomplete line

                    for (const line of lines) {
                        const trimmed = line.trim();
                        if (trimmed.startsWith('data: ')) {
                            try {
                                const parsed = JSON.parse(trimmed.substring(6));
                                if (parsed.token) {
                                    accumulatedText += parsed.token;
                                    botBubble.querySelector('.ai-bubble').innerHTML = formatGoogleAnswer(accumulatedText);
                                    container.scrollTop = container.scrollHeight;
                                }
                            } catch(e) {}
                        }
                    }
                }

                if (!accumulatedText.trim()) {
                    botBubble.querySelector('.ai-bubble').innerHTML = 'I encountered an issue generating the Google-style overview. Please try rephrasing your search query.';
                }
            } else {
                const data = await res.json();
                if (data && data.answer) {
                    if (data.conversation_id) activeConversationId = data.conversation_id;
                    botBubble.querySelector('.ai-bubble').innerHTML = formatGoogleAnswer(data.answer);
                } else if (data && data.error) {
                    botBubble.querySelector('.ai-bubble').innerHTML = `<div style="color: #EA4335;"><i class="fas fa-exclamation-triangle"></i> ${escapeHTML(data.error)}</div>`;
                } else {
                    botBubble.querySelector('.ai-bubble').innerHTML = 'I encountered an issue generating the Google-style overview. Please try rephrasing your search query.';
                }
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
