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

# เอาไฟล์ไว้ใน folder cpallproject > database
# โหลดไฟล์ CSV
df = pd.read_csv('C:/Users/benrueyakam/Documents/cpallproject/database/allcomcom.csv')  # เปลี่ยนชื่อไฟล์ตามที่ใช้งานจริง

# สร้างคำสั่ง SQL
insert_query = """
INSERT IGNORE INTO commandaccount (monitor_bank, account_number)
VALUES (%s, %s)
"""


# แปลงข้อมูลใน DataFrame เป็น list of tuples
data = list(df[['monitor_bank', 'account_number']].itertuples(index=False, name=None))

# Insert ข้อมูลทั้งหมด
cursor.executemany(insert_query, data)
conn.commit()

print(f"Inserted {cursor.rowcount} rows.")

# ปิดการเชื่อมต่อ
cursor.close()
conn.close()
