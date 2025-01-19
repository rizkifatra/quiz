<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', 'debug.log');

require_once 'database.php';

$data = json_decode(file_get_contents('php://input'), true);

try {
    // Validate input
    if (!isset($data['username']) || !isset($data['email']) || !isset($data['password'])) {
        throw new Exception('Missing required fields');
    }

    $username = trim($data['username']);
    $email = trim($data['email']);
    $password = trim($data['password']);

    // Password validation
    if (strlen($password) < 3) {
        throw new Exception('Password must be at least 6 characters');
    }

    $database = new Database();
    $db = $database->getConnection();

    // Check existing user
    $stmt = $db->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$username, $email]);
    if ($stmt->fetch()) {
        throw new Exception('Username or email already exists');
    }

    // Debug original password
    error_log("Original password: " . $password);

    // Create password hash
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    // Verify hash was created correctly
    if (!$hashedPassword) {
        throw new Exception('Password hashing failed');
    }

    // Debug hashed password
    error_log("Hashed password: " . $hashedPassword);
    
    // Verify hash works
    if (!password_verify($password, $hashedPassword)) {
        throw new Exception('Password hash verification failed');
    }

    // Insert user
    $stmt = $db->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
    if (!$stmt->execute([$username, $email, $hashedPassword])) {
        throw new Exception('Failed to create user');
    }

    // Debug successful registration
    error_log("Registration successful for user: " . $username);
    error_log("Stored hash: " . $hashedPassword);

    echo json_encode([
        'success' => true,
        'message' => 'Registration successful'
    ]);

} catch (Exception $e) {
    error_log("Registration error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}