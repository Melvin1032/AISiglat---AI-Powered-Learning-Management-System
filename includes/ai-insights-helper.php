<?php
session_start();
require_once '../config/config.php';
require_once '../includes/auth-check.php';

if ($_SESSION['role'] !== 'faculty') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$facultyId = (int)$_SESSION['user_id'];

// Get analytics data
$stmt = $pdo->prepare("
    SELECT 
        AVG(p.score) as avg_score
    FROM courses c
    LEFT JOIN modules m ON c.id = m.course_id
    LEFT JOIN progress p ON m.id = p.module_id
    WHERE c.faculty_id = ?
");
$stmt->execute([$facultyId]);
$stats = $stmt->fetch();
$avgScore = (float)($stats['avg_score'] ?? 0);

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
");
$stmt->execute([$facultyId]);
$atRiskCount = $stmt->rowCount();

$stmt = $pdo->prepare("
    SELECT 
        c.title as course_title,
        AVG(p.score) as avg_score
    FROM courses c
    LEFT JOIN modules m ON c.id = m.course_id
    LEFT JOIN progress p ON m.id = p.module_id
    WHERE c.faculty_id = ?
    GROUP BY c.id
    HAVING AVG(p.score) < 70
");
$stmt->execute([$facultyId]);
$lowCourseCount = $stmt->rowCount();

// Generate insights (without external API for now - use rule-based)
$insights = [];

// Insight 1: Performance Summary
$insights[] = [
    'title' => '📊 Performance Summary',
    'insight' => "Your average score is {$avgScore}%. " . ($atRiskCount > 0 ? "{$atRiskCount} students need attention." : "All students are on track.")
];

// Insight 2: At-Risk Alert
if ($atRiskCount > 0) {
    $insights[] = [
        'title' => '⚠️ At-Risk Alert',
        'insight' => "You have {$atRiskCount} student(s) scoring below 60%. Early intervention is recommended."
    ];
} else {
    $insights[] = [
        'title' => '✅ Student Success',
        'insight' => "No at-risk students detected. Great job supporting your learners!"
    ];
}

// Insight 3: Course Improvement
if ($lowCourseCount > 0) {
    $insights[] = [
        'title' => '📉 Course Improvement',
        'insight' => "{$lowCourseCount} course(s) are scoring below 70%. Consider reviewing content or quiz difficulty."
    ];
} else {
    $insights[] = [
        'title' => '📈 Strong Performance',
        'insight' => "All courses are scoring above 70%. Keep up the great work!"
    ];
}

// Simulate AI processing delay (remove in production)
sleep(1);

echo json_encode(['insights' => $insights]);