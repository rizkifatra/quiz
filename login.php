<?php
session_start();
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', 'debug.log');

require_once 'database.php';

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

try {
    // Validate input
    if (!isset($data['username']) || !isset($data['password'])) {
        throw new Exception('Missing username or password');
    }

    $database = new Database();
    $db = $database->getConnection();
    
    // Get user data
    $stmt = $db->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
    $stmt->execute([$data['username']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Debug log
    error_log("Login attempt - Username: " . $data['username']);
    error_log("User found: " . ($user ? 'yes' : 'no'));

    if ($user) {
        // Use password_verify for hashed passwords
        if (password_verify($data['password'], $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            
            error_log("Login successful for user: " . $user['username']);
            echo json_encode([
                'success' => true,
                'redirect' => 'home.php'
            ]);
        } else {
            error_log("Password verification failed for user: " . $user['username']);
            echo json_encode([
                'success' => false,
                'message' => 'Invalid username or password'
            ]);
        }
    } else {
        error_log("User not found: " . $data['username']);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid username or password'
        ]);
    }
} catch(Exception $e) {
    error_log("Login error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred during login'
    ]);
}
?>