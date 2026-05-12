@echo off
echo ==========================================
echo  Starting Tally Automation Frontend
echo ==========================================
echo.
echo Starting React dev server on http://localhost:5173
echo.
cd /d "%~dp0frontend"
npm run dev
pause
