<?php
session_start();
require_once '../config/config.php';
require_once '../includes/auth-check.php';

$userId = (int) $_SESSION['user_id'];
$userRole = $_SESSION['role'];

// Only students should access this page
if ($userRole !== 'student') {
    header('Location: ../faculty/faculty-dashboard.php');
    exit;
}

// Get course overview if course_id is specified
$selected_course = null;
$modules = [];
$course_id = isset($_GET['course_id']) ? (int)$_GET['course_id'] : null;

if ($course_id) {
    $stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ?");
    $stmt->execute([$course_id]);
    $selected_course = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($selected_course) {
        // FIXED: Use LEFT JOIN + GROUP BY instead of scalar subquery
        $stmt = $pdo->prepare("
            SELECT 
                m.*,
                MAX(p.completed) AS is_completed
            FROM modules m
            LEFT JOIN progress p ON p.module_id = m.id AND p.student_id = ?
            WHERE m.course_id = ?
            GROUP BY m.id
            ORDER BY m.order_num ASC, m.id ASC
        ");
        $stmt->execute([$userId, $course_id]);
        $modules = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        header('Location: view-courses.php?error=invalid_course');
        exit;
    }
} else {
    // Student: Get courses where student has progress (enrolled)
    $stmt = $pdo->prepare("
        SELECT DISTINCT c.id, c.title, c.description, c.created_at, 
               u.username as faculty_name,
               (SELECT COUNT(*) FROM modules m WHERE m.course_id = c.id) as total_modules,
               (SELECT COUNT(DISTINCT p.module_id) 
                FROM modules m2 
                JOIN progress p ON m2.id = p.module_id 
                WHERE m2.course_id = c.id AND p.student_id = ?) as completed_modules
        FROM courses c
        JOIN modules m ON c.id = m.course_id
        JOIN progress p ON m.id = p.module_id
        JOIN users u ON c.faculty_id = u.id
        WHERE p.student_id = ?
        GROUP BY c.id
        ORDER BY c.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$userId, $userId]);
    $recentCourses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get all enrolled courses
    $stmt = $pdo->prepare("
        SELECT DISTINCT c.id, c.title, c.description, c.created_at, 
               u.username as faculty_name,
               (SELECT COUNT(*) FROM modules m WHERE m.course_id = c.id) as total_modules,
               (SELECT COUNT(DISTINCT p.module_id) 
                FROM modules m2 
                JOIN progress p ON m2.id = p.module_id 
                WHERE m2.course_id = c.id AND p.student_id = ?) as completed_modules
        FROM courses c
        JOIN modules m ON c.id = m.course_id
        JOIN progress p ON m.id = p.module_id
        JOIN users u ON c.faculty_id = u.id
        WHERE p.student_id = ?
        GROUP BY c.id
        ORDER BY c.title ASC
    ");
    $stmt->execute([$userId, $userId]);
    $allCourses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get available courses (not started by student)
    $stmt = $pdo->prepare("
        SELECT c.id, c.title, c.description, u.username as faculty_name
        FROM courses c
        JOIN users u ON c.faculty_id = u.id
        WHERE c.id NOT IN (
            SELECT DISTINCT m.course_id 
            FROM modules m 
            JOIN progress p ON m.id = p.module_id 
            WHERE p.student_id = ?
        )
    ");
    $stmt->execute([$userId]);
    $availableCourses = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $selected_course ? htmlspecialchars($selected_course['title']) . ' - ' : ''; ?>Student Dashboard</title>
    <link rel="icon" href="../assets/images/AISiglat3.png">    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/faculty-styles.css">
    <style>
        .course-details {
            padding: 20px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
            margin-top: 20px;
        }
        .module-card {
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 16px;
            background: #fafafa;
        }
        .module-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }
        .module-status.completed {
            color: #28a745;
            font-weight: bold;
        }
        .module-status.incomplete {
            color: #dc3545;
        }
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
    </style>
</head>
<body>
    <div class="app-container">
        <?php include '../includes/sidebar-student.php'; ?>
        
        <div class="main-content">
            <?php include '../includes/header-student.php'; ?>
            
            <div id="student-dashboard">
                <?php if ($selected_course): ?>
                    <!-- Course Detail View -->
                    <div class="dashboard-header">
                        <a href="view-courses.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Dashboard
                        </a>
                        <h1><?php echo htmlspecialchars($selected_course['title']); ?></h1>
                    </div>

                    <div class="course-details">
                        <div class="course-description">
                            <h3>Description</h3>
                            <p><?php echo nl2br(htmlspecialchars($selected_course['description'] ?? '')); ?></p>
                        </div>

                        <div class="course-modules">
                            <h3>Course Modules</h3>
                            <?php if (empty($modules)): ?>
                                <div class="empty-state">
                                    <i class="fas fa-book"></i>
                                    <h3>No modules available</h3>
                                    <p>This course doesn't have any modules yet.</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($modules as $module): ?>
                                    <div class="module-card">
                                        <div class="module-header">
                                            <h4>Module <?php echo (int)$module['order_num']; ?>: <?php echo htmlspecialchars($module['title']); ?></h4>
                                            <?php
                                            // Treat NULL or 0 as incomplete; any truthy value (1, '1', etc.) as completed
                                            $isCompleted = !empty($module['is_completed']);
                                            ?>
                                            <span class="module-status <?php echo $isCompleted ? 'completed' : 'incomplete'; ?>">
                                              
                                            </span>
                                        </div>

                                        <?php if (!empty($module['content'])): ?>
                                            <div class="module-content">
                                                <?php echo nl2br(htmlspecialchars($module['content'])); ?>
                                            </div>
                                        <?php endif; ?>

                                        <div class="module-actions" style="margin-top: 12px;">
                                            <a href="take-quiz.php?module_id=<?php echo (int)$module['id']; ?>" class="btn btn-primary">
                                                <i class="fas fa-question-circle"></i> Take Quiz
                                            </a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                <?php else: ?>
                    <!-- Student Dashboard View -->
                    <div class="dashboard-header">
                        <h1>Student Dashboard</h1>
                        <p>Manage your courses and track progress</p>
                    </div>
                    
                    <?php if (isset($_GET['success']) && $_GET['success'] == 'enrolled'): ?>
                    <div class="alert alert-success">
                        Successfully enrolled in the course!
                    </div>
                    <?php endif; ?>
                    
                    <?php if (isset($_GET['error'])): ?>
                    <div class="alert alert-error">
                        <?php 
                        $error = $_GET['error'];
                        switch($error) {
                            case 'invalid_course': echo 'Invalid course selected.'; break;
                            case 'no_modules': echo 'This course has no modules available.'; break;
                            case 'enrollment_failed': echo 'Enrollment failed. Please try again.'; break;
                            default: echo 'An error occurred.';
                        }
                        ?>
                    </div>
                    <?php endif; ?>
                    
                    <div class="dashboard-grid">
                        <div class="left-column">
                            <!-- Enrolled Courses -->
                            <div class="dashboard-section">
                                <div class="section-header">
                                    <h2>My Courses</h2>
                                </div>
                                <div class="course-list">
                                    <?php if (empty($allCourses)): ?>
                                        <div class="empty-state">
                                            <i class="fas fa-graduation-cap"></i>
                                            <h3>No enrolled courses</h3>
                                            <p>You haven't started any courses yet.</p>
                                        </div>
                                    <?php else: ?>
                                        <?php foreach ($allCourses as $course): ?>
                                            <div class="course-item">
                                                <div class="course-info">
                                                    <h4><?php echo htmlspecialchars($course['title']); ?></h4>
                                                    <p>by <?php echo htmlspecialchars($course['faculty_name']); ?></p>
                                                    <div class="course-stats">
                                                        <span><i class="fas fa-book"></i> <?php echo (int)($course['total_modules'] ?? 0); ?> modules</span>
                                                    </div>
                                                </div>
                                                <div class="course-actions">
                                                    <a href="view-courses.php?course_id=<?php echo (int)$course['id']; ?>" class="btn btn-primary">View Course</a>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Recently Accessed -->
                            <div class="dashboard-section">
                                <div class="section-header">
                                    <h2>Recently Accessed</h2>
                                </div>
                                <div class="results-container">
                                    <?php if (empty($recentCourses)): ?>
                                        <div class="empty-state">
                                            <i class="fas fa-book"></i>
                                            <h3>No recent activity</h3>
                                            <p>Start learning in your courses to see them here</p>
                                        </div>
                                    <?php else: ?>
                                        <?php foreach ($recentCourses as $course): ?>
                                            <div class="result-item">
                                                <div class="result-info">
                                                    <h4><?php echo htmlspecialchars($course['title']); ?></h4>
                                                    <p><?php echo htmlspecialchars($course['faculty_name']); ?></p>
                                                    <span class="result-date"><?php echo date('M j, Y', strtotime($course['created_at'])); ?></span>
                                                </div>
                                                <a href="view-courses.php?course_id=<?php echo (int)$course['id']; ?>" class="btn-outline">Continue</a>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Available Courses -->
<!-- Available Courses -->
<div class="right-column">
    <div class="dashboard-section">
        <div class="section-header">
            <h2>Available Courses</h2>
        </div>
        
        <?php if (empty($availableCourses)): ?>
            <div class="empty-state" style="padding: 30px; text-align: center; color: #6c757d;">
                <i class="fas fa-book" style="font-size: 2.5em; margin-bottom: 12px; color: #dee2e6;"></i>
                <h4 style="margin: 10px 0;">No New Courses</h4>
                <p style="font-size: 0.95em;">You’re enrolled in all available courses.</p>
            </div>
        <?php else: ?>
            <div class="available-courses-grid">
                <?php foreach ($availableCourses as $course): ?>
                    <div class="available-course-card">
                        <div class="course-card-header">
                            <h4><?php echo htmlspecialchars($course['title']); ?></h4>
                        </div>
                        <?php if (!empty($course['description'])): ?>
                            <p class="course-card-desc">
                                <?php echo htmlspecialchars(substr($course['description'], 0, 100)) . (strlen($course['description']) > 100 ? '…' : ''); ?>
                            </p>
                        <?php endif; ?>
                        <div class="course-card-footer">
                            <small class="text-muted">Instructor: <?php echo htmlspecialchars($course['faculty_name'] ?? '—'); ?></small>
                            <br>
                            <a 
                                href="enroll-course.php?course_id=<?php echo (int)$course['id']; ?>" 
                                class="btn btn-sm btn-outline" 
                                onclick="return confirmEnrollment('<?php echo addslashes(htmlspecialchars($course['title'], ENT_QUOTES)); ?>');"
                            >
                                Enroll
                            </a>
                            <br><br>
                        </div>
                    </div>
                <?php endforeach; ?>
                
            </div>
        <?php endif; ?>
    </div>
</div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function confirmEnrollment(courseTitle) {
            return confirm('Are you sure you want to enroll in "' + courseTitle + '"?');
        }

        // Clean URL after showing alerts
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('success') || urlParams.get('error')) {
                setTimeout(() => {
                    window.history.replaceState({}, document.title, 'view-courses.php');
                }, 3000);
            }
        });
    </script>
</body>
</html>