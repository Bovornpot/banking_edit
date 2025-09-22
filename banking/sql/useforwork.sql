SET GLOBAL local_infile = 1;

##เปลี่ยนตามชื่อไฟล์
LOAD DATA LOCAL INFILE '"D:\benrueyakam\Documents\cpallproject\mysql_data\bank_31mar.csv"'
INTO TABLE transactions
FIELDS TERMINATED BY ','
ENCLOSED BY '"'
LINES TERMINATED BY '\r\n'
IGNORE 1 LINES;

##for check can change date
SELECT * FROM transactions
WHERE payment_day = '2025-04-05';

## inset but lost connection next to vscode (tojointable)
INSERT INTO joined_table (store_id, machine, region, payment_day, payment_time, shiftwork, account_number, id_number, phone, amount, monitor_bank)
SELECT DISTINCT
    t.store_id, 
    t.machine,
    cm.region,
    t.payment_day, 
    t.payment_time, 
    t.shiftwork, 
    t.account_number, 
    t.id_number, 
    t.phone,  
    t.amount,  
    cm.monitor_bank  
FROM transactions t
LEFT JOIN commandaccount cm 
    ON t.account_number = cm.account_number 
    OR t.account_number = cm.additional_account 
    OR t.account_number = cm.additional_account2
WHERE t.payment_day = '2025-03-16';

##show joined_table for check 
select * from joined_table
where payment_day = '2025-04-05';

##join region with storere vscode (region)
UPDATE joined_table jt
JOIN storere s ON jt.store_id = s.store_id
SET jt.region = s.region;

## check when its error 
SHOW PROCESSLIST;
kill 41;

##show joined_table for check 
select * from joined_table
where payment_day = '2025-04-05';

##for region is null check
select * from joined_table
where payment_day = '2025-04-05' and region is null;

##if region have null
SELECT DISTINCT store_id
FROM joined_table
WHERE payment_day = '2025-04-07' AND region IS NULL;

##if region have null check
select * from joined_table
where store_id = '22911';

##if region have null, insert into storere
INSERT INTO storere (store_id, region)
VALUES
(22928, 'RC'),
(22957, 'NEL'),
(22921, 'NEU'),
(22925, 'BG'),
(22917, 'RC');

##if region have null, update can use vscode (region) 
##join region with storere vscode (region)
UPDATE joined_table jt
JOIN storere s ON jt.store_id = s.store_id
SET jt.region = s.region
where jt.region is null;

##check store_id the you wanted after update region null
select * from joined_table
where store_id = '22911';

##really change  vscode
UPDATE joined_table 
SET monitor_bank = 'Unchecked' 
WHERE monitor_bank IS NULL;

##check monitor_bank 
select * from joined_table
where payment_day = '2025-04-05';


##Next vscode Model
##realorfake use newdata_final
##use final when you want train model

##check
select * from resultsforuse
where payment_day = '2025-03-27'
ORDER BY payment_day ASC, payment_time ASC;

##check
select * from resultsforuse
where payment_day = '2025-03-27' and risk_levels = 'Low'
ORDER BY payment_day ASC, payment_time ASC;

##check
select * from resultsforuse
where risk_levels = 'high'
ORDER BY payment_day ASC, payment_time ASC;
