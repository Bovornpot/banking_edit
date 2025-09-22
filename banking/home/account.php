<?php
session_start();

// เชื่อมต่อฐานข้อมูล
$conn = new mysqli('localhost', 'root', '1234', 'counterservice');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// กำหนดจำนวนข้อมูลต่อหน้า
$limit = 100;

// รับค่าหน้าปัจจุบันจาก URL (ถ้าไม่มีให้เป็นหน้า 1)
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($current_page < 1) {
    $current_page = 1;
}

// คำนวณค่า OFFSET
$offset = ($current_page - 1) * $limit;

// ค่าตัวกรองเริ่มต้น
$filter_date = $_GET['filter_date'] ?? '';
$filter_status = $_GET['filter_status'] ?? '';
$filter_risk = isset($_GET['filter_risk']) ? $_GET['filter_risk'] : '';
$filter_type = $_GET['filter_type'] ?? '';
$sort_order = $_GET['sort_order'] ?? '';

// กำหนดเงื่อนไข WHERE สำหรับการกรองข้อมูล
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
    $additionalWhere = " WHERE " . implode(" AND ", $whereConditions);
}

// ค่าเริ่มต้นสำหรับ SQL ORDER BY
$order_by = "total_amount_per_account_day DESC";
if ($filter_type && $sort_order) {
    $order_by = ($filter_type == 'amount' ? "total_amount_per_account_day" : "deposit_frequency") . " $sort_order";
}

// คำสั่ง SQL สำหรับนับจำนวนแถวทั้งหมด (จากตารางใหม่)
$sql_count = "
    SELECT COUNT(*) AS total_rows
    FROM preprocessed_results
    $additionalWhere
";

$result_count = $conn->query($sql_count);
$row_count = $result_count->fetch_assoc();
$total_rows = $row_count['total_rows'];
$total_pages = ceil($total_rows / $limit);

// คำสั่ง SQL สำหรับดึงข้อมูลมาแสดงผลในหน้าปัจจุบัน (จากตารางใหม่)
$sql_results = "
    SELECT 
        store_id,
        branch_count,
        deposit_frequency,
        machine,
        region,
        payment_day,
        payment_time,
        shiftwork,
        account_number,
        total_amount_per_account_day,
        monitor_bank,
        risk_levels
    FROM preprocessed_results
    $additionalWhere
    ORDER BY 
        store_id ASC, 
        CASE 
            WHEN risk_levels = 'High' THEN 1
            WHEN risk_levels = 'Medium' THEN 2
            WHEN risk_levels = 'Low' THEN 3
            ELSE 4
        END,
        $order_by
    LIMIT $limit OFFSET $offset
";

$result_results = $conn->query($sql_results);

$sql_date_range = "
SELECT MIN(payment_day) AS first_date, MAX(payment_day) AS last_date
FROM preprocessed_results
";
$date_range_result = $conn->query($sql_date_range);
$date_range = $date_range_result->fetch_assoc();

$first_date = $date_range['first_date'];
$last_date = $date_range['last_date'];

?>

<html>
    <head>
        <title>Command Center - Home Page</title>
        <link rel="stylesheet" href="../css/home.css">
    </head>

    <body>

    <script>
    if (performance.navigation.type === 1) { // ถ้าหน้าเพิ่งโหลดใหม่ (reload)
        window.location.href = window.location.pathname; // รีเซ็ต URL ล้างค่ากรอง
    }
    </script>
    
    <div class="menu">
        <div class="wrapper">
            <ul>
                <li><a href="index.php">หน้าหลัก</a></li>
                <li><a href="account.php">บัญชีที่ยังไม่ได้ตรวจสอบ</a></li>
                
                <?php
                // ตรวจสอบว่า ผู้ใช้เข้าสู่ระบบแล้วหรือไม่
                if (isset($_SESSION['username'])) {
                    // ถ้าผู้ใช้เข้าสู่ระบบแล้ว แสดงชื่อผู้ใช้และปุ่ม "Log Out"
                    echo "<li><a href='#'>สวัสดี, คุณ " . $_SESSION['username'] . "</a></li>";
                    echo "<li><a href='logout.php'>ออกจากระบบ</a></li>";
                } else {
                    // ถ้าผู้ใช้ยังไม่ได้เข้าสู่ระบบ ให้แสดงลิงก์ Log in
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
    <div class="wrappermain">

        <h1>วันที่ปัจจุบัน: <?php echo date('Y-m-d'); ?></h1> 

        <div class="filter-container">

        <h3 style="color: #c0392b; font-weight: bold;" >ข้อมูลจากวันที่: <?php echo $first_date; ?> ถึง <?php echo $last_date; ?></h3>
        <h3>จำนวนบัญชีทั้งหมด: <?php echo $total_rows; ?></h3>

            
            <form action="" method="get" class="filter-form">
    <input type="date" name="filter_date" value="<?php echo htmlspecialchars($filter_date); ?>">

    <select name="filter_status">
        <option value="" disabled selected>สถานะการตรวจสอบ</option>n>
        <option value="Unchecked" <?php if ($filter_status == 'Unchecked') echo 'selected'; ?>>ยังไม่ตรวจสอบ</option>
        <option value="Staff Deposit Abnormal" <?php if ($filter_status == 'Staff Deposit Abnormal') echo 'selected'; ?>>พนักงานฝาก/ผิดปกติ</option>
        <option value="Fraudulent account" <?php if ($filter_status == 'Fraudulent account') echo 'selected'; ?>>บัญชีทุจริต</option>
    </select>

    <select name="filter_risk">
        <option value="" disabled selected>ระดับความเสี่ยง</option>n>
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

<table class="sort-eiei" border="1">
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
            </th>
            <th>ผลัด</th>
            <th>หมายเลขบัญชี</th>
            <th onclick="sortTable(8)">
                จำนวนเงินทั้งหมด
                <span class="sort-icon"></span>
            </th>
            <th>สถานะการตรวจสอบ</th>
            <th>ความเสี่ยง</th>
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
            }
        } else {
            echo "<tr><td colspan='10'>No results found</td></tr>";
        }
        ?>
    </tbody>
</table>

<div class="pagination-container">
    <div class="pagination-info">
        <span>หน้า <?php echo $current_page; ?> จาก <?php echo $total_pages; ?></span>
    </div>
    <div class="pagination-controls">
        <?php
        $query_string = $_SERVER['QUERY_STRING'];
        // ลบค่า 'page' และ 'limit' ออกจาก query string เดิม
        $query_string = preg_replace('/&?page=\d*/', '', $query_string);
        $query_string = preg_replace('/&?limit=\d*/', '', $query_string);
        if (!empty($query_string)) {
            $query_string = '?' . $query_string . '&';
        } else {
            $query_string = '?';
        }

        // แสดงปุ่ม "หน้าแรก"
        $first_page_link = "account.php" . $query_string . "page=1";
        $first_page_class = ($current_page == 1) ? 'disabled' : '';
        echo "<a href='$first_page_link' class='$first_page_class'>หน้าแรก</a>";

        // แสดงปุ่ม "ก่อนหน้า"
        $prev_page_link = "account.php" . $query_string . "page=" . ($current_page - 1);
        $prev_page_class = ($current_page == 1) ? 'disabled' : '';
        echo "<a href='$prev_page_link' class='$prev_page_class'>ก่อนหน้า</a>";

        // แสดงหน้าปัจจุบันและหน้าอื่นๆ รอบๆ
        $start_page = max(1, $current_page - 2);
        $end_page = min($total_pages, $current_page + 2);
        
        for ($i = $start_page; $i <= $end_page; $i++) {
            $page_link = "account.php" . $query_string . "page=$i";
            $page_class = ($i == $current_page) ? 'active' : '';
            echo "<a href='$page_link' class='$page_class'>$i</a>";
        }
        
        // แสดงปุ่ม "ถัดไป"
        $next_page_link = "account.php" . $query_string . "page=" . ($current_page + 1);
        $next_page_class = ($current_page >= $total_pages) ? 'disabled' : '';
        echo "<a href='$next_page_link' class='$next_page_class'>ถัดไป</a>";

        // แสดงปุ่ม "หน้าสุดท้าย"
        $last_page_link = "account.php" . $query_string . "page=" . $total_pages;
        $last_page_class = ($current_page >= $total_pages) ? 'disabled' : '';
        echo "<a href='$last_page_link' class='$last_page_class'>หน้าสุดท้าย</a>";

        ?>
    </div>
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