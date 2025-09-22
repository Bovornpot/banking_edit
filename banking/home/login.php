<?php
session_start();  // เริ่มต้น session

$error_message = '';  // ตัวแปรเพื่อเก็บข้อความ error


// เปิดการแสดงข้อผิดพลาด
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ตรวจสอบว่า form ถูกส่งมาแล้วหรือไม่
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // เชื่อมต่อกับ MySQL
    $conn = new mysqli('localhost', 'root', '1234', 'counterservice');

    // ตรวจสอบการเชื่อมต่อ
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    // รับค่าจากฟอร์ม login
    $username = $_POST['username'];
    $password = $_POST['password'];

    // คำสั่ง SQL เพื่อตรวจสอบข้อมูลผู้ใช้
    $sql = "SELECT * FROM users WHERE username = ?";

    // เตรียมคำสั่ง SQL
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);  // bind parameter สำหรับ username

    // รันคำสั่ง SQL
    $stmt->execute();

    // ดึงผลลัพธ์
    $result = $stmt->get_result();

    // ตรวจสอบว่าพบผู้ใช้หรือไม่
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();

        // ตรวจสอบรหัสผ่าน (ต้องใช้ password_hash และ password_verify สำหรับการเข้ารหัส)
        if (password_verify($password, $row['password'])) {
            // ถ้ารหัสผ่านถูกต้อง ตั้งค่า session และเปลี่ยนหน้า
            $_SESSION['username'] = $row['username'];
            header("Location: index.php");  // หลังจากเข้าสู่ระบบแล้วให้ไปที่หน้า index.php
            exit();
        } else {
            // ถ้ารหัสผ่านผิด
            $error_message = "Incorrect password.";
        }
    } else {
        // ถ้าไม่พบ username
        $error_message = "Username not found.";
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
    <title>Login Page</title>
    <link rel="stylesheet" href="../css/home.css">
</head>

<body id="login-page">
    <div class="body-log">
        <div class="container">
            <div class="form-box">
                <form method="POST" action="login.php">
                    <h2>Login</h2>
                    
                    <input type="text" name="username" placeholder="Username" required></input>
                    <input type="password" name="password" placeholder="Password" required></input>
                    
                    <button type="submit" name="login">Login</button>
                    

                    <p>Don't have an account? <a href="register.php">Register</a></p>

                    <!-- แสดงข้อความ error ที่นี่ -->
                    <?php if (!empty($error_message)): ?>
                        <p style="color: red;"><?php echo $error_message; ?></p>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>
</body>



</html>
