@echo off
REM LOKA Fleet Management System - Database Reset Script
REM This script will clear the local database and import data from the server

echo ========================================
echo LOKA Database Reset and Import
echo ========================================
echo.

set DB_NAME=lokaloka2
set DB_USER=root
set SQL_FILE="C:\wamp64\www\Projects\loka2\datafromonline\loka 2-27-26127_0_0_1.sql"

echo WARNING: This will DELETE all data in the local database!
echo.
echo Database: %DB_NAME%
echo SQL File: %SQL_FILE%
echo.

set /p confirm="Are you sure you want to continue? (yes/no): "

if /i not "%confirm%"=="yes" (
    echo Operation cancelled.
    pause
    exit /b 0
)

echo.
echo [1/3] Dropping and recreating database...
mysql -u %DB_USER% -e "DROP DATABASE IF EXISTS %DB_NAME%; CREATE DATABASE %DB_NAME% CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;"

if errorlevel 1 (
    echo ERROR: Failed to recreate database!
    pause
    exit /b 1
)

echo OK - Database recreated.
echo.
echo [2/3] Importing data from server...
mysql -u %DB_USER% %DB_NAME% < %SQL_FILE%

if errorlevel 1 (
    echo ERROR: Failed to import data!
    pause
    exit /b 1
)

echo OK - Data imported.
echo.
echo [3/3] Verifying import...
mysql -u %DB_USER% -e "SELECT COUNT(*) as user_count FROM %DB_NAME%.users;"

echo.
echo ========================================
echo Database reset completed successfully!
echo ========================================
echo.
pause
