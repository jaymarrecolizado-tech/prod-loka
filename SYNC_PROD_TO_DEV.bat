@echo off
REM Sync PROD2PROD to PUBLIC_HTML (Development)
REM This script copies production code to development, keeping .env and configs

set PROD_DIR=C:\wamp64\www\Projects\loka2\prod2prod
set DEV_DIR=C:\wamp64\www\Projects\loka2\public_html

echo ======================================
echo Syncing PROD2PROD -^> PUBLIC_HTML
echo ======================================
echo.

echo Syncing classes...
xcopy "%PROD_DIR%\classes" "%DEV_DIR%\classes\" /E /Y /Q >nul
echo   [OK] classes/

echo Syncing config (keeping .env)...
xcopy "%PROD_DIR%\config\*.php" "%DEV_DIR%\config\" /Y /Q >nul
echo   [OK] config/

echo Syncing includes...
xcopy "%PROD_DIR%\includes" "%DEV_DIR%\includes\" /E /Y /Q >nul
echo   [OK] includes/

echo Syncing pages...
xcopy "%PROD_DIR%\pages" "%DEV_DIR%\pages\" /E /Y /Q >nul
echo   [OK] pages/

echo Syncing api...
xcopy "%PROD_DIR%\api" "%DEV_DIR%\api\" /E /Y /Q >nul
echo   [OK] api/

echo Syncing assets...
xcopy "%PROD_DIR%\assets" "%DEV_DIR%\assets\" /E /Y /Q >nul
echo   [OK] assets/

echo Syncing cron...
xcopy "%PROD_DIR%\cron" "%DEV_DIR%\cron\" /E /Y /Q >nul
echo   [OK] cron/

echo Syncing root files...
copy /Y "%PROD_DIR%\index.php" "%DEV_DIR%\index.php" >nul
copy /Y "%PROD_DIR%\migrate.php" "%DEV_DIR%\migrate.php" >nul
copy /Y "%PROD_DIR%\reset_admin_password.php" "%DEV_DIR%\reset_admin_password.php" >nul
echo   [OK] index.php, migrate.php, reset_admin_password.php

echo.
echo ======================================
echo Sync Complete!
echo ======================================
echo.
echo NOTE: Development .env file was NOT overwritten.
echo       Production .env settings are in: %PROD_DIR%\.env.production
echo.
echo Please test at: http://localhost/Projects/loka2/public_html/
echo.
pause
