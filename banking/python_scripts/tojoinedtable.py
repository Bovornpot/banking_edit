import mysql.connector

# เชื่อมต่อกับ MySQL
conn = mysql.connector.connect(
    host='localhost',
    user='root',
    password='1234',
    database='counterservice'
)

cursor = conn.cursor()

query = """
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
WHERE t.payment_day BETWEEN '2025-03-24' AND '2025-04-07'; 
GROUP BY t.store_id, t.machine, cm.region, t.payment_day, t.payment_time, t.shiftwork, t.account_number, t.id_number, t.phone, cm.monitor_bank;
"""

#change date
# ถ้าเป็นวันเดียว เปลี่ยนเป็น WHERE t.payment_day = '2025-03-16'
try:
    cursor.execute(query)
    
    conn.commit()
    print("✅ Data inserted successfully!")

except mysql.connector.Error as err:
    print("❌Error:", err)
    conn.rollback()

finally:
    cursor.close()
    conn.close()