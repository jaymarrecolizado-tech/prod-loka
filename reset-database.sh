#!/bin/bash
# LOKA Fleet Management System - Database Reset Script
# This script will clear the local database and import data from the server

echo "========================================"
echo "LOKA Database Reset and Import"
echo "========================================"
echo

DB_NAME="lokaloka2"
DB_USER="root"
SQL_FILE="C:/wamp64/www/Projects/loka2/datafromonline/loka 2-27-26127_0_0_1.sql"

# For production on Linux, use:
# SQL_FILE="./datafromonline/loka-2-27-26.sql"

echo "WARNING: This will DELETE all data in the local database!"
echo
echo "Database: $DB_NAME"
echo "SQL File: $SQL_FILE"
echo

read -p "Are you sure you want to continue? (yes/no): " confirm

if [ "$confirm" != "yes" ]; then
    echo "Operation cancelled."
    exit 0
fi

echo
echo "[1/3] Dropping and recreating database..."
mysql -u "$DB_USER" -e "DROP DATABASE IF EXISTS $DB_NAME; CREATE DATABASE $DB_NAME CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;"

if [ $? -ne 0 ]; then
    echo "ERROR: Failed to recreate database!"
    exit 1
fi

echo "OK - Database recreated."
echo
echo "[2/3] Importing data from server..."
mysql -u "$DB_USER" "$DB_NAME" < "$SQL_FILE"

if [ $? -ne 0 ]; then
    echo "ERROR: Failed to import data!"
    exit 1
fi

echo "OK - Data imported."
echo
echo "[3/3] Verifying import..."
mysql -u "$DB_USER" -e "SELECT COUNT(*) as user_count FROM $DB_NAME.users;"

echo
echo "========================================"
echo "Database reset completed successfully!"
echo "========================================"
echo
