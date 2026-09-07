<?php
// backend/models/Task.php

require_once __DIR__ . '/../config/database.php';

class Task {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function getByUser($userId, $status = null) {
        if ($status) {
            $stmt = $this->db->prepare("SELECT * FROM tasks WHERE user_id = ? AND status = ? ORDER BY deadline ASC, created_at DESC");
            $stmt->bind_param("is", $userId, $status);
        } else {
            $stmt = $this->db->prepare("SELECT * FROM tasks WHERE user_id = ? ORDER BY FIELD(status, 'todo', 'in_progress', 'completed'), deadline ASC");
            $stmt->bind_param("i", $userId);
        }
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function create($userId, $data) {
        $stmt = $this->db->prepare(
            "INSERT INTO tasks (user_id, title, description, priority, status, category, deadline)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $priority = $data['priority'] ?? 'medium';
        $status = $data['status'] ?? 'todo';
        $category = $data['category'] ?? 'academic';
        $deadline = !empty($data['deadline']) ? $data['deadline'] : null;

        $stmt->bind_param(
            "issssss",
            $userId,
            $data['title'],
            $data['description'],
            $priority,
            $status,
            $category,
            $deadline
        );
        return $stmt->execute();
    }

    public function updateStatus($taskId, $userId, $status) {
        $stmt = $this->db->prepare("UPDATE tasks SET status = ?, updated_at = NOW() WHERE id = ? AND user_id = ?");
        $stmt->bind_param("sii", $status, $taskId, $userId);
        return $stmt->execute();
    }

    public function delete($taskId, $userId) {
        $stmt = $this->db->prepare("DELETE FROM tasks WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $taskId, $userId);
        return $stmt->execute();
    }

    // Goals helper
    public function getGoals($userId) {
        $stmt = $this->db->prepare("SELECT * FROM goals WHERE user_id = ? ORDER BY target_date ASC");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function createGoal($userId, $data) {
        $stmt = $this->db->prepare("INSERT INTO goals (user_id, title, description, category, target_date, progress, status) VALUES (?, ?, ?, ?, ?, ?, 'in_progress')");
        $progress = (int)($data['progress'] ?? 0);
        $targetDate = !empty($data['target_date']) ? $data['target_date'] : null;
        $category = $data['category'] ?? 'academic';
        $stmt->bind_param("issssi", $userId, $data['title'], $data['description'], $category, $targetDate, $progress);
        return $stmt->execute();
    }

    public function updateGoalProgress($goalId, $userId, $progress) {
        $status = $progress >= 100 ? 'completed' : 'in_progress';
        $stmt = $this->db->prepare("UPDATE goals SET progress = ?, status = ?, updated_at = NOW() WHERE id = ? AND user_id = ?");
        $stmt->bind_param("isii", $progress, $status, $goalId, $userId);
        return $stmt->execute();
    }

    // Study sessions
    public function getStudySessions($userId) {
        $stmt = $this->db->prepare("SELECT ss.*, s.name AS subject_name FROM study_sessions ss LEFT JOIN subjects s ON ss.subject_id = s.id WHERE ss.user_id = ? ORDER BY ss.session_date DESC, ss.created_at DESC");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function logStudySession($userId, $subjectId, $topic, $durationMinutes, $notes) {
        $stmt = $this->db->prepare("INSERT INTO study_sessions (user_id, subject_id, topic, duration_minutes, notes, session_date) VALUES (?, ?, ?, ?, ?, CURDATE())");
        $stmt->bind_param("iisis", $userId, $subjectId, $topic, $durationMinutes, $notes);
        return $stmt->execute();
    }
}
