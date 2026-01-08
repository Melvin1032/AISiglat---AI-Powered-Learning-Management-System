<?php
session_start();
require_once '../config/config.php';
require_once '../includes/auth-check.php';

if ($_SESSION['role'] !== 'faculty') {
    header('Location: index.php');
    exit;
}

$userId = (int)$_SESSION['user_id'];
$quizId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$quizId) {
    header('Location: quizzes.php?error=invalid_quiz');
    exit;
}

// Fetch quiz with ownership verification
$stmt = $pdo->prepare("
    SELECT 
        q.title AS quiz_title,
        q.questions,
        q.created_at,
        c.title AS course_title,
        m.title AS module_title
    FROM quizzes q
    JOIN modules m ON q.module_id = m.id
    JOIN courses c ON m.course_id = c.id
    WHERE q.id = ? AND c.faculty_id = ?
");
$stmt->execute([$quizId, $userId]);
$quiz = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$quiz) {
    header('Location: quizzes.php?error=unauthorized');
    exit;
}

$questions = json_decode($quiz['questions'], true);
if (json_last_error() !== JSON_ERROR_NONE || !is_array($questions)) {
    $questions = [];
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Quiz - SmartLMS</title>
    <link rel="icon" href="../assets/images/AISiglat3.png">    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/faculty-styles.css">
    <style>
        .quiz-view-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        .quiz-header {
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 1px solid #eee;
        }
        .quiz-header h1 {
            font-size: 1.8em;
            color: #333;
            margin-bottom: 8px;
        }
        .quiz-meta {
            color: #666;
            font-size: 0.95em;
        }
        .question-card {
            background: #f9f9f9;
            padding: 18px;
            margin-bottom: 20px;
            border-radius: 8px;
            border-left: 4px solid #4f46e5;;
        }
        .question-text {
            font-weight: 600;
            margin-bottom: 10px;
            font-size: 1.1em;
        }
        .options-list {
            margin: 12px 0;
            padding-left: 20px;
        }
        .options-list li {
            margin-bottom: 6px;
            color: #555;
        }
        .correct-answer {
            background: #e8f5e9;
            padding: 6px 12px;
            border-radius: 6px;
            display: inline-block;
            font-weight: bold;
            color: #4f46e5;;
            margin-top: 8px;
        }
        .back-link {
            display: inline-block;
            margin-top: 20px;
            color: #4f46e5;;
            text-decoration: none;
            font-weight: 500;
        }
        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include '../includes/sidebar.php'; ?>
        
        <div class="main-content">
            <?php include '../includes/header.php'; ?>
            
            <div class="quiz-view-container">
                <a href="quizzes.php" class="back-link">
                    <i class="fas fa-arrow-left"></i> Back to Quizzes
                </a>

                <div class="quiz-header">
                    <h1><?php echo htmlspecialchars($quiz['quiz_title']); ?></h1>
                    <div class="quiz-meta">
                        Course: <?php echo htmlspecialchars($quiz['course_title']); ?> • 
                        Module: <?php echo htmlspecialchars($quiz['module_title']); ?> • 
                        Created: <?php echo date('M j, Y \a\t g:i A', strtotime($quiz['created_at'])); ?>
                    </div>
                </div>

                <?php if (empty($questions)): ?>
                    <div class="empty-state">
                        <i class="fas fa-exclamation-triangle"></i>
                        <h3>No questions found</h3>
                        <p>This quiz has no content.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($questions as $index => $q): ?>
                        <div class="question-card">
                            <div class="question-text">
                                <?php echo ($index + 1) . '. ' . htmlspecialchars($q['question'] ?? 'Untitled Question'); ?>
                            </div>
                            <?php if (!empty($q['options']) && is_array($q['options'])): ?>
                                <ul class="options-list">
                                    <?php foreach ($q['options'] as $opt): ?>
                                        <li><?php echo htmlspecialchars($opt); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                            <div class="correct-answer">
                                Correct Answer: <?php echo htmlspecialchars($q['answer'] ?? 'Not specified'); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>