<?php
session_start();
require_once '../config/config.php';
require_once '../includes/auth-check.php';

if ($_SESSION['role'] !== 'student') {
    header('Location: ../index.php');
    exit;
}

$studentId = (int)$_SESSION['user_id'];

// ✅ Get ONLY courses the student has access to (via progress OR any module interaction)
// Simpler: courses with at least one quiz
$stmt = $pdo->prepare("
    SELECT DISTINCT 
        c.id, 
        c.title, 
        c.description,
        u.username AS faculty_name
    FROM courses c
    JOIN modules m ON c.id = m.course_id
    JOIN quizzes q ON m.id = q.module_id
    JOIN users u ON c.faculty_id = u.id
    WHERE EXISTS (
        SELECT 1 FROM progress p 
        WHERE p.student_id = ? AND p.quiz_id = q.id
    ) 
    OR EXISTS (
        SELECT 1 FROM modules m2 
        JOIN quizzes q2 ON m2.id = q2.module_id 
        WHERE m2.course_id = c.id
    )
    ORDER BY c.created_at DESC
");
$stmt->execute([$studentId]);
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ✅ Get completed quiz IDs (this part was already correct)
$stmt = $pdo->prepare("SELECT DISTINCT quiz_id FROM progress WHERE student_id = ?");
$stmt->execute([$studentId]);
$completedQuizIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
$completedCount = count($completedQuizIds);

// ✅ Get ALL available quizzes the student can take (from their courses)
$totalQuizzes = 0;
if (!empty($courses)) {
    $courseIds = array_column($courses, 'id');
    $placeholders = str_repeat('?,', count($courseIds) - 1) . '?';
    
    $stmt = $pdo->prepare("
        SELECT COUNT(q.id)
        FROM quizzes q
        JOIN modules m ON q.module_id = m.id
        WHERE m.course_id IN ($placeholders)
    ");
    $stmt->execute($courseIds);
    $totalQuizzes = (int)$stmt->fetchColumn();
}

// ✅ Get average score (correct)
$avgScore = 0;
if ($completedCount > 0) {
    $stmt = $pdo->prepare("SELECT AVG(score) FROM progress WHERE student_id = ?");
    $stmt->execute([$studentId]);
    $avgScoreRaw = $stmt->fetchColumn();
    $avgScore = $avgScoreRaw !== null ? round((float)$avgScoreRaw, 1) : 0;
}

// ✅ Get recent results
$stmt = $pdo->prepare("
    SELECT 
        q.title AS quiz_title,
        c.title AS course_title,
        p.score,
        p.completed_at
    FROM progress p
    JOIN quizzes q ON p.quiz_id = q.id
    JOIN modules m ON q.module_id = m.id
    JOIN courses c ON m.course_id = c.id
    WHERE p.student_id = ?
    ORDER BY p.completed_at DESC
    LIMIT 5
");
$stmt->execute([$studentId]);
$recentResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - ScholarisAI</title>
    <link rel="icon" href="../assets/images/AISiglat3.png">    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/faculty-styles.css">
    <style>
        .text-muted { color: #6c757d; font-size: 0.9em; }
        .score-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-weight: bold;
            color: white;
            font-size: 0.9em;
        }
        .score-badge.success { background: #28a745; color: white; }
        .score-badge.warning { background: #ffc107; color: #212529; }
        .score-badge.danger { background: #dc3545; }
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #6c757d;
        }
        .empty-state i {
            font-size: 3em;
            margin-bottom: 16px;
            color: #dee2e6;
        }
        .module-item {
            margin: 8px 0;
            padding: 8px;
            background: #f8f9fa;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include '../includes/sidebar-student.php'; ?>
        
        <div class="main-content">
            <?php include '../includes/header-student.php'; ?>
            
            <div class="dashboard-content">
                <div class="dashboard-header">
                    <p>Track your progress, complete modules, and take quizzes</p>
                </div>
                
                <!-- Progress Overview -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon bg-blue">
                            <i class="fas fa-book"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo count($courses); ?></h3>
                            <p>Enrolled Courses</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon bg-green">
                            <i class="fas fa-tasks"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $totalQuizzes; ?></h3>
                            <p>Quizzes Completed</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon bg-orange">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $avgScore; ?>%</h3>
                            <p>Average Score</p>
                        </div>
                    </div>
                </div>

                <!-- Courses & Modules -->
                <div class="dashboard-section">
                    <div class="section-header">
                        <h2>My Courses</h2>
                    </div>
                    
                    <?php if (empty($courses)): ?>
                        <div class="empty-state">
                            <i class="fas fa-book-open"></i>
                            <h3>No courses enrolled</h3>
                            <p>Ask your instructor to add you to a course.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($courses as $course): ?>
                            <div class="course-card">
                                <div style="display: flex; justify-content: space-between; align-items: start;">
                                    <div>
                                        <h3><?php echo htmlspecialchars($course['title']); ?></h3>
                                        <p class="text-muted">Instructor: <?php echo htmlspecialchars($course['faculty_name']); ?></p>
                                        <p><?php echo htmlspecialchars(substr($course['description'] ?? 'No description', 0, 120)) . (strlen($course['description'] ?? '') > 120 ? '...' : ''); ?></p>
                                    </div>
                                </div>

                                <!-- List Modules & Quizzes -->
                                <?php
                                $modStmt = $pdo->prepare("
                                    SELECT m.id, m.title
                                    FROM modules m
                                    WHERE m.course_id = ?
                                    ORDER BY m.created_at ASC
                                ");
                                $modStmt->execute([$course['id']]);
                                $modules = $modStmt->fetchAll(PDO::FETCH_ASSOC);
                                ?>

                                <?php if (!empty($modules)): ?>
                                    <div style="margin-top: 16px;">
                                        <strong>Modules:</strong>
                                        <?php foreach ($modules as $module): ?>
                                            <div class="module-item">
                                                <span><?php echo htmlspecialchars($module['title']); ?></span>
                                                <?php
                                                // Get quizzes in this module
                                                $quizStmt = $pdo->prepare("SELECT id, title FROM quizzes WHERE module_id = ?");
                                                $quizStmt->execute([$module['id']]);
                                                $quizzes = $quizStmt->fetchAll(PDO::FETCH_ASSOC);

                                                if (!empty($quizzes)):
                                                    foreach ($quizzes as $quiz):
                                                        $quizId = (int)$quiz['id'];
                                                        $isCompleted = in_array($quizId, $completedQuizIds);
                                                        ?>
                                                        </a>
                                                        <?php
                                                    endforeach;
                                                else:
                                                    echo '<span class="text-muted">No quizzes</span>';
                                                endif;
                                                ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted"><em>No modules yet.</em></p>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Recent Results -->
                <?php if (!empty($recentResults)): ?>
                <div class="dashboard-section">
                    <div class="section-header">
                        <h2>Recent Quiz Results</h2>
                    </div>
                    <div class="results-list">
                        <?php foreach ($recentResults as $result): ?>
                            <div class="result-item">
                                <div>
                                    <h4><?php echo htmlspecialchars($result['quiz_title']); ?></h4>
                                    <p class="text-muted"><?php echo htmlspecialchars($result['course_title']); ?></p>
                                </div>
                                <div style="text-align: right;">
                                    <span class="score-badge <?php echo $result['score'] >= 80 ? 'success' : ($result['score'] >= 60 ? 'warning' : 'danger'); ?>">
                                        <?php echo round($result['score']); ?>%
                                    </span>
                                    <p class="text-muted"><?php echo date('M j', strtotime($result['completed_at'])); ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>