<?php
session_start();
require_once '../config/config.php';
require_once '../includes/auth-check.php';

if ($_SESSION['role'] !== 'student') {
    header('Location: ../index.php');
    exit;
}

$userId = (int)$_SESSION['user_id'];
$quizId = isset($_GET['quiz_id']) ? (int)$_GET['quiz_id'] : 0;

if (!$quizId) {
    header('Location: quizzes.php?error=invalid_quiz');
    exit;
}

// Fetch quiz + course/module info
$stmt = $pdo->prepare("
    SELECT 
        q.id, q.title, q.questions,
        c.title AS course_title,
        m.title AS module_title,
        m.id AS module_id
    FROM quizzes q
    JOIN modules m ON q.module_id = m.id
    JOIN courses c ON m.course_id = c.id
    WHERE q.id = ?
");
$stmt->execute([$quizId]);
$quizData = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$quizData) {
    header('Location: quizzes.php?error=quiz_not_found');
    exit;
}

// Optional: Verify student is enrolled (has progress in this course)
// (Skip if your system allows open quiz access)

$questions = json_decode($quizData['questions'], true) ?: [];

// Handle submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userAnswers = $_POST['answers'] ?? [];
    $correct = 0;
    $total = count($questions);

    foreach ($questions as $index => $q) {
        $expected = $q['answer'] ?? 'A';
        $userAns = $userAnswers[$index] ?? '';
        if ($userAns === $expected) {
            $correct++;
        }
    }

    $score = ($total > 0) ? round(($correct / $total) * 100, 1) : 0;

    // ✅ Include module_id (required by foreign key)
    $progressStmt = $pdo->prepare("
        INSERT INTO progress (student_id, module_id, quiz_id, score, completed_at)
        VALUES (?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE 
            score = VALUES(score),
            completed_at = NOW()
    ");
    $progressStmt->execute([
        $userId,
        $quizData['module_id'], // ✅ Critical: provide valid module_id
        $quizId,
        $score
    ]);

    header("Location: quizzes.php?success=quiz_submitted&score=" . $score);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz: <?= htmlspecialchars($quizData['title']) ?> - SmartLMS</title>
    <link rel="icon" href="../assets/images/AISiglat3.png">    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/faculty-styles.css">
    <style>
        .quiz-container {
            max-width: 800px;
            margin: 20px auto;
            padding: 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .quiz-question {
            margin-bottom: 25px;
            padding: 15px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
        }
        .quiz-option {
            display: block;
            padding: 8px;
            margin: 6px 0;
            background: #f8f9fa;
            border-radius: 5px;
            cursor: pointer;
        }
        .quiz-option input {
            margin-right: 10px;
        }
        .btn-submit {
            background: #28a745;
            color: white;
            padding: 12px 24px;
            font-size: 16px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        .btn-submit:hover {
            background: #218838;
        }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include '../includes/sidebar-student.php'; ?>
        <div class="main-content">
            <?php include '../includes/header-student.php'; ?>

            <div class="dashboard-header">
                <a href="quizzes.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Quizzes
                </a>
                <h1><?= htmlspecialchars($quizData['title']) ?></h1>
                <p>
                    Course: <?= htmlspecialchars($quizData['course_title']) ?> | 
                    Module: <?= htmlspecialchars($quizData['module_title']) ?>
                </p>
            </div>

            <div class="quiz-container">
                <form method="POST">
                    <?php foreach ($questions as $index => $q): ?>
                        <div class="quiz-question">
                            <h4><?= $index + 1 ?>. <?= htmlspecialchars($q['question']) ?></h4>
                            <?php
                            $letters = ['A', 'B', 'C', 'D'];
                            foreach ($letters as $i => $letter) {
                                $optionText = $q['options'][$i] ?? "Option $letter";
                                echo '<label class="quiz-option">';
                                echo '<input type="radio" name="answers[' . $index . ']" value="' . $letter . '" required>';
                                echo "<strong>$letter.</strong> " . htmlspecialchars($optionText);
                                echo '</label>';
                            }
                            ?>
                        </div>
                    <?php endforeach; ?>

                    <button type="submit" class="btn-submit">
                        <i class="fas fa-paper-plane"></i> Submit Quiz
                    </button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>