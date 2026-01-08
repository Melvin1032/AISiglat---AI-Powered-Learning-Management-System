<?php
session_start();
require_once '../config/config.php';
require_once '../includes/auth-check.php';

if ($_SESSION['role'] !== 'faculty') {
    header('Location: ../index.php');
    exit;
}

$facultyId = $_SESSION['user_id'];
$courseId = (int) ($_GET['course_id'] ?? 0);
$moduleId = (int) ($_GET['module_id'] ?? 0);

// Verify course ownership (if provided)
if ($courseId) {
    $stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ? AND faculty_id = ?");
    $stmt->execute([$courseId, $facultyId]);
    $course = $stmt->fetch();
    if (!$course) {
        header('Location: course-management.php');
        exit;
    }
}

// Verify module ownership (if provided)
$module = null;
if ($moduleId) {
    $stmt = $pdo->prepare("
        SELECT m.*, c.faculty_id 
        FROM modules m 
        JOIN courses c ON m.course_id = c.id 
        WHERE m.id = ? AND c.faculty_id = ?
    ");
    $stmt->execute([$moduleId, $facultyId]);
    $module = $stmt->fetch();
    if (!$module) {
        header('Location: course-management.php');
        exit;
    }
}

// 🔥 Handle AJAX quiz save (JSON POST) – WITH NORMALIZATION
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['CONTENT_TYPE'])) {
    if (strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (isset($input['title'], $input['module_id'], $input['questions'])) {
            try {
                $title = trim($input['title']);
                $moduleId = (int)$input['module_id'];
                
                // ✅ CRITICAL FIX: Normalize 'correct_answer' → 'answer'
                $normalizedQuestions = [];
                foreach ($input['questions'] as $q) {
                    // Clean options (remove "A. " prefix if needed)
                    $cleanOptions = [];
                    foreach (['A', 'B', 'C', 'D'] as $i => $letter) {
                        $opt = $q['options'][$i] ?? "$letter. [Missing]";
                        // Remove leading "A. ", "B. ", etc.
                        $cleanOpt = preg_replace('/^[A-D]\.\s*/i', '', trim($opt));
                        $cleanOptions[] = $cleanOpt;
                    }

                    // Ensure correct_answer is a valid letter
                    $answer = strtoupper(substr(trim($q['correct_answer'] ?? 'A'), 0, 1));
                    if (!in_array($answer, ['A', 'B', 'C', 'D'])) {
                        $answer = 'A';
                    }

                    $normalizedQuestions[] = [
                        'question' => trim($q['question'] ?? 'Untitled Question'),
                        'options' => $cleanOptions,
                        'answer' => $answer  // 👈 NOW USING 'answer'
                    ];
                }

                $questions = json_encode($normalizedQuestions, JSON_UNESCAPED_UNICODE);
                
                // Verify module belongs to faculty
                $stmt = $pdo->prepare("
                    SELECT m.id FROM modules m
                    JOIN courses c ON m.course_id = c.id
                    WHERE m.id = ? AND c.faculty_id = ?
                ");
                $stmt->execute([$moduleId, $facultyId]);
                if (!$stmt->fetch()) {
                    echo json_encode(['error' => 'Unauthorized module']);
                    exit;
                }
                
                // Insert quiz
                $stmt = $pdo->prepare("INSERT INTO quizzes (module_id, title, questions) VALUES (?, ?, ?)");
                $stmt->execute([$moduleId, $title, $questions]);
                
                echo json_encode(['success' => true]);
            } catch (Exception $e) {
                error_log("Quiz save error: " . $e->getMessage());
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
    <title>Quiz Creator - SmartLMS</title>
    <link rel="icon" href="../assets/images/AISiglat3.png">    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/faculty-styles.css">
</head>
<body>
    <div class="app-container">
        <?php include '../includes/sidebar.php'; ?>
        
        <div class="main-content">
            <?php include '../includes/header.php'; ?>
            
            <div id="quizzes-page">
                <div class="dashboard-header">
                    <h1>AI Quiz Generator</h1>
                    <p>AI Generated quizzes from uploaded content</p>
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
                                        <?php
                                        $stmt = $pdo->prepare("SELECT id, title FROM courses WHERE faculty_id = ?");
                                        $stmt->execute([$facultyId]);
                                        $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                        foreach ($courses as $c):
                                        ?>
                                            <option value="<?php echo $c['id']; ?>" <?php echo $courseId == $c['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($c['title']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label>Select Module</label>
                                    <select class="form-control" id="quiz-module-select">
                                        <option value="">Select a module</option>
                                        <?php if ($courseId): ?>
                                            <?php
                                            $stmt = $pdo->prepare("SELECT id, title FROM modules WHERE course_id = ?");
                                            $stmt->execute([$courseId]);
                                            $modules = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                            foreach ($modules as $m):
                                            ?>
                                                <option value="<?php echo $m['id']; ?>" <?php echo $moduleId == $m['id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($m['title']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label>Number of Questions</label>
                                    <input type="number" class="form-control" id="question-count" min="5" max="20" value="10">
                                </div>
                                
                                <div class="form-group">
                                    <label>Quiz Title</label>
                                    <input type="text" class="form-control" id="quiz-title" placeholder="Enter quiz title">
                                </div>
                                <br>
                                <button class="btn btn-primary" id="generate-quiz-btn">Generate AI Quiz</button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="right-column">
                        <div class="dashboard-section">
                            <div class="section-header">
                                <h2>Quiz Content</h2>
                            </div>
                            
                            <div class="quiz-container">
                                <h2 id="quiz-title-display">Programming Basics Quiz</h2>
                                <div id="quiz-content">
                                    <div class="empty-state">
                                        <i class="fas fa-file-alt"></i>
                                        <h3>Generate a quiz to see content</h3>
                                        <p>Use the form on the left to generate AI-powered quizzes</p>
                                    </div>
                                </div>
                                
                                <form id="quiz-form" style="display: none;">
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
document.getElementById('generate-quiz-btn').addEventListener('click', async function() {
    const courseSelect = document.getElementById('quiz-course-select');
    const moduleSelect = document.getElementById('quiz-module-select');
    const questionCount = document.getElementById('question-count').value;
    const quizTitle = document.getElementById('quiz-title').value.trim();
    
    if (!courseSelect.value || !moduleSelect.value) {
        alert('Please select both course and module');
        return;
    }
    if (!quizTitle) {
        alert('Please enter a quiz title');
        return;
    }

    const btn = this;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating...';
    btn.disabled = true;

    try {
        const formData = new URLSearchParams();
        formData.append('module_id', moduleSelect.value);
        formData.append('num_questions', questionCount);
        
        const response = await fetch('../ajax/generate-quiz-from-module.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: formData
        });
        
        const result = await response.json();

        if (result.error) {
            alert('❌ AI Error: ' + result.error);
        } else if (result.quiz && Array.isArray(result.quiz)) {
            displayQuestions(result.quiz);
            document.getElementById('quiz-title-display').textContent = quizTitle;
            
            window.quizData = {
                title: quizTitle,
                module_id: parseInt(moduleSelect.value),
                questions: result.quiz
            };
            
            document.getElementById('quiz-form').style.display = 'block';
        } else {
            alert('Unexpected AI response format.');
        }
    } catch (err) {
        console.error("Network error:", err);
        alert('Network error. Check console.');
    } finally {
        btn.innerHTML = 'Generate AI Quiz';
        btn.disabled = false;
    }
});

document.getElementById('quiz-form').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    if (!window.quizData) {
        alert('No quiz to save!');
        return;
    }

    const saveBtn = this.querySelector('button');
    saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
    saveBtn.disabled = true;

    try {
        const response = await fetch('', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(window.quizData)
        });

        const result = await response.json();
        if (result.success) {
            alert('✅ Quiz saved successfully!');
            setTimeout(() => location.reload(), 1500);
        } else {
            alert('❌ Save failed: ' + (result.error || 'Unknown error'));
        }
    } catch (err) {
        console.error("Save error:", err);
        alert('Network error during save.');
    } finally {
        saveBtn.innerHTML = 'Save Quiz';
        saveBtn.disabled = false;
    }
});

function displayQuestions(questions) {
    const container = document.getElementById('quiz-content');
    container.innerHTML = '';
    
    questions.forEach((q, index) => {
        const questionDiv = document.createElement('div');
        questionDiv.className = 'quiz-question';
        
        let optionsHtml = '';
        if (Array.isArray(q.options)) {
            // Clean options for display (remove A. prefix)
            const cleanOpts = q.options.map(opt => opt.replace(/^[A-D]\.\s*/i, ''));
            cleanOpts.forEach(opt => {
                optionsHtml += `<div class="option">${opt}</div>`;
            });
        }

        // Use 'correct_answer' for preview (from AI)
        const correctLetter = q.correct_answer || 'A';
        
        questionDiv.innerHTML = `
            <h3>${index + 1}. ${q.question}</h3>
            <div class="quiz-options">${optionsHtml}</div>
            <p><em>Correct: ${correctLetter}</em></p>
        `;
        container.appendChild(questionDiv);
    });
}
</script>
</body>
</html>