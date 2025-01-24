<?php
session_start();
header('Content-Type: application/json');
require_once 'database.php';

try {
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('User not logged in');
    }

    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['image']) || !isset($data['text'])) {
        throw new Exception('Missing required fields');
    }

    $database = new Database();
    $db = $database->getConnection();
    
    // Updated query to include user_id
    $stmt = $db->prepare("INSERT INTO captured_images (image_data, converted_text, user_id, created_at) VALUES (?, ?, ?, NOW())");
    
    if ($stmt->execute([$data['image'], $data['text'], $_SESSION['user_id']])) {
        echo json_encode([
            'success' => true,
            'message' => 'Image and text saved successfully'
        ]);
    } else {
        throw new Exception('Failed to save to database');
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>