<?php
include "connect.php";

$selected_role = $_GET['role'] ?? '';
$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $fullname = trim($_POST['fullname']);
    $email    = trim($_POST['email']);
    $raw_password = $_POST['password'];
    $phone    = trim($_POST['phone']);
    $address  = trim($_POST['address']);
    $role     = $_POST['role'];

    $password_hash = password_hash($raw_password, PASSWORD_DEFAULT);

    try {
        $stmt = $pdo->prepare("INSERT INTO Users (fullname, email, password, role, address, phone) 
                               VALUES (:fullname, :email, :password, :role, :address, :phone)");
        $stmt->execute([
            ':fullname' => $fullname,
            ':email'    => $email,
            ':password' => $password_hash,
            ':role'     => $role,
            ':address'  => $address,
            ':phone'    => $phone
        ]);

        $message = "✅ Registration successful!";
    } catch (PDOException $e) {
        $message = "❌ Registration error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Register Account</title>
    <style>
        body {
            background-color: #FFA500;
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
        }
        .container {
            width: 420px;
            margin: 60px auto;
            background-color: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0px 0px 10px #555;
            text-align: center;
        }
        .role-buttons {
            margin-bottom: 20px;
        }
        .role-buttons a {
            padding: 10px 20px;
            margin: 5px;
            display: inline-block;
            background-color: white;
            color: black;
            text-decoration: none;
            border: 2px solid #FF8C00;
            border-radius: 5px;
            font-weight: bold;
        }
        .role-buttons a.active {
            background-color: #FF8C00;
            color: white;
        }
        input, select {
            width: 100%;
            padding: 10px;
            margin-top: 10px;
            border-radius: 5px;
            border: 1px solid #ccc;
        }
        button {
            background-color: #FF8C00;
            color: white;
            padding: 10px;
            width: 100%;
            margin-top: 15px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
        }
        .message {
            color: red;
            margin-top: 10px;
        }
        .link-login {
            margin-top: 15px;
            display: block;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Select Role</h2>
        <div class="role-buttons">
            <a href="?role=student" class="<?= $selected_role == 'student' ? 'active' : '' ?>">Student</a>
            <a href="?role=instructor" class="<?= $selected_role == 'instructor' ? 'active' : '' ?>">Instructor</a>
        </div>

        <?php if ($selected_role == 'student' || $selected_role == 'instructor'): ?>
        <form method="POST">
            <input type="hidden" name="role" value="<?= htmlspecialchars($selected_role) ?>">

            <input type="text" name="fullname" placeholder="Full Name" required>
            <input type="email" name="email" placeholder="Email" required>
            <input type="password" name="password" placeholder="Password" required>
            <input type="text" name="phone" placeholder="Phone Number">
            <input type="text" name="address" placeholder="Address">

            <button type="submit">Register</button>
        </form>
        <?php else: ?>
            <div class="message">Please select a role to register</div>
        <?php endif; ?>

        <?php if (!empty($message)): ?>
            <div class="message"><?= $message ?></div>
        <?php endif; ?>

        <a class="link-login" href="login.php">Already have an account? Login</a>
    </div>
</body>
</html>
