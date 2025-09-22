import mysql.connector
import pandas as pd
from datetime import datetime

# Connect to the database
try:
    conn = mysql.connector.connect(
        host='localhost', 
        user='root',     
        password='1234',   
        database='counterservice'  
    )
    if not conn.is_connected():
        raise Exception("Database connection failed.")
    
    cursor = conn.cursor(dictionary=True)
    
    print("Database connection successful.")
    
    # Define a complex SQL query to fetch and group data
    sql_query = """
    SELECT 
        ANY_VALUE(store_id) AS store_id,
        ANY_VALUE(branch_count) AS branch_count,
        ANY_VALUE(deposit_frequency) AS deposit_frequency,
        ANY_VALUE(machine) AS machine,
        ANY_VALUE(region) AS region,
        payment_day,
        ANY_VALUE(payment_time) AS payment_time,
        ANY_VALUE(shiftwork) AS shiftwork,
        account_number,
        total_amount_per_account_day,
        monitor_bank,
        risk_levels
    FROM resultsforuse
    WHERE 
        (
            (
                monitor_bank = 'Unchecked' AND (
                    (consecutive_days >= 3 AND total_amount_per_account_day >= 90000) OR
                    (transaction_count_per_day_id >= 7 AND total_amount_per_day_id >= 70000) OR
                    (transaction_count_per_day_phone >= 7 AND total_amount_per_day_phone >= 70000)
                )
            ) 
            OR monitor_bank IN ('Staff Deposit Abnormal', 'Fraudulent account')
            OR (risk_levels = 'High' AND monitor_bank != 'Deposit Normal')
        )
    GROUP BY 
        payment_day, account_number, total_amount_per_account_day, monitor_bank, risk_levels
    ORDER BY 
        payment_day DESC, risk_levels DESC;
    """
    
    # Execute the query and fetch data into a pandas DataFrame
    print("Executing complex SQL query...")
    cursor.execute(sql_query)
    data = cursor.fetchall()
    df = pd.DataFrame(data)
    
    # Check if DataFrame is empty
    if df.empty:
        print("No data found to preprocess.")
        cursor.close()
        conn.close()
    else:
        print("Data fetched successfully. Preprocessing...")
        
        # Clear the existing data from the preprocessed_results table
        cursor.execute("TRUNCATE TABLE preprocessed_results;")
        
        print("Truncated table 'preprocessed_results' to prepare for new data.")
        
        # Insert data from DataFrame into the new table
        insert_query = """
        INSERT INTO preprocessed_results (
            store_id, branch_count, deposit_frequency, machine, region, payment_day, 
            payment_time, shiftwork, account_number, total_amount_per_account_day, 
            monitor_bank, risk_levels
        ) VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
        """
        
        # Prepare data for insertion
        data_to_insert = []
        for index, row in df.iterrows():
            try:
                # Explicitly cast branch_count to integer, handling potential non-numeric values
                branch_count_val = int(row['branch_count']) if pd.notnull(row['branch_count']) else 0
            except (ValueError, TypeError):
                # If conversion fails, default to 0 and print a warning
                print(f"Warning: Could not convert 'branch_count' value '{row['branch_count']}' for row {index}. Defaulting to 0.")
                branch_count_val = 0
            
            # Explicitly cast deposit_frequency to integer, handling potential non-numeric values
            try:
                deposit_frequency_val = int(row['deposit_frequency']) if pd.notnull(row['deposit_frequency']) else 0
            except (ValueError, TypeError):
                print(f"Warning: Could not convert 'deposit_frequency' value '{row['deposit_frequency']}' for row {index}. Defaulting to 0.")
                deposit_frequency_val = 0
            
            # Prepare the rest of the row, truncating string data to fit column size
            row_tuple = (
                str(row['store_id'])[:255] if pd.notnull(row['store_id']) else None,
                branch_count_val,
                deposit_frequency_val,
                str(row['machine'])[:255] if pd.notnull(row['machine']) else None,
                str(row['region'])[:255] if pd.notnull(row['region']) else None,
                row['payment_day'],
                row['payment_time'],
                str(row['shiftwork'])[:255] if pd.notnull(row['shiftwork']) else None,
                str(row['account_number'])[:255] if pd.notnull(row['account_number']) else None,
                row['total_amount_per_account_day'],
                str(row['monitor_bank'])[:255] if pd.notnull(row['monitor_bank']) else None,
                str(row['risk_levels'])[:255] if pd.notnull(row['risk_levels']) else None
            )
            data_to_insert.append(row_tuple)

        print(f"Inserting {len(data_to_insert)} rows into 'preprocessed_results'...")
        cursor.executemany(insert_query, data_to_insert)
        conn.commit()
        print("Data preprocessing and insertion completed successfully.")

except mysql.connector.Error as err:
    print(f"Error: {err}")
    print("Failed to preprocess data. Please check your database connection and credentials.")
except Exception as e:
    print(f"An unexpected error occurred: {e}")
finally:
    if 'cursor' in locals() and cursor:
        cursor.close()
    if 'conn' in locals() and conn and conn.is_connected():
        conn.close()
        print("Database connection closed.")