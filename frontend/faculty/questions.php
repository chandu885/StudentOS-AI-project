<?php
// frontend/faculty/questions.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('faculty');

$userId = (int)($_SESSION['user']['id'] ?? 0);
$successMsg = '';
$errorMsg = '';
$db = getDbConnection();

// Auto-seed baseline examination records if exams table is empty so faculty always has active exams
if ($db) {
    $eChk = $db->query("SELECT COUNT(*) AS cnt FROM exams");
    if ($eChk && (int)$eChk->fetch_assoc()['cnt'] === 0) {
        $firstSub = $db->query("SELECT id FROM subjects LIMIT 1")->fetch_assoc();
        $subId = !empty($firstSub['id']) ? (int)$firstSub['id'] : 1;
        $seedEx = $db->prepare(
            "INSERT IGNORE INTO exams (subject_id, faculty_id, title, exam_type, exam_date, start_time, end_time, total_marks, passing_marks, room_number, status, created_at, updated_at) 
             VALUES (?, ?, 'Midterm Examination: Core Assessment', 'midterm', DATE_ADD(CURDATE(), INTERVAL 7 DAY), '10:00:00', '12:00:00', 50, 20, 'Auditorium A', 'scheduled', NOW(), NOW()),
                    (?, ?, 'Internal Quiz Assessment 1', 'quiz', DATE_ADD(CURDATE(), INTERVAL 14 DAY), '14:00:00', '15:00:00', 25, 10, 'LH-201', 'scheduled', NOW(), NOW())"
        );
        if ($seedEx) {
            $seedEx->bind_param("iiii", $subId, $userId, $subId, $userId);
            $seedEx->execute();
            $seedEx->close();
        }
    }
}

// Fetch exams for dropdown
$examsList = [];
if ($db) {
    $res = $db->query(
        "SELECT e.id, e.title, s.name AS subject_name, s.code AS subject_code 
         FROM exams e 
         JOIN subjects s ON e.subject_id = s.id 
         ORDER BY e.id DESC"
    );
    if ($res) {
        $examsList = $res->fetch_all(MYSQLI_ASSOC);
    }
}

// ==========================================
// Parsing & Import Helper Functions
// ==========================================

/**
 * Bulk insert parsed questions and options atomically into database
 */
function bulkInsertQuestions($db, $examId, array $questionsList) {
    if (empty($questionsList)) {
        return ['success' => false, 'error' => 'No valid questions found to import.'];
    }

    $insertedCount = 0;
    $db->begin_transaction();
    try {
        $qStmt = $db->prepare("INSERT INTO questions (exam_id, question_text, question_type, marks, created_at) VALUES (?, ?, ?, ?, NOW())");
        $oStmt = $db->prepare("INSERT INTO question_options (question_id, option_text, is_correct) VALUES (?, ?, ?)");

        foreach ($questionsList as $item) {
            $qText = trim($item['question_text'] ?? '');
            if (empty($qText)) continue;

            $qType = in_array(strtolower($item['question_type'] ?? ''), ['mcq', 'descriptive']) 
                     ? strtolower($item['question_type']) 
                     : 'mcq';
            $marks = max(1, (int)($item['marks'] ?? ($qType === 'mcq' ? 2 : 5)));

            $qStmt->bind_param("issi", $examId, $qText, $qType, $marks);
            if ($qStmt->execute()) {
                $qId = $db->insert_id;
                $insertedCount++;

                if ($qType === 'mcq' && !empty($item['options']) && is_array($item['options'])) {
                    foreach ($item['options'] as $opt) {
                        $optText = trim(is_array($opt) ? ($opt['text'] ?? '') : (string)$opt);
                        $isCorrect = is_array($opt) && !empty($opt['is_correct']) ? 1 : 0;
                        if ($optText !== '') {
                            $oStmt->bind_param("isi", $qId, $optText, $isCorrect);
                            $oStmt->execute();
                        }
                    }
                }
            }
        }
        $qStmt->close();
        $oStmt->close();
        $db->commit();
        return ['success' => true, 'count' => $insertedCount];
    } catch (Throwable $e) {
        $db->rollback();
        return ['success' => false, 'error' => 'Database error: ' . $e->getMessage()];
    }
}

/**
 * Parse CSV question rows
 */
function parseCsvQuestionsContent($content) {
    $lines = preg_split('/\r\n|\r|\n/', trim($content));
    $questions = [];
    $isHeader = true;

    foreach ($lines as $line) {
        if (trim($line) === '') continue;
        $row = str_getcsv($line, ',', '"', '\\');
        if (empty($row) || empty(trim($row[0] ?? ''))) continue;

        // Skip header row if contains "question"
        if ($isHeader) {
            $isHeader = false;
            $firstCol = strtolower(trim($row[0]));
            if (strpos($firstCol, 'question') !== false) {
                continue;
            }
        }

        $qText = trim($row[0]);
        $type = !empty($row[1]) && stripos($row[1], 'desc') !== false ? 'descriptive' : 'mcq';
        $marks = !empty($row[2]) && is_numeric(trim($row[2])) ? (int)trim($row[2]) : ($type === 'mcq' ? 2 : 5);

        $options = [];
        if ($type === 'mcq') {
            $optLetters = ['A', 'B', 'C', 'D', 'E', 'F'];
            $correctRef = isset($row[7]) ? trim(strtoupper($row[7])) : '';

            for ($i = 3; $i <= 6; $i++) {
                if (isset($row[$i]) && trim($row[$i]) !== '') {
                    $optText = trim($row[$i]);
                    $letter = $optLetters[$i - 3];
                    $isCorrect = 0;
                    if ($correctRef === $letter || $correctRef === (string)($i - 2)) {
                        $isCorrect = 1;
                    } elseif (strcasecmp($correctRef, $optText) === 0) {
                        $isCorrect = 1;
                    }
                    $options[] = [
                        'text' => $optText,
                        'is_correct' => $isCorrect
                    ];
                }
            }
            if (empty($options)) {
                $type = 'descriptive';
            }
        }

        $questions[] = [
            'question_text' => $qText,
            'question_type' => $type,
            'marks' => $marks,
            'options' => $options
        ];
    }
    return $questions;
}

/**
 * Parse Excel (.xlsx) files via ZipArchive XML extraction
 */
function parseXlsxQuestionsFile($filePath) {
    if (!class_exists('ZipArchive')) {
        return ['error' => 'ZipArchive PHP extension is required to read Excel .xlsx files.'];
    }
    $zip = new ZipArchive();
    if ($zip->open($filePath) !== true) {
        return ['error' => 'Could not open Excel (.xlsx) file. Please ensure it is a valid workbook.'];
    }

    // Read shared strings
    $sharedStrings = [];
    $stringsXml = $zip->getFromName('xl/sharedStrings.xml');
    if ($stringsXml) {
        $xml = @simplexml_load_string($stringsXml);
        if ($xml) {
            foreach ($xml->si as $si) {
                if (isset($si->t)) {
                    $sharedStrings[] = (string)$si->t;
                } elseif (isset($si->r)) {
                    $part = '';
                    foreach ($si->r as $r) {
                        $part .= (string)$r->t;
                    }
                    $sharedStrings[] = $part;
                } else {
                    $sharedStrings[] = '';
                }
            }
        }
    }

    // Read primary sheet
    $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();

    if (!$sheetXml) {
        return ['error' => 'Sheet1 worksheet data was not found in the uploaded .xlsx file.'];
    }

    $xml = @simplexml_load_string($sheetXml);
    if (!$xml || !isset($xml->sheetData->row)) {
        return ['error' => 'Unable to read rows from the Excel worksheet.'];
    }

    $questions = [];
    $isHeader = true;

    foreach ($xml->sheetData->row as $row) {
        $cells = [];
        foreach ($row->c as $c) {
            $attr = $c->attributes();
            $ref = (string)($attr['r'] ?? 'A1');
            $type = (string)($attr['t'] ?? '');
            $val = isset($c->v) ? (string)$c->v : '';

            // Compute 0-indexed column from cell ref (e.g. A->0, B->1, etc.)
            preg_match('/^([A-Z]+)/', strtoupper($ref), $m);
            $colLetter = $m[1] ?? 'A';
            $colIdx = 0;
            for ($k = 0; $k < strlen($colLetter); $k++) {
                $colIdx = $colIdx * 26 + (ord($colLetter[$k]) - ord('A') + 1);
            }
            $colIdx--;

            $cellValue = '';
            if ($type === 's') {
                $idx = (int)$val;
                $cellValue = $sharedStrings[$idx] ?? '';
            } elseif ($type === 'inlineStr' && isset($c->is->t)) {
                $cellValue = (string)$c->is->t;
            } else {
                $cellValue = $val;
            }
            $cells[$colIdx] = trim($cellValue);
        }

        if (empty(array_filter($cells, 'strlen'))) continue;

        // Skip header
        if ($isHeader) {
            $isHeader = false;
            $first = strtolower($cells[0] ?? '');
            if (strpos($first, 'question') !== false) {
                continue;
            }
        }

        $qText = $cells[0] ?? '';
        if (empty($qText)) continue;

        $type = !empty($cells[1]) && stripos($cells[1], 'desc') !== false ? 'descriptive' : 'mcq';
        $marks = !empty($cells[2]) && is_numeric($cells[2]) ? (int)$cells[2] : ($type === 'mcq' ? 2 : 5);

        $options = [];
        if ($type === 'mcq') {
            $optLetters = ['A', 'B', 'C', 'D', 'E', 'F'];
            $correctRef = isset($cells[7]) ? strtoupper(trim($cells[7])) : '';

            for ($i = 3; $i <= 6; $i++) {
                if (!empty($cells[$i])) {
                    $optText = $cells[$i];
                    $letter = $optLetters[$i - 3];
                    $isCorrect = 0;
                    if ($correctRef === $letter || $correctRef === (string)($i - 2)) {
                        $isCorrect = 1;
                    } elseif (strcasecmp($correctRef, $optText) === 0) {
                        $isCorrect = 1;
                    }
                    $options[] = [
                        'text' => $optText,
                        'is_correct' => $isCorrect
                    ];
                }
            }
            if (empty($options)) {
                $type = 'descriptive';
            }
        }

        $questions[] = [
            'question_text' => $qText,
            'question_type' => $type,
            'marks' => $marks,
            'options' => $options
        ];
    }

    return ['success' => true, 'questions' => $questions];
}

/**
 * Parse JSON questions
 */
function parseJsonQuestionsContent($jsonString) {
    $data = json_decode($jsonString, true);
    if (!is_array($data)) {
        return ['error' => 'Invalid JSON formatting. Please check syntax and quotes.'];
    }

    $list = isset($data['questions']) && is_array($data['questions']) ? $data['questions'] : $data;
    if (!is_array($list)) {
        return ['error' => 'No questions array found in JSON payload.'];
    }

    $questions = [];
    foreach ($list as $item) {
        if (!is_array($item)) continue;
        $qText = trim($item['question_text'] ?? $item['question'] ?? $item['title'] ?? '');
        if (empty($qText)) continue;

        $type = strtolower($item['question_type'] ?? $item['type'] ?? '');
        $type = (stripos($type, 'desc') !== false) ? 'descriptive' : 'mcq';
        $marks = isset($item['marks']) ? (int)$item['marks'] : (isset($item['points']) ? (int)$item['points'] : ($type === 'mcq' ? 2 : 5));
        if ($marks <= 0) $marks = 2;

        $options = [];
        $rawOpts = $item['options'] ?? $item['choices'] ?? [];
        $correctAns = trim(strtoupper($item['correct_answer'] ?? $item['answer'] ?? $item['correct_option'] ?? ''));

        if (!empty($rawOpts) && is_array($rawOpts)) {
            $optLetters = ['A', 'B', 'C', 'D', 'E', 'F'];
            $idx = 0;
            foreach ($rawOpts as $key => $optVal) {
                if (is_array($optVal)) {
                    $optText = trim($optVal['text'] ?? $optVal['option'] ?? '');
                    $isCorrect = !empty($optVal['is_correct']) ? 1 : 0;
                } else {
                    $optText = trim((string)$optVal);
                    $letter = is_string($key) && strlen($key) === 1 ? strtoupper($key) : ($optLetters[$idx] ?? chr(65 + $idx));
                    $isCorrect = 0;
                    if ($correctAns === $letter || $correctAns === (string)($idx + 1)) {
                        $isCorrect = 1;
                    } elseif (strcasecmp($correctAns, $optText) === 0) {
                        $isCorrect = 1;
                    }
                }
                if ($optText !== '') {
                    $options[] = [
                        'text' => $optText,
                        'is_correct' => $isCorrect
                    ];
                }
                $idx++;
            }
        }

        if (empty($options) && $type === 'mcq') {
            $type = 'descriptive';
        }

        $questions[] = [
            'question_text' => $qText,
            'question_type' => $type,
            'marks' => $marks,
            'options' => $options
        ];
    }

    return ['success' => true, 'questions' => $questions];
}

/**
 * Parse Word (.docx) documents into text lines
 */
function parseDocxQuestionsFile($filePath) {
    if (!class_exists('ZipArchive')) {
        return ['error' => 'ZipArchive PHP extension is required to process Word (.docx) files.'];
    }
    $zip = new ZipArchive();
    if ($zip->open($filePath) !== true) {
        return ['error' => 'Could not open Word (.docx) file. Please ensure it is a valid document.'];
    }
    $xmlContent = $zip->getFromName('word/document.xml');
    $zip->close();

    if (!$xmlContent) {
        return ['error' => 'word/document.xml was not found inside the uploaded Word document.'];
    }

    // Convert paragraph endings and table row endings into newline breaks
    $xmlContent = str_replace(['</w:p>', '</w:tr>'], "\n", $xmlContent);
    $text = strip_tags($xmlContent);
    return ['success' => true, 'text' => $text];
}

/**
 * Universal text block parser for Text files and Word documents
 */
function parseTextQuestionBlocks($rawText) {
    $rawText = str_replace(["\r\n", "\r"], "\n", $rawText);
    $lines = explode("\n", $rawText);
    
    $questions = [];
    $currentQ = null;

    $flushCurrent = function() use (&$questions, &$currentQ) {
        if ($currentQ && !empty(trim($currentQ['question_text']))) {
            if (empty($currentQ['question_type'])) {
                $currentQ['question_type'] = !empty($currentQ['raw_options']) ? 'mcq' : 'descriptive';
            }
            $formattedOptions = [];
            $correctRef = trim(strtoupper($currentQ['correct_raw'] ?? ''));
            $optLetters = ['A', 'B', 'C', 'D', 'E', 'F'];
            
            foreach ($currentQ['raw_options'] as $idx => $opt) {
                $letter = $optLetters[$idx] ?? chr(65 + $idx);
                $isCorrect = 0;
                if ($correctRef === $letter || $correctRef === (string)($idx + 1)) {
                    $isCorrect = 1;
                } elseif (strcasecmp($correctRef, trim($opt)) === 0) {
                    $isCorrect = 1;
                }
                $formattedOptions[] = [
                    'text' => $opt,
                    'is_correct' => $isCorrect
                ];
            }
            
            $currentQ['options'] = $formattedOptions;
            unset($currentQ['raw_options'], $currentQ['correct_raw']);
            $questions[] = $currentQ;
        }
        $currentQ = null;
    };

    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '') continue;

        // Pipe delimited single line format: Question | Type | Marks | Opt A | Opt B | Opt C | Opt D | Correct
        if (strpos($trimmed, '|') !== false && !preg_match('/^[A-F]\s*[\)\.]/i', $trimmed)) {
            $parts = array_map('trim', explode('|', $trimmed));
            if (count($parts) >= 2) {
                $flushCurrent();
                $qText = $parts[0];
                $type = in_array(strtolower($parts[1]), ['mcq', 'descriptive']) ? strtolower($parts[1]) : 'mcq';
                $marks = isset($parts[2]) && is_numeric($parts[2]) ? (int)$parts[2] : ($type === 'mcq' ? 2 : 5);
                $rawOpts = [];
                $correctAns = '';
                if ($type === 'mcq' && count($parts) > 3) {
                    $lastPart = end($parts);
                    if (preg_match('/^[A-F]$/i', $lastPart) || count($parts) > 7) {
                        $correctAns = array_pop($parts);
                    }
                    $rawOpts = array_slice($parts, 3);
                }
                $currentQ = [
                    'question_text' => $qText,
                    'question_type' => $type,
                    'marks' => $marks,
                    'raw_options' => $rawOpts,
                    'correct_raw' => $correctAns
                ];
                $flushCurrent();
                continue;
            }
        }

        // New question start (e.g. "1. Question", "Q1: Question", "Question 1:", etc.)
        if (preg_match('/^(?:Q(?:uestion)?\s*\d*[\:\.\-]?|\d+[\.\)])\s*(.+)$/i', $trimmed, $m) && !preg_match('/^[A-F][\.\)]/i', $trimmed)) {
            $flushCurrent();
            $currentQ = [
                'question_text' => $m[1],
                'question_type' => '',
                'marks' => 2,
                'raw_options' => [],
                'correct_raw' => ''
            ];
            continue;
        }

        if (!$currentQ) {
            $currentQ = [
                'question_text' => $trimmed,
                'question_type' => '',
                'marks' => 2,
                'raw_options' => [],
                'correct_raw' => ''
            ];
            continue;
        }

        // Check Type
        if (preg_match('/^(?:Type|Question Type)\s*[\:\=]\s*(.+)$/i', $trimmed, $m)) {
            $currentQ['question_type'] = stripos($m[1], 'desc') !== false ? 'descriptive' : 'mcq';
            continue;
        }

        // Check Marks
        if (preg_match('/^(?:Marks|Points|Score)\s*[\:\=]\s*(\d+)/i', $trimmed, $m)) {
            $currentQ['marks'] = max(1, (int)$m[1]);
            continue;
        }

        // Check Answer
        if (preg_match('/^(?:Answer|Correct Answer|Ans|Correct)\s*[\:\=]\s*(.+)$/i', $trimmed, $m)) {
            $currentQ['correct_raw'] = trim($m[1]);
            continue;
        }

        // Check Option: e.g. "A) ...", "A. ...", "(A) ...", "Option A: ..."
        if (preg_match('/^(?:Option\s*[A-F]\s*[\:\.\-\)]?|\(?([A-F])[\)\.\:\-])\s*(.+)$/i', $trimmed, $m)) {
            $optText = trim(isset($m[2]) && strlen($m[2]) ? $m[2] : $m[1]);
            $currentQ['raw_options'][] = $optText;
            $currentQ['question_type'] = 'mcq';
            continue;
        }

        // Append to current question text if no options encountered yet
        if (empty($currentQ['raw_options'])) {
            $currentQ['question_text'] .= ' ' . $trimmed;
        }
    }
    $flushCurrent();

    return $questions;
}

// ==========================================
// Handle Form Submissions Across All Formats
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $examId = (int)($_POST['exam_id'] ?? ($examsList[0]['id'] ?? 1));

    if ($action === 'delete_question') {
        $qId = (int)($_POST['question_id'] ?? 0);
        if ($qId > 0 && $db) {
            $delStmt = $db->prepare("DELETE FROM questions WHERE id = ?");
            if ($delStmt) {
                $delStmt->bind_param("i", $qId);
                if ($delStmt->execute()) {
                    $successMsg = 'Question removed from examination question bank.';
                } else {
                    $errorMsg = 'Failed to delete question: ' . $delStmt->error;
                }
                $delStmt->close();
            }
        }
    }

    // 1. Manual Entry Option
    elseif ($action === 'add_manual') {
        $questionText = sanitize($_POST['question_text'] ?? '');
        $qType = in_array(strtolower($_POST['type'] ?? ''), ['mcq', 'descriptive']) ? strtolower($_POST['type']) : 'mcq';
        $marks = max(1, (int)($_POST['marks'] ?? ($qType === 'mcq' ? 2 : 5)));

        if (!empty($questionText) && $examId > 0 && $db) {
            $options = [];
            if ($qType === 'mcq' && isset($_POST['manual_options']) && is_array($_POST['manual_options'])) {
                $correctIndex = (int)($_POST['manual_correct'] ?? 0);
                foreach ($_POST['manual_options'] as $idx => $optVal) {
                    $optClean = sanitize($optVal);
                    if ($optClean !== '') {
                        $options[] = [
                            'text' => $optClean,
                            'is_correct' => ($idx === $correctIndex) ? 1 : 0
                        ];
                    }
                }
            }

            $res = bulkInsertQuestions($db, $examId, [[
                'question_text' => $questionText,
                'question_type' => $qType,
                'marks' => $marks,
                'options' => $options
            ]]);

            if ($res['success']) {
                $successMsg = 'Question successfully added to question bank via Manual Entry!';
            } else {
                $errorMsg = $res['error'] ?? 'Failed to save question.';
            }
        } else {
            $errorMsg = 'Please enter valid question text and select a linked examination.';
        }
    }

    // 2. CSV Option
    elseif ($action === 'upload_csv') {
        $csvContent = '';
        if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
            $csvContent = file_get_contents($_FILES['csv_file']['tmp_name']);
        } elseif (!empty($_POST['csv_text'])) {
            $csvContent = $_POST['csv_text'];
        }

        if (empty(trim($csvContent))) {
            $errorMsg = 'Please select a CSV file or paste CSV records.';
        } else {
            $parsedQuestions = parseCsvQuestionsContent($csvContent);
            if (empty($parsedQuestions)) {
                $errorMsg = 'No questions could be extracted from the CSV input. Please check the column layout.';
            } else {
                $res = bulkInsertQuestions($db, $examId, $parsedQuestions);
                if ($res['success']) {
                    $successMsg = "Successfully imported {$res['count']} questions from CSV into the Question Bank!";
                } else {
                    $errorMsg = $res['error'];
                }
            }
        }
    }

    // 3. Excel (.xlsx) Option
    elseif ($action === 'upload_excel') {
        if (!isset($_FILES['excel_file']) || $_FILES['excel_file']['error'] !== UPLOAD_ERR_OK) {
            $errorMsg = 'Please select a valid Excel workbook (.xlsx) to upload.';
        } else {
            $ext = strtolower(pathinfo($_FILES['excel_file']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['xlsx', 'xls'])) {
                $errorMsg = 'Invalid file extension. Please upload an Excel file (.xlsx).';
            } else {
                $resXlsx = parseXlsxQuestionsFile($_FILES['excel_file']['tmp_name']);
                if (!empty($resXlsx['error'])) {
                    $errorMsg = $resXlsx['error'];
                } elseif (empty($resXlsx['questions'])) {
                    $errorMsg = 'No questions found in the Excel workbook. Please verify column headers.';
                } else {
                    $res = bulkInsertQuestions($db, $examId, $resXlsx['questions']);
                    if ($res['success']) {
                        $successMsg = "Successfully imported {$res['count']} questions from Excel (.xlsx) into the Question Bank!";
                    } else {
                        $errorMsg = $res['error'];
                    }
                }
            }
        }
    }

    // 4. JSON Option
    elseif ($action === 'upload_json') {
        $jsonContent = '';
        if (isset($_FILES['json_file']) && $_FILES['json_file']['error'] === UPLOAD_ERR_OK) {
            $jsonContent = file_get_contents($_FILES['json_file']['tmp_name']);
        } elseif (!empty($_POST['json_text'])) {
            $jsonContent = $_POST['json_text'];
        }

        if (empty(trim($jsonContent))) {
            $errorMsg = 'Please upload a JSON file or paste valid JSON questions.';
        } else {
            $resJson = parseJsonQuestionsContent($jsonContent);
            if (!empty($resJson['error'])) {
                $errorMsg = $resJson['error'];
            } elseif (empty($resJson['questions'])) {
                $errorMsg = 'No questions extracted from the JSON input. Please review schema format.';
            } else {
                $res = bulkInsertQuestions($db, $examId, $resJson['questions']);
                if ($res['success']) {
                    $successMsg = "Successfully imported {$res['count']} questions from JSON into the Question Bank!";
                } else {
                    $errorMsg = $res['error'];
                }
            }
        }
    }

    // 5. Word (.docx) Option
    elseif ($action === 'upload_word') {
        if (!isset($_FILES['word_file']) || $_FILES['word_file']['error'] !== UPLOAD_ERR_OK) {
            $errorMsg = 'Please select a Word document (.docx) to upload.';
        } else {
            $ext = strtolower(pathinfo($_FILES['word_file']['name'], PATHINFO_EXTENSION));
            if ($ext !== 'docx') {
                $errorMsg = 'Invalid file extension. Please upload a Word document (.docx).';
            } else {
                $resDocx = parseDocxQuestionsFile($_FILES['word_file']['tmp_name']);
                if (!empty($resDocx['error'])) {
                    $errorMsg = $resDocx['error'];
                } else {
                    $parsedQuestions = parseTextQuestionBlocks($resDocx['text']);
                    if (empty($parsedQuestions)) {
                        $errorMsg = 'No questions could be parsed from the Word document. Please ensure questions are numbered or prefixed with Q1:, 1., etc.';
                    } else {
                        $res = bulkInsertQuestions($db, $examId, $parsedQuestions);
                        if ($res['success']) {
                            $successMsg = "Successfully imported {$res['count']} questions from Word (.docx) into the Question Bank!";
                        } else {
                            $errorMsg = $res['error'];
                        }
                    }
                }
            }
        }
    }

    // 6. Text (.txt) Option
    elseif ($action === 'upload_text') {
        $textContent = '';
        if (isset($_FILES['text_file']) && $_FILES['text_file']['error'] === UPLOAD_ERR_OK) {
            $textContent = file_get_contents($_FILES['text_file']['tmp_name']);
        } elseif (!empty($_POST['text_raw'])) {
            $textContent = $_POST['text_raw'];
        }

        if (empty(trim($textContent))) {
            $errorMsg = 'Please select a Text file (.txt) or paste question text blocks.';
        } else {
            $parsedQuestions = parseTextQuestionBlocks($textContent);
            if (empty($parsedQuestions)) {
                $errorMsg = 'No questions could be extracted from the text input. Please ensure questions are numbered or formatted in Q&A blocks.';
            } else {
                $res = bulkInsertQuestions($db, $examId, $parsedQuestions);
                if ($res['success']) {
                    $successMsg = "Successfully imported {$res['count']} questions from Text (.txt) into the Question Bank!";
                } else {
                    $errorMsg = $res['error'];
                }
            }
        }
    }
}

// ==========================================
// Filtering & Data Retrieval
// ==========================================
$filterExamId = !empty($_GET['exam_id']) ? (int)$_GET['exam_id'] : 0;
$filterType = !empty($_GET['type']) ? sanitize($_GET['type']) : '';
$searchKeyword = !empty($_GET['q']) ? sanitize($_GET['q']) : '';

$questions = [];
$totalQuestionsCount = 0;
$mcqCount = 0;
$descCount = 0;
$uniqueExams = [];

if ($db) {
    $sql = "SELECT q.id, q.exam_id, q.question_text, q.question_type, q.marks, q.created_at,
                   e.title AS exam_title, s.name AS subject_name, s.code AS subject_code
            FROM questions q
            LEFT JOIN exams e ON q.exam_id = e.id
            LEFT JOIN subjects s ON e.subject_id = s.id
            WHERE 1=1";

    $params = [];
    $types = "";

    if ($filterExamId > 0) {
        $sql .= " AND q.exam_id = ?";
        $params[] = $filterExamId;
        $types .= "i";
    }

    if (!empty($filterType) && in_array($filterType, ['mcq', 'descriptive'])) {
        $sql .= " AND q.question_type = ?";
        $params[] = $filterType;
        $types .= "s";
    }

    if (!empty($searchKeyword)) {
        $sql .= " AND (q.question_text LIKE ? OR e.title LIKE ? OR s.name LIKE ?)";
        $searchWild = "%{$searchKeyword}%";
        $params[] = $searchWild;
        $params[] = $searchWild;
        $params[] = $searchWild;
        $types .= "sss";
    }

    $sql .= " ORDER BY q.id DESC";

    if (!empty($params)) {
        $stmt = $db->prepare($sql);
        if ($stmt) {
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res) {
                $questions = $res->fetch_all(MYSQLI_ASSOC);
            }
            $stmt->close();
        }
    } else {
        $res = $db->query($sql);
        if ($res) {
            $questions = $res->fetch_all(MYSQLI_ASSOC);
        }
    }

    // Load options for MCQ questions
    $optionsByQuestion = [];
    if (!empty($questions)) {
        $qIds = array_column($questions, 'id');
        $qIdList = implode(',', array_map('intval', $qIds));
        $optRes = $db->query("SELECT question_id, option_text, is_correct FROM question_options WHERE question_id IN ($qIdList) ORDER BY id ASC");
        if ($optRes) {
            while ($opt = $optRes->fetch_assoc()) {
                $optionsByQuestion[$opt['question_id']][] = $opt;
            }
        }
    }

    // Calculate metrics
    $totalQuestionsCount = count($questions);
    foreach ($questions as $q) {
        if ($q['question_type'] === 'mcq') {
            $mcqCount++;
        } else {
            $descCount++;
        }
        if (!empty($q['exam_id'])) {
            $uniqueExams[$q['exam_id']] = true;
        }
    }
}
?>
<?php
$pageTitle = 'Examination Question Bank - StudentOS AI';
include_once __DIR__ . '/../components/header.php';
?>
                <div class="page-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px;">
                    <div>
                        <h1><i class="fas fa-database" style="color: var(--primary); margin-right: 8px;"></i> Examination Question Bank</h1>
                        <p class="page-subtitle">Curate, import, and organize reusable exam questions across Manual Entry, CSV, Excel, JSON, Word, and Text formats</p>
                    </div>
                    <div class="header-actions">
                        <button class="btn btn-primary" onclick="openModal('addQuestionModal')" style="display: inline-flex; align-items: center; gap: 8px;">
                            <i class="fas fa-plus-circle"></i> Add Question
                        </button>
                    </div>
                </div>

                <!-- Operational Metrics -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 16px; margin-bottom: 24px;">
                    <div class="card" style="padding: 18px; display: flex; align-items: center; gap: 14px;">
                        <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(79, 70, 229, 0.12); color: #4F46E5; display: flex; align-items: center; justify-content: center; font-size: 22px;">
                            <i class="fas fa-database"></i>
                        </div>
                        <div>
                            <div style="font-size: 11px; color: var(--text-muted); text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px;">Total Questions</div>
                            <div style="font-size: 24px; font-weight: 800; color: var(--text-primary); line-height: 1.2;"><?php echo $totalQuestionsCount; ?></div>
                        </div>
                    </div>

                    <div class="card" style="padding: 18px; display: flex; align-items: center; gap: 14px;">
                        <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(16, 185, 129, 0.12); color: #10B981; display: flex; align-items: center; justify-content: center; font-size: 22px;">
                            <i class="fas fa-check-square"></i>
                        </div>
                        <div>
                            <div style="font-size: 11px; color: var(--text-muted); text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px;">Multiple Choice (MCQ)</div>
                            <div style="font-size: 24px; font-weight: 800; color: var(--text-primary); line-height: 1.2;"><?php echo $mcqCount; ?></div>
                        </div>
                    </div>

                    <div class="card" style="padding: 18px; display: flex; align-items: center; gap: 14px;">
                        <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(6, 182, 212, 0.12); color: #06B6D4; display: flex; align-items: center; justify-content: center; font-size: 22px;">
                            <i class="fas fa-align-left"></i>
                        </div>
                        <div>
                            <div style="font-size: 11px; color: var(--text-muted); text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px;">Descriptive / Theory</div>
                            <div style="font-size: 24px; font-weight: 800; color: var(--text-primary); line-height: 1.2;"><?php echo $descCount; ?></div>
                        </div>
                    </div>

                    <div class="card" style="padding: 18px; display: flex; align-items: center; gap: 14px;">
                        <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(245, 158, 11, 0.12); color: #F59E0B; display: flex; align-items: center; justify-content: center; font-size: 22px;">
                            <i class="fas fa-book-open"></i>
                        </div>
                        <div>
                            <div style="font-size: 11px; color: var(--text-muted); text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px;">Linked Exams</div>
                            <div style="font-size: 24px; font-weight: 800; color: var(--text-primary); line-height: 1.2;"><?php echo count($uniqueExams); ?></div>
                        </div>
                    </div>
                </div>

                <?php if ($successMsg): ?>
                    <div class="alert alert-success" style="background: rgba(34, 197, 94, 0.15); border: 1px solid var(--success); color: var(--success); padding: 12px 16px; border-radius: var(--radius-md); margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($successMsg); ?>
                    </div>
                <?php endif; ?>
                <?php if (!empty($errorMsg)): ?>
                    <div class="alert alert-danger" style="background: rgba(239, 68, 68, 0.15); border: 1px solid var(--danger); color: var(--danger); padding: 12px 16px; border-radius: var(--radius-md); margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($errorMsg); ?>
                    </div>
                <?php endif; ?>

                <!-- Filters Bar -->
                <div class="card" style="margin-bottom: 24px; padding: 16px 20px;">
                    <form method="GET" action="questions.php" style="display: flex; gap: 12px; flex-wrap: wrap; align-items: center;">
                        <div style="flex: 1 1 200px; min-width: 180px;">
                            <label style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--text-muted); margin-bottom: 4px; display: block;">Search</label>
                            <input type="text" name="q" class="form-control" placeholder="Search question or exam..." value="<?php echo htmlspecialchars($searchKeyword); ?>" style="height: 38px; font-size: 13px;">
                        </div>

                        <div style="flex: 1 1 200px; min-width: 180px;">
                            <label style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--text-muted); margin-bottom: 4px; display: block;">Exam / Course</label>
                            <select name="exam_id" class="form-control" style="height: 38px; font-size: 13px;">
                                <option value="">-- All Examinations --</option>
                                <?php foreach ($examsList as $ex): ?>
                                    <option value="<?php echo (int)$ex['id']; ?>" <?php echo ($filterExamId === (int)$ex['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($ex['title'] . (!empty($ex['subject_name']) ? ' (' . $ex['subject_name'] . ')' : '')); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div style="flex: 0 1 140px; min-width: 120px;">
                            <label style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--text-muted); margin-bottom: 4px; display: block;">Type</label>
                            <select name="type" class="form-control" style="height: 38px; font-size: 13px;">
                                <option value="">-- All Types --</option>
                                <option value="mcq" <?php echo ($filterType === 'mcq') ? 'selected' : ''; ?>>MCQ</option>
                                <option value="descriptive" <?php echo ($filterType === 'descriptive') ? 'selected' : ''; ?>>Descriptive</option>
                            </select>
                        </div>

                        <div style="display: flex; gap: 8px; align-self: flex-end; margin-top: 4px;">
                            <button type="submit" class="btn btn-primary" style="height: 38px; padding: 0 16px; font-size: 13px; display: inline-flex; align-items: center; gap: 6px;">
                                <i class="fas fa-filter"></i> Filter
                            </button>
                            <?php if ($filterExamId || $filterType || $searchKeyword): ?>
                                <a href="questions.php" class="btn btn-secondary" style="height: 38px; padding: 0 14px; font-size: 13px; display: inline-flex; align-items: center;" title="Clear Filters">
                                    <i class="fas fa-times"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>

                <!-- Questions Table -->
                <div class="card">
                    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                        <h3><i class="fas fa-list-check"></i> Stored Questions (<?php echo count($questions); ?>)</h3>
                        <span style="font-size: 12px; color: var(--text-muted);">Question Bank Repository</span>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th style="min-width: 320px;">Question Statement</th>
                                        <th style="min-width: 160px;">Linked Exam / Subject</th>
                                        <th style="min-width: 90px;">Type</th>
                                        <th style="min-width: 80px;">Marks</th>
                                        <th style="min-width: 120px;">Created Date</th>
                                        <th style="text-align: right; min-width: 80px;">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($questions)): ?>
                                        <tr>
                                            <td colspan="6" style="text-align: center; padding: 36px; color: var(--text-muted);">
                                                <i class="fas fa-database" style="font-size: 32px; margin-bottom: 10px; display: block; opacity: 0.4;"></i>
                                                No questions found matching your filter. Click "Add Question" above to upload in Manual, CSV, Excel, JSON, Word, or Text format.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($questions as $q): 
                                            $isMcq = ($q['question_type'] === 'mcq');
                                            $qOptions = $optionsByQuestion[$q['id']] ?? [];
                                        ?>
                                            <tr>
                                                <td>
                                                    <strong style="color: var(--text-primary); font-size: 13.5px; line-height: 1.4; display: block;">
                                                        <?php echo htmlspecialchars($q['question_text']); ?>
                                                    </strong>

                                                    <?php if ($isMcq && !empty($qOptions)): ?>
                                                        <div style="margin-top: 6px;">
                                                            <span class="options-badge-pill" onclick="toggleOptionsPopover('pop_<?php echo $q['id']; ?>')">
                                                                <i class="fas fa-list-ul"></i> <?php echo count($qOptions); ?> Options
                                                                <i class="fas fa-chevron-down" style="font-size: 9px; opacity: 0.7;"></i>
                                                            </span>
                                                            <div id="pop_<?php echo $q['id']; ?>" class="options-popover-box" style="display: none;">
                                                                <ul style="list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 4px;">
                                                                    <?php 
                                                                    $letters = ['A', 'B', 'C', 'D', 'E', 'F'];
                                                                    foreach ($qOptions as $idx => $opt): 
                                                                        $isCorrect = !empty($opt['is_correct']);
                                                                    ?>
                                                                        <li style="display: flex; align-items: center; gap: 6px; <?php echo $isCorrect ? 'color: var(--success); font-weight: 600;' : 'color: var(--text-secondary);'; ?>">
                                                                            <span style="font-family: monospace; font-size: 11px; opacity: 0.8;"><?php echo $letters[$idx] ?? chr(65 + $idx); ?>)</span>
                                                                            <span><?php echo htmlspecialchars($opt['option_text']); ?></span>
                                                                            <?php if ($isCorrect): ?>
                                                                                <i class="fas fa-check-circle" title="Correct Answer" style="margin-left: auto; font-size: 11px;"></i>
                                                                            <?php endif; ?>
                                                                        </li>
                                                                    <?php endforeach; ?>
                                                                </ul>
                                                            </div>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge badge-secondary"><?php echo htmlspecialchars($q['subject_code'] ?? $q['subject_name'] ?? 'General'); ?></span>
                                                    <?php if (!empty($q['exam_title'])): ?>
                                                        <div style="font-size: 11px; color: var(--text-muted); margin-top: 3px; font-weight: 500;">
                                                            <?php echo htmlspecialchars($q['exam_title']); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge badge-<?php echo $isMcq ? 'info' : 'primary'; ?>" style="text-transform: uppercase; font-size: 10.5px; font-weight: 700;">
                                                        <?php echo htmlspecialchars($q['question_type']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <strong><?php echo (int)$q['marks']; ?></strong> <span style="font-size: 11px; color: var(--text-muted);">pts</span>
                                                </td>
                                                <td>
                                                    <span style="font-size: 12px; color: var(--text-muted);">
                                                        <?php echo !empty($q['created_at']) ? date('M d, Y', strtotime($q['created_at'])) : 'Recent'; ?>
                                                    </span>
                                                </td>
                                                <td style="text-align: right;">
                                                    <form method="POST" action="questions.php" onsubmit="return confirm('Delete this question from question bank?');" style="display: inline;">
                                                        <input type="hidden" name="action" value="delete_question">
                                                        <input type="hidden" name="question_id" value="<?php echo (int)$q['id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline" style="color: var(--danger); border-color: var(--danger); padding: 4px 8px; font-size: 11px;" title="Delete Question">
                                                            <i class="fas fa-trash-alt"></i>
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <?php include_once __DIR__ . '/../components/footer.php'; ?>
        </main>
    </div>

    <!-- ============================================================== -->
    <!-- Multi-Format "Add Question" Modal with 6 Distinct Upload Modes -->
    <!-- ============================================================== -->
    <div class="modal-backdrop" id="addQuestionModal">
        <div class="modal-card" style="max-width: 780px; width: 95%;">
            <div class="modal-header">
                <h3><i class="fas fa-layer-group" style="color: var(--primary);"></i> Add Questions to Bank</h3>
                <button type="button" class="modal-close" onclick="closeModal('addQuestionModal')">&times;</button>
            </div>
            <div class="modal-body" style="padding-top: 18px;">

                <!-- Format Selection Grid: 6 Distinct Upload & Entry Modes -->
                <div style="margin-bottom: 12px;">
                    <label style="font-size: 11.5px; font-weight: 700; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.5px; display: block; margin-bottom: 8px;">
                        Select Question Upload &amp; Entry Method
                    </label>
                    <div class="format-selector-grid">
                        <div class="format-tile active" onclick="switchUploadFormat('manual')" id="tile-manual">
                            <i class="fas fa-keyboard tile-icon" style="color: #4F46E5;"></i>
                            <span class="tile-title">Manual Entry</span>
                            <span class="tile-ext">Form Input</span>
                        </div>
                        <div class="format-tile" onclick="switchUploadFormat('csv')" id="tile-csv">
                            <i class="fas fa-file-csv tile-icon" style="color: #10B981;"></i>
                            <span class="tile-title">CSV</span>
                            <span class="tile-ext">.csv</span>
                        </div>
                        <div class="format-tile" onclick="switchUploadFormat('excel')" id="tile-excel">
                            <i class="fas fa-file-excel tile-icon" style="color: #059669;"></i>
                            <span class="tile-title">Excel</span>
                            <span class="tile-ext">.xlsx</span>
                        </div>
                        <div class="format-tile" onclick="switchUploadFormat('json')" id="tile-json">
                            <i class="fas fa-file-code tile-icon" style="color: #F59E0B;"></i>
                            <span class="tile-title">JSON</span>
                            <span class="tile-ext">.json</span>
                        </div>
                        <div class="format-tile" onclick="switchUploadFormat('word')" id="tile-word">
                            <i class="fas fa-file-word tile-icon" style="color: #2563EB;"></i>
                            <span class="tile-title">Word</span>
                            <span class="tile-ext">.docx</span>
                        </div>
                        <div class="format-tile" onclick="switchUploadFormat('text')" id="tile-text">
                            <i class="fas fa-file-alt tile-icon" style="color: #8B5CF6;"></i>
                            <span class="tile-title">Text</span>
                            <span class="tile-ext">.txt</span>
                        </div>
                    </div>
                </div>

                <!-- ------------------------------------------------------------------ -->
                <!-- 1. MANUAL ENTRY PANEL -->
                <!-- ------------------------------------------------------------------ -->
                <div class="format-panel active" id="panel-manual">
                    <form method="POST" action="questions.php">
                        <input type="hidden" name="action" value="add_manual">
                        <div class="form-group">
                            <label for="manualExamSelect">Linked Exam / Course *</label>
                            <select name="exam_id" id="manualExamSelect" class="form-control" required>
                                <?php foreach ($examsList as $ex): ?>
                                    <option value="<?php echo (int)$ex['id']; ?>">
                                        <?php echo htmlspecialchars($ex['title'] . (!empty($ex['subject_name']) ? ' (' . $ex['subject_name'] . ')' : '')); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="manualQText">Question Text *</label>
                            <textarea name="question_text" id="manualQText" class="form-control" rows="3" placeholder="Enter detailed question text or problem statement..." required></textarea>
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                            <div class="form-group">
                                <label for="manualTypeSelect">Question Type *</label>
                                <select name="type" id="manualTypeSelect" class="form-control" onchange="toggleManualType()">
                                    <option value="mcq">Multiple Choice (MCQ)</option>
                                    <option value="descriptive">Descriptive / Theory</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="manualMarks">Marks *</label>
                                <input type="number" name="marks" id="manualMarks" class="form-control" value="2" min="1" max="100" required>
                            </div>
                        </div>

                        <!-- MCQ Dynamic Options -->
                        <div id="manualOptionsWrapper" style="margin-top: 14px; background: rgba(0,0,0,0.18); padding: 16px; border-radius: var(--radius-md); border: 1px solid var(--border-color, rgba(255,255,255,0.08));">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                                <label style="font-weight: 700; font-size: 13px; margin: 0;">Multiple Choice Options &amp; Correct Answer</label>
                                <button type="button" class="btn btn-sm btn-secondary" onclick="addManualOptionRow()" style="font-size: 11px; padding: 3px 8px;">
                                    <i class="fas fa-plus"></i> Add Option
                                </button>
                            </div>
                            <div id="manualOptionsList">
                                <div class="mcq-option-row">
                                    <span class="mcq-option-letter">A</span>
                                    <input type="text" name="manual_options[]" class="form-control" placeholder="Option text..." required>
                                    <label class="mcq-radio-label">
                                        <input type="radio" name="manual_correct" value="0" checked> Correct
                                    </label>
                                </div>
                                <div class="mcq-option-row">
                                    <span class="mcq-option-letter">B</span>
                                    <input type="text" name="manual_options[]" class="form-control" placeholder="Option text..." required>
                                    <label class="mcq-radio-label">
                                        <input type="radio" name="manual_correct" value="1"> Correct
                                    </label>
                                </div>
                                <div class="mcq-option-row">
                                    <span class="mcq-option-letter">C</span>
                                    <input type="text" name="manual_options[]" class="form-control" placeholder="Option text...">
                                    <label class="mcq-radio-label">
                                        <input type="radio" name="manual_correct" value="2"> Correct
                                    </label>
                                </div>
                                <div class="mcq-option-row">
                                    <span class="mcq-option-letter">D</span>
                                    <input type="text" name="manual_options[]" class="form-control" placeholder="Option text...">
                                    <label class="mcq-radio-label">
                                        <input type="radio" name="manual_correct" value="3"> Correct
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="modal-footer" style="padding-left: 0; padding-right: 0; margin-top: 18px; padding-bottom: 0;">
                            <button type="button" class="btn btn-secondary" onclick="closeModal('addQuestionModal')">Cancel</button>
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Question</button>
                        </div>
                    </form>
                </div>

                <!-- ------------------------------------------------------------------ -->
                <!-- 2. CSV UPLOAD PANEL -->
                <!-- ------------------------------------------------------------------ -->
                <div class="format-panel" id="panel-csv">
                    <form method="POST" action="questions.php" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="upload_csv">
                        <div class="guide-banner">
                            <div>
                                <strong style="font-size: 13px; color: var(--text-primary);"><i class="fas fa-info-circle"></i> CSV Layout Format:</strong>
                                <div style="font-size: 11.5px; color: var(--text-muted); margin-top: 2px;">
                                    Columns: <code>Question, Type, Marks, Option A, Option B, Option C, Option D, Correct Option</code>
                                </div>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline" onclick="downloadSampleCsv()" style="font-size: 11.5px; gap: 5px;">
                                <i class="fas fa-download"></i> Sample CSV
                            </button>
                        </div>

                        <div class="form-group">
                            <label for="csvExamSelect">Linked Exam / Course *</label>
                            <select name="exam_id" id="csvExamSelect" class="form-control" required>
                                <?php foreach ($examsList as $ex): ?>
                                    <option value="<?php echo (int)$ex['id']; ?>">
                                        <?php echo htmlspecialchars($ex['title'] . (!empty($ex['subject_name']) ? ' (' . $ex['subject_name'] . ')' : '')); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="upload-dropzone" onclick="document.getElementById('csvFileInput').click()">
                            <i class="fas fa-file-csv" style="font-size: 32px; color: #10B981; margin-bottom: 8px;"></i>
                            <div style="font-weight: 600; font-size: 13px;" id="csvFileLabel">Click or Drag &amp; Drop CSV File (.csv)</div>
                            <div style="font-size: 11px; color: var(--text-muted); margin-top: 3px;">Supports comma-separated question records</div>
                            <input type="file" name="csv_file" id="csvFileInput" accept=".csv,text/csv" onchange="updateFileLabel('csvFileInput', 'csvFileLabel')">
                        </div>

                        <div class="form-group">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                                <label for="csvText">Or Paste CSV Text Directly (Optional)</label>
                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertSampleCsv()" style="font-size: 10.5px; padding: 2px 6px;">
                                    Insert Sample Data
                                </button>
                            </div>
                            <textarea name="csv_text" id="csvText" class="form-control" rows="4" placeholder="Question,Type,Marks,Option A,Option B,Option C,Option D,Correct Option&#10;What is a Primary Key?,mcq,2,Unique record ID,Foreign Table,Encrypted token,Index only,A"></textarea>
                        </div>

                        <div class="modal-footer" style="padding-left: 0; padding-right: 0; margin-top: 18px; padding-bottom: 0;">
                            <button type="button" class="btn btn-secondary" onclick="closeModal('addQuestionModal')">Cancel</button>
                            <button type="submit" class="btn btn-primary"><i class="fas fa-file-import"></i> Import CSV Questions</button>
                        </div>
                    </form>
                </div>

                <!-- ------------------------------------------------------------------ -->
                <!-- 3. EXCEL (.xlsx) UPLOAD PANEL -->
                <!-- ------------------------------------------------------------------ -->
                <div class="format-panel" id="panel-excel">
                    <form method="POST" action="questions.php" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="upload_excel">
                        <div class="guide-banner">
                            <div>
                                <strong style="font-size: 13px; color: var(--text-primary);"><i class="fas fa-info-circle"></i> Excel Spreadsheet Layout:</strong>
                                <div style="font-size: 11.5px; color: var(--text-muted); margin-top: 2px;">
                                    Sheet1 Columns: <code>A: Question, B: Type, C: Marks, D-G: Options A-D, H: Correct Option (A/B/C/D)</code>
                                </div>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline" onclick="downloadSampleCsv()" style="font-size: 11.5px; gap: 5px;">
                                <i class="fas fa-download"></i> Sample Template
                            </button>
                        </div>

                        <div class="form-group">
                            <label for="excelExamSelect">Linked Exam / Course *</label>
                            <select name="exam_id" id="excelExamSelect" class="form-control" required>
                                <?php foreach ($examsList as $ex): ?>
                                    <option value="<?php echo (int)$ex['id']; ?>">
                                        <?php echo htmlspecialchars($ex['title'] . (!empty($ex['subject_name']) ? ' (' . $ex['subject_name'] . ')' : '')); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="upload-dropzone" onclick="document.getElementById('excelFileInput').click()">
                            <i class="fas fa-file-excel" style="font-size: 32px; color: #059669; margin-bottom: 8px;"></i>
                            <div style="font-weight: 600; font-size: 13px;" id="excelFileLabel">Click or Drag &amp; Drop Excel Workbook (.xlsx)</div>
                            <div style="font-size: 11px; color: var(--text-muted); margin-top: 3px;">Supports standard OpenXML Excel spreadsheets (.xlsx, .xls)</div>
                            <input type="file" name="excel_file" id="excelFileInput" accept=".xlsx,.xls,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required onchange="updateFileLabel('excelFileInput', 'excelFileLabel')">
                        </div>

                        <div class="modal-footer" style="padding-left: 0; padding-right: 0; margin-top: 18px; padding-bottom: 0;">
                            <button type="button" class="btn btn-secondary" onclick="closeModal('addQuestionModal')">Cancel</button>
                            <button type="submit" class="btn btn-primary"><i class="fas fa-file-excel"></i> Import Excel Questions</button>
                        </div>
                    </form>
                </div>

                <!-- ------------------------------------------------------------------ -->
                <!-- 4. JSON UPLOAD PANEL -->
                <!-- ------------------------------------------------------------------ -->
                <div class="format-panel" id="panel-json">
                    <form method="POST" action="questions.php" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="upload_json">
                        <div class="guide-banner">
                            <div>
                                <strong style="font-size: 13px; color: var(--text-primary);"><i class="fas fa-info-circle"></i> JSON Structure:</strong>
                                <div style="font-size: 11.5px; color: var(--text-muted); margin-top: 2px;">
                                    Array of question objects with <code>question, type, marks, options, correct_answer</code>.
                                </div>
                            </div>
                            <div style="display: flex; gap: 6px;">
                                <button type="button" class="btn btn-sm btn-outline" onclick="downloadSampleJson()" style="font-size: 11.5px; gap: 4px;">
                                    <i class="fas fa-download"></i> Download JSON
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertSampleJson()" style="font-size: 11.5px;">
                                    <i class="fas fa-code"></i> Paste Sample
                                </button>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="jsonExamSelect">Linked Exam / Course *</label>
                            <select name="exam_id" id="jsonExamSelect" class="form-control" required>
                                <?php foreach ($examsList as $ex): ?>
                                    <option value="<?php echo (int)$ex['id']; ?>">
                                        <?php echo htmlspecialchars($ex['title'] . (!empty($ex['subject_name']) ? ' (' . $ex['subject_name'] . ')' : '')); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="upload-dropzone" onclick="document.getElementById('jsonFileInput').click()">
                            <i class="fas fa-file-code" style="font-size: 32px; color: #F59E0B; margin-bottom: 8px;"></i>
                            <div style="font-weight: 600; font-size: 13px;" id="jsonFileLabel">Click or Drag &amp; Drop JSON File (.json)</div>
                            <div style="font-size: 11px; color: var(--text-muted); margin-top: 3px;">Or paste JSON text in the editor below</div>
                            <input type="file" name="json_file" id="jsonFileInput" accept=".json,application/json" onchange="updateFileLabel('jsonFileInput', 'jsonFileLabel')">
                        </div>

                        <div class="form-group">
                            <label for="jsonText">JSON Code Payload</label>
                            <textarea name="json_text" id="jsonText" class="form-control" rows="5" style="font-family: monospace; font-size: 12px;" placeholder='[&#10;  {&#10;    "question": "What is 1NF in DBMS?",&#10;    "type": "mcq",&#10;    "marks": 2,&#10;    "options": ["Atomic values only", "No partial keys", "B-Tree keys", "Encrypted tables"],&#10;    "correct_answer": "A"&#10;  }&#10;]'></textarea>
                        </div>

                        <div class="modal-footer" style="padding-left: 0; padding-right: 0; margin-top: 18px; padding-bottom: 0;">
                            <button type="button" class="btn btn-secondary" onclick="closeModal('addQuestionModal')">Cancel</button>
                            <button type="submit" class="btn btn-primary"><i class="fas fa-code"></i> Import JSON Questions</button>
                        </div>
                    </form>
                </div>

                <!-- ------------------------------------------------------------------ -->
                <!-- 5. WORD (.docx) UPLOAD PANEL -->
                <!-- ------------------------------------------------------------------ -->
                <div class="format-panel" id="panel-word">
                    <form method="POST" action="questions.php" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="upload_word">
                        <div class="guide-banner">
                            <div>
                                <strong style="font-size: 13px; color: var(--text-primary);"><i class="fas fa-info-circle"></i> Word (.docx) Format:</strong>
                                <div style="font-size: 11.5px; color: var(--text-muted); margin-top: 2px;">
                                    Numbered items (<code>1. Question</code>) followed by <code>A) ... B) ... Answer: B, Marks: 2</code>
                                </div>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline" onclick="downloadSampleTxt()" style="font-size: 11.5px; gap: 5px;">
                                <i class="fas fa-download"></i> View Text Guide
                            </button>
                        </div>

                        <div class="form-group">
                            <label for="wordExamSelect">Linked Exam / Course *</label>
                            <select name="exam_id" id="wordExamSelect" class="form-control" required>
                                <?php foreach ($examsList as $ex): ?>
                                    <option value="<?php echo (int)$ex['id']; ?>">
                                        <?php echo htmlspecialchars($ex['title'] . (!empty($ex['subject_name']) ? ' (' . $ex['subject_name'] . ')' : '')); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="upload-dropzone" onclick="document.getElementById('wordFileInput').click()">
                            <i class="fas fa-file-word" style="font-size: 32px; color: #2563EB; margin-bottom: 8px;"></i>
                            <div style="font-weight: 600; font-size: 13px;" id="wordFileLabel">Click or Drag &amp; Drop Word Document (.docx)</div>
                            <div style="font-size: 11px; color: var(--text-muted); margin-top: 3px;">Accepts Microsoft Word documents (.docx)</div>
                            <input type="file" name="word_file" id="wordFileInput" accept=".docx,application/vnd.openxmlformats-officedocument.wordprocessingml.document" required onchange="updateFileLabel('wordFileInput', 'wordFileLabel')">
                        </div>

                        <div class="modal-footer" style="padding-left: 0; padding-right: 0; margin-top: 18px; padding-bottom: 0;">
                            <button type="button" class="btn btn-secondary" onclick="closeModal('addQuestionModal')">Cancel</button>
                            <button type="submit" class="btn btn-primary"><i class="fas fa-file-word"></i> Import Word Questions</button>
                        </div>
                    </form>
                </div>

                <!-- ------------------------------------------------------------------ -->
                <!-- 6. TEXT (.txt) UPLOAD PANEL -->
                <!-- ------------------------------------------------------------------ -->
                <div class="format-panel" id="panel-text">
                    <form method="POST" action="questions.php" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="upload_text">
                        <div class="guide-banner">
                            <div>
                                <strong style="font-size: 13px; color: var(--text-primary);"><i class="fas fa-info-circle"></i> Text (.txt) Format:</strong>
                                <div style="font-size: 11.5px; color: var(--text-muted); margin-top: 2px;">
                                    Q&amp;A blocks with <code>1. Question</code>, options <code>A), B)</code>, <code>Answer: A</code>, <code>Marks: 2</code>, or pipe format.
                                </div>
                            </div>
                            <div style="display: flex; gap: 6px;">
                                <button type="button" class="btn btn-sm btn-outline" onclick="downloadSampleTxt()" style="font-size: 11.5px; gap: 4px;">
                                    <i class="fas fa-download"></i> Sample .txt
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertSampleText()" style="font-size: 11.5px;">
                                    <i class="fas fa-paste"></i> Paste Sample
                                </button>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="textExamSelect">Linked Exam / Course *</label>
                            <select name="exam_id" id="textExamSelect" class="form-control" required>
                                <?php foreach ($examsList as $ex): ?>
                                    <option value="<?php echo (int)$ex['id']; ?>">
                                        <?php echo htmlspecialchars($ex['title'] . (!empty($ex['subject_name']) ? ' (' . $ex['subject_name'] . ')' : '')); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="upload-dropzone" onclick="document.getElementById('textFileInput').click()">
                            <i class="fas fa-file-alt" style="font-size: 32px; color: #8B5CF6; margin-bottom: 8px;"></i>
                            <div style="font-weight: 600; font-size: 13px;" id="textFileLabel">Click or Drag &amp; Drop Plain Text File (.txt)</div>
                            <div style="font-size: 11px; color: var(--text-muted); margin-top: 3px;">Or paste raw text blocks in the text area below</div>
                            <input type="file" name="text_file" id="textFileInput" accept=".txt,text/plain" onchange="updateFileLabel('textFileInput', 'textFileLabel')">
                        </div>

                        <div class="form-group">
                            <label for="textRaw">Plain Text Question Blocks</label>
                            <textarea name="text_raw" id="textRaw" class="form-control" rows="5" style="font-family: monospace; font-size: 12.5px;" placeholder="1. What is the time complexity of binary search?&#10;A) O(1)&#10;B) O(log n)&#10;C) O(n)&#10;D) O(n log n)&#10;Answer: B&#10;Marks: 2&#10;&#10;2. Define referential integrity constraint with an example.&#10;Type: Descriptive&#10;Marks: 5"></textarea>
                        </div>

                        <div class="modal-footer" style="padding-left: 0; padding-right: 0; margin-top: 18px; padding-bottom: 0;">
                            <button type="button" class="btn btn-secondary" onclick="closeModal('addQuestionModal')">Cancel</button>
                            <button type="submit" class="btn btn-primary"><i class="fas fa-file-alt"></i> Import Text Questions</button>
                        </div>
                    </form>
                </div>

            </div>
        </div>
    </div>

    <script src="../assets/js/utils.js"></script>
    <script src="../assets/js/notifications.js"></script>
    <script>
    // Format switcher for Add Question Modal
    function switchUploadFormat(format) {
        // Toggle active tile
        const tiles = document.querySelectorAll('.format-tile');
        tiles.forEach(t => t.classList.remove('active'));
        const activeTile = document.getElementById('tile-' + format);
        if (activeTile) activeTile.classList.add('active');

        // Toggle active panel
        const panels = document.querySelectorAll('.format-panel');
        panels.forEach(p => p.classList.remove('active'));
        const activePanel = document.getElementById('panel-' + format);
        if (activePanel) activePanel.classList.add('active');
    }

    // Toggle MCQ vs Descriptive in Manual Entry
    function toggleManualType() {
        const typeSelect = document.getElementById('manualTypeSelect');
        const optWrap = document.getElementById('manualOptionsWrapper');
        const marksInput = document.getElementById('manualMarks');
        if (typeSelect && optWrap) {
            if (typeSelect.value === 'mcq') {
                optWrap.style.display = 'block';
                if (marksInput && marksInput.value == 5) marksInput.value = 2;
            } else {
                optWrap.style.display = 'none';
                if (marksInput && marksInput.value == 2) marksInput.value = 5;
            }
        }
    }

    // Add another option row in Manual Entry
    function addManualOptionRow() {
        const list = document.getElementById('manualOptionsList');
        if (!list) return;
        const currentCount = list.children.length;
        if (currentCount >= 8) {
            alert('Maximum 8 options supported per question.');
            return;
        }
        const letter = String.fromCharCode(65 + currentCount);
        const row = document.createElement('div');
        row.className = 'mcq-option-row';
        row.innerHTML = `
            <span class="mcq-option-letter">${letter}</span>
            <input type="text" name="manual_options[]" class="form-control" placeholder="Option ${letter}..." required>
            <label class="mcq-radio-label">
                <input type="radio" name="manual_correct" value="${currentCount}"> Correct
            </label>
            <button type="button" class="btn btn-sm btn-outline" style="color: var(--danger); padding: 4px 7px;" onclick="this.parentElement.remove()" title="Remove option">
                <i class="fas fa-times"></i>
            </button>
        `;
        list.appendChild(row);
    }

    // Update File Label upon file selection
    function updateFileLabel(inputId, labelId) {
        const input = document.getElementById(inputId);
        const label = document.getElementById(labelId);
        if (input && input.files && input.files[0]) {
            label.textContent = 'Selected: ' + input.files[0].name + ' (' + Math.round(input.files[0].size / 1024) + ' KB)';
            label.style.color = 'var(--primary)';
        }
    }

    // Toggle options preview in questions table
    function toggleOptionsPopover(id) {
        const el = document.getElementById(id);
        if (el) {
            el.style.display = (el.style.display === 'none' || el.style.display === '') ? 'block' : 'none';
        }
    }

    // Blob File Download Helper
    function downloadBlob(content, fileName, mimeType) {
        const blob = new Blob([content], { type: mimeType });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = fileName;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    }

    // Download Sample CSV
    function downloadSampleCsv() {
        const csv = "Question,Type,Marks,Option A,Option B,Option C,Option D,Correct Option\r\n" +
            "What is a Primary Key in relational databases?,mcq,2,Unique identifier for records,Foreign table pointer,Encrypted password,Table index only,A\r\n" +
            "Which normal form eliminates partial functional dependencies?,mcq,2,First Normal Form (1NF),Second Normal Form (2NF),Third Normal Form (3NF),Boyce-Codd Normal Form (BCNF),B\r\n" +
            "Explain the ACID properties of database transactions with examples.,descriptive,5,,,,,\r\n" +
            "Which SQL clause is used to filter records after aggregation?,mcq,2,WHERE,HAVING,GROUP BY,ORDER BY,B\r\n";
        downloadBlob(csv, "questions_sample_template.csv", "text/csv;charset=utf-8;");
    }

    // Download Sample JSON
    function downloadSampleJson() {
        const json = [
            {
                "question": "What is the primary function of the DBMS buffer manager?",
                "type": "mcq",
                "marks": 2,
                "options": [
                    "Manage virtual memory and cache data pages from disk",
                    "Compile SQL queries into relational algebra",
                    "Encrypt user passwords and database connections",
                    "Generate automated database backups"
                ],
                "correct_answer": "A"
            },
            {
                "question": "Differentiate between clustered and non-clustered indexes in SQL databases.",
                "type": "descriptive",
                "marks": 5
            }
        ];
        downloadBlob(JSON.stringify(json, null, 2), "questions_sample_template.json", "application/json");
    }

    // Download Sample Text
    function downloadSampleTxt() {
        const txt = "1. What is the average time complexity of searching in a Hash Table?\n" +
            "A) O(1)\n" +
            "B) O(log n)\n" +
            "C) O(n)\n" +
            "D) O(n log n)\n" +
            "Answer: A\n" +
            "Marks: 2\n\n" +
            "2. Describe the stages of transaction execution in a write-ahead logging (WAL) system.\n" +
            "Type: Descriptive\n" +
            "Marks: 5\n";
        downloadBlob(txt, "questions_sample_template.txt", "text/plain;charset=utf-8;");
    }

    // Insert Sample Data into textareas
    function insertSampleCsv() {
        const box = document.getElementById('csvText');
        if (box) {
            box.value = "Question,Type,Marks,Option A,Option B,Option C,Option D,Correct Option\n" +
                "What is a Primary Key in relational databases?,mcq,2,Unique identifier for records,Foreign table pointer,Encrypted password,Table index only,A\n" +
                "Which normal form eliminates partial dependencies?,mcq,2,1NF,2NF,3NF,BCNF,B\n" +
                "Explain ACID transaction properties with examples.,descriptive,5,,,,,";
        }
    }

    function insertSampleJson() {
        const box = document.getElementById('jsonText');
        if (box) {
            box.value = JSON.stringify([
                {
                    "question": "What is the primary function of the DBMS buffer manager?",
                    "type": "mcq",
                    "marks": 2,
                    "options": [
                        "Manage virtual memory and cache data pages from disk",
                        "Compile SQL queries into relational algebra",
                        "Encrypt user passwords and database connections",
                        "Generate automated database backups"
                    ],
                    "correct_answer": "A"
                },
                {
                    "question": "Differentiate between clustered and non-clustered indexes.",
                    "type": "descriptive",
                    "marks": 5
                }
            ], null, 2);
        }
    }

    function insertSampleText() {
        const box = document.getElementById('textRaw');
        if (box) {
            box.value = "1. What is the average time complexity of searching in a Hash Table?\n" +
                "A) O(1)\n" +
                "B) O(log n)\n" +
                "C) O(n)\n" +
                "D) O(n log n)\n" +
                "Answer: A\n" +
                "Marks: 2\n\n" +
                "2. Describe the stages of transaction execution in a write-ahead logging (WAL) system.\n" +
                "Type: Descriptive\n" +
                "Marks: 5";
        }
    }

    // Modal helpers
    function openModal(id) {
        const modal = document.getElementById(id);
        if (modal) {
            modal.classList.add('show');
            modal.style.display = 'flex';
            modal.style.opacity = '1';
            modal.style.pointerEvents = 'auto';
            document.body.style.overflow = 'hidden';
        }
    }

    function closeModal(id) {
        const modal = document.getElementById(id);
        if (modal) {
            modal.classList.remove('show');
            modal.style.display = 'none';
            modal.style.opacity = '';
            modal.style.pointerEvents = '';
            document.body.style.overflow = '';
        }
    }

    window.addEventListener('click', function(e) {
        const modal = document.getElementById('addQuestionModal');
        if (modal && e.target === modal) {
            closeModal('addQuestionModal');
        }
    });

    window.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeModal('addQuestionModal');
        }
    });
    </script>
</body>
</html>
