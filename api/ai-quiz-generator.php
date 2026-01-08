<?php
header('Content-Type: application/json');
require_once '../config/config.php';
require_once '../includes/auth-check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$content = $data['content'] ?? '';
$questionCount = $data['questionCount'] ?? 10;

if (empty($content)) {
    http_response_code(400);
    echo json_encode(['error' => 'Content is required']);
    exit;
}

// Create AI prompt for quiz generation
$prompt = "Generate $questionCount multiple-choice questions based on the following content. Return in JSON format with question, options (A, B, C, D), and correct answer. Content: $content";

$curl = curl_init();
curl_setopt_array($curl, [
    CURLOPT_URL => AI_BASE_URL,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode([
        'model' => 'gpt-3.5-turbo',
        'messages' => [
            ['role' => 'user', 'content' => $prompt]
        ],
        'temperature' => 0.7
    ]),
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . AI_API_KEY
    ]
]);

$response = curl_exec($curl);
$httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
curl_close($curl);

if ($httpCode === 200) {
    $result = json_decode($response, true);
    $aiResponse = $result['choices'][0]['message']['content'];
    
    // Clean the response and convert to JSON
    $jsonStart = strpos($aiResponse, '[');
    $jsonEnd = strrpos($aiResponse, ']') + 1;
    $jsonStr = substr($aiResponse, $jsonStart, $jsonEnd - $jsonStart);
    
    $questions = json_decode($jsonStr, true);
    
    if ($questions) {
        echo json_encode(['questions' => $questions]);
    } else {
        echo json_encode(['error' => 'Failed to parse AI response']);
    }
} else {
    echo json_encode(['error' => 'AI service error']);
}
?>