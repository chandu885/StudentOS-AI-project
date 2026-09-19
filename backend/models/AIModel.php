<?php
// backend/models/AIModel.php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

class AIModel {
    private $db;

    public function __construct() {
        try {
            $this->db = Database::getInstance();
        } catch (Throwable $e) {
            $this->db = null;
        }
    }

    // ------------------------------------------------------------------------
    // AI Settings & Configuration
    // ------------------------------------------------------------------------

    /**
     * Retrieve all AI settings from database with system defaults
     */
    public function getAISettings() {
        $config = Config::getInstance();
        $defaults = [
            'gemini_api_key' => $config->get('gemini_api_key', ''),
            'default_model' => $config->get('gemini_model', 'gemini-3.5-flash-lite'),
            'gemini_key_name' => $config->get('gemini_key_name', 'chandan'),
            'gemini_project_name' => $config->get('gemini_project_name', 'project/406491916720'),
            'gemini_project_number' => $config->get('gemini_project_number', '406491916720'),
            'temperature' => '0.7',
            'max_output_tokens' => '4096',
            'enable_rag' => '1'
        ];

        try {
            $res = $this->db->query("SELECT setting_key, setting_value FROM ai_settings");
            if ($res) {
                while ($row = $res->fetch_assoc()) {
                    if (!empty($row['setting_value'])) {
                        $defaults[$row['setting_key']] = $row['setting_value'];
                    }
                }
            }
        } catch (Throwable $e) {}

        return $defaults;
    }

    /**
     * Update or insert a single AI setting
     */
    public function updateAISetting($key, $value, $description = null) {
        try {
            if ($description !== null) {
                $stmt = $this->db->prepare(
                    "INSERT INTO ai_settings (setting_key, setting_value, description, updated_at) 
                     VALUES (?, ?, ?, NOW()) 
                     ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), description = VALUES(description), updated_at = NOW()"
                );
                if ($stmt) {
                    $stmt->bind_param("sss", $key, $value, $description);
                    $res = $stmt->execute();
                    $stmt->close();
                    return $res;
                }
            } else {
                $stmt = $this->db->prepare(
                    "INSERT INTO ai_settings (setting_key, setting_value, updated_at) 
                     VALUES (?, ?, NOW()) 
                     ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()"
                );
                if ($stmt) {
                    $stmt->bind_param("ss", $key, $value);
                    $res = $stmt->execute();
                    $stmt->close();
                    return $res;
                }
            }
        } catch (Throwable $e) {}
        return false;
    }

    /**
     * Save comprehensive AI Key settings
     */
    public function saveAIKeySettings($apiKey, $keyName = 'chandan', $projectName = 'project/406491916720', $projectNumber = '406491916720', $model = 'gemini-3.6-flash') {
        $ok = true;
        if (!empty($apiKey)) {
            $ok = $this->updateAISetting('gemini_api_key', trim($apiKey), 'Google Gemini API key for AI Tutor & Question Generation') && $ok;
        }
        if (!empty($keyName)) {
            $ok = $this->updateAISetting('gemini_key_name', trim($keyName), 'Gemini Key Identifier Name') && $ok;
        }
        if (!empty($projectName)) {
            $ok = $this->updateAISetting('gemini_project_name', trim($projectName), 'Google Cloud Project Name') && $ok;
        }
        if (!empty($projectNumber)) {
            $ok = $this->updateAISetting('gemini_project_number', trim($projectNumber), 'Google Cloud Project Number') && $ok;
        }
        if (!empty($model)) {
            $ok = $this->updateAISetting('default_model', trim($model), 'Primary LLM model') && $ok;
        }
        return $ok;
    }

    /**
     * Get active AI configuration object
     */
    public function getAIConfig() {
        $settings = $this->getAISettings();
        $apiKey = $settings['gemini_api_key'] ?? Config::getInstance()->get('gemini_api_key', '');
        $masked = !empty($apiKey) ? substr($apiKey, 0, 6) . '...' . substr($apiKey, -4) : '';

        return [
            'api_key' => $apiKey,
            'api_key_masked' => $masked,
            'name' => $settings['gemini_key_name'] ?? 'chandan',
            'project_name' => $settings['gemini_project_name'] ?? 'project/406491916720',
            'project_number' => $settings['gemini_project_number'] ?? '406491916720',
            'model' => $settings['default_model'] ?? 'gemini-3.6-flash',
            'provider' => 'Google Gemini (Generative Language API)',
            'endpoint' => 'https://generativelanguage.googleapis.com/v1beta/models',
            'status' => !empty($apiKey) ? 'configured' : 'missing_key'
        ];
    }

    // ------------------------------------------------------------------------
    // Conversations & Messages
    // ------------------------------------------------------------------------

    public function getConversations($userId, $mode = 'assistant') {
        try {
            $stmt = $this->db->prepare("SELECT * FROM ai_conversations WHERE user_id = ? AND mode = ? ORDER BY updated_at DESC");
            if ($stmt) {
                $uid = (int)$userId;
                $stmt->bind_param("is", $uid, $mode);
                $stmt->execute();
                $res = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $stmt->close();
                return $res;
            }
        } catch (Throwable $e) {}
        return [];
    }

    public function createConversation($userId, $title, $mode = 'assistant') {
        if (empty($userId) || (int)$userId <= 0) return null;
        try {
            $stmt = $this->db->prepare("INSERT INTO ai_conversations (user_id, title, mode) VALUES (?, ?, ?)");
            if ($stmt) {
                $uid = (int)$userId;
                $stmt->bind_param("iss", $uid, $title, $mode);
                if ($stmt->execute()) {
                    $insertId = $this->db->lastInsertId();
                    $stmt->close();
                    return $insertId;
                }
                $stmt->close();
            }
        } catch (Throwable $e) {}
        return null;
    }

    public function getMessages($conversationId) {
        try {
            $stmt = $this->db->prepare("SELECT * FROM ai_messages WHERE conversation_id = ? ORDER BY id ASC");
            if ($stmt) {
                $cid = (int)$conversationId;
                $stmt->bind_param("i", $cid);
                $stmt->execute();
                $res = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $stmt->close();
                return $res;
            }
        } catch (Throwable $e) {}
        return [];
    }

    public function addMessage($conversationId, $role, $content, $sources = null, $tokens = 0) {
        if (empty($conversationId) || (int)$conversationId <= 0) return false;
        try {
            $sourcesJson = $sources ? json_encode($sources) : null;
            $stmt = $this->db->prepare("INSERT INTO ai_messages (conversation_id, role, content, sources_json, tokens_used) VALUES (?, ?, ?, ?, ?)");
            if ($stmt) {
                $cid = (int)$conversationId;
                $tok = (int)$tokens;
                $stmt->bind_param("isssi", $cid, $role, $content, $sourcesJson, $tok);
                $stmt->execute();
                $stmt->close();
            }

            // Update conversation timestamp
            $up = $this->db->prepare("UPDATE ai_conversations SET updated_at = NOW() WHERE id = ?");
            if ($up) {
                $cid = (int)$conversationId;
                $up->bind_param("i", $cid);
                $up->execute();
                $up->close();
            }
            return true;
        } catch (Throwable $e) {}
        return false;
    }

    // ------------------------------------------------------------------------
    // Dynamic AI Quizzes & Questions
    // ------------------------------------------------------------------------

    public function getQuizzes($userId) {
        try {
            $stmt = $this->db->prepare(
                "SELECT q.*, s.name AS subject_name 
                 FROM ai_quizzes q 
                 LEFT JOIN subjects s ON q.subject_id = s.id 
                 WHERE q.user_id = ? 
                 ORDER BY q.created_at DESC"
            );
            if ($stmt) {
                $uid = (int)$userId;
                $stmt->bind_param("i", $uid);
                $stmt->execute();
                $res = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $stmt->close();
                return $res;
            }
        } catch (Throwable $e) {}
        return [];
    }

    /**
     * Persist AI generated questions into database
     */
    public function createQuiz($userId, $subjectId, $topic, $difficulty, $questions) {
        if (empty($questions) || !is_array($questions)) {
            return false;
        }

        try {
            $stmt = $this->db->prepare("INSERT INTO ai_quizzes (user_id, subject_id, topic, total_questions, difficulty) VALUES (?, ?, ?, ?, ?)");
            if (!$stmt) return false;

            $total = count($questions);
            $subId = !empty($subjectId) ? (int)$subjectId : null;
            $uid = (int)$userId;
            $stmt->bind_param("iisis", $uid, $subId, $topic, $total, $difficulty);
            if (!$stmt->execute()) {
                $stmt->close();
                return false;
            }
            $quizId = $this->db->lastInsertId();
            $stmt->close();

            foreach ($questions as $q) {
                $qStmt = $this->db->prepare("INSERT INTO ai_quiz_questions (quiz_id, question_text, options_json, correct_answer, explanation) VALUES (?, ?, ?, ?, ?)");
                if ($qStmt) {
                    $optionsJson = json_encode($q['options'] ?? []);
                    $qText = $q['question'] ?? '';
                    $cAnswer = $q['correct_answer'] ?? '';
                    $explanation = $q['explanation'] ?? '';
                    $qStmt->bind_param("issss", $quizId, $qText, $optionsJson, $cAnswer, $explanation);
                    $qStmt->execute();
                    $qStmt->close();
                }
            }

            return $quizId;
        } catch (Throwable $e) {}
        return false;
    }

    public function getQuizWithQuestions($quizId, $userId) {
        try {
            $stmt = $this->db->prepare("SELECT * FROM ai_quizzes WHERE id = ? AND user_id = ?");
            if (!$stmt) return null;
            $qid = (int)$quizId;
            $uid = (int)$userId;
            $stmt->bind_param("ii", $qid, $uid);
            $stmt->execute();
            $quiz = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$quiz) return null;

            $qStmt = $this->db->prepare("SELECT * FROM ai_quiz_questions WHERE quiz_id = ? ORDER BY id ASC");
            if ($qStmt) {
                $qStmt->bind_param("i", $qid);
                $qStmt->execute();
                $quiz['questions'] = $qStmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $qStmt->close();
                foreach ($quiz['questions'] as &$item) {
                    $item['options'] = json_decode($item['options_json'], true) ?: [];
                }
                unset($item);
            }
            return $quiz;
        } catch (Throwable $e) {}
        return null;
    }

    // ------------------------------------------------------------------------
    // Study Plans & Recommendations
    // ------------------------------------------------------------------------

    public function getStudyPlans($userId) {
        try {
            $stmt = $this->db->prepare(
                "SELECT p.*, s.name AS subject_name 
                 FROM ai_study_plans p 
                 LEFT JOIN subjects s ON p.subject_id = s.id 
                 WHERE p.user_id = ? 
                 ORDER BY p.created_at DESC"
            );
            if ($stmt) {
                $uid = (int)$userId;
                $stmt->bind_param("i", $uid);
                $stmt->execute();
                $res = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $stmt->close();
                return $res;
            }
        } catch (Throwable $e) {}
        return [];
    }

    public function saveStudyPlan($userId, $subjectId, $title, $content, $startDate, $endDate) {
        try {
            $stmt = $this->db->prepare("INSERT INTO ai_study_plans (user_id, subject_id, title, plan_content, start_date, end_date) VALUES (?, ?, ?, ?, ?, ?)");
            if ($stmt) {
                $uid = (int)$userId;
                $subId = !empty($subjectId) ? (int)$subjectId : null;
                $stmt->bind_param("iissss", $uid, $subId, $title, $content, $startDate, $endDate);
                $res = $stmt->execute();
                $stmt->close();
                return $res;
            }
        } catch (Throwable $e) {}
        return false;
    }

    public function getRecommendations($userId) {
        try {
            $stmt = $this->db->prepare("SELECT * FROM ai_recommendations WHERE user_id = ? ORDER BY FIELD(priority, 'urgent', 'high', 'medium', 'low'), created_at DESC");
            if ($stmt) {
                $uid = (int)$userId;
                $stmt->bind_param("i", $uid);
                $stmt->execute();
                $res = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $stmt->close();
                return $res;
            }
        } catch (Throwable $e) {}
        return [];
    }

    public function addRecommendation($userId, $title, $category, $suggestion, $priority = 'medium') {
        try {
            $stmt = $this->db->prepare("INSERT INTO ai_recommendations (user_id, title, category, suggestion, priority) VALUES (?, ?, ?, ?, ?)");
            if ($stmt) {
                $uid = (int)$userId;
                $stmt->bind_param("issss", $uid, $title, $category, $suggestion, $priority);
                $res = $stmt->execute();
                $stmt->close();
                return $res;
            }
        } catch (Throwable $e) {}
        return false;
    }

    // ------------------------------------------------------------------------
    // AI Usage Logging
    // ------------------------------------------------------------------------

    public function recordUsage($userId, $feature, $promptTokens, $responseTokens, $model = 'gemini-3.6-flash') {
        if (empty($userId) || (int)$userId <= 0) return false;
        try {
            $stmt = $this->db->prepare("INSERT INTO ai_usage_logs (user_id, feature, prompt_tokens, response_tokens, model) VALUES (?, ?, ?, ?, ?)");
            if ($stmt) {
                $uid = (int)$userId;
                $pt = (int)$promptTokens;
                $rt = (int)$responseTokens;
                $stmt->bind_param("isiis", $uid, $feature, $pt, $rt, $model);
                $res = $stmt->execute();
                $stmt->close();
                return $res;
            }
        } catch (Throwable $e) {}
        return false;
    }

    // ------------------------------------------------------------------------
    // Document RAG Storage and Search
    // ------------------------------------------------------------------------

    public function storeDocumentChunks($documentId, $chunks) {
        try {
            foreach ($chunks as $index => $text) {
                $stmt = $this->db->prepare("INSERT INTO document_chunks (document_id, chunk_index, chunk_text) VALUES (?, ?, ?)");
                if ($stmt) {
                    $did = (int)$documentId;
                    $idx = (int)$index;
                    $stmt->bind_param("iis", $did, $idx, $text);
                    $stmt->execute();
                    $stmt->close();
                }
            }
        } catch (Throwable $e) {}
    }

    /**
     * High-Speed Semantic / Fulltext Search for RAG
     */
    public function searchChunks($documentId, $queryWords) {
        if (empty($queryWords)) return [];
        $did = (int)$documentId;
        if ($did <= 0) return [];

        try {
            // 1. Try FULLTEXT Natural Language / Boolean Search
            $cleanWords = array_values(array_filter(array_map('trim', $queryWords), function($w) {
                return strlen($w) >= 3;
            }));

            if (!empty($cleanWords)) {
                $booleanQuery = '+' . implode(' +', array_slice($cleanWords, 0, 8));
                $stmt = $this->db->prepare(
                    "SELECT id, chunk_index, chunk_text, MATCH(chunk_text) AGAINST (? IN BOOLEAN MODE) AS score 
                     FROM document_chunks 
                     WHERE document_id = ? AND MATCH(chunk_text) AGAINST (? IN BOOLEAN MODE) 
                     ORDER BY score DESC LIMIT 5"
                );
                if ($stmt) {
                    $stmt->bind_param("sis", $booleanQuery, $did, $booleanQuery);
                    $stmt->execute();
                    $res = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                    $stmt->close();
                    if (!empty($res)) {
                        return $res;
                    }
                }

                $naturalQuery = implode(' ', array_slice($cleanWords, 0, 10));
                $stmt = $this->db->prepare(
                    "SELECT id, chunk_index, chunk_text, MATCH(chunk_text) AGAINST (?) AS score 
                     FROM document_chunks 
                     WHERE document_id = ? AND MATCH(chunk_text) AGAINST (?) 
                     ORDER BY score DESC LIMIT 5"
                );
                if ($stmt) {
                    $stmt->bind_param("sis", $naturalQuery, $did, $naturalQuery);
                    $stmt->execute();
                    $res = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                    $stmt->close();
                    if (!empty($res)) {
                        return $res;
                    }
                }
            }

            // 2. Keyword LIKE search fallback if fulltext had no hits
            $likes = [];
            $params = [$did];
            $types = "i";
            foreach ($queryWords as $w) {
                if (strlen($w) > 2) {
                    $likes[] = "chunk_text LIKE ?";
                    $params[] = '%' . $w . '%';
                    $types .= "s";
                }
            }

            if (!empty($likes)) {
                $sql = "SELECT id, chunk_index, chunk_text FROM document_chunks WHERE document_id = ? AND (" . implode(" OR ", $likes) . ") LIMIT 5";
                $stmt = $this->db->prepare($sql);
                if ($stmt) {
                    $stmt->bind_param($types, ...$params);
                    $stmt->execute();
                    $res = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                    $stmt->close();
                    if (!empty($res)) {
                        return $res;
                    }
                }
            }

            // 3. Fallback to first 5 chunks of the document
            $stmt = $this->db->prepare("SELECT id, chunk_index, chunk_text FROM document_chunks WHERE document_id = ? ORDER BY chunk_index ASC LIMIT 5");
            if ($stmt) {
                $stmt->bind_param("i", $did);
                $stmt->execute();
                $res = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $stmt->close();
                return $res;
            }
        } catch (Throwable $e) {
            error_log("searchChunks error: " . $e->getMessage());
        }
        return [];
    }

    // ------------------------------------------------------------------------
    // AI Cache Layer (Sub-millisecond query responses)
    // ------------------------------------------------------------------------

    /**
     * Get cached response by prompt hash and model
     */
    public function getCachedResponse($promptHash, $model) {
        if (empty($promptHash) || empty($model)) return null;
        try {
            $stmt = $this->db->prepare(
                "SELECT response_text FROM ai_cache 
                 WHERE prompt_hash = ? AND model = ? AND expires_at > NOW() 
                 LIMIT 1"
            );
            if ($stmt) {
                $stmt->bind_param("ss", $promptHash, $model);
                $stmt->execute();
                $res = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if ($res && isset($res['response_text'])) {
                    // Async increment hit_count
                    $escHash = $this->db->escape($promptHash);
                    $escModel = $this->db->escape($model);
                    $this->db->query("UPDATE ai_cache SET hit_count = hit_count + 1 WHERE prompt_hash = '$escHash' AND model = '$escModel'");
                    return $res['response_text'];
                }
            }
        } catch (Throwable $e) {}
        return null;
    }

    /**
     * Save AI response to cache with TTL
     */
    public function setCachedResponse($promptHash, $model, $promptType, $promptText, $responseText, $ttlSeconds = 86400) {
        if (empty($promptHash) || empty($responseText)) return false;
        try {
            $ttl = (int)$ttlSeconds;
            $stmt = $this->db->prepare(
                "INSERT INTO ai_cache (prompt_hash, model, prompt_type, prompt_text, response_text, expires_at) 
                 VALUES (?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ? SECOND)) 
                 ON DUPLICATE KEY UPDATE response_text = VALUES(response_text), expires_at = DATE_ADD(NOW(), INTERVAL ? SECOND), hit_count = hit_count + 1"
            );
            if ($stmt) {
                $stmt->bind_param("sssssii", $promptHash, $model, $promptType, $promptText, $responseText, $ttl, $ttl);
                $res = $stmt->execute();
                $stmt->close();
                return $res;
            }
        } catch (Throwable $e) {}
        return false;
    }

    /**
     * Periodic garbage collection for expired cache entries
     */
    public function cleanExpiredCache() {
        try {
            $this->db->query("DELETE FROM ai_cache WHERE expires_at < NOW()");
        } catch (Throwable $e) {}
    }
}

