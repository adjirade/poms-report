@echo off
REM ========================================
REM POMS Report - Setup Verification Script
REM ========================================

echo.
echo ========================================
echo   POMS Report - System Check
echo ========================================
echo.

REM Check PHP
echo [1/7] Checking PHP...
where php >nul 2>&1
if %errorlevel% neq 0 (
    echo    [X] PHP not found in PATH
    echo    Please install PHP 8.3+ from https://windows.php.net/download/
    set ERRORS=1
) else (
    php --version | findstr /C:"PHP"
    echo    [OK] PHP is installed
)

REM Check Composer
echo.
echo [2/7] Checking Composer...
where composer >nul 2>&1
if %errorlevel% neq 0 (
    echo    [X] Composer not found in PATH
    echo    Please install Composer from https://getcomposer.org/
    set ERRORS=1
) else (
    composer --version | findstr /C:"Composer"
    echo    [OK] Composer is installed
)

REM Check Node.js
echo.
echo [3/7] Checking Node.js...
where node >nul 2>&1
if %errorlevel% neq 0 (
    echo    [X] Node.js not found in PATH
    echo    Please install Node.js from https://nodejs.org/
    set ERRORS=1
) else (
    node --version
    echo    [OK] Node.js is installed
)

REM Check PostgreSQL
echo.
echo [4/7] Checking PostgreSQL...
where psql >nul 2>&1
if %errorlevel% neq 0 (
    echo    [X] PostgreSQL not found in PATH
    echo    Please install PostgreSQL 16 from https://www.postgresql.org/download/
    set ERRORS=1
) else (
    psql --version
    echo    [OK] PostgreSQL is installed
)

REM Check Redis
echo.
echo [5/7] Checking Redis...
where redis-cli >nul 2>&1
if %errorlevel% neq 0 (
    echo    [X] Redis not found in PATH
    echo    Please install Redis (Memurai for Windows or WSL)
    set ERRORS=1
) else (
    redis-cli --version
    echo    [OK] Redis CLI is installed
)

REM Check .env file
echo.
echo [6/7] Checking .env configuration...
if not exist ".env" (
    echo    [X] .env file not found
    echo    Run: copy .env.example .env
    set ERRORS=1
) else (
    echo    [OK] .env file exists
)

REM Check vendor directory
echo.
echo [7/7] Checking dependencies...
if not exist "vendor" (
    echo    [X] Vendor directory not found
    echo    Run: composer install
    set ERRORS=1
) else (
    echo    [OK] Composer dependencies installed
)

if not exist "node_modules" (
    echo    [X] Node modules not found
    echo    Run: npm install
    set ERRORS=1
) else (
    echo    [OK] NPM dependencies installed
)

echo.
echo ========================================

if defined ERRORS (
    echo   [!] Setup is INCOMPLETE
    echo   Please fix the errors above
    echo.
    echo   See SETUP_GUIDE.md for detailed instructions
    exit /b 1
) else (
    echo   [√] All prerequisites are installed!
    echo.
    echo   Next steps:
    echo   1. Configure .env file (database, Redis, Telegram)
    echo   2. Run: php artisan migrate
    echo   3. Run: php artisan db:seed --class=ValidationRulesSeeder
    echo   4. Create first user with php artisan tinker
    echo.
    echo   Then run the system:
    echo   - Terminal 1: php artisan serve
    echo   - Terminal 2: php artisan queue:work redis --queue=telegram
    echo   - Terminal 3: php artisan telegram:poll
)

echo ========================================
pause
