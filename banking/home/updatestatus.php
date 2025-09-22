<?php
session_start();

$conn = new mysqli('localhost', 'root', '1234', 'counterservice');

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $account_number = $_POST['account_number'];
    $payment_day = $_POST['payment_day'];
    $verification_status = $_POST['verification_status'];
    $source_page = $_POST['source_page']; // 🟢 ดึงค่าหน้าต้นทาง
    $verify_by = $_SESSION['username']; // ตัวอย่างการดึงข้อมูลผู้ที่ยืนยันจาก session
    $note = $_POST['note'] ?? null;

    // อัปเดต resultsforuse
    $stmt = $conn->prepare("
    UPDATE resultsforuse 
    SET monitor_bank = ?
    WHERE account_number = ?
    ");
    $stmt->bind_param("ss", $verification_status, $account_number); 

    if (!$stmt->execute()) {
        die("Error updating resultsforuse: " . $stmt->error);
    }
    $stmt->close();

    // ตรวจสอบว่ามี account_number ใน commandaccount หรือไม่
    $stmt = $conn->prepare("SELECT account_number FROM commandaccount WHERE account_number = ?");
    $stmt->bind_param("s", $account_number);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // อัปเดตข้อมูลเดิม
        $stmt = $conn->prepare("
            UPDATE commandaccount 
            SET monitor_bank = ? 
            WHERE account_number = ?
        ");
        $stmt->bind_param("ss", $verification_status, $account_number);
        if (!$stmt->execute()) {
            die("Error updating commandaccount: " . $stmt->error);
        }
    } else {
        // เพิ่มข้อมูลใหม่
        $stmt = $conn->prepare("
            INSERT INTO commandaccount (account_number, monitor_bank, payment_day)
            VALUES (?, ?, ?)
        ");
        $stmt->bind_param("sss", $account_number, $verification_status, $payment_day);
        if (!$stmt->execute()) {
            die("Error inserting into commandaccount: " . $stmt->error);
        }
    }

    // เพิ่มข้อมูลลงใน verifiedaccount
    $stmt = $conn->prepare("
    INSERT INTO verifiedaccount (account_number, monitor_bank, verify_by, detail)
    VALUES (?, ?, ?, ?)
    ");
    $stmt->bind_param("ssss", $account_number, $verification_status, $verify_by, $note);

    if (!$stmt->execute()) {
        die("Error inserting into verifiedaccount: " . $stmt->error);
    }
    
    $stmt->close();

    $conn->close();

    // 🟢 Redirect กลับไปที่หน้าต้นทาง
    header("Location: $source_page?account_number=$account_number&payment_day=$payment_day&success=1");
    exit();
    
    
}
?>
