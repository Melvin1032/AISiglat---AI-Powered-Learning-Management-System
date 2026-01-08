<?php
session_start();
require_once '../config/config.php';
require_once '../includes/auth-check.php';

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $userRole === 'faculty') {
    $title = trim($_POST['course-title']);
    $description = trim($_POST['course-description']);
    
    $stmt = $pdo->prepare("INSERT INTO courses (title, description, faculty_id) VALUES (?, ?, ?)");
    $stmt->execute([$title, $description, $userId]);
    
    $success = "Course created successfully!";
}

// Get user courses
if ($userRole === 'faculty') {
    $stmt = $pdo->prepare("SELECT * FROM courses WHERE faculty_id = ?");
    $stmt->execute([$userId]);
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $stmt = $pdo->prepare("
        SELECT c.* FROM courses c
        JOIN modules m ON c.id = m.course_id
        JOIN progress p ON m.id = p.module_id
        WHERE p.student_id = ?
        GROUP BY c.id
    ");
    $stmt->execute([$userId]);
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Courses - SmartLMS</title>
    <link rel="icon" href="../assets/images/AISiglat3.png">    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/faculty-styles.css">
</head>
<body>
    <div class="app-container">
        <?php include '../includes/sidebar.php'; ?>
        
        <div class="main-content">
            <?php include '../includes/header.php'; ?>
            
            <div id="courses-page">
                <div class="dashboard-header">
                    <h1>Course Management</h1>
                    <p>Intelligent course and module management system</p>
                </div>
                
                <div class="dashboard-grid">
                    <div class="left-column">
                        <?php if ($userRole === 'faculty'): ?>
                            <div class="dashboard-section">
                                <div class="section-header">
                                    <h2>Create New Course</h2>
                                </div>
                                
                                <?php if (isset($success)): ?>
                                    <div class="alert alert-success"><?php echo $success; ?></div>
                                <?php endif; ?>
                                
                                <form method="POST" class="course-form">
                                    <div class="form-group">
                                        <label for="course-title">Course Title</label>
                                        <input type="text" id="course-title" name="course-title" class="form-control" placeholder="Enter course title" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="course-description">Description</label>
                                        <textarea id="course-description" name="course-description" class="form-control" placeholder="Enter course description" rows="3" required></textarea>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label>Upload Course Content</label>
                                        <div class="upload-area" id="upload-area">
                                            <i class="fas fa-cloud-upload-alt" style="font-size: 2rem; margin-bottom: 10px;"></i>
                                            <p>Click to upload content or drag and drop</p>
                                            <p style="font-size: 0.9rem; color: var(--gray);">PDF, DOC, TXT files accepted</p>
                                        </div>
                                        <input type="file" id="content-upload" name="content_upload" accept=".pdf,.doc,.docx,.txt" style="display: none;">
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary" id="create-course-btn">Create Course</button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="right-column">
                        <div class="dashboard-section">
                            <div class="section-header">
                                <h2>Your Courses</h2>
                            </div>
                            
                            <div class="course-list">
                                <?php if (empty($courses)): ?>
                                    <div class="empty-state">
                                        <i class="fas fa-book-open"></i>
                                        <h3>No courses found</h3>
                                        <p><?php echo $userRole === 'faculty' ? 'Create your first course using the form on the left' : 'You are not enrolled in any courses yet'; ?></p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($courses as $course): ?>
                                        <div class="course-item">
                                            <div class="course-info">
                                                <h3><?php echo htmlspecialchars($course['title']); ?></h3>
                                                <p><?php echo htmlspecialchars($course['description']); ?></p>
                                            </div>
                                            <div class="course-actions">
                                                <?php if ($userRole === 'faculty'): ?>
                                                    <a href="module-creator.php?course_id=<?php echo $course['id']; ?>" class="btn btn-primary">Manage Modules</a>
                                                    <a href="quiz-creator.php?course_id=<?php echo $course['id']; ?>" class="btn btn-info">Create Quiz</a>
                                                <?php else: ?>
                                                    <a href="../student/modules.php?course_id=<?php echo $course['id']; ?>" class="btn btn-primary">View Course</a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Upload area functionality
        document.addEventListener('DOMContentLoaded', function() {
            const uploadArea = document.getElementById('upload-area');
            const contentUpload = document.getElementById('content-upload');
            
            if (uploadArea) {
                uploadArea.addEventListener('click', () => {
                    contentUpload.click();
                });
                
                contentUpload.addEventListener('change', (e) => {
                    if (e.target.files.length > 0) {
                        uploadArea.innerHTML = `
                            <i class="fas fa-check-circle" style="font-size: 2rem; margin-bottom: 10px; color: var(--success);"></i>
                            <p>${e.target.files[0].name}</p>
                            <p style="font-size: 0.9rem; color: var(--success);">Uploaded successfully!</p>
                        `;
                    }
                });
            }
        });
    </script>
</body>
</html>