<?php
session_start();
require_once '../config/config.php';
require_once '../includes/auth-check.php';
require_once '../includes/ai-helper.php';

if ($_SESSION['role'] !== 'faculty') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$moduleId = (int) ($_POST['module_id'] ?? 0);
$numQuestions = min(20, max(5, (int)($_POST['num_questions'] ?? 10)));

if (!$moduleId) {
    echo json_encode(['error' => 'Module ID is required']);
    exit;
}

$stmt = $pdo->prepare("
    SELECT m.content, c.faculty_id 
    FROM modules m
    JOIN courses c ON m.course_id = c.id
    WHERE m.id = ? AND c.faculty_id = ?
");
$stmt->execute([$moduleId, $_SESSION['user_id']]);
$module = $stmt->fetch();

if (!$module) {
    echo json_encode(['error' => 'Module not found or access denied']);
    exit;
}

$content = trim($module['content']);
if (!$content) {
    echo json_encode(['error' => 'Module has no content to generate quiz from']);
    exit;
}

$result = generateQuizFromContent($content, $numQuestions, AI_API_KEY);
echo json_encode($result);