<?php
session_start();

// ตัวแปรสำหรับเก็บข้อความ error
$error_message = '';

// เปิดการแสดงข้อผิดพลาด
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ตรวจสอบว่า form ถูกส่งมาแล้วหรือไม่
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // เชื่อมต่อกับฐานข้อมูล MySQL
    $conn = new mysqli('localhost', 'root', '1234', 'counterservice');

    // ตรวจสอบการเชื่อมต่อ
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    // รับค่าจากฟอร์ม
    $username = $_POST['username'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT); // เข้ารหัสรหัสผ่าน
    $full_name = $_POST['full_name'];
    $role = 'user'; // ค่าเริ่มต้นเป็น user

    // คำสั่ง SQL เพื่อเพิ่มข้อมูลผู้ใช้ใหม่
    $sql = "INSERT INTO users (username, email, password, full_name, role) VALUES (?, ?, ?, ?, ?)";

    // เตรียมคำสั่ง SQL
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssss", $username, $email, $password, $full_name, $role);  // bind parameter

    // รันคำสั่ง SQL
    if ($stmt->execute()) {
        header("Location: login.php");  // เมื่อสมัครเสร็จให้ไปที่หน้า Login
        exit();
    } else {
        $error_message = "Error: " . $stmt->error;  // ถ้ามีข้อผิดพลาด
    }

    // ปิดการเชื่อมต่อ
    $stmt->close();
    $conn->close();
}
?>



<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register Page</title>
    <link rel="stylesheet" href="../css/home.css"> <!-- ใช้ไฟล์ CSS เดียวกัน -->
</head>
<body id="login-page"> <!-- ใช้ ID เดียวกันกับ Login -->
    <section class="body-log">
        <div class="container">
            <div class="form-box">
                <form method="POST" action="register.php">
                    <h2>Register</h2>
                    
                    <input type="text" name="username" placeholder="Username" required>
                    <input type="email" name="email" placeholder="Email" required>
                    <input type="text" name="full_name" placeholder="Full Name" required>
                    <input type="password" name="password" placeholder="Password" required>

                    <button type="submit">Register</button>

                    <p>Already have an account? <a href="login.php">Login</a></p>

                    <!-- แสดงข้อความ error -->
                    <?php if (!empty($error_message)): ?>
                        <p style="color: red;"><?php echo $error_message; ?></p>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </section>
</body>
</html>
