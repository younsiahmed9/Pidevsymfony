@echo off
setlocal
cd /d "%~dp0.."
if not exist var\log mkdir var\log
echo [%date% %time%] START app:transfers:run-scheduled >> var\log\scheduled-transfers.log
php bin\console app:transfers:run-scheduled --env=prod --no-interaction >> var\log\scheduled-transfers.log 2>&1
set "EXIT_CODE=%ERRORLEVEL%"
echo [%date% %time%] END code=%EXIT_CODE% >> var\log\scheduled-transfers.log
endlocal
