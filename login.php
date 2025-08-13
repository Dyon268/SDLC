<?php
session_start();
include "connect.php";

$message = "";
$selected_role = $_POST['role'] ?? '';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['email'])) {
    $email    = trim($_POST['email']);
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        if (password_verify($password, $user['password'])) {
            if (trim($user['role']) === trim($selected_role)) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['role'] = $user['role'];

                if ($user['role'] == 'admin') {
                    header("Location: admin.php");
                } elseif ($user['role'] == 'instructor') {
                    header("Location: teacher.php");
                } elseif ($user['role'] == 'student') {
                    header("Location: student.php");
                }
                exit;
            } else {
                $message = "You do not have permission for this role. Your role: '" . $user['role'] . "', Selected role: '" . $selected_role . "'";
            }
        } else {
            $message = "Incorrect password.";
        }
    } else {
        $message = "Account does not exist.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Login</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: orange;
            display: flex;
            flex-direction: column;
            align-items: center;
            min-height: 100vh;
            margin: 0;
        }
        .instruction {
            margin-top: 60px;
            font-size: 18px;
            color: white;
            font-weight: bold;
        }
        .role-selection {
            margin-top: 10px;
        }
        .role-btn {
            padding: 10px 20px;
            margin: 0 10px;
            border: 2px solid darkorange;
            background-color: white;
            color: darkorange;
            font-weight: bold;
            cursor: pointer;
            border-radius: 5px;
            transition: all 0.3s;
        }
        .role-btn.active {
            background-color: darkorange;
            color: white;
        }
        .container {
            background: #fff;
            padding: 30px 40px;
            border-radius: 10px;
            width: 400px;
            box-shadow: 0 0 10px rgba(0,0,0,0.2);
            text-align: center;
            margin-top: 30px;
            display: none;
        }
        label {
            display: block;
            margin-top: 15px;
            text-align: left;
        }
        input {
            width: 100%;
            padding: 10px;
            margin-top: 5px;
        }
        button[type="submit"] {
            margin-top: 20px;
            width: 100%;
            padding: 10px;
            background-color: orange;
            color: white;
            border: none;
            cursor: pointer;
            font-weight: bold;
        }
        .link {
            margin-top: 15px;
        }
        .message {
            color: red;
            margin-top: 15px;
        }
    </style>
    <script>
        function selectRole(role) {
            document.getElementById('selected_role').value = role;
            document.querySelector('.container').style.display = 'block';

            document.querySelector('.instruction').style.display = 'none';

            const buttons = document.querySelectorAll('.role-btn');
            buttons.forEach(btn => btn.classList.remove('active'));
            document.getElementById('btn-' + role).classList.add('active');
        }
    </script>
</head>
<body>

<div class="instruction">Please select a role to login</div>

<div class="role-selection">
    <button class="role-btn" id="btn-admin" onclick="selectRole('admin')">Admin</button>
    <button class="role-btn" id="btn-instructor" onclick="selectRole('instructor')">Teacher</button>
    <button class="role-btn" id="btn-student" onclick="selectRole('student')">Student</button>
</div>

<div class="container">
    <h2>Login</h2>
    <form method="POST">
        <input type="hidden" name="role" id="selected_role" value="<?= htmlspecialchars($selected_role) ?>">

        <label>Email:</label>
        <input type="email" name="email" required>

        <label>Password:</label>
        <input type="password" name="password" required>

        <button type="submit">Login</button>

        <div class="link">
            Don't have an account? <a href="register.php">Register now</a>
        </div>

        <?php if ($message): ?>
            <div class="message"><?= $message ?></div>
        <?php endif; ?>
    </form>
</div>

<?php if (!empty($selected_role)): ?>
<script>
    selectRole("<?= $selected_role ?>");
</script>
<?php endif; ?>

</body>
</html>
