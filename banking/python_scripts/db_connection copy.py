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
DELETE j1
FROM joined_table j1
JOIN joined_table j2
    ON j1.store_id = j2.store_id
    AND j1.machine = j2.machine
    AND j1.region = j2.region
    AND j1.payment_day = j2.payment_day
    AND j1.payment_time = j2.payment_time
    AND j1.shiftwork = j2.shiftwork
    AND j1.account_number = j2.account_number
    AND j1.id_number = j2.id_number
    AND j1.phone = j2.phone
    AND j1.amount = j2.amount
WHERE j1.monitor_bank = 'Deposit Normal Incomplete'
  AND j2.monitor_bank = 'Deposit Normal'
  AND j1.payment_day BETWEEN '2025-03-24' AND '2025-04-07';
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