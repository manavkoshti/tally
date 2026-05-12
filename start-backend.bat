@echo off
echo ==========================================
echo  Starting Tally Automation Backend
echo ==========================================
echo.
echo [1] Starting Laravel server on http://localhost:8000
echo [2] Starting Queue Worker
echo.
cd /d "%~dp0backend"

start cmd /k "php artisan serve --host=localhost --port=8000"
timeout /t 2 /nobreak >nul
start cmd /k "php artisan queue:work --tries=3 --timeout=120"

echo Backend started!
echo API URL: http://localhost:8000/api/v1
echo.
pause
