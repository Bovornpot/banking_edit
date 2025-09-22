<?php
session_start();
session_unset();  // ลบข้อมูลทั้งหมดจาก session
session_destroy();  // ทำลาย session
header('Location: login.php');  // เปลี่ยนหน้าไปที่หน้า login
exit();
?>
