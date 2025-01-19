<?php
header('Content-Type: application/json');
require_once 'database.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', 'debug.log');

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['token']) || !isset($data['password'])) {
        throw new Exception('Missing required fields');
    }

    $database = new Database();
    $db = $database->getConnection();
    
    // Debug token
    error_log("Received token: " . $data['token']);
    
    // Check token in database
    $stmt = $db->prepare("SELECT id, reset_token, reset_expiry FROM users WHERE reset_token = ?");
    $stmt->execute([$data['token']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    error_log("User found: " . ($user ? 'Yes' : 'No'));
    if ($user) {
        error_log("Token in DB: " . $user['reset_token']);
        error_log("Token expiry: " . $user['reset_expiry']);
    }

    if (!$user || $user['reset_expiry'] < date('Y-m-d H:i:s')) {
        throw new Exception('Invalid or expired reset token');
    }

    // Update password
    $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
    $stmt = $db->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_expiry = NULL WHERE id = ?");
    $stmt->execute([$hashedPassword, $user['id']]);

    error_log("Password updated successfully for user ID: " . $user['id']);
    
    echo json_encode([
        'success' => true,
        'message' => 'Password updated successfully'
    ]);

} catch (Exception $e) {
    error_log("Error in update-password.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>