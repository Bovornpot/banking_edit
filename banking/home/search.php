<?php
session_start();

// กำหนดเวลาหมดอายุของแคช (เป็นวินาที)
$cache_lifetime = 3600; // 1 ชั่วโมง

// เชื่อมต่อกับฐานข้อมูล
$conn = new mysqli('localhost', 'root', '1234', 'counterservice');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if (isset($_GET['account_number'])) {
    $account_number = $conn->real_escape_string($_GET['account_number']);

    // สร้างชื่อไฟล์แคชที่ไม่ซ้ำกันสำหรับแต่ละบัญชี
    $cache_file = 'cache/' . md5($account_number) . '.cache';

    // ตรวจสอบว่ามีไฟล์แคชอยู่และยังไม่หมดอายุ
    if (file_exists($cache_file) && (time() - filemtime($cache_file) < $cache_lifetime)) {
        // ถ้าแคชยังใช้งานได้ ให้ดึงข้อมูลจากไฟล์แคช
        $transactions = unserialize(file_get_contents($cache_file));
    } else {
        // ถ้าไม่มีไฟล์แคชหรือแคชหมดอายุ ให้ดึงข้อมูลจากฐานข้อมูล
        $sql_transaction = "
            SELECT store_id, camera_status, machine, region, payment_day, payment_time, shiftwork, 
                   account_number, amount, monitor_bank, risk_levels
            FROM resultsforuse
            WHERE account_number = ?
            ORDER BY payment_day DESC, payment_time ASC
        ";
        
        $stmt = $conn->prepare($sql_transaction);
        $stmt->bind_param('s', $account_number);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $transactions = $result->fetch_all(MYSQLI_ASSOC);

        // ตรวจสอบว่ามีโฟลเดอร์ cache หรือไม่ ถ้าไม่มีให้สร้าง
        if (!is_dir('cache')) {
            mkdir('cache');
        }

        // บันทึกข้อมูลที่ได้จากฐานข้อมูลลงในไฟล์แคช
        file_put_contents($cache_file, serialize($transactions));
    }

} else {
    $transactions = [];
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ผลการค้นหา</title>
    <link rel="stylesheet" href="../css/home.css">
</head>
<body>

    <div class="menu">
        <div class="wrapper">
            <ul>
                <li><a href="index.php">หน้าหลัก</a></li>
                <li><a href="account.php">บัญชีที่ยังไม่ได้ตรวจสอบ</a></li>

                <?php
                if (isset($_SESSION['username'])) {
                    echo "<li><a href='#'>สวัสดี, คุณ " . $_SESSION['username'] . "</a></li>";
                    echo "<li><a href='logout.php'>ออกจากระบบ</a></li>";
                } else {
                    echo "<li><a href='login.php'>Log in</a></li>";
                }
                ?>
            </ul>
            <div class="search-container">
                <form action="search.php" method="get" class="search-form">
                    <input type="text" name="account_number" placeholder="ค้นหาหมายเลขบัญชี" required>
                    <button type="submit">ค้นหา</button>
                </form>
            </div>
        </div>
    </div>

    <div class="main-content">
    <div class="wrapper">
        <h1>🔎 ผลการค้นหาบัญชี: <?php echo htmlspecialchars($account_number); ?></h1>

        <?php
        if (!empty($transactions)) {
            $current_date = ""; 
            $first_table = true;

            foreach ($transactions as $row) {
                if ($current_date != $row['payment_day']) {
                    if ($current_date != "") {
                        echo "</tbody></table>";
                    }

                    $current_date = $row['payment_day'];
                    $total_amount_per_day = 0;
                    $frequency_per_day = 0;
                    
                    foreach ($transactions as $check_row) {
                        if ($check_row['payment_day'] == $current_date && $check_row['account_number'] == $row['account_number']) {
                            $total_amount_per_day += $check_row['amount'];
                            $frequency_per_day++;
                        }
                    }
                    
                    if ($first_table) {
                        echo '<form action="updatestatus.php" method="post" class="filter-jaja">
                                <input type="hidden" name="account_number" value="' . htmlspecialchars($account_number) . '">
                                <input type="hidden" name="payment_day" value="' . htmlspecialchars($current_date) . '">
                                <input type="hidden" name="source_page" value="search.php">
                            
                                <label for="verification_status">สถานะการตรวจสอบ:</label>
                                <select name="verification_status" required>
                                    <option value="" disabled selected>เลือก</option>
                                    <option value="Deposit Normal">ฝากเงินปกติ ปฎิบัติครบทุกขั้นตอน</option>
                                    <option value="Deposit Normal Incomplete">ฝากเงินปกติ แต่ปฎิบัติไม่ครบตามขั้นตอน</option>
                                    <option value="Staff Deposit Abnormal">พนักงานฝากเงินที่ร้าน / การฝากมีความผิดปกติ</option>
                                    <option value="Fraudulent">ฝากเงินลอย</option>
                                </select>
                            
                                <label for="note">รายละเอียดเพิ่มเติม:</label>
                                <input list="noteSuggestions" name="note" id="note" placeholder="กรุณาพิมพ์..." autocomplete="off" />
                                <datalist id="noteSuggestions">
                                    <option value="โดนมิจฉาชีพหลอก">
                                    <option value="บัญชีบริษัท">
                                </datalist>
                            
                                <button type="submit">ยืนยัน</button>
                            </form>';
                        
                        $first_table = false;
                    }
                    
                    if (isset($_GET['success']) && $_GET['success'] == 1) {
                        echo '<p id="success-message" style="color: green;">สถานะการตรวจสอบอัปเดตสำเร็จ!</p>';
                    }
                     echo "<h3>ข้อมูลวันที่: " . $current_date . " | ความถี่ต่อวัน: " . $frequency_per_day . " | ยอดรวม: " . number_format($total_amount_per_day, 2) . "</h3>";
                    echo "<table border='1' cellpadding='10'>
                            <thead>
                                <tr>
                                    <th style='white-space: nowrap;'>รหัสสาขา</th>
                                    <th style='white-space: nowrap;'>สถานะกล้อง</th>
                                    <th>เครื่อง</th>
                                    <th>ภาค</th>
                                    <th style='white-space: nowrap;'>วันที่ทำรายการ</th>
                                    <th style='white-space: nowrap;'>เวลาที่ทำรายการ</th>
                                    <th>ผลัด</th>
                                    <th style='white-space: nowrap;'>หมายเลขบัญชี</th>
                                    <th style='white-space: nowrap;'>จำนวนเงิน</th>
                                    <th style='white-space: nowrap;'>สถานะการตรวจสอบ</th>
                                    <th style='white-space: nowrap;'>ความเสี่ยง</th>
                                </tr>
                            </thead>
                            <tbody>";
                }

                echo "<tr>
                        <td>" . $row['store_id'] . "</td>
                        <td>" . $row['camera_status'] . "</td>
                        <td>" . $row['machine'] . "</td>
                        <td>" . $row['region'] . "</td>
                        <td>" . $row['payment_day'] . "</td>
                        <td>" . $row['payment_time'] . "</td>
                        <td>" . $row['shiftwork'] . "</td>
                        <td>" . $row['account_number'] . "</td>
                        <td>" . $row['amount'] . "</td>
                        <td>" . $row['monitor_bank'] . "</td>
                        <td>" . $row['risk_levels'] . "</td>
                      </tr>";
            }
            echo "</tbody></table>";
        } else {
            echo "<p>ไม่พบข้อมูลสำหรับหมายเลขบัญชีนี้</p>";
        }
        ?>
    </div>
    </div>
</body>
</html>