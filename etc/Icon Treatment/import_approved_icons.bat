@echo off
setlocal EnableExtensions EnableDelayedExpansion
py -3 "%~dp0import_approved_icons.py" %*
set "EXIT_CODE=!ERRORLEVEL!"
echo.
pause
exit /b %EXIT_CODE%
