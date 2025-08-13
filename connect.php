<?php
$host = 'localhost';
$db   = 'lms'; 
$user = 'root';
$pass = ''; 
$charset = 'utf8mb4';
    

try {
    $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
    $pdo = new PDO($dsn, $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (\PDOException $e) {
    die("Kết nối database thất bại: " . $e->getMessage());
}
