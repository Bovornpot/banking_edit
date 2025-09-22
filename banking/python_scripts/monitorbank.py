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
UPDATE joined_table 
SET monitor_bank = 'Unchecked' 
WHERE monitor_bank IS NULL;
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