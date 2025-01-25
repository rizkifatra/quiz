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

    // Define upload directory with proper permissions
    $upload_dir = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'uploads';
    
    // Check and create directory with proper permissions
    if (!file_exists($upload_dir)) {
        if (!@mkdir($upload_dir, 0755, true)) {
            error_log("Failed to create directory: " . error_get_last()['message']);
            throw new Exception('Failed to create upload directory');
        }
    }

    // Verify directory is writable
    if (!is_writable($upload_dir)) {
        error_log("Directory not writable: " . $upload_dir);
        throw new Exception('Upload directory is not writable');
    }

    function processImage($base64_string, $upload_dir) {
        if (empty($base64_string)) {
            throw new Exception('Empty image data');
        }

        // Clean up base64 string
        $base64_string = str_replace('data:image/jpeg;base64,', '', $base64_string);
        $base64_string = str_replace('data:image/png;base64,', '', $base64_string);
        $base64_string = str_replace(' ', '+', $base64_string);
        
        $image_data = base64_decode($base64_string);
        $filename = $upload_dir . DIRECTORY_SEPARATOR . uniqid() . '.jpg';

        // Save original image directly
        if (file_put_contents($filename, $image_data) === false) {
            throw new Exception('Failed to save image file');
        }

        return $filename;
    }

    $data = json_decode(file_get_contents('php://input'), true);
    if (!isset($data['image1']) || !isset($data['image2'])) {
        throw new Exception('Missing image data');
    }

    // Process images with enhanced error logging
    error_log("Processing first image");
    $temp_file1 = processImage($data['image1'], $upload_dir);
    error_log("First image saved as: " . $temp_file1);
    
    error_log("Processing second image");
    $temp_file2 = processImage($data['image2'], $upload_dir);
    error_log("Second image saved as: " . $temp_file2);

    // Improved OCR API requests
    $api_key = '25hNzTnP1kiYOyBAIZpNLt9kP2GEtJqMG8wM66WE';
    
    function makeOCRRequest($file, $api_key) {
        if (!file_exists($file)) {
            throw new Exception('Image file not found');
        }

        // Create the multipart form data
        $cfile = new CURLFile($file, 'image/jpeg', 'image.jpg');
        
        $ch = curl_init('https://api.api-ninjas.com/v1/imagetotext');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => [
                'image' => $cfile
            ],
            CURLOPT_HTTPHEADER => [
                'X-Api-Key: ' . $api_key,
                'Accept: application/json'
            ],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_VERBOSE => true
        ]);

        $response = curl_exec($ch);
        
        if (curl_errno($ch)) {
            error_log("Curl Error: " . curl_error($ch));
            throw new Exception('Curl error: ' . curl_error($ch));
        }
        
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        error_log("API Response Code: " . $http_code);
        error_log("API Response: " . $response);
        
        if ($http_code !== 200) {
            throw new Exception("OCR API error (HTTP $http_code): $response");
        }

        return $response;
    }

    // Make OCR requests with better error handling
    error_log("Starting OCR for first image");
    $text1 = makeOCRRequest($temp_file1, $api_key);
    error_log("OCR Response 1: " . $text1);
    
    error_log("Starting OCR for second image");
    $text2 = makeOCRRequest($temp_file2, $api_key);
    error_log("OCR Response 2: " . $text2);

    // Cleanup temp files
    unlink($temp_file1);
    unlink($temp_file2);

    // Process OCR results with improved text extraction
    $result1 = json_decode($text1, true);
    $result2 = json_decode($text2, true);

    $extracted_text1 = '';
    $extracted_text2 = '';

    if (is_array($result1)) {
        $extracted_text1 = implode(' ', array_map(function($item) {
            return $item['text'] ?? '';
        }, $result1));
    }

    if (is_array($result2)) {
        $extracted_text2 = implode(' ', array_map(function($item) {
            return $item['text'] ?? '';
        }, $result2));
    }

    // Clean up extracted text
    $extracted_text1 = trim(preg_replace('/\s+/', ' ', $extracted_text1));
    $extracted_text2 = trim(preg_replace('/\s+/', ' ', $extracted_text2));

    error_log("Extracted Text 1: " . $extracted_text1);
    error_log("Extracted Text 2: " . $extracted_text2);

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