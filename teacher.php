<?php
session_start();
include "connect.php";
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
                $roleLabel = ' (Giảng viên)';
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

if (isset($_GET['ajax']) && $_GET['ajax'] === 'materials') {
    $course_id = (int)$_GET['course_id'];

    $stmt = $pdo->prepare("SELECT * FROM course_materials WHERE course_id = ? ORDER BY uploaded_at DESC");
    $stmt->execute([$course_id]);
    $materials = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($materials)) {
        echo '<p style="text-align: center; color: #666; font-style: italic;">Chưa có tài liệu nào được tải lên.</p>';
    } else {
        echo '<div class="materials-list">';
        foreach ($materials as $material) {
            $fileSize = file_exists("uploads/" . $material['filename']) ? filesize("uploads/" . $material['filename']) : 0;
            $fileSizeFormatted = $fileSize > 0 ? formatFileSize($fileSize) : 'N/A';
            
            echo '<div class="material-item">';
            echo '<div class="material-info">';
            echo '<h4>' . htmlspecialchars($material['filename']) . '</h4>';
            echo '<p>Tải lên: ' . date('d/m/Y H:i', strtotime($material['uploaded_at'])) . '</p>';
            echo '<p>Kích thước: ' . $fileSizeFormatted . '</p>';
            echo '</div>';
            echo '<div class="material-actions">';
            echo '<a href="download.php?id=' . $material['id'] . '" class="btn btn-primary btn-sm">Tải xuống</a>';
            echo '<button class="btn btn-danger btn-sm" onclick="deleteMaterial(' . $material['id'] . ')">Xóa</button>';
            echo '</div>';
            echo '</div>';
        }
        echo '</div>';
    }
    exit;
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'instructor') {
    header("Location: login.php");
    exit;
}

$teacher_id = $_SESSION['user_id'];
$message = "";
$search_keyword = $_GET['search'] ?? '';
$search_date = $_GET['date'] ?? '';

if (!file_exists('uploads')) {
    mkdir('uploads', 0755, true);
}

function formatFileSize($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create_course':
            $course_name = trim($_POST['course_name']);
            $description = trim($_POST['description']);
            $start_date = $_POST['start_date'];
            $end_date = $_POST['end_date'];
            $category = trim($_POST['category']) ?: null;
            $industry = trim($_POST['industry']) ?: null;
            if (!empty($course_name)) {
                $stmt = $pdo->prepare("INSERT INTO courses (name, description, instructor_id, start_date, end_date, category, industry) VALUES (?, ?, ?, ?, ?, ?, ?)");
                if ($stmt->execute([$course_name, $description, $teacher_id, $start_date, $end_date, $category, $industry])) {
                    $message = "Tạo khóa học thành công!";
                } else {
                    $message = "Lỗi tạo khóa học!";
                }
            }
            break;

            $course_id = (int)$_POST['course_id'];
            $course_name = trim($_POST['course_name']);
            $description = trim($_POST['description']);
            $start_date = $_POST['start_date'];
            $end_date = $_POST['end_date'];
            $category = trim($_POST['category']) ?: null;
            $industry = trim($_POST['industry']) ?: null;
            
            $stmt = $pdo->prepare("UPDATE courses SET name=?, description=?, start_date=?, end_date=?, category=?, industry=? WHERE id=? AND instructor_id=?");
            if ($stmt->execute([$course_name, $description, $start_date, $end_date, $category, $industry, $course_id, $teacher_id])) {
                $message = "Cập nhật khóa học thành công!";
            } else {
                $message = "Lỗi cập nhật khóa học!";
            }
            break;
            
        case 'delete_course':
            $course_id = (int)$_POST['course_id'];
            
            $stmt = $pdo->prepare("SELECT filename FROM course_materials WHERE course_id = ?");
            $stmt->execute([$course_id]);
            $materials = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($materials as $material) {
                $filePath = "uploads/" . $material['filename'];
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
            }
            $pdo->prepare("DELETE FROM course_materials WHERE course_id = ?")->execute([$course_id]);
            
            $stmt = $pdo->prepare("DELETE FROM courses WHERE id=? AND instructor_id=?");
            if ($stmt->execute([$course_id, $teacher_id])) {
                $message = "Xóa khóa học thành công!";
            } else {
                $message = "Lỗi xóa khóa học!";
            }
            break;
        case 'send_message':
            $course_id = (int)$_POST['course_id'];
            $message_content = trim($_POST['message']);
            
            if (!empty($message_content)) {
                $stmt = $pdo->prepare("INSERT INTO messages (course_id, sender_id, message, sent_at) VALUES (?, ?, ?, NOW())");
                if ($stmt->execute([$course_id, $teacher_id, $message_content])) {
                    $message = "Gửi tin nhắn thành công!";
                } else {
                    $message = "Lỗi gửi tin nhắn!";
                }
            }
            break;
        case 'upload_material':
            $course_id = (int)$_POST['course_id'];
            
            if (isset($_FILES['material_file']) && $_FILES['material_file']['error'] == UPLOAD_ERR_OK) {
                $file = $_FILES['material_file'];
                $fileName = $file['name'];
                $fileSize = $file['size'];
                $fileTmp = $file['tmp_name'];
                
                if ($fileSize > 50 * 1024 * 1024) {
                    $message = "File quá lớn! Kích thước tối đa là 50MB.";
                    break;
                }
                $allowedExtensions = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'txt', 'jpg', 'jpeg', 'png', 'gif', 'mp4', 'avi', 'mp3', 'zip', 'rar'];
                $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                
                if (!in_array($fileExtension, $allowedExtensions)) {
                    $message = "Định dạng file không được hỗ trợ!";
                    break;
                }
                $uniqueFileName = time() . '_' . uniqid() . '_' . $fileName;
                $uploadPath = "uploads/" . $uniqueFileName;
                
                if (move_uploaded_file($fileTmp, $uploadPath)) {
                    $stmt = $pdo->prepare("INSERT INTO course_materials (course_id, filename, uploaded_at) VALUES (?, ?, NOW())");
                    if ($stmt->execute([$course_id, $uniqueFileName])) {
                        $message = "Tải lên tài liệu thành công!";
                    } else {
                        unlink($uploadPath);
                        $message = "Lỗi lưu thông tin tài liệu!";
                    }
                } else {
                    $message = "Lỗi tải lên file!";
                }
            } else {
                $message = "Vui lòng chọn file để tải lên!";
            }
            break;
            
        case 'delete_material':
            $material_id = (int)$_POST['material_id'];
            
            $stmt = $pdo->prepare("SELECT filename FROM course_materials WHERE id = ?");
            $stmt->execute([$material_id]);
            $material = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($material) {
                $filePath = "uploads/" . $material['filename'];
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
                
                $stmt = $pdo->prepare("DELETE FROM course_materials WHERE id = ?");
                if ($stmt->execute([$material_id])) {
                    echo json_encode(['success' => true, 'message' => 'Xóa tài liệu thành công!']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Lỗi xóa tài liệu!']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Không tìm thấy tài liệu!']);
            }
            exit;
            break;
    }
}

$where_conditions = ["instructor_id = ?"];
$params = [$teacher_id];

if (!empty($search_keyword)) {
    $where_conditions[] = "name LIKE ?";
    $params[] = "%$search_keyword%";
}

if (!empty($search_date)) {
    $where_conditions[] = "DATE(start_date) = ?";
    $params[] = $search_date;
}

$where_clause = implode(" AND ", $where_conditions);
$stmt = $pdo->prepare("SELECT * FROM courses WHERE $where_clause ORDER BY id DESC");
$stmt->execute($params);
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("SELECT fullname FROM users WHERE id = ?");
$stmt->execute([$teacher_id]);
$teacher = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Giảng viên</title>
    <link rel="stylesheet" href="teacher.css">
    <style>
        .materials-list {
            max-height: 400px;
            overflow-y: auto;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 10px;
            margin: 10px 0;
        }
        
        .material-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            border-bottom: 1px solid #eee;
            background: #f9f9f9;
            margin-bottom: 5px;
            border-radius: 3px;
        }
        
        .material-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }
        
        .material-info h4 {
            margin: 0 0 5px 0;
            color: #333;
        }
        
        .material-info p {
            margin: 2px 0;
            font-size: 0.9em;
            color: #666;
        }
        
        .material-actions {
            display: flex;
            gap: 5px;
        }
        
        .upload-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            border: 2px dashed #dee2e6;
        }
        
        .upload-section h3 {
            margin-top: 0;
            color: #495057;
        }
        
        .file-input-wrapper {
            position: relative;
            display: inline-block;
            margin-bottom: 10px;
        }
        
        .file-input {
            position: absolute;
            left: -9999px;
        }
        
        .file-input-label {
            display: inline-block;
            padding: 8px 16px;
            background: #007bff;
            color: white;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .file-input-label:hover {
            background: #0056b3;
        }
        
        .file-selected {
            margin-left: 10px;
            color: #28a745;
            font-weight: bold;
        }
        
        .supported-formats {
            font-size: 0.85em;
            color: #666;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Dashboard Giảng viên</h1>
        <p>Xin chào, <?= htmlspecialchars($teacher['fullname']) ?>!</p>
        <a href="logout.php" class="logout-btn">Đăng xuất</a>
    </div>

    <div class="container">
        <?php if ($message): ?>
            <div class="message <?= strpos($message, 'thành công') !== false ? 'success' : 'error' ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <div class="tabs">
            <button class="tab active" onclick="showTab('courses')">Quản lý khóa học</button>
            <button class="tab" onclick="showTab('create')">Tạo khóa học mới</button>
            <button class="tab" onclick="showTab('materials')">Quản lý tài liệu</button>
            <button class="tab" onclick="showTab('forum')">Diễn đàn chat</button>
        </div>

        <!-- Tab Quản lý khóa học -->
        <div id="courses" class="tab-content active">
            <h2>Danh sách khóa học của bạn</h2>
            
            <!-- Tìm kiếm -->
            <div class="search-section">
                <form method="GET" style="display: flex; gap: 1rem; flex-wrap: wrap; width: 100%;">
                    <input type="text" name="search" placeholder="Tìm kiếm theo tên khóa học..." 
                           value="<?= htmlspecialchars($search_keyword) ?>" style="flex: 1; min-width: 200px;">
                    <input type="date" name="date" value="<?= htmlspecialchars($search_date) ?>" 
                           style="min-width: 150px;">
                    <button type="submit" class="btn btn-primary">Tìm kiếm</button>
                    <a href="teacher.php" class="btn btn-warning">Xóa bộ lọc</a>
                </form>
            </div>

            <div class="course-grid">
                <?php foreach ($courses as $course): ?>
                    <div class="course-card">
                        <div class="course-title"><?= htmlspecialchars($course['name']) ?></div>
                        <div class="course-info">
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
                            <button class="btn btn-warning btn-sm" onclick="editCourse(<?= $course['id'] ?>, '<?= htmlspecialchars($course['name']) ?>', '<?= htmlspecialchars($course['description'] ?? '') ?>', '<?= $course['start_date'] ?>', '<?= $course['end_date'] ?>', '<?= htmlspecialchars($course['category'] ?? '') ?>', '<?= htmlspecialchars($course['industry'] ?? '') ?>')">
                                Sửa
                            </button>
                            <button class="btn btn-danger btn-sm" onclick="deleteCourse(<?= $course['id'] ?>)">
                                Xóa
                            </button>
                            <button class="btn btn-info btn-sm" onclick="manageMaterials(<?= $course['id'] ?>, '<?= htmlspecialchars($course['name']) ?>')">
                                Tài liệu
                            </button>
                            <button class="btn btn-primary btn-sm" onclick="openForum(<?= $course['id'] ?>, '<?= htmlspecialchars($course['name']) ?>')">
                                Chat
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Tab Tạo khóa học -->
        <div id="create" class="tab-content">
            <h2>Tạo khóa học mới</h2>
            <form method="POST">
                <input type="hidden" name="action" value="create_course">
                
                <div class="form-group">
                    <label>Tên khóa học *</label>
                    <input type="text" name="course_name" required>
                </div>
                
                <div class="form-group">
                    <label>Mô tả</label>
                    <textarea name="description" rows="4"></textarea>
                </div>
                
                <div class="form-group">
                    <label>Ngày bắt đầu *</label>
                    <input type="date" name="start_date" required>
                </div>
                
                <div class="form-group">
                    <label>Ngày kết thúc *</label>
                    <input type="date" name="end_date" required>
                </div>
                
                <div class="form-group">
                    <label>Danh mục</label>
                    <input type="text" name="category" placeholder="VD: Lập trình, Thiết kế...">
                </div>
                
                <div class="form-group">
                    <label>Lĩnh vực</label>
                    <input type="text" name="industry" placeholder="VD: Công nghệ thông tin, Marketing...">
                </div>
                
                <button type="submit" class="btn btn-success">Tạo khóa học</button>
            </form>
        </div>

        <!-- Tab Quản lý tài liệu -->
        <div id="materials" class="tab-content">
            <h2>Quản lý tài liệu học tập</h2>
            <p>Chọn khóa học để quản lý tài liệu:</p>
            
            <div class="course-grid">
                <?php foreach ($courses as $course): ?>
                    <div class="course-card">
                        <div class="course-title"><?= htmlspecialchars($course['name']) ?></div>
                        <div class="course-info">
                            <?php if (!empty($course['description'])): ?>
                                <p><?= htmlspecialchars($course['description']) ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="course-actions">
                            <button class="btn btn-info" onclick="manageMaterials(<?= $course['id'] ?>, '<?= htmlspecialchars($course['name']) ?>')">
                                Quản lý tài liệu
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Tab Diễn đàn -->
        <div id="forum" class="tab-content">
            <h2>Diễn đàn chat với sinh viên</h2>
            <p>Chọn khóa học để xem và tham gia chat với sinh viên:</p>
            
            <div class="course-grid">
                <?php foreach ($courses as $course): ?>
                    <div class="course-card">
                        <div class="course-title"><?= htmlspecialchars($course['name']) ?></div>
                        <div class="course-info">
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
        </div>
    </div>

    <!-- Modal sửa khóa học -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <h2>Sửa khóa học</h2>
            <form method="POST">
                <input type="hidden" name="action" value="update_course">
                <input type="hidden" name="course_id" id="edit_course_id">
                
                <div class="form-group">
                    <label>Tên khóa học *</label>
                    <input type="text" name="course_name" id="edit_course_name" required>
                </div>
                
                <div class="form-group">
                    <label>Mô tả</label>
                    <textarea name="description" id="edit_description" rows="4"></textarea>
                </div>
                
                <div class="form-group">
                    <label>Ngày bắt đầu *</label>
                    <input type="date" name="start_date" id="edit_start_date" required>
                </div>
                
                <div class="form-group">
                    <label>Ngày kết thúc *</label>
                    <input type="date" name="end_date" id="edit_end_date" required>
                </div>
                
                <div class="form-group">
                    <label>Danh mục</label>
                    <input type="text" name="category" id="edit_category">
                </div>
                
                <div class="form-group">
                    <label>Lĩnh vực</label>
                    <input type="text" name="industry" id="edit_industry">
                </div>
                
                <button type="submit" class="btn btn-success">Cập nhật</button>
                <button type="button" class="btn btn-warning" onclick="closeModal()">Hủy</button>
            </form>
        </div>
    </div>

    <!-- Modal diễn đàn -->
    <div id="forumModal" class="modal">
        <div class="modal-content" style="max-width: 800px;">
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

    <!-- Modal quản lý tài liệu -->
    <div id="materialsModal" class="modal">
        <div class="modal-content" style="max-width: 900px;">
            <span class="close" onclick="closeMaterials()">&times;</span>
            <h2 id="materialsTitle">Quản lý tài liệu</h2>
            
            <!-- Upload section -->
            <div class="upload-section">
                <h3>Tải lên tài liệu mới</h3>
                <form id="uploadForm" enctype="multipart/form-data">
                    <input type="hidden" id="materials_course_id" name="course_id">
                    <input type="hidden" name="action" value="upload_material">
                    
                    <div class="file-input-wrapper">
                        <input type="file" id="materialFile" name="material_file" class="file-input" onchange="showSelectedFile()">
                        <label for="materialFile" class="file-input-label">Chọn file</label>
                        <span id="selectedFile" class="file-selected"></span>
                    </div>
                    
                    <br>
                    <button type="submit" class="btn btn-success">Tải lên</button>
                    
                    <div class="supported-formats">
                        <strong>Định dạng hỗ trợ:</strong> PDF, DOC, DOCX, PPT, PPTX, XLS, XLSX, TXT, JPG, JPEG, PNG, GIF, MP4, AVI, MP3, ZIP, RAR<br>
                        <strong>Kích thước tối đa:</strong> 50MB
                    </div>
                </form>
            </div>
            
            <!-- Materials list -->
            <div id="materialsList">
                <!-- Materials sẽ được load bằng JavaScript -->
            </div>
        </div>
    </div>

    <script>
        function showTab(tabName) {
            const tabContents = document.querySelectorAll('.tab-content');
            tabContents.forEach(content => content.classList.remove('active'));
            const tabs = document.querySelectorAll('.tab');
            tabs.forEach(tab => tab.classList.remove('active'))
            document.getElementById(tabName).classList.add('active');
            event.target.classList.add('active');
        }

        function editCourse(id, name, description, startDate, endDate, category, industry) {
            document.getElementById('edit_course_id').value = id;
            document.getElementById('edit_course_name').value = name;
            document.getElementById('edit_description').value = description;
            document.getElementById('edit_start_date').value = startDate;
            document.getElementById('edit_end_date').value = endDate;
            document.getElementById('edit_category').value = category;
            document.getElementById('edit_industry').value = industry;
            document.getElementById('editModal').style.display = 'block';
        }
        function closeModal() {
            document.getElementById('editModal').style.display = 'none';
        }
        function deleteCourse(id) {
            if (confirm('Bạn có chắc chắn muốn xóa khóa học này? Tất cả tài liệu liên quan cũng sẽ bị xóa.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_course">
                    <input type="hidden" name="course_id" value="${id}">
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
            fetch(`teacher.php?ajax=forum&course_id=${courseId}`)
                .then(response => response.text())
                .then(data => {
                    document.getElementById('forumMessages').innerHTML = data;
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
            
            fetch('teacher.php', {
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
        function manageMaterials(courseId, courseName) {
            document.getElementById('materials_course_id').value = courseId;
            document.getElementById('materialsTitle').textContent = `Quản lý tài liệu: ${courseName}`;
            document.getElementById('materialsModal').style.display = 'block';
            loadMaterials(courseId);
        }
        function closeMaterials() {
            document.getElementById('materialsModal').style.display = 'none';
            document.getElementById('selectedFile').textContent = '';
            document.getElementById('materialFile').value = '';
        }
        function loadMaterials(courseId) {
            fetch(`teacher.php?ajax=materials&course_id=${courseId}`)
                .then(response => response.text())
                .then(data => {
                    document.getElementById('materialsList').innerHTML = data;
                })
                .catch(error => console.error('Error:', error));
        }
        function showSelectedFile() {
            const fileInput = document.getElementById('materialFile');
            const selectedFile = document.getElementById('selectedFile');
            
            if (fileInput.files.length > 0) {
                const file = fileInput.files[0];
                const fileSize = (file.size / (1024 * 1024)).toFixed(2); // Convert to MB
                selectedFile.textContent = `${file.name} (${fileSize} MB)`;
            } else {
                selectedFile.textContent = '';
            }
        }

        function deleteMaterial(materialId) {
            if (confirm('Bạn có chắc chắn muốn xóa tài liệu này?')) {
                const formData = new FormData();
                formData.append('action', 'delete_material');
                formData.append('material_id', materialId);
                
                fetch('teacher.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(data.message);
                        const courseId = document.getElementById('materials_course_id').value;
                        loadMaterials(courseId);
                    } else {
                        alert(data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Có lỗi xảy ra khi xóa tài liệu!');
                });
            }
        }

        document.getElementById('uploadForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const fileInput = document.getElementById('materialFile');
            if (!fileInput.files.length) {
                alert('Vui lòng chọn file để tải lên!');
                return;
            }
            
            const formData = new FormData(this);
        
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.textContent;
            submitBtn.textContent = 'Đang tải lên...';
            submitBtn.disabled = true;
            
            fetch('teacher.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(data => {
                fileInput.value = '';
                document.getElementById('selectedFile').textContent = '';
                
                const courseId = document.getElementById('materials_course_id').value;
                loadMaterials(courseId);
                
                alert('Tải lên tài liệu thành công!');
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Có lỗi xảy ra khi tải lên tài liệu!');
            })
            .finally(() => {
                submitBtn.textContent = originalText;
                submitBtn.disabled = false;
            });
        });
        setInterval(() => {
            const forumModal = document.getElementById('forumModal');
            if (forumModal.style.display === 'block') {
                const courseId = document.getElementById('forum_course_id').value;
                if (courseId) {
                    loadForumMessages(courseId);
                }
            }
        }, 5000);
        window.onclick = function(event) {
            const editModal = document.getElementById('editModal');
            const forumModal = document.getElementById('forumModal');
            const materialsModal = document.getElementById('materialsModal');
            
            if (event.target === editModal) {
                closeModal();
            }
            if (event.target === forumModal) {
                closeForum();
            }
            if (event.target === materialsModal) {
                closeMaterials();
            }
        }
    </script>
</body>
</html>