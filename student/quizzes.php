<?php
session_start();
require_once '../config/config.php';
require_once '../includes/auth-check.php';

if ($_SESSION['role'] !== 'student') {
    header('Location: ../index.php?error=access_denied');
    exit;
}

$userId = (int)$_SESSION['user_id'];

// Validate session
$userCheck = $pdo->prepare("SELECT id FROM users WHERE id = ?");
$userCheck->execute([$userId]);
if (!$userCheck->fetch()) {
    session_unset();
    session_destroy();
    header('Location: ../index.php?error=invalid_user');
    exit;
}

// Get available quizzes (not yet taken)
$stmt = $pdo->prepare("
    SELECT q.id as quiz_id, q.title as quiz_title,
           c.title as course_title, m.title as module_title
    FROM quizzes q
    JOIN modules m ON q.module_id = m.id
    JOIN courses c ON m.course_id = c.id
    LEFT JOIN progress p ON q.id = p.quiz_id AND p.student_id = ?
    WHERE p.id IS NULL
");
$stmt->execute([$userId]);
$availableQuizzes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get completed quizzes
$stmt = $pdo->prepare("
    SELECT c.title as course_title, m.title as module_title,
           q.title as quiz_title, p.score, p.completed_at
    FROM progress p
    JOIN quizzes q ON p.quiz_id = q.id
    JOIN modules m ON q.module_id = m.id
    JOIN courses c ON m.course_id = c.id
    WHERE p.student_id = ?
    ORDER BY p.completed_at DESC
    LIMIT 5
");
$stmt->execute([$userId]);
$completedQuizzes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz Center</title>
    <link rel="icon" href="../assets/images/AISiglat3.png">    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/faculty-styles.css">
    <style>
        .dashboard-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
        }
        @media (max-width: 768px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
        }
        .btn-outline {
            background: transparent;
            border: 1px solid #4CAF50;
            color: #4CAF50;
            text-decoration: none;
            padding: 6px 12px;
            border-radius: 4px;
            display: inline-block;
            margin-top: 8px;
        }
        .btn-outline:hover {
            background: #e8f5e9;
        }
        .score-badge.success { color: #ffffffff; }
        .score-badge.warning { color: #ffffffff; }
        .score-badge.danger { color: #ffffffff; }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include '../includes/sidebar-student.php'; ?>
        <div class="main-content">
            <?php include '../includes/header-student.php'; ?>

            <div id="quizzes-page">
                <div class="dashboard-header">
                    <h1>Quiz Center</h1>
                    <p>Take and review your quizzes</p>
                </div>

                <div class="dashboard-grid">
                    <div class="left-column">
                        <!-- Available Quizzes -->
                        <div class="dashboard-section">
                            <div class="section-header">
                                <h2>Available Quizzes</h2>
                            </div>
                            <div class="quiz-list">
                                <?php if (empty($availableQuizzes)): ?>
                                    <div class="empty-state">
                                        <i class="fas fa-clipboard-list"></i>
                                        <h3>No quizzes available</h3>
                                        <p>You've completed all quizzes.</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($availableQuizzes as $quiz): ?>
                                        <div class="quiz-item">
                                            <div class="quiz-info">
                                                <h4><?php echo htmlspecialchars($quiz['quiz_title']); ?></h4>
                                                <p><?php echo htmlspecialchars($quiz['course_title']); ?> – <?php echo htmlspecialchars($quiz['module_title']); ?></p>
                                            </div>
                                            <a href="take-quiz.php?quiz_id=<?php echo (int)$quiz['quiz_id']; ?>" class="btn btn-primary">Take Quiz</a>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Recent Results -->
                        <div class="dashboard-section">
                            <div class="section-header">
                                <h2>Recent Results</h2>
                            </div>
                            <div class="results-container">
                                <?php if (empty($completedQuizzes)): ?>
                                    <div class="empty-state">
                                        <i class="fas fa-trophy"></i>
                                        <h3>No results yet</h3>
                                        <p>Complete a quiz to see your score</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($completedQuizzes as $quiz): ?>
                                        <div class="result-item">
                                            <div class="result-info">
                                                <h4></b>Quiz Name: <b><?php echo htmlspecialchars($quiz['quiz_title']); ?></h4>
                                                <p><?php echo htmlspecialchars($quiz['course_title']); ?></p>
                                                <span class="result-date"><?php echo date('M j, Y', strtotime($quiz['completed_at'])); ?></span>
                                            </div>
                                            <div class="result-score">
                                                <span class="score-badge <?php echo $quiz['score'] >= 80 ? 'success' : ($quiz['score'] >= 60 ? 'warning' : 'danger'); ?>">
                                                    <?php echo round($quiz['score']); ?>%
                                                </span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Performance Analytics -->
                    <div class="right-column">
                        <div class="dashboard-section">
                            <div class="section-header">
                                <h2>Your Performance</h2>
                            </div>
                            <div class="analytics-container">
                                <div class="stat-card">
                                    <div class="stat-icon bg-primary">
                                        <i class="fas fa-clipboard-check"></i>
                                    </div>
                                    <div class="stat-info">
                                        <h3><?php echo count($completedQuizzes); ?></h3>
                                        <p>Quizzes Completed</p>
                                    </div>
                                </div>
                                <?php if (!empty($completedQuizzes)): ?>
                                <div class="stat-card">
                                    <div class="stat-icon bg-warning">
                                        <i class="fas fa-chart-line"></i>
                                    </div>
                                    <div class="stat-info">
                                        <h3>
                                            <?php
                                            $scores = array_column($completedQuizzes, 'score');
                                            echo round(array_sum($scores) / count($scores), 1);
                                            ?>%
                                        </h3>
                                        <p>Average Score</p>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>