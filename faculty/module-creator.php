<?php
session_start();
require_once '../config/config.php';
require_once '../includes/auth-check.php';

if ($_SESSION['role'] !== 'faculty') {
    header('Location: ../index.php');
    exit;
}

$courseId = $_GET['course_id'] ?? 0;
$facultyId = $_SESSION['user_id'];

// Verify course belongs to faculty
$stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ? AND faculty_id = ?");
$stmt->execute([$courseId, $facultyId]);
$course = $stmt->fetch();

if (!$course) {
    header('Location: course-management.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['module-title']);
    $content = trim($_POST['module-content']);
    $orderNum = $_POST['order-num'] ?? 1;
    
    $stmt = $pdo->prepare("INSERT INTO modules (course_id, title, content, order_num) VALUES (?, ?, ?, ?)");
    $stmt->execute([$courseId, $title, $content, $orderNum]);
    
    $success = "Module created successfully!";
}

$stmt = $pdo->prepare("SELECT * FROM modules WHERE course_id = ? ORDER BY order_num");
$stmt->execute([$courseId]);
$modules = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Module Creator - SmartLMS</title>
    <link rel="icon" href="../assets/images/AISiglat3.png">    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/faculty-styles.css">
</head>
<body>
    <div class="app-container">
        <?php include '../includes/sidebar.php'; ?>
        
        <div class="main-content">
            <?php include '../includes/header.php'; ?>
            
            <div id="modules-page">
                <div class="dashboard-header">
                    <h1>Module Creator</h1>
                    <p>Intelligent course and module management for: <?php echo htmlspecialchars($course['title']); ?></p>
                </div>
                
                <div class="dashboard-grid">
                    <div class="left-column">
                        <div class="dashboard-section">
                            <div class="section-header">
                                <h2>Create New Module</h2>
                            </div>
                            
                            <?php if (isset($success)): ?>
                                <div class="alert alert-success"><?php echo $success; ?></div>
                            <?php endif; ?>
                            
                            <form method="POST" class="course-form">
                                <div class="form-group">
                                    <label for="module-title">Module Title</label>
                                    <input type="text" id="module-title" name="module-title" class="form-control" placeholder="Enter module title" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="order-num">Order Number</label>
                                    <input type="number" id="order-num" name="order-num" class="form-control" value="1" min="1" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="module-content">Module Content</label>
                                    <textarea id="module-content" name="module-content" class="form-control" placeholder="Enter module content" rows="8" required></textarea>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">Create Module</button>
                            </form>
                        </div>
                    </div>
                    
                    <div class="right-column">
                        <div class="dashboard-section">
                            <div class="section-header">
                                <h2>Course Modules</h2>
                            </div>
                            
                            <div class="module-list">
                                <?php if (empty($modules)): ?>
                                    <div class="empty-state">
                                        <i class="fas fa-book-open"></i>
                                        <h3>No modules created yet</h3>
                                        <p>Create your first module using the form on the left</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($modules as $module): ?>
                                        <div class="module-item">
                                            <div class="module-info">
                                                <h4><?php echo htmlspecialchars($module['title']); ?></h4>
                                                <p><?php echo htmlspecialchars(substr($module['content'], 0, 100)); ?>...</p>
                                                <div class="module-meta">
                                                    <span>Order: <?php echo $module['order_num']; ?></span>
                                                </div>
                                            </div>
                                            <div class="module-actions">
                                                <a href="quiz-creator.php?module_id=<?php echo $module['id']; ?>" class="btn btn-info">Create Quiz</a>
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