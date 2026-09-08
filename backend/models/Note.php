<?php
// backend/models/Note.php

require_once __DIR__ . '/../config/database.php';

class Note {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function getNotes($userId, $subjectId = null) {
        if ($subjectId) {
            $stmt = $this->db->prepare(
                "SELECT n.*, s.name AS subject_name, s.code AS subject_code 
                 FROM notes n 
                 LEFT JOIN subjects s ON n.subject_id = s.id 
                 WHERE n.user_id = ? AND n.subject_id = ? 
                 ORDER BY n.is_pinned DESC, n.updated_at DESC"
            );
            $stmt->bind_param("ii", $userId, $subjectId);
        } else {
            $stmt = $this->db->prepare(
                "SELECT n.*, s.name AS subject_name, s.code AS subject_code 
                 FROM notes n 
                 LEFT JOIN subjects s ON n.subject_id = s.id 
                 WHERE n.user_id = ? 
                 ORDER BY n.is_pinned DESC, n.updated_at DESC"
            );
            $stmt->bind_param("i", $userId);
        }
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function getNoteById($noteId, $userId) {
        $stmt = $this->db->prepare("SELECT * FROM notes WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $noteId, $userId);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    public function createNote($userId, $subjectId, $title, $content, $tags = null) {
        $stmt = $this->db->prepare("INSERT INTO notes (user_id, subject_id, title, content, tags) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("iisss", $userId, $subjectId, $title, $content, $tags);
        return $stmt->execute();
    }

    public function updateNote($noteId, $userId, $title, $content, $tags, $isPinned = 0) {
        $stmt = $this->db->prepare("UPDATE notes SET title = ?, content = ?, tags = ?, is_pinned = ?, updated_at = NOW() WHERE id = ? AND user_id = ?");
        $stmt->bind_param("sssiii", $title, $content, $tags, $isPinned, $noteId, $userId);
        return $stmt->execute();
    }

    public function deleteNote($noteId, $userId) {
        $stmt = $this->db->prepare("DELETE FROM notes WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $noteId, $userId);
        return $stmt->execute();
    }

    // Documents
    public function getDocuments($userId) {
        $stmt = $this->db->prepare("SELECT d.*, s.name AS subject_name FROM documents d LEFT JOIN subjects s ON d.subject_id = s.id WHERE d.user_id = ? OR d.is_public = 1 ORDER BY d.created_at DESC");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function createDocument($userId, $subjectId, $title, $filePath, $fileSize, $fileType, $desc = null) {
        $stmt = $this->db->prepare("INSERT INTO documents (user_id, subject_id, title, file_path, file_size, file_type, description) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iississ", $userId, $subjectId, $title, $filePath, $fileSize, $fileType, $desc);
        if ($stmt->execute()) {
            return $this->db->lastInsertId();
        }
        return false;
    }
}
