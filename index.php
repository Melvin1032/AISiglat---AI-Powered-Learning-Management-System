<?php
session_start();
include 'config/config.php';

if (isset($_SESSION['user_id'])) {
    header('Location: ' . ($_SESSION['role'] == 'student' ? 'student/dashboard.php' : 'faculty/dashboard.php'));
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AISiglat LMS - Login</title>
    <link rel="icon" href="../assets/images/AISiglat3.png">
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
<div class="login-wrapper">
    <!-- Left: Logo -->
    <div class="login-logo">
        <img src="assets/images/landing.png" alt="AISiglat LMS Logo">
    </div>
    

    <!-- Right: Login Card (original content) -->
    <div class="login-content">
        <div class="login-card">
            <div class="login-header">
                <h2>Log-in</h2>
                <p>Select your role to continue</p>
            </div>
            
            
            <div class="role-selector">
                <div class="role-option active" data-role="faculty">
                    <i class="fas fa-chalkboard-teacher"></i>
                    <h3>Faculty</h3>
                    <p>Instructor Dashboard</p>
                </div>
                <div class="role-option" data-role="student">
                    <i class="fas fa-user-graduate"></i>
                    <h3>Student</h3>
                    <p>Learning Dashboard</p>
                </div>
            </div>
            
            <input type="hidden" id="selectedRole" value="faculty">
            
            <div class="user-info">
                <div class="avatar">
                    <i class="fas fa-user"></i>
                </div>
                <div class="user-details">
                    <h3 id="userRoleName">Faculty Dashboard</h3>
                    <p id="userRoleDesc">Access to course management and analytics</p>
                </div>
            </div>
            
            <button class="btn btn-primary" id="loginBtn">
                <i class="fas fa-sign-in-alt"></i> Continue to Dashboard
            </button>
        </div>
    </div>
</div>

<script src="assets/js/app.js"></script>

    <script src="assets/js/app.js"></script>
</body>
</html>