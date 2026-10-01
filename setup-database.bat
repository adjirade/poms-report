@echo off
echo ========================================
echo   POMS Report - Database Setup Helper
echo ========================================
echo.
echo PostgreSQL requires password authentication.
echo.
echo Common PostgreSQL passwords for Laragon:
echo   1. (empty/blank)
echo   2. root
echo   3. postgres
echo   4. password
echo.
echo Please try each password when prompted.
echo.
pause
echo.
echo ========================================
echo Testing PostgreSQL Connection...
echo ========================================
echo.

REM Try to find PostgreSQL in Laragon
set PSQL_PATH=
if exist "C:\laragon\bin\postgres\postgres-16\bin\psql.exe" set PSQL_PATH=C:\laragon\bin\postgres\postgres-16\bin\psql.exe
if exist "C:\laragon\bin\postgres\postgres-15\bin\psql.exe" set PSQL_PATH=C:\laragon\bin\postgres\postgres-15\bin\psql.exe
if exist "C:\laragon\bin\postgres\postgres-14\bin\psql.exe" set PSQL_PATH=C:\laragon\bin\postgres\postgres-14\bin\psql.exe

if "%PSQL_PATH%"=="" (
    echo PostgreSQL not found in Laragon bin directory.
    echo.
    echo Please install PostgreSQL via Laragon:
    echo   1. Right-click Laragon icon
    echo   2. Tools ^> Quick add ^> PostgreSQL
    echo.
    pause
    exit /b 1
)

echo Found PostgreSQL: %PSQL_PATH%
echo.
echo ========================================
echo Creating Database: poms_report
echo ========================================
echo.
echo Enter PostgreSQL password when prompted.
echo (If no password, just press ENTER)
echo.

"%PSQL_PATH%" -U postgres -c "CREATE DATABASE poms_report;"

if %errorlevel% equ 0 (
    echo.
    echo ========================================
    echo   SUCCESS! Database created.
    echo ========================================
    echo.
    echo Now update .env file with correct password.
    echo.
) else (
    echo.
    echo ========================================
    echo   Connection failed!
    echo ========================================
    echo.
    echo Please check:
    echo   1. PostgreSQL service is running in Laragon
    echo   2. Correct password
    echo.
    echo To start PostgreSQL:
    echo   Right-click Laragon ^> PostgreSQL ^> Start
    echo.
)

pause
