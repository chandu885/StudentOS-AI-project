<?php
// backend/services/AIService.php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/AIModel.php';
require_once __DIR__ . '/../models/Academic.php';
require_once __DIR__ . '/../models/Note.php';
require_once __DIR__ . '/../models/Assignment.php';
require_once __DIR__ . '/../models/Exam.php';

class AIService {
    private $aiModel;
    private $academicModel;
    private $noteModel;
    private $assignmentModel;
    private $examModel;

    // AI Key Settings
    private $apiKey;
    private $keyName;
    private $projectName;
    private $projectNumber;
    private $modelName;
    private $lastProviderUsed = 'Google Gemini 3.6 Flash';
    private $lastError = null;

    public function __construct() {
        $this->aiModel = new AIModel();
        try { $this->academicModel = new Academic(); } catch (Throwable $t) { $this->academicModel = null; }
        try { $this->noteModel = new Note(); } catch (Throwable $t) { $this->noteModel = null; }
        try { $this->assignmentModel = new Assignment(); } catch (Throwable $t) { $this->assignmentModel = null; }
        try { $this->examModel = new Exam(); } catch (Throwable $t) { $this->examModel = null; }

        $config = Config::getInstance();
        
        // Configured AI Key settings with defaults specified by the user
        $this->apiKey = $config->get('gemini_api_key', '');
        $this->modelName = $config->get('gemini_model', 'gemini-3.6-flash');
        $this->keyName = $config->get('gemini_key_name', 'chandan');
        $this->projectName = $config->get('gemini_project_name', 'project/406491916720');
        $this->projectNumber = $config->get('gemini_project_number', '406491916720');

        // Check database ai_settings table for dynamic overrides
        try {
            $db = Database::getInstance();
            $res = $db->query("SELECT setting_key, setting_value FROM ai_settings");
            if ($res) {
                while ($row = $res->fetch_assoc()) {
                    if ($row['setting_key'] === 'gemini_api_key' && !empty($row['setting_value'])) {
                        $this->apiKey = trim($row['setting_value']);
                    }
                    if ($row['setting_key'] === 'default_model' && !empty($row['setting_value'])) {
                        $this->modelName = trim($row['setting_value']);
                    }
                    if ($row['setting_key'] === 'gemini_key_name' && !empty($row['setting_value'])) {
                        $this->keyName = trim($row['setting_value']);
                    }
                    if ($row['setting_key'] === 'gemini_project_name' && !empty($row['setting_value'])) {
                        $this->projectName = trim($row['setting_value']);
                    }
                    if ($row['setting_key'] === 'gemini_project_number' && !empty($row['setting_value'])) {
                        $this->projectNumber = trim($row['setting_value']);
                    }
                }
            }
        } catch (Throwable $t) {}

        // Allow session override if specifically provided
        if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['ai_api_key'])) {
            $this->apiKey = trim($_SESSION['ai_api_key']);
        }

        // Sanitize model to guarantee an active model is used
        $this->modelName = $this->normalizeModelName($this->modelName);
    }

    /**
     * Map deprecated/retired model names to active Gemini models
     */
    public function normalizeModelName($model) {
        $m = trim($model ?? '');
        $deprecated = [
            'gemini-1.5-flash', 'gemini-1.5-pro', 'gemini-2.0-flash', 
            'gemini-2.5-flash', 'gemini-pro', 'gemini-flash', 'gemini-1.0-pro'
        ];
        if (empty($m) || in_array(strtolower($m), $deprecated)) {
            return 'gemini-3.6-flash';
        }
        return $m;
    }

    public function getLastError() {
        return $this->lastError;
    }

    /**
     * Get current AI Key configuration details
     */
    public function getKeySettings() {
        return [
            'api_key' => $this->apiKey,
            'api_key_masked' => !empty($this->apiKey) ? substr($this->apiKey, 0, 6) . '...' . substr($this->apiKey, -4) : '',
            'name' => $this->keyName,
            'project_name' => $this->projectName,
            'project_number' => $this->projectNumber,
            'model' => $this->modelName,
            'provider' => 'Google Gemini (Generative Language API)',
            'endpoint' => "https://generativelanguage.googleapis.com/v1beta/models/{$this->modelName}:generateContent"
        ];
    }

    /**
     * Update AI Key settings
     */
    public function updateKeySettings($apiKey, $keyName = null, $projectName = null, $projectNumber = null, $model = null) {
        if (!empty($apiKey)) {
            $this->apiKey = trim($apiKey);
        }
        if (!empty($keyName)) {
            $this->keyName = trim($keyName);
        }
        if (!empty($projectName)) {
            $this->projectName = trim($projectName);
        }
        if (!empty($projectNumber)) {
            $this->projectNumber = trim($projectNumber);
        }
        if (!empty($model)) {
            $this->modelName = trim($model);
        }

        return $this->aiModel->saveAIKeySettings($this->apiKey, $this->keyName, $this->projectName, $this->projectNumber, $this->modelName);
    }

    /**
     * Generate dynamic quiz questions strictly retrieved from Google Gemini API
     * (No hardcoded questions remain in code)
     */
    public function generateQuiz($userId, $subjectId, $topic, $numQuestions = 5, $difficulty = 'medium', $customApiKey = null) {
        $subject = $this->academicModel->getSubjectById($subjectId);
        $subjectName = $subject['name'] ?? 'Computer Science & Engineering';

        $numQuestions = max(1, min(20, (int)$numQuestions));

        $prompt = "Generate exactly $numQuestions unique, high-quality multiple-choice questions (MCQs) for the topic '$topic' in the academic subject '$subjectName' at a '$difficulty' difficulty level.\n"
                . "Requirements:\n"
                . "1. Provide 4 distinct options per question.\n"
                . "2. Clearly identify the single correct answer which must exactly match one of the 4 options.\n"
                . "3. Include a comprehensive pedagogical explanation of why that answer is correct and why other distractors are wrong.\n"
                . "4. Output strictly a valid JSON array of objects conforming to this schema:\n"
                . "[\n"
                . "  {\n"
                . "    \"question\": \"Question text here\",\n"
                . "    \"options\": [\"Option 1\", \"Option 2\", \"Option 3\", \"Option 4\"],\n"
                . "    \"correct_answer\": \"Option 1\",\n"
                . "    \"explanation\": \"Detailed explanation here\"\n"
                . "  }\n"
                . "]\n"
                . "Do not include any introductory remarks, markdown code blocks, or explanations outside the JSON array.";

        $systemInstruction = "You are an expert university professor and examination board chair generating rigorous academic questions.";

        // Retrieve directly from the Gemini API using JSON output mode
        $raw = $this->callGeminiApi($systemInstruction, $prompt, $customApiKey, true);

        if (empty($raw)) {
            // Fallback retry with standard generation if JSON mode failed
            $raw = $this->callGeminiApi($systemInstruction, $prompt, $customApiKey, false);
        }

        $questions = [];
        if (!empty($raw)) {
            $cleaned = trim($raw);
            $cleaned = preg_replace('/^```(?:json)?\s*/i', '', $cleaned);
            $cleaned = preg_replace('/\s*```$/i', '', $cleaned);
            
            // If json is wrapped in an object like {"questions": [...]}
            $decoded = json_decode($cleaned, true);
            if (is_array($decoded)) {
                if (isset($decoded['questions']) && is_array($decoded['questions'])) {
                    $questions = $decoded['questions'];
                } elseif (isset($decoded[0]['question'])) {
                    $questions = $decoded;
                }
            }
        }

        if (empty($questions) || !is_array($questions)) {
            return [
                'success' => false,
                'error' => 'Failed to retrieve questions from the AI API. Please verify your network connection and API key quota.',
                'provider' => $this->lastProviderUsed,
                'raw_response' => substr($raw ?? '', 0, 500)
            ];
        }

        // Format and compute correct option index
        $validQuestions = [];
        foreach ($questions as $q) {
            if (empty($q['question']) || empty($q['options']) || !is_array($q['options'])) {
                continue;
            }

            $opts = array_values($q['options']);
            $correctAnswer = $q['correct_answer'] ?? ($opts[0] ?? '');
            $correctIdx = array_search($correctAnswer, $opts);
            if ($correctIdx === false) {
                $correctIdx = 0;
                $correctAnswer = $opts[0];
            }

            $validQuestions[] = [
                'question' => (string)$q['question'],
                'options' => $opts,
                'correct_answer' => (string)$correctAnswer,
                'correct_index' => (int)$correctIdx,
                'explanation' => (string)($q['explanation'] ?? 'Consult core course syllabus.')
            ];
        }

        if (empty($validQuestions)) {
            return [
                'success' => false,
                'error' => 'AI returned an invalid question structure. Please try again.',
                'provider' => $this->lastProviderUsed
            ];
        }

        // Save generated quiz and questions into the database
        $quizId = $this->aiModel->createQuiz($userId, $subjectId, $topic, $difficulty, $validQuestions);
        $this->aiModel->recordUsage($userId, 'ai_quiz_generation', strlen($prompt)/4, strlen($raw)/4, $this->modelName);

        return [
            'success' => true,
            'quiz_id' => $quizId,
            'topic' => $topic,
            'difficulty' => $difficulty,
            'total' => count($validQuestions),
            'questions' => $validQuestions,
            'provider' => $this->lastProviderUsed
        ];
    }

    /**
     * Retrieve examination / question bank questions dynamically from Gemini API
     */
    public function fetchQuestionsFromAI($topic, $count = 5, $type = 'mcq', $difficulty = 'medium', $subjectName = 'Computer Science') {
        $count = max(1, min(20, (int)$count));

        if ($type === 'descriptive') {
            $prompt = "Generate $count rigorous university examination questions for the topic '$topic' in '$subjectName' ($difficulty difficulty).\n"
                    . "Format strictly as a JSON array of objects:\n"
                    . "[\n"
                    . "  {\n"
                    . "    \"question\": \"Question statement\",\n"
                    . "    \"marks\": 5,\n"
                    . "    \"rubric\": \"Key grading points and expected concepts\",\n"
                    . "    \"sample_answer\": \"Complete academic answer\"\n"
                    . "  }\n"
                    . "]\n"
                    . "Return only raw JSON.";
        } else {
            $prompt = "Generate $count multiple-choice examination questions for '$topic' in '$subjectName' ($difficulty difficulty).\n"
                    . "Format strictly as a JSON array of objects:\n"
                    . "[\n"
                    . "  {\n"
                    . "    \"question\": \"Question statement\",\n"
                    . "    \"options\": [\"Option A\", \"Option B\", \"Option C\", \"Option D\"],\n"
                    . "    \"correct_answer\": \"Option A\",\n"
                    . "    \"explanation\": \"Why this option is correct\"\n"
                    . "  }\n"
                    . "]\n"
                    . "Return only raw JSON.";
        }

        $raw = $this->callGeminiApi("You are an official university examination controller.", $prompt, null, true);
        if (empty($raw)) {
            $raw = $this->callGeminiApi("You are an official university examination controller.", $prompt, null, false);
        }

        $cleaned = trim($raw ?? '');
        $cleaned = preg_replace('/^```(?:json)?\s*/i', '', $cleaned);
        $cleaned = preg_replace('/\s*```$/i', '', $cleaned);
        $items = json_decode($cleaned, true);

        if (!is_array($items) || empty($items)) {
            return [
                'success' => false,
                'error' => 'Unable to retrieve questions from Gemini API. Check API key and quota.',
                'provider' => $this->lastProviderUsed
            ];
        }

        return [
            'success' => true,
            'topic' => $topic,
            'type' => $type,
            'questions' => $items,
            'provider' => $this->lastProviderUsed
        ];
    }

    /**
     * Ask Academic Assistant
     */
    public function askAssistant($userId, $question, $conversationId = null, $customApiKey = null) {
        if (!$conversationId) {
            $title = mb_substr($question, 0, 45) . '...';
            $conversationId = $this->aiModel->createConversation($userId, $title, 'assistant');
        }

        // Record user message
        $this->aiModel->addMessage($conversationId, 'user', $question);

        // Fetch student context
        $context = $this->buildStudentContext($userId);

        $systemPrompt = "You are StudentOS AI, an intelligent Google-style academic search and tutoring assistant.\n"
                      . "Tone: Clear, structured, authoritative, concise, and helpful like Google Search AI Overviews and Featured Snippets.\n"
                      . "Formatting Guidelines:\n"
                      . "1. **Direct Answer / Overview**: Start immediately with a succinct 1-2 sentence direct answer/definition that gives the student the core answer upfront.\n"
                      . "2. **Key Highlights**: Use clear, readable bullet points with **bold terms** explaining the primary concepts, mechanisms, or steps.\n"
                      . "3. **Knowledge Card / Practical Example**: Provide a real-world example, comparison table, or key formula if applicable.\n"
                      . "4. **Code / Math (if relevant)**: Provide clean, tested code blocks with language identifiers or explicit mathematical notation.\n"
                      . "5. **People Also Ask**: Conclude with 2-3 related follow-up questions students frequently ask about this subject (format each as a bullet starting with '• ').\n\n"
                      . "Context about this student:\n" . $context . "\n\n"
                      . "If asked about classes, schedules, or exams, use the provided student context.";

        $answer = $this->callGeminiApi($systemPrompt, $question, $customApiKey);

        if (empty($answer)) {
            $errDetail = !empty($this->lastError) ? " ({$this->lastError})" : "";
            $answer = "Unable to connect to Google Gemini API. Please ensure your API key ('{$this->apiKey}') has active Generative Language API permissions.{$errDetail}";
        }

        // Record assistant response
        $this->aiModel->addMessage($conversationId, 'assistant', $answer);
        $this->aiModel->recordUsage($userId, 'assistant', strlen($question)/4, strlen($answer)/4, $this->modelName);

        return [
            'success' => true,
            'conversation_id' => $conversationId,
            'answer' => $answer,
            'provider' => $this->lastProviderUsed,
            'model' => $this->modelName
        ];
    }

    /**
     * Dedicated AI Assignment Solver & Tutor
     */
    public function askAssignmentAssist($userId, $assignmentId, $question = '', $taskType = 'solve', $draftText = '', $customApiKey = null) {
        $assignment = null;
        if ($assignmentId > 0) {
            $assignment = $this->assignmentModel->findById($assignmentId);
        }

        $asgTitle = $assignment['title'] ?? 'Coursework Assignment';
        $subName = $assignment['subject_name'] ?? 'Academic Subject';
        $desc = $assignment['description'] ?? '';
        $instructions = $assignment['instructions'] ?? '';
        $maxMarks = $assignment['max_marks'] ?? 100;

        $systemPrompt = "You are StudentOS AI's Master Academic Assignment Solver & University Professor.\n"
                      . "Your task is to provide publication-grade academic solutions, code, theoretical breakdowns, and grading evaluations.\n"
                      . "Formatting Guidelines:\n"
                      . "- Use clean Markdown formatting.\n"
                      . "- Structure your response with clear sections: Executive Summary, Mathematical/Theoretical Foundation, Step-by-Step Complete Solution, Verified Code Implementation (if applicable), and Analysis/Checklist.\n"
                      . "- For code, write complete, syntax-correct, modular, commented implementations with time/space complexity notes.\n"
                      . "- For proofs or derivations, show every intermediate step clearly.\n"
                      . "- Ensure the response directly helps the student achieve full marks ({$maxMarks} pts).";

        $userPrompt = "ASSIGNMENT CONTEXT:\n"
                    . "- Subject: $subName\n"
                    . "- Assignment Title: $asgTitle\n"
                    . "- Description / Problem Statement: $desc\n"
                    . ($instructions ? "- Faculty Instructions: $instructions\n" : "")
                    . "- Maximum Marks: $maxMarks pts\n\n";

        if ($taskType === 'solve') {
            $userPrompt .= "TASK: Provide a complete, step-by-step, comprehensive academic solution for this entire assignment.\n"
                        . "Address all problem statements, show calculations/proofs, provide full working code if programming is required, and summarize final conclusions.";
        } elseif ($taskType === 'code') {
            $userPrompt .= "TASK: Generate the complete production-grade code implementation to solve this assignment.\n"
                        . "Include comments explaining key algorithmic decisions, instructions on how to run/test the code, sample inputs/outputs, and edge case handling.";
        } elseif ($taskType === 'explain') {
            $userPrompt .= "TASK: Break down and explain the core theoretical principles, formulas, and concepts behind this assignment.\n"
                        . "Provide an intuitive explanation, analogies, and a study roadmap so the student thoroughly understands the concepts.";
        } elseif ($taskType === 'review') {
            $userPrompt .= "TASK: Review and grade the student's draft response below for this assignment:\n"
                        . "STUDENT'S DRAFT RESPONSE:\n" . ($draftText ?: 'No draft text provided.') . "\n\n"
                        . "Provide a detailed evaluation: strengths, missing elements, logical/code errors, actionable fixes to get full marks, and estimated score out of $maxMarks.";
        } else {
            $userPrompt .= "STUDENT'S SPECIFIC QUESTION / REQUEST:\n" . ($question ?: "How do I solve this assignment step-by-step?");
        }

        if (!empty($question) && $taskType !== 'ask') {
            $userPrompt .= "\n\nADDITIONAL STUDENT NOTE / QUESTION:\n" . $question;
        }

        $answer = $this->callGeminiApi($systemPrompt, $userPrompt, $customApiKey);

        if (empty($answer)) {
            $answer = "Unable to retrieve assignment solution from Google Gemini API. Please verify the API connection.";
        }

        $this->aiModel->recordUsage($userId, 'assignment_assist', strlen($userPrompt)/4, strlen($answer)/4, $this->modelName);

        return [
            'success' => true,
            'assignment_id' => $assignmentId,
            'assignment_title' => $asgTitle,
            'subject_name' => $subName,
            'task_type' => $taskType,
            'answer' => $answer,
            'provider' => $this->lastProviderUsed
        ];
    }

    /**
     * PDF Q&A / Document RAG
     */
    public function askDocument($userId, $documentId, $question, $customApiKey = null) {
        $words = preg_split('/[^\w]+/', strtolower($question), -1, PREG_SPLIT_NO_EMPTY);
        $chunks = $this->aiModel->searchChunks($documentId, $words);

        $chunkContext = "";
        $sources = [];
        foreach ($chunks as $c) {
            $chunkContext .= "--- Section #" . $c['chunk_index'] . " ---\n" . $c['chunk_text'] . "\n\n";
            $sources[] = "Section #" . $c['chunk_index'];
        }

        if (empty($chunkContext)) {
            $db = Database::getInstance();
            $stmt = $db->prepare("SELECT chunk_index, chunk_text FROM document_chunks WHERE document_id = ? ORDER BY chunk_index ASC LIMIT 5");
            if ($stmt) {
                $stmt->bind_param("i", $documentId);
                $stmt->execute();
                $allChunks = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $stmt->close();
                foreach ($allChunks as $c) {
                    $chunkContext .= "--- Section #" . $c['chunk_index'] . " ---\n" . $c['chunk_text'] . "\n\n";
                    $sources[] = "Section #" . $c['chunk_index'];
                }
            }
        }

        if (empty($chunkContext)) {
            $chunkContext = "Document excerpts currently not indexed or no direct keyword match found.";
        }

        $prompt = "You are StudentOS AI Document Q&A assistant (RAG).\n"
                . "Based on the following excerpts from the student's document, provide a structured, Google-style answer to the question.\n"
                . "DOCUMENT EXCERPTS:\n" . $chunkContext . "\n\n"
                . "QUESTION:\n" . $question;

        $answer = $this->callGeminiApi("You are an academic document Q&A tutor providing Google-style structured summaries.", $prompt, $customApiKey);
        $this->aiModel->recordUsage($userId, 'pdf_qa', strlen($prompt)/4, strlen($answer)/4, $this->modelName);

        return [
            'success' => true,
            'answer' => $answer,
            'sources' => $sources,
            'provider' => $this->lastProviderUsed
        ];
    }

    /**
     * AI Study Planner
     */
    public function generateStudyPlan($userId, $subjectId, $examDate, $daysCount = 7, $customApiKey = null) {
        $subject = $this->academicModel->getSubjectById($subjectId);
        $subjectName = $subject['name'] ?? 'Academic Subject';
        $syllabus = $subject['syllabus'] ?? 'Core curriculum topics and exam review';

        $prompt = "Generate a structured, day-by-day $daysCount-day exam preparation study plan for the subject '$subjectName'.\n"
                . "Target Exam Date: $examDate.\n"
                . "Syllabus/Topics:\n$syllabus\n\n"
                . "Format the output in clear Markdown with daily study goals, recommended hours (1.5 - 2.5 hrs/day), active recall questions, and revision intervals.";

        $plan = $this->callGeminiApi("You are an expert academic study strategist.", $prompt, $customApiKey);

        $startDate = date('Y-m-d');
        $title = "$subjectName Exam Study Plan ($daysCount Days)";
        $this->aiModel->saveStudyPlan($userId, $subjectId, $title, $plan, $startDate, $examDate);

        return [
            'success' => true,
            'title' => $title,
            'plan' => $plan,
            'provider' => $this->lastProviderUsed
        ];
    }

    /**
     * AI Summarizer
     */
    public function summarizeText($userId, $text, $style = 'bullet', $customApiKey = null) {
        $prompt = "Summarize the following study material for university exams.\n"
                . "Style: High-yield bullet points, bold key terms, core formulas/theorems, and 3 vital exam takeaway points.\n\n"
                . "CONTENT:\n" . $text;

        $summary = $this->callGeminiApi("You are a university academic summarizer.", $prompt, $customApiKey);
        return [
            'success' => true,
            'summary' => $summary,
            'provider' => $this->lastProviderUsed
        ];
    }

    /**
     * AI Search across local database
     */
    public function search($userId, $query) {
        $db = Database::getInstance();
        $kw = '%' . $query . '%';

        $nStmt = $db->prepare("SELECT id, title, content, 'note' AS type FROM notes WHERE user_id = ? AND (title LIKE ? OR content LIKE ? OR tags LIKE ?) LIMIT 5");
        $nStmt->bind_param("isss", $userId, $kw, $kw, $kw);
        $nStmt->execute();
        $notes = $nStmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $aStmt = $db->prepare("SELECT id, title, description, deadline, 'assignment' AS type FROM assignments WHERE (title LIKE ? OR description LIKE ?) AND deleted_at IS NULL LIMIT 5");
        $aStmt->bind_param("ss", $kw, $kw);
        $aStmt->execute();
        $assignments = $aStmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $sStmt = $db->prepare("SELECT id, code, name, syllabus, 'subject' AS type FROM subjects WHERE (name LIKE ? OR code LIKE ? OR syllabus LIKE ?) LIMIT 5");
        $sStmt->bind_param("sss", $kw, $kw, $kw);
        $sStmt->execute();
        $subjects = $sStmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $results = array_merge($notes, $assignments, $subjects);
        $synthesis = "Found " . count($results) . " direct records matching '$query'.";
        if (!empty($results)) {
            $synthesis .= " Review the relevant items below.";
        }

        return [
            'success' => true,
            'query' => $query,
            'synthesis' => $synthesis,
            'results' => $results
        ];
    }

    /**
     * Call Google Gemini API (Universal entrypoint supporting AQ. and AIza keys)
     */
    public function callAI($systemInstruction, $userPrompt, $customApiKey = null) {
        return $this->callGeminiApi($systemInstruction, $userPrompt, $customApiKey);
    }

    /**
     * Dedicated Google Gemini API caller
     * Uses Generative Language API with configured API Key, Project Number, and Model
     */
    public function callGeminiApi($systemInstruction, $userPrompt, $customApiKey = null, $forceJson = false) {
        $activeKey = !empty($customApiKey) ? trim($customApiKey) : $this->apiKey;
        if (empty($activeKey) && session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['ai_api_key'])) {
            $activeKey = trim($_SESSION['ai_api_key']);
        }

        if (empty($activeKey)) {
            return null;
        }

        $model = $this->modelName ?: 'gemini-3.6-flash';
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent";

        $genConfig = [
            'temperature' => 0.7,
            'maxOutputTokens' => 4096
        ];

        if ($forceJson) {
            $genConfig['responseMimeType'] = 'application/json';
        }

        $payload = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [['text' => $userPrompt]]
                ]
            ],
            'generationConfig' => $genConfig
        ];

        if (!empty($systemInstruction)) {
            $payload['systemInstruction'] = [
                'parts' => [['text' => $systemInstruction]]
            ];
        }

        // Pass authentication via standard x-goog-api-key header (fully compatible with AQ. and AIza keys)
        $headers = [
            'Content-Type: application/json',
            'x-goog-api-key: ' . $activeKey
        ];

        // Include Google Cloud Project attribution if configured
        if (!empty($this->projectNumber)) {
            $headers[] = 'X-Goog-User-Project: ' . $this->projectNumber;
        }

        $response = $this->httpPostJson($url, $payload, $headers, 30);
        if (!empty($response)) {
            $data = json_decode($response, true);
            if (isset($data['candidates'][0]['content']['parts'])) {
                $text = '';
                foreach ($data['candidates'][0]['content']['parts'] as $part) {
                    if (isset($part['text']) && empty($part['thought'])) {
                        $text .= $part['text'];
                    }
                }
                if (empty($text) && isset($data['candidates'][0]['content']['parts'][0]['text'])) {
                    $text = $data['candidates'][0]['content']['parts'][0]['text'];
                }

                if (!empty($text)) {
                    $this->lastProviderUsed = "Google Gemini ({$model}) (Live)";
                    return $text;
                }
            }
        }

        return null;
    }

    /**
     * HTTP POST JSON with cURL
     */
    private function httpPostJson($url, $payload, $customHeaders = [], $timeout = 30) {
        $json = is_string($payload) ? $payload : json_encode($payload);
        $headers = array_merge(['Content-Type: application/json'], $customHeaders);

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

            $response = curl_exec($ch);
            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err = curl_error($ch);
            curl_close($ch);

            if ($err) {
                $this->lastError = "Network error: " . $err;
                error_log("cURL Error: " . $err);
            } elseif ($status >= 200 && $status < 300 && !empty($response)) {
                $this->lastError = null;
                return $response;
            } else {
                $errMsg = "HTTP $status";
                if (!empty($response)) {
                    $jsonErr = json_decode($response, true);
                    if (!empty($jsonErr['error']['message'])) {
                        $errMsg .= ": " . $jsonErr['error']['message'];
                    }
                }
                $this->lastError = $errMsg;
                error_log("Gemini API Error: " . $errMsg);
            }
        }

        return null;
    }

    private function buildStudentContext($userId) {
        try {
            $subs = $this->academicModel->getStudentSubjects($userId);
            if (!empty($subs)) {
                $subList = implode(', ', array_map(function($s) { return ($s['name'] ?? '') . ' (' . ($s['code'] ?? '') . ')'; }, $subs));
                return "Student Enrolled Subjects: " . $subList;
            }
        } catch (Throwable $e) {}
        return "Student Enrolled Subjects: Computer Science Core Subjects";
    }
}
