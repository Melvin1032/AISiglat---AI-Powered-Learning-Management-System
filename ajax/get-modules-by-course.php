<?php
// ajax/get-modules-by-course.php
session_start();
if ($_SESSION['role'] !== 'faculty') {
    http_response_code(403);
    echo json_encode([]);
    exit;
}
require_once __DIR__ . '/../config/config.php';

$courseId = (int) ($_GET['course_id'] ?? 0);
$facultyId = (int) $_SESSION['user_id'];

$stmt = $pdo->prepare("
    SELECT m.id, m.title 
    FROM modules m
    JOIN courses c ON m.course_id = c.id
    WHERE c.id = ? AND c.faculty_id = ?
");
$stmt->execute([$courseId, $facultyId]);
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));