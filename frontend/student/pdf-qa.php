<?php
// frontend/student/pdf-qa.php - Interactive PDF Upload & RAG Document Q&A
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../../backend/utils/PDFExtractor.php';

requireRole('student');

$userId = (int)$_SESSION['user']['id'];
$db = getDbConnection();

$successMsg = '';
$errorMsg = '';

// Handle PDF Upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_pdf') {
    if (!isset($_FILES['pdf_file']) || $_FILES['pdf_file']['error'] !== UPLOAD_ERR_OK) {
        $errorMsg = 'Please select a valid PDF file to upload.';
    } else {
        $file = $_FILES['pdf_file'];
        $origName = $file['name'];
        $tmpPath = $file['tmp_name'];
        $fileSize = (int)$file['size'];
        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

        if ($ext !== 'pdf') {
            $errorMsg = 'Invalid file type. Only PDF documents (.pdf) are allowed.';
        } elseif ($fileSize > 15 * 1024 * 1024) {
            $errorMsg = 'File is too large. Maximum PDF file size is 15MB.';
        } else {
            // Ensure target directory exists
            $uploadDir = realpath(__DIR__ . '/../../storage') . '/uploads/documents';
            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0777, true);
            }

            $safeBase = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($origName, PATHINFO_FILENAME));
            $safeName = 'doc_' . time() . '_' . substr($safeBase, 0, 20) . '_' . bin2hex(random_bytes(3)) . '.pdf';
            $destPath = $uploadDir . '/' . $safeName;
            $dbPath = 'storage/uploads/documents/' . $safeName;

            if (move_uploaded_file($tmpPath, $destPath)) {
                // Extract plain text
                $extractedText = PDFExtractor::extractText($destPath);
                $title = trim($_POST['title'] ?? '');
                if (empty($title)) {
                    $title = str_replace(['_', '-'], ' ', pathinfo($origName, PATHINFO_FILENAME));
                    $title = ucwords(trim($title)) . ' (PDF)';
                }
                $desc = trim($_POST['description'] ?? '');
                if (empty($desc)) {
                    $wordCount = str_word_count($extractedText);
                    $desc = "Uploaded by student on " . date('M d, Y') . " (~$wordCount words extracted).";
                }

                if ($db) {
                    $stmt = $db->prepare(
                        "INSERT INTO documents (user_id, title, file_path, file_size, file_type, description, is_public, created_at, updated_at) 
                         VALUES (?, ?, ?, ?, 'application/pdf', ?, 0, NOW(), NOW())"
                    );
                    if ($stmt) {
                        $stmt->bind_param("issis", $userId, $title, $dbPath, $fileSize, $desc);
                        $stmt->execute();
                        $newDocId = $stmt->insert_id;
                        $stmt->close();

                        // Chunk and index text into document_chunks
                        $chunks = PDFExtractor::chunkText($extractedText, 300, 40);
                        if (empty($chunks)) {
                            $chunks = [
                                "Document: $title\n\nUploaded PDF document file ($origName). Content index ready for semantic questions and course review."
                            ];
                        }

                        $chunkStmt = $db->prepare("INSERT INTO document_chunks (document_id, chunk_index, chunk_text, created_at) VALUES (?, ?, ?, NOW())");
                        if ($chunkStmt) {
                            foreach ($chunks as $idx => $chunkStr) {
                                $cIdx = $idx + 1;
                                $chunkStmt->bind_param("iis", $newDocId, $cIdx, $chunkStr);
                                $chunkStmt->execute();
                            }
                            $chunkStmt->close();
                        }

                        $chunkCount = count($chunks);
                        $successMsg = "🎉 PDF \"$title\" uploaded successfully and indexed into $chunkCount semantic chunks! You can now ask questions based on it.";
                        header("Location: pdf-qa.php?doc_id=$newDocId&msg=" . urlencode($successMsg));
                        exit;
                    } else {
                        $errorMsg = "Database error while saving document metadata.";
                    }
                }
            } else {
                $errorMsg = 'Failed to move uploaded file. Please check folder permissions.';
            }
        }
    }
}

// Handle Delete PDF (only user's own uploads)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_pdf') {
    $delDocId = (int)($_POST['document_id'] ?? 0);
    if ($delDocId > 0 && $db) {
        // Verify ownership
        $stmt = $db->prepare("SELECT file_path FROM documents WHERE id = ? AND user_id = ? AND is_public = 0");
        if ($stmt) {
            $stmt->bind_param("ii", $delDocId, $userId);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($row = $res->fetch_assoc()) {
                $filePath = realpath(__DIR__ . '/../../') . '/' . $row['file_path'];
                if (file_exists($filePath)) {
                    @unlink($filePath);
                }
                $stmt->close();

                // Delete chunks and record
                $db->query("DELETE FROM document_chunks WHERE document_id = $delDocId");
                $db->query("DELETE FROM documents WHERE id = $delDocId");
                $successMsg = "Document deleted successfully.";
                header("Location: pdf-qa.php?msg=" . urlencode($successMsg));
                exit;
            } else {
                $errorMsg = "Unauthorized or document cannot be deleted.";
                $stmt->close();
            }
        }
    }
}

// Check flash message in URL
if (isset($_GET['msg'])) {
    $successMsg = sanitize($_GET['msg']);
}

// Fetch all available documents (public + student's own)
$documents = [];
$activeDoc = null;

if ($db) {
    $sql = "SELECT d.id, d.title, d.description, d.file_path, d.file_size, d.is_public, d.user_id, d.created_at,
            (SELECT COUNT(*) FROM document_chunks dc WHERE dc.document_id = d.id) as chunk_count
            FROM documents d 
            WHERE d.is_public = 1 OR d.user_id = ? 
            ORDER BY (d.user_id = ?) DESC, d.id DESC";
    $stmt = $db->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("ii", $userId, $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $documents[$row['id']] = $row;
        }
        $stmt->close();
    }
}

$firstDocId = !empty($documents) ? (int)array_key_first($documents) : 0;
$docId = isset($_GET['doc_id']) && isset($documents[(int)$_GET['doc_id']]) ? (int)$_GET['doc_id'] : $firstDocId;
$activeDoc = $documents[$docId] ?? null;
$activeDocTitle = $activeDoc['title'] ?? 'Selected Document';
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
    <style>
    .pdf-upload-box {
        border: 2px dashed rgba(66, 133, 244, 0.4);
        background: rgba(66, 133, 244, 0.03);
        border-radius: var(--radius-lg);
        padding: 24px;
        text-align: center;
        transition: all 0.2s ease;
        cursor: pointer;
    }
    .pdf-upload-box:hover, .pdf-upload-box.dragover {
        border-color: #4285F4;
        background: rgba(66, 133, 244, 0.08);
    }
    .pdf-active-card {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 16px;
        padding: 16px 20px;
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: var(--radius-lg);
        margin-bottom: 20px;
    }
    .doc-pill {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 11px;
        font-weight: 600;
        padding: 3px 8px;
        border-radius: 12px;
    }
    .google-doc-overview {
        background: var(--bg-card) !important;
        border: 1px solid rgba(66, 133, 244, 0.25) !important;
        border-radius: 12px !important;
        padding: 18px 22px !important;
        box-shadow: 0 4px 16px rgba(66, 133, 244, 0.05);
        color: var(--text-primary) !important;
        max-width: 85% !important;
        line-height: 1.65;
    }
    .citation-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        font-size: 11px;
        background: rgba(16, 185, 129, 0.1);
        color: var(--success);
        border: 1px solid rgba(16, 185, 129, 0.3);
        padding: 2px 8px;
        border-radius: 10px;
        margin-right: 6px;
    }
    </style>
</head>
<body>
    <div class="dashboard-layout">
        <?php include_once __DIR__ . '/../components/sidebar.php'; ?>
        
        <main class="dashboard-main">
            <?php include_once __DIR__ . '/../components/navbar.php'; ?>
            
            <div class="dashboard-content">
                <div class="page-header" style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 14px;">
                    <div>
                        <h1><i class="fas fa-file-pdf" style="color: #EF4444; margin-right: 8px;"></i> PDF Q&A (Document RAG)</h1>
                        <p class="page-subtitle">Upload course lecture notes or textbooks, ask questions, and receive Google-style answers with verified citations</p>
                    </div>
                    <button class="btn btn-primary" onclick="toggleUploadModal()" style="display: inline-flex; align-items: center; gap: 8px; border-radius: 20px;">
                        <i class="fas fa-cloud-upload-alt"></i> Upload New PDF
                    </button>
                </div>

                <?php if (!empty($successMsg)): ?>
                    <div class="alert alert-success" style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px;">
                        <div><i class="fas fa-check-circle" style="margin-right: 8px;"></i> <?php echo htmlspecialchars($successMsg); ?></div>
                        <button type="button" onclick="this.parentElement.style.display='none'" style="background:none;border:none;color:inherit;cursor:pointer;font-size:16px;">&times;</button>
                    </div>
                <?php endif; ?>

                <?php if (!empty($errorMsg)): ?>
                    <div class="alert alert-danger" style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px;">
                        <div><i class="fas fa-exclamation-circle" style="margin-right: 8px;"></i> <?php echo htmlspecialchars($errorMsg); ?></div>
                        <button type="button" onclick="this.parentElement.style.display='none'" style="background:none;border:none;color:inherit;cursor:pointer;font-size:16px;">&times;</button>
                    </div>
                <?php endif; ?>

                <!-- Active Document Info Card -->
                <?php if (!empty($activeDoc)): ?>
                    <div class="pdf-active-card">
                        <div style="display: flex; align-items: center; gap: 14px;">
                            <div style="width: 44px; height: 44px; border-radius: 10px; background: rgba(239, 68, 68, 0.1); display: flex; align-items: center; justify-content: center; color: #EF4444; font-size: 22px;">
                                <i class="fas fa-file-pdf"></i>
                            </div>
                            <div>
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <strong style="font-size: 15px; color: var(--text-primary);"><?php echo htmlspecialchars($activeDoc['title']); ?></strong>
                                    <?php if ($activeDoc['user_id'] == $userId): ?>
                                        <span class="doc-pill" style="background: rgba(66, 133, 244, 0.1); color: #4285F4;"><i class="fas fa-user"></i> My Upload</span>
                                    <?php else: ?>
                                        <span class="doc-pill" style="background: rgba(16, 185, 129, 0.1); color: var(--success);"><i class="fas fa-book"></i> Textbook Chapter</span>
                                    <?php endif; ?>
                                </div>
                                <div style="font-size: 12px; color: var(--text-muted); margin-top: 3px; display: flex; gap: 14px; flex-wrap: wrap;">
                                    <span><i class="fas fa-layer-group"></i> <?php echo (int)($activeDoc['chunk_count'] ?? 0); ?> Indexed Chunks</span>
                                    <span><i class="fas fa-hdd"></i> <?php echo round(((int)$activeDoc['file_size'])/1024, 1); ?> KB</span>
                                    <span><i class="fas fa-calendar-alt"></i> Added <?php echo date('M d, Y', strtotime($activeDoc['created_at'])); ?></span>
                                </div>
                            </div>
                        </div>

                        <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                            <!-- Document Switcher Dropdown -->
                            <div style="display: flex; align-items: center; gap: 6px;">
                                <label style="font-size: 12px; color: var(--text-muted); font-weight: 600;">Switch PDF:</label>
                                <select class="form-control" style="width: auto; height: 36px; padding: 4px 10px; font-size: 13px;" onchange="window.location.href='pdf-qa.php?doc_id=' + this.value">
                                    <?php foreach ($documents as $d): ?>
                                        <option value="<?php echo $d['id']; ?>" <?php echo $d['id'] === $docId ? 'selected' : ''; ?>>
                                            <?php echo ($d['user_id'] == $userId ? '👤 ' : '📚 ') . htmlspecialchars($d['title']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <?php if ($activeDoc['user_id'] == $userId): ?>
                                <form method="POST" action="pdf-qa.php" onsubmit="return confirm('Delete this uploaded PDF and all its indexed chunks?')" style="display: inline;">
                                    <input type="hidden" name="action" value="delete_pdf">
                                    <input type="hidden" name="document_id" value="<?php echo $activeDoc['id']; ?>">
                                    <button type="submit" class="btn btn-outline" style="color: var(--danger); border-color: rgba(239, 68, 68, 0.4); padding: 6px 12px; font-size: 12px;" title="Delete this PDF">
                                        <i class="fas fa-trash-alt"></i> Delete
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Quick Sample Questions for this PDF -->
                    <div style="display: flex; gap: 8px; margin-bottom: 16px; overflow-x: auto; padding-bottom: 4px;">
                        <button class="btn btn-outline" style="font-size: 12px; padding: 6px 14px; white-space: nowrap; border-radius: 20px;" onclick="fillAndSend('Can you summarize this entire document in 3 key takeaways?')">
                            📄 Summarize Document
                        </button>
                        <button class="btn btn-outline" style="font-size: 12px; padding: 6px 14px; white-space: nowrap; border-radius: 20px;" onclick="fillAndSend('What are the core definitions and concepts explained in this PDF?')">
                            🔍 Core Definitions
                        </button>
                        <button class="btn btn-outline" style="font-size: 12px; padding: 6px 14px; white-space: nowrap; border-radius: 20px;" onclick="fillAndSend('What are the important formulas, algorithms, or rules mentioned?')">
                            💡 Key Rules & Formulas
                        </button>
                        <button class="btn btn-outline" style="font-size: 12px; padding: 6px 14px; white-space: nowrap; border-radius: 20px;" onclick="fillAndSend('Generate 3 exam-style revision questions based on this document.')">
                            ❓ Exam Practice Questions
                        </button>
                    </div>

                    <!-- Chat Box -->
                    <div class="ai-chat-box" style="height: 520px;">
                        <div class="ai-chat-messages" id="ragMessages">
                            <div class="ai-message bot">
                                <div class="ai-avatar" style="background: #EF4444; color: white;"><i class="fas fa-file-pdf"></i></div>
                                <div class="ai-bubble google-doc-overview">
                                    <div style="display: flex; align-items: center; gap: 6px; font-size: 11px; font-weight: 700; color: #4285F4; text-transform: uppercase; margin-bottom: 8px;">
                                        <i class="fab fa-google" style="color: #EA4335;"></i> Document RAG Engine
                                    </div>
                                    I am ready to answer questions grounded in <strong><?php echo htmlspecialchars($activeDocTitle); ?></strong>. Ask me to explain any definitions, locate specific sections, or summarize key chapters!
                                </div>
                            </div>
                        </div>

                        <div class="ai-chat-input-bar" style="border-radius: 28px; box-shadow: 0 4px 14px rgba(0,0,0,0.06); border: 1px solid rgba(66, 133, 244, 0.25);">
                            <i class="fas fa-search" style="color: #EF4444; margin-left: 8px;"></i>
                            <input type="text" id="ragInput" placeholder="Ask any question based on this PDF (e.g. 'What is the definition of...', 'Explain chapter 2')..." onkeydown="if(event.key==='Enter') sendRagQuestion()">
                            <button class="btn btn-primary" onclick="sendRagQuestion()" style="border-radius: 20px; padding: 8px 20px; background: #EF4444; border-color: #EF4444;">
                                <i class="fas fa-paper-plane"></i> Ask PDF
                            </button>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- No documents state -->
                    <div class="card" style="text-align: center; padding: 50px 20px;">
                        <div style="width: 70px; height: 70px; border-radius: 50%; background: rgba(239, 68, 68, 0.1); color: #EF4444; font-size: 32px; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px;">
                            <i class="fas fa-file-pdf"></i>
                        </div>
                        <h3 style="font-size: 18px; margin-bottom: 8px;">No PDF Documents Available</h3>
                        <p style="color: var(--text-muted); max-width: 460px; margin: 0 auto 20px; font-size: 14px;">
                            Upload your lecture notes, textbook chapters, or research papers as a PDF to ask questions and receive instant AI answers.
                        </p>
                        <button class="btn btn-primary" onclick="toggleUploadModal()" style="border-radius: 20px;">
                            <i class="fas fa-cloud-upload-alt"></i> Upload Your First PDF
                        </button>
                    </div>
                <?php endif; ?>

            </div>
            <?php include_once __DIR__ . '/../components/footer.php'; ?>
        </main>
    </div>

    <!-- Upload PDF Modal -->
    <div class="modal-backdrop" id="uploadPdfModal" style="display: none; align-items: center; justify-content: center; z-index: 1050; position: fixed; inset: 0; background: rgba(0,0,0,0.6);">
        <div class="modal-card" style="background: var(--bg-card); width: 100%; max-width: 520px; border-radius: var(--radius-lg); border: 1px solid var(--border-color); overflow: hidden; box-shadow: 0 20px 40px rgba(0,0,0,0.3);">
            <div style="padding: 16px 20px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
                <h3 style="margin: 0; font-size: 16px; display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-file-pdf" style="color: #EF4444;"></i> Upload PDF Document
                </h3>
                <button type="button" onclick="toggleUploadModal()" style="background:none;border:none;font-size:20px;color:var(--text-muted);cursor:pointer;">&times;</button>
            </div>

            <form method="POST" action="pdf-qa.php" enctype="multipart/form-data" id="pdfUploadForm" style="padding: 20px;">
                <input type="hidden" name="action" value="upload_pdf">

                <div class="pdf-upload-box" id="dropZone" onclick="document.getElementById('pdfFileInput').click()">
                    <i class="fas fa-cloud-upload-alt" style="font-size: 40px; color: #4285F4; margin-bottom: 10px;"></i>
                    <p style="font-weight: 600; font-size: 14px; margin-bottom: 4px; color: var(--text-primary);" id="uploadPrompt">
                        Click or Drag & Drop PDF file here
                    </p>
                    <span style="font-size: 12px; color: var(--text-muted);" id="fileSelectedName">Maximum size: 15MB • PDF format only</span>
                    <input type="file" name="pdf_file" id="pdfFileInput" accept="application/pdf,.pdf" style="display: none;" required onchange="handleFileSelected(this)">
                </div>

                <div class="form-group" style="margin-top: 16px;">
                    <label style="font-size: 12px; font-weight: 600; color: var(--text-secondary); margin-bottom: 4px; display: block;">Document Title (Optional)</label>
                    <input type="text" name="title" id="pdfTitleInput" class="form-control" placeholder="e.g., Operating Systems Lecture 4 - Deadlocks">
                </div>

                <div class="form-group" style="margin-top: 12px;">
                    <label style="font-size: 12px; font-weight: 600; color: var(--text-secondary); margin-bottom: 4px; display: block;">Brief Description (Optional)</label>
                    <textarea name="description" class="form-control" rows="2" placeholder="e.g., Exam unit notes covering Banker's algorithm and critical section problem."></textarea>
                </div>

                <div id="uploadingSpinner" style="display: none; padding: 12px; background: rgba(66, 133, 244, 0.08); border-radius: 8px; margin-top: 14px; text-align: center; font-size: 13px; color: #4285F4;">
                    <i class="fas fa-spinner fa-spin" style="margin-right: 6px;"></i> Extracting text & indexing semantic chunks...
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px;">
                    <button type="button" class="btn btn-secondary" onclick="toggleUploadModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="uploadSubmitBtn" style="background: #4285F4; border-color: #4285F4;">
                        <i class="fas fa-cloud-upload-alt"></i> Upload & Index PDF
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="../assets/js/utils.js"></script>
    <script src="../assets/js/notifications.js"></script>
    <script>
    const currentDocId = <?php echo $docId; ?>;

    function toggleUploadModal() {
        const modal = document.getElementById('uploadPdfModal');
        if (modal.style.display === 'none' || !modal.style.display) {
            modal.style.display = 'flex';
        } else {
            modal.style.display = 'none';
        }
    }

    function handleFileSelected(input) {
        if (input.files && input.files[0]) {
            const file = input.files[0];
            document.getElementById('uploadPrompt').innerText = 'Selected: ' + file.name;
            document.getElementById('fileSelectedName').innerText = (file.size / (1024 * 1024)).toFixed(2) + ' MB • Ready to index';
            
            const titleInput = document.getElementById('pdfTitleInput');
            if (!titleInput.value) {
                let clean = file.name.replace(/\.[^/.]+$/, "").replace(/[_-]/g, ' ');
                titleInput.value = clean.charAt(0).toUpperCase() + clean.slice(1);
            }
        }
    }

    // Drag and drop handlers
    const dropZone = document.getElementById('dropZone');
    if (dropZone) {
        ['dragenter', 'dragover'].forEach(eventName => {
            dropZone.addEventListener(eventName, (e) => {
                e.preventDefault();
                dropZone.classList.add('dragover');
            }, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, (e) => {
                e.preventDefault();
                dropZone.classList.remove('dragover');
            }, false);
        });

        dropZone.addEventListener('drop', (e) => {
            const dt = e.dataTransfer;
            const files = dt.files;
            if (files && files[0] && files[0].type === 'application/pdf') {
                document.getElementById('pdfFileInput').files = files;
                handleFileSelected(document.getElementById('pdfFileInput'));
            } else {
                alert('Please drop a valid PDF file.');
            }
        });
    }

    // Show spinner on submit
    const uploadForm = document.getElementById('pdfUploadForm');
    if (uploadForm) {
        uploadForm.addEventListener('submit', function() {
            document.getElementById('uploadingSpinner').style.display = 'block';
            document.getElementById('uploadSubmitBtn').disabled = true;
        });
    }

    function fillAndSend(text) {
        const input = document.getElementById('ragInput');
        if (input) {
            input.value = text;
            sendRagQuestion();
        }
    }

    function formatRagAnswer(text, sources) {
        if (!text) return '';

        let html = text;

        // Replace headers
        html = html.replace(/^###\s*(.*?)$/gm, '<h4 style="font-size: 14px; font-weight: 700; color: var(--text-primary); margin: 12px 0 6px 0;">$1</h4>');
        html = html.replace(/^##\s*(.*?)$/gm, '<h3 style="font-size: 15px; font-weight: 700; color: #EF4444; margin: 14px 0 8px 0;">$1</h3>');

        // Quick Answer card styling
        html = html.replace(/\*\*Quick Answer:\*\*\s*(.*?)(?=\n\n|\n###|$)/s, '<div style="background:rgba(239,68,68,0.05);border-left:4px solid #EF4444;padding:10px 14px;border-radius:0 8px 8px 0;margin:8px 0 12px;"><strong style="color:#EF4444;">Quick Answer:</strong> $1</div>');

        // Bold & Italics
        html = html.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
        html = html.replace(/\*([^\*]+)\*/g, '<em>$1</em>');

        // Bullets
        html = html.replace(/^[•\-\*]\s+(.*?)$/gm, '<div style="display:flex; gap:8px; margin:4px 0 4px 8px;"><i class="fas fa-check-circle" style="font-size:10px; color:#10B981; margin-top:5px; flex-shrink:0;"></i><span>$1</span></div>');

        // Linebreaks
        html = html.replace(/\n\n/g, '<div style="height:6px;"></div>');
        html = html.replace(/\n/g, '<br>');

        let sourcesHtml = '';
        if (sources && sources.length > 0) {
            sourcesHtml = `
                <div style="margin-top: 12px; padding-top: 8px; border-top: 1px solid var(--border-color); font-size: 12px; color: var(--text-muted); display:flex; align-items:center; flex-wrap:wrap; gap:6px;">
                    <strong style="color:var(--text-secondary);"><i class="fas fa-bookmark"></i> Verified Sources:</strong>
                    ${sources.map(s => `<span class="citation-badge"><i class="fas fa-file-alt"></i> ${escapeHTML(s)}</span>`).join('')}
                </div>
            `;
        }

        return `
            <div style="display:inline-flex;align-items:center;gap:6px;font-size:11px;font-weight:700;color:#EF4444;background:rgba(239,68,68,0.08);padding:3px 8px;border-radius:12px;margin-bottom:8px;">
                <i class="fas fa-file-pdf"></i> Verified PDF Synthesis
            </div>
            ${html}
            ${sourcesHtml}
        `;
    }

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
                <div class="ai-avatar" style="background: #EF4444; color:white;"><i class="fas fa-file-pdf"></i></div>
                <div class="ai-bubble google-doc-overview">
                    <i class="fas fa-spinner fa-spin" style="color: #EF4444;"></i> Searching document chunks & synthesizing answer...
                </div>
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
                botBubble.querySelector('.ai-bubble').innerHTML = formatRagAnswer(data.answer, data.sources || []);
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
