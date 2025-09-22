import mysql.connector
import pandas as pd  # นำเข้า pandas

# เชื่อมต่อ MySQL
conn = mysql.connector.connect(
    host='localhost',  # หรือ IP ของเซิร์ฟเวอร์ฐานข้อมูล
    user='root',       # ชื่อผู้ใช้งาน
    password='1234',   # รหัสผ่าน
    database='counterservice'  # ชื่อฐานข้อมูล
)

cursor = conn.cursor()

query = """
DELETE FROM joined_table
WHERE id NOT IN (
    SELECT * FROM (
        SELECT MIN(id)
        FROM joined_table
        WHERE payment_day BETWEEN '2025-03-24' AND '2025-04-07'
        GROUP BY store_id, machine, region, payment_day, payment_time, shiftwork, 
                 account_number, id_number, phone, amount, monitor_bank
    ) AS temp
)
AND payment_day BETWEEN '2025-03-24' AND '2025-04-07';
"""

try:
    cursor.execute(query)
    
    conn.commit()
    print("✅ Data update successfully!")

except mysql.connector.Error as err:
    print("❌Error:", err)
    conn.rollback()

finally:
    cursor.close()
    conn.close()