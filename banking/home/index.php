<?php
session_start();  // เริ่มต้น session เพื่อให้สามารถเข้าถึงข้อมูล session

// เชื่อมต่อกับฐานข้อมูล
$conn = new mysqli('localhost', 'root', '1234', 'counterservice');

// ตรวจสอบการเชื่อมต่อ
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// 📌 ส่วนเพิ่มเติมสำหรับ Pagination 
$results_per_page = 100;
$current_page = $_GET['page'] ?? 1;
$offset = ($current_page - 1) * $results_per_page;

// หาวันที่ก่อนหน้า (ถ้าวันนี้คือ 2025-03-24 ให้ดึง 2025-03-23)
$previous_day = date('Y-m-d', strtotime('-1 day'));

// ค่าตัวกรองเริ่มต้น
$filter_date = $_GET['filter_date'] ?? '';
$filter_status = $_GET['filter_status'] ?? '';
$filter_risk = isset($_GET['filter_risk']) ? $_GET['filter_risk'] : '';
$filter_type = $_GET['filter_type'] ?? '';
$sort_order = $_GET['sort_order'] ?? '';

// คำสั่ง SQL เพื่อนับจำนวนบัญชีทั้งหมดในวันที่ก่อนหน้า
$sql_count = "SELECT COUNT(DISTINCT account_number) AS total_accounts FROM resultsforuse WHERE payment_day = '$previous_day'";
$result_count = $conn->query($sql_count);
$row_count = $result_count->fetch_assoc();
$total_accounts = $row_count['total_accounts'];

$whereConditions = [];

if (!empty($filter_date)) {
    $whereConditions[] = "payment_day = '" . $conn->real_escape_string($filter_date) . "'";
}

if (!empty($filter_status)) {
    $whereConditions[] = "monitor_bank = '" . $conn->real_escape_string($filter_status) . "'";
}

if (!empty($filter_risk)) {
    $whereConditions[] = "risk_levels = '" . $conn->real_escape_string($filter_risk) . "'";
}

// สร้างเงื่อนไขรวม (ใช้ AND เชื่อม)
$additionalWhere = '';
if (!empty($whereConditions)) {
    $additionalWhere = " AND " . implode(" AND ", $whereConditions);
}

// สร้าง $order_by ตามตัวกรอง
$order_by_field = '';
if ($filter_type == 'amount') {
    $order_by_field = "total_amount_per_account_day";
} elseif ($filter_type == 'frequency') {
    $order_by_field = "deposit_frequency";
} elseif ($filter_type == 'branch_count') {
    $order_by_field = "branch_count";
}

$order_by_clause = "";
if (!empty($order_by_field) && !empty($sort_order)) {
    $order_by_clause = "$order_by_field $sort_order, ";
}


// 📌 คำสั่ง SQL เพื่อหาจำนวนแถวทั้งหมดที่ตรงตามเงื่อนไข (เพื่อใช้ในการคำนวณ Pagination)
$sql_total_rows = "
SELECT COUNT(*) AS total_rows
FROM (
    SELECT 
        account_number
    FROM resultsforuse
    WHERE
        payment_day = '$previous_day'
        $additionalWhere
        AND (
            (
                monitor_bank = 'Unchecked' AND (
                    (consecutive_days >= 3 AND total_amount_per_account_day >= 90000) OR
                    (transaction_count_per_day_id >= 7 AND total_amount_per_day_id >= 70000) OR
                    (transaction_count_per_day_phone >= 7 AND total_amount_per_day_phone >= 70000)
                )
            ) 
            OR monitor_bank IN ('Staff Deposit Abnormal', 'Fraudulent account')
            OR risk_levels = 'High'
        )
    GROUP BY payment_day, account_number, total_amount_per_account_day, monitor_bank, risk_levels
) AS subquery";

$result_total = $conn->query($sql_total_rows);
$row_total = $result_total->fetch_assoc();
$total_rows = $row_total['total_rows'];


// SQL Query หลัก
$sql_results = "
SELECT 
    SUBSTRING_INDEX(GROUP_CONCAT(DISTINCT store_id ORDER BY store_id ASC), ',', 3) AS store_id,
    MAX(branch_count) AS branch_count,
    MAX(deposit_frequency) AS deposit_frequency,
    GROUP_CONCAT(DISTINCT machine ORDER BY machine ASC) AS machine, 
    SUBSTRING_INDEX(GROUP_CONCAT(DISTINCT region ORDER BY region ASC), ',', 3) AS region,
    payment_day, 
    MIN(payment_time) AS payment_time,
    MAX(shiftwork) AS shiftwork, 
    account_number, 
    total_amount_per_account_day,
    monitor_bank, 
    risk_levels,
    MAX(probability) AS max_probability
FROM resultsforuse
WHERE
    payment_day = '$previous_day'
    $additionalWhere
    AND (
        (
            monitor_bank = 'Unchecked' AND (
                (consecutive_days >= 3 AND total_amount_per_account_day >= 90000) OR
                (transaction_count_per_day_id >= 7 AND total_amount_per_day_id >= 70000) OR
                (transaction_count_per_day_phone >= 7 AND total_amount_per_day_phone >= 70000)
            )
        ) 
        OR monitor_bank IN ('Staff Deposit Abnormal', 'Fraudulent account')
        OR risk_levels = 'High'
    )
GROUP BY payment_day, account_number, total_amount_per_account_day, monitor_bank, risk_levels
ORDER BY 
    $order_by_clause
    CASE 
        WHEN risk_levels = 'High' THEN 1
        WHEN risk_levels = 'Medium' THEN 2
        WHEN risk_levels = 'Low' THEN 3
        ELSE 4  
    END,
    max_probability DESC,
    total_amount_per_account_day DESC,
    deposit_frequency DESC,
    payment_day DESC, 
    payment_time DESC
LIMIT $results_per_page OFFSET $offset
";

// ดึงข้อมูลจากคำสั่ง SQL
$result_results = $conn->query($sql_results);

// นับจำนวนแถวที่ได้รับจากคำสั่ง SQL
// $total_rows ถูกคำนวณไปแล้วจากด้านบน

$total_pages = ceil($total_rows / $results_per_page);
?>

<html>
    <head>
        <title>Command Center - Home Page</title>
        <link rel="stylesheet" href="../css/home.css">
    </head>

    <body>
    <div class="menu">
        <div class="wrapper">
            <ul>
                <li><a href="index.php">หน้าหลัก</a></li>
                <li><a href="account.php">บัญชีที่ยังไม่ได้ตรวจสอบ</a></li>
                
                <?php
                // ตรวจสอบว่า ผู้ใช้เข้าสู่ระบบแล้วหรือไม่
                if (isset($_SESSION['username'])) {
                    echo "<li><a href='#'>สวัสดี, คุณ " . $_SESSION['username'] . "</a></li>";
                    echo "<li><a href='logout.php'>ออกจากระบบ</a></li>";  // ปุ่ม Log Out
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

                <h1>ข้อมูลของวันที่: <?php echo $previous_day; ?></h1>

                <h3>จำนวนบัญชีทั้งหมด: <?php echo $total_rows; ?></h3>


            <form action="" method="get" class="filter-ping">
            <select name="filter_status">
        <option disable value="">สถานะการตรวจสอบ</option>
        <option value="Unchecked" <?php if ($filter_status == 'Unchecked') echo 'selected'; ?>>ยังไม่ตรวจสอบ</option>
        <option value="Staff Deposit Abnormal" <?php if ($filter_status == 'Staff Deposit Abnormal') echo 'selected'; ?>>พนักงานฝาก/ผิดปกติ</option>
        <option value="Fraudulent" <?php if ($filter_status == 'Fraudulent') echo 'selected'; ?>>ฝากเงินลอย</option>
    </select>

    <select name="filter_risk">
        <option disable value="">ระดับความเสี่ยง</option>
        <option value="High" <?php if ($filter_risk == 'High') echo 'selected'; ?>>สูง</option>
        <option value="Medium" <?php if ($filter_risk == 'Medium') echo 'selected'; ?>>กลาง</option>
        <option value="Low" <?php if ($filter_risk == 'Low') echo 'selected'; ?>>ต่ำ</option>
    </select>

    <button type="submit">กรอง</button>
    <button type="button" onclick="window.location='account.php'">ล้าง</button>
</form>


<script>
let currentSortColumn = -1;
let currentSortOrder = 'asc';

function sortTable(columnIndex) {
    const table = document.querySelector("table tbody");
    const rows = Array.from(table.rows);
    const order = currentSortOrder === 'asc' ? 1 : -1;

    // เปลี่ยนทิศทางการเรียงลำดับเมื่อคลิกคอลัมน์เดิม
    if (currentSortColumn === columnIndex) {
        currentSortOrder = currentSortOrder === 'asc' ? 'desc' : 'asc';
    } else {
        currentSortColumn = columnIndex;
        currentSortOrder = 'asc';
    }

    // เรียงแถวตามคอลัมน์ที่เลือก
    rows.sort(function (rowA, rowB) {
        const cellA = rowA.cells[columnIndex].textContent.trim();
        const cellB = rowB.cells[columnIndex].textContent.trim();

        if (isNaN(cellA) || isNaN(cellB)) {
            return cellA.localeCompare(cellB) * order;
        } else {
            return (parseFloat(cellA) - parseFloat(cellB)) * order;
        }
    });

    // อัพเดตการแสดงผล
    rows.forEach(row => table.appendChild(row));

    // อัพเดตไอคอนการเรียงลำดับ
    updateSortIcons(columnIndex);
}

function updateSortIcons(columnIndex) {
    const headers = document.querySelectorAll("th");
    headers.forEach((header, index) => {
        const icon = header.querySelector('.sort-icon');
        if (icon) {
            if (index === columnIndex) {
                icon.classList.remove('sort-asc', 'sort-desc');
                icon.classList.add(currentSortOrder === 'asc' ? 'sort-asc' : 'sort-desc');
            } else {
                icon.classList.remove('sort-asc', 'sort-desc');
            }
        }
    });
}
</script>



                <?php if ($total_rows == 0): ?>
                    <p>ข้อมูลยังไม่ได้รับการอัพเดท</p>
                <?php else: ?>
                    <table border="1">
                        <thead>
                            <tr>
                                <th>รหัสสาขา</th>
                                <th onclick="sortTable(1)">
                                    จำนวนสาขาที่ฝากต่อวัน
                                    <span class="sort-icon"></span>
                                </th>
                                <th onclick="sortTable(2)">
                                    ความถี่ต่อวัน
                                    <span class="sort-icon"></span>
                                </th>
                                <th>เครื่อง</th>
                                <th>ภาค</th>
                                <th onclick="sortTable(5)" style='white-space: nowrap;'>
                                    วันที่ทำรายการ
                                <span class="sort-icon"></span>
                                <th>ผลัด</th>
                                <th>หมายเลขบัญชี</th>
                                <th onclick="sortTable(8)">
                                    จำนวนเงินทั้งหมด
                                    <span class="sort-icon"></span>
                                </th>
                                <th style='white-space: nowrap;'>สถานะการตรวจสอบ</th>
                                <th style='white-space: nowrap;'>ความเสี่ยง</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php
                        if ($result_results->num_rows > 0) {
                            while ($row = $result_results->fetch_assoc()) {
                                echo "<tr onclick=\"window.location='transactions.php?account_number=" . $row['account_number'] . "&payment_day=" . $row['payment_day'] . "'\" style='cursor:pointer;'>";
                                echo "<td>" . $row['store_id'] . "</td>";
                                echo "<td>" . $row['branch_count'] . "</td>"; 
                                echo "<td>" . $row['deposit_frequency'] . "</td>";
                                echo "<td>" . $row['machine'] . "</td>";
                                echo "<td>" . $row['region'] . "</td>";
                                echo "<td>" . $row['payment_day'] . "</td>";
                                echo "<td>" . $row['shiftwork'] . "</td>";
                                echo "<td>" . $row['account_number'] . "</td>";
                                echo "<td>" . $row['total_amount_per_account_day'] . "</td>";
                                echo "<td>" . $row['monitor_bank'] . "</td>";
                                echo "<td>" . $row['risk_levels'] . "</td>";
                                echo "</tr>";
                            }
                        }
                        ?>
                        </tbody>
                    </table>
                <?php endif; ?>
                
                <div class="pagination">
                    <?php if ($total_pages > 1): ?>
                        <ul class="page-list">
                            <?php if ($current_page > 1): ?>
                                <li>
                                    <a href="?page=<?php echo $current_page - 1; ?>&filter_status=<?php echo urlencode($filter_status); ?>&filter_risk=<?php echo urlencode($filter_risk); ?>&filter_type=<?php echo urlencode($filter_type); ?>&sort_order=<?php echo urlencode($sort_order); ?>">ก่อนหน้า</a>
                                </li>
                            <?php endif; ?>

                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li>
                                    <a href="?page=<?php echo $i; ?>&filter_status=<?php echo urlencode($filter_status); ?>&filter_risk=<?php echo urlencode($filter_risk); ?>&filter_type=<?php echo urlencode($filter_type); ?>&sort_order=<?php echo urlencode($sort_order); ?>" class="<?php echo ($i == $current_page) ? 'active' : ''; ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if ($current_page < $total_pages): ?>
                                <li>
                                    <a href="?page=<?php echo $current_page + 1; ?>&filter_status=<?php echo urlencode($filter_status); ?>&filter_risk=<?php echo urlencode($filter_risk); ?>&filter_type=<?php echo urlencode($filter_type); ?>&sort_order=<?php echo urlencode($sort_order); ?>">ถัดไป</a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    <?php endif; ?>
                </div>

            </div>
        </div>
        <div class="footer">
            <div class="wrapper">
                <p class="text-center">CCTV Command Center@CPALL PCL, All right reserved. Developed By Benrueya Kamkongsak</p>
            </div>
        </div>
        </body>
</html>