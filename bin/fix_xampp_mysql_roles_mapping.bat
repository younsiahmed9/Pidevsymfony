@echo off
chcp 65001 >nul
echo ============================================
echo Reparation mysql.roles_mapping (XAMPP MariaDB)
echo Ferme le panneau XAMPP ou arrete MySQL avant.
echo ============================================
pause

taskkill /F /IM mysqld.exe 2>nul
timeout /t 3 /nobreak >nul

echo Demarrage temporaire avec --skip-grant-tables ...
start /B "" "C:\xampp\mysql\bin\mysqld.exe" --defaults-file=C:\xampp\mysql\bin\my.ini --skip-grant-tables
timeout /t 8 /nobreak >nul

echo REPAIR TABLE mysql.roles_mapping ...
"C:\xampp\mysql\bin\mysql.exe" -u root -e "REPAIR TABLE mysql.roles_mapping;"
if errorlevel 1 (
  echo Echec REPAIR. Essayez aussi :
  echo   mysqlcheck -u root --repair mysql
  pause
  taskkill /F /IM mysqld.exe 2>nul
  exit /b 1
)

echo Arret du serveur temporaire...
taskkill /F /IM mysqld.exe 2>nul
timeout /t 3 /nobreak >nul

echo OK. Relance MySQL depuis XAMPP Control Panel ^(sans skip-grant-tables^).
pause
