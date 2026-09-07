<?php
// backend/models/AIModel.php

require_once __DIR__ . '/../config/database.php';

class AIModel {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    // Conversations
    public function getConversations($userId, $mode = 'assistant') {
        $stmt = $this->db->prepare("SELECT * FROM ai_conversations WHERE user_id = ? AND mode = ? ORDER BY updated_at DESC");
        $stmt->bind_param("is", $userId, $mode);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function createConversation($userId, $title, $mode = 'assistant') {
        $stmt = $this->db->prepare("INSERT INTO ai_conversations (user_id, title, mode) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $userId, $title, $mode);
        if ($stmt->execute()) {
            return $this->db->lastInsertId();
        }
        return false;
    }

    public function getMessages($conversationId) {
        $stmt = $this->db->prepare("SELECT * FROM ai_messages WHERE conversation_id = ? ORDER BY id ASC");
        $stmt->bind_param("i", $conversationId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function addMessage($conversationId, $role, $content, $sources = null, $tokens = 0) {
        $sourcesJson = $sources ? json_encode($sources) : null;
        $stmt = $this->db->prepare("INSERT INTO ai_messages (conversation_id, role, content, sources_json, tokens_used) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("isssi", $conversationId, $role, $content, $sourcesJson, $tokens);
        $stmt->execute();

        // Update conversation updated_at
        $up = $this->db->prepare("UPDATE ai_conversations SET updated_at = NOW() WHERE id = ?");
        $up->bind_param("i", $conversationId);
        $up->execute();

        return $this->db->lastInsertId();
    }

    // Study Plans
    public function getStudyPlans($userId) {
        $stmt = $this->db->prepare(
            "SELECT p.*, s.name AS subject_name 
             FROM ai_study_plans p 
             LEFT JOIN subjects s ON p.subject_id = s.id 
             WHERE p.user_id = ? 
             ORDER BY p.created_at DESC"
        );
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function saveStudyPlan($userId, $subjectId, $title, $content, $startDate, $endDate) {
        $stmt = $this->db->prepare("INSERT INTO ai_study_plans (user_id, subject_id, title, plan_content, start_date, end_date) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iissss", $userId, $subjectId, $title, $content, $startDate, $endDate);
        return $stmt->execute();
    }

    // Recommendations
    public function getRecommendations($userId) {
        $stmt = $this->db->prepare("SELECT * FROM ai_recommendations WHERE user_id = ? ORDER BY FIELD(priority, 'urgent', 'high', 'medium', 'low'), created_at DESC");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function addRecommendation($userId, $title, $category, $suggestion, $priority = 'medium') {
        $stmt = $this->db->prepare("INSERT INTO ai_recommendations (user_id, title, category, suggestion, priority) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("issss", $userId, $title, $category, $suggestion, $priority);
        return $stmt->execute();
    }

    // Quizzes
    public function getQuizzes($userId) {
        $stmt = $this->db->prepare("SELECT q.*, s.name AS subject_name FROM ai_quizzes q LEFT JOIN subjects s ON q.subject_id = s.id WHERE q.user_id = ? ORDER BY q.created_at DESC");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function createQuiz($userId, $subjectId, $topic, $difficulty, $questions) {
        $stmt = $this->db->prepare("INSERT INTO ai_quizzes (user_id, subject_id, topic, total_questions, difficulty) VALUES (?, ?, ?, ?, ?)");
        $total = count($questions);
        $stmt->bind_param("iisis", $userId, $subjectId, $topic, $total, $difficulty);
        if (!$stmt->execute()) {
            return false;
        }
        $quizId = $this->db->lastInsertId();

        foreach ($questions as $q) {
            $qStmt = $this->db->prepare("INSERT INTO ai_quiz_questions (quiz_id, question_text, options_json, correct_answer, explanation) VALUES (?, ?, ?, ?, ?)");
            $optionsJson = json_encode($q['options']);
            $qStmt->bind_param("issss", $quizId, $q['question'], $optionsJson, $q['correct_answer'], $q['explanation']);
            $qStmt->execute();
        }

        return $quizId;
    }

    public function getQuizWithQuestions($quizId, $userId) {
        $stmt = $this->db->prepare("SELECT * FROM ai_quizzes WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $quizId, $userId);
        $stmt->execute();
        $quiz = $stmt->get_result()->fetch_assoc();
        if (!$quiz) return null;

        $qStmt = $this->db->prepare("SELECT * FROM ai_quiz_questions WHERE quiz_id = ?");
        $qStmt->bind_param("i", $quizId);
        $qStmt->execute();
        $quiz['questions'] = $qStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        foreach ($quiz['questions'] as &$item) {
            $item['options'] = json_decode($item['options_json'], true);
        }
        return $quiz;
    }

    public function recordUsage($userId, $feature, $promptTokens, $responseTokens, $model = 'gemini-1.5-flash') {
        $stmt = $this->db->prepare("INSERT INTO ai_usage_logs (user_id, feature, prompt_tokens, response_tokens, model) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("isiis", $userId, $feature, $promptTokens, $responseTokens, $model);
        return $stmt->execute();
    }

    // RAG Document Chunks
    public function storeDocumentChunks($documentId, $chunks) {
        foreach ($chunks as $index => $text) {
            $stmt = $this->db->prepare("INSERT INTO document_chunks (document_id, chunk_index, chunk_text) VALUES (?, ?, ?)");
            $stmt->bind_param("iis", $documentId, $index, $text);
            $stmt->execute();
        }
    }

    public function searchChunks($documentId, $queryWords) {
        if (empty($queryWords)) return [];
        $likes = [];
        $params = [$documentId];
        $types = "i";

        foreach ($queryWords as $w) {
            if (strlen($w) > 2) {
                $likes[] = "chunk_text LIKE ?";
                $params[] = '%' . $w . '%';
                $types .= "s";
            }
        }
        if (empty($likes)) {
            $stmt = $this->db->prepare("SELECT * FROM document_chunks WHERE document_id = ? LIMIT 5");
            $stmt->bind_param("i", $documentId);
            $stmt->execute();
            return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        }

        $sql = "SELECT * FROM document_chunks WHERE document_id = ? AND (" . implode(" OR ", $likes) . ") LIMIT 5";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}
