import mysql.connector

# เชื่อมต่อกับ MySQL
conn = mysql.connector.connect(
    host='localhost',
    user='root',
    password='1234',
    database='counterservice'
)

cursor = conn.cursor()

#change date
query = """
UPDATE joined_table jt
JOIN storere s ON jt.store_id = s.store_id
SET jt.region = s.region
where payment_day = '2025-04-05';
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