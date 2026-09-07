@echo off
setlocal enabledelayedexpansion
echo ============================================
echo   StudentOS AI Quick Setup
echo ============================================
echo.

:: Detect XAMPP path
set "XAMPP_DIR="
if exist "D:\xampp\xampp_start.exe" (
    set "XAMPP_DIR=D:\xampp"
) else if exist "C:\xampp\xampp_start.exe" (
    set "XAMPP_DIR=C:\xampp"
)

:: Detect MySQL executable
set "MYSQL_CMD=mysql"
if defined XAMPP_DIR (
    if exist "!XAMPP_DIR!\mysql\bin\mysql.exe" (
        set "MYSQL_CMD=!XAMPP_DIR!\mysql\bin\mysql.exe"
    )
)

echo [1/6] Starting XAMPP services...
if defined XAMPP_DIR (
    call "!XAMPP_DIR!\xampp_start.exe"
    timeout /t 3 /nobreak >nul
    echo OK (Using !XAMPP_DIR!)
) else (
    echo Note: XAMPP root not auto-detected. Please ensure Apache and MySQL are running.
)

echo.
echo [2/6] Creating database...
"%MYSQL_CMD%" -u root -e "CREATE DATABASE IF NOT EXISTS studentos_ai CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
if errorlevel 1 (
    echo Warning: Could not execute database creation. Ensure MySQL is running on port 3306.
) else (
    echo OK
)

echo.
echo [3/6] Importing schema...
"%MYSQL_CMD%" -u root studentos_ai < database\schema.sql
if errorlevel 1 (
    echo Warning: Could not import schema.sql.
) else (
    echo OK
)

echo.
echo [4/6] Importing seed data...
"%MYSQL_CMD%" -u root studentos_ai < database\seed.sql
if errorlevel 1 (
    echo Warning: Could not import seed.sql.
) else (
    echo OK
)

echo.
echo [5/6] Creating storage and logs directories...
if not exist "storage\uploads" mkdir "storage\uploads"
if not exist "storage\documents" mkdir "storage\documents"
if not exist "storage\assignments" mkdir "storage\assignments"
if not exist "storage\profile_photos" mkdir "storage\profile_photos"
if not exist "storage\temp" mkdir "storage\temp"
if not exist "storage\backups" mkdir "storage\backups"
if not exist "storage\cache" mkdir "storage\cache"
if not exist "logs" mkdir "logs"
echo OK

echo.
echo [6/6] Setting permissions...
icacls storage /grant Everyone:F /T 2>nul
icacls logs /grant Everyone:F /T 2>nul
echo OK

echo.
echo ============================================
echo   Setup Complete!
echo ============================================
echo.
echo Access the application in your browser at:
echo   http://localhost/StudentOS-AI-project/frontend/login.php
echo.
echo Default Logins (from database\seed.sql):
echo   Super Admin: superadmin@studentos.ai / SuperAdmin@12345
echo   Admin:       admin@studentos.ai      / Admin@12345
echo   Faculty:     faculty@studentos.ai    / Faculty@12345
echo   Student:     student@studentos.ai    / Student@12345
echo.
pause