<?php
require_once 'config/config.php';

try {
    // Create users table if needed
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        email VARCHAR(100),
        password VARCHAR(255) NOT NULL,
        role ENUM('student', 'faculty', 'admin') NOT NULL
    )");

    // Create courses table if needed
    $pdo->exec("CREATE TABLE IF NOT EXISTS courses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        faculty_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (faculty_id) REFERENCES users(id)
    )");

    // Create modules table if needed
    $pdo->exec("CREATE TABLE IF NOT EXISTS modules (
        id INT AUTO_INCREMENT PRIMARY KEY,
        course_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        content TEXT,
        order_num INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
    )");

    // Create quizzes table if needed
    $pdo->exec("CREATE TABLE IF NOT EXISTS quizzes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        course_id INT NOT NULL,
        module_id INT,
        title VARCHAR(255) NOT NULL,
        questions JSON,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
        FOREIGN KEY (module_id) REFERENCES modules(id) ON DELETE SET NULL
    )");

    // Create progress table if needed
    $pdo->exec("CREATE TABLE IF NOT EXISTS progress (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        module_id INT,
        quiz_id INT,
        score DECIMAL(5,2),
        completed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (student_id) REFERENCES users(id),
        FOREIGN KEY (module_id) REFERENCES modules(id) ON DELETE CASCADE,
        FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE
    )");

    // Create enrollments table if needed (this is the missing table!)
    $pdo->exec("CREATE TABLE IF NOT EXISTS enrollments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        course_id INT NOT NULL,
        enrolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_enrollment (student_id, course_id),
        FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
    )");

    // Define standard accounts
    $testUsers = [
        ['username' => 'student', 'password' => 'student123', 'role' => 'student'],
        ['username' => 'faculty', 'password' => 'faculty123', 'role' => 'faculty']
    ];

    foreach ($testUsers as $u) {
        // We DELETE and RE-INSERT to make sure the hash is fresh
        $pdo->prepare("DELETE FROM users WHERE username = ?")->execute([$u['username']]);

        $hashedPassword = password_hash($u['password'], PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
        $stmt->execute([$u['username'], $hashedPassword, $u['role']]);

        echo "Reset account: <b>" . $u['username'] . "</b> | Password: <b>" . $u['password'] . "</b><br>";
    }

    // Create a sample course for the faculty user
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND role = 'faculty'");
    $stmt->execute(['faculty']);
    $faculty = $stmt->fetch();

    if ($faculty) {
        // Check if sample course already exists
        $stmt = $pdo->prepare("SELECT id FROM courses WHERE title = ? AND faculty_id = ?");
        $stmt->execute(['Introduction to Programming', $faculty['id']]);
        $existingCourse = $stmt->fetch();

        if (!$existingCourse) {
            $stmt = $pdo->prepare("INSERT INTO courses (title, description, faculty_id) VALUES (?, ?, ?)");
            $stmt->execute([
                'Introduction to Programming',
                'Learn the basics of programming with practical examples',
                $faculty['id']
            ]);

            // Get the course ID
            $courseId = $pdo->lastInsertId();

            // Create a sample module
            $stmt = $pdo->prepare("INSERT INTO modules (course_id, title, content, order_num) VALUES (?, ?, ?, ?)");
            $stmt->execute([
                $courseId,
                'Variables and Data Types',
                'This module covers variables, data types, and basic operations in programming.',
                1
            ]);

            // Enroll the student in the course
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND role = 'student'");
            $stmt->execute(['student']);
            $student = $stmt->fetch();

            if ($student) {
                $stmt = $pdo->prepare("INSERT IGNORE INTO enrollments (student_id, course_id) VALUES (?, ?)");
                $stmt->execute([$student['id'], $courseId]);

                echo "Created sample course and enrolled student<br>";
            }
        }
    }

    echo "<br><b>Success!</b> Database tables created and sample data added. Please go to the student login and use 'student' and 'student123'.";
} catch(Exception $e) {
    echo "Setup Error: " . $e->getMessage();
}
?>