<?php
session_start();

// กำหนดเวลาหมดอายุของแคช (เป็นวินาที)
$cache_lifetime = 3600; // 1 ชั่วโมง

// เชื่อมต่อกับฐานข้อมูล
$conn = new mysqli('localhost', 'root', '1234', 'counterservice');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// ลบ query parameter success=1 ออกจาก URL หากมันมีอยู่
$previous_page = $_SERVER['HTTP_REFERER'] ?? "index.php";
$parsed_url = parse_url($previous_page);
if (isset($parsed_url['query'])) {
    parse_str($parsed_url['query'], $query_params);
    unset($query_params['success']);
    $previous_page = strtok($previous_page, '?') . '?' . http_build_query($query_params);
} else {
    $previous_page = strtok($previous_page, '?');
}

// ตรวจสอบว่าได้ส่งหมายเลขบัญชีและวันชำระเงินมาใน URL หรือไม่
if (isset($_GET['account_number']) && isset($_GET['payment_day'])) {
    $account_number = $_GET['account_number'];
    $payment_day = $_GET['payment_day'];

    // สร้างชื่อไฟล์แคชที่ไม่ซ้ำกันสำหรับแต่ละบัญชีและแต่ละวัน
    $cache_file = 'cache/' . md5($account_number . $payment_day) . '.cache';

    // ตรวจสอบว่ามีไฟล์แคชอยู่และยังไม่หมดอายุ
    if (file_exists($cache_file) && (time() - filemtime($cache_file) < $cache_lifetime)) {
        // ถ้าแคชยังใช้งานได้ ให้ดึงข้อมูลจากไฟล์แคช
        $data = unserialize(file_get_contents($cache_file));
        $transactions = $data['transactions'];
        $deposit_frequency = $data['deposit_frequency'];
    } else {
        // ถ้าไม่มีไฟล์แคชหรือแคชหมดอายุ ให้ดึงข้อมูลจากฐานข้อมูล
        $sql_transaction = "
            SELECT store_id, camera_status, machine, region, payment_day, payment_time, shiftwork, account_number, amount, monitor_bank, risk_levels
            FROM resultsforuse
            WHERE account_number = ? AND payment_day = ? 
            ORDER BY payment_time ASC
        ";
        
        $stmt = $conn->prepare($sql_transaction);
        $stmt->bind_param('ss', $account_number, $payment_day);
        $stmt->execute();
        $result_transaction = $stmt->get_result();
        
        $transactions = $result_transaction->fetch_all(MYSQLI_ASSOC);

        // คำนวณความถี่ (จำนวนธุรกรรมต่อวัน)
        $deposit_frequency = count($transactions);

        // ตรวจสอบว่ามีโฟลเดอร์ cache หรือไม่ ถ้าไม่มีให้สร้าง
        if (!is_dir('cache')) {
            mkdir('cache');
        }

        // บันทึกข้อมูลที่ได้จากฐานข้อมูลลงในไฟล์แคช
        $data_to_cache = [
            'transactions' => $transactions,
            'deposit_frequency' => $deposit_frequency
        ];
        file_put_contents($cache_file, serialize($data_to_cache));
    }

} else {
    $transactions = [];
    $deposit_frequency = 0;
}

?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>transaction page</title>
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
    <a href="#" onclick="goBack()" class="previous">⬅ ย้อนกลับ</a>

<script>
function goBack() {
    if (window.history.length > 1) {
        window.history.back();
    } else {
        window.location.href = "index.php"; // Default fallback
    }
}
</script> 
        
    <div class="wrapper">
        <h1>รายการธุรกรรมของบัญชี: <?php echo htmlspecialchars($account_number); ?></h1>

        <form action="updatestatus.php" method="post" class="filter-jaja">
            <input type="hidden" name="account_number" value="<?php echo htmlspecialchars($account_number); ?>">
            <input type="hidden" name="payment_day" value="<?php echo htmlspecialchars($payment_day); ?>">
            <input type="hidden" name="source_page" value="transactions.php"> 

            <label for="verification_status">สถานะการตรวจสอบ:</label>
            <select name="verification_status" required>
                <option value="" disabled selected>เลือก</option>
                <option value="Deposit Normal">ฝากเงินปกติ ปฎิบัติครบทุกขั้นตอน</option>
                <option value="Deposit Normal Incomplete">ฝากเงินปกติ แต่ปฎิบัติไม่ครบตามขั้นตอน</option>
                <option value="Staff Deposit Abnormal">พนักงานฝากเงินที่ร้าน / การฝากมีความผิดปกติ</option>
                <option value="Fraudulent">ฝากเงินลอย</option>
            </select>

            <label for="note">รายละเอียดเพิ่มเติม:</label>
            <input list="noteSuggestions" name="note" id="note" placeholder="กรุณาพิมพ์..." />
                <datalist id="noteSuggestions">
                <option value="โดนมิจฉาชีพหลอก">
                <option value="บัญชีบริษัท">
            </datalist>
            <button type="submit">ยืนยัน</button>
        </form>


        <?php if (isset($_GET['success']) && $_GET['success'] == 1) : ?>
            <p id="success-message" style="color: green;">สถานะการตรวจสอบอัปเดตสำเร็จ!</p>
            <script>
                setTimeout(() => {
                    const url = new URL(window.location.href);
                    url.searchParams.delete('success');
                    window.history.replaceState({}, '', url);
                }, 3000); 
            </script>
        <?php endif; ?>
        
        <h3>
            วันที่: <?php echo htmlspecialchars($payment_day); ?> | 
            <?php
            echo "ความถี่ต่อวัน: $deposit_frequency";
            ?>
        </h3>

        <?php
        echo "<table border='1'>
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
        
        if (!empty($transactions)) {
            foreach ($transactions as $row) {
                echo "<tr>";
                echo "<td>" . $row['store_id'] . "</td>";
                echo "<td>" . $row['camera_status'] . "</td>";
                echo "<td>" . $row['machine'] . "</td>";
                echo "<td>" . $row['region'] . "</td>";
                echo "<td>" . $row['payment_day'] . "</td>";
                echo "<td>" . $row['payment_time'] . "</td>";
                echo "<td>" . $row['shiftwork'] . "</td>";
                echo "<td>" . $row['account_number'] . "</td>";
                echo "<td>" . $row['amount'] . "</td>";
                echo "<td>" . $row['monitor_bank'] . "</td>";
                echo "<td>" . $row['risk_levels'] . "</td>";
                echo "</tr>";
            }
        } else {
            echo "<tr><td colspan='11'>ไม่มีข้อมูลธุรกรรม</td></tr>";
        }
        echo "</tbody></table>";
        ?>
    </div>
</div>
</body>
</html>