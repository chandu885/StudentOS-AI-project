<?php
// frontend/components/navbar.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$user = $_SESSION['user'] ?? [
    'first_name' => 'Guest',
    'last_name' => 'User',
    'email' => 'guest@studentos.ai',
    'role_id' => 4,
    'role_name' => 'Student'
];
$initials = strtoupper(substr($user['first_name'] ?? 'U', 0, 1) . substr($user['last_name'] ?? 'S', 0, 1));
$portalPrefix = '';
$roleSlug = 'student';
if (($user['role_id'] ?? 4) == 1) {
    $portalPrefix = '/super-admin';
    $roleSlug = 'superadmin';
} elseif (($user['role_id'] ?? 4) == 2) {
    $portalPrefix = '/admin';
    $roleSlug = 'admin';
} elseif (($user['role_id'] ?? 4) == 3) {
    $portalPrefix = '/faculty';
    $roleSlug = 'faculty';
} else {
    $portalPrefix = '/student';
    $roleSlug = 'student';
}
?>
<header class="top-navbar">
    <div class="navbar-left">
        <a href="<?php echo htmlspecialchars(url(getDashboardUrl())); ?>" class="sidebar-brand navbar-brand" title="BSTUDENTOS Dashboard" style="margin-right: 16px; display: inline-flex; align-items: center; text-decoration: none;">
            <i class="fas fa-graduation-cap"></i>
            <span>BSTUDENTOS</span>
        </a>
        <button class="sidebar-toggle" onclick="toggleSidebar()" title="Toggle Sidebar" aria-label="Toggle Sidebar">
            <i class="fas fa-bars"></i>
        </button>
        
        <div class="nav-search-box">
            <i class="fas fa-search search-icon"></i>
            <input type="text" placeholder="Search anything... " id="globalSearchInput" onkeydown="if(event.key==='Enter') window.location.href='<?php echo htmlspecialchars(url($portalPrefix . '/search.php')); ?>?q=' + encodeURIComponent(this.value)">
            <span class="shortcut-badge">⌘K</span>
        </div>
    </div>

    <div class="navbar-right">
        <!-- Notification Bell -->
        <div style="position: relative;">
            <button class="nav-action-btn" id="notification-btn" onclick="toggleNotificationDropdown()" title="Notifications">
                <i class="fas fa-bell"></i>
                <span class="badge-dot" id="navbar-notif-badge" style="display: none;"></span>
            </button>
            <div class="dropdown-menu" id="notification-dropdown">
                <div style="padding: 10px 16px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
                    <strong style="font-size: 13px; color: var(--text-primary);">Notifications</strong>
                    <a href="<?php echo htmlspecialchars(url($portalPrefix . '/notifications.php')); ?>" style="font-size: 11px; color: var(--primary);">View All</a>
                </div>
                <div id="navbar-notif-list" style="max-height: 280px; overflow-y: auto;">
                    <div style="padding: 16px; text-align: center; color: var(--text-muted); font-size: 13px;">No new notifications</div>
                </div>
            </div>
        </div>

        <?php if (!empty($_SESSION['auth_token']) && !empty($_SESSION['user'])): ?>
        <!-- Dedicated Logout Button -->
        <a href="<?php echo htmlspecialchars(url('/logout.php')); ?>" class="nav-logout-btn btn btn-danger" role="button" title="Sign Out of Account" onclick="try{localStorage.removeItem('auth_token');localStorage.removeItem('session_token');sessionStorage.removeItem('auth_token');sessionStorage.removeItem('session_token');}catch(e){}">
            <i class="fas fa-sign-out-alt"></i>
            <span class="hide-mobile">Logout</span>
        </a>
        <?php else: ?>
        <!-- Dedicated Login Button -->
        <a href="<?php echo htmlspecialchars(url('/login.php')); ?>" class="btn btn-primary nav-login-btn" role="button" title="Sign In">
            <i class="fas fa-sign-in-alt"></i>
            <span class="hide-mobile">Sign In</span>
        </a>
        <?php endif; ?>

        <!-- User Profile Dropdown -->
        <div style="position: relative;">
            <div class="nav-profile-menu" onclick="toggleUserDropdown(event)">
                <div class="nav-avatar"><?php echo htmlspecialchars($initials); ?></div>
                <div style="display: flex; flex-direction: column; text-align: left;" class="hide-mobile">
                    <span style="font-size: 13px; font-weight: 600; color: var(--text-primary); line-height: 1.2;">
                        <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                    </span>
                    <span style="font-size: 11px; color: var(--text-muted);">
                        <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $user['role_name'] ?? 'User'))); ?>
                    </span>
                </div>
                <i class="fas fa-chevron-down" style="font-size: 10px; color: var(--text-muted); margin-left: 4px;"></i>
            </div>
            
            <div class="dropdown-menu" id="user-dropdown">
                <div style="padding: 10px 16px; border-bottom: 1px solid var(--border-color);">
                    <div style="font-weight: 600; font-size: 13px; color: var(--text-primary);"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></div>
                    <div style="font-size: 12px; color: var(--text-muted);"><?php echo htmlspecialchars($user['email'] ?? ''); ?></div>
                </div>
                <a href="<?php echo htmlspecialchars(url($portalPrefix . '/profile.php')); ?>" class="dropdown-item">
                    <i class="fas fa-user-circle"></i> View Profile
                </a>
                <a href="<?php echo htmlspecialchars(url($portalPrefix . '/profile.php?tab=security')); ?>" class="dropdown-item">
                    <i class="fas fa-key"></i> Change Password
                </a>
                <a href="<?php echo htmlspecialchars(url($portalPrefix . '/tasks.php')); ?>" class="dropdown-item">
                    <i class="fas fa-tasks"></i> My Tasks
                </a>
                <a href="<?php echo htmlspecialchars(url($portalPrefix . '/support.php')); ?>" class="dropdown-item">
                    <i class="fas fa-life-ring"></i> Get Help & Support
                </a>
                <div class="dropdown-divider"></div>
                <a href="<?php echo htmlspecialchars(url('/logout.php')); ?>" class="dropdown-item" style="color: var(--danger); font-weight: 600;" onclick="try{localStorage.removeItem('auth_token');localStorage.removeItem('session_token');sessionStorage.removeItem('auth_token');sessionStorage.removeItem('session_token');}catch(e){}">
                    <i class="fas fa-sign-out-alt"></i> Sign Out
                </a>
            </div>
        </div>
    </div>
</header>

<!-- Universal AI Photo/PDF/File Upload & Assistant Modal -->
<div class="modal-backdrop" id="navAiUploadModal" role="dialog" aria-modal="true" style="display: none;" onclick="if(event.target===this) closeNavAiUploadModal()">
    <div class="ai-modal-card">
        <div class="ai-modal-header">
            <div>
                <h3 class="ai-modal-title">
                    <i class="fas fa-plus-circle" style="color: #4285F4;"></i>
                    Ask AI with Photo, PDF or File
                </h3>
                <div class="ai-modal-sub">Upload or snap a photo, or choose PDFs & documents to get instant AI answers</div>
            </div>
            <button type="button" class="ai-modal-close-btn" onclick="closeNavAiUploadModal()" title="Close">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <div class="ai-modal-body">
            <!-- Hidden native file inputs -->
            <input type="file" id="navAiCameraInput" accept="image/*" capture="environment" style="display:none;" onchange="handleNavAiFileSelected(this)">
            <input type="file" id="navAiPhotoInput" accept="image/*" style="display:none;" onchange="handleNavAiFileSelected(this)">
            <input type="file" id="navAiDocInput" accept=".pdf,application/pdf,.txt,.doc,.docx,.csv,.py,.java,.c,.cpp,.js,.html,.json" style="display:none;" onchange="handleNavAiFileSelected(this)">

            <!-- 3 Intuitive Upload Source Action Cards -->
            <div class="ai-source-cards-grid" id="navAiSourceGrid">
                <button type="button" class="ai-source-card-btn" onclick="triggerNavCamera()">
                    <div class="ai-source-card-icon" style="background: rgba(234, 67, 53, 0.12); color: #EA4335;">
                        <i class="fas fa-camera"></i>
                    </div>
                    <div class="ai-source-card-title">Phone Camera</div>
                    <div class="ai-source-card-desc">Take live photo or snap homework</div>
                </button>

                <button type="button" class="ai-source-card-btn" onclick="triggerNavPhoto()">
                    <div class="ai-source-card-icon" style="background: rgba(52, 168, 83, 0.12); color: #34A853;">
                        <i class="fas fa-images"></i>
                    </div>
                    <div class="ai-source-card-title">Photos & Images</div>
                    <div class="ai-source-card-desc">Upload from laptop folder or gallery</div>
                </button>

                <button type="button" class="ai-source-card-btn" onclick="triggerNavDoc()">
                    <div class="ai-source-card-icon" style="background: rgba(66, 133, 244, 0.12); color: #4285F4;">
                        <i class="fas fa-file-pdf"></i>
                    </div>
                    <div class="ai-source-card-title">PDFs & Documents</div>
                    <div class="ai-source-card-desc">PDF, notes, syllabus, code files</div>
                </button>
            </div>

            <!-- Laptop Webcam Live Feed Container (Hidden by default) -->
            <div id="navAiWebcamBox" style="display: none; flex-direction: column; gap: 8px;">
                <div class="ai-webcam-preview-box">
                    <video id="navAiWebcamVideo" autoplay playsinline muted></video>
                </div>
                <div style="display: flex; gap: 8px; justify-content: center;">
                    <button type="button" class="btn btn-primary" onclick="captureNavWebcam()" style="font-size: 12px; padding: 6px 14px;">
                        <i class="fas fa-camera"></i> Capture Snapshot
                    </button>
                    <button type="button" class="btn btn-outline" onclick="stopNavWebcam()" style="font-size: 12px; padding: 6px 14px;">
                        Cancel Webcam
                    </button>
                </div>
            </div>

            <!-- Drag and Drop Dropzone -->
            <div class="ai-modal-dropzone" id="navAiDropzone" onclick="triggerNavPhoto()">
                <i class="fas fa-cloud-upload-alt" style="font-size: 24px; color: #4285F4; margin-bottom: 6px;"></i>
                <div style="font-size: 13px; font-weight: 600; color: var(--text-primary);">
                    Click or drag & drop files here
                </div>
                <div style="font-size: 11px; color: var(--text-muted); margin-top: 2px;">
                    Supports Photos (JPG, PNG, WEBP), PDFs, Notes, and Code files up to 25MB
                </div>
            </div>

            <!-- Selected File Preview Tray (Hidden until a file is selected) -->
            <div class="ai-modal-preview-box" id="navAiPreviewBox" style="display: none;">
                <div class="ai-modal-preview-left">
                    <img id="navAiPreviewImg" class="ai-modal-preview-thumb" src="" alt="Preview" style="display: none;">
                    <div id="navAiPreviewIcon" class="ai-modal-preview-icon" style="background: rgba(66, 133, 244, 0.1); color: #4285F4;">
                        <i class="fas fa-file-alt"></i>
                    </div>
                    <div class="ai-modal-preview-meta">
                        <span id="navAiPreviewName" class="ai-modal-preview-name">file.pdf</span>
                        <span id="navAiPreviewMeta" class="ai-modal-preview-size">0 KB</span>
                    </div>
                </div>
                <button type="button" class="ai-modal-close-btn" onclick="removeNavAiFile()" title="Remove file" style="color: var(--danger);">
                    <i class="fas fa-times-circle" style="font-size: 18px;"></i>
                </button>
            </div>

            <!-- Question / Prompt Input -->
            <div class="ai-modal-input-box">
                <label for="navAiQuestion" style="font-size: 12px; font-weight: 600; color: var(--text-primary); display: flex; align-items: center; gap: 6px;">
                    <i class="fas fa-comment-dots" style="color: #4285F4;"></i> What would you like AI to answer?
                </label>
                <textarea id="navAiQuestion" placeholder="E.g. Solve this problem step-by-step, summarize this document, explain what this diagram illustrates, or check code correctness... (Optional)"></textarea>
                
                <!-- Quick Suggestion Chips -->
                <div class="ai-modal-quick-chips">
                    <span style="font-size: 11px; color: var(--text-muted); font-weight: 600;">Quick Prompts:</span>
                    <button type="button" class="ai-modal-chip" onclick="setNavAiQuestion('Please provide a complete, verified step-by-step solution for this problem with all intermediate calculations.')">
                        ⚡ Step-by-Step Solution
                    </button>
                    <button type="button" class="ai-modal-chip" onclick="setNavAiQuestion('Provide a structured high-yield summary of this document with core takeaways and bullet points.')">
                        📝 Summarize Document
                    </button>
                    <button type="button" class="ai-modal-chip" onclick="setNavAiQuestion('Explain the core concepts, definitions, and theory shown in this file.')">
                        💡 Explain Concepts
                    </button>
                    <button type="button" class="ai-modal-chip" onclick="setNavAiQuestion('Extract all mathematical formulas, theorems, and code snippets from this file with brief explanations.')">
                        🔬 Formulas & Code
                    </button>
                </div>
            </div>

            <!-- Answer Box (Hidden until answer is generating) -->
            <div id="navAiAnswerContainer" style="display: none;">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px;">
                    <span style="font-size: 12px; font-weight: 700; color: #4285F4; text-transform: uppercase; letter-spacing: 0.5px; display: inline-flex; align-items: center; gap: 6px;">
                        <i class="fab fa-google" style="color: #EA4335;"></i> Google-Style AI Overview
                    </span>
                    <div style="display: flex; gap: 6px;">
                        <button type="button" class="btn btn-outline" onclick="copyNavAiAnswer()" style="padding: 2px 8px; font-size: 11px; height: 26px;">
                            <i class="fas fa-copy"></i> Copy
                        </button>
                        <button type="button" class="btn btn-outline" id="navAiOpenInFullBtn" onclick="openNavAiInFullAssistant()" style="padding: 2px 8px; font-size: 11px; height: 26px;">
                            <i class="fas fa-external-link-alt"></i> Full AI Chat
                        </button>
                    </div>
                </div>
                <div id="navAiAnswerContent" class="ai-modal-answer-box"></div>
            </div>
        </div>

        <div class="ai-modal-footer">
            <div style="font-size: 11px; color: var(--text-muted); display: flex; align-items: center; gap: 4px;">
                <i class="fab fa-google" style="color: #4285F4;"></i> Google Gemini Multimodal Engine
            </div>
            <div style="display: flex; gap: 8px;">
                <button type="button" class="btn btn-outline" onclick="closeNavAiUploadModal()" style="font-size: 12.5px; padding: 7px 14px;">
                    Close
                </button>
                <button type="button" class="btn btn-primary" id="navAiSubmitBtn" onclick="submitNavAiUpload()" style="font-size: 12.5px; padding: 7px 18px; font-weight: 600;">
                    <i class="fas fa-paper-plane"></i> <span>Get AI Answer</span>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let navSelectedFile = null;
let navWebcamStream = null;
let navLastAiAnswer = '';
let navLastConvId = null;

function openNavAiUploadModal(e) {
    if (e) e.stopPropagation();
    const modal = document.getElementById('navAiUploadModal');
    if (modal) {
        modal.style.display = 'flex';
        modal.classList.add('show');
        document.body.style.overflow = 'hidden';
    }
}

function closeNavAiUploadModal() {
    const modal = document.getElementById('navAiUploadModal');
    if (modal) {
        modal.style.display = 'none';
        modal.classList.remove('show');
        document.body.style.overflow = '';
    }
    stopNavWebcam();
}

function triggerNavCamera() {
    // Check if on mobile or if user prefers webcam
    const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
    const camInput = document.getElementById('navAiCameraInput');
    if (isMobile && camInput) {
        camInput.click();
    } else {
        // Offer webcam stream directly on laptop or trigger file picker if unavailable
        startNavWebcam();
    }
}

function triggerNavPhoto() {
    const photoInput = document.getElementById('navAiPhotoInput');
    if (photoInput) photoInput.click();
}

function triggerNavDoc() {
    const docInput = document.getElementById('navAiDocInput');
    if (docInput) docInput.click();
}

function setNavAiQuestion(txt) {
    const qInput = document.getElementById('navAiQuestion');
    if (qInput) {
        qInput.value = txt;
        qInput.focus();
    }
}

function handleNavAiFileSelected(input) {
    if (input.files && input.files[0]) {
        setNavAiFile(input.files[0]);
    }
}

function setNavAiFile(file) {
    navSelectedFile = file;
    stopNavWebcam();

    const previewBox = document.getElementById('navAiPreviewBox');
    const previewImg = document.getElementById('navAiPreviewImg');
    const previewIcon = document.getElementById('navAiPreviewIcon');
    const previewName = document.getElementById('navAiPreviewName');
    const previewMeta = document.getElementById('navAiPreviewMeta');
    const dropzone = document.getElementById('navAiDropzone');

    if (!file) {
        if (previewBox) previewBox.style.display = 'none';
        if (dropzone) dropzone.style.display = 'block';
        return;
    }

    if (dropzone) dropzone.style.display = 'none';
    if (previewBox) previewBox.style.display = 'flex';

    if (previewName) previewName.textContent = file.name;
    const sizeKb = (file.size / 1024).toFixed(1);
    const sizeStr = file.size > 1048576 ? (file.size / 1048576).toFixed(2) + ' MB' : sizeKb + ' KB';
    if (previewMeta) previewMeta.textContent = sizeStr + ' • ' + (file.type || 'file');

    if (file.type.startsWith('image/')) {
        const reader = new FileReader();
        reader.onload = function(e) {
            if (previewImg) {
                previewImg.src = e.target.result;
                previewImg.style.display = 'block';
            }
            if (previewIcon) previewIcon.style.display = 'none';
        };
        reader.readAsDataURL(file);
    } else {
        if (previewImg) previewImg.style.display = 'none';
        if (previewIcon) {
            previewIcon.style.display = 'flex';
            if (file.type.includes('pdf') || file.name.endsWith('.pdf')) {
                previewIcon.innerHTML = '<i class="fas fa-file-pdf" style="color: #EA4335;"></i>';
            } else {
                previewIcon.innerHTML = '<i class="fas fa-file-code" style="color: #4285F4;"></i>';
            }
        }
    }
}

function removeNavAiFile() {
    navSelectedFile = null;
    const previewBox = document.getElementById('navAiPreviewBox');
    const dropzone = document.getElementById('navAiDropzone');
    const camInput = document.getElementById('navAiCameraInput');
    const photoInput = document.getElementById('navAiPhotoInput');
    const docInput = document.getElementById('navAiDocInput');

    if (previewBox) previewBox.style.display = 'none';
    if (dropzone) dropzone.style.display = 'block';
    if (camInput) camInput.value = '';
    if (photoInput) photoInput.value = '';
    if (docInput) docInput.value = '';
}

// Laptop Webcam Live Controls
function startNavWebcam() {
    const box = document.getElementById('navAiWebcamBox');
    const video = document.getElementById('navAiWebcamVideo');
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        // Fallback to camera input file chooser
        const cam = document.getElementById('navAiCameraInput');
        if (cam) cam.click();
        return;
    }

    navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } })
        .then(function(stream) {
            navWebcamStream = stream;
            if (video) {
                video.srcObject = stream;
            }
            if (box) box.style.display = 'flex';
        })
        .catch(function(err) {
            console.warn('Webcam access error, falling back to file picker:', err);
            const photoInput = document.getElementById('navAiPhotoInput');
            if (photoInput) photoInput.click();
        });
}

function stopNavWebcam() {
    if (navWebcamStream) {
        navWebcamStream.getTracks().forEach(track => track.stop());
        navWebcamStream = null;
    }
    const box = document.getElementById('navAiWebcamBox');
    if (box) box.style.display = 'none';
}

function captureNavWebcam() {
    const video = document.getElementById('navAiWebcamVideo');
    if (!video) return;

    const canvas = document.createElement('canvas');
    canvas.width = video.videoWidth || 640;
    canvas.height = video.videoHeight || 480;
    const ctx = canvas.getContext('2d');
    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

    canvas.toBlob(function(blob) {
        if (blob) {
            const file = new File([blob], 'camera_capture_' + Date.now() + '.jpg', { type: 'image/jpeg' });
            setNavAiFile(file);
        }
        stopNavWebcam();
    }, 'image/jpeg', 0.92);
}

// Setup Drag & Drop handlers for dropzone
(function() {
    const dz = document.getElementById('navAiDropzone');
    if (!dz) return;

    ['dragenter', 'dragover'].forEach(eventName => {
        dz.addEventListener(eventName, function(e) {
            e.preventDefault();
            e.stopPropagation();
            dz.classList.add('dragover');
        }, false);
    });

    ['dragleave', 'drop'].forEach(eventName => {
        dz.addEventListener(eventName, function(e) {
            e.preventDefault();
            e.stopPropagation();
            dz.classList.remove('dragover');
        }, false);
    });

    dz.addEventListener('drop', function(e) {
        const dt = e.dataTransfer;
        if (dt && dt.files && dt.files[0]) {
            setNavAiFile(dt.files[0]);
        }
    }, false);
})();

// Markdown parser helper for Google-style answers
function formatAiModalAnswer(markdown) {
    if (!markdown) return '';
    let html = markdown;

    // Headers
    html = html.replace(/^###\s*(.*?)$/gm, '<h4 style="font-size: 13.5px; font-weight: 700; color: var(--text-primary); margin: 10px 0 4px 0; display:flex; align-items:center; gap:6px;">$1</h4>');
    html = html.replace(/^##\s*(.*?)$/gm, '<h3 style="font-size: 15px; font-weight: 700; color: #4285F4; margin: 12px 0 6px 0;">$1</h3>');

    // Bold & Italics
    html = html.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
    html = html.replace(/\*([^\*]+)\*/g, '<em>$1</em>');

    // Code blocks
    html = html.replace(/```([a-zA-Z0-9_-]*)\n([\s\S]*?)```/g, function(m, lang, code) {
        return `<pre style="background: var(--bg-secondary); border: 1px solid var(--border-color); padding: 10px; border-radius: 6px; overflow-x: auto; font-size: 12px; margin: 8px 0;"><code>${code.replace(/</g, '&lt;').replace(/>/g, '&gt;')}</code></pre>`;
    });

    // Bullets
    html = html.replace(/^[•\-\*]\s+(.*?)$/gm, '<div style="display:flex; gap:8px; margin:3px 0 3px 6px;"><i class="fas fa-circle" style="font-size:5px; color:#4285F4; margin-top:7px; flex-shrink:0;"></i><span>$1</span></div>');

    // Numbered lists
    html = html.replace(/^(\d+)\.\s+(.*?)$/gm, '<div style="display:flex; gap:8px; margin:3px 0 3px 6px;"><strong style="color:#4285F4; flex-shrink:0;">$1.</strong><span>$2</span></div>');

    // Linebreaks
    html = html.replace(/\n\n/g, '<div style="height:6px;"></div>');

    return html;
}

// Submit File + Question to Backend
async function submitNavAiUpload() {
    if (!navSelectedFile) {
        alert('Please select or capture a photo, PDF, or file first.');
        return;
    }

    const questionInput = document.getElementById('navAiQuestion');
    const question = questionInput ? questionInput.value.trim() : '';
    const submitBtn = document.getElementById('navAiSubmitBtn');
    const answerContainer = document.getElementById('navAiAnswerContainer');
    const answerContent = document.getElementById('navAiAnswerContent');

    if (answerContainer) answerContainer.style.display = 'block';
    if (answerContent) {
        answerContent.innerHTML = `
            <div style="display:flex; align-items:center; gap:10px; color:#4285F4; padding:12px 0;">
                <i class="fas fa-spinner fa-spin fa-lg"></i>
                <div>
                    <strong>Analyzing ${escapeHTML(navSelectedFile.name)}...</strong>
                    <div style="font-size: 11px; color: var(--text-muted);">Consulting Google Gemini Multimodal Engine...</div>
                </div>
            </div>
        `;
    }

    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <span>Analyzing...</span>';
    }

    try {
        const formData = new FormData();
        formData.append('file', navSelectedFile);
        formData.append('question', question);
        formData.append('stream', '1');

        const savedKey = localStorage.getItem('user_ai_api_key') || '';
        if (savedKey) formData.append('api_key', savedKey);

        // Resolve API URL
        let apiUrl = '../../backend/api/ai.php?path=upload-ask&stream=1';
        if (window.location.pathname.toLowerCase().includes('/studentos-ai-project/')) {
            apiUrl = '/StudentOS-AI-project/backend/api/ai.php?path=upload-ask&stream=1';
        }

        const headers = typeof getAuthHeaders === 'function' ? getAuthHeaders({}) : {};
        // Don't set Content-Type header on FormData; let browser set multipart boundary
        delete headers['Content-Type'];

        const res = await fetch(apiUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: Object.assign({}, headers, { 'Accept': 'text/event-stream, application/json' }),
            body: formData
        });

        if (!res.ok) {
            let errMsg = 'Server error (' + res.status + ')';
            try {
                const errData = await res.json();
                if (errData && errData.error) errMsg = errData.error;
            } catch(e) {}
            if (answerContent) {
                answerContent.innerHTML = `<div style="color: #EA4335;"><i class="fas fa-exclamation-triangle"></i> ${escapeHTML(errMsg)}</div>`;
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
                streamBuffer = lines.pop();

                for (const line of lines) {
                    const trimmed = line.trim();
                    if (trimmed.startsWith('data: ')) {
                        try {
                            const parsed = JSON.parse(trimmed.substring(6));
                            if (parsed.token) {
                                accumulatedText += parsed.token;
                                navLastAiAnswer = accumulatedText;
                                if (answerContent) {
                                    answerContent.innerHTML = formatAiModalAnswer(accumulatedText);
                                    answerContent.scrollTop = answerContent.scrollHeight;
                                }
                            }
                        } catch(e) {}
                    }
                }
            }

            if (!accumulatedText.trim() && answerContent) {
                answerContent.innerHTML = 'Completed analysis, but received an empty response. Please try again.';
            }
        } else {
            const data = await res.json();
            if (data && data.answer) {
                navLastAiAnswer = data.answer;
                if (data.conversation_id) navLastConvId = data.conversation_id;
                if (answerContent) {
                    answerContent.innerHTML = formatAiModalAnswer(data.answer);
                }
            } else if (data && data.error) {
                if (answerContent) {
                    answerContent.innerHTML = `<div style="color: #EA4335;"><i class="fas fa-exclamation-triangle"></i> ${escapeHTML(data.error)}</div>`;
                }
            }
        }
    } catch (err) {
        console.error('Nav AI Upload Error:', err);
        if (answerContent) {
            answerContent.innerHTML = `<div style="color: #EA4335;"><i class="fas fa-exclamation-circle"></i> Connection error: ${escapeHTML(err.message || 'Failed to communicate with AI server')}.</div>`;
        }
    } finally {
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> <span>Get AI Answer</span>';
        }
    }
}

function copyNavAiAnswer() {
    if (!navLastAiAnswer) return;
    navigator.clipboard.writeText(navLastAiAnswer).then(function() {
        if (typeof showToast === 'function') {
            showToast('AI Answer copied to clipboard!', 'success');
        } else {
            alert('AI Answer copied to clipboard!');
        }
    }).catch(function() {
        alert('Could not copy to clipboard.');
    });
}

function openNavAiInFullAssistant() {
    // Save state into sessionStorage so student/ai-assistant.php displays it
    try {
        if (navLastAiAnswer) {
            sessionStorage.setItem('pending_ai_file_answer', JSON.stringify({
                question: (document.getElementById('navAiQuestion') ? document.getElementById('navAiQuestion').value : ''),
                fileName: navSelectedFile ? navSelectedFile.name : 'Attached file',
                answer: navLastAiAnswer,
                convId: navLastConvId
            }));
        }
    } catch(e) {}

    let dest = '<?php echo htmlspecialchars(url('/student/ai-assistant.php')); ?>';
    window.location.href = dest;
}

function toggleUserDropdown(e) {
    if (e) e.stopPropagation();
    const userDropdown = document.getElementById('user-dropdown');
    const notifDropdown = document.getElementById('notification-dropdown');
    if (notifDropdown) notifDropdown.classList.remove('show');
    if (userDropdown) userDropdown.classList.toggle('show');
}

function toggleNotificationDropdown(e) {
    if (e) e.stopPropagation();
    const userDropdown = document.getElementById('user-dropdown');
    const notifDropdown = document.getElementById('notification-dropdown');
    if (userDropdown) userDropdown.classList.remove('show');
    if (notifDropdown) notifDropdown.classList.toggle('show');
}

// Close dropdowns on outside click
document.addEventListener('click', function(e) {
    const userDropdown = document.getElementById('user-dropdown');
    const notifDropdown = document.getElementById('notification-dropdown');
    if (userDropdown && !e.target.closest('.nav-profile-menu')) {
        userDropdown.classList.remove('show');
    }
    if (notifDropdown && !e.target.closest('#notification-btn')) {
        notifDropdown.classList.remove('show');
    }
});

// Shortcut listener for Ctrl+K global search
document.addEventListener('keydown', function(e) {
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
        e.preventDefault();
        const search = document.getElementById('globalSearchInput');
        if (search) search.focus();
    }
});

// Synchronize session tokens to localStorage for client-side API calls
(function() {
    try {
        <?php if (!empty($_SESSION['auth_token'])): ?>
            localStorage.setItem('auth_token', <?php echo json_encode($_SESSION['auth_token']); ?>);
            <?php if (!empty($_SESSION['session_token'])): ?>
            localStorage.setItem('session_token', <?php echo json_encode($_SESSION['session_token']); ?>);
            <?php endif; ?>
        <?php else: ?>
            localStorage.removeItem('auth_token');
            localStorage.removeItem('session_token');
        <?php endif; ?>
    } catch(e) {}
})();
</script>
