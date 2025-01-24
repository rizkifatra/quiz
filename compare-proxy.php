<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');
require_once 'database.php';

try {
    error_log("Starting image comparison process");

    if (!isset($_SESSION['user_id'])) {
        throw new Exception('User not logged in');
    }

    // Define upload directory with full path
    $upload_dir = dirname(__FILE__) . '/uploads';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    function processImage($base64_string, $upload_dir) {
        if (empty($base64_string)) {
            throw new Exception('Empty image data');
        }

        if (strpos($base64_string, ',') !== false) {
            list(, $base64_string) = explode(',', $base64_string);
        }
        
        $image_data = base64_decode($base64_string, true);
        if ($image_data === false) {
            throw new Exception('Invalid base64 image data');
        }
        
        $filename = $upload_dir . '/img_' . uniqid() . '.jpg';
        if (file_put_contents($filename, $image_data) === false) {
            throw new Exception('Failed to save image file');
        }
        
        return $filename;
    }

    $data = json_decode(file_get_contents('php://input'), true);
    if (!isset($data['image1']) || !isset($data['image2'])) {
        throw new Exception('Missing image data');
    }

    // Process images
    $temp_file1 = processImage($data['image1'], $upload_dir);
    $temp_file2 = processImage($data['image2'], $upload_dir);

    // OCR API requests
    $api_key = '25hNzTnP1kiYOyBAIZpNLt9kP2GEtJqMG8wM66WE';
    
    $ch1 = curl_init('https://api.api-ninjas.com/v1/imagetotext');
    curl_setopt_array($ch1, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => ['image' => new CURLFile($temp_file1)],
        CURLOPT_HTTPHEADER => ['X-Api-Key: ' . $api_key]
    ]);

    $ch2 = curl_init('https://api.api-ninjas.com/v1/imagetotext');
    curl_setopt_array($ch2, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => ['image' => new CURLFile($temp_file2)],
        CURLOPT_HTTPHEADER => ['X-Api-Key: ' . $api_key]
    ]);

    $text1 = curl_exec($ch1);
    $text2 = curl_exec($ch2);

    // Cleanup temp files
    unlink($temp_file1);
    unlink($temp_file2);

    if (curl_errno($ch1) || curl_errno($ch2)) {
        throw new Exception('OCR API request failed');
    }

    curl_close($ch1);
    curl_close($ch2);

    // Process OCR results
    $result1 = json_decode($text1, true);
    $result2 = json_decode($text2, true);

    $extracted_text1 = '';
    $extracted_text2 = '';

    if (is_array($result1)) {
        foreach ($result1 as $item) {
            $extracted_text1 .= $item['text'] . ' ';
        }
    }

    if (is_array($result2)) {
        foreach ($result2 as $item) {
            $extracted_text2 .= $item['text'] . ' ';
        }
    }

    // Compare texts
    $similarity = 0;
    if ($extracted_text1 && $extracted_text2) {
        $ch = curl_init('https://api.api-ninjas.com/v1/textsimilarity');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode([
                'text_1' => $extracted_text1,
                'text_2' => $extracted_text2
            ]),
            CURLOPT_HTTPHEADER => [
                'X-Api-Key: ' . $api_key,
                'Content-Type: application/json'
            ]
        ]);

        $response = curl_exec($ch);
        $result = json_decode($response, true);
        $similarity = $result['similarity'] ?? 0;
        curl_close($ch);
    }

    // Save to database
    $database = new Database();
    $db = $database->getConnection();
    
    $stmt = $db->prepare("
        INSERT INTO image_comparisons 
            (user_id, image1_data, image2_data, text1, text2, similarity_score, created_at) 
        VALUES 
            (?, ?, ?, ?, ?, ?, NOW())
    ");
    
    $stmt->execute([
        $_SESSION['user_id'],
        $data['image1'],
        $data['image2'],
        $extracted_text1,
        $extracted_text2,
        $similarity
    ]);

    echo json_encode([
        'success' => true,
        'similarity' => $similarity,
        'text1' => $extracted_text1,
        'text2' => $extracted_text2,
        'user_id' => $_SESSION['user_id']
    ]);

} catch (Exception $e) {
    error_log("Error in compare-proxy.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>