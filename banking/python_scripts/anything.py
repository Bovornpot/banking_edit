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
SELECT SUM(amount) AS total_amount
FROM resultsforuse
WHERE payment_day = '2025-04-06'
  AND (
    (
      monitor_bank = 'Unchecked' AND (
        (consecutive_days >= 3 AND total_amount_per_account_day >= 90000) OR
        (transaction_count_per_day_id >= 7 AND total_amount_per_day_id >= 70000) OR
        (transaction_count_per_day_phone >= 7 AND total_amount_per_day_phone >= 70000)
      )
    ) 
    OR monitor_bank IN ('Staff Deposit Abnormal', 'Fraudulent account')
    OR (risk_levels = 'High' AND monitor_bank != 'Deposit Normal')
  );
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