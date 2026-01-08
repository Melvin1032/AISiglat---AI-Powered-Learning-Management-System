<?php
session_start();
require_once '../config/config.php';
require_once '../includes/auth-check.php';

if ($_SESSION['role'] !== 'faculty') {
    header('Location: ../index.php');
    exit;
}

$facultyId = $_SESSION['user_id'];

// Get faculty courses (for course list)
$stmt = $pdo->prepare("
    SELECT 
        c.id, 
        c.title, 
        c.description,
        COUNT(DISTINCT m.id) as total_modules,
        COUNT(DISTINCT p.student_id) as enrolled_students,
        AVG(p.score) as average_score
    FROM courses c
    LEFT JOIN modules m ON c.id = m.course_id
    LEFT JOIN progress p ON m.id = p.module_id
    WHERE c.faculty_id = ?
    GROUP BY c.id, c.title, c.description
");
$stmt->execute([$facultyId]);
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get total unique students across ALL your courses (NOT per-course)
$stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT p.student_id) as total_unique_students
    FROM progress p
    JOIN modules m ON p.module_id = m.id
    JOIN courses c ON m.course_id = c.id
    WHERE c.faculty_id = ?
");
$stmt->execute([$facultyId]);
$totalUniqueStudents = (int)$stmt->fetchColumn();

// Get total modules
$totalModules = 0;
foreach ($courses as $course) {
    $totalModules += (int)$course['total_modules'];
}

// Get global average score (not average of averages)
$stmt = $pdo->prepare("
    SELECT AVG(p.score) as global_avg_score
    FROM progress p
    JOIN modules m ON p.module_id = m.id
    JOIN courses c ON m.course_id = c.id
    WHERE c.faculty_id = ?
");
$stmt->execute([$facultyId]);
$globalAvgScore = (float)$stmt->fetchColumn();
$avgScore = is_numeric($globalAvgScore) ? $globalAvgScore : 0;

// Get at-risk students
$stmt = $pdo->prepare("
    SELECT 
        u.username as student_name,
        c.title as course_title,
        p.score,
        p.completed_at
    FROM progress p
    JOIN users u ON p.student_id = u.id
    JOIN modules m ON p.module_id = m.id
    JOIN courses c ON m.course_id = c.id
    WHERE c.faculty_id = ? AND p.score < 60
    ORDER BY p.score ASC
    LIMIT 5
");
$stmt->execute([$facultyId]);
$atRiskStudents = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent activity
$stmt = $pdo->prepare("
    SELECT 
        u.username,
        c.title as course_title,
        p.completed_at
    FROM progress p
    JOIN users u ON p.student_id = u.id
    JOIN modules m ON p.module_id = m.id
    JOIN courses c ON m.course_id = c.id
    WHERE c.faculty_id = ?
    ORDER BY p.completed_at DESC
    LIMIT 5
");
$stmt->execute([$facultyId]);
$recentActivity = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalCourses = count($courses);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faculty Dashboard - ScholarisAI</title>
    <link rel="icon" href="../assets/images/AISiglat3.png">    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/faculty-styles.css">
</head>
<body>
    <div class="app-container">
        <?php include '../includes/sidebar.php'; ?>
        
        <div class="main-content">
            <?php include '../includes/header.php'; ?>
            
            <div class="dashboard-content">
                <div class="dashboard-header">
                    <p>Manage your courses and track student progress</p>
                </div>
                
                <!-- Key Statistics -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon bg-blue">
                            <i class="fas fa-book"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $totalCourses; ?></h3>
                            <p>Courses</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon bg-green">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $totalUniqueStudents; ?></h3>
                            <p>Students</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon bg-purple">
                            <i class="fas fa-layer-group"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $totalModules; ?></h3>
                            <p>Modules</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon bg-orange">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $avgScore > 0 ? round($avgScore, 1) : 0; ?>%</h3>
                            <p>Avg. Score</p>
                        </div>
                    </div>
                </div>
                
                <!-- At-Risk Students Alert -->
                <?php if (count($atRiskStudents) > 0): ?>
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
                
                <!-- Dashboard Grid -->
                <div class="dashboard-grid">
                    <div class="left-column">
                        <!-- Course Management Section -->
                        <div class="dashboard-section">
                            <div class="section-header">
                                <h2>My Courses</h2>
                                <a href="course-management.php" class="btn btn-primary">
                                    <i class="fas fa-plus"></i> Create Course
                                </a>
                            </div>
                            
                            <div class="courses-grid">
                                <?php foreach ($courses as $course): ?>
                                    <div class="course-card">
                                        <div class="course-header">
                                            <h3><?php echo htmlspecialchars($course['title']); ?></h3>
                                            <span class="course-status">Active</span>
                                        </div>
                                        <p class="course-desc"><?php echo htmlspecialchars(substr($course['description'], 0, 100)); ?>...</p>
                                        
                                        <div class="course-stats">
                                            <div class="stat">
                                                <i class="fas fa-layer-group"></i>
                                                <span><?php echo $course['total_modules']; ?> modules</span>
                                            </div>
                                            <div class="stat">
                                                <i class="fas fa-user-graduate"></i>
                                                <span><?php echo $course['enrolled_students']; ?> students</span>
                                            </div>
                                            <div class="stat">
                                                <i class="fas fa-chart-line"></i>
                                                <span><?php echo $course['average_score'] ? round($course['average_score'], 1) : 0; ?>% avg</span>
                                            </div>
                                        </div>
                                        
                                        <div class="course-actions">
                                            <a href="module-creator.php?course_id=<?php echo $course['id']; ?>" 
                                               class="btn btn-outline">Manage</a>
                                            <a href="quiz-creator.php?course_id=<?php echo $course['id']; ?>" 
                                               class="btn btn-info">Quizzes</a>
                                            <a href="analytics.php?course_id=<?php echo $course['id']; ?>" 
                                               class="btn btn-secondary">Analytics</a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <!-- Recent Activity -->
                        <div class="dashboard-section">
                            <div class="section-header">
                                <h2>Recent Activity</h2>
                            </div>
                            
                            <div class="activity-list">
                                <?php foreach ($recentActivity as $activity): ?>
                                    <div class="activity-item">
                                        <div class="activity-icon">
                                            <i class="fas fa-user-graduate"></i>
                                        </div>
                                        <div class="activity-content">
                                            <h4><?php echo htmlspecialchars($activity['username']); ?></h4>
                                            <p>Completed quiz in <?php echo htmlspecialchars($activity['course_title']); ?></p>
                                            <span class="activity-time"><?php echo date('M j, Y g:i A', strtotime($activity['completed_at'])); ?></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="right-column">
                        <!-- Quick Actions -->
                        <div class="dashboard-section">
                            <div class="section-header">
                                <h2>Quick Actions</h2>
                            </div>
                            
                            <div class="quick-actions">
                                <a href="course-management.php" class="quick-action">
                                    <div class="action-icon bg-blue">
                                        <i class="fas fa-book"></i>
                                    </div>
                                    <div class="action-content">
                                        <h4>Create Course</h4>
                                        <p>Design a new course</p>
                                    </div>
                                </a>
                                
                                <a href="module-creator.php" class="quick-action">
                                    <div class="action-icon bg-green">
                                        <i class="fas fa-layer-group"></i>
                                    </div>
                                    <div class="action-content">
                                        <h4>Add Module</h4>
                                        <p>Add new content</p>
                                    </div>
                                </a>
                                
                                <a href="quiz-creator.php" class="quick-action">
                                    <div class="action-icon bg-purple">
                                        <i class="fas fa-file-alt"></i>
                                    </div>
                                    <div class="action-content">
                                        <h4>Create Quiz</h4>
                                        <p>Generate assessments</p>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Removed unused AI script since it wasn't wired to HTML
        // (You can re-add if connected to a form later)
    </script>
</body>
</html>