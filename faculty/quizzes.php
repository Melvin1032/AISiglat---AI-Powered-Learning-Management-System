<?php
session_start();
require_once '../config/config.php';
require_once '../includes/auth-check.php';

if ($_SESSION['role'] !== 'faculty') {
    header('Location: ../index.php?error=access_denied');
    exit;
}

$userId = (int)$_SESSION['user_id'];

// Validate session
$userCheck = $pdo->prepare("SELECT id FROM users WHERE id = ?");
$userCheck->execute([$userId]);
if (!$userCheck->fetch()) {
    session_unset();
    session_destroy();
    header('Location: ../index.php?error=invalid_user');
    exit;
}

// Fetch faculty's courses
$stmt = $pdo->prepare("SELECT id, title FROM courses WHERE faculty_id = ?");
$stmt->execute([$userId]);
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch recently created quizzes
$recentQuizzesStmt = $pdo->prepare("
    SELECT 
        q.id, 
        q.title AS quiz_title, 
        c.title AS course_title, 
        m.title AS module_title,
        q.created_at
    FROM quizzes q
    JOIN modules m ON q.module_id = m.id
    JOIN courses c ON m.course_id = c.id
    WHERE c.faculty_id = ?
    ORDER BY q.created_at DESC
    LIMIT 5
");
$recentQuizzesStmt->execute([$userId]);
$recentQuizzes = $recentQuizzesStmt->fetchAll(PDO::FETCH_ASSOC);

// Handle AJAX save (JSON POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['CONTENT_TYPE'])) {
    if (strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
        $input = json_decode(file_get_contents('php://input'), true);
        if (isset($input['title'], $input['module_id'], $input['questions'])) {
            try {
                $title = trim($input['title']);
                $moduleId = (int)$input['module_id'];

                // Normalize: convert 'correct_answer' → 'answer'
                $normalizedQuestions = [];
                foreach ($input['questions'] as $q) {
                    $normalizedQuestions[] = [
                        'question' => $q['question'] ?? '',
                        'options' => $q['options'] ?? [],
                        'answer' => $q['correct_answer'] ?? 'A'
                    ];
                }
                $questions = json_encode($normalizedQuestions, JSON_UNESCAPED_UNICODE);

                // Verify module ownership
                $stmt = $pdo->prepare("
                    SELECT m.id FROM modules m
                    JOIN courses c ON m.course_id = c.id
                    WHERE m.id = ? AND c.faculty_id = ?
                ");
                $stmt->execute([$moduleId, $userId]);
                if (!$stmt->fetch()) {
                    echo json_encode(['error' => 'Unauthorized']);
                    exit;
                }

                $stmt = $pdo->prepare("INSERT INTO quizzes (module_id, title, questions) VALUES (?, ?, ?)");
                $stmt->execute([$moduleId, $title, $questions]);

                echo json_encode(['success' => true]);
            } catch (Exception $e) {
                error_log("Save error: " . $e->getMessage());
                echo json_encode(['error' => 'Database error']);
            }
            exit;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Quiz Generator - SmartLMS</title>
    <link rel="icon" href="../assets/images/AISiglat3.png">    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/faculty-styles.css">
    <style>
        .dashboard-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
        }
        @media (max-width: 768px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
        }
        .btn-outline {
            background: transparent;
            border: 1px solid #4CAF50;
            color: #4CAF50;
            text-decoration: none;
            padding: 6px 12px;
            border-radius: 4px;
            display: inline-block;
            margin-top: 8px;
        }
        .btn-outline:hover {
            background: #e8f5e9;
        }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include '../includes/sidebar.php'; ?>
        <div class="main-content">
            <?php include '../includes/header.php'; ?>

            <div id="quizzes-page">
                <div class="dashboard-header">
                    <h1>AI Quiz Generator</h1>
                    <p>Generate and manage AI-powered quizzes</p>
                </div>

                <div class="dashboard-grid">
                    <div class="left-column">
                        <div class="dashboard-section">
                            <div class="section-header">
                                <h2>Generate AI Quiz</h2>
                            </div>
                            <div class="course-form">
                                <div class="form-group">
                                    <label>Select Course</label>
                                    <select class="form-control" id="quiz-course-select">
                                        <option value="">Select a course</option>
                                        <?php foreach ($courses as $course): ?>
                                            <option value="<?php echo (int)$course['id']; ?>"><?php echo htmlspecialchars($course['title']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label>Select Module</label>
                                    <select class="form-control" id="quiz-module-select" disabled>
                                        <option value="">Select a course first</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label>Number of Questions</label>
                                    <input type="number" class="form-control" id="question-count" min="5" max="20" value="10">
                                </div>
                                
                                <div class="form-group">
                                    <label>Quiz Title</label>
                                    <input type="text" class="form-control" id="quiz-title" placeholder="e.g., Loops Quiz">
                                </div>
                                <br>
                                <button class="btn btn-primary" id="generate-quiz-btn">Generate AI Quiz</button>
                            </div>
                        </div>

                        <!-- Recent Quizzes -->
                        <div class="dashboard-section">
                            <div class="section-header">
                                <h2>Recent Quizzes</h2>
                            </div>
                            <?php if (empty($recentQuizzes)): ?>
                                <div class="empty-state">
                                    <i class="fas fa-clipboard-list"></i>
                                    <h3>No quizzes created yet</h3>
                                    <p>Generate and save a quiz to see it here</p>
                                </div>
                            <?php else: ?>
                                <div class="results-container">
                                    <?php foreach ($recentQuizzes as $quiz): ?>
                                        <div class="result-item">
                                            <div class="result-info">
                                                <h4><?php echo htmlspecialchars($quiz['quiz_title']); ?></h4>
                                                <p><?php echo htmlspecialchars($quiz['course_title']); ?> – <?php echo htmlspecialchars($quiz['module_title']); ?></p>
                                                <span class="result-date"><?php echo date('M j, Y \a\t g:i A', strtotime($quiz['created_at'])); ?></span>
                                            </div>
                                            <a href="view-quiz.php?id=<?php echo (int)$quiz['id']; ?>" class="btn-outline">View</a>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Preview Panel -->
                    <div class="right-column">
                        <div class="dashboard-section">
                            <div class="section-header">
                                <h2>Quiz Preview</h2>
                            </div>
                            <div class="quiz-container">
                                <h2 id="quiz-title-display">AI-Generated Quiz</h2>
                                <div id="quiz-content">
                                    <div class="empty-state">
                                        <i class="fas fa-robot"></i>
                                        <h3>Generate a quiz to preview</h3>
                                        <p>Your AI-generated questions will appear here</p>
                                    </div>
                                </div>
                                <form id="quiz-form" style="display: none; margin-top: 20px;">
                                    <button type="submit" class="btn btn-success btn-block">Save Quiz</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Load modules by course
        document.getElementById('quiz-course-select').addEventListener('change', async function () {
            const courseId = this.value;
            const select = document.getElementById('quiz-module-select');
            select.innerHTML = '<option>Loading...</option>';
            select.disabled = true;

            if (!courseId) {
                select.innerHTML = '<option>Select a course first</option>';
                return;
            }

            try {
                const res = await fetch(`../ajax/get-modules-by-course.php?course_id=${courseId}`);
                if (!res.ok) throw new Error('HTTP ' + res.status);
                const modules = await res.json();
                select.innerHTML = '<option>Select a module</option>';
                modules.forEach(m => {
                    const opt = document.createElement('option');
                    opt.value = m.id;
                    opt.textContent = m.title;
                    select.appendChild(opt);
                });
                select.disabled = modules.length === 0;
            } catch (err) {
                console.error(err);
                select.innerHTML = '<option>Failed to load</option>';
                alert('❌ Could not load modules.');
            }
        });

        // Generate quiz
        document.getElementById('generate-quiz-btn').addEventListener('click', async function () {
            const course = document.getElementById('quiz-course-select').value;
            const module = document.getElementById('quiz-module-select').value;
            const num = document.getElementById('question-count').value;
            const title = document.getElementById('quiz-title').value.trim();

            if (!course || !module) return alert('Select course and module');
            if (!title) return alert('Enter a quiz title');

            const btn = this;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating...';

            try {
                const formData = new URLSearchParams();
                formData.append('module_id', module);
                formData.append('num_questions', num);

                const res = await fetch('../ajax/generate-quiz-from-module.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: formData
                });

                const result = await res.json();
                if (result.error) {
                    alert('❌ ' + result.error);
                } else if (result.quiz && Array.isArray(result.quiz)) {
                    displayQuiz(result.quiz, title);
                    window.quizData = { title, module_id: module, questions: result.quiz };
                    document.getElementById('quiz-form').style.display = 'block';
                } else {
                    alert('Unexpected AI response.');
                }
            } catch (err) {
                console.error(err);
                alert('Network error. See console.');
            } finally {
                btn.disabled = false;
                btn.innerHTML = 'Generate AI Quiz';
            }
        });

        // Save quiz
        document.getElementById('quiz-form').addEventListener('submit', async function (e) {
            e.preventDefault();
            if (!window.quizData) return;

            const btn = this.querySelector('button');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

            try {
                const res = await fetch('', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(window.quizData)
                });
                const result = await res.json();

                if (result.success) {
                    alert('✅ Quiz saved successfully!');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    alert('❌ Save failed: ' + (result.error || 'Unknown error'));
                }
            } catch (err) {
                console.error(err);
                alert('Save failed. Check console.');
            } finally {
                btn.disabled = false;
                btn.innerHTML = 'Save Quiz';
            }
        });

        function displayQuiz(questions, title) {
            const container = document.getElementById('quiz-content');
            container.innerHTML = '';
            questions.forEach((q, i) => {
                const div = document.createElement('div');
                div.className = 'quiz-question';
                const opts = (q.options || []).map(opt => `<div>${opt}</div>`).join('');
                div.innerHTML = `
                    <h3>${i + 1}. ${q.question || ''}</h3>
                    <div class="quiz-options">${opts}</div>
                `;
                container.appendChild(div);
            });
            document.getElementById('quiz-title-display').textContent = title;
        }
    </script>
</body>
</html>