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
                        <!-- Attachment Preview Tray -->
                        <div id="chatAttachmentTray" class="ai-chat-attachment-tray" style="display: none;">
                            <div class="ai-attachment-chip">
                                <img id="chatAttachThumb" class="ai-chip-thumb" src="" alt="Thumbnail" style="display: none;">
                                <div id="chatAttachIcon" class="ai-chip-icon" style="display: none;">
                                    <i class="fas fa-file-pdf"></i>
                                </div>
                                <div class="ai-chip-meta">
                                    <span id="chatAttachName" class="ai-chip-name">filename.png</span>
                                    <span id="chatAttachSize" class="ai-chip-size">1.2 MB</span>
                                </div>
                                <button type="button" class="ai-chip-remove" onclick="removeChatAttachment()" title="Remove file">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>

                        <div class="ai-input-pill-wrapper">
                            <!-- Plus Button Dropdown Next to Search Input -->
                            <div class="ai-plus-container">
                                <button type="button" class="ai-plus-circle-btn" id="chatPlusBtn" onclick="toggleChatPlusMenu(event)" title="Upload photo, PDF or file from camera or folders" aria-label="Upload photo, PDF or file">
                                    <i class="fas fa-plus"></i>
                                </button>
                                <div class="ai-plus-popup-menu" id="chatPlusMenu">
                                    <label class="ai-plus-option-row">
                                        <div class="ai-option-text">
                                            <div class="ai-option-title">Camera</div>
                                        </div>
                                        <input type="file" id="chatCameraInput" accept="image/*" capture="environment" style="display:none;" onchange="handleChatFileSelected(this)">
                                    </label>
                                    <label class="ai-plus-option-row">
                                        <div class="ai-option-text">
                                            <div class="ai-option-title">Photos</div>
                                        </div>
                                        <input type="file" id="chatPhotoInput" accept="image/*" style="display:none;" onchange="handleChatFileSelected(this)">
                                    </label>
                                    <label class="ai-plus-option-row">
                                        <div class="ai-option-text">
                                            <div class="ai-option-title">PDF</div>
                                        </div>
                                        <input type="file" id="chatDocInput" accept=".pdf,application/pdf,.txt,.doc,.docx,.csv,.py,.java,.c,.cpp,.js,.html,.json" style="display:none;" onchange="handleChatFileSelected(this)">
                                    </label>
                                </div>
                            </div>

                            <i class="fas fa-search" style="color: #4285F4; font-size: 14px;"></i>
                            <input type="text" id="userInput" placeholder="Ask anything, or upload photos, PDFs & files ..." onkeydown="if(event.key==='Enter') sendMessage()" autofocus>
                            <button class="ai-send-btn" id="sendBtn" onclick="sendMessage()">
                                <i class="fas fa-search"></i> <span>Search</span>
                            </button>
                        </div>
                        <div class="ai-disclaimer-subline">
                            <span><i class="fab fa-google" style="color: #4285F4;"></i> Google Gemini 3.6 Flash</span> • <span>StudentOS AI Multimodal</span> • <span>Verify critical academic concepts with core syllabus</span>
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
    let chatSelectedFile = null;
    let chatSelectedFileDataUrl = null;

    function toggleChatPlusMenu(e) {
        if (e) e.stopPropagation();
        const menu = document.getElementById('chatPlusMenu');
        if (menu) menu.classList.toggle('show');
    }

    document.addEventListener('click', function(e) {
        const menu = document.getElementById('chatPlusMenu');
        if (menu && !e.target.closest('.ai-plus-container')) {
            menu.classList.remove('show');
        }
    });

    function handleChatFileSelected(input) {
        if (input.files && input.files[0]) {
            setChatFile(input.files[0]);
        }
        const menu = document.getElementById('chatPlusMenu');
        if (menu) menu.classList.remove('show');
    }

    function setChatFile(file) {
        chatSelectedFile = file;
        const tray = document.getElementById('chatAttachmentTray');
        const thumb = document.getElementById('chatAttachThumb');
        const icon = document.getElementById('chatAttachIcon');
        const name = document.getElementById('chatAttachName');
        const size = document.getElementById('chatAttachSize');

        if (!file) {
            if (tray) tray.style.display = 'none';
            chatSelectedFileDataUrl = null;
            return;
        }

        if (tray) tray.style.display = 'flex';
        if (name) name.textContent = file.name;
        const sizeKb = (file.size / 1024).toFixed(1);
        const sizeStr = file.size > 1048576 ? (file.size / 1048576).toFixed(2) + ' MB' : sizeKb + ' KB';
        if (size) size.textContent = sizeStr;

        if (file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = function(e) {
                chatSelectedFileDataUrl = e.target.result;
                if (thumb) {
                    thumb.src = e.target.result;
                    thumb.style.display = 'block';
                }
                if (icon) icon.style.display = 'none';
            };
            reader.readAsDataURL(file);
        } else {
            chatSelectedFileDataUrl = null;
            if (thumb) thumb.style.display = 'none';
            if (icon) {
                icon.style.display = 'flex';
                if (file.type.includes('pdf') || file.name.endsWith('.pdf')) {
                    icon.innerHTML = '<i class="fas fa-file-pdf" style="color: #EA4335;"></i>';
                } else {
                    icon.innerHTML = '<i class="fas fa-file-alt" style="color: #4285F4;"></i>';
                }
            }
        }
    }

    function removeChatAttachment() {
        chatSelectedFile = null;
        chatSelectedFileDataUrl = null;
        const tray = document.getElementById('chatAttachmentTray');
        if (tray) tray.style.display = 'none';

        const cCam = document.getElementById('chatCameraInput');
        const cPhoto = document.getElementById('chatPhotoInput');
        const cDoc = document.getElementById('chatDocInput');
        if (cCam) cCam.value = '';
        if (cPhoto) cPhoto.value = '';
        if (cDoc) cDoc.value = '';
    }

    // Drag & Drop support onto chat box
    (function() {
        const chatBox = document.querySelector('.ai-chat-box');
        if (!chatBox) return;

        ['dragenter', 'dragover'].forEach(name => {
            chatBox.addEventListener(name, function(e) {
                e.preventDefault();
                e.stopPropagation();
            });
        });

        chatBox.addEventListener('drop', function(e) {
            e.preventDefault();
            e.stopPropagation();
            if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0]) {
                setChatFile(e.dataTransfer.files[0]);
            }
        });
    })();

    // Clipboard Paste support (Ctrl+V) for screenshots and images
    window.addEventListener('paste', function(e) {
        if (e.clipboardData && e.clipboardData.items) {
            for (let i = 0; i < e.clipboardData.items.length; i++) {
                const item = e.clipboardData.items[i];
                if (item.type.indexOf('image') !== -1) {
                    const blob = item.getAsFile();
                    if (blob) {
                        const file = new File([blob], 'screenshot_' + Date.now() + '.png', { type: blob.type });
                        setChatFile(file);
                        break;
                    }
                }
            }
        }
    });

    function clearChat() {
        activeConversationId = null;
        removeChatAttachment();
        const container = document.getElementById('chatMessages');
        container.innerHTML = `
            <div class="ai-message bot">
                <div class="ai-avatar" style="background: linear-gradient(135deg, #4285F4, #34A853); color: white;"><i class="fab fa-google"></i></div>
                <div class="ai-bubble google-overview-bubble">
                    <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px; font-size: 12px; font-weight: 700; color: #4285F4; text-transform: uppercase; letter-spacing: 0.5px;">
                        <i class="fab fa-google" style="color: #EA4335;"></i> Google-Style AI Overview
                    </div>
                    Hello <strong><?php echo htmlspecialchars($_SESSION['user']['first_name']); ?></strong>! Conversation reset. Ask any question or upload a photo, PDF, or document to get an instant, structured overview!
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

        // Bold & Italics
        html = html.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
        html = html.replace(/\*([^\*]+)\*/g, '<em>$1</em>');

        // Code blocks
        html = html.replace(/```([a-zA-Z0-9_-]*)\n([\s\S]*?)```/g, function(m, lang, code) {
            return `<pre style="background: var(--bg-secondary); border: 1px solid var(--border-color); padding: 12px; border-radius: 8px; overflow-x: auto; font-size: 12.5px; margin: 10px 0;"><code>${code.replace(/</g, '&lt;').replace(/>/g, '&gt;')}</code></pre>`;
        });

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
        let question = input.value.trim();
        const hasAttachment = !!chatSelectedFile;

        if (!question && !hasAttachment) return;

        if (!question && hasAttachment) {
            question = "Please analyze this uploaded file/photo in detail and provide a complete structured explanation.";
        }

        input.value = '';
        const container = document.getElementById('chatMessages');

        // User message bubble with attachment preview if applicable
        let attachmentBubbleHtml = '';
        const currentFile = chatSelectedFile;
        const currentDataUrl = chatSelectedFileDataUrl;

        if (currentFile) {
            const sizeKb = (currentFile.size / 1024).toFixed(1);
            const sizeStr = currentFile.size > 1048576 ? (currentFile.size / 1048576).toFixed(2) + ' MB' : sizeKb + ' KB';

            if (currentDataUrl && currentFile.type.startsWith('image/')) {
                attachmentBubbleHtml = `
                    <div class="chat-attachment-bubble-preview">
                        <img src="${currentDataUrl}" class="chat-bubble-thumb" alt="Attachment">
                    </div>
                `;
            } else {
                attachmentBubbleHtml = `
                    <div class="chat-attachment-bubble-preview">
                        <div class="chat-bubble-doc-icon"><i class="fas fa-file-pdf"></i></div>
                        <div>
                            <div style="font-weight:600; font-size:12px;">${escapeHTML(currentFile.name)}</div>
                            <div style="font-size:10px; opacity:0.8;">${sizeStr}</div>
                        </div>
                    </div>
                `;
            }
        }

        container.innerHTML += `
            <div class="ai-message user">
                <div class="ai-avatar"><i class="fas fa-user"></i></div>
                <div class="ai-bubble">
                    ${attachmentBubbleHtml}
                    <div>${escapeHTML(question)}</div>
                </div>
            </div>
        `;

        // Add typing placeholder
        const typingId = 'typing-' + Date.now();
        const loadingPrompt = currentFile 
            ? `Analyzing ${escapeHTML(currentFile.name)} with Google Gemini AI...` 
            : `Searching & synthesizing Google-style answer...`;

        container.innerHTML += `
            <div class="ai-message bot" id="${typingId}">
                <div class="ai-avatar" style="background: linear-gradient(135deg, #4285F4, #34A853); color:white;"><i class="fab fa-google"></i></div>
                <div class="ai-bubble google-overview-bubble">
                    <i class="fas fa-spinner fa-spin" style="color:#4285F4;"></i> ${loadingPrompt}
                </div>
            </div>
        `;
        container.scrollTop = container.scrollHeight;

        // Clear attachment tray
        removeChatAttachment();

        try {
            const savedKey = localStorage.getItem('user_ai_api_key') || '';
            let apiUrl = '../../backend/api/ai.php?path=assistant&stream=1';
            if (window.location.pathname.toLowerCase().includes('/studentos-ai-project/')) {
                apiUrl = '/StudentOS-AI-project/backend/api/ai.php?path=assistant&stream=1';
            }

            let fetchOptions = {
                method: 'POST',
                credentials: 'same-origin'
            };

            if (currentFile) {
                // Use multipart FormData for file upload
                apiUrl = apiUrl.replace('path=assistant', 'path=upload-ask');
                const formData = new FormData();
                formData.append('file', currentFile);
                formData.append('question', question);
                formData.append('stream', '1');
                if (activeConversationId) formData.append('conversation_id', activeConversationId);
                if (savedKey) formData.append('api_key', savedKey);

                const headers = typeof getAuthHeaders === 'function' ? getAuthHeaders({}) : {};
                delete headers['Content-Type'];

                fetchOptions.headers = Object.assign({}, headers, { 'Accept': 'text/event-stream, application/json' });
                fetchOptions.body = formData;
            } else {
                // Standard JSON payload
                const headers = typeof getAuthHeaders === 'function' 
                    ? getAuthHeaders({ 'Content-Type': 'application/json' }) 
                    : { 'Content-Type': 'application/json' };
                const payload = { 
                    question: question, 
                    conversation_id: activeConversationId,
                    stream: 1
                };
                if (savedKey && savedKey.trim().length > 10) {
                    payload.api_key = savedKey.trim();
                }

                fetchOptions.headers = Object.assign({}, headers, { 'Accept': 'text/event-stream, application/json' });
                fetchOptions.body = JSON.stringify(payload);
            }

            const res = await fetch(apiUrl, fetchOptions);
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
                    botBubble.querySelector('.ai-bubble').innerHTML = 'I encountered an issue analyzing this request. Please try rephrasing or re-uploading.';
                }
            } else {
                const data = await res.json();
                if (data && data.answer) {
                    if (data.conversation_id) activeConversationId = data.conversation_id;
                    botBubble.querySelector('.ai-bubble').innerHTML = formatGoogleAnswer(data.answer);
                } else if (data && data.error) {
                    botBubble.querySelector('.ai-bubble').innerHTML = `<div style="color: #EA4335;"><i class="fas fa-exclamation-triangle"></i> ${escapeHTML(data.error)}</div>`;
                } else {
                    botBubble.querySelector('.ai-bubble').innerHTML = 'I encountered an issue analyzing this request. Please try rephrasing or re-uploading.';
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

    // Auto-load question and answer passed from the top navbar modal if any
    document.addEventListener('DOMContentLoaded', function() {
        try {
            const pending = sessionStorage.getItem('pending_ai_file_answer');
            if (pending) {
                sessionStorage.removeItem('pending_ai_file_answer');
                const parsed = JSON.parse(pending);
                if (parsed && parsed.answer) {
                    if (parsed.convId) activeConversationId = parsed.convId;
                    const container = document.getElementById('chatMessages');
                    if (container) {
                        container.innerHTML += `
                            <div class="ai-message user">
                                <div class="ai-avatar"><i class="fas fa-user"></i></div>
                                <div class="ai-bubble">
                                    <div class="chat-attachment-bubble-preview">
                                        <div class="chat-bubble-doc-icon"><i class="fas fa-file-alt"></i></div>
                                        <div><strong>${escapeHTML(parsed.fileName || 'Uploaded file')}</strong></div>
                                    </div>
                                    <div>${escapeHTML(parsed.question || 'Please analyze this uploaded file.')}</div>
                                </div>
                            </div>
                            <div class="ai-message bot">
                                <div class="ai-avatar" style="background: linear-gradient(135deg, #4285F4, #34A853); color:white;"><i class="fab fa-google"></i></div>
                                <div class="ai-bubble google-overview-bubble">
                                    ${formatGoogleAnswer(parsed.answer)}
                                </div>
                            </div>
                        `;
                        container.scrollTop = container.scrollHeight;
                    }
                }
            }
        } catch(e) {}
    });
    </script>
</body>
</html>
