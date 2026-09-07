<?php
// backend/utils/Validator.php

class Validator {
    public function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    public function validatePassword($password) {
        if (strlen($password) < 8) {
            return ['valid' => false, 'message' => 'Password must be at least 8 characters long'];
        }
        if (!preg_match('/[A-Z]/', $password)) {
            return ['valid' => false, 'message' => 'Password must contain at least one uppercase letter'];
        }
        if (!preg_match('/[a-z]/', $password)) {
            return ['valid' => false, 'message' => 'Password must contain at least one lowercase letter'];
        }
        if (!preg_match('/[0-9]/', $password)) {
            return ['valid' => false, 'message' => 'Password must contain at least one number'];
        }
        if (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) {
            return ['valid' => false, 'message' => 'Password must contain at least one special character'];
        }
        return ['valid' => true, 'message' => 'Password is valid'];
    }
    
    public function validatePhone($phone) {
        return preg_match('/^[0-9]{10}$/', $phone) === 1;
    }
    
    public function validateDate($date) {
        $d = DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }
    
    public function validateFile($file, $allowedExtensions = null) {
        if (!$allowedExtensions) {
            $allowedExtensions = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'txt'];
        }
        
        if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            return ['valid' => false, 'message' => 'File upload error'];
        }
        
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $allowedExtensions)) {
            return ['valid' => false, 'message' => 'File type not allowed'];
        }
        
        $maxSize = Config::getInstance()->get('max_file_size');
        if ($file['size'] > $maxSize) {
            return ['valid' => false, 'message' => 'File size exceeds limit'];
        }
        
        return ['valid' => true, 'message' => 'File is valid'];
    }
    
    public function sanitize($input) {
        if (is_array($input)) {
            return array_map([$this, 'sanitize'], $input);
        }
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }
    
    public function validateRequired($data, $fields) {
        $missing = [];
        foreach ($fields as $field) {
            if (empty($data[$field]) && $data[$field] !== '0') {
                $missing[] = $field;
            }
        }
        return empty($missing) ? ['valid' => true] : ['valid' => false, 'missing' => $missing];
    }
}