<?php
session_start();
require_once '../config/config.php';
require_once '../includes/auth-check.php';

$userId = (int)$_SESSION['user_id'];
$userRole = $_SESSION['role'];

// Get analytics data
if ($userRole === 'faculty') {
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT c.id) as total_courses,
            COUNT(DISTINCT p.student_id) as total_students,
            AVG(p.score) as avg_score
        FROM courses c
        LEFT JOIN modules m ON c.id = m.course_id
        LEFT JOIN progress p ON m.id = p.module_id
        WHERE c.faculty_id = ?
    ");
    $stmt->execute([$userId]);
    $stats = $stmt->fetch();
    
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
        LIMIT 5
    ");
    $stmt->execute([$userId]);
    $atRiskStudents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt = $pdo->prepare("
        SELECT 
            c.title as course_title,
            AVG(p.score) as avg_score,
            COUNT(p.id) as total_attempts
        FROM courses c
        LEFT JOIN modules m ON c.id = m.course_id
        LEFT JOIN progress p ON m.id = p.module_id
        WHERE c.faculty_id = ?
        GROUP BY c.id
    ");
    $stmt->execute([$userId]);
    $coursePerformance = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT c.id) as total_courses,
            COUNT(DISTINCT p.module_id) as completed_modules,
            AVG(p.score) as avg_score
        FROM courses c
        JOIN modules m ON c.id = m.course_id
        LEFT JOIN progress p ON m.id = p.module_id AND p.student_id = ?
    ");
    $stmt->execute([$userId]);
    $stats = $stmt->fetch();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics - SmartLMS</title>
    <link rel="icon" href="../assets/images/AISiglat3.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/faculty-styles.css">
    <style>
        .btn-ai {
            background: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            font-weight: bold;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: opacity 0.3s;
        }
        .btn-ai:hover {
            opacity: 0.9;
        }
        .btn-ai:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        .spinner {
            border: 2px solid #f3f3f3;
            border-top: 2px solid #2575fc;
            border-radius: 50%;
            width: 16px;
            height: 16px;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .insight-card {
            opacity: 0;
            animation: fadeIn 0.5s forwards;
        }
        @keyframes fadeIn {
            to { opacity: 1; }
        }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include '../includes/sidebar.php'; ?>
        
        <div class="main-content">
            <?php include '../includes/header.php'; ?>
            
            <div id="analytics-page">
                <div class="dashboard-header">
                    <h1>Predictive Analytics Dashboard</h1>
                    <p>Analytics for course performance and student progress</p>
                </div>
                
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon bg-primary">
                            <i class="fas fa-percentage"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $stats['avg_score'] ? round($stats['avg_score'], 0) : 0; ?>%</h3>
                            <p>Average Score</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon bg-success">
                            <i class="fas fa-chart-bar"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo count($coursePerformance ?? []); ?></h3>
                            <p>Courses Tracked</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon bg-warning">
                            <i class="fas fa-exclamation-circle"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo count($atRiskStudents ?? []); ?></h3>
                            <p>At-Risk Students</p>
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
                
                <div class="dashboard-grid">
                    <div class="left-column">
                        <div class="dashboard-section">
                            <div class="section-header">
                                <h2>Course Performance</h2>
                            </div>
                            
                            <div class="performance-list">
                                <?php if (empty($coursePerformance)): ?>
                                    <div class="empty-state">
                                        <i class="fas fa-chart-line"></i>
                                        <p>No quiz data available yet.</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($coursePerformance as $course): ?>
                                        <div class="performance-item">
                                            <div class="performance-info">
                                                <h4><?php echo htmlspecialchars($course['course_title']); ?></h4>
                                                <div class="progress-container">
                                                    <div class="progress-bar" style="width: <?php echo min(100, max(0, (float)$course['avg_score'])); ?>%"></div>
                                                </div>
                                            </div>
                                            <div class="performance-score">
                                                <?php echo $course['avg_score'] !== null ? round($course['avg_score'], 1) : '0'; ?>%
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="right-column">
                        <div class="dashboard-section">
                            <div class="section-header">
                                <h2>
                                    AI Predictive Insights
                                    <?php if ($userRole === 'faculty'): ?>
                                        <button id="generate-ai-btn" class="btn-ai">
                                            <span id="btn-text">Generate AI Insights</span>
                                            <span id="spinner" class="spinner" style="display:none;"></span>
                                        </button>
                                    <?php endif; ?>
                                </h2>
                            </div>
                            
                            <div class="insights-container" id="insights-container">
                                <!-- Static fallback -->
                                <div class="insight-card">
                                    <h4><i class="fas fa-lightbulb"></i> Course Improvement</h4>
                                    <p>Review course content for modules with low quiz scores.</p>
                                </div>
                                <div class="insight-card">
                                    <h4><i class="fas fa-user-check"></i> Student Intervention</h4>
                                    <p>Reach out to students scoring below 60% for additional support.</p>
                                </div>
                                <div class="insight-card">
                                    <h4><i class="fas fa-chart-line"></i> Trend Analysis</h4>
                                    <p>Monitor progress weekly to identify emerging trends.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    document.getElementById('generate-ai-btn').addEventListener('click', async function() {
        const btn = this;
        const btnText = document.getElementById('btn-text');
        const spinner = document.getElementById('spinner');
        const container = document.getElementById('insights-container');
        
        // Disable button and show spinner
        btn.disabled = true;
        btnText.style.display = 'none';
        spinner.style.display = 'inline-block';
        
        try {
            const response = await fetch('../ajax/generate-ai-insights.php');
            const data = await response.json();
            
            if (data.error) {
                throw new Error(data.error);
            }
            
            // Build new HTML
            let html = '';
            data.insights.forEach(insight => {
                html += `
                    <div class="insight-card">
                        <h4>${insight.title}</h4>
                        <p>${insight.insight}</p>
                    </div>
                `;
            });
            
            container.innerHTML = html;
            
        } catch (error) {
            console.error('AI Insights error:', error);
            container.innerHTML = `
                <div class="insight-card">
                    <h4><i class="fas fa-exclamation-triangle"></i> AI Insights Unavailable</h4>
                    <p>Could not generate insights. Please try again later.</p>
                </div>
            `;
        } finally {
            // Re-enable button
            btn.disabled = false;
            btnText.style.display = 'inline';
            spinner.style.display = 'none';
        }
    });
    </script>
</body>
</html>