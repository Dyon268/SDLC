<?php
session_start();
include "connect.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax']) && $_POST['ajax'] === 'send_message') {
    $course_id = (int)$_POST['course_id'];
    $sender_id = $_SESSION['user_id'];
    $message = trim($_POST['message']);

    if ($message !== '') {
        $stmt = $pdo->prepare("INSERT INTO messages (course_id, sender_id, message, sent_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$course_id, $sender_id, $message]);

        echo 'success';
    } else {
        echo 'empty';
    }
    exit;

if (isset($_GET['ajax']) && $_GET['ajax'] === 'forum') {
    $course_id = (int)$_GET['course_id'];
    $user_id = $_SESSION['user_id'];

    $stmt = $pdo->prepare("
        SELECT fm.*, u.fullname, u.role 
        FROM messages fm 
        JOIN users u ON fm.sender_id = u.id 
        WHERE fm.course_id = ? 
        ORDER BY fm.sent_at ASC
    ");
    $stmt->execute([$course_id]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($messages)) {
        echo '<p style="text-align: center; color: #666; font-style: italic;">Chưa có tin nhắn nào. Hãy bắt đầu cuộc trò chuyện!</p>';
    } else {
        foreach ($messages as $message) {
            $isCurrentUser = $message['sender_id'] == $user_id;
            $isTeacher = $message['role'] === 'instructor';
            
            $messageClass = '';
            if ($isTeacher) {
                $messageClass = 'teacher';
            } elseif ($isCurrentUser) {
                $messageClass = 'current-user';
            }   
            $roleLabel = '';
            if ($isTeacher) {
                $roleLabel = ' (teacher)';
            }
            echo '<div class="forum-message ' . $messageClass . '">';
            echo '<div class="message-header">';
            echo '<span><strong>' . htmlspecialchars($message['fullname']) . $roleLabel . '</strong></span>';
            echo '<span class="message-time">' . date('d/m/Y H:i', strtotime($message['sent_at'])) . '</span>';
            echo '</div>';
            echo '<div class="message-content">' . nl2br(htmlspecialchars($message['message'])) . '</div>';
            echo '</div>';
        }
    }
    exit;
}

// Kiểm tra quyền truy cập
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit;
}

$student_id = $_SESSION['user_id'];
$message = "";
$search_keyword = $_GET['search'] ?? '';
$search_date = $_GET['date'] ?? '';
$search_category = $_GET['category'] ?? '';
$search_industry = $_GET['industry'] ?? '';

// Xử lý các action
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST['action'] ?? '';
    switch ($action) {
        case 'register_course':
            $course_id = (int)$_POST['course_id'];
            $stmt = $pdo->prepare("SELECT * FROM enrollments WHERE student_id = ? AND course_id = ?");
            $stmt->execute([$student_id, $course_id]);
            if ($stmt->rowCount() == 0) {
                $stmt = $pdo->prepare("INSERT INTO enrollments (student_id, course_id, enrolled_at) VALUES (?, ?, NOW())");
                if ($stmt->execute([$student_id, $course_id])) {
                    $message = "Đăng ký khóa học thành công!";
                } else {
                    $message = "Lỗi đăng ký khóa học!";
                }
            } else {
                $message = "Bạn đã đăng ký khóa học này rồi!";
            }
            break;
        case 'unregister_course':
            $course_id = (int)$_POST['course_id'];
            $stmt = $pdo->prepare("DELETE FROM enrollments WHERE student_id = ? AND course_id = ?");
            if ($stmt->execute([$student_id, $course_id])) {
                $message = "Hủy đăng ký khóa học thành công!";
            } else {
                $message = "Lỗi hủy đăng ký khóa học!";
            }
            break;
        case 'send_message':
            $course_id = (int)$_POST['course_id'];
            $message_content = trim($_POST['message']);
            $stmt = $pdo->prepare("SELECT * FROM enrollments WHERE student_id = ? AND course_id = ?");
            $stmt->execute([$student_id, $course_id]);
            
            if ($stmt->rowCount() > 0 && !empty($message_content)) {
                $stmt = $pdo->prepare("INSERT INTO forum_messages (course_id, sender_id, message, sent_at) VALUES (?, ?, ?, NOW())");
                if ($stmt->execute([$course_id, $student_id, $message_content])) {
                    $message = "Gửi tin nhắn thành công!";
                } else {
                    $message = "Lỗi gửi tin nhắn!";
                }
            } else {
                $message = "Bạn chỉ có thể chat trong các khóa học đã đăng ký!";
            }
            break;
    }
}

// Lấy danh sách tất cả khóa học với tìm kiếm
$where_conditions = ["1=1"];
$params = [];

if (!empty($search_keyword)) {
    $where_conditions[] = "c.name LIKE ?";
    $params[] = "%$search_keyword%";
}

if (!empty($search_date)) {
    $where_conditions[] = "DATE(c.start_date) = ?";
    $params[] = $search_date;
}

if (!empty($search_category)) {
    $where_conditions[] = "c.category LIKE ?";
    $params[] = "%$search_category%";
}

if (!empty($search_industry)) {
    $where_conditions[] = "c.industry LIKE ?";
    $params[] = "%$search_industry%";
}

$where_clause = implode(" AND ", $where_conditions);
$stmt = $pdo->prepare("
    SELECT c.*, u.fullname as instructor_name,
           CASE WHEN e.student_id IS NOT NULL THEN 1 ELSE 0 END as is_enrolled
    FROM courses c 
    JOIN users u ON c.instructor_id = u.id 
    LEFT JOIN enrollments e ON c.id = e.course_id AND e.student_id = ?
    WHERE $where_clause 
    ORDER BY c.id DESC
");
array_unshift($params, $student_id);
$stmt->execute($params);
$all_courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Lấy danh sách khóa học đã đăng ký
$stmt = $pdo->prepare("
    SELECT c.*, u.fullname as instructor_name, e.enrolled_at
    FROM courses c 
    JOIN users u ON c.instructor_id = u.id 
    JOIN enrollments e ON c.id = e.course_id 
    WHERE e.student_id = ? 
    ORDER BY e.enrolled_at DESC
");
$stmt->execute([$student_id]);
$enrolled_courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("SELECT fullname FROM users WHERE id = ?");
$stmt->execute([$student_id]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("SELECT DISTINCT category FROM courses WHERE category IS NOT NULL AND category != '' ORDER BY category");
$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_COLUMN);

$stmt = $pdo->prepare("SELECT DISTINCT industry FROM courses WHERE industry IS NOT NULL AND industry != '' ORDER BY industry");
$stmt->execute();
$industries = $stmt->fetchAll(PDO::FETCH_COLUMN);
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Sinh viên</title>
    <link rel="stylesheet" href="student.css">
</head>
<body>
    <div class="header">
        <h1>Dashboard Sinh viên</h1>
        <p>Xin chào, <?= htmlspecialchars($student['fullname']) ?>!</p>
        <a href="logout.php" class="logout-btn">Đăng xuất</a>
    </div>

    <div class="container">
        <?php if ($message): ?>
            <div class="message <?= strpos($message, 'thành công') !== false ? 'success' : 'error' ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <div class="stats-section">
            <div class="stat-card">
                <div class="stat-number"><?= count($enrolled_courses) ?></div>
                <div class="stat-label">Khóa học đã đăng ký</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= count($all_courses) ?></div>
                <div class="stat-label">Tổng khóa học có sẵn</div>
            </div>
        </div>

        <div class="tabs">
            <button class="tab active" onclick="showTab('browse')">Duyệt khóa học</button>
            <button class="tab" onclick="showTab('enrolled')">Khóa học của tôi</button>
            <button class="tab" onclick="showTab('forum')">Diễn đàn chat</button>
        </div>

        <!-- Tab Duyệt khóa học -->
        <div id="browse" class="tab-content active">
            <h2>Tìm kiếm và đăng ký khóa học</h2>
            
            <!-- Tìm kiếm -->
            <div class="search-section">
                <form method="GET" style="display: flex; gap: 1rem; flex-wrap: wrap; width: 100%;">
                    <input type="text" name="search" placeholder="Tìm kiếm theo tên khóa học..." 
                           value="<?= htmlspecialchars($search_keyword) ?>" style="flex: 1; min-width: 200px;">
                    
                    <select name="category" style="min-width: 150px;">
                        <option value="">Tất cả danh mục</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= htmlspecialchars($category) ?>" <?= $search_category === $category ? 'selected' : '' ?>>
                                <?= htmlspecialchars($category) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <select name="industry" style="min-width: 150px;">
                        <option value="">Tất cả lĩnh vực</option>
                        <?php foreach ($industries as $industry): ?>
                            <option value="<?= htmlspecialchars($industry) ?>" <?= $search_industry === $industry ? 'selected' : '' ?>>
                                <?= htmlspecialchars($industry) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <input type="date" name="date" value="<?= htmlspecialchars($search_date) ?>" 
                           style="min-width: 150px;">
                    
                    <button type="submit" class="btn btn-primary">Tìm kiếm</button>
                    <a href="student.php" class="btn btn-warning">Xóa bộ lọc</a>
                </form>
            </div>

            <div class="course-grid">
                <?php foreach ($all_courses as $course): ?>
                    <div class="course-card <?= $course['is_enrolled'] ? 'enrolled' : '' ?>">
                        <?php if ($course['is_enrolled']): ?>
                            <div class="enrollment-badge">Đã đăng ký</div>
                        <?php endif; ?>
                        
                        <div class="course-title"><?= htmlspecialchars($course['name']) ?></div>
                        <div class="course-info">
                            <p><strong>Giảng viên:</strong> <?= htmlspecialchars($course['instructor_name']) ?></p>
                            <p><strong>Mô tả:</strong> <?= htmlspecialchars($course['description'] ?? 'Chưa có mô tả') ?></p>
                            <p><strong>Ngày bắt đầu:</strong> <?= date('d/m/Y', strtotime($course['start_date'])) ?></p>
                            <p><strong>Ngày kết thúc:</strong> <?= date('d/m/Y', strtotime($course['end_date'])) ?></p>
                            <?php if (!empty($course['category'])): ?>
                                <p><strong>Danh mục:</strong> <?= htmlspecialchars($course['category']) ?></p>
                            <?php endif; ?>
                            <?php if (!empty($course['industry'])): ?>
                                <p><strong>Lĩnh vực:</strong> <?= htmlspecialchars($course['industry']) ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="course-actions">
                            <?php if ($course['is_enrolled']): ?>
                                <button class="btn btn-danger btn-sm" onclick="unregisterCourse(<?= $course['id'] ?>)">
                                    Hủy đăng ký
                                </button>
                                <button class="btn btn-info btn-sm" onclick="openForum(<?= $course['id'] ?>, '<?= htmlspecialchars($course['name']) ?>')">
                                    Chat
                                </button>
                            <?php else: ?>
                                <button class="btn btn-success btn-sm" onclick="registerCourse(<?= $course['id'] ?>)">
                                    Đăng ký
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Tab Khóa học đã đăng ký -->
        <div id="enrolled" class="tab-content">
            <h2>Khóa học đã đăng ký</h2>
            
            <?php if (empty($enrolled_courses)): ?>
                <p style="text-align: center; color: #666; font-style: italic; margin: 2rem 0;">
                    Bạn chưa đăng ký khóa học nào. <a href="#" onclick="showTab('browse')" style="color: #667eea;">Tìm khóa học</a> để bắt đầu học!
                </p>
            <?php else: ?>
                <div class="course-grid">
                    <?php foreach ($enrolled_courses as $course): ?>
                        <div class="course-card enrolled">
                            <div class="enrollment-badge">Đã đăng ký</div>
                            
                            <div class="course-title"><?= htmlspecialchars($course['name']) ?></div>
                            <div class="course-info">
                                <p><strong>Giảng viên:</strong> <?= htmlspecialchars($course['instructor_name']) ?></p>
                                <p><strong>Mô tả:</strong> <?= htmlspecialchars($course['description'] ?? 'Chưa có mô tả') ?></p>
                                <p><strong>Ngày bắt đầu:</strong> <?= date('d/m/Y', strtotime($course['start_date'])) ?></p>
                                <p><strong>Ngày kết thúc:</strong> <?= date('d/m/Y', strtotime($course['end_date'])) ?></p>
                                <p><strong>Đăng ký lúc:</strong> <?= date('d/m/Y H:i', strtotime($course['enrolled_at'])) ?></p>
                                <?php if (!empty($course['category'])): ?>
                                    <p><strong>Danh mục:</strong> <?= htmlspecialchars($course['category']) ?></p>
                                <?php endif; ?>
                                <?php if (!empty($course['industry'])): ?>
                                    <p><strong>Lĩnh vực:</strong> <?= htmlspecialchars($course['industry']) ?></p>
                                <?php endif; ?>
                            </div>
                            <div class="course-actions">
                                <button class="btn btn-info btn-sm" onclick="openForum(<?= $course['id'] ?>, '<?= htmlspecialchars($course['name']) ?>')">
                                    Chat với lớp
                                </button>
                                <button class="btn btn-danger btn-sm" onclick="unregisterCourse(<?= $course['id'] ?>)">
                                    Hủy đăng ký
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Tab Diễn đàn -->
        <div id="forum" class="tab-content">
            <h2>Diễn đàn chat với giảng viên và sinh viên</h2>
            <p>Chọn khóa học đã đăng ký để tham gia chat:</p>
            
            <?php if (empty($enrolled_courses)): ?>
                <p style="text-align: center; color: #666; font-style: italic; margin: 2rem 0;">
                    Bạn chưa đăng ký khóa học nào. <a href="#" onclick="showTab('browse')" style="color: #667eea;">Đăng ký khóa học</a> để tham gia diễn đàn!
                </p>
            <?php else: ?>
                <div class="course-grid">
                    <?php foreach ($enrolled_courses as $course): ?>
                        <div class="course-card">
                            <div class="course-title"><?= htmlspecialchars($course['name']) ?></div>
                            <div class="course-info">
                                <p><strong>Giảng viên:</strong> <?= htmlspecialchars($course['instructor_name']) ?></p>
                                <?php if (!empty($course['description'])): ?>
                                    <p><?= htmlspecialchars($course['description']) ?></p>
                                <?php endif; ?>
                            </div>
                            <div class="course-actions">
                                <button class="btn btn-primary" onclick="openForum(<?= $course['id'] ?>, '<?= htmlspecialchars($course['name']) ?>')">
                                    Mở diễn đàn
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal diễn đàn -->
    <div id="forumModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeForum()">&times;</span>
            <h2 id="forumTitle">Diễn đàn khóa học</h2>
            
            <div id="forumMessages" class="forum-section">
                <!-- Messages sẽ được load bằng JavaScript -->
            </div>
            
            <form onsubmit="sendMessage(event)">
                <input type="hidden" id="forum_course_id">
                <div class="form-group">
                    <textarea id="messageInput" placeholder="Nhập tin nhắn..." rows="3" required></textarea>
                </div>
                <button type="submit" class="btn btn-primary">Gửi tin nhắn</button>
            </form>
        </div>
    </div>

    <script>
        function showTab(tabName) {
            const tabContents = document.querySelectorAll('.tab-content');
            tabContents.forEach(content => content.classList.remove('active'));
            const tabs = document.querySelectorAll('.tab');
            tabs.forEach(tab => tab.classList.remove('active'));
            document.getElementById(tabName).classList.add('active');
            event.target.classList.add('active');   }
        function registerCourse(courseId) {
            if (confirm('Bạn có chắc chắn muốn đăng ký khóa học này?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="register_course">
                    <input type="hidden" name="course_id" value="${courseId}">
  `;
                document.body.appendChild(form);
                form.submit();  } }
        function unregisterCourse(courseId) {
            if (confirm('Bạn có chắc chắn muốn hủy đăng ký khóa học này?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="unregister_course">
                    <input type="hidden" name="course_id" value="${courseId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        function openForum(courseId, courseName) {
            document.getElementById('forum_course_id').value = courseId;
            document.getElementById('forumTitle').textContent = `Diễn đàn: ${courseName}`;
            document.getElementById('forumModal').style.display = 'block';
            loadForumMessages(courseId);
        }
        function closeForum() {
            document.getElementById('forumModal').style.display = 'none';
        }
        function loadForumMessages(courseId) {
            fetch(`student.php?ajax=forum&course_id=${courseId}`)
                .then(response => response.text())
                .then(data => {
                    document.getElementById('forumMessages').innerHTML = data;
                    // Scroll to bottom
                    const forumMessages = document.getElementById('forumMessages');
                    forumMessages.scrollTop = forumMessages.scrollHeight;
                })
                .catch(error => console.error('Error:', error));
        }
        function sendMessage(event) {
            event.preventDefault();
            const courseId = document.getElementById('forum_course_id').value;
            const message = document.getElementById('messageInput').value;
            const formData = new FormData();
            formData.append('action', 'send_message');
            formData.append('course_id', courseId);
            formData.append('message', message);
            
            fetch('student.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(data => {
                document.getElementById('messageInput').value = '';
                loadForumMessages(courseId);
            })
            .catch(error => console.error('Error:', error));
        }
        setInterval(() => {
            const forumModal = document.getElementById('forumModal');
            if (forumModal.style.display === 'block') {
                const courseId = document.getElementById('forum_course_id').value;
                if (courseId) {
                    loadForumMessages(courseId);
                }
            }
        }, 5000);

        // Close modal when clicking outside
        window.onclick = function(event) {
            const forumModal = document.getElementById('forumModal');
            
            if (event.target === forumModal) {
                closeForum();
            }
        }

        // Auto-refresh page after successful actions (optional)
        document.addEventListener('DOMContentLoaded', function() {
            // Check if there's a success message and auto-hide it after 5 seconds
            const message = document.querySelector('.message.success');
            if (message) {
                setTimeout(() => {
                    message.style.opacity = '0';
                    setTimeout(() => {
                        message.style.display = 'none';
                    }, 300);
                }, 5000);
            }
        });
    </script>
</body>
</html>