<?php
// backend/utils/Email.php

require_once __DIR__ . '/../config/config.php';

class Email {
    private $config;
    
    public function __construct() {
        $this->config = Config::getInstance();
    }
    
    public function sendVerificationEmail($to, $name, $token) {
        $subject = 'Verify Your Email - StudentOS AI';
        $verificationLink = $this->config->get('app_url') . '/frontend/verify-email.php?token=' . $token;
        
        $html = $this->getEmailTemplate(
            'Verify Your Email',
            "Hi $name,",
            "Welcome to StudentOS AI! Please verify your email address to get started.",
            "Click the button below to verify your email:",
            'Verify Email',
            $verificationLink,
            "If you didn't create an account, you can safely ignore this email."
        );
        
        return $this->send($to, $subject, $html);
    }
    
    public function sendPasswordResetEmail($to, $name, $token) {
        $subject = 'Reset Your Password - StudentOS AI';
        $resetLink = $this->config->get('app_url') . '/frontend/reset-password.php?token=' . $token;
        
        $html = $this->getEmailTemplate(
            'Reset Your Password',
            "Hi $name,",
            "We received a request to reset your password.",
            "Click the button below to set a new password:",
            'Reset Password',
            $resetLink,
            "If you didn't request a password reset, please ignore this email. The link will expire in 1 hour."
        );
        
        return $this->send($to, $subject, $html);
    }
    
    public function sendNotificationEmail($to, $name, $subject, $message, $actionText = null, $actionLink = null) {
        $html = $this->getEmailTemplate(
            $subject,
            "Hi $name,",
            $message,
            $actionText ? "Click the button below for more details:" : '',
            $actionText,
            $actionLink,
            "This is an automated notification from StudentOS AI."
        );
        
        return $this->send($to, $subject, $html);
    }
    
    private function getEmailTemplate($title, $greeting, $message, $actionMessage, $actionText, $actionLink, $footer) {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>$title</title>
            <style>
                body { font-family: 'Inter', Arial, sans-serif; background: #0B1020; color: #F8FAFC; margin: 0; padding: 0; }
                .container { max-width: 600px; margin: 0 auto; padding: 40px 20px; }
                .card { background: #111827; border-radius: 12px; padding: 40px; border: 1px solid #1F2937; }
                .header { text-align: center; margin-bottom: 30px; }
                .header h1 { color: #6366F1; font-size: 24px; }
                .content { line-height: 1.6; }
                .button { display: inline-block; background: #6366F1; color: white; padding: 12px 32px; 
                         border-radius: 8px; text-decoration: none; margin: 20px 0; }
                .footer { text-align: center; margin-top: 30px; color: #94A3B8; font-size: 14px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='card'>
                    <div class='header'>
                        <h1>🎓 StudentOS AI</h1>
                    </div>
                    <div class='content'>
                        <p>$greeting</p>
                        <p>$message</p>
                        " . ($actionMessage ? "<p>$actionMessage</p>" : "") . "
                        " . ($actionText && $actionLink ? "
                        <div style='text-align: center;'>
                            <a href='$actionLink' class='button'>$actionText</a>
                        </div>
                        " : "") . "
                        <p style='color: #94A3B8; font-size: 14px; margin-top: 20px;'>$footer</p>
                    </div>
                    <div class='footer'>
                        <p>&copy; " . date('Y') . " StudentOS AI. All rights reserved.</p>
                    </div>
                </div>
            </div>
        </body>
        </html>
        ";
    }
    
    private function send($to, $subject, $html) {
        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $this->config->get('mail_from'),
            'Reply-To: ' . $this->config->get('mail_from'),
            'X-Mailer: PHP/' . phpversion()
        ];
        
        // Use SMTP if configured
        $host = $this->config->get('smtp_host', $this->config->get('mail_host'));
        if ($host && $host !== 'smtp.gmail.com' && !empty($this->config->get('smtp_user'))) {
            // Custom SMTP implementation or use PHPMailer
            return $this->sendSMTP($to, $subject, $html);
        }
        
        // Fallback to mail() function safely
        return @mail($to, $subject, $html, implode("\r\n", $headers));
    }
    
    private function sendSMTP($to, $subject, $html) {
        // This is a simplified version - use PHPMailer for production
        // For now, fallback to mail() safely
        return @mail($to, $subject, $html, "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\nFrom: " . $this->config->get('mail_from'));
    }
}