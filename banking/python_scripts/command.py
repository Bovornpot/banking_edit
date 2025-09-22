import mysql.connector
import pandas as pd

# 📌 เชื่อมต่อ MySQL
conn = mysql.connector.connect(
    host="localhost",
    user="root",
    password="1234",
    database="counterservice"
)
cursor = conn.cursor()

# เอาไฟล์ไว้ใน folder cpallproject > database
# 📌 โหลดไฟล์ CSV
csv_file = "C:/Users/benrueyakam/Documents/cpallproject/database/finalcom.csv"
df = pd.read_csv(csv_file)

# แปลงค่าโทรศัพท์เป็นตัวเลข (int) และแทนค่า null ด้วย NaN
df[['account_phone2', 'account_phone3']] = df[['account_phone2', 'account_phone3']].apply(pd.to_numeric, errors='coerce')

# ฟังก์ชันจัดหมวดข้อมูล
def categorize_phones(row):
    phones = [row['account_phone2'], row['account_phone3']]
    valid_phones = [p for p in phones if pd.notna(p)]  # ลบ NaN ออกจากลิสต์

    # แยกเป็น additional_account หรือ additional_phone
    high_values = [int(p) for p in valid_phones if p >= 10]  # แปลงเป็น int ก่อนเปรียบเทียบ
    
    if len(high_values) == 2:
        return pd.Series([None, high_values[0], high_values[1]])  # มี 2 ตัว → additional_account, additional_account2
    elif len(high_values) == 1:
        return pd.Series([None, high_values[0], None])  # มี 1 ตัว → additional_account
    elif len(valid_phones) > 0:
        return pd.Series([int(valid_phones[0]), None, None])  # ถ้าไม่ถึง 10 → additional_phone
    else:
        return pd.Series([None, None, None])  # ไม่มีข้อมูลเลย

# ใช้ apply() กับ dataframe
df[['additional_phone', 'additional_account', 'additional_account2']] = df.apply(
    lambda row: categorize_phones(row), axis=1
)

# **🔹 แก้ไขให้ None ถูกส่งเข้า MySQL แทน NaN**
for _, row in df.iterrows():
    sql = """
    INSERT IGNORE INTO commandaccount (store_id, region, payment_day, topicof_work, monitor_bank, account_number, 
                                       additional_phone, additional_account, additional_account2)
    VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s)
    """
    values = (
        row['store_id'], 
        row['region'], 
        row['payment_day'], 
        row['topicof_work'], 
        row['monitor_bank'], 
        row['account_number'],
        None if pd.isna(row['additional_phone']) else row['additional_phone'],  
        None if pd.isna(row['additional_account']) else row['additional_account'],
        None if pd.isna(row['additional_account2']) else row['additional_account2']
    )
    cursor.execute(sql, values)


# บันทึกข้อมูล
conn.commit()

# ปิดการเชื่อมต่อ
cursor.close()
conn.close()

print("✅ Import CSV เข้าฐานข้อมูล MySQL สำเร็จ!")