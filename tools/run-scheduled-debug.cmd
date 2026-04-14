@echo off
setlocal
cd /d "%~dp0.."
echo [DEBUG] Running scheduled transfers at %date% %time%
php bin\console app:transfers:run-scheduled --env=dev --no-interaction
echo.
echo Exit code: %errorlevel%
echo Press any key to close...
pause >nul
endlocal
