<?php
session_start();
require_once '../config/config.php';
require_once '../includes/auth-check.php';

if ($_SESSION['role'] !== 'student') {
    header('Location: ../index.php');
    exit;
}

$userId = (int)$_SESSION['user_id'];
$courseId = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;

if ($courseId > 0) {
    // 1. Verify Course exists and get first module
    $stmt = $pdo->prepare("SELECT id FROM modules WHERE course_id = ? ORDER BY order_num ASC LIMIT 1");
    $stmt->execute([$courseId]);
    $firstModule = $stmt->fetch();

    if (!$firstModule) {
        header('Location: view-courses.php?error=no_modules');
        exit;
    }

    // 2. Check if already enrolled
    $check = $pdo->prepare("
        SELECT p.id FROM progress p 
        JOIN modules m ON p.module_id = m.id 
        WHERE m.course_id = ? AND p.student_id = ?
    ");
    $check->execute([$courseId, $userId]);
    
    if ($check->fetch()) {
        header('Location: view-courses.php?info=already_enrolled');
        exit;
    }

    // 3. Perform Enrollment (Insert first progress record)
    try {
        $stmt = $pdo->prepare("INSERT INTO progress (student_id, module_id, created_at) VALUES (?, ?, NOW())");
        $stmt->execute([$userId, $firstModule['id']]);
        header('Location: view-courses.php?success=enrolled');
    } catch (Exception $e) {
        header('Location: view-courses.php?error=enrollment_failed');
    }
} else {
    header('Location: view-courses.php?error=invalid_course');
}
exit;