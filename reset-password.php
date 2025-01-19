<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', 'debug.log');

require 'vendor/autoload.php';
require_once 'database.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['email'])) {
        throw new Exception('Email is required');
    }

    $email = filter_var($data['email'], FILTER_VALIDATE_EMAIL);
    if (!$email) {
        throw new Exception('Invalid email format');
    }

    // Database operations
    $database = new Database();
    $db = $database->getConnection();
    
    $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    
    if (!$stmt->fetch()) {
        throw new Exception('Email not found');
    }

    // Generate token
    $token = bin2hex(random_bytes(32));
    $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
    
    $stmt = $db->prepare("UPDATE users SET reset_token = ?, reset_expiry = ? WHERE email = ?");
    $stmt->execute([$token, $expiry, $email]);

    // Email configuration
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'rizkifatra31@gmail.com';
    $mail->Password = 'csgtjktolxvqnxyd';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;

    $mail->setFrom('rizkifatra31@gmail.com', 'Quiz App');
    $mail->addAddress($email);
    $mail->isHTML(true);
    
     $resetLink = "http://" . $_SERVER['HTTP_HOST'] . "/QUIZ/reset-password.html?token=" . $token;
    
    $mail->Subject = 'Reset Your Password';
    $mail->Body = "
        <h2>Password Reset Request</h2>
        <p>Click the link below to reset your password:</p>
        <p><a href='{$resetLink}'>Reset Password</a></p>
        <p>Or copy this URL: {$resetLink}</p>
        <p>This link will expire in 1 hour.</p>
    ";

    $mail->send();
    
    echo json_encode([
        'success' => true,
        'message' => 'Reset instructions sent to your email'
    ]);

} catch (Exception $e) {
    error_log("Reset password error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to send reset email'
    ]);
}
?>