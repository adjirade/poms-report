@echo off
REM ============================================================
REM POMS - Instalasi 3 layanan Windows via NSSM (butuh admin).
REM   poms-queue    : queue worker (telegram,default)
REM   poms-schedule : scheduler (rekap harian 17:30, mingguan Senin 07:00)
REM   poms-poll     : bot Telegram long-polling
REM Catatan: APP memakai junction D:\poms-app (tanpa spasi) agar
REM AppParameters NSSM tidak terpotong; set eksplisit setelah install
REM (install saja di-skip bila service sudah ada).
REM ============================================================
setlocal
set NSSM=%~dp0nssm.exe
set PHP=C:\Users\Adjira\AppData\Local\Programs\PHP\current\php.exe
set APP=D:\poms-app
set LOGS=%APP%\storage\logs

if not exist "%NSSM%" ( echo ERROR: nssm.exe tidak ditemukan di %~dp0 & exit /b 1 )
if not exist "%PHP%" ( echo ERROR: php.exe tidak ditemukan di %PHP% & exit /b 1 )
if not exist "%APP%\artisan" ( echo ERROR: junction %APP% tidak ada - jalankan: mklink /J D:\poms-app "D:\Project\Sawit APP\SawitApp" & exit /b 1 )

echo === Instalasi layanan POMS (NSSM) ===

REM ---- 1. Queue worker ----
"%NSSM%" stop poms-queue >nul 2>&1
"%NSSM%" install poms-queue "%PHP%" >nul 2>&1
"%NSSM%" set poms-queue Application "%PHP%"
"%NSSM%" set poms-queue AppDirectory "%APP%"
"%NSSM%" set poms-queue AppParameters %APP%\artisan queue:work --queue=telegram,default --tries=3 --timeout=90 --sleep=1
"%NSSM%" set poms-queue DisplayName "POMS Queue Worker"
"%NSSM%" set poms-queue Description "Queue worker Telegram POMS"
"%NSSM%" set poms-queue AppStdout "%LOGS%\service-queue.log"
"%NSSM%" set poms-queue AppStderr "%LOGS%\service-queue.log"
"%NSSM%" set poms-queue AppRotateFiles 1
"%NSSM%" set poms-queue AppRotateOnline 1
"%NSSM%" set poms-queue AppExit Default Restart
"%NSSM%" restart poms-queue >nul 2>&1 || "%NSSM%" start poms-queue

REM ---- 2. Scheduler ----
"%NSSM%" stop poms-schedule >nul 2>&1
"%NSSM%" install poms-schedule "%PHP%" >nul 2>&1
"%NSSM%" set poms-schedule Application "%PHP%"
"%NSSM%" set poms-schedule AppDirectory "%APP%"
"%NSSM%" set poms-schedule AppParameters %APP%\artisan schedule:work
"%NSSM%" set poms-schedule DisplayName "POMS Scheduler"
"%NSSM%" set poms-schedule Description "Scheduler Laravel POMS: rekap harian 17:30 + mingguan Senin 07:00 WIB"
"%NSSM%" set poms-schedule AppStdout "%LOGS%\service-schedule.log"
"%NSSM%" set poms-schedule AppStderr "%LOGS%\service-schedule.log"
"%NSSM%" set poms-schedule AppRotateFiles 1
"%NSSM%" set poms-schedule AppRotateOnline 1
"%NSSM%" set poms-schedule AppExit Default Restart
"%NSSM%" restart poms-schedule >nul 2>&1 || "%NSSM%" start poms-schedule

REM ---- 3. Poller bot Telegram ----
"%NSSM%" stop poms-poll >nul 2>&1
"%NSSM%" install poms-poll "%PHP%" >nul 2>&1
"%NSSM%" set poms-poll Application "%PHP%"
"%NSSM%" set poms-poll AppDirectory "%APP%"
"%NSSM%" set poms-poll AppParameters %APP%\artisan telegram:poll
"%NSSM%" set poms-poll DisplayName "POMS Telegram Poller"
"%NSSM%" set poms-poll Description "Bot Telegram POMS @POMS_1_bot (long polling)"
"%NSSM%" set poms-poll AppStdout "%LOGS%\service-poll.log"
"%NSSM%" set poms-poll AppStderr "%LOGS%\service-poll.log"
"%NSSM%" set poms-poll AppRotateFiles 1
"%NSSM%" set poms-poll AppRotateOnline 1
"%NSSM%" set poms-poll AppExit Default Restart
"%NSSM%" restart poms-poll >nul 2>&1 || "%NSSM%" start poms-poll

REM ---- 4. Web server (artisan serve, LAN) ----
"%NSSM%" stop poms-web >nul 2>&1
"%NSSM%" install poms-web "%PHP%" >nul 2>&1
"%NSSM%" set poms-web Application "%PHP%"
"%NSSM%" set poms-web AppDirectory "%APP%"
"%NSSM%" set poms-web AppParameters %APP%\artisan serve --host=0.0.0.0 --port=8000
"%NSSM%" set poms-web DisplayName "POMS Web Server"
"%NSSM%" set poms-web Description "HTTP server POMS (localhost + LAN, port 8000)"
"%NSSM%" set poms-web AppStdout "%LOGS%\service-web.log"
"%NSSM%" set poms-web AppStderr "%LOGS%\service-web.log"
"%NSSM%" set poms-web AppRotateFiles 1
"%NSSM%" set poms-web AppRotateOnline 1
"%NSSM%" set poms-web AppExit Default Restart
"%NSSM%" restart poms-web >nul 2>&1 || "%NSSM%" start poms-web

echo.
echo === Parameter tersimpan (poms-poll) ===
reg query "HKLM\SYSTEM\CurrentControlSet\Services\poms-poll\Parameters" /v AppParameters
reg query "HKLM\SYSTEM\CurrentControlSet\Services\poms-poll\Parameters" /v AppDirectory
echo === Status layanan ===
sc query poms-queue | find "STATE"
sc query poms-schedule | find "STATE"
sc query poms-poll | find "STATE"
sc query poms-web | find "STATE"
endlocal
