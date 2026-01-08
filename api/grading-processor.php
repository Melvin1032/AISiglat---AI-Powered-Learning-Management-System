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
$quizId = $data['quizId'] ?? 0;
$answers = $data['answers'] ?? [];
$studentId = $_SESSION['user_id'];

// Get quiz questions from database
$stmt = $pdo->prepare("SELECT questions FROM quizzes WHERE id = ?");
$stmt->execute([$quizId]);
$quiz = $stmt->fetch();

if (!$quiz) {
    http_response_code(404);
    echo json_encode(['error' => 'Quiz not found']);
    exit;
}

$questions = json_decode($quiz['questions'], true);
$correct = 0;
$total = count($questions);

for ($i = 0; $i < $total; $i++) {
    if (isset($questions[$i]['answer']) && isset($answers[$i])) {
        if ($questions[$i]['answer'] === $answers[$i]) {
            $correct++;
        }
    }
}

$score = ($correct / $total) * 100;

// Save results to database
$stmt = $pdo->prepare("INSERT INTO progress (student_id, quiz_id, score, completed_at) VALUES (?, ?, ?, NOW())");
$stmt->execute([$studentId, $quizId, $score]);

echo json_encode([
    'score' => $score,
    'correct' => $correct,
    'total' => $total,
    'percentage' => round($score, 2)
]);
?>