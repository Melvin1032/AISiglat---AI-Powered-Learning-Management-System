<?php
session_start();
require_once '../config/config.php';
require_once '../includes/auth-check.php';

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role'];

// Get student progress data
if ($userRole === 'faculty') {
    $stmt = $pdo->prepare("
        SELECT 
            u.username,
            AVG(p.score) as avg_score,
            COUNT(p.id) as total_quizzes
        FROM users u
        JOIN progress p ON u.id = p.student_id
        JOIN modules m ON p.module_id = m.id
        JOIN courses c ON m.course_id = c.id
        WHERE c.faculty_id = ?
        GROUP BY u.id
        ORDER BY avg_score DESC
    ");
    $stmt->execute([$userId]);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get at-risk students
    $stmt = $pdo->prepare("
        SELECT 
            u.username as student_name,
            c.title as course_title,
            p.score
        FROM progress p
        JOIN users u ON p.student_id = u.id
        JOIN modules m ON p.module_id = m.id
        JOIN courses c ON m.course_id = c.id
        WHERE c.faculty_id = ? AND p.score < 60
        ORDER BY p.score ASC
    ");
    $stmt->execute([$userId]);
    $atRiskStudents = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $stmt = $pdo->prepare("
        SELECT 
            c.title as course_title,
            m.title as module_title,
            p.score,
            p.completed_at
        FROM progress p
        JOIN modules m ON p.module_id = m.id
        JOIN courses c ON m.course_id = c.id
        WHERE p.student_id = ?
        ORDER BY p.completed_at DESC
    ");
    $stmt->execute([$userId]);
    $progress = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Progress - SmartLMS</title>
    <link rel="icon" href="../assets/images/AISiglat3.png">    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/faculty-styles.css">
</head>
<body>
    <div class="app-container">
        <?php include '../includes/sidebar.php'; ?>
        
        <div class="main-content">
            <?php include '../includes/header.php'; ?>
            
            <div id="progress-page">
                <div class="dashboard-header">
                    <h1><?php echo $userRole === 'faculty' ? 'Student Progress Overview' : 'Your Progress Overview'; ?></h1>
                    <p>Track learning progress and performance</p>
                </div>
                
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon bg-primary">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo count($students ?? []); ?></h3>
                            <p>Students</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon bg-success">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo count($atRiskStudents ?? []); ?></h3>
                            <p>At-Risk Students</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon bg-warning">
                            <i class="fas fa-tasks"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo array_sum(array_column($students ?? [], 'total_quizzes')); ?></h3>
                            <p>Total Quizzes</p>
                        </div>
                    </div>
                </div>
                
                <?php if ($userRole === 'faculty' && count($atRiskStudents) > 0): ?>
                <div class="alert alert-warning">
                    <h3><i class="fas fa-exclamation-triangle"></i> At-Risk Students</h3>
                    <div class="risk-students-grid">
                        <?php foreach ($atRiskStudents as $student): ?>
                            <div class="risk-student-card">
                                <div class="risk-info">
                                    <h4><?php echo htmlspecialchars($student['student_name']); ?></h4>
                                    <p>Course: <?php echo htmlspecialchars($student['course_title']); ?></p>
                                    <p>Score: <?php echo $student['score']; ?>%</p>
                                </div>
                                <span class="risk-badge">At Risk</span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="dashboard-section">
                    <div class="section-header">
                        <h2><?php echo $userRole === 'faculty' ? 'Student Progress' : 'Your Progress'; ?></h2>
                    </div>
                    
                    <div class="progress-list">
                        <?php if ($userRole === 'faculty'): ?>
                            <?php foreach ($students as $student): ?>
                                <div class="progress-item">
                                    <div class="progress-info">
                                        <h4><?php echo htmlspecialchars($student['username']); ?></h4>
                                        <div class="progress-container">
                                            <div class="progress-bar" style="width: <?php echo $student['avg_score']; ?>%"></div>
                                        </div>
                                    </div>
                                    <div class="progress-score">
                                        <?php echo round($student['avg_score'], 1); ?>%
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <?php foreach ($progress as $record): ?>
                                <div class="progress-item">
                                    <div class="progress-info">
                                        <h4><?php echo htmlspecialchars($record['course_title']); ?> - <?php echo htmlspecialchars($record['module_title']); ?></h4>
                                        <div class="progress-container">
                                            <div class="progress-bar" style="width: <?php echo $record['score']; ?>%"></div>
                                        </div>
                                    </div>
                                    <div class="progress-score">
                                        <?php echo $record['score']; ?>%
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="js/app.js"></script>
</body>
</html>