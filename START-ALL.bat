@echo off
echo ==========================================
echo   TALLY AUTOMATION SYSTEM - STARTUP
echo ==========================================
echo.
echo STEP 1: Make sure XAMPP MySQL is running!
echo STEP 2: Make sure Tally Prime is running (for sync)
echo.
echo Starting all services...
echo.

REM Start Backend + Queue
start "Laravel Backend" cmd /k "cd /d "%~dp0backend" && php artisan serve --host=localhost --port=8000"
timeout /t 3 /nobreak >nul
start "Queue Worker" cmd /k "cd /d "%~dp0backend" && php artisan queue:work --tries=3"

timeout /t 2 /nobreak >nul

REM Start Frontend
start "React Frontend" cmd /k "cd /d "%~dp0frontend" && npm run dev"

echo.
echo ==========================================
echo  All services started!
echo ==========================================
echo.
echo  Frontend:  http://localhost:5173
echo  Backend:   http://localhost:8000
echo  API Base:  http://localhost:8000/api/v1
echo.
echo  Login: admin@demo.com / password
echo.
echo Press any key to exit this window...
pause >nul
