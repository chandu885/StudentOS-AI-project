<?php
// frontend/student/pdf-qa.php - Interactive PDF Upload & RAG Document Q&A
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
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
    $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
        || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
        || isset($_POST['ajax']);

    if (!isset($_FILES['pdf_file'])) {
        $errorMsg = 'No PDF file was provided. Please select a file to upload.';
    } elseif ($_FILES['pdf_file']['error'] !== UPLOAD_ERR_OK) {
        $errCode = (int)$_FILES['pdf_file']['error'];
        switch ($errCode) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $errorMsg = 'The uploaded file exceeds the allowed upload limit (Max 25MB).';
                break;
            case UPLOAD_ERR_PARTIAL:
                $errorMsg = 'The file was only partially uploaded. Please try uploading again.';
                break;
            case UPLOAD_ERR_NO_FILE:
                $errorMsg = 'Please select a valid PDF file to upload.';
                break;
            case UPLOAD_ERR_NO_TMP_DIR:
                $errorMsg = 'Server configuration error: Missing temporary upload directory.';
                break;
            case UPLOAD_ERR_CANT_WRITE:
                $errorMsg = 'Server write error: Failed to write uploaded file to disk.';
                break;
            default:
                $errorMsg = 'Upload failed with error code: ' . $errCode;
                break;
        }
    } else {
        $file = $_FILES['pdf_file'];
        $origName = $file['name'];
        $tmpPath = $file['tmp_name'];
        $fileSize = (int)$file['size'];
        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

        if ($ext !== 'pdf') {
            $errorMsg = 'Invalid file format. Only PDF documents (.pdf) are allowed.';
        } elseif ($fileSize <= 0) {
            $errorMsg = 'The selected file is empty.';
        } elseif ($fileSize > 25 * 1024 * 1024) {
            $errorMsg = 'File is too large. Maximum PDF file size is 25MB.';
        } else {
            // Ensure target directory exists
            $baseStorage = realpath(__DIR__ . '/../../storage') ?: (__DIR__ . '/../../storage');
            $uploadDir = rtrim($baseStorage, '/\\') . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'documents';
            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0777, true);
            }

            $safeBase = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($origName, PATHINFO_FILENAME));
            $safeName = 'doc_' . time() . '_' . substr($safeBase, 0, 20) . '_' . bin2hex(random_bytes(3)) . '.pdf';
            $destPath = $uploadDir . DIRECTORY_SEPARATOR . $safeName;
            $dbPath = 'storage/uploads/documents/' . $safeName;

            $moved = @move_uploaded_file($tmpPath, $destPath);
            if (!$moved && (php_sapi_name() === 'cli' || !is_uploaded_file($tmpPath))) {
                $moved = @copy($tmpPath, $destPath) || @rename($tmpPath, $destPath);
            }

            if ($moved) {
                // Extract plain text safely
                try {
                    $extractedText = PDFExtractor::extractText($destPath);
                } catch (Throwable $pe) {
                    $extractedText = '';
                    error_log('PDF Text Extraction Warning: ' . $pe->getMessage());
                }

                $title = trim($_POST['title'] ?? '');
                if (empty($title)) {
                    $cleanBase = pathinfo($origName, PATHINFO_FILENAME);
                    $cleanBase = preg_replace('/[_\-]+/', ' ', $cleanBase);
                    $title = ucwords(trim($cleanBase)) . ' (PDF)';
                }
                $desc = trim($_POST['description'] ?? '');
                if (empty($desc)) {
                    $wordCount = !empty($extractedText) ? str_word_count($extractedText) : 0;
                    $desc = "Uploaded by student on " . date('M d, Y') . " (~$wordCount words extracted).";
                }

                if ($db) {
                    try {
                        $stmt = $db->prepare(
                            "INSERT INTO documents (user_id, title, file_path, file_size, file_type, description, is_public, created_at, updated_at) 
                             VALUES (?, ?, ?, ?, 'application/pdf', ?, 0, NOW(), NOW())"
                        );
                        if (!$stmt) {
                            throw new Exception("Database prepare failed: " . $db->error);
                        }
                        $stmt->bind_param("issis", $userId, $title, $dbPath, $fileSize, $desc);
                        if (!$stmt->execute()) {
                            throw new Exception("Database insert failed: " . $stmt->error);
                        }
                        $newDocId = $stmt->insert_id;
                        $stmt->close();

                        // Chunk and index text into document_chunks
                        $chunks = PDFExtractor::chunkText($extractedText, 300, 40);
                        if (empty($chunks)) {
                            $chunks = [
                                "Document: $title\n\nUploaded PDF document ($origName). Content index ready for semantic questions and course review."
                            ];
                        }

                        $chunkStmt = $db->prepare("INSERT INTO document_chunks (document_id, chunk_index, chunk_text, created_at) VALUES (?, ?, ?, NOW())");
                        if ($chunkStmt) {
                            foreach ($chunks as $idx => $chunkStr) {
                                $cIdx = $idx + 1;
                                if (function_exists('mb_convert_encoding')) {
                                    $chunkStr = mb_convert_encoding($chunkStr, 'UTF-8', 'UTF-8');
                                }
                                $chunkStr = str_replace("\0", '', $chunkStr);
                                $chunkStmt->bind_param("iis", $newDocId, $cIdx, $chunkStr);
                                $chunkStmt->execute();
                            }
                            $chunkStmt->close();
                        }

                        $chunkCount = count($chunks);
                        $successMsg = "PDF \"$title\" uploaded successfully and indexed into $chunkCount semantic chunks! You can now ask questions based on it.";

                        if ($isAjax) {
                            header('Content-Type: application/json; charset=utf-8');
                            echo json_encode([
                                'success' => true,
                                'message' => $successMsg,
                                'doc_id' => $newDocId,
                                'redirect_url' => "pdf-qa.php?doc_id=$newDocId&msg=" . urlencode($successMsg)
                            ]);
                            exit;
                        }

                        header("Location: pdf-qa.php?doc_id=$newDocId&msg=" . urlencode($successMsg));
                        exit;
                    } catch (Throwable $e) {
                        $errorMsg = "Database error while saving document: " . $e->getMessage();
                    }
                } else {
                    $errorMsg = "Database connection unavailable.";
                }
            } else {
                $errorMsg = 'Failed to move uploaded file. Please check folder permissions for storage/uploads/documents.';
            }
        }
    }

    if (!empty($errorMsg) && $isAjax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'error' => $errorMsg
        ]);
        exit;
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
<?php
$bodyClass = 'ai-app-screen-mode';
$pageTitle = 'PDF Q&A / Document RAG - StudentOS AI';
include_once __DIR__ . '/../components/header.php';
?>

                <!-- Sleek Document RAG Topbar -->
                <div class="pdf-app-topbar">
                    <div class="pdf-topbar-left">
                        <div class="pdf-icon-box">
                            <i class="fas fa-file-pdf"></i>
                        </div>
                        <div class="pdf-doc-meta">
                            <?php if (!empty($activeDoc)): ?>
                                <div class="pdf-topbar-title-row">
                                    <h1 class="pdf-topbar-heading" title="<?php echo htmlspecialchars($activeDoc['title']); ?>">
                                        <?php echo htmlspecialchars($activeDoc['title']); ?>
                                    </h1>
                                    <?php if ($activeDoc['user_id'] == $userId): ?>
                                        <span class="doc-pill" style="background: rgba(66, 133, 244, 0.12); color: #4285F4;"><i class="fas fa-user"></i> My Upload</span>
                                    <?php else: ?>
                                        <span class="doc-pill" style="background: rgba(16, 185, 129, 0.12); color: var(--success);"><i class="fas fa-book"></i> Textbook</span>
                                    <?php endif; ?>
                                </div>
                                <div class="pdf-topbar-sub">
                                    <span><i class="fas fa-layer-group"></i> <?php echo (int)($activeDoc['chunk_count'] ?? 0); ?> Chunks</span>
                                    <span><i class="fas fa-hdd"></i> <?php echo round(((int)$activeDoc['file_size'])/1024, 1); ?> KB</span>
                                    <span><i class="fas fa-calendar-alt"></i> Added <?php echo date('M d, Y', strtotime($activeDoc['created_at'])); ?></span>
                                </div>
                            <?php else: ?>
                                <div class="pdf-topbar-title-row">
                                    <h1 class="pdf-topbar-heading">Document RAG &amp; PDF Q&amp;A</h1>
                                </div>
                                <div class="pdf-topbar-sub">Grounded semantic search &amp; AI question answering</div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if (!empty($activeDoc)): ?>

                    <?php endif; ?>

                    <div class="pdf-topbar-right">
                        <?php if (count($documents) > 1): ?>
                            <select class="pdf-switcher-select" onchange="window.location.href='pdf-qa.php?doc_id=' + this.value" title="Switch active PDF">
                                <?php foreach ($documents as $d): ?>
                                    <option value="<?php echo $d['id']; ?>" <?php echo $d['id'] === $docId ? 'selected' : ''; ?>>
                                        <?php echo ($d['user_id'] == $userId ? '👤 ' : '📚 ') . htmlspecialchars($d['title']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>

                        <?php if (!empty($activeDoc) && $activeDoc['user_id'] == $userId): ?>
                            <form method="POST" action="pdf-qa.php" onsubmit="return confirm('Delete this uploaded PDF and all its indexed chunks?')" style="display: inline; margin: 0;">
                                <input type="hidden" name="action" value="delete_pdf">
                                <input type="hidden" name="document_id" value="<?php echo $activeDoc['id']; ?>">
                                <button type="submit" class="pdf-delete-btn" title="Delete this uploaded PDF">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </form>
                        <?php endif; ?>

                        <button type="button" class="pdf-upload-btn" onclick="toggleUploadModal()" title="Upload a new PDF">
                            <i class="fas fa-cloud-upload-alt"></i> <span>Upload PDF</span>
                        </button>

                        <?php if (!empty($activeDoc)): ?>
                            <button type="button" class="pdf-reset-btn" onclick="clearRagChat()" title="Reset chat history">
                                <i class="fas fa-redo-alt"></i> <span>Reset</span>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (!empty($successMsg)): ?>
                    <div class="pdf-alert-banner alert-success">
                        <div><i class="fas fa-check-circle" style="margin-right: 8px;"></i> <?php echo htmlspecialchars($successMsg); ?></div>
                        <button type="button" onclick="this.parentElement.style.display='none'" style="background:none;border:none;color:inherit;cursor:pointer;font-size:16px;">&times;</button>
                    </div>
                <?php endif; ?>

                <?php if (!empty($errorMsg)): ?>
                    <div class="pdf-alert-banner alert-danger">
                        <div><i class="fas fa-exclamation-circle" style="margin-right: 8px;"></i> <?php echo htmlspecialchars($errorMsg); ?></div>
                        <button type="button" onclick="this.parentElement.style.display='none'" style="background:none;border:none;color:inherit;cursor:pointer;font-size:16px;">&times;</button>
                    </div>
                <?php endif; ?>

                <?php if (!empty($activeDoc)): ?>
                    <!-- Chat Box Container (Flex Full Height) -->
                    <div class="ai-chat-box">
                        <div class="ai-chat-messages" id="ragMessages">
                            <div class="ai-message bot">
                                <div class="ai-avatar" style="background: #EF4444; color: white;"><i class="fas fa-file-pdf"></i></div>
                                <div class="ai-bubble google-doc-overview">
                                    <div style="display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-bottom: 6px;">
                                        <div style="display: flex; align-items: center; gap: 6px; font-size: 11px; font-weight: 700; color: #EF4444; text-transform: uppercase; letter-spacing: 0.5px;">
                                            <i class="fas fa-file-pdf"></i> Grounded Document RAG Engine
                                        </div>
                                        <span style="font-size: 10.5px; color: var(--text-muted);"><i class="fas fa-layer-group"></i> <?php echo (int)($activeDoc['chunk_count'] ?? 0); ?> chunks indexed</span>
                                    </div>
                                    I am ready to answer questions strictly grounded in <strong><?php echo htmlspecialchars($activeDocTitle); ?></strong>. You can ask me to explain definitions, locate specific sections, summarize key chapters, or generate practice questions based on this document!
                                </div>
                            </div>
                        </div>

                        <div class="ai-chat-input-bar">
                            <div class="pdf-input-pill-wrapper">
                                <i class="fas fa-search" style="color: #EF4444; font-size: 14px;"></i>
                                <input type="text" id="ragInput" placeholder="Ask any question grounded in this PDF..." onkeydown="if(event.key==='Enter') sendRagQuestion()" autofocus>
                                <button class="pdf-send-btn" id="ragSendBtn" onclick="sendRagQuestion()">
                                    <i class="fas fa-paper-plane"></i> <span>Ask PDF</span>
                                </button>
                            </div>
                            <div class="pdf-disclaimer-subline">
                                <span><i class="fas fa-file-pdf" style="color: #EF4444;"></i> <?php echo htmlspecialchars($activeDocTitle); ?></span> &bull; <span>Semantic RAG Indexing</span> &bull; <span>Answers cited directly from document text</span>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- No documents state -->
                    <div class="pdf-empty-card">
                        <div style="width: 64px; height: 64px; border-radius: 50%; background: rgba(239, 68, 68, 0.1); color: #EF4444; font-size: 28px; display: flex; align-items: center; justify-content: center; margin-bottom: 14px;">
                            <i class="fas fa-file-pdf"></i>
                        </div>
                        <h3 style="font-size: 18px; margin-bottom: 8px; font-weight: 700; color: var(--text-primary);">No PDF Documents Available</h3>
                        <p style="color: var(--text-muted); max-width: 440px; margin: 0 auto 18px; font-size: 13.5px; line-height: 1.5;">
                            Upload your lecture notes, textbook chapters, or research papers as a PDF to ask questions and receive instant AI answers grounded in your course materials.
                        </p>
                        <button class="pdf-upload-btn" onclick="toggleUploadModal()" style="height: 38px; padding: 0 20px; font-size: 13px;">
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
                <button type="button" onclick="toggleUploadModal()" style="background:none;border:none;font-size:20px;color:var(--text-muted);cursor:pointer;" aria-label="Close">&times;</button>
            </div>

            <form method="POST" action="pdf-qa.php" enctype="multipart/form-data" id="pdfUploadForm" style="padding: 20px;">
                <input type="hidden" name="action" value="upload_pdf">

                <div id="uploadModalAlert" style="display: none; padding: 10px 14px; border-radius: 8px; font-size: 13px; margin-bottom: 14px;"></div>

                <label for="pdfFileInput" class="pdf-upload-box" id="dropZone" style="display: block; width: 100%; box-sizing: border-box; cursor: pointer; text-align: center; border: 2px dashed rgba(66, 133, 244, 0.4); background: rgba(66, 133, 244, 0.03); border-radius: var(--radius-lg); padding: 24px; transition: all 0.2s ease;">
                    <i class="fas fa-cloud-upload-alt" style="font-size: 40px; color: #4285F4; margin-bottom: 10px; display: block;"></i>
                    <p style="font-weight: 600; font-size: 14px; margin-bottom: 4px; color: var(--text-primary);" id="uploadPrompt">
                        Click to browse or Drag &amp; Drop PDF file here
                    </p>
                    <span style="font-size: 12px; color: var(--text-muted); display: block;" id="fileSelectedName">Maximum size: 25MB &bull; PDF format only</span>
                    <input type="file" name="pdf_file" id="pdfFileInput" accept="application/pdf,.pdf" class="pdf-accessible-input" required onchange="handleFileSelected(this)">
                </label>

                <div class="form-group" style="margin-top: 16px;">
                    <label style="font-size: 12px; font-weight: 600; color: var(--text-secondary); margin-bottom: 4px; display: block;">Document Title (Optional)</label>
                    <input type="text" name="title" id="pdfTitleInput" class="form-control" placeholder="e.g., Operating Systems Lecture 4 - Deadlocks">
                </div>

                <div class="form-group" style="margin-top: 12px;">
                    <label style="font-size: 12px; font-weight: 600; color: var(--text-secondary); margin-bottom: 4px; display: block;">Brief Description (Optional)</label>
                    <textarea name="description" id="pdfDescInput" class="form-control" rows="2" placeholder="e.g., Exam unit notes covering Banker's algorithm and critical section problem."></textarea>
                </div>

                <div id="uploadingProgressContainer" style="display: none; margin-top: 16px; padding: 14px; background: rgba(66, 133, 244, 0.06); border: 1px solid rgba(66, 133, 244, 0.2); border-radius: 8px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; font-size: 12.5px; font-weight: 600; color: #4285F4;">
                        <span id="uploadStatusText"><i class="fas fa-spinner fa-spin" style="margin-right: 6px;"></i> Uploading PDF...</span>
                        <span id="uploadProgressPct">0%</span>
                    </div>
                    <div style="width: 100%; height: 8px; background: rgba(66, 133, 244, 0.15); border-radius: 4px; overflow: hidden;">
                        <div id="uploadProgressBar" style="width: 0%; height: 100%; background: #4285F4; border-radius: 4px; transition: width 0.2s ease;"></div>
                    </div>
                    <div id="uploadSubText" style="font-size: 11px; color: var(--text-muted); margin-top: 6px; text-align: center;">Extracting text &amp; creating semantic chunks for RAG AI...</div>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px;">
                    <button type="button" class="btn btn-secondary" id="uploadCancelBtn" onclick="toggleUploadModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="uploadSubmitBtn" style="background: #4285F4; border-color: #4285F4;">
                        <i class="fas fa-cloud-upload-alt"></i> Upload &amp; Index PDF
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="../assets/js/utils.js"></script>
    <script src="../assets/js/notifications.js"></script>
    <script>
    const currentDocId = <?php echo (int)$docId; ?>;
    const activeDocChunks = <?php echo (int)($activeDoc['chunk_count'] ?? 0); ?>;
    const activeDocTitle = <?php echo json_encode($activeDocTitle); ?>;

    function escapeHTML(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function toggleUploadModal() {
        const modal = document.getElementById('uploadPdfModal');
        if (!modal) return;
        const isHidden = (modal.style.display === 'none' || !modal.style.display);
        if (isHidden) {
            modal.style.display = 'flex';
            hideModalError();
        } else {
            modal.style.display = 'none';
        }
    }

    function showModalError(msg) {
        const alertBox = document.getElementById('uploadModalAlert');
        if (alertBox) {
            alertBox.className = 'pdf-alert-banner alert-danger';
            alertBox.style.display = 'block';
            alertBox.style.background = 'rgba(239, 68, 68, 0.1)';
            alertBox.style.color = '#EF4444';
            alertBox.style.border = '1px solid rgba(239, 68, 68, 0.3)';
            alertBox.innerHTML = '<i class="fas fa-exclamation-circle" style="margin-right: 6px;"></i> ' + escapeHTML(msg);
        }
    }

    function hideModalError() {
        const alertBox = document.getElementById('uploadModalAlert');
        if (alertBox) {
            alertBox.style.display = 'none';
            alertBox.innerHTML = '';
        }
    }

    function handleFileSelected(input) {
        const files = input && input.files ? input.files : [];
        if (files.length > 0) {
            const file = files[0];
            const isPdf = file.name.toLowerCase().endsWith('.pdf') || file.type === 'application/pdf';
            if (!isPdf) {
                showModalError('Please select a valid PDF document (.pdf).');
                input.value = '';
                document.getElementById('uploadPrompt').innerText = 'Click to browse or Drag & Drop PDF file here';
                document.getElementById('fileSelectedName').innerText = 'Maximum size: 25MB • PDF format only';
                return;
            }
            if (file.size > 25 * 1024 * 1024) {
                showModalError('File is too large (max 25MB). Please select a smaller PDF.');
                input.value = '';
                document.getElementById('uploadPrompt').innerText = 'Click to browse or Drag & Drop PDF file here';
                document.getElementById('fileSelectedName').innerText = 'Maximum size: 25MB • PDF format only';
                return;
            }
            hideModalError();
            document.getElementById('uploadPrompt').innerText = 'Selected: ' + file.name;
            document.getElementById('fileSelectedName').innerText = (file.size / (1024 * 1024)).toFixed(2) + ' MB • Ready to upload & index';
            
            const titleInput = document.getElementById('pdfTitleInput');
            if (titleInput && !titleInput.value) {
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
                e.stopPropagation();
                dropZone.classList.add('dragover');
            }, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, (e) => {
                e.preventDefault();
                e.stopPropagation();
                dropZone.classList.remove('dragover');
            }, false);
        });

        dropZone.addEventListener('drop', (e) => {
            const dt = e.dataTransfer;
            const files = dt && dt.files ? dt.files : null;
            if (files && files.length > 0) {
                const file = files[0];
                const isPdf = file.name.toLowerCase().endsWith('.pdf') || file.type === 'application/pdf';
                if (isPdf) {
                    const fileInput = document.getElementById('pdfFileInput');
                    fileInput.files = files;
                    handleFileSelected(fileInput);
                } else {
                    showModalError('Please drop a valid PDF document (.pdf).');
                }
            }
        });
    }

    // AJAX Form submission with real-time progress bar
    const uploadForm = document.getElementById('pdfUploadForm');
    if (uploadForm) {
        uploadForm.addEventListener('submit', function(e) {
            e.preventDefault();
            hideModalError();

            const fileInput = document.getElementById('pdfFileInput');
            if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
                showModalError('Please select a PDF file first.');
                return;
            }

            const file = fileInput.files[0];
            const isPdf = file.name.toLowerCase().endsWith('.pdf') || file.type === 'application/pdf';
            if (!isPdf) {
                showModalError('Invalid format. Only PDF files are supported.');
                return;
            }

            const submitBtn = document.getElementById('uploadSubmitBtn');
            const cancelBtn = document.getElementById('uploadCancelBtn');
            const progressContainer = document.getElementById('uploadingProgressContainer');
            const progressBar = document.getElementById('uploadProgressBar');
            const progressPct = document.getElementById('uploadProgressPct');
            const statusText = document.getElementById('uploadStatusText');
            const subText = document.getElementById('uploadSubText');

            submitBtn.disabled = true;
            cancelBtn.disabled = true;
            progressContainer.style.display = 'block';
            progressBar.style.width = '0%';
            progressPct.textContent = '0%';
            statusText.innerHTML = '<i class="fas fa-spinner fa-spin" style="margin-right: 6px;"></i> Uploading PDF document...';
            subText.textContent = 'Sending file to server...';

            const formData = new FormData(uploadForm);
            formData.append('ajax', '1');

            const xhr = new XMLHttpRequest();
            xhr.open('POST', 'pdf-qa.php', true);
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

            xhr.upload.onprogress = function(event) {
                if (event.lengthComputable) {
                    const percent = Math.min(Math.round((event.loaded / event.total) * 100), 95);
                    progressBar.style.width = percent + '%';
                    progressPct.textContent = percent + '%';
                    if (percent >= 90) {
                        statusText.innerHTML = '<i class="fas fa-cog fa-spin" style="margin-right: 6px;"></i> Indexing semantic chunks...';
                        subText.textContent = 'Extracting plain text & building RAG search index...';
                    }
                }
            };

            xhr.onload = function() {
                if (xhr.status >= 200 && xhr.status < 300) {
                    try {
                        const res = JSON.parse(xhr.responseText);
                        if (res.success) {
                            progressBar.style.width = '100%';
                            progressPct.textContent = '100%';
                            statusText.innerHTML = '<i class="fas fa-check-circle" style="color: #10B981; margin-right: 6px;"></i> Upload Complete!';
                            subText.textContent = res.message || 'PDF indexed successfully!';
                            setTimeout(function() {
                                window.location.href = res.redirect_url || ('pdf-qa.php?doc_id=' + res.doc_id);
                            }, 500);
                        } else {
                            showModalError(res.error || res.message || 'Upload failed.');
                            submitBtn.disabled = false;
                            cancelBtn.disabled = false;
                            progressContainer.style.display = 'none';
                        }
                    } catch(err) {
                        // Fallback in case of raw page response
                        if (xhr.responseText.includes('alert-success') || xhr.status === 200) {
                            window.location.reload();
                        } else {
                            showModalError('Unexpected response received from server.');
                            submitBtn.disabled = false;
                            cancelBtn.disabled = false;
                            progressContainer.style.display = 'none';
                        }
                    }
                } else {
                    showModalError('Upload failed (HTTP ' + xhr.status + '). Please try again.');
                    submitBtn.disabled = false;
                    cancelBtn.disabled = false;
                    progressContainer.style.display = 'none';
                }
            };

            xhr.onerror = function() {
                showModalError('Network error occurred during upload. Please verify your connection.');
                submitBtn.disabled = false;
                cancelBtn.disabled = false;
                progressContainer.style.display = 'none';
            };

            xhr.send(formData);
        });
    }

    function clearRagChat() {
        const container = document.getElementById('ragMessages');
        if (!container) return;
        const safeTitle = escapeHTML(activeDocTitle);
        container.innerHTML = `
            <div class="ai-message bot">
                <div class="ai-avatar" style="background: #EF4444; color: white;"><i class="fas fa-file-pdf"></i></div>
                <div class="ai-bubble google-doc-overview">
                    <div style="display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-bottom: 6px;">
                        <div style="display: flex; align-items: center; gap: 6px; font-size: 11px; font-weight: 700; color: #EF4444; text-transform: uppercase; letter-spacing: 0.5px;">
                            <i class="fas fa-file-pdf"></i> Grounded Document RAG Engine
                        </div>
                        <span style="font-size: 10.5px; color: var(--text-muted);"><i class="fas fa-layer-group"></i> ${activeDocChunks} chunks indexed</span>
                    </div>
                    I am ready to answer questions strictly grounded in <strong>${safeTitle}</strong>. You can ask me to explain definitions, locate specific sections, summarize key chapters, or generate practice questions based on this document!
                </div>
            </div>
        `;
        const input = document.getElementById('ragInput');
        if (input) {
            input.value = '';
            input.focus();
        }
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
        html = html.replace(/^[â€¢\-\*]\s+(.*?)$/gm, '<div style="display:flex; gap:8px; margin:4px 0 4px 8px;"><i class="fas fa-check-circle" style="font-size:10px; color:#10B981; margin-top:5px; flex-shrink:0;"></i><span>$1</span></div>');

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
        const sendBtn = document.getElementById('ragSendBtn');
        const question = input.value.trim();
        if (!question) return;

        input.value = '';
        input.disabled = true;
        if (sendBtn) {
            sendBtn.disabled = true;
            sendBtn.style.opacity = '0.7';
        }

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

            let apiUrl = '../../backend/api/ai.php?path=pdf-qa&stream=1';
            if (window.location.pathname.toLowerCase().includes('/studentos-ai-project/')) {
                apiUrl = '/StudentOS-AI-project/backend/api/ai.php?path=pdf-qa&stream=1';
            }

            const res = await fetch(apiUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: Object.assign({}, headers, { 'Accept': 'text/event-stream, application/json' }),
                body: JSON.stringify({ document_id: currentDocId, question: question, stream: 1 })
            });

            const botBubble = document.getElementById(typingId);

            if (!res.ok) {
                let errMsg = 'Server error (' + res.status + ')';
                try {
                    const errData = await res.json();
                    if (errData && errData.error) errMsg = errData.error;
                } catch(e) {}
                if (botBubble) {
                    botBubble.querySelector('.ai-bubble').innerHTML = `<div style="color: #EF4444;"><i class="fas fa-exclamation-triangle"></i> ${escapeHTML(errMsg)}</div>`;
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
                                    botBubble.querySelector('.ai-bubble').innerHTML = formatRagAnswer(accumulatedText, ["Verified Document Content"]);
                                    container.scrollTop = container.scrollHeight;
                                }
                            } catch(e) {}
                        }
                    }
                }

                if (!accumulatedText.trim()) {
                    botBubble.querySelector('.ai-bubble').innerHTML = 'Unable to retrieve answer for the specified document.';
                }
            } else {
                const data = await res.json();
                if (data && data.answer) {
                    botBubble.querySelector('.ai-bubble').innerHTML = formatRagAnswer(data.answer, data.sources || []);
                } else {
                    botBubble.querySelector('.ai-bubble').innerHTML = data && data.error ? escapeHTML(data.error) : 'Unable to retrieve answer for the specified document.';
                }
            }
        } catch (err) {
            console.error('PDF Q&A Error:', err);
            const botBubble = document.getElementById(typingId);
            if (botBubble) {
                const msg = err && err.message ? err.message : 'Unable to connect to Document AI service';
                botBubble.querySelector('.ai-bubble').innerHTML = `<div style="color: #EF4444;"><i class="fas fa-exclamation-circle"></i> Error: ${escapeHTML(msg)}. Please verify your connection.</div>`;
            }
        } finally {
            input.disabled = false;
            if (sendBtn) {
                sendBtn.disabled = false;
                sendBtn.style.opacity = '1';
            }
            input.focus();
            container.scrollTop = container.scrollHeight;
        }
    }
    </script>
</body>
</html>

