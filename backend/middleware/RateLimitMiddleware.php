<?php
// backend/middleware/RateLimitMiddleware.php

class RateLimitMiddleware {
    private $db;
    private $limit;
    private $window;
    
    public function __construct($limit = 100, $window = 3600) {
        $this->db = Database::getInstance();
        $this->limit = $limit;
        $this->window = $window;
    }
    
    public function check($key, $ip = null) {
        $ip = $ip ?? $_SERVER['REMOTE_ADDR'];
        $identifier = $key . ':' . $ip;
        $windowStart = time() - $this->window;
        
        // Clean old entries
        $this->cleanOldEntries($windowStart);
        
        // Count requests
        $count = $this->countRequests($identifier, $windowStart);
        
        if ($count >= $this->limit) {
            http_response_code(429);
            echo json_encode([
                'error' => 'Rate limit exceeded',
                'limit' => $this->limit,
                'window' => $this->window
            ]);
            exit;
        }
        
        // Log this request
        $this->logRequest($identifier);
        
        return true;
    }
    
    private function cleanOldEntries($windowStart) {
        $stmt = $this->db->prepare("DELETE FROM rate_limits WHERE created_at < ?");
        $stmt->bind_param("i", $windowStart);
        $stmt->execute();
    }
    
    private function countRequests($identifier, $windowStart) {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) as count FROM rate_limits 
             WHERE identifier = ? AND created_at >= ?"
        );
        $stmt->bind_param("si", $identifier, $windowStart);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        return $row['count'] ?? 0;
    }
    
    private function logRequest($identifier) {
        $stmt = $this->db->prepare(
            "INSERT INTO rate_limits (identifier, created_at) VALUES (?, ?)"
        );
        $createdAt = time();
        $stmt->bind_param("si", $identifier, $createdAt);
        $stmt->execute();
    }
}