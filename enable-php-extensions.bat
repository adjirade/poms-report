@echo off
REM Enable PHP Extensions for Laragon

echo Enabling required PHP extensions for POMS Report...
echo.

set PHP_INI=C:\laragon\bin\php\php-8.3.33-Win32-vs16-x64\php.ini

if not exist "%PHP_INI%" (
    echo Error: php.ini not found at %PHP_INI%
    echo Please check your Laragon PHP installation
    pause
    exit /b 1
)

echo Found php.ini at: %PHP_INI%
echo.
echo Backing up php.ini...
copy "%PHP_INI%" "%PHP_INI%.backup" >nul

echo Enabling extensions...

REM Enable required extensions
powershell -Command "(Get-Content '%PHP_INI%') -replace '^;extension=zip', 'extension=zip' | Set-Content '%PHP_INI%'"
powershell -Command "(Get-Content '%PHP_INI%') -replace '^;extension=pdo_pgsql', 'extension=pdo_pgsql' | Set-Content '%PHP_INI%'"
powershell -Command "(Get-Content '%PHP_INI%') -replace '^;extension=pgsql', 'extension=pgsql' | Set-Content '%PHP_INI%'"
powershell -Command "(Get-Content '%PHP_INI%') -replace '^;extension=mbstring', 'extension=mbstring' | Set-Content '%PHP_INI%'"
powershell -Command "(Get-Content '%PHP_INI%') -replace '^;extension=fileinfo', 'extension=fileinfo' | Set-Content '%PHP_INI%'"
powershell -Command "(Get-Content '%PHP_INI%') -replace '^;extension=openssl', 'extension=openssl' | Set-Content '%PHP_INI%'"
powershell -Command "(Get-Content '%PHP_INI%') -replace '^;extension=curl', 'extension=curl' | Set-Content '%PHP_INI%'"
powershell -Command "(Get-Content '%PHP_INI%') -replace '^;extension=gd', 'extension=gd' | Set-Content '%PHP_INI%'"

echo.
echo [OK] Extensions enabled:
echo   - zip
echo   - pdo_pgsql
echo   - pgsql
echo   - mbstring
echo   - fileinfo
echo   - openssl
echo   - curl
echo   - gd
echo.
echo Backup saved at: %PHP_INI%.backup
echo.
echo Please restart Laragon for changes to take effect!
echo.
pause
