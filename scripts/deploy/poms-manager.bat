@echo off
setlocal EnableExtensions
REM ================================================================
REM  POMS Service Manager - launcher auto-elevate
REM  Double-click -> UAC sekali -> GUI terbuka sebagai Administrator
REM  (tanpa jendela console, memakai pythonw.exe)
REM ================================================================

REM --- cari pythonw.exe: hasil 'where python' pertama yang bukan
REM     WindowsApps stub dan punya pythonw.exe di folder yang sama
set "PYW="
for /f "delims=" %%i in ('where python 2^>nul') do (
    if not defined PYW (
        echo %%i | findstr /i /c:"WindowsApps" >nul
        if errorlevel 1 if exist "%%~dpipythonw.exe" set "PYW=%%~dpipythonw.exe"
    )
)
REM fallback: via py launcher
if not defined PYW (
    for /f "delims=" %%i in ('py -c "import sys; print(sys.executable)" 2^>nul') do (
        if not defined PYW if exist "%%~dpipythonw.exe" set "PYW=%%~dpipythonw.exe"
    )
)
if not defined PYW set "PYW=pythonw.exe"

REM --- mode uji tanpa menjalankan GUI/UAC: set POMS_DRYRUN=1
if defined POMS_DRYRUN (
    echo DRYRUN PYW=%PYW%
    echo DRYRUN SCRIPT=%~dp0poms-manager.py
    net session >nul 2>&1
    if %errorlevel% equ 0 (echo DRYRUN TOKEN=elevated) else (echo DRYRUN TOKEN=non-elevated^>UAC)
    exit /b 0
)

REM --- token belum elevated? -> relaunch bat ini sendiri via UAC
net session >nul 2>&1
if %errorlevel% neq 0 (
    echo Meminta hak Administrator - klik "Ya" pada dialog UAC...
    powershell -NoProfile -Command "Start-Process -FilePath '%~f0' -Verb RunAs"
    exit /b 0
)

REM --- sudah elevated: jalankan GUI (pythonw mewarisi token admin)
cd /d "%~dp0"
start "" "%PYW%" "%~dp0poms-manager.py"
exit /b 0
