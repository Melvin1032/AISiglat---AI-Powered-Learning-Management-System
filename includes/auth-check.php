<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

// Optional: enforce integer user_id
$_SESSION['user_id'] = (int) $_SESSION['user_id'];
if ($_SESSION['user_id'] <= 0) {
    session_destroy();
    header('Location: ../index.php?error=invalid_session');
    exit;
}

// Check session expiry
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) {
    session_unset();
    session_destroy();
    header('Location: ../index.php?error=session_expired');
    exit;
}

// 🔑 NEW: Verify user still exists in database
require_once '../config/config.php'; // Make sure $pdo is available
$userCheck = $pdo->prepare("SELECT id, role FROM users WHERE id = ?");
$userCheck->execute([$_SESSION['user_id']]);
$userData = $userCheck->fetch(PDO::FETCH_ASSOC);

if (!$userData) {
    session_unset();
    session_destroy();
    header('Location: ../index.php?error=user_not_found');
    exit;
}

// Optional: Re-sync role in case it changed
$_SESSION['role'] = $userData['role'];

// Update activity
$_SESSION['last_activity'] = time();
?>