<?php
session_start();
include 'connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_user'])) {
        $stmt = $pdo->prepare("INSERT INTO users (fullname, email, password, role, address, phone) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            trim($_POST['fullname']), trim($_POST['email']), password_hash($_POST['password'], PASSWORD_DEFAULT),
            $_POST['role'], $_POST['address'], $_POST['phone']
        ]);
        header("Location: admin.php");
        exit;
    }
     if (isset($_POST['create_course'])) {
        $stmt = $pdo->prepare("INSERT INTO courses (name, description, start_date, end_date, industry, instructor_id) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$_POST['name'], $_POST['description'], $_POST['start_date'], 
                       $_POST['end_date'], $_POST['industry'], $_POST['instructor_id']]);
        header("Location: admin.php");
        exit;
    }
    if (isset($_POST['update_course'])) {
        $stmt = $pdo->prepare("UPDATE courses SET name=?, description=?, start_date=?, end_date=?, industry=?, instructor_id=? WHERE id=?");
        $stmt->execute([$_POST['name'], $_POST['description'], $_POST['start_date'], 
                       $_POST['end_date'], $_POST['industry'], $_POST['instructor_id'], $_POST['course_id']]);
        header("Location: admin.php");
        exit;
    }
    if (isset($_POST['update_user'])) {
        $stmt = $pdo->prepare("UPDATE users SET fullname=?, email=?, role=?, address=?, phone=? WHERE id=?");
        $stmt->execute([
            trim($_POST['fullname']), trim($_POST['email']), $_POST['role'], 
            $_POST['address'], $_POST['phone'], $_POST['user_id']
        ]);
        header("Location: admin.php");
        exit;

}
}
if (isset($_GET['delete_course_id'])) {
    $pdo->prepare("DELETE FROM courses WHERE id = ?")->execute([$_GET['delete_course_id']]);
    header("Location: admin.php");
    exit;
}
if (isset($_GET['delete_user_id'])) {
    $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$_GET['delete_user_id']]);
    header("Location: admin.php");
    exit;
}

// Lấy dữ liệu
$userEdit = null;
if (isset($_GET['edit_user_id'])) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_GET['edit_user_id']]);
    $userEdit = $stmt->fetch();
}
$courseEdit = null;
if (isset($_GET['edit_course_id'])) {
    $stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ?");
    $stmt->execute([$_GET['edit_course_id']]);
    $courseEdit = $stmt->fetch();
}

$userCount = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$courseCount = $pdo->query("SELECT COUNT(*) FROM courses")->fetchColumn();

$userKeyword = $_GET['search_user'] ?? '';
$courseKeyword = $_GET['search_course'] ?? '';

$userQuery = $pdo->prepare("SELECT * FROM users WHERE fullname LIKE ?");
$userQuery->execute(["%$userKeyword%"]);
$users = $userQuery->fetchAll();

$courseQuery = $pdo->prepare("SELECT * FROM courses WHERE name LIKE ?");
$courseQuery->execute(["%$courseKeyword%"]);
$courses = $courseQuery->fetchAll();

$teacherList = $pdo->query("SELECT id, fullname FROM users WHERE role = 'instructor'")->fetchAll();
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard</title>
    <style>
        body{font-family:Arial;background:#fff;margin:0;padding:0}
        .navbar{background:orange;color:white;padding:10px 20px;display:flex;justify-content:space-between;align-items:center}
        .avatar{width:35px;height:35px;border-radius:50%;background:white;cursor:pointer}
        .dropdown{position:absolute;right:10px;top:50px;background:white;border:1px solid #ccc;display:none}
        .dropdown a{display:block;padding:10px;color:black;text-decoration:none}
        .dropdown a:hover{background:#eee}
        .container{padding:20px}
        .card{background:white;padding:20px;margin-bottom:20px;border-radius:10px;box-shadow:0 0 5px #ccc}
        table{width:100%;border-collapse:collapse}
        th,td{padding:10px;border-bottom:1px solid #ddd;text-align:left}
        input,textarea,select{padding:5px;width:100%;margin-bottom:10px}
        .btn{padding:5px 10px;background:orange;color:white;border:none;border-radius:5px;cursor:pointer}
        .btn:hover{background:darkorange}
        .section{display:none}
        .btn-toggle{margin-right:10px}
        .form-container{margin-top:20px;background:#fff;padding:15px;border-radius:10px;box-shadow:0 0 5px #ccc}
    </style>
</head>
<body>

<div class="navbar">
    <h2>Admin Dashboard</h2>
    <div style="position:relative">
        <div class="avatar" onclick="toggleDropdown()"></div>
        <div class="dropdown" id="dropdown">
            <a href="#">Profile</a>
            <a href="logout.php">Logout</a>
        </div></div></div>
<div class="container">
    <div class="card">
        <h3>System Overview</h3>
        <p>Number of users: <strong><?=$userCount?></strong></p>
        <p>Number of courses: <strong><?=$courseCount?></strong></p>
    </div>
    <div class="card">
        <button class="btn btn-toggle" onclick="showSection('users')">User Management</button>
        <button class="btn btn-toggle" onclick="showSection('courses')">Course Management</button>
    </div>
    <div class="card section" id="section-users">
        <h3>User Management</h3>
        <form method="get">
            <input type="text" name="search_user" placeholder="Tìm theo tên..." value="<?=htmlspecialchars($userKeyword)?>">
            <button class="btn">Find</button>
        </form><table>
            <tr><th>ID</th><th>Full name</th><th>Email</th><th>Role</th><th>Address</th><th>Phone</th><th>Act</th></tr>
            <?php foreach($users as $user):?><tr>
                <td><?=$user['id']?></td>
                <td><?=htmlspecialchars($user['fullname'])?></td>
                <td><?=$user['email']?></td>
                <td><?=$user['role']?></td>
                <td><?=$user['address']?></td>
                <td><?=$user['phone']?></td>
                <td>
                    <a href="admin.php?edit_user_id=<?=$user['id']?>" class="btn">Edit</a>
                    <a href="admin.php?delete_user_id=<?=$user['id']?>" class="btn" onclick="return confirm('Delete user?')">Delete</a>
                </td></tr>
            <?php endforeach;?>
        </table>
    <div class="card section" id="section-courses">
        <h3>Course Management</h3>
        <form method="get">
            <input type="text" name="search_course" placeholder="Tìm tên khóa học..." value="<?=htmlspecialchars($courseKeyword)?>">
            <button class="btn">Find</button>
        </form><table>
            <tr><th>ID</th><th>Name</th><th>industry</th><th>Lecturer</th><th>act</th></tr>
            <?php foreach($courses as $course):?><tr>
                <td><?=$course['id']?></td>
                <td><?=htmlspecialchars($course['name'])?></td>
                <td><?=htmlspecialchars($course['industry'])?></td>
                <td><?php
                    foreach($teacherList as $t){
                        if($t['id']==$course['instructor_id']){
                            echo htmlspecialchars($t['fullname']);
                            break; }}?></td><td>
                    <a href="admin.php?edit_course_id=<?=$course['id']?>" class="btn">Edit</a>
                    <a href="admin.php?delete_course_id=<?=$course['id']?>" class="btn" onclick="return confirm('Delete this course?')">Xóa</a>
                </td></tr><?php endforeach;?></table>

        <h4><?=$courseEdit?"Sửa khóa học":"Tạo khóa học mới"?></h4>
        <form method="post">
            <?php if($courseEdit):?>
                <input type="hidden" name="update_course" value="1">
                <input type="hidden" name="course_id" value="<?=$courseEdit['id']?>">
            <?php else:?>
                <input type="hidden" name="create_course" value="1">
            <?php endif;?>

            <label>Tên:</label>
            <input type="text" name="name" required value="<?=$courseEdit?htmlspecialchars($courseEdit['name']):''?>">

            <label>Mô tả:</label>
            <textarea name="description" required><?=$courseEdit?htmlspecialchars($courseEdit['description']):''?></textarea>

            <label>Ngày bắt đầu:</label>
            <input type="date" name="start_date" required value="<?=$courseEdit?$courseEdit['start_date']:''?>">

            <label>Ngày kết thúc:</label>
            <input type="date" name="end_date" required value="<?=$courseEdit?$courseEdit['end_date']:''?>">

            <label>Ngành:</label>
            <input type="text" name="industry" required value="<?=$courseEdit?htmlspecialchars($courseEdit['industry']):''?>">

            <label>Giảng viên:</label>
            <select name="instructor_id" required>
                <option value="">-- Chọn giảng viên --</option>
                <?php foreach($teacherList as $teacher):?>
                    <option value="<?=$teacher['id']?>" <?=$courseEdit&&$teacher['id']==$courseEdit['instructor_id']?'selected':''?>>
                        <?=htmlspecialchars($teacher['fullname'])?>
                    </option>
                <?php endforeach;?>
            </select>

            <button type="submit" class="btn"><?=$courseEdit?"Lưu thay đổi":"Tạo mới"?></button>
            <?php if($courseEdit):?><a href="admin.php" class="btn" style="background:gray;text-decoration:none;color:white">Hủy</a><?php endif;?>
        </form>
    </div>
</div>

<script>
function toggleDropdown(){const d=document.getElementById("dropdown");d.style.display=d.style.display==="block"?"none":"block"}
document.addEventListener("click",function(e){if(!e.target.closest(".avatar"))document.getElementById("dropdown").style.display="none"});
function showSection(section){document.querySelectorAll('.section').forEach(sec=>sec.style.display='none');document.getElementById('section-'+section).style.display='block'}
window.onload=function(){
    <?php if(isset($_GET['edit_course_id']) || isset($_GET['search_course'])):?>
        showSection('courses');
    <?php elseif(isset($_GET['edit_user_id']) || isset($_GET['search_user'])):?>
        showSection('users');
    <?php else:?>
        showSection('users');
    <?php endif;?>
}
</script>

</body>
</html>