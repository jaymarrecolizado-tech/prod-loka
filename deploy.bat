@echo off
REM =============================================================================
REM LOKA Fleet Management - Windows Deployment Script
REM Creates a production deployment package
REM =============================================================================

setlocal enabledelayedexpansion

echo === LOKA Fleet Management - Deployment Package Creator ===
echo.

REM Configuration
set "SOURCE_DIR=public_html"
set "OUTPUT_DIR=deploy-package"
set "TIMESTAMP=%date:~10,4%%date:~4,2%%date:~7,2%_%time:~0,2%%time:~3,2%%time:~6,2%"
set "TIMESTAMP=%TIMESTAMP: =0%"
set "PACKAGE_NAME=loka-deploy-%TIMESTAMP%"

echo Creating deployment package: %PACKAGE_NAME%
echo.

REM Check if source directory exists
if not exist "%SOURCE_DIR%\" (
    echo ERROR: Source directory '%SOURCE_DIR%' not found!
    pause
    exit /b 1
)

REM Create output directory
if exist "%OUTPUT_DIR%" rmdir /s /q "%OUTPUT_DIR%"
mkdir "%OUTPUT_DIR%"
mkdir "%OUTPUT_DIR%\%PACKAGE_NAME%"

echo Copying files...
echo.

REM Use robocopy to copy files excluding unwanted patterns
robocopy "%SOURCE_DIR%" "%OUTPUT_DIR%\%PACKAGE_NAME%" /E /NFL /NDL /NJH /NJS ^
    /XD ".git" "tests" "ref" "docs" "vendor\bin" "logs\sessions" ^
    /XF "*.log" ".env" ".env.local" ".env.development" "*.md" "diagnostic.php" "import_users.php" "db-test.php" "setup_*.php" "migrate.php" "welcome.php" "AUTH_FIX*.md" "EMAIL_QUEUE*.md" "GLM*.md" "NOTIFICATION*.md" "VERIFICATION*.md" "DEPLOYMENT_*.md" ".gitignore" ^
    /XD "cache\data" > nul

REM Create cache data directory (empty)
mkdir "%OUTPUT_DIR%\%PACKAGE_NAME%\cache\data" 2>nul

REM Copy .env.production as template
copy "%SOURCE_DIR%\.env.production" "%OUTPUT_DIR%\%PACKAGE_NAME%\.env.production" > nul

echo Verifying critical files...
echo.

set "ALL_OK=1"

REM Check critical files
set "FILES=index.php config\bootstrap.php config\database.php classes\Database.php classes\Auth.php classes\Security.php classes\Cache.php .htaccess.production run-migrations.php composer.json composer.lock"

for %%f in (%FILES%) do (
    if not exist "%OUTPUT_DIR%\%PACKAGE_NAME%\%%f" (
        echo   [MISSING] %%f
        set "ALL_OK=0"
    ) else (
        echo   [OK] %%f
    )
)

echo.
echo.

if "%ALL_OK%"=="0" (
    echo ERROR: Some critical files are missing!
    pause
    exit /b 1
)

echo Creating ZIP archive...
echo.

REM Check if 7-Zip is available
where 7z >nul 2>nul
if %errorlevel%==0 (
    7z a -tzip "%OUTPUT_DIR%\%PACKAGE_NAME%.zip" "%OUTPUT_DIR%\%PACKAGE_NAME%\*" -mx5 > nul
    echo Package created: %OUTPUT_DIR%\%PACKAGE_NAME%.zip
) else (
    echo WARNING: 7-Zip not found.
    echo Package folder created at: %OUTPUT_DIR%\%PACKAGE_NAME%\
    echo Please manually compress this folder to ZIP.
)

echo.
echo === Deployment Package Ready ===
echo.
echo Next steps:
echo 1. Copy .env.production to .env and update with production credentials
echo 2. Upload the package to your server
echo 3. Extract files in the web root
echo 4. Run: php run-migrations.php
echo 5. Set file permissions (see DEPLOYMENT_GUIDE.md)
echo 6. Visit: https://lokafleet.dictr2.online/verify-production.php
echo.
echo For detailed instructions, see: DEPLOYMENT_GUIDE.md
echo.

pause
