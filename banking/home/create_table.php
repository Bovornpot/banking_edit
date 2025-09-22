<?php
// PHP script to create the preprocessed_results table
$conn = new mysqli('localhost', 'root', '1234', 'counterservice');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Drop the existing table to create a new one with the correct primary key
$sql_drop_table = "DROP TABLE IF EXISTS preprocessed_results;";
if ($conn->query($sql_drop_table) === TRUE) {
    echo "Existing table 'preprocessed_results' dropped successfully.<br>";
} else {
    echo "Error dropping table: " . $conn->error;
}

$sql_create_table = "
CREATE TABLE preprocessed_results (
    id INT(11) PRIMARY KEY AUTO_INCREMENT,
    store_id VARCHAR(255),
    branch_count INT,
    deposit_frequency INT,
    machine VARCHAR(255),
    region VARCHAR(255),
    payment_day DATE,
    payment_time TIME,
    shiftwork VARCHAR(255),
    account_number VARCHAR(255),
    total_amount_per_account_day DECIMAL(15, 2),
    monitor_bank VARCHAR(255),
    risk_levels VARCHAR(255),
    INDEX idx_performance (monitor_bank, risk_levels, payment_day, account_number, total_amount_per_account_day),
    INDEX idx_account_payment (account_number, payment_day)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

if ($conn->query($sql_create_table) === TRUE) {
    echo "Table 'preprocessed_results' created successfully.";
} else {
    echo "Error creating table: " . $conn->error;
}

$conn->close();
?>