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

// Get average score
$stmt = $pdo->prepare("
    SELECT AVG(p.score) as avg_score
    FROM courses c
    LEFT JOIN modules m ON c.id = m.course_id
    LEFT JOIN progress p ON m.id = p.module_id
    WHERE c.faculty_id = ?
");
$stmt->execute([$facultyId]);
$avgScore = (float)($stmt->fetchColumn() ?: 0);

// Get at-risk count (FIXED: use COUNT instead of rowCount)
$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM progress p
    JOIN modules m ON p.module_id = m.id
    JOIN courses c ON m.course_id = c.id
    WHERE c.faculty_id = ? AND p.score < 60
");
$stmt->execute([$facultyId]);
$atRiskCount = (int)$stmt->fetchColumn();

// Get low-performing courses (FIXED)
$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM (
        SELECT c.id
        FROM courses c
        LEFT JOIN modules m ON c.id = m.course_id
        LEFT JOIN progress p ON m.id = p.module_id
        WHERE c.faculty_id = ?
        GROUP BY c.id
        HAVING AVG(p.score) < 70
    ) low_courses
");
$stmt->execute([$facultyId]);
$lowCourseCount = (int)$stmt->fetchColumn();

// Generate insights
$insights = [
    [
        'title' => '📊 Performance Summary',
        'insight' => "Your average score is " . round($avgScore, 1) . "%. " .
                    ($atRiskCount > 0 ? 
                     "There are {$atRiskCount} students who need support." : 
                     "All students are performing well!")
    ],
    [
        'title' => $atRiskCount > 0 ? '⚠️ At-Risk Alert' : '✅ Student Success',
        'insight' => $atRiskCount > 0 ?
            "You have {$atRiskCount} student(s) scoring below 60%. Consider early intervention." :
            "No students are at risk. Great engagement!"
    ],
    [
        'title' => $lowCourseCount > 0 ? '📉 Course Improvement' : '📈 Strong Performance',
        'insight' => $lowCourseCount > 0 ?
            "{$lowCourseCount} course(s) are scoring below 70%. Review content or assessments." :
            "All courses are scoring above 70%. Excellent work!"
    ]
];

echo json_encode(['insights' => $insights]);