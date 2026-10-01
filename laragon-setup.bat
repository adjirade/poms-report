@echo off
REM ========================================
REM POMS Report - Laragon Auto Setup
REM ========================================

echo.
echo ========================================
echo   POMS Report - Laragon Setup
echo ========================================
echo.

REM Check if Laragon is installed
if not exist "C:\laragon" (
    echo [ERROR] Laragon not found in C:\laragon
    echo Please install Laragon first from https://laragon.org
    pause
    exit /b 1
)

echo [1/10] Detecting Laragon PHP...
set PHP_PATH=C:\laragon\bin\php\php-8.3.13\php.exe
if not exist "%PHP_PATH%" (
    set PHP_PATH=C:\laragon\bin\php\php-8.3.12\php.exe
)
if not exist "%PHP_PATH%" (
    set PHP_PATH=C:\laragon\bin\php\php-8.3.11\php.exe
)
if not exist "%PHP_PATH%" (
    for /d %%i in (C:\laragon\bin\php\php-8.*) do set PHP_PATH=%%i\php.exe
)

if not exist "%PHP_PATH%" (
    echo [ERROR] PHP not found in Laragon
    echo Please start Laragon and ensure PHP is installed
    pause
    exit /b 1
)

echo    Found: %PHP_PATH%

REM Check Composer
echo.
echo [2/10] Detecting Composer...
set COMPOSER_PATH=C:\laragon\bin\composer\composer.phar
if not exist "%COMPOSER_PATH%" (
    echo [ERROR] Composer not found in Laragon
    echo Please install Composer via Laragon
    pause
    exit /b 1
)
echo    Found: %COMPOSER_PATH%

REM Check PostgreSQL
echo.
echo [3/10] Checking PostgreSQL...
set PSQL_PATH=C:\laragon\bin\postgres\postgres-16\bin\psql.exe
if not exist "%PSQL_PATH%" (
    set PSQL_PATH=C:\laragon\bin\postgres\postgres-15\bin\psql.exe
)
if not exist "%PSQL_PATH%" (
    for /d %%i in (C:\laragon\bin\postgres\postgres-*) do set PSQL_PATH=%%i\bin\psql.exe
)

if not exist "%PSQL_PATH%" (
    echo [WARNING] PostgreSQL not found in Laragon
    echo You need to add PostgreSQL via Laragon Menu
    echo Right-click Laragon > Tools > Quick add > PostgreSQL
    pause
)

REM Check Redis
echo.
echo [4/10] Checking Redis...
set REDIS_PATH=C:\laragon\bin\redis\redis-server.exe
if not exist "%REDIS_PATH%" (
    echo [WARNING] Redis not found in Laragon
    echo Installing Redis is recommended
    echo Right-click Laragon > Tools > Quick add > Redis
)

REM Create .env file
echo.
echo [5/10] Creating .env configuration...
if not exist ".env" (
    copy .env.example .env >nul
    echo    Created .env file
) else (
    echo    .env already exists, skipping
)

REM Install Composer dependencies
echo.
echo [6/10] Installing Composer dependencies...
echo    This may take 2-3 minutes...
"%PHP_PATH%" "%COMPOSER_PATH%" install --no-interaction --prefer-dist
if %errorlevel% neq 0 (
    echo [ERROR] Composer install failed
    pause
    exit /b 1
)
echo    [OK] Composer dependencies installed

REM Install NPM dependencies
echo.
echo [7/10] Installing NPM dependencies...
echo    This may take 2-3 minutes...
call npm install --silent
if %errorlevel% neq 0 (
    echo [ERROR] NPM install failed
    pause
    exit /b 1
)
echo    [OK] NPM dependencies installed

REM Generate app key
echo.
echo [8/10] Generating application key...
"%PHP_PATH%" artisan key:generate --force
echo    [OK] Application key generated

REM Create storage directories
echo.
echo [9/10] Setting up storage directories...
if not exist "storage\logs" mkdir storage\logs
if not exist "storage\framework\cache" mkdir storage\framework\cache
if not exist "storage\framework\sessions" mkdir storage\framework\sessions
if not exist "storage\framework\views" mkdir storage\framework\views
if not exist "bootstrap\cache" mkdir bootstrap\cache
echo    [OK] Storage directories created

REM Build frontend assets
echo.
echo [10/10] Building frontend assets...
call npm run build
echo    [OK] Assets built

echo.
echo ========================================
echo   Setup Complete!
echo ========================================
echo.
echo Next steps:
echo.
echo 1. Configure your .env file:
echo    - Set DB_PASSWORD (default: root or empty)
echo    - Set TELEGRAM_BOT_TOKEN (get from @BotFather)
echo.
echo 2. Create database:
echo    Open Laragon Terminal and run:
echo    psql -U postgres -c "CREATE DATABASE poms_report;"
echo.
echo 3. Run migrations:
echo    php artisan migrate
echo    php artisan db:seed --class=ValidationRulesSeeder
echo.
echo 4. Create first user:
echo    php artisan tinker
echo    (Then create user as shown in SETUP_GUIDE.md)
echo.
echo 5. Start the system (3 terminals):
echo    Terminal 1: php artisan serve
echo    Terminal 2: php artisan queue:work redis --queue=telegram
echo    Terminal 3: php artisan telegram:poll
echo.
echo See SETUP_GUIDE.md for detailed instructions
echo ========================================

pause
