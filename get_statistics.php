<?php
header('Content-Type: application/json');

require_once 'database.php';

try {
    // Get database connection using Database class
    $database = new Database();
    $db = $database->getConnection();

    // Assuming user is logged in and user_id is available in session
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 10; // Default to 10 for testing

    // Get total comparisons for user
    $totalQuery = $db->prepare("SELECT COUNT(*) as total FROM image_comparisons WHERE user_id = ?");
    $totalQuery->execute([$user_id]);
    $total = $totalQuery->fetch(PDO::FETCH_ASSOC)['total'];

    // Get average score for user
    $avgQuery = $db->prepare("SELECT AVG(similarity_score) as average FROM image_comparisons WHERE user_id = ?");
    $avgQuery->execute([$user_id]);
    $average = $avgQuery->fetch(PDO::FETCH_ASSOC)['average'];

    // Get highest score for user
    $maxQuery = $db->prepare("SELECT MAX(similarity_score) as highest FROM image_comparisons WHERE user_id = ?");
    $maxQuery->execute([$user_id]);
    $highest = $maxQuery->fetch(PDO::FETCH_ASSOC)['highest'];

    // Get comparison history (last 10 entries) for user
    $historyQuery = $db->prepare("
        SELECT 
            id,
            similarity_score,
            created_at as timestamp,
            text1,
            text2
        FROM image_comparisons 
        WHERE user_id = ? AND similarity_score IS NOT NULL
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $historyQuery->execute([$user_id]);
    $history = $historyQuery->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'statistics' => [
            'total_comparisons' => (int)$total,
            'average_score' => (float)($average ?: 0),
            'highest_score' => (float)($highest ?: 0)
        ],
        'history' => array_map(function($item) {
            return [
                'id' => (int)$item['id'],
                'similarity_score' => (float)$item['similarity_score'],
                'timestamp' => $item['timestamp'],
                'text1' => $item['text1'] ?: 'No text available',
                'text2' => $item['text2'] ?: 'No text available'
            ];
        }, $history)
    ]);

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Server error: ' . $e->getMessage()
    ]);
}
?>
